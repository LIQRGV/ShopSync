package handler_test

import (
	"context"
	"errors"
	"sync"
	"testing"

	"github.com/LIQRGV/ShopSync/sse-server/handler"
)

// mockConnectionCounter is a test double for ConnectionCounter that
// tracks calls and allows injecting errors.
type mockConnectionCounter struct {
	mu    sync.Mutex
	count int64

	incrementErr error
	decrementErr error
	getCountErr  error
}

func (m *mockConnectionCounter) IncrementConnections(_ context.Context) error {
	if m.incrementErr != nil {
		return m.incrementErr
	}
	m.mu.Lock()
	m.count++
	m.mu.Unlock()
	return nil
}

func (m *mockConnectionCounter) DecrementConnections(_ context.Context) error {
	if m.decrementErr != nil {
		return m.decrementErr
	}
	m.mu.Lock()
	m.count--
	if m.count < 0 {
		m.count = 0
	}
	m.mu.Unlock()
	return nil
}

func (m *mockConnectionCounter) GetConnectionCount(_ context.Context) (int64, error) {
	if m.getCountErr != nil {
		return 0, m.getCountErr
	}
	m.mu.Lock()
	defer m.mu.Unlock()
	return m.count, nil
}

func (m *mockConnectionCounter) getCount() int64 {
	m.mu.Lock()
	defer m.mu.Unlock()
	return m.count
}

// Compile-time check that mockConnectionCounter implements ConnectionCounter.
var _ handler.ConnectionCounter = (*mockConnectionCounter)(nil)

func TestMockConnectionCounter_IncrementDecrement(t *testing.T) {
	t.Parallel()
	ctx := context.Background()
	m := &mockConnectionCounter{}

	// Increment 3 times.
	for i := 0; i < 3; i++ {
		if err := m.IncrementConnections(ctx); err != nil {
			t.Fatalf("IncrementConnections: unexpected error: %v", err)
		}
	}

	count, err := m.GetConnectionCount(ctx)
	if err != nil {
		t.Fatalf("GetConnectionCount: unexpected error: %v", err)
	}
	if count != 3 {
		t.Errorf("expected count=3, got %d", count)
	}

	// Decrement 2 times.
	for i := 0; i < 2; i++ {
		if err := m.DecrementConnections(ctx); err != nil {
			t.Fatalf("DecrementConnections: unexpected error: %v", err)
		}
	}

	count, err = m.GetConnectionCount(ctx)
	if err != nil {
		t.Fatalf("GetConnectionCount: unexpected error: %v", err)
	}
	if count != 1 {
		t.Errorf("expected count=1, got %d", count)
	}
}

func TestMockConnectionCounter_ClampToZero(t *testing.T) {
	t.Parallel()
	ctx := context.Background()
	m := &mockConnectionCounter{}

	// Decrement when count is already 0 should clamp to 0.
	if err := m.DecrementConnections(ctx); err != nil {
		t.Fatalf("DecrementConnections: unexpected error: %v", err)
	}

	count, err := m.GetConnectionCount(ctx)
	if err != nil {
		t.Fatalf("GetConnectionCount: unexpected error: %v", err)
	}
	if count != 0 {
		t.Errorf("expected count=0 after clamp, got %d", count)
	}
}

func TestMockConnectionCounter_Errors(t *testing.T) {
	t.Parallel()
	ctx := context.Background()
	testErr := errors.New("redis down")

	tests := []struct {
		name string
		fn   func(m *mockConnectionCounter) error
	}{
		{
			name: "IncrementConnections error",
			fn: func(m *mockConnectionCounter) error {
				m.incrementErr = testErr
				return m.IncrementConnections(ctx)
			},
		},
		{
			name: "DecrementConnections error",
			fn: func(m *mockConnectionCounter) error {
				m.decrementErr = testErr
				return m.DecrementConnections(ctx)
			},
		},
		{
			name: "GetConnectionCount error",
			fn: func(m *mockConnectionCounter) error {
				m.getCountErr = testErr
				_, err := m.GetConnectionCount(ctx)
				return err
			},
		},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()
			m := &mockConnectionCounter{}
			err := tc.fn(m)
			if err == nil {
				t.Errorf("expected error, got nil")
			}
		})
	}
}
