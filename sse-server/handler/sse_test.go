package handler_test

import (
	"bufio"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"github.com/LIQRGV/ShopSync/sse-server/auth"
	"github.com/LIQRGV/ShopSync/sse-server/handler"
	"github.com/LIQRGV/ShopSync/sse-server/hub"
)

const testAppKey = "test-secret-key-for-hmac"

// makeToken creates a signed token matching the PHP format for testing.
func makeToken(payload map[string]interface{}, appKey string) string {
	jsonBytes, err := json.Marshal(payload)
	if err != nil {
		panic(err)
	}
	b64 := base64.StdEncoding.EncodeToString(jsonBytes)
	mac := hmac.New(sha256.New, []byte(appKey))
	mac.Write([]byte(b64))
	sig := hex.EncodeToString(mac.Sum(nil))
	return b64 + "." + sig
}

// makeWLToken creates a valid WL mode token.
func makeWLToken() string {
	return makeToken(map[string]interface{}{
		"mode":           "wl",
		"client_id":      nil,
		"upstream_url":   "",
		"upstream_token": "",
		"iat":            time.Now().Unix(),
		"exp":            time.Now().Add(60 * time.Second).Unix(),
	}, testAppKey)
}

// makeWTMToken creates a valid WTM mode token.
func makeWTMToken(upstreamURL, upstreamToken string) string {
	return makeToken(map[string]interface{}{
		"mode":           "wtm",
		"client_id":      1,
		"upstream_url":   upstreamURL,
		"upstream_token": upstreamToken,
		"iat":            time.Now().Unix(),
		"exp":            time.Now().Add(60 * time.Second).Unix(),
	}, testAppKey)
}

// newTestSSEHandler creates an SSEHandler for testing in the specified mode.
func newTestSSEHandler(mode string, h *hub.Hub) *handler.SSEHandler {
	validator := auth.NewTokenValidator(testAppKey)
	counter := &mockConnectionCounter{}
	return handler.NewSSEHandler(
		validator,
		h,
		counter,
		mode,
		500*time.Millisecond,  // short heartbeat for tests
		2*time.Second,         // short timeout for tests
		"test_",
	)
}

func TestSSEHandler_MissingToken(t *testing.T) {
	t.Parallel()

	sseHandler := newTestSSEHandler("wl", nil)
	req := httptest.NewRequest(http.MethodGet, "/sse/events", nil)
	w := httptest.NewRecorder()

	sseHandler.ServeHTTP(w, req)

	resp := w.Result()
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusBadRequest {
		t.Errorf("expected status 400, got %d", resp.StatusCode)
	}

	body := w.Body.String()
	if !strings.Contains(body, "missing token") {
		t.Errorf("expected error about missing token, got %q", body)
	}
}

func TestSSEHandler_InvalidToken(t *testing.T) {
	t.Parallel()

	sseHandler := newTestSSEHandler("wl", nil)
	req := httptest.NewRequest(http.MethodGet, "/sse/events?token=invalid.token", nil)
	w := httptest.NewRecorder()

	sseHandler.ServeHTTP(w, req)

	resp := w.Result()
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusUnauthorized {
		t.Errorf("expected status 401, got %d", resp.StatusCode)
	}
}

func TestSSEHandler_ExpiredToken(t *testing.T) {
	t.Parallel()

	token := makeToken(map[string]interface{}{
		"mode":           "wl",
		"client_id":      nil,
		"upstream_url":   "",
		"upstream_token": "",
		"iat":            time.Now().Add(-120 * time.Second).Unix(),
		"exp":            time.Now().Add(-60 * time.Second).Unix(), // expired
	}, testAppKey)

	sseHandler := newTestSSEHandler("wl", nil)
	req := httptest.NewRequest(http.MethodGet, "/sse/events?token="+token, nil)
	w := httptest.NewRecorder()

	sseHandler.ServeHTTP(w, req)

	resp := w.Result()
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusUnauthorized {
		t.Errorf("expected status 401 for expired token, got %d", resp.StatusCode)
	}
}

func TestSSEHandler_ModeMismatch(t *testing.T) {
	t.Parallel()

	// Server is in WL mode, but token says WTM.
	wtmToken := makeWTMToken("https://example.com", "upstream-token")

	sseHandler := newTestSSEHandler("wl", nil)
	req := httptest.NewRequest(http.MethodGet, "/sse/events?token="+wtmToken, nil)
	w := httptest.NewRecorder()

	sseHandler.ServeHTTP(w, req)

	resp := w.Result()
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusForbidden {
		t.Errorf("expected status 403 for mode mismatch, got %d", resp.StatusCode)
	}

	body := w.Body.String()
	if !strings.Contains(body, "mode mismatch") {
		t.Errorf("expected error about mode mismatch, got %q", body)
	}
}

func TestSSEHandler_WL_ConnectedEventAndMessages(t *testing.T) {
	t.Parallel()

	h := hub.NewHub()
	go h.Run()
	defer h.Stop()

	sseHandler := newTestSSEHandler("wl", h)
	token := makeWLToken()

	// Use httptest.Server to get a real streaming connection.
	ts := httptest.NewServer(sseHandler)
	defer ts.Close()

	url := ts.URL + "/sse/events?token=" + token
	req, err := http.NewRequest(http.MethodGet, url, nil)
	if err != nil {
		t.Fatalf("failed to create request: %v", err)
	}
	req.Header.Set("Accept", "text/event-stream")

	client := &http.Client{Timeout: 5 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		t.Fatalf("failed to connect: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		t.Fatalf("expected status 200, got %d", resp.StatusCode)
	}

	if ct := resp.Header.Get("Content-Type"); ct != "text/event-stream" {
		t.Errorf("expected Content-Type text/event-stream, got %q", ct)
	}
	if cc := resp.Header.Get("Cache-Control"); cc != "no-cache" {
		t.Errorf("expected Cache-Control no-cache, got %q", cc)
	}
	if xa := resp.Header.Get("X-Accel-Buffering"); xa != "no" {
		t.Errorf("expected X-Accel-Buffering no, got %q", xa)
	}

	scanner := bufio.NewScanner(resp.Body)

	// Read the connected event.
	connectedEvent := readSSEEvent(t, scanner)
	if connectedEvent.eventType != "connected" {
		t.Errorf("expected first event type 'connected', got %q", connectedEvent.eventType)
	}
	var connData map[string]string
	if err := json.Unmarshal([]byte(connectedEvent.data), &connData); err != nil {
		t.Fatalf("failed to parse connected event data: %v", err)
	}
	if connData["session_id"] == "" {
		t.Error("expected session_id in connected event, got empty")
	}
	if connData["mode"] != "wl" {
		t.Errorf("expected mode=wl in connected event, got %q", connData["mode"])
	}

	// Now broadcast a message through the hub and verify we receive it.
	sseMsg := "event: product.updated\ndata: {\"product_id\":1}\n\n"
	h.Broadcast([]byte(sseMsg))

	productEvent := readSSEEvent(t, scanner)
	if productEvent.eventType != "product.updated" {
		t.Errorf("expected event type 'product.updated', got %q", productEvent.eventType)
	}
	if !strings.Contains(productEvent.data, "product_id") {
		t.Errorf("expected data to contain product_id, got %q", productEvent.data)
	}
}

func TestSSEHandler_WL_Heartbeat(t *testing.T) {
	t.Parallel()

	h := hub.NewHub()
	go h.Run()
	defer h.Stop()

	sseHandler := newTestSSEHandler("wl", h)
	token := makeWLToken()

	ts := httptest.NewServer(sseHandler)
	defer ts.Close()

	url := ts.URL + "/sse/events?token=" + token
	client := &http.Client{Timeout: 5 * time.Second}
	resp, err := client.Get(url)
	if err != nil {
		t.Fatalf("failed to connect: %v", err)
	}
	defer resp.Body.Close()

	scanner := bufio.NewScanner(resp.Body)

	// Skip the connected event.
	_ = readSSEEvent(t, scanner)

	// Wait for a heartbeat comment (should come within 500ms).
	// Heartbeat lines start with ": heartbeat".
	deadline := time.After(3 * time.Second)
	heartbeatReceived := false

	for !heartbeatReceived {
		select {
		case <-deadline:
			t.Fatal("timed out waiting for heartbeat")
		default:
		}

		if !scanner.Scan() {
			t.Fatal("scanner stopped before heartbeat received")
		}
		line := scanner.Text()
		if strings.HasPrefix(line, ": heartbeat") {
			heartbeatReceived = true
		}
	}

	if !heartbeatReceived {
		t.Error("heartbeat was not received")
	}
}

func TestSSEHandler_WL_ConnectionTimeout(t *testing.T) {
	t.Parallel()

	h := hub.NewHub()
	go h.Run()
	defer h.Stop()

	// Create a handler with a very short timeout (1 second).
	validator := auth.NewTokenValidator(testAppKey)
	counter := &mockConnectionCounter{}
	sseHandler := handler.NewSSEHandler(
		validator,
		h,
		counter,
		"wl",
		10*time.Second,       // heartbeat longer than timeout
		1*time.Second,        // very short timeout
		"test_",
	)

	token := makeWLToken()
	ts := httptest.NewServer(sseHandler)
	defer ts.Close()

	url := ts.URL + "/sse/events?token=" + token
	client := &http.Client{Timeout: 5 * time.Second}
	resp, err := client.Get(url)
	if err != nil {
		t.Fatalf("failed to connect: %v", err)
	}
	defer resp.Body.Close()

	scanner := bufio.NewScanner(resp.Body)

	// Skip the connected event.
	_ = readSSEEvent(t, scanner)

	// Wait for the disconnected event (should come within ~1 second).
	disconnectedEvent := readSSEEvent(t, scanner)
	if disconnectedEvent.eventType != "disconnected" {
		t.Errorf("expected event type 'disconnected', got %q", disconnectedEvent.eventType)
	}
	if !strings.Contains(disconnectedEvent.data, "timeout") {
		t.Errorf("expected data to contain 'timeout', got %q", disconnectedEvent.data)
	}
}

func TestSSEHandler_WL_CounterIncDec(t *testing.T) {
	t.Parallel()

	h := hub.NewHub()
	go h.Run()
	defer h.Stop()

	validator := auth.NewTokenValidator(testAppKey)
	counter := &mockConnectionCounter{}
	sseHandler := handler.NewSSEHandler(
		validator,
		h,
		counter,
		"wl",
		10*time.Second,
		500*time.Millisecond, // short timeout so connection closes quickly
		"test_",
	)

	token := makeWLToken()
	ts := httptest.NewServer(sseHandler)
	defer ts.Close()

	url := ts.URL + "/sse/events?token=" + token
	client := &http.Client{Timeout: 5 * time.Second}
	resp, err := client.Get(url)
	if err != nil {
		t.Fatalf("failed to connect: %v", err)
	}

	// Read until the connection closes (timeout).
	scanner := bufio.NewScanner(resp.Body)
	for scanner.Scan() {
		// drain
	}
	resp.Body.Close()

	// Give some time for the deferred decrement to run.
	time.Sleep(200 * time.Millisecond)

	// After connection closes, counter should be back to 0.
	finalCount := counter.getCount()
	if finalCount != 0 {
		t.Errorf("expected counter=0 after disconnect, got %d", finalCount)
	}
}

// sseEvent represents a parsed SSE event from the stream.
type sseEvent struct {
	eventType string
	data      string
}

// readSSEEvent reads lines from the scanner until a complete SSE event
// (terminated by a blank line) is found. It skips SSE comments.
func readSSEEvent(t *testing.T, scanner *bufio.Scanner) sseEvent {
	t.Helper()

	var event sseEvent
	var dataLines []string

	for scanner.Scan() {
		line := scanner.Text()

		if line == "" {
			// Blank line = end of event.
			if event.eventType != "" || len(dataLines) > 0 {
				event.data = strings.Join(dataLines, "\n")
				return event
			}
			continue
		}

		// Skip SSE comments.
		if strings.HasPrefix(line, ":") {
			continue
		}

		if strings.HasPrefix(line, "event: ") {
			event.eventType = strings.TrimPrefix(line, "event: ")
		} else if strings.HasPrefix(line, "data: ") {
			dataLines = append(dataLines, strings.TrimPrefix(line, "data: "))
		}
	}

	if err := scanner.Err(); err != nil {
		t.Fatalf("scanner error while reading SSE event: %v", err)
	}

	// If we reach here, the stream ended before a complete event.
	if event.eventType != "" || len(dataLines) > 0 {
		event.data = strings.Join(dataLines, "\n")
		return event
	}

	t.Fatal("stream ended without receiving a complete SSE event")
	return event // unreachable
}

func TestSSEHandler_WTM_ModeMismatchReverse(t *testing.T) {
	t.Parallel()

	// Server is in WTM mode, but token says WL.
	wlToken := makeWLToken()

	sseHandler := newTestSSEHandler("wtm", nil)
	req := httptest.NewRequest(http.MethodGet, "/sse/events?token="+wlToken, nil)
	w := httptest.NewRecorder()

	sseHandler.ServeHTTP(w, req)

	resp := w.Result()
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusForbidden {
		t.Errorf("expected status 403 for mode mismatch, got %d", resp.StatusCode)
	}
}

func TestSSEHandler_WTM_ConnectsToUpstreamAndRelays(t *testing.T) {
	t.Parallel()

	// Create a fake upstream SSE server.
	upstream := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		flusher, ok := w.(http.Flusher)
		if !ok {
			http.Error(w, "no flusher", 500)
			return
		}

		w.Header().Set("Content-Type", "text/event-stream")
		w.Header().Set("Cache-Control", "no-cache")
		w.WriteHeader(http.StatusOK)
		flusher.Flush()

		// Send a connected event.
		fmt.Fprint(w, "event: connected\ndata: {\"session_id\":\"upstream-session\"}\n\n")
		flusher.Flush()

		// Send a product event.
		fmt.Fprint(w, "event: product.updated\ndata: {\"product_id\":42}\n\n")
		flusher.Flush()

		// Close the connection (upstream disconnect).
	}))
	defer upstream.Close()

	// Create WTM token pointing to our fake upstream.
	wtmToken := makeWTMToken(upstream.URL, "fake-upstream-token")

	sseHandler := newTestSSEHandler("wtm", nil)

	ts := httptest.NewServer(sseHandler)
	defer ts.Close()

	url := ts.URL + "/sse/events?token=" + wtmToken
	client := &http.Client{Timeout: 5 * time.Second}
	resp, err := client.Get(url)
	if err != nil {
		t.Fatalf("failed to connect: %v", err)
	}
	defer resp.Body.Close()

	scanner := bufio.NewScanner(resp.Body)

	// First event should be the local "connected" event.
	connectedEvent := readSSEEvent(t, scanner)
	if connectedEvent.eventType != "connected" {
		t.Errorf("expected first event 'connected', got %q", connectedEvent.eventType)
	}

	// Second event should be the upstream "connected" event relayed.
	upstreamConnected := readSSEEvent(t, scanner)
	if upstreamConnected.eventType != "connected" {
		t.Errorf("expected upstream connected event, got %q", upstreamConnected.eventType)
	}

	// Third event should be the product.updated event relayed.
	productEvent := readSSEEvent(t, scanner)
	if productEvent.eventType != "product.updated" {
		t.Errorf("expected 'product.updated', got %q", productEvent.eventType)
	}
	if !strings.Contains(productEvent.data, "42") {
		t.Errorf("expected data to contain product_id 42, got %q", productEvent.data)
	}

	// After upstream closes, we should get a disconnected event.
	disconnectedEvent := readSSEEvent(t, scanner)
	if disconnectedEvent.eventType != "disconnected" {
		t.Errorf("expected 'disconnected' event, got %q", disconnectedEvent.eventType)
	}
}

func TestGenerateUUID_Format(t *testing.T) {
	t.Parallel()

	// Generate multiple UUIDs and verify format.
	seen := make(map[string]bool)
	for i := 0; i < 100; i++ {
		uuid := handler.GenerateUUIDForTest()
		// UUID v4 format: 8-4-4-4-12 hex chars.
		if len(uuid) != 36 {
			t.Errorf("UUID length: expected 36, got %d for %q", len(uuid), uuid)
		}
		if uuid[8] != '-' || uuid[13] != '-' || uuid[18] != '-' || uuid[23] != '-' {
			t.Errorf("UUID format invalid: %q", uuid)
		}
		// Check version nibble (position 14 should be '4').
		if uuid[14] != '4' {
			t.Errorf("UUID version nibble: expected '4', got %c in %q", uuid[14], uuid)
		}
		// Check variant nibble (position 19 should be 8, 9, a, or b).
		variant := uuid[19]
		if variant != '8' && variant != '9' && variant != 'a' && variant != 'b' {
			t.Errorf("UUID variant nibble: expected 8/9/a/b, got %c in %q", variant, uuid)
		}
		if seen[uuid] {
			t.Errorf("duplicate UUID: %q", uuid)
		}
		seen[uuid] = true
	}
}
