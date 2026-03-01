package config

import (
	"os"
	"os/exec"
	"testing"
)

// clearEnvVars removes all environment variables that Load() reads,
// ensuring a clean slate for each test.
func clearEnvVars(t *testing.T) {
	t.Helper()
	vars := []string{
		"SSE_GO_SOCKET", "SSE_GO_PORT",
		"SSE_GO_REDIS_ADDR", "SSE_GO_REDIS_DB", "SSE_GO_REDIS_PASSWORD", "SSE_GO_REDIS_PREFIX",
		"SSE_GO_APP_KEY", "SSE_GO_MODE",
		"SSE_GO_HEARTBEAT_INTERVAL", "SSE_GO_CONNECTION_TIMEOUT", "SSE_GO_MAX_CLIENTS",
		"REDIS_HOST", "REDIS_PORT", "REDIS_DB", "REDIS_PASSWORD", "REDIS_PREFIX",
		"APP_KEY", "APP_NAME", "PRODUCT_PACKAGE_MODE",
	}
	for _, v := range vars {
		os.Unsetenv(v)
	}
}

func TestLoadDefaults(t *testing.T) {
	clearEnvVars(t)

	// APP_KEY is required -- set it to avoid log.Fatal
	os.Setenv("APP_KEY", "test-app-key")
	defer os.Unsetenv("APP_KEY")

	cfg := Load()

	if cfg.Socket != "" {
		t.Errorf("Socket: got %q, want %q", cfg.Socket, "")
	}
	if cfg.Port != 8081 {
		t.Errorf("Port: got %d, want %d", cfg.Port, 8081)
	}
	if cfg.RedisAddr != "127.0.0.1:6379" {
		t.Errorf("RedisAddr: got %q, want %q", cfg.RedisAddr, "127.0.0.1:6379")
	}
	if cfg.RedisDB != 0 {
		t.Errorf("RedisDB: got %d, want %d", cfg.RedisDB, 0)
	}
	if cfg.RedisPassword != "" {
		t.Errorf("RedisPassword: got %q, want %q", cfg.RedisPassword, "")
	}
	if cfg.RedisPrefix != "laravel_database_" {
		t.Errorf("RedisPrefix: got %q, want %q", cfg.RedisPrefix, "laravel_database_")
	}
	if cfg.AppKey != "test-app-key" {
		t.Errorf("AppKey: got %q, want %q", cfg.AppKey, "test-app-key")
	}
	if cfg.Mode != "wl" {
		t.Errorf("Mode: got %q, want %q", cfg.Mode, "wl")
	}
	if cfg.HeartbeatInterval != 30 {
		t.Errorf("HeartbeatInterval: got %d, want %d", cfg.HeartbeatInterval, 30)
	}
	if cfg.ConnectionTimeout != 600 {
		t.Errorf("ConnectionTimeout: got %d, want %d", cfg.ConnectionTimeout, 600)
	}
	if cfg.MaxClients != 1000 {
		t.Errorf("MaxClients: got %d, want %d", cfg.MaxClients, 1000)
	}
	if cfg.RedisStreamKey != "laravel_database_products.updates.stream" {
		t.Errorf("RedisStreamKey: got %q, want %q", cfg.RedisStreamKey, "laravel_database_products.updates.stream")
	}
}

func TestLoadSSEGoOverrides(t *testing.T) {
	clearEnvVars(t)
	defer clearEnvVars(t)

	// Set both Laravel and SSE_GO_ vars -- SSE_GO_ should win
	os.Setenv("APP_KEY", "laravel-key")
	os.Setenv("SSE_GO_APP_KEY", "go-override-key")
	os.Setenv("SSE_GO_SOCKET", "/run/sse.sock")
	os.Setenv("SSE_GO_PORT", "9090")
	os.Setenv("SSE_GO_REDIS_ADDR", "redis.prod:6380")
	os.Setenv("SSE_GO_REDIS_DB", "5")
	os.Setenv("SSE_GO_REDIS_PASSWORD", "go-secret")
	os.Setenv("SSE_GO_REDIS_PREFIX", "custom_prefix_")
	os.Setenv("SSE_GO_MODE", "wtm")
	os.Setenv("SSE_GO_HEARTBEAT_INTERVAL", "15")
	os.Setenv("SSE_GO_CONNECTION_TIMEOUT", "300")
	os.Setenv("SSE_GO_MAX_CLIENTS", "500")

	// Set Laravel fallbacks that should be ignored
	os.Setenv("REDIS_HOST", "laravel-redis")
	os.Setenv("REDIS_PORT", "6381")
	os.Setenv("REDIS_DB", "3")
	os.Setenv("REDIS_PASSWORD", "laravel-secret")
	os.Setenv("REDIS_PREFIX", "laravel_prefix_")
	os.Setenv("PRODUCT_PACKAGE_MODE", "wl")

	cfg := Load()

	if cfg.Socket != "/run/sse.sock" {
		t.Errorf("Socket: got %q, want %q", cfg.Socket, "/run/sse.sock")
	}
	if cfg.Port != 9090 {
		t.Errorf("Port: got %d, want %d", cfg.Port, 9090)
	}
	if cfg.RedisAddr != "redis.prod:6380" {
		t.Errorf("RedisAddr: got %q, want %q", cfg.RedisAddr, "redis.prod:6380")
	}
	if cfg.RedisDB != 5 {
		t.Errorf("RedisDB: got %d, want %d", cfg.RedisDB, 5)
	}
	if cfg.RedisPassword != "go-secret" {
		t.Errorf("RedisPassword: got %q, want %q", cfg.RedisPassword, "go-secret")
	}
	if cfg.RedisPrefix != "custom_prefix_" {
		t.Errorf("RedisPrefix: got %q, want %q", cfg.RedisPrefix, "custom_prefix_")
	}
	if cfg.AppKey != "go-override-key" {
		t.Errorf("AppKey: got %q, want %q", cfg.AppKey, "go-override-key")
	}
	if cfg.Mode != "wtm" {
		t.Errorf("Mode: got %q, want %q", cfg.Mode, "wtm")
	}
	if cfg.HeartbeatInterval != 15 {
		t.Errorf("HeartbeatInterval: got %d, want %d", cfg.HeartbeatInterval, 15)
	}
	if cfg.ConnectionTimeout != 300 {
		t.Errorf("ConnectionTimeout: got %d, want %d", cfg.ConnectionTimeout, 300)
	}
	if cfg.MaxClients != 500 {
		t.Errorf("MaxClients: got %d, want %d", cfg.MaxClients, 500)
	}
	if cfg.RedisStreamKey != "custom_prefix_products.updates.stream" {
		t.Errorf("RedisStreamKey: got %q, want %q", cfg.RedisStreamKey, "custom_prefix_products.updates.stream")
	}
}

func TestLoadLaravelFallbacks(t *testing.T) {
	clearEnvVars(t)
	defer clearEnvVars(t)

	// Set only Laravel env vars (no SSE_GO_ overrides)
	os.Setenv("APP_KEY", "laravel-app-key")
	os.Setenv("REDIS_HOST", "redis.internal")
	os.Setenv("REDIS_PORT", "6380")
	os.Setenv("REDIS_DB", "2")
	os.Setenv("REDIS_PASSWORD", "laravel-redis-pw")
	os.Setenv("REDIS_PREFIX", "myapp_cache_")
	os.Setenv("PRODUCT_PACKAGE_MODE", "wtm")

	cfg := Load()

	if cfg.RedisAddr != "redis.internal:6380" {
		t.Errorf("RedisAddr: got %q, want %q", cfg.RedisAddr, "redis.internal:6380")
	}
	if cfg.RedisDB != 2 {
		t.Errorf("RedisDB: got %d, want %d", cfg.RedisDB, 2)
	}
	if cfg.RedisPassword != "laravel-redis-pw" {
		t.Errorf("RedisPassword: got %q, want %q", cfg.RedisPassword, "laravel-redis-pw")
	}
	if cfg.RedisPrefix != "myapp_cache_" {
		t.Errorf("RedisPrefix: got %q, want %q", cfg.RedisPrefix, "myapp_cache_")
	}
	if cfg.AppKey != "laravel-app-key" {
		t.Errorf("AppKey: got %q, want %q", cfg.AppKey, "laravel-app-key")
	}
	if cfg.Mode != "wtm" {
		t.Errorf("Mode: got %q, want %q", cfg.Mode, "wtm")
	}
}

func TestLoadRedisAddrFromHostPort(t *testing.T) {
	clearEnvVars(t)
	defer clearEnvVars(t)
	os.Setenv("APP_KEY", "test-key")
	os.Setenv("REDIS_HOST", "10.0.0.5")
	os.Setenv("REDIS_PORT", "6390")

	cfg := Load()

	if cfg.RedisAddr != "10.0.0.5:6390" {
		t.Errorf("RedisAddr: got %q, want %q", cfg.RedisAddr, "10.0.0.5:6390")
	}
}

func TestLoadRedisAddrHostOnlyDefaultPort(t *testing.T) {
	clearEnvVars(t)
	defer clearEnvVars(t)
	os.Setenv("APP_KEY", "test-key")
	os.Setenv("REDIS_HOST", "10.0.0.5")
	// REDIS_PORT not set -- should default to 6379

	cfg := Load()

	if cfg.RedisAddr != "10.0.0.5:6379" {
		t.Errorf("RedisAddr: got %q, want %q", cfg.RedisAddr, "10.0.0.5:6379")
	}
}

func TestDeriveRedisPrefix(t *testing.T) {
	tests := []struct {
		name    string
		appName string
		want    string
	}{
		{
			name:    "simple app name",
			appName: "TheDiamondBox",
			want:    "thediamondbox_database_",
		},
		{
			name:    "app name with spaces",
			appName: "My Cool App",
			want:    "my_cool_app_database_",
		},
		{
			name:    "app name with special chars",
			appName: "Shop--Sync!!App",
			want:    "shop_sync_app_database_",
		},
		{
			name:    "app name with leading/trailing special chars",
			appName: "---My App---",
			want:    "my_app_database_",
		},
		{
			name:    "app name with mixed case and numbers",
			appName: "App123Test",
			want:    "app123test_database_",
		},
		{
			name:    "single word lowercase",
			appName: "laravel",
			want:    "laravel_database_",
		},
		{
			name:    "marketplace api with hyphen",
			appName: "Marketplace-API",
			want:    "marketplace_api_database_",
		},
		{
			name:    "app name with consecutive special chars",
			appName: "My___App",
			want:    "my_app_database_",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := DeriveRedisPrefix(tt.appName)
			if got != tt.want {
				t.Errorf("DeriveRedisPrefix(%q) = %q, want %q", tt.appName, got, tt.want)
			}
		})
	}
}

func TestLoadRedisPrefixDerivedFromAppName(t *testing.T) {
	clearEnvVars(t)
	defer clearEnvVars(t)
	os.Setenv("APP_KEY", "test-key")
	os.Setenv("APP_NAME", "TheDiamondBox")

	cfg := Load()

	if cfg.RedisPrefix != "thediamondbox_database_" {
		t.Errorf("RedisPrefix: got %q, want %q", cfg.RedisPrefix, "thediamondbox_database_")
	}
	if cfg.RedisStreamKey != "thediamondbox_database_products.updates.stream" {
		t.Errorf("RedisStreamKey: got %q, want %q", cfg.RedisStreamKey, "thediamondbox_database_products.updates.stream")
	}
}

func TestLoadRedisPrefixPriorityChain(t *testing.T) {
	t.Run("SSE_GO_REDIS_PREFIX wins over REDIS_PREFIX", func(t *testing.T) {
		clearEnvVars(t)
		defer clearEnvVars(t)
		os.Setenv("APP_KEY", "test-key")
		os.Setenv("SSE_GO_REDIS_PREFIX", "go_override_")
		os.Setenv("REDIS_PREFIX", "laravel_prefix_")
		os.Setenv("APP_NAME", "SomeApp")

		cfg := Load()
		if cfg.RedisPrefix != "go_override_" {
			t.Errorf("RedisPrefix: got %q, want %q", cfg.RedisPrefix, "go_override_")
		}
	})

	t.Run("REDIS_PREFIX wins over APP_NAME derivation", func(t *testing.T) {
		clearEnvVars(t)
		defer clearEnvVars(t)
		os.Setenv("APP_KEY", "test-key")
		os.Setenv("REDIS_PREFIX", "laravel_prefix_")
		os.Setenv("APP_NAME", "SomeApp")

		cfg := Load()
		if cfg.RedisPrefix != "laravel_prefix_" {
			t.Errorf("RedisPrefix: got %q, want %q", cfg.RedisPrefix, "laravel_prefix_")
		}
	})

	t.Run("APP_NAME derivation used as last resort", func(t *testing.T) {
		clearEnvVars(t)
		defer clearEnvVars(t)
		os.Setenv("APP_KEY", "test-key")
		os.Setenv("APP_NAME", "MarketplaceAPI")

		cfg := Load()
		if cfg.RedisPrefix != "marketplaceapi_database_" {
			t.Errorf("RedisPrefix: got %q, want %q", cfg.RedisPrefix, "marketplaceapi_database_")
		}
	})
}

func TestLoadMissingAppKeyFatal(t *testing.T) {
	// Test that Load() calls log.Fatal when APP_KEY is missing.
	// We use the subprocess pattern: re-run this test binary with an env flag
	// that triggers the fatal code path, then verify the process exits non-zero.
	if os.Getenv("TEST_MISSING_APP_KEY") == "1" {
		// This runs inside the subprocess. Clear all env vars and call Load().
		// Load() should call log.Fatal because APP_KEY is not set.
		vars := []string{
			"SSE_GO_SOCKET", "SSE_GO_PORT",
			"SSE_GO_REDIS_ADDR", "SSE_GO_REDIS_DB", "SSE_GO_REDIS_PASSWORD", "SSE_GO_REDIS_PREFIX",
			"SSE_GO_APP_KEY", "SSE_GO_MODE",
			"SSE_GO_HEARTBEAT_INTERVAL", "SSE_GO_CONNECTION_TIMEOUT", "SSE_GO_MAX_CLIENTS",
			"REDIS_HOST", "REDIS_PORT", "REDIS_DB", "REDIS_PASSWORD", "REDIS_PREFIX",
			"APP_KEY", "APP_NAME", "PRODUCT_PACKAGE_MODE",
		}
		for _, v := range vars {
			os.Unsetenv(v)
		}
		Load()
		// If we reach here, log.Fatal did not fire -- test should fail
		os.Exit(0)
	}

	cmd := exec.Command(os.Args[0], "-test.run=^TestLoadMissingAppKeyFatal$")
	cmd.Env = append(os.Environ(), "TEST_MISSING_APP_KEY=1")
	err := cmd.Run()
	if err == nil {
		t.Fatal("Expected Load() to call log.Fatal when APP_KEY is missing, but process exited 0")
	}

	// Verify the process exited with a non-zero status (log.Fatal calls os.Exit(1))
	if exitErr, ok := err.(*exec.ExitError); ok {
		if exitErr.ExitCode() == 0 {
			t.Fatal("Expected non-zero exit code from log.Fatal, got 0")
		}
		// Success: process exited non-zero as expected
	} else {
		t.Fatalf("Unexpected error type: %v", err)
	}
}
