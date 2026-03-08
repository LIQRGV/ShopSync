package handler

import (
	"context"
	"fmt"

	"github.com/redis/go-redis/v9"
)

// ConnectionCounter abstracts the tracking of active SSE connections.
// The real implementation uses a Redis key; tests use a mock.
type ConnectionCounter interface {
	IncrementConnections(ctx context.Context) error
	DecrementConnections(ctx context.Context) error
	GetConnectionCount(ctx context.Context) (int64, error)
}

// RedisConnectionCounter implements ConnectionCounter using a Redis key.
// The key is {prefix}sse:active_connections.
type RedisConnectionCounter struct {
	client *redis.Client
	key    string
}

// NewRedisConnectionCounter creates a RedisConnectionCounter that uses
// the given Redis client and key prefix.
func NewRedisConnectionCounter(client *redis.Client, prefix string) *RedisConnectionCounter {
	return &RedisConnectionCounter{
		client: client,
		key:    prefix + "sse:active_connections",
	}
}

// IncrementConnections increments the active connection count by 1.
func (c *RedisConnectionCounter) IncrementConnections(ctx context.Context) error {
	if err := c.client.Incr(ctx, c.key).Err(); err != nil {
		return fmt.Errorf("redis incr %s: %w", c.key, err)
	}
	return nil
}

// DecrementConnections decrements the active connection count by 1,
// clamping to 0 if the result would be negative.
func (c *RedisConnectionCounter) DecrementConnections(ctx context.Context) error {
	result, err := c.client.Decr(ctx, c.key).Result()
	if err != nil {
		return fmt.Errorf("redis decr %s: %w", c.key, err)
	}
	if result < 0 {
		if err := c.client.Set(ctx, c.key, 0, 0).Err(); err != nil {
			return fmt.Errorf("redis set %s to 0: %w", c.key, err)
		}
	}
	return nil
}

// GetConnectionCount returns the current active connection count.
// Returns 0 if the key does not exist (redis.Nil).
func (c *RedisConnectionCounter) GetConnectionCount(ctx context.Context) (int64, error) {
	val, err := c.client.Get(ctx, c.key).Int64()
	if err != nil {
		if err == redis.Nil {
			return 0, nil
		}
		return 0, fmt.Errorf("redis get %s: %w", c.key, err)
	}
	return val, nil
}
