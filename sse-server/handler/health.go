package handler

import (
	"encoding/json"
	"log"
	"net/http"
	"time"
)

// healthResponse is the JSON structure returned by the health endpoint.
type healthResponse struct {
	Status            string `json:"status"`
	Mode              string `json:"mode"`
	ActiveConnections int64  `json:"active_connections"`
	UptimeSeconds     int64  `json:"uptime_seconds"`
}

// HealthHandler serves the GET /health endpoint with server status.
type HealthHandler struct {
	mode      string
	startTime time.Time
	counter   ConnectionCounter
}

// NewHealthHandler creates a HealthHandler.
func NewHealthHandler(mode string, startTime time.Time, counter ConnectionCounter) *HealthHandler {
	return &HealthHandler{
		mode:      mode,
		startTime: startTime,
		counter:   counter,
	}
}

// ServeHTTP returns a JSON response with server status information.
func (h *HealthHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	count, err := h.counter.GetConnectionCount(r.Context())
	if err != nil {
		log.Printf("HealthHandler: failed to get connection count: %v", err)
		// Continue with count=0 rather than failing the health check.
	}

	uptime := int64(time.Since(h.startTime).Seconds())

	resp := healthResponse{
		Status:            "ok",
		Mode:              h.mode,
		ActiveConnections: count,
		UptimeSeconds:     uptime,
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	if err := json.NewEncoder(w).Encode(resp); err != nil {
		log.Printf("HealthHandler: failed to encode response: %v", err)
	}
}
