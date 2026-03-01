package stream

import (
	"context"
	"encoding/json"
	"errors"
	"sync"
	"testing"
	"time"

	"github.com/redis/go-redis/v9"
)

// ---------------------------------------------------------------------------
// Mock StreamReader
// ---------------------------------------------------------------------------

type mockStreamReader struct {
	mu sync.Mutex

	// Stubbed return values / side-effects.
	createGroupErr  error
	readGroupResult []redis.XStream
	readGroupErr    error
	ackErr          error
	trimErr         error
	infoGroupsResult []redis.XInfoGroup
	infoGroupsErr   error
	destroyGroupErr error

	// Call tracking.
	createGroupCalls  []createGroupCall
	readGroupCalls    int
	ackCalls          []ackCall
	trimCalls         int
	infoGroupsCalls   int
	destroyGroupCalls []string // group names

	// Optional: callback invoked on each ReadGroup call so the test
	// can return different results on successive invocations.
	readGroupFunc func(call int) ([]redis.XStream, error)
}

type createGroupCall struct {
	stream, group, start string
}

type ackCall struct {
	stream, group string
	ids           []string
}

func (m *mockStreamReader) CreateGroup(_ context.Context, stream, group, start string) error {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.createGroupCalls = append(m.createGroupCalls, createGroupCall{stream, group, start})
	return m.createGroupErr
}

func (m *mockStreamReader) ReadGroup(_ context.Context, group, consumer string, streams []string, count int64, block time.Duration) ([]redis.XStream, error) {
	m.mu.Lock()
	defer m.mu.Unlock()
	call := m.readGroupCalls
	m.readGroupCalls++
	if m.readGroupFunc != nil {
		return m.readGroupFunc(call)
	}
	return m.readGroupResult, m.readGroupErr
}

func (m *mockStreamReader) Ack(_ context.Context, stream, group string, ids ...string) error {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.ackCalls = append(m.ackCalls, ackCall{stream, group, ids})
	return m.ackErr
}

func (m *mockStreamReader) TrimApprox(_ context.Context, stream string, maxLen int64) error {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.trimCalls++
	return m.trimErr
}

func (m *mockStreamReader) InfoGroups(_ context.Context, stream string) ([]redis.XInfoGroup, error) {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.infoGroupsCalls++
	return m.infoGroupsResult, m.infoGroupsErr
}

func (m *mockStreamReader) DestroyGroup(_ context.Context, stream, group string) error {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.destroyGroupCalls = append(m.destroyGroupCalls, group)
	return m.destroyGroupErr
}

// ---------------------------------------------------------------------------
// Mock BroadcastTarget
// ---------------------------------------------------------------------------

type mockBroadcast struct {
	mu       sync.Mutex
	messages []string
}

func (b *mockBroadcast) Broadcast(msg []byte) {
	b.mu.Lock()
	defer b.mu.Unlock()
	b.messages = append(b.messages, string(msg))
}

func (b *mockBroadcast) getMessages() []string {
	b.mu.Lock()
	defer b.mu.Unlock()
	out := make([]string, len(b.messages))
	copy(out, b.messages)
	return out
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

func TestNewRedisReader(t *testing.T) {
	t.Parallel()

	sr := &mockStreamReader{}
	bc := &mockBroadcast{}
	r := NewRedisReader(sr, "test_prefix_", bc)

	if r.streamKey != "test_prefix_products.updates.stream" {
		t.Errorf("unexpected streamKey: %s", r.streamKey)
	}
	if r.groupName != "sse-go-reader" {
		t.Errorf("unexpected groupName: %s", r.groupName)
	}
	if r.consumerName != "consumer-1" {
		t.Errorf("unexpected consumerName: %s", r.consumerName)
	}
}

func TestRun_CreatesConsumerGroupOnStart(t *testing.T) {
	t.Parallel()

	sr := &mockStreamReader{}

	// ReadGroup returns redis.Nil (no messages) so the loop keeps
	// spinning, and the test can cancel after a short time.
	sr.readGroupErr = redis.Nil

	bc := &mockBroadcast{}
	r := NewRedisReader(sr, "pfx_", bc)

	ctx, cancel := context.WithTimeout(context.Background(), 100*time.Millisecond)
	defer cancel()

	r.Run(ctx)

	sr.mu.Lock()
	defer sr.mu.Unlock()

	if len(sr.createGroupCalls) == 0 {
		t.Fatal("expected CreateGroup to be called")
	}
	call := sr.createGroupCalls[0]
	if call.stream != "pfx_products.updates.stream" {
		t.Errorf("CreateGroup stream = %q, want %q", call.stream, "pfx_products.updates.stream")
	}
	if call.group != "sse-go-reader" {
		t.Errorf("CreateGroup group = %q, want %q", call.group, "sse-go-reader")
	}
	if call.start != "$" {
		t.Errorf("CreateGroup start = %q, want %q", call.start, "$")
	}
}

func TestRun_IgnoresBusyGroupError(t *testing.T) {
	t.Parallel()

	sr := &mockStreamReader{
		createGroupErr: errors.New("BUSYGROUP Consumer Group name already exists"),
		readGroupErr:   redis.Nil,
	}
	bc := &mockBroadcast{}
	r := NewRedisReader(sr, "pfx_", bc)

	ctx, cancel := context.WithTimeout(context.Background(), 100*time.Millisecond)
	defer cancel()

	// Should not panic or stop -- BUSYGROUP is expected.
	r.Run(ctx)
}

func TestRun_ParsesAndBroadcastsMessages(t *testing.T) {
	t.Parallel()

	payload := map[string]interface{}{
		"event": "product.updated",
		"data": map[string]interface{}{
			"product_id": 42,
			"message":    "Product updated: Widget",
		},
		"timestamp": "2026-02-28T10:30:00.000Z",
	}
	payloadJSON, _ := json.Marshal(payload)

	callCount := 0
	sr := &mockStreamReader{
		readGroupFunc: func(call int) ([]redis.XStream, error) {
			callCount++
			if callCount == 1 {
				return []redis.XStream{
					{
						Stream: "pfx_products.updates.stream",
						Messages: []redis.XMessage{
							{
								ID: "1-0",
								Values: map[string]interface{}{
									"data": string(payloadJSON),
								},
							},
						},
					},
				}, nil
			}
			// Subsequent calls return no data.
			return nil, redis.Nil
		},
	}
	bc := &mockBroadcast{}
	r := NewRedisReader(sr, "pfx_", bc)

	ctx, cancel := context.WithTimeout(context.Background(), 200*time.Millisecond)
	defer cancel()

	r.Run(ctx)

	msgs := bc.getMessages()
	if len(msgs) == 0 {
		t.Fatal("expected at least one broadcast message")
	}

	expected := "event: product.updated\ndata: {\"message\":\"Product updated: Widget\",\"product_id\":42}\n\n"
	if msgs[0] != expected {
		t.Errorf("broadcast message:\ngot:  %q\nwant: %q", msgs[0], expected)
	}

	// Verify ACK was called.
	sr.mu.Lock()
	defer sr.mu.Unlock()
	if len(sr.ackCalls) == 0 {
		t.Fatal("expected Ack to be called")
	}
	if sr.ackCalls[0].ids[0] != "1-0" {
		t.Errorf("Ack id = %q, want %q", sr.ackCalls[0].ids[0], "1-0")
	}
}

func TestRun_CleansUpLegacyPHPGroups(t *testing.T) {
	t.Parallel()

	sr := &mockStreamReader{
		readGroupErr: redis.Nil,
		infoGroupsResult: []redis.XInfoGroup{
			{Name: "sse-go-reader"},
			{Name: "sse-group-abc123"},
			{Name: "sse-group-def456"},
			{Name: "some-other-group"},
		},
	}
	bc := &mockBroadcast{}
	r := NewRedisReader(sr, "pfx_", bc)

	ctx, cancel := context.WithTimeout(context.Background(), 100*time.Millisecond)
	defer cancel()

	r.Run(ctx)

	sr.mu.Lock()
	defer sr.mu.Unlock()

	if len(sr.destroyGroupCalls) != 2 {
		t.Fatalf("expected 2 DestroyGroup calls, got %d", len(sr.destroyGroupCalls))
	}

	destroyed := map[string]bool{}
	for _, name := range sr.destroyGroupCalls {
		destroyed[name] = true
	}
	if !destroyed["sse-group-abc123"] {
		t.Error("expected sse-group-abc123 to be destroyed")
	}
	if !destroyed["sse-group-def456"] {
		t.Error("expected sse-group-def456 to be destroyed")
	}
	if destroyed["sse-go-reader"] {
		t.Error("sse-go-reader should NOT be destroyed")
	}
	if destroyed["some-other-group"] {
		t.Error("some-other-group should NOT be destroyed")
	}
}

func TestRun_NOGROUPRecovery(t *testing.T) {
	t.Parallel()

	callCount := 0
	sr := &mockStreamReader{
		readGroupFunc: func(call int) ([]redis.XStream, error) {
			callCount++
			if callCount == 1 {
				return nil, errors.New("NOGROUP No such consumer group 'sse-go-reader'")
			}
			return nil, redis.Nil
		},
	}
	bc := &mockBroadcast{}
	r := NewRedisReader(sr, "pfx_", bc)

	ctx, cancel := context.WithTimeout(context.Background(), 200*time.Millisecond)
	defer cancel()

	r.Run(ctx)

	sr.mu.Lock()
	defer sr.mu.Unlock()

	// First call is the initial ensureGroup. Second call should be
	// the recovery after NOGROUP error.
	if len(sr.createGroupCalls) < 2 {
		t.Fatalf("expected at least 2 CreateGroup calls (initial + recovery), got %d", len(sr.createGroupCalls))
	}
}

func TestRun_GracefulShutdownOnContextCancel(t *testing.T) {
	t.Parallel()

	sr := &mockStreamReader{
		readGroupErr: redis.Nil,
	}
	bc := &mockBroadcast{}
	r := NewRedisReader(sr, "pfx_", bc)

	ctx, cancel := context.WithCancel(context.Background())

	done := make(chan struct{})
	go func() {
		r.Run(ctx)
		close(done)
	}()

	// Cancel and verify Run returns promptly.
	cancel()

	select {
	case <-done:
		// success
	case <-time.After(2 * time.Second):
		t.Fatal("Run did not return after context cancellation")
	}
}

func TestRun_SkipsMessagesWithoutDataField(t *testing.T) {
	t.Parallel()

	callCount := 0
	sr := &mockStreamReader{
		readGroupFunc: func(call int) ([]redis.XStream, error) {
			callCount++
			if callCount == 1 {
				return []redis.XStream{
					{
						Stream: "pfx_products.updates.stream",
						Messages: []redis.XMessage{
							{
								ID:     "1-0",
								Values: map[string]interface{}{"other_field": "value"},
							},
						},
					},
				}, nil
			}
			return nil, redis.Nil
		},
	}
	bc := &mockBroadcast{}
	r := NewRedisReader(sr, "pfx_", bc)

	ctx, cancel := context.WithTimeout(context.Background(), 200*time.Millisecond)
	defer cancel()

	r.Run(ctx)

	msgs := bc.getMessages()
	if len(msgs) != 0 {
		t.Errorf("expected no broadcast messages for message without 'data' field, got %d", len(msgs))
	}

	// Should still ACK the message.
	sr.mu.Lock()
	defer sr.mu.Unlock()
	if len(sr.ackCalls) == 0 {
		t.Error("expected message without 'data' field to still be ACKed")
	}
}

func TestRun_SkipsMessagesWithInvalidJSON(t *testing.T) {
	t.Parallel()

	callCount := 0
	sr := &mockStreamReader{
		readGroupFunc: func(call int) ([]redis.XStream, error) {
			callCount++
			if callCount == 1 {
				return []redis.XStream{
					{
						Stream: "pfx_products.updates.stream",
						Messages: []redis.XMessage{
							{
								ID:     "1-0",
								Values: map[string]interface{}{"data": "not valid json{{{"},
							},
						},
					},
				}, nil
			}
			return nil, redis.Nil
		},
	}
	bc := &mockBroadcast{}
	r := NewRedisReader(sr, "pfx_", bc)

	ctx, cancel := context.WithTimeout(context.Background(), 200*time.Millisecond)
	defer cancel()

	r.Run(ctx)

	msgs := bc.getMessages()
	if len(msgs) != 0 {
		t.Errorf("expected no broadcast for invalid JSON, got %d", len(msgs))
	}

	sr.mu.Lock()
	defer sr.mu.Unlock()
	if len(sr.ackCalls) == 0 {
		t.Error("expected invalid JSON message to still be ACKed")
	}
}

func TestFormatSSE(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name      string
		event     string
		data      string
		expected  string
	}{
		{
			name:     "product updated",
			event:    "product.updated",
			data:     `{"product_id":1}`,
			expected: "event: product.updated\ndata: {\"product_id\":1}\n\n",
		},
		{
			name:     "product created",
			event:    "product.created",
			data:     `{"product_id":2,"name":"Widget"}`,
			expected: "event: product.created\ndata: {\"product_id\":2,\"name\":\"Widget\"}\n\n",
		},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()
			got := formatSSE(tc.event, tc.data)
			if got != tc.expected {
				t.Errorf("formatSSE(%q, %q):\ngot:  %q\nwant: %q", tc.event, tc.data, got, tc.expected)
			}
		})
	}
}

func TestIsBusyGroupError(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name string
		err  error
		want bool
	}{
		{"nil error", nil, false},
		{"busygroup", errors.New("BUSYGROUP Consumer Group name already exists"), true},
		{"other error", errors.New("connection refused"), false},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()
			if got := isBusyGroupError(tc.err); got != tc.want {
				t.Errorf("isBusyGroupError(%v) = %v, want %v", tc.err, got, tc.want)
			}
		})
	}
}

func TestIsNoGroupError(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name string
		err  error
		want bool
	}{
		{"nil error", nil, false},
		{"nogroup", errors.New("NOGROUP No such consumer group"), true},
		{"other error", errors.New("timeout"), false},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()
			if got := isNoGroupError(tc.err); got != tc.want {
				t.Errorf("isNoGroupError(%v) = %v, want %v", tc.err, got, tc.want)
			}
		})
	}
}
