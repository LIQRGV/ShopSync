<?php

namespace Liqrgv\ShopSync\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseController extends Controller
{
    /**
     * Stream server-sent events
     *
     * @param Request $request
     * @return StreamedResponse
     */
    public function events(Request $request): StreamedResponse
    {
        return new StreamedResponse(function () {
            // Set headers for SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Disable Nginx buffering

            // Send initial connection message
            $this->sendEvent('connected', ['message' => 'SSE connection established']);

            // Keep the connection alive and send timestamp every minute
            $lastSent = time();
            $counter = 0;

            while (true) {
                // Check if client disconnected
                if (connection_aborted()) {
                    break;
                }

                $currentTime = time();

                // Send timestamp event every 60 seconds
                if ($currentTime - $lastSent >= 60) {
                    $counter++;
                    $this->sendEvent('timestamp', [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'unix_timestamp' => $currentTime,
                        'counter' => $counter
                    ]);
                    $lastSent = $currentTime;
                }

                // Send heartbeat every 30 seconds to keep connection alive
                if ($currentTime % 30 == 0) {
                    echo ": heartbeat\n\n";
                    ob_flush();
                    flush();
                }

                // Sleep for 1 second before checking again
                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Send an SSE event
     *
     * @param string $event
     * @param mixed $data
     * @param string|null $id
     * @return void
     */
    private function sendEvent(string $event, $data, ?string $id = null): void
    {
        if ($id !== null) {
            echo "id: {$id}\n";
        }

        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";

        ob_flush();
        flush();
    }
}