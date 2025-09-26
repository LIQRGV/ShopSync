<?php

namespace Liqrgv\ShopSync\Services\Contracts;

use Illuminate\Http\Request;

interface SseStreamerInterface
{
    /**
     * Stream SSE events to the client
     *
     * @param string $sessionId Unique session identifier
     * @param Request $request The HTTP request
     * @return void
     */
    public function stream(string $sessionId, Request $request): void;
}