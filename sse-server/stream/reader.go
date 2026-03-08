package stream

import (
	"context"
	"time"

	"github.com/redis/go-redis/v9"
)

// StreamReader abstracts Redis stream operations for testability.
// The real implementation wraps *redis.Client; tests use a mock.
type StreamReader interface {
	CreateGroup(ctx context.Context, stream, group, start string) error
	ReadGroup(ctx context.Context, group, consumer string, streams []string, count int64, block time.Duration) ([]redis.XStream, error)
	Ack(ctx context.Context, stream, group string, ids ...string) error
	TrimApprox(ctx context.Context, stream string, maxLen int64) error
	InfoGroups(ctx context.Context, stream string) ([]redis.XInfoGroup, error)
	DestroyGroup(ctx context.Context, stream, group string) error
}

// BroadcastTarget is the interface the Redis reader uses to push
// formatted SSE messages. In production this is *hub.Hub; tests
// supply a stub.
type BroadcastTarget interface {
	Broadcast(msg []byte)
}

// RedisStreamReader is the production implementation of StreamReader.
// Each method delegates to the corresponding command on a *redis.Client
// and returns .Result().
type RedisStreamReader struct {
	client *redis.Client
}

// NewRedisStreamReader wraps a *redis.Client as a StreamReader.
func NewRedisStreamReader(client *redis.Client) *RedisStreamReader {
	return &RedisStreamReader{client: client}
}

func (r *RedisStreamReader) CreateGroup(ctx context.Context, stream, group, start string) error {
	return r.client.XGroupCreateMkStream(ctx, stream, group, start).Err()
}

func (r *RedisStreamReader) ReadGroup(ctx context.Context, group, consumer string, streams []string, count int64, block time.Duration) ([]redis.XStream, error) {
	return r.client.XReadGroup(ctx, &redis.XReadGroupArgs{
		Group:    group,
		Consumer: consumer,
		Streams:  streams,
		Count:    count,
		Block:    block,
	}).Result()
}

func (r *RedisStreamReader) Ack(ctx context.Context, stream, group string, ids ...string) error {
	return r.client.XAck(ctx, stream, group, ids...).Err()
}

func (r *RedisStreamReader) TrimApprox(ctx context.Context, stream string, maxLen int64) error {
	return r.client.XTrimMaxLenApprox(ctx, stream, maxLen, 0).Err()
}

func (r *RedisStreamReader) InfoGroups(ctx context.Context, stream string) ([]redis.XInfoGroup, error) {
	return r.client.XInfoGroups(ctx, stream).Result()
}

func (r *RedisStreamReader) DestroyGroup(ctx context.Context, stream, group string) error {
	return r.client.XGroupDestroy(ctx, stream, group).Err()
}
