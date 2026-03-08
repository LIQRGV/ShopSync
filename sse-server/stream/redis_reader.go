package stream

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"strings"
	"time"

	"github.com/redis/go-redis/v9"
)

const (
	defaultGroupName    = "sse-go-reader"
	defaultConsumerName = "consumer-1"
	readCount           = 10
	blockDuration       = 200 * time.Millisecond
	trimInterval        = 5 * time.Minute
	trimMaxLen          = 10000
	legacyGroupPrefix   = "sse-group-"
)

// RedisReader reads messages from a Redis stream using a consumer group
// and broadcasts formatted SSE events to a BroadcastTarget (the Hub).
// It is used exclusively in WL mode.
type RedisReader struct {
	reader       StreamReader
	prefix       string
	hub          BroadcastTarget
	streamKey    string
	groupName    string
	consumerName string
}

// NewRedisReader creates a RedisReader that reads from
// {prefix}products.updates.stream and pushes SSE-formatted messages
// to the given BroadcastTarget.
func NewRedisReader(reader StreamReader, prefix string, hub BroadcastTarget) *RedisReader {
	return &RedisReader{
		reader:       reader,
		prefix:       prefix,
		hub:          hub,
		streamKey:    prefix + "products.updates.stream",
		groupName:    defaultGroupName,
		consumerName: defaultConsumerName,
	}
}

// streamMessage is the JSON structure stored in each Redis stream
// message's "data" field.
type streamMessage struct {
	Event     string          `json:"event"`
	Data      json.RawMessage `json:"data"`
	Timestamp string          `json:"timestamp"`
}

// Run starts the reader loop. It blocks until ctx is cancelled.
//
// On startup it:
//  1. Creates the consumer group (ignoring BUSYGROUP if it already exists).
//  2. Cleans up legacy PHP consumer groups matching "sse-group-*".
//
// In the main loop it:
//   - Reads messages with XREADGROUP.
//   - Parses each message, formats it as an SSE event, and broadcasts it.
//   - ACKs each processed message.
//   - Re-creates the consumer group if it receives a NOGROUP error.
//   - Trims the stream every 5 minutes.
func (r *RedisReader) Run(ctx context.Context) {
	r.ensureGroup(ctx)
	r.cleanLegacyGroups(ctx)

	trimTicker := time.NewTicker(trimInterval)
	defer trimTicker.Stop()

	for {
		select {
		case <-ctx.Done():
			log.Println("RedisReader: context cancelled, stopping")
			return
		case <-trimTicker.C:
			r.trimStream(ctx)
		default:
			r.readAndBroadcast(ctx)
		}
	}
}

// ensureGroup creates the consumer group. If the group already exists
// (BUSYGROUP error), the error is silently ignored.
func (r *RedisReader) ensureGroup(ctx context.Context) {
	err := r.reader.CreateGroup(ctx, r.streamKey, r.groupName, "$")
	if err != nil && !isBusyGroupError(err) {
		log.Printf("RedisReader: failed to create consumer group: %v", err)
	}
}

// cleanLegacyGroups removes consumer groups created by the old PHP SSE
// implementation. Any group whose name starts with "sse-group-" is
// destroyed.
func (r *RedisReader) cleanLegacyGroups(ctx context.Context) {
	groups, err := r.reader.InfoGroups(ctx, r.streamKey)
	if err != nil {
		log.Printf("RedisReader: failed to list consumer groups: %v", err)
		return
	}

	for _, g := range groups {
		if strings.HasPrefix(g.Name, legacyGroupPrefix) {
			if err := r.reader.DestroyGroup(ctx, r.streamKey, g.Name); err != nil {
				log.Printf("RedisReader: failed to destroy legacy group %s: %v", g.Name, err)
			} else {
				log.Printf("RedisReader: destroyed legacy group %s", g.Name)
			}
		}
	}
}

// readAndBroadcast performs one XREADGROUP call, processes all returned
// messages, and ACKs them. If a NOGROUP error is returned the consumer
// group is re-created.
func (r *RedisReader) readAndBroadcast(ctx context.Context) {
	streams, err := r.reader.ReadGroup(
		ctx,
		r.groupName,
		r.consumerName,
		[]string{r.streamKey, ">"},
		readCount,
		blockDuration,
	)
	if err != nil {
		if isNoGroupError(err) {
			log.Println("RedisReader: NOGROUP error, re-creating consumer group")
			r.ensureGroup(ctx)
			return
		}
		// redis.Nil means the BLOCK timed out with no messages -- not an error.
		if err == redis.Nil {
			return
		}
		// Context cancellation is expected during shutdown.
		if ctx.Err() != nil {
			return
		}
		log.Printf("RedisReader: XREADGROUP error: %v", err)
		return
	}

	for _, s := range streams {
		for _, msg := range s.Messages {
			r.processMessage(ctx, msg)
		}
	}
}

// processMessage parses the "data" field of a Redis stream message,
// formats it as an SSE event, broadcasts it, and ACKs it.
func (r *RedisReader) processMessage(ctx context.Context, msg redis.XMessage) {
	rawData, ok := msg.Values["data"]
	if !ok {
		log.Printf("RedisReader: message %s has no 'data' field, skipping", msg.ID)
		r.ackMessage(ctx, msg.ID)
		return
	}

	dataStr, ok := rawData.(string)
	if !ok {
		log.Printf("RedisReader: message %s 'data' field is not a string, skipping", msg.ID)
		r.ackMessage(ctx, msg.ID)
		return
	}

	var sm streamMessage
	if err := json.Unmarshal([]byte(dataStr), &sm); err != nil {
		log.Printf("RedisReader: message %s JSON parse error: %v", msg.ID, err)
		r.ackMessage(ctx, msg.ID)
		return
	}

	// Re-serialize the nested data object as compact JSON for the SSE
	// data line. json.RawMessage preserves the original bytes, but
	// re-serializing through json.Marshal produces compact output.
	dataJSON, err := json.Marshal(sm.Data)
	if err != nil {
		log.Printf("RedisReader: message %s failed to re-serialize data: %v", msg.ID, err)
		r.ackMessage(ctx, msg.ID)
		return
	}

	sseEvent := formatSSE(sm.Event, string(dataJSON))
	r.hub.Broadcast([]byte(sseEvent))

	r.ackMessage(ctx, msg.ID)
}

// ackMessage ACKs a single message. Errors are logged but not fatal.
func (r *RedisReader) ackMessage(ctx context.Context, id string) {
	if err := r.reader.Ack(ctx, r.streamKey, r.groupName, id); err != nil {
		log.Printf("RedisReader: failed to ACK message %s: %v", id, err)
	}
}

// trimStream runs XTRIM with approximate MAXLEN to keep the stream
// from growing unbounded.
func (r *RedisReader) trimStream(ctx context.Context) {
	if err := r.reader.TrimApprox(ctx, r.streamKey, trimMaxLen); err != nil {
		log.Printf("RedisReader: XTRIM error: %v", err)
	}
}

// formatSSE builds an SSE-formatted string from an event type and a
// JSON data payload:
//
//	event: {eventType}\ndata: {dataJSON}\n\n
func formatSSE(eventType, dataJSON string) string {
	return fmt.Sprintf("event: %s\ndata: %s\n\n", eventType, dataJSON)
}

// isBusyGroupError returns true if the error indicates the consumer
// group already exists (Redis BUSYGROUP response).
func isBusyGroupError(err error) bool {
	return err != nil && strings.Contains(err.Error(), "BUSYGROUP")
}

// isNoGroupError returns true if the error indicates the consumer group
// does not exist (Redis NOGROUP response).
func isNoGroupError(err error) bool {
	return err != nil && strings.Contains(err.Error(), "NOGROUP")
}
