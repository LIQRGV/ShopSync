package config

import (
	"log"
	"os"
	"regexp"
	"strconv"
	"strings"

	"github.com/joho/godotenv"
)

// Config holds all configuration for the Go SSE server.
// Fields are loaded with this priority: SSE_GO_* env var > Laravel env var > default value.
type Config struct {
	Socket            string // SSE_GO_SOCKET — Unix socket path (production)
	Port              int    // SSE_GO_PORT, default 8081 (local dev)
	RedisAddr         string // SSE_GO_REDIS_ADDR > REDIS_HOST:REDIS_PORT, default "127.0.0.1:6379"
	RedisDB           int    // SSE_GO_REDIS_DB > REDIS_DB, default 0
	RedisPassword     string // SSE_GO_REDIS_PASSWORD > REDIS_PASSWORD
	RedisPrefix       string // SSE_GO_REDIS_PREFIX > REDIS_PREFIX > derived from APP_NAME
	AppKey            string // SSE_GO_APP_KEY > APP_KEY — REQUIRED
	Mode              string // SSE_GO_MODE > PRODUCT_PACKAGE_MODE, default "wl"
	HeartbeatInterval int    // SSE_GO_HEARTBEAT_INTERVAL, default 30 (seconds)
	ConnectionTimeout int    // SSE_GO_CONNECTION_TIMEOUT, default 600 (seconds)
	MaxClients        int    // SSE_GO_MAX_CLIENTS, default 1000
	RedisStreamKey    string // Computed: {RedisPrefix}products.updates.stream
}

// Load reads the Laravel .env file (if present) and builds a Config
// using the priority: SSE_GO_* override > Laravel env var > default.
// It calls log.Fatal if AppKey is missing.
func Load() *Config {
	// Load Laravel .env file — ignore errors (file may not exist)
	_ = godotenv.Load(".env")

	cfg := &Config{}

	// Socket (Go-specific only)
	cfg.Socket = getEnv("SSE_GO_SOCKET", "")

	// Port (Go-specific only)
	cfg.Port = getEnvInt("SSE_GO_PORT", 8081)

	// RedisAddr: SSE_GO_REDIS_ADDR > REDIS_HOST:REDIS_PORT > default
	cfg.RedisAddr = getEnvWithFallback("SSE_GO_REDIS_ADDR", "")
	if cfg.RedisAddr == "" {
		host := getEnv("REDIS_HOST", "127.0.0.1")
		port := getEnv("REDIS_PORT", "6379")
		cfg.RedisAddr = host + ":" + port
	}

	// RedisDB: SSE_GO_REDIS_DB > REDIS_DB > 0
	redisDBStr := getEnvWithFallback("SSE_GO_REDIS_DB", "")
	if redisDBStr != "" {
		cfg.RedisDB = mustParseInt(redisDBStr, "SSE_GO_REDIS_DB")
	} else {
		cfg.RedisDB = getEnvInt("REDIS_DB", 0)
	}

	// RedisPassword: SSE_GO_REDIS_PASSWORD > REDIS_PASSWORD > ""
	cfg.RedisPassword = getEnvWithFallbackChain("SSE_GO_REDIS_PASSWORD", "REDIS_PASSWORD", "")

	// RedisPrefix: SSE_GO_REDIS_PREFIX > REDIS_PREFIX > derived from APP_NAME > "laravel_database_"
	cfg.RedisPrefix = resolveRedisPrefix()

	// AppKey: SSE_GO_APP_KEY > APP_KEY — REQUIRED
	cfg.AppKey = getEnvWithFallbackChain("SSE_GO_APP_KEY", "APP_KEY", "")
	if cfg.AppKey == "" {
		log.Fatal("APP_KEY is required: set SSE_GO_APP_KEY or APP_KEY environment variable")
	}

	// Mode: SSE_GO_MODE > PRODUCT_PACKAGE_MODE > "wl"
	cfg.Mode = getEnvWithFallbackChain("SSE_GO_MODE", "PRODUCT_PACKAGE_MODE", "wl")

	// HeartbeatInterval (Go-specific only)
	cfg.HeartbeatInterval = getEnvInt("SSE_GO_HEARTBEAT_INTERVAL", 30)

	// ConnectionTimeout (Go-specific only)
	cfg.ConnectionTimeout = getEnvInt("SSE_GO_CONNECTION_TIMEOUT", 600)

	// MaxClients (Go-specific only)
	cfg.MaxClients = getEnvInt("SSE_GO_MAX_CLIENTS", 1000)

	// Computed field
	cfg.RedisStreamKey = cfg.RedisPrefix + "products.updates.stream"

	return cfg
}

// resolveRedisPrefix determines the Redis key prefix using the priority chain:
// SSE_GO_REDIS_PREFIX > REDIS_PREFIX > derived from APP_NAME > "laravel_database_"
func resolveRedisPrefix() string {
	if v := os.Getenv("SSE_GO_REDIS_PREFIX"); v != "" {
		return v
	}
	if v := os.Getenv("REDIS_PREFIX"); v != "" {
		return v
	}
	if appName := os.Getenv("APP_NAME"); appName != "" {
		return DeriveRedisPrefix(appName)
	}
	return "laravel_database_"
}

// DeriveRedisPrefix converts an APP_NAME into a Redis key prefix using
// the same logic as Laravel's Str::slug(APP_NAME, '_') . '_database_':
//   - Convert to lowercase
//   - Replace non-alphanumeric characters with underscore
//   - Collapse consecutive underscores
//   - Trim leading/trailing underscores
//   - Append "_database_"
func DeriveRedisPrefix(appName string) string {
	s := strings.ToLower(appName)

	// Replace non-alphanumeric with underscore
	nonAlphaNum := regexp.MustCompile(`[^a-z0-9]+`)
	s = nonAlphaNum.ReplaceAllString(s, "_")

	// Collapse consecutive underscores
	multiUnderscore := regexp.MustCompile(`_+`)
	s = multiUnderscore.ReplaceAllString(s, "_")

	// Trim leading/trailing underscores
	s = strings.Trim(s, "_")

	return s + "_database_"
}

// getEnv returns the value of the environment variable or the default.
func getEnv(key, defaultVal string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return defaultVal
}

// getEnvWithFallback returns the value of the environment variable or the fallback.
// Unlike getEnv, this is used when we need to distinguish between "not set" and "empty".
func getEnvWithFallback(key, fallback string) string {
	if v, ok := os.LookupEnv(key); ok && v != "" {
		return v
	}
	return fallback
}

// getEnvWithFallbackChain checks the primary env var, then the fallback env var,
// then returns the default.
func getEnvWithFallbackChain(primary, fallback, defaultVal string) string {
	if v := os.Getenv(primary); v != "" {
		return v
	}
	if v := os.Getenv(fallback); v != "" {
		return v
	}
	return defaultVal
}

// getEnvInt returns the integer value of the environment variable or the default.
func getEnvInt(key string, defaultVal int) int {
	v := os.Getenv(key)
	if v == "" {
		return defaultVal
	}
	return mustParseInt(v, key)
}

// mustParseInt parses a string as int, calling log.Fatal on failure.
func mustParseInt(s, name string) int {
	n, err := strconv.Atoi(s)
	if err != nil {
		log.Fatalf("Invalid integer value for %s: %q", name, s)
	}
	return n
}
