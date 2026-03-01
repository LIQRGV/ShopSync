<?php

namespace TheDiamondBox\ShopSync\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use TheDiamondBox\ShopSync\Services\SseService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseController extends Controller
{
    protected $sseService;

    public function __construct(SseService $sseService)
    {
        $this->sseService = $sseService;
    }

    /**
     * Stream server-sent events
     *
     * @param Request $request
     * @return StreamedResponse
     */
    public function events(Request $request): StreamedResponse
    {
        return $this->sseService->streamEvents($request);
    }

    /**
     * Get SSE service status (for debugging/monitoring)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        try {
            $status = $this->sseService->getConfigStatus();
            $validationErrors = $this->sseService->validateConfiguration();

            return response()->json([
                'status' => 'ok',
                'configuration' => $status,
                'validation_errors' => $validationErrors,
                'is_valid' => empty($validationErrors),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('SSE status check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Generate SSE authentication token for Go SSE server
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function token(Request $request): JsonResponse
    {
        try {
            // Get user ID from authenticated user or use a default identifier
            $userId = $request->user()?->id ?? $request->ip();

            // Generate a random token
            $token = Str::random(64);

            // Store token in Redis with 1 hour TTL
            $key = 'sse:tokens:' . $token;
            Redis::setex($key, 3600, (string) $userId);

            Log::info('SSE token generated', [
                'user_id' => $userId,
                'token_prefix' => substr($token, 0, 8) . '...',
                'expires_in' => 3600
            ]);

            return response()->json([
                'token' => $token,
                'expires_in' => 3600,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('SSE token generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to generate token',
                'message' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }
}