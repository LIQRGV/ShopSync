package hub

import (
	"fmt"
	"sync"
	"testing"
	"time"
)

// waitFor polls a condition function until it returns true or the
// timeout elapses. Returns true if the condition was met.
func waitFor(t *testing.T, timeout time.Duration, cond func() bool) bool {
	t.Helper()
	deadline := time.Now().Add(timeout)
	for time.Now().Before(deadline) {
		if cond() {
			return true
		}
		time.Sleep(5 * time.Millisecond)
	}
	return false
}

// startHub creates a new Hub, starts Run() in a goroutine, and returns
// the hub. The caller is responsible for calling h.Stop().
func startHub(t *testing.T) *Hub {
	t.Helper()
	h := NewHub()
	go h.Run()
	return h
}

func TestNewClient(t *testing.T) {
	c := NewClient("test-123")

	if c.ID != "test-123" {
		t.Errorf("expected ID %q, got %q", "test-123", c.ID)
	}
	if cap(c.Send) != sendBufferSize {
		t.Errorf("expected Send capacity %d, got %d", sendBufferSize, cap(c.Send))
	}
	if c.ConsecutiveDrops != 0 {
		t.Errorf("expected ConsecutiveDrops 0, got %d", c.ConsecutiveDrops)
	}

	// Done channel should be open (not closed).
	select {
	case <-c.Done:
		t.Error("Done channel should be open for a new client")
	default:
	}
}

func TestRegisterClient(t *testing.T) {
	h := startHub(t)
	defer h.Stop()

	c := NewClient("c1")
	h.Register(c)

	if !waitFor(t, time.Second, func() bool { return h.ClientCount() == 1 }) {
		t.Fatalf("expected clientCount 1, got %d", h.ClientCount())
	}
}

func TestUnregisterClient(t *testing.T) {
	h := startHub(t)
	defer h.Stop()

	c := NewClient("c1")
	h.Register(c)
	waitFor(t, time.Second, func() bool { return h.ClientCount() == 1 })

	h.Unregister(c)

	if !waitFor(t, time.Second, func() bool { return h.ClientCount() == 0 }) {
		t.Fatalf("expected clientCount 0 after unregister, got %d", h.ClientCount())
	}

	// Send channel should be closed after unregister.
	_, open := <-c.Send
	if open {
		t.Error("expected Send channel to be closed after unregister")
	}
}

func TestUnregisterIdempotent(t *testing.T) {
	h := startHub(t)
	defer h.Stop()

	c := NewClient("c1")
	h.Register(c)
	waitFor(t, time.Second, func() bool { return h.ClientCount() == 1 })

	h.Unregister(c)
	waitFor(t, time.Second, func() bool { return h.ClientCount() == 0 })

	// Second unregister should not panic or change the count.
	h.Unregister(c)
	// Give the run loop time to process.
	time.Sleep(50 * time.Millisecond)

	if h.ClientCount() != 0 {
		t.Fatalf("expected clientCount 0 after double unregister, got %d", h.ClientCount())
	}
}

func TestBroadcastDeliversToAllClients(t *testing.T) {
	h := startHub(t)
	defer h.Stop()

	const numClients = 5
	clients := make([]*Client, numClients)
	for i := 0; i < numClients; i++ {
		clients[i] = NewClient(fmt.Sprintf("c%d", i))
		h.Register(clients[i])
	}
	waitFor(t, time.Second, func() bool { return h.ClientCount() == numClients })

	msg := []byte("hello everyone")
	h.Broadcast(msg)

	for i, c := range clients {
		select {
		case got := <-c.Send:
			if string(got) != string(msg) {
				t.Errorf("client %d: expected %q, got %q", i, msg, got)
			}
		case <-time.After(time.Second):
			t.Errorf("client %d: timed out waiting for message", i)
		}
	}
}

func TestUnregisteredClientDoesNotReceiveBroadcast(t *testing.T) {
	h := startHub(t)
	defer h.Stop()

	c1 := NewClient("c1")
	c2 := NewClient("c2")
	h.Register(c1)
	h.Register(c2)
	waitFor(t, time.Second, func() bool { return h.ClientCount() == 2 })

	h.Unregister(c2)
	waitFor(t, time.Second, func() bool { return h.ClientCount() == 1 })

	msg := []byte("only for c1")
	h.Broadcast(msg)

	// c1 should receive the message.
	select {
	case got := <-c1.Send:
		if string(got) != string(msg) {
			t.Errorf("c1: expected %q, got %q", msg, got)
		}
	case <-time.After(time.Second):
		t.Error("c1: timed out waiting for message")
	}

	// c2's Send channel is closed, so any read returns zero-value immediately.
	// It should not receive the broadcast message.
	select {
	case val, open := <-c2.Send:
		if open {
			t.Errorf("c2: unexpectedly received message %q after unregister", val)
		}
		// Channel closed -- expected.
	default:
		// Nothing available -- also acceptable.
	}
}

func TestSlowClientDetection(t *testing.T) {
	h := startHub(t)
	defer h.Stop()

	c := NewClient("slow")
	h.Register(c)
	waitFor(t, time.Second, func() bool { return h.ClientCount() == 1 })

	// Fill the client's Send channel completely.
	for i := 0; i < sendBufferSize; i++ {
		c.Send <- []byte("fill")
	}

	// Now broadcast 3 messages. Each should fail the non-blocking send
	// and increment ConsecutiveDrops. After the 3rd, the client should
	// be force-disconnected.
	for i := 0; i < maxConsecutiveDrops; i++ {
		h.Broadcast([]byte(fmt.Sprintf("drop-%d", i)))
	}

	// Wait for force-disconnect: Done channel should be closed.
	if !waitFor(t, time.Second, func() bool {
		select {
		case <-c.Done:
			return true
		default:
			return false
		}
	}) {
		t.Fatal("expected client Done channel to be closed after 3 consecutive drops")
	}

	// Client should be removed from the hub.
	if !waitFor(t, time.Second, func() bool { return h.ClientCount() == 0 }) {
		t.Fatalf("expected clientCount 0 after force-disconnect, got %d", h.ClientCount())
	}
}

func TestConsecutiveDropsResetOnSuccessfulSend(t *testing.T) {
	h := startHub(t)
	defer h.Stop()

	c := NewClient("intermittent")
	h.Register(c)
	waitFor(t, time.Second, func() bool { return h.ClientCount() == 1 })

	// Fill the Send channel.
	for i := 0; i < sendBufferSize; i++ {
		c.Send <- []byte("fill")
	}

	// Drop 2 messages (below threshold).
	for i := 0; i < maxConsecutiveDrops-1; i++ {
		h.Broadcast([]byte("drop"))
	}

	// Give the run loop time to process.
	time.Sleep(50 * time.Millisecond)

	// Drain the Send channel so the next broadcast succeeds.
	for len(c.Send) > 0 {
		<-c.Send
	}

	// This broadcast should succeed and reset ConsecutiveDrops to 0.
	h.Broadcast([]byte("success"))

	select {
	case got := <-c.Send:
		if string(got) != "success" {
			t.Errorf("expected %q, got %q", "success", got)
		}
	case <-time.After(time.Second):
		t.Fatal("timed out waiting for successful broadcast")
	}

	// Client should still be registered.
	if h.ClientCount() != 1 {
		t.Fatalf("expected clientCount 1, got %d", h.ClientCount())
	}

	// Now fill again and drop maxConsecutiveDrops times -- the counter
	// should start from 0, not from the previous drops.
	for i := 0; i < sendBufferSize; i++ {
		c.Send <- []byte("fill2")
	}

	for i := 0; i < maxConsecutiveDrops; i++ {
		h.Broadcast([]byte("drop-again"))
	}

	if !waitFor(t, time.Second, func() bool {
		select {
		case <-c.Done:
			return true
		default:
			return false
		}
	}) {
		t.Fatal("expected client to be force-disconnected after 3 fresh consecutive drops")
	}
}

func TestClientCountAccuracy(t *testing.T) {
	h := startHub(t)
	defer h.Stop()

	if h.ClientCount() != 0 {
		t.Fatalf("expected 0 clients initially, got %d", h.ClientCount())
	}

	clients := make([]*Client, 10)
	for i := 0; i < 10; i++ {
		clients[i] = NewClient(fmt.Sprintf("c%d", i))
		h.Register(clients[i])
	}
	waitFor(t, time.Second, func() bool { return h.ClientCount() == 10 })

	if h.ClientCount() != 10 {
		t.Fatalf("expected 10 clients, got %d", h.ClientCount())
	}

	// Unregister 5 of them.
	for i := 0; i < 5; i++ {
		h.Unregister(clients[i])
	}
	waitFor(t, time.Second, func() bool { return h.ClientCount() == 5 })

	if h.ClientCount() != 5 {
		t.Fatalf("expected 5 clients after unregistering 5, got %d", h.ClientCount())
	}
}

func TestStopClosesAllClientSendChannels(t *testing.T) {
	h := startHub(t)

	const numClients = 5
	clients := make([]*Client, numClients)
	for i := 0; i < numClients; i++ {
		clients[i] = NewClient(fmt.Sprintf("c%d", i))
		h.Register(clients[i])
	}
	waitFor(t, time.Second, func() bool { return h.ClientCount() == int64(numClients) })

	h.Stop()

	// After Stop(), Run() should close all Send channels.
	for i, c := range clients {
		if !waitFor(t, time.Second, func() bool {
			_, open := <-c.Send
			return !open
		}) {
			t.Errorf("client %d: Send channel not closed after Stop()", i)
		}
	}
}

func TestConcurrentRegisterUnregister(t *testing.T) {
	h := startHub(t)
	defer h.Stop()

	const goroutines = 50
	var wg sync.WaitGroup
	wg.Add(goroutines)

	clients := make([]*Client, goroutines)
	for i := 0; i < goroutines; i++ {
		clients[i] = NewClient(fmt.Sprintf("c%d", i))
	}

	// Register all clients concurrently.
	for i := 0; i < goroutines; i++ {
		go func(idx int) {
			defer wg.Done()
			h.Register(clients[idx])
		}(i)
	}
	wg.Wait()

	if !waitFor(t, 2*time.Second, func() bool { return h.ClientCount() == goroutines }) {
		t.Fatalf("expected %d clients after concurrent register, got %d", goroutines, h.ClientCount())
	}

	// Unregister all clients concurrently.
	wg.Add(goroutines)
	for i := 0; i < goroutines; i++ {
		go func(idx int) {
			defer wg.Done()
			h.Unregister(clients[idx])
		}(i)
	}
	wg.Wait()

	if !waitFor(t, 2*time.Second, func() bool { return h.ClientCount() == 0 }) {
		t.Fatalf("expected 0 clients after concurrent unregister, got %d", h.ClientCount())
	}
}

func TestBroadcastMultipleMessages(t *testing.T) {
	h := startHub(t)
	defer h.Stop()

	c := NewClient("c1")
	h.Register(c)
	waitFor(t, time.Second, func() bool { return h.ClientCount() == 1 })

	// Send fewer messages than the Send buffer capacity so all of them
	// fit without requiring concurrent draining. This tests that
	// multiple broadcasts are delivered in order.
	const numMessages = sendBufferSize
	for i := 0; i < numMessages; i++ {
		h.Broadcast([]byte(fmt.Sprintf("msg-%d", i)))
	}

	// Give the run loop time to process all broadcasts.
	if !waitFor(t, 2*time.Second, func() bool { return len(c.Send) == numMessages }) {
		t.Fatalf("expected %d messages in Send, got %d", numMessages, len(c.Send))
	}

	for i := 0; i < numMessages; i++ {
		select {
		case got := <-c.Send:
			expected := fmt.Sprintf("msg-%d", i)
			if string(got) != expected {
				t.Errorf("message %d: expected %q, got %q", i, expected, got)
			}
		case <-time.After(2 * time.Second):
			t.Fatalf("timed out waiting for message %d", i)
		}
	}
}

func TestStopIsIdempotentWithEmptyHub(t *testing.T) {
	h := startHub(t)

	// Stopping a hub with no clients should not panic.
	h.Stop()

	// Give Run() time to exit.
	time.Sleep(50 * time.Millisecond)
}

func TestNewHub(t *testing.T) {
	h := NewHub()

	if h.clients == nil {
		t.Error("clients map should be initialized")
	}
	if cap(h.broadcast) != 256 {
		t.Errorf("expected broadcast capacity 256, got %d", cap(h.broadcast))
	}
	if cap(h.register) != 0 {
		t.Errorf("expected register to be unbuffered, got capacity %d", cap(h.register))
	}
	if cap(h.unregister) != 0 {
		t.Errorf("expected unregister to be unbuffered, got capacity %d", cap(h.unregister))
	}
	if h.ClientCount() != 0 {
		t.Errorf("expected initial clientCount 0, got %d", h.ClientCount())
	}
}
