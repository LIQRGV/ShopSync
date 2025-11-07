/**
 * SSE Client for real-time product updates
 * Handles Server-Sent Events connection and message processing
 */
export class ProductSSEClient {
    constructor(endpoint, clientId) {
        this.endpoint = endpoint;
        this.clientId = clientId;
        this.eventSource = null;
        this.abortController = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 10;
        this.reconnectDelay = 1000; // Start with 1 second
        this.maxReconnectDelay = 30000; // Max 30 seconds
        this.listeners = new Map();
        this.connectionListeners = [];
        this.lastEventTime = null;
        this.heartbeatInterval = null;
        this.connectionTimeout = null;
        // State for incomplete SSE events across chunks
        this.incompleteEvent = {
            eventType: 'message',
            eventData: '',
            eventId: null
        };
    }

    /**
     * Connect to SSE endpoint
     */
    connect() {
        if (this.eventSource) {
            this.disconnect();
        }

        try {
            const url = new URL(this.endpoint, window.location.origin);
            // Use custom EventSource implementation that supports headers
            this.createCustomEventSource(url.toString());

            // Start heartbeat monitoring
            this.startHeartbeatMonitoring();

            return true;
        } catch (error) {
            console.error('[SSE] Connection error:', error);
            this.handleConnectionError(error);
            return false;
        }
    }

    /**
     * Create custom EventSource implementation using fetch with headers
     */
    createCustomEventSource(url) {
        const controller = new AbortController();
        this.abortController = controller;

        // Headers to send with the request
        const headers = {
            'Accept': 'text/event-stream',
            'Cache-Control': 'no-cache',
        };

        // Add client-id header if available
        if (this.clientId) {
            headers['client-id'] = this.clientId;
        }

        // Create a fetch-based EventSource
        fetch(url, {
            method: 'GET',
            headers: headers,
            signal: controller.signal
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            if (!response.body) {
                throw new Error('Response body is null');
            }

            // Create a mock EventSource object
            this.eventSource = this.createMockEventSource(response.body.getReader());

            // Setup event listeners
            this.setupEventListeners();

            // Trigger open event
            if (this.eventSource.onopen) {
                this.eventSource.onopen({ type: 'open' });
            }

        })
        .catch(error => {
            console.error('[SSE] Fetch error:', error);
            this.handleConnectionError(error);
        });
    }

    /**
     * Create a mock EventSource object that behaves like the native EventSource
     */
    createMockEventSource(reader) {
        const eventSource = {
            readyState: 1, // OPEN
            onopen: null,
            onmessage: null,
            onerror: null,
            addEventListener: function(type, listener) {
                this['on' + type] = listener;
            },
            close: function() {
                this.readyState = 2; // CLOSED
                // The abortController is handled by the parent class
            }
        };

        // Start reading the stream
        this.readEventStream(reader, eventSource);

        return eventSource;
    }

    /**
     * Read and parse the event stream
     */
    async readEventStream(reader, eventSource) {
        const decoder = new TextDecoder();
        let buffer = '';

        try {
            while (true) {
                const { done, value } = await reader.read();

                if (done) {
                    console.log('[SSE] Stream ended');
                    this.handleDisconnection();
                    break;
                }

                // Decode the chunk and add to buffer
                buffer += decoder.decode(value, { stream: true });

                // Process complete messages
                const lines = buffer.split('\n');
                buffer = lines.pop() || ''; // Keep incomplete line in buffer

                this.processEventLines(lines, eventSource);
            }
        } catch (error) {
            console.error('[SSE] Stream reading error:', error);
            if (eventSource.onerror) {
                eventSource.onerror({ type: 'error', error });
            }
            this.handleConnectionError(error);
        }
    }

    /**
     * Process event stream lines
     */
    processEventLines(lines, eventSource) {
        // Use incomplete event state from previous chunk (if any)
        let eventType = this.incompleteEvent.eventType;
        let eventData = this.incompleteEvent.eventData;
        let eventId = this.incompleteEvent.eventId;

        for (const line of lines) {
            if (line === '') {
                // Empty line indicates end of event
                if (eventData) {
                    const event = {
                        type: eventType,
                        data: eventData.trim(),
                        lastEventId: eventId
                    };

                    // Trigger appropriate event handler
                    if (eventType === 'message' && eventSource.onmessage) {
                        eventSource.onmessage(event);
                    } else if (this.customEventHandlers && this.customEventHandlers[eventType]) {
                        this.customEventHandlers[eventType](event);
                    }

                    // Reset for next event
                    eventType = 'message';
                    eventData = '';
                    eventId = null;
                }
            } else if (line.startsWith('event:')) {
                eventType = line.substring(6).trim();
            } else if (line.startsWith('data:')) {
                eventData += line.substring(5).trim() + '\n';
            } else if (line.startsWith('id:')) {
                eventId = line.substring(3).trim();
            } else if (line.startsWith('retry:')) {
                const retryTime = parseInt(line.substring(6).trim(), 10);
                if (!isNaN(retryTime)) {
                    this.reconnectDelay = retryTime;
                }
            }
            // Ignore comments (lines starting with :)
        }

        // Save incomplete event state for next chunk
        this.incompleteEvent = {
            eventType: eventType,
            eventData: eventData,
            eventId: eventId
        };
    }

    /**
     * Setup SSE event listeners
     */
    setupEventListeners() {
        if (!this.eventSource) return;

        // Connection opened
        this.eventSource.onopen = (event) => {
            console.log('[SSE] Connection established');
            this.isConnected = true;
            this.reconnectAttempts = 0;
            this.reconnectDelay = 1000;
            this.lastEventTime = Date.now();
            this.notifyConnectionListeners('connected');
            this.resetConnectionTimeout();
        };

        // Error occurred
        this.eventSource.onerror = (event) => {
            console.error('[SSE] Connection error:', event);

            // Check readyState constants (for compatibility with both native and custom EventSource)
            const CLOSED = 2;
            const CONNECTING = 0;

            if (this.eventSource.readyState === CLOSED) {
                this.handleDisconnection();
            } else if (this.eventSource.readyState === CONNECTING) {
                console.log('[SSE] Reconnecting...');
                this.notifyConnectionListeners('reconnecting');
            }
        };

        // Default message handler
        this.eventSource.onmessage = (event) => {
            this.handleMessage(event);
        };

        // Setup custom event handlers using our event listener approach
        this.setupCustomEventHandlers();
    }

    /**
     * Setup custom event handlers for specific event types
     */
    setupCustomEventHandlers() {
        // Map custom event handlers to our mock EventSource
        const eventHandlers = {
            'product.updated': (event) => this.handleProductUpdate(event),
            'product.created': (event) => this.handleProductCreated(event),
            'product.deleted': (event) => this.handleProductDeleted(event),
            'product.restored': (event) => this.handleProductRestored(event),
            'product.imported': (event) => this.handleProductImported(event),
            'products.bulk.updated': (event) => this.handleBulkUpdate(event),
            'ping': (event) => this.handlePing(event),
            'error': (event) => this.handleServerError(event)
        };

        // Store event handlers for our custom event processing
        this.customEventHandlers = eventHandlers;
    }

    /**
     * Handle default messages
     */
    handleMessage(event) {
        try {
            const data = JSON.parse(event.data);
            this.lastEventTime = Date.now();
            this.resetConnectionTimeout();

            console.log('[SSE] Message received:', data);
            this.notifyListeners('message', data);
        } catch (error) {
            console.error('[SSE] Error parsing message:', error, event.data);
        }
    }

    /**
     * Handle product update event
     */
    handleProductUpdate(event) {
        try {
            const data = JSON.parse(event.data);
            this.lastEventTime = Date.now();
            this.resetConnectionTimeout();

            console.log('[SSE] Product updated:', data);
            this.notifyListeners('product.updated', data);
        } catch (error) {
            console.error('[SSE] Error handling product update:', error);
        }
    }

    /**
     * Handle product created event
     */
    handleProductCreated(event) {
        try {
            const data = JSON.parse(event.data);
            this.lastEventTime = Date.now();
            this.resetConnectionTimeout();

            console.log('[SSE] Product created:', data);
            this.notifyListeners('product.created', data);
        } catch (error) {
            console.error('[SSE] Error handling product created:', error);
        }
    }

    /**
     * Handle product deleted event
     */
    handleProductDeleted(event) {
        try {
            const data = JSON.parse(event.data);
            this.lastEventTime = Date.now();
            this.resetConnectionTimeout();

            console.log('[SSE] Product deleted:', data);
            this.notifyListeners('product.deleted', data);
        } catch (error) {
            console.error('[SSE] Error handling product deleted:', error);
        }
    }

    /**
     * Handle product restored event
     */
    handleProductRestored(event) {
        try {
            const data = JSON.parse(event.data);
            this.lastEventTime = Date.now();
            this.resetConnectionTimeout();

            console.log('[SSE] Product restored:', data);
            this.notifyListeners('product.restored', data);
        } catch (error) {
            console.error('[SSE] Error handling product restored:', error);
        }
    }

    /**
     * Handle bulk update event
     */
    handleBulkUpdate(event) {
        try {
            const data = JSON.parse(event.data);
            this.lastEventTime = Date.now();
            this.resetConnectionTimeout();

            console.log('[SSE] Bulk update:', data);
            this.notifyListeners('products.bulk.updated', data);
        } catch (error) {
            console.error('[SSE] Error handling bulk update:', error);
        }
    }

    /**
     * Handle product imported event
     */
    handleProductImported(event) {
        try {
            const data = JSON.parse(event.data);
            this.lastEventTime = Date.now();
            this.resetConnectionTimeout();

            console.log('[SSE] Products imported:', data);
            this.notifyListeners('product.imported', data);
        } catch (error) {
            console.error('[SSE] Error handling product import:', error);
        }
    }

    /**
     * Handle ping/heartbeat
     */
    handlePing(event) {
        this.lastEventTime = Date.now();
        this.resetConnectionTimeout();
        console.log('[SSE] Ping received');
        this.notifyListeners('ping', { timestamp: this.lastEventTime });
    }

    /**
     * Handle server error event
     */
    handleServerError(event) {
        try {
            const data = JSON.parse(event.data);
            console.error('[SSE] Server error:', data);
            this.notifyListeners('server.error', data);
        } catch (error) {
            console.error('[SSE] Error parsing server error:', error);
        }
    }

    /**
     * Handle disconnection
     */
    handleDisconnection() {
        this.isConnected = false;
        this.eventSource = null;
        this.notifyConnectionListeners('disconnected');

        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.scheduleReconnect();
        } else {
            console.error('[SSE] Max reconnection attempts reached');
            this.notifyConnectionListeners('failed');
        }
    }

    /**
     * Handle connection error
     */
    handleConnectionError(error) {
        this.isConnected = false;
        this.notifyConnectionListeners('error', error);
        this.scheduleReconnect();
    }

    /**
     * Schedule reconnection attempt
     */
    scheduleReconnect() {
        this.reconnectAttempts++;
        const delay = Math.min(
            this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1),
            this.maxReconnectDelay
        );

        console.log(`[SSE] Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts})`);

        setTimeout(() => {
            if (!this.isConnected) {
                this.connect();
            }
        }, delay);
    }

    /**
     * Start heartbeat monitoring
     */
    startHeartbeatMonitoring() {
        this.stopHeartbeatMonitoring();

        // Check for connection health every 30 seconds
        this.heartbeatInterval = setInterval(() => {
            if (this.lastEventTime) {
                const timeSinceLastEvent = Date.now() - this.lastEventTime;
                // If no event received in 60 seconds, consider connection stale
                if (timeSinceLastEvent > 60000) {
                    console.warn('[SSE] Connection appears stale, reconnecting...');
                    this.reconnect();
                }
            }
        }, 30000);
    }

    /**
     * Stop heartbeat monitoring
     */
    stopHeartbeatMonitoring() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
    }

    /**
     * Reset connection timeout
     */
    resetConnectionTimeout() {
        if (this.connectionTimeout) {
            clearTimeout(this.connectionTimeout);
        }

        // Set a timeout for 90 seconds (allowing for network delays)
        this.connectionTimeout = setTimeout(() => {
            if (this.isConnected && this.lastEventTime) {
                const timeSinceLastEvent = Date.now() - this.lastEventTime;
                if (timeSinceLastEvent > 90000) {
                    console.warn('[SSE] Connection timeout, reconnecting...');
                    this.reconnect();
                }
            }
        }, 90000);
    }

    /**
     * Disconnect from SSE
     */
    disconnect() {
        // Abort fetch request if using custom implementation
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }

        // Close EventSource if using native implementation
        if (this.eventSource) {
            if (typeof this.eventSource.close === 'function') {
                this.eventSource.close();
            }
            this.eventSource = null;
        }

        this.isConnected = false;
        this.stopHeartbeatMonitoring();

        if (this.connectionTimeout) {
            clearTimeout(this.connectionTimeout);
            this.connectionTimeout = null;
        }

        this.notifyConnectionListeners('disconnected');
        console.log('[SSE] Disconnected');
    }

    /**
     * Reconnect to SSE
     */
    reconnect() {
        this.disconnect();
        this.connect();
    }

    /**
     * Register event listener
     */
    on(eventType, callback) {
        if (!this.listeners.has(eventType)) {
            this.listeners.set(eventType, []);
        }
        this.listeners.get(eventType).push(callback);
    }

    /**
     * Remove event listener
     */
    off(eventType, callback) {
        if (this.listeners.has(eventType)) {
            const callbacks = this.listeners.get(eventType);
            const index = callbacks.indexOf(callback);
            if (index > -1) {
                callbacks.splice(index, 1);
            }
        }
    }

    /**
     * Notify event listeners
     */
    notifyListeners(eventType, data) {
        if (this.listeners.has(eventType)) {
            this.listeners.get(eventType).forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`[SSE] Error in listener for ${eventType}:`, error);
                }
            });
        }
    }

    /**
     * Register connection state listener
     */
    onConnectionStateChange(callback) {
        this.connectionListeners.push(callback);
    }

    /**
     * Notify connection state listeners
     */
    notifyConnectionListeners(state, data = null) {
        this.connectionListeners.forEach(callback => {
            try {
                callback(state, data);
            } catch (error) {
                console.error('[SSE] Error in connection listener:', error);
            }
        });
    }

    /**
     * Get connection status
     */
    getConnectionStatus() {
        return {
            isConnected: this.isConnected,
            readyState: this.eventSource ? this.eventSource.readyState : null,
            reconnectAttempts: this.reconnectAttempts,
            lastEventTime: this.lastEventTime
        };
    }

    /**
     * Cleanup and destroy
     */
    destroy() {
        this.disconnect();
        this.listeners.clear();
        this.connectionListeners = [];
    }
}
