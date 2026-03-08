package hub

import "sync/atomic"

// maxConsecutiveDrops is the threshold after which a slow client is
// force-disconnected. If a client's Send channel is full for this many
// consecutive broadcast attempts, the Hub closes its Done channel and
// removes it.
const maxConsecutiveDrops = 3

// Hub is a fan-out message broadcaster for WL mode. A single Redis
// reader goroutine pushes messages into Broadcast, and Run() delivers
// each message to every registered client's Send channel.
type Hub struct {
	clients     map[*Client]bool
	broadcast   chan []byte
	register    chan *Client
	unregister  chan *Client
	done        chan struct{}
	clientCount atomic.Int64
}

// NewHub creates a Hub ready to be started with Run().
func NewHub() *Hub {
	return &Hub{
		clients:   make(map[*Client]bool),
		broadcast: make(chan []byte, 256),
		register:  make(chan *Client),
		unregister: make(chan *Client),
		done:      make(chan struct{}),
	}
}

// Run is the main event loop. It must be called in its own goroutine.
// It processes register, unregister, and broadcast operations
// sequentially, ensuring that the clients map is only accessed from
// this single goroutine (no mutex needed).
//
// Run exits when Stop() is called (closing the done channel). On exit
// it closes every client's Send channel so that HTTP handler goroutines
// can detect the shutdown and drain gracefully.
func (h *Hub) Run() {
	for {
		select {
		case <-h.done:
			// Shutdown: close all client Send channels.
			for client := range h.clients {
				close(client.Send)
				delete(h.clients, client)
			}
			return

		case client := <-h.register:
			h.clients[client] = true
			h.clientCount.Add(1)

		case client := <-h.unregister:
			if _, ok := h.clients[client]; ok {
				close(client.Send)
				delete(h.clients, client)
				h.clientCount.Add(-1)
			}

		case msg := <-h.broadcast:
			for client := range h.clients {
				select {
				case client.Send <- msg:
					// Successful send -- reset drop counter.
					client.ConsecutiveDrops = 0
				default:
					// Client's Send channel is full -- slow client.
					client.ConsecutiveDrops++
					if client.ConsecutiveDrops >= maxConsecutiveDrops {
						close(client.Done)
						close(client.Send)
						delete(h.clients, client)
						h.clientCount.Add(-1)
					}
				}
			}
		}
	}
}

// Stop signals the Run loop to exit. It is safe to call from any
// goroutine but must only be called once.
func (h *Hub) Stop() {
	close(h.done)
}

// Register adds a client to the hub. It blocks until Run() processes
// the registration.
func (h *Hub) Register(client *Client) {
	h.register <- client
}

// Unregister removes a client from the hub. It blocks until Run()
// processes the removal.
func (h *Hub) Unregister(client *Client) {
	h.unregister <- client
}

// Broadcast enqueues a message for delivery to all registered clients.
func (h *Hub) Broadcast(msg []byte) {
	h.broadcast <- msg
}

// ClientCount returns the number of currently registered clients. It is
// safe to call from any goroutine.
func (h *Hub) ClientCount() int64 {
	return h.clientCount.Load()
}
