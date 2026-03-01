package stream

import (
	"context"
	"errors"
	"fmt"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"testing"
	"time"
)

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

// sseEvent is a collected event from the writer callback.
type sseEvent struct {
	Event string
	Data  string
}

// collectEvents returns a writer func that appends events to a
// thread-safe slice.
func collectEvents() (func(event, data string) error, func() []sseEvent) {
	var mu sync.Mutex
	var events []sseEvent

	writer := func(event, data string) error {
		mu.Lock()
		defer mu.Unlock()
		events = append(events, sseEvent{Event: event, Data: data})
		return nil
	}

	getter := func() []sseEvent {
		mu.Lock()
		defer mu.Unlock()
		out := make([]sseEvent, len(events))
		copy(out, events)
		return out
	}

	return writer, getter
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

func TestUpstreamRelay_ParsesAndForwardsEvents(t *testing.T) {
	t.Parallel()

	// Simulate an upstream SSE server that sends two events then closes.
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Accept") != "text/event-stream" {
			t.Errorf("expected Accept: text/event-stream, got %q", r.Header.Get("Accept"))
		}
		if r.Header.Get("Cache-Control") != "no-cache" {
			t.Errorf("expected Cache-Control: no-cache, got %q", r.Header.Get("Cache-Control"))
		}

		// Verify the token is passed as a query parameter.
		token := r.URL.Query().Get("token")
		if token != "test-token-123" {
			t.Errorf("expected token=test-token-123, got %q", token)
		}

		w.Header().Set("Content-Type", "text/event-stream")
		w.WriteHeader(http.StatusOK)

		flusher, ok := w.(http.Flusher)
		if !ok {
			t.Fatal("ResponseWriter does not support Flusher")
		}

		// Event 1
		fmt.Fprint(w, "event: product.updated\n")
		fmt.Fprint(w, "data: {\"product_id\":1}\n")
		fmt.Fprint(w, "\n")
		flusher.Flush()

		// Event 2 with an id line
		fmt.Fprint(w, "id: 42\n")
		fmt.Fprint(w, "event: product.created\n")
		fmt.Fprint(w, "data: {\"product_id\":2}\n")
		fmt.Fprint(w, "\n")
		flusher.Flush()
	}))
	defer server.Close()

	writer, getEvents := collectEvents()

	relay := NewUpstreamRelay(server.URL, "test-token-123", server.Client())

	err := relay.Relay(context.Background(), writer)
	// The server closes the connection, so we expect the "upstream closed"
	// error.
	if err == nil || !strings.Contains(err.Error(), "upstream closed connection") {
		t.Fatalf("expected upstream closed error, got: %v", err)
	}

	events := getEvents()
	if len(events) != 2 {
		t.Fatalf("expected 2 events, got %d", len(events))
	}

	if events[0].Event != "product.updated" {
		t.Errorf("event[0].Event = %q, want %q", events[0].Event, "product.updated")
	}
	if events[0].Data != `{"product_id":1}` {
		t.Errorf("event[0].Data = %q, want %q", events[0].Data, `{"product_id":1}`)
	}

	if events[1].Event != "product.created" {
		t.Errorf("event[1].Event = %q, want %q", events[1].Event, "product.created")
	}
	if events[1].Data != `{"product_id":2}` {
		t.Errorf("event[1].Data = %q, want %q", events[1].Data, `{"product_id":2}`)
	}
}

func TestUpstreamRelay_SkipsCommentLines(t *testing.T) {
	t.Parallel()

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/event-stream")
		w.WriteHeader(http.StatusOK)

		flusher, _ := w.(http.Flusher)

		// Comment line followed by a real event.
		fmt.Fprint(w, ": heartbeat 1709123456\n")
		fmt.Fprint(w, "\n")
		fmt.Fprint(w, "event: product.updated\n")
		fmt.Fprint(w, "data: {\"id\":1}\n")
		fmt.Fprint(w, "\n")
		flusher.Flush()
	}))
	defer server.Close()

	writer, getEvents := collectEvents()
	relay := NewUpstreamRelay(server.URL, "tok", server.Client())

	_ = relay.Relay(context.Background(), writer)

	events := getEvents()
	// The comment-only block (blank line after comment) produces an event
	// with empty event and empty data. We should still only get 1 real event
	// since the comment block has no event/data fields set.
	realEvents := []sseEvent{}
	for _, e := range events {
		if e.Event != "" || e.Data != "" {
			realEvents = append(realEvents, e)
		}
	}
	if len(realEvents) != 1 {
		t.Fatalf("expected 1 real event, got %d: %+v", len(realEvents), realEvents)
	}
	if realEvents[0].Event != "product.updated" {
		t.Errorf("event = %q, want %q", realEvents[0].Event, "product.updated")
	}
}

func TestUpstreamRelay_HandlesUpstreamDisconnect(t *testing.T) {
	t.Parallel()

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/event-stream")
		w.WriteHeader(http.StatusOK)

		flusher, _ := w.(http.Flusher)

		fmt.Fprint(w, "event: connected\n")
		fmt.Fprint(w, "data: {}\n")
		fmt.Fprint(w, "\n")
		flusher.Flush()

		// Close the connection immediately after one event
		// (the handler returns, closing the response body).
	}))
	defer server.Close()

	writer, getEvents := collectEvents()
	relay := NewUpstreamRelay(server.URL, "tok", server.Client())

	err := relay.Relay(context.Background(), writer)

	if err == nil {
		t.Fatal("expected error on upstream disconnect")
	}
	if !strings.Contains(err.Error(), "upstream closed connection") {
		t.Errorf("expected 'upstream closed connection' error, got: %v", err)
	}

	events := getEvents()
	if len(events) != 1 {
		t.Fatalf("expected 1 event before disconnect, got %d", len(events))
	}
}

func TestUpstreamRelay_ContextCancellationStopsRelay(t *testing.T) {
	t.Parallel()

	// Server that sends events indefinitely until the client disconnects.
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/event-stream")
		w.WriteHeader(http.StatusOK)

		flusher, _ := w.(http.Flusher)

		for i := 0; ; i++ {
			select {
			case <-r.Context().Done():
				return
			default:
			}
			fmt.Fprintf(w, "event: tick\ndata: %d\n\n", i)
			flusher.Flush()
			time.Sleep(10 * time.Millisecond)
		}
	}))
	defer server.Close()

	writer, _ := collectEvents()
	relay := NewUpstreamRelay(server.URL, "tok", server.Client())

	ctx, cancel := context.WithTimeout(context.Background(), 100*time.Millisecond)
	defer cancel()

	done := make(chan error, 1)
	go func() {
		done <- relay.Relay(ctx, writer)
	}()

	select {
	case err := <-done:
		if !errors.Is(err, context.DeadlineExceeded) {
			t.Errorf("expected context.DeadlineExceeded, got: %v", err)
		}
	case <-time.After(5 * time.Second):
		t.Fatal("Relay did not return after context cancellation")
	}
}

func TestUpstreamRelay_NonOKStatus(t *testing.T) {
	t.Parallel()

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusUnauthorized)
	}))
	defer server.Close()

	writer, _ := collectEvents()
	relay := NewUpstreamRelay(server.URL, "bad-token", server.Client())

	err := relay.Relay(context.Background(), writer)
	if err == nil {
		t.Fatal("expected error for 401 response")
	}
	if !strings.Contains(err.Error(), "unexpected status 401") {
		t.Errorf("expected 'unexpected status 401' error, got: %v", err)
	}
}

func TestUpstreamRelay_WriterErrorStopsRelay(t *testing.T) {
	t.Parallel()

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/event-stream")
		w.WriteHeader(http.StatusOK)

		flusher, _ := w.(http.Flusher)

		// Send two events; the writer will fail on the first.
		fmt.Fprint(w, "event: first\ndata: 1\n\n")
		flusher.Flush()
		fmt.Fprint(w, "event: second\ndata: 2\n\n")
		flusher.Flush()
	}))
	defer server.Close()

	writerErr := errors.New("client disconnected")
	writer := func(event, data string) error {
		return writerErr
	}

	relay := NewUpstreamRelay(server.URL, "tok", server.Client())
	err := relay.Relay(context.Background(), writer)

	if err == nil {
		t.Fatal("expected writer error to propagate")
	}
	if !strings.Contains(err.Error(), "writer error") {
		t.Errorf("expected 'writer error', got: %v", err)
	}
}

func TestUpstreamRelay_URLConstruction(t *testing.T) {
	t.Parallel()

	// Capture the actual URL the relay requests.
	var requestedURL string
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		requestedURL = r.URL.String()
		w.WriteHeader(http.StatusOK)
		w.Header().Set("Content-Type", "text/event-stream")
	}))
	defer server.Close()

	writer, _ := collectEvents()
	relay := NewUpstreamRelay(server.URL, "my-token", server.Client())
	_ = relay.Relay(context.Background(), writer)

	expected := "/sse/events?token=my-token"
	if requestedURL != expected {
		t.Errorf("URL = %q, want %q", requestedURL, expected)
	}
}

func TestUpstreamRelay_MultiLineData(t *testing.T) {
	t.Parallel()

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/event-stream")
		w.WriteHeader(http.StatusOK)

		flusher, _ := w.(http.Flusher)

		// SSE multi-line data: each line prefixed with "data:"
		fmt.Fprint(w, "event: multi\n")
		fmt.Fprint(w, "data: line1\n")
		fmt.Fprint(w, "data: line2\n")
		fmt.Fprint(w, "\n")
		flusher.Flush()
	}))
	defer server.Close()

	writer, getEvents := collectEvents()
	relay := NewUpstreamRelay(server.URL, "tok", server.Client())

	_ = relay.Relay(context.Background(), writer)

	events := getEvents()
	if len(events) != 1 {
		t.Fatalf("expected 1 event, got %d", len(events))
	}

	// Multi-line data should be joined with newlines.
	if events[0].Data != "line1\nline2" {
		t.Errorf("data = %q, want %q", events[0].Data, "line1\nline2")
	}
}

func TestNewUpstreamRelay_DefaultHTTPClient(t *testing.T) {
	t.Parallel()

	relay := NewUpstreamRelay("http://example.com", "tok", nil)
	if relay.httpClient != http.DefaultClient {
		t.Error("expected default http.Client when nil is passed")
	}
}
