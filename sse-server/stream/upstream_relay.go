package stream

import (
	"bufio"
	"context"
	"fmt"
	"net/http"
	"strings"
)

// UpstreamRelay connects to an upstream WL SSE server and relays events
// to a local WTM client. Each WTM connection gets its own relay.
type UpstreamRelay struct {
	upstreamURL   string
	upstreamToken string
	httpClient    *http.Client
}

// NewUpstreamRelay creates a relay that will connect to the given
// upstream WL server. The upstreamURL should already include the route
// prefix (e.g. "https://shop-a.com/api/v1"). The httpClient can be
// injected for testing.
func NewUpstreamRelay(upstreamURL, upstreamToken string, httpClient *http.Client) *UpstreamRelay {
	if httpClient == nil {
		httpClient = http.DefaultClient
	}
	return &UpstreamRelay{
		upstreamURL:   upstreamURL,
		upstreamToken: upstreamToken,
		httpClient:    httpClient,
	}
}

// Relay connects to the upstream SSE endpoint and forwards each parsed
// event to the writer callback. It blocks until the upstream
// disconnects, ctx is cancelled, or an error occurs.
//
// The writer callback receives the SSE event type and the data payload.
// If the writer returns an error (e.g., the downstream client
// disconnected), Relay stops and returns that error.
func (u *UpstreamRelay) Relay(ctx context.Context, writer func(event, data string) error) error {
	url := fmt.Sprintf("%s/sse/events?token=%s", u.upstreamURL, u.upstreamToken)

	req, err := http.NewRequestWithContext(ctx, http.MethodGet, url, nil)
	if err != nil {
		return fmt.Errorf("upstream relay: failed to create request: %w", err)
	}
	req.Header.Set("Accept", "text/event-stream")
	req.Header.Set("Cache-Control", "no-cache")

	resp, err := u.httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("upstream relay: request failed: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("upstream relay: unexpected status %d", resp.StatusCode)
	}

	return u.parseSSEStream(ctx, resp, writer)
}

// parseSSEStream reads the response body line by line, accumulating SSE
// fields until a blank line (event boundary) is encountered, then
// delivers the complete event via the writer callback.
func (u *UpstreamRelay) parseSSEStream(ctx context.Context, resp *http.Response, writer func(event, data string) error) error {
	scanner := bufio.NewScanner(resp.Body)

	var currentEvent string
	var dataLines []string

	for scanner.Scan() {
		// Check context between lines so we can exit promptly.
		select {
		case <-ctx.Done():
			return ctx.Err()
		default:
		}

		line := scanner.Text()

		if line == "" {
			// Blank line = end of an SSE event block.
			if currentEvent != "" || len(dataLines) > 0 {
				data := strings.Join(dataLines, "\n")
				if err := writer(currentEvent, data); err != nil {
					return fmt.Errorf("upstream relay: writer error: %w", err)
				}
			}
			// Reset for the next event.
			currentEvent = ""
			dataLines = nil
			continue
		}

		// SSE comment lines start with ':'
		if strings.HasPrefix(line, ":") {
			continue
		}

		// Parse "field: value" or "field:value"
		if strings.HasPrefix(line, "event:") {
			currentEvent = strings.TrimSpace(strings.TrimPrefix(line, "event:"))
		} else if strings.HasPrefix(line, "data:") {
			dataLines = append(dataLines, strings.TrimSpace(strings.TrimPrefix(line, "data:")))
		} else if strings.HasPrefix(line, "id:") {
			// We note the id but do not use it currently.
			_ = strings.TrimSpace(strings.TrimPrefix(line, "id:"))
		}
	}

	if err := scanner.Err(); err != nil {
		// If the context was cancelled, prefer returning the context error.
		select {
		case <-ctx.Done():
			return ctx.Err()
		default:
		}
		return fmt.Errorf("upstream relay: stream read error: %w", err)
	}

	// Scanner finished without error = upstream closed the connection.
	return fmt.Errorf("upstream relay: upstream closed connection")
}
