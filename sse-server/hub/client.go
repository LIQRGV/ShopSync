package hub

// Client represents a single SSE connection. The Hub manages Client
// lifecycle: registering, broadcasting messages, and force-disconnecting
// slow clients.
type Client struct {
	// ID is a unique identifier for this client connection.
	ID string

	// Send is the buffered channel through which the Hub delivers SSE
	// messages to the HTTP handler goroutine serving this client.
	Send chan []byte

	// Done is closed by the Hub to signal the HTTP handler that this
	// client has been force-disconnected (e.g., due to slow consumption).
	Done chan struct{}

	// ConsecutiveDrops tracks how many broadcast messages in a row were
	// dropped because Send was full. The Hub force-disconnects a client
	// after 3 consecutive drops.
	ConsecutiveDrops int
}

// sendBufferSize is the capacity of each Client's Send channel.
const sendBufferSize = 64

// NewClient creates a Client with the given id, a buffered Send channel
// of capacity 64, and an open Done channel.
func NewClient(id string) *Client {
	return &Client{
		ID:   id,
		Send: make(chan []byte, sendBufferSize),
		Done: make(chan struct{}),
	}
}
