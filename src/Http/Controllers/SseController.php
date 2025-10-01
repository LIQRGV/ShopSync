<?php

namespace TheDiamondBox\ShopSync\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
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
}