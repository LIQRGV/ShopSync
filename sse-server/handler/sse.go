package handler

import (
	"context"
	"crypto/rand"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"time"

	"github.com/LIQRGV/ShopSync/sse-server/auth"
	"github.com/LIQRGV/ShopSync/sse-server/hub"
	"github.com/LIQRGV/ShopSync/sse-server/stream"
)

// SSEHandler is the HTTP handler for the SSE events endpoint. It
// validates the token, sets SSE headers, and delegates to the
// mode-specific handler (WL or WTM).
type SSEHandler struct {
	validator   *auth.TokenValidator
	hub         *hub.Hub // nil in WTM mode
	counter     ConnectionCounter
	mode        string
	heartbeat   time.Duration
	connTimeout time.Duration
	prefix      string // Redis prefix for upstream relay
}

// NewSSEHandler creates an SSEHandler. Pass nil for h when in WTM mode.
func NewSSEHandler(
	validator *auth.TokenValidator,
	h *hub.Hub,
	counter ConnectionCounter,
	mode string,
	heartbeat time.Duration,
	connTimeout time.Duration,
	prefix string,
) *SSEHandler {
	return &SSEHandler{
		validator:   validator,
		hub:         h,
		counter:     counter,
		mode:        mode,
		heartbeat:   heartbeat,
		connTimeout: connTimeout,
		prefix:      prefix,
	}
}

// ServeHTTP handles SSE connection requests.
func (s *SSEHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	// Step 1: Extract token from query param.
	token := r.URL.Query().Get("token")
	if token == "" {
		http.Error(w, `{"error":"missing token parameter"}`, http.StatusBadRequest)
		return
	}

	// Step 2: Validate token.
	claims, err := s.validator.Validate(token)
	if err != nil {
		http.Error(w, fmt.Sprintf(`{"error":"%s"}`, err.Error()), http.StatusUnauthorized)
		return
	}

	// Step 3: Check mode matches server mode.
	if claims.Mode != s.mode {
		http.Error(w, `{"error":"mode mismatch"}`, http.StatusForbidden)
		return
	}

	// Step 4: Verify the response writer supports flushing.
	flusher, ok := w.(http.Flusher)
	if !ok {
		http.Error(w, `{"error":"streaming not supported"}`, http.StatusInternalServerError)
		return
	}

	// Step 5: Set SSE headers.
	w.Header().Set("Content-Type", "text/event-stream")
	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Connection", "keep-alive")
	w.Header().Set("X-Accel-Buffering", "no")
	w.WriteHeader(http.StatusOK)
	flusher.Flush()

	// Step 6: Send connected event.
	sessionID := generateUUID()
	connectedData, _ := json.Marshal(map[string]string{
		"session_id": sessionID,
		"mode":       s.mode,
	})
	writeSSE(w, "connected", string(connectedData))
	flusher.Flush()

	// Step 7: Increment connection counter.
	if err := s.counter.IncrementConnections(r.Context()); err != nil {
		log.Printf("SSEHandler: failed to increment connections: %v", err)
	}
	defer func() {
		// Use a background context because r.Context() may already be cancelled.
		ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
		defer cancel()
		if err := s.counter.DecrementConnections(ctx); err != nil {
			log.Printf("SSEHandler: failed to decrement connections: %v", err)
		}
	}()

	// Step 8: Delegate to mode-specific handler.
	switch s.mode {
	case "wl":
		s.handleWL(w, r, flusher)
	case "wtm":
		s.handleWTM(w, r, flusher, claims)
	}
}

// handleWL serves an SSE connection in WL (WhiteLabel) mode. It
// registers a client with the Hub and relays broadcast messages until
// the connection closes, times out, or the client is force-disconnected.
func (s *SSEHandler) handleWL(w http.ResponseWriter, r *http.Request, flusher http.Flusher) {
	client := hub.NewClient(generateUUID())
	s.hub.Register(client)
	defer s.hub.Unregister(client)

	heartbeatTicker := time.NewTicker(s.heartbeat)
	defer heartbeatTicker.Stop()

	timeoutTimer := time.NewTimer(s.connTimeout)
	defer timeoutTimer.Stop()

	for {
		select {
		case <-client.Done:
			// Client was force-disconnected by hub (slow client).
			log.Printf("SSEHandler: client %s force-disconnected (slow client)", client.ID)
			return

		case msg, ok := <-client.Send:
			if !ok {
				// Hub was stopped (server shutdown).
				log.Printf("SSEHandler: client %s send channel closed (hub stopped)", client.ID)
				return
			}
			if _, err := w.Write(msg); err != nil {
				log.Printf("SSEHandler: client %s write error: %v", client.ID, err)
				return
			}
			flusher.Flush()

		case <-heartbeatTicker.C:
			comment := fmt.Sprintf(": heartbeat %d\n\n", time.Now().Unix())
			if _, err := w.Write([]byte(comment)); err != nil {
				log.Printf("SSEHandler: client %s heartbeat write error: %v", client.ID, err)
				return
			}
			flusher.Flush()

		case <-timeoutTimer.C:
			writeSSE(w, "disconnected", `{"reason":"timeout"}`)
			flusher.Flush()
			log.Printf("SSEHandler: client %s timed out", client.ID)
			return

		case <-r.Context().Done():
			// Client disconnected.
			log.Printf("SSEHandler: client %s disconnected", client.ID)
			return
		}
	}
}

// handleWTM serves an SSE connection in WTM mode. It creates an
// upstream relay to the WL server specified in the token claims and
// forwards events to the client.
func (s *SSEHandler) handleWTM(w http.ResponseWriter, r *http.Request, flusher http.Flusher, claims *auth.TokenPayload) {
	relay := stream.NewUpstreamRelay(claims.UpstreamURL, claims.UpstreamToken, nil)

	ctx, cancel := context.WithTimeout(r.Context(), s.connTimeout)
	defer cancel()

	heartbeatTicker := time.NewTicker(s.heartbeat)
	defer heartbeatTicker.Stop()

	// Start a goroutine to send heartbeats while the relay is running.
	heartbeatDone := make(chan struct{})
	go func() {
		defer close(heartbeatDone)
		for {
			select {
			case <-ctx.Done():
				return
			case <-heartbeatTicker.C:
				comment := fmt.Sprintf(": heartbeat %d\n\n", time.Now().Unix())
				if _, err := w.Write([]byte(comment)); err != nil {
					return
				}
				flusher.Flush()
			}
		}
	}()

	writerFunc := func(event, data string) error {
		writeSSE(w, event, data)
		flusher.Flush()
		return nil
	}

	err := relay.Relay(ctx, writerFunc)
	if err != nil {
		log.Printf("SSEHandler: upstream relay error: %v", err)
	}

	// Wait for heartbeat goroutine to finish.
	cancel()
	<-heartbeatDone

	writeSSE(w, "disconnected", `{"reason":"upstream_closed"}`)
	flusher.Flush()
}

// writeSSE writes a single SSE event to the writer.
func writeSSE(w http.ResponseWriter, event, data string) {
	fmt.Fprintf(w, "event: %s\ndata: %s\n\n", event, data)
}

// generateUUID produces a version 4 UUID string using crypto/rand.
func generateUUID() string {
	var uuid [16]byte
	if _, err := rand.Read(uuid[:]); err != nil {
		// crypto/rand.Read should never fail on a properly configured OS.
		panic(fmt.Sprintf("crypto/rand.Read failed: %v", err))
	}
	// Set version 4 (bits 12-15 of time_hi_and_version).
	uuid[6] = (uuid[6] & 0x0f) | 0x40
	// Set variant to RFC 4122 (bits 6-7 of clock_seq_hi_and_reserved).
	uuid[8] = (uuid[8] & 0x3f) | 0x80

	return fmt.Sprintf("%08x-%04x-%04x-%04x-%012x",
		uuid[0:4], uuid[4:6], uuid[6:8], uuid[8:10], uuid[10:16])
}
