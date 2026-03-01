package handler_test

import (
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/LIQRGV/ShopSync/sse-server/handler"
)

func TestHealthHandler_ReturnsCorrectJSON(t *testing.T) {
	t.Parallel()

	counter := &mockConnectionCounter{count: 5}
	startTime := time.Now().Add(-1 * time.Hour) // 1 hour ago

	h := handler.NewHealthHandler("wl", startTime, counter)

	req := httptest.NewRequest(http.MethodGet, "/health", nil)
	w := httptest.NewRecorder()

	h.ServeHTTP(w, req)

	resp := w.Result()
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		t.Errorf("expected status 200, got %d", resp.StatusCode)
	}

	contentType := resp.Header.Get("Content-Type")
	if contentType != "application/json" {
		t.Errorf("expected Content-Type application/json, got %q", contentType)
	}

	var body map[string]interface{}
	if err := json.NewDecoder(resp.Body).Decode(&body); err != nil {
		t.Fatalf("failed to decode response: %v", err)
	}

	if body["status"] != "ok" {
		t.Errorf("expected status=ok, got %v", body["status"])
	}
	if body["mode"] != "wl" {
		t.Errorf("expected mode=wl, got %v", body["mode"])
	}
	if body["active_connections"] != float64(5) {
		t.Errorf("expected active_connections=5, got %v", body["active_connections"])
	}

	uptime, ok := body["uptime_seconds"].(float64)
	if !ok {
		t.Fatalf("uptime_seconds is not a number: %v", body["uptime_seconds"])
	}
	// Allow a small tolerance for the time check (between 3599 and 3601 seconds).
	if uptime < 3599 || uptime > 3601 {
		t.Errorf("expected uptime_seconds ~3600, got %v", uptime)
	}
}

func TestHealthHandler_WTMMode(t *testing.T) {
	t.Parallel()

	counter := &mockConnectionCounter{count: 12}
	startTime := time.Now().Add(-30 * time.Second)

	h := handler.NewHealthHandler("wtm", startTime, counter)

	req := httptest.NewRequest(http.MethodGet, "/health", nil)
	w := httptest.NewRecorder()

	h.ServeHTTP(w, req)

	resp := w.Result()
	defer resp.Body.Close()

	var body map[string]interface{}
	if err := json.NewDecoder(resp.Body).Decode(&body); err != nil {
		t.Fatalf("failed to decode response: %v", err)
	}

	if body["mode"] != "wtm" {
		t.Errorf("expected mode=wtm, got %v", body["mode"])
	}

	uptime, ok := body["uptime_seconds"].(float64)
	if !ok {
		t.Fatalf("uptime_seconds is not a number")
	}
	if uptime < 29 || uptime > 31 {
		t.Errorf("expected uptime_seconds ~30, got %v", uptime)
	}
}

func TestHealthHandler_CounterError(t *testing.T) {
	t.Parallel()

	counter := &mockConnectionCounter{getCountErr: errors.New("redis down")}
	startTime := time.Now()

	h := handler.NewHealthHandler("wl", startTime, counter)

	req := httptest.NewRequest(http.MethodGet, "/health", nil)
	w := httptest.NewRecorder()

	h.ServeHTTP(w, req)

	resp := w.Result()
	defer resp.Body.Close()

	// Should still return 200 OK with count=0 rather than failing.
	if resp.StatusCode != http.StatusOK {
		t.Errorf("expected status 200 even on counter error, got %d", resp.StatusCode)
	}

	var body map[string]interface{}
	if err := json.NewDecoder(resp.Body).Decode(&body); err != nil {
		t.Fatalf("failed to decode response: %v", err)
	}

	if body["active_connections"] != float64(0) {
		t.Errorf("expected active_connections=0 on error, got %v", body["active_connections"])
	}
}

func TestHealthHandler_ZeroConnections(t *testing.T) {
	t.Parallel()

	counter := &mockConnectionCounter{count: 0}
	startTime := time.Now()

	h := handler.NewHealthHandler("wl", startTime, counter)

	req := httptest.NewRequest(http.MethodGet, "/health", nil)
	w := httptest.NewRecorder()

	h.ServeHTTP(w, req)

	resp := w.Result()
	defer resp.Body.Close()

	var body map[string]interface{}
	if err := json.NewDecoder(resp.Body).Decode(&body); err != nil {
		t.Fatalf("failed to decode response: %v", err)
	}

	if body["active_connections"] != float64(0) {
		t.Errorf("expected active_connections=0, got %v", body["active_connections"])
	}
}
