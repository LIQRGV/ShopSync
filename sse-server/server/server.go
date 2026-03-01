package server

import (
	"net/http"
	"time"

	"github.com/redis/go-redis/v9"

	"github.com/LIQRGV/ShopSync/sse-server/auth"
	"github.com/LIQRGV/ShopSync/sse-server/config"
	"github.com/LIQRGV/ShopSync/sse-server/handler"
	"github.com/LIQRGV/ShopSync/sse-server/hub"
)

// New creates an *http.Server with all routes registered. The hub
// parameter may be nil when running in WTM mode. The rdb parameter
// is used for the connection counter.
func New(cfg *config.Config, h *hub.Hub, rdb *redis.Client) *http.Server {
	validator := auth.NewTokenValidator(cfg.AppKey)

	counter := handler.NewRedisConnectionCounter(rdb, cfg.RedisPrefix)

	startTime := time.Now()

	healthHandler := handler.NewHealthHandler(cfg.Mode, startTime, counter)
	sseHandler := handler.NewSSEHandler(
		validator,
		h,
		counter,
		cfg.Mode,
		time.Duration(cfg.HeartbeatInterval)*time.Second,
		time.Duration(cfg.ConnectionTimeout)*time.Second,
		cfg.RedisPrefix,
	)

	mux := http.NewServeMux()
	mux.Handle("/health", healthHandler)
	mux.Handle("/sse/events", sseHandler)

	return &http.Server{
		Handler: mux,
	}
}
