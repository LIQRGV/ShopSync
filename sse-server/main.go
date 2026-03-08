package main

import (
	"context"
	"fmt"
	"log"
	"net"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/redis/go-redis/v9"

	"github.com/LIQRGV/ShopSync/sse-server/config"
	"github.com/LIQRGV/ShopSync/sse-server/hub"
	"github.com/LIQRGV/ShopSync/sse-server/server"
	"github.com/LIQRGV/ShopSync/sse-server/stream"
)

func main() {
	cfg := config.Load()

	// Redis client
	rdb := redis.NewClient(&redis.Options{
		Addr:     cfg.RedisAddr,
		DB:       cfg.RedisDB,
		Password: cfg.RedisPassword,
	})

	// Create hub and Redis reader (WL mode only)
	var h *hub.Hub
	var readerCancel context.CancelFunc
	if cfg.Mode == "wl" {
		h = hub.NewHub()
		go h.Run()

		// Start Redis reader with cancellable context
		readerCtx, cancel := context.WithCancel(context.Background())
		readerCancel = cancel
		sr := stream.NewRedisStreamReader(rdb)
		reader := stream.NewRedisReader(sr, cfg.RedisPrefix, h)
		go reader.Run(readerCtx)
	}

	// HTTP server
	srv := server.New(cfg, h, rdb)

	// Create listener: Unix socket (production) or TCP (local dev)
	var listener net.Listener
	var err error

	if cfg.Socket != "" {
		// Unix socket mode (production)
		// Remove stale socket from previous crash (defense-in-depth;
		// systemd ExecStartPre also does this on managed deployments)
		if _, statErr := os.Stat(cfg.Socket); statErr == nil {
			os.Remove(cfg.Socket)
		}

		listener, err = net.Listen("unix", cfg.Socket)
		if err != nil {
			log.Fatalf("Failed to listen on unix socket %s: %v", cfg.Socket, err)
		}

		// Set socket permissions: owner + group can read/write
		// (Nginx needs group access via psaserv group)
		if chmodErr := os.Chmod(cfg.Socket, 0660); chmodErr != nil {
			log.Printf("Warning: failed to chmod socket: %v", chmodErr)
		}

		log.Printf("Listening on unix socket: %s", cfg.Socket)
	} else {
		// TCP mode (local development / single-instance)
		addr := fmt.Sprintf(":%d", cfg.Port)
		listener, err = net.Listen("tcp", addr)
		if err != nil {
			log.Fatalf("Failed to listen on %s: %v", addr, err)
		}
		log.Printf("Listening on TCP port: %d", cfg.Port)
	}

	// Graceful shutdown
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)

	go func() {
		if err := srv.Serve(listener); err != nil && err != http.ErrServerClosed {
			log.Fatalf("Server error: %v", err)
		}
	}()

	<-quit
	log.Println("Shutting down...")

	// 1. Cancel Redis reader (stops reading new messages)
	if readerCancel != nil {
		readerCancel()
	}

	// 2. Stop Hub (closes all client Send channels -> HTTP handlers drain)
	if h != nil {
		h.Stop()
	}

	// 3. Graceful HTTP shutdown (waits for in-flight handlers, up to 10s)
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	if err := srv.Shutdown(ctx); err != nil {
		log.Printf("Server shutdown error: %v", err)
	}

	// 4. Clean up socket file
	if cfg.Socket != "" {
		os.Remove(cfg.Socket)
		log.Printf("Removed socket file: %s", cfg.Socket)
	}
}
