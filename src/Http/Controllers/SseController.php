<?php

namespace TheDiamondBox\ShopSync\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use TheDiamondBox\ShopSync\Models\Client;
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
            $mode = config('products-package.mode', 'wl');
            $ttl = 60;

            $payload = [
                'mode' => $mode,
                'client_id' => null,
                'upstream_url' => '',
                'upstream_token' => '',
                'iat' => time(),
                'exp' => time() + $ttl,
            ];

            if ($mode === 'wtm') {
                $clientId = $request->header('client-id');

                if (empty($clientId)) {
                    return response()->json([
                        'error' => 'Client ID header is required in WTM mode',
                    ], 422);
                }

                $client = Client::query()->find($clientId);

                if (!$client) {
                    return response()->json([
                        'error' => 'Client not found',
                    ], 404);
                }

                $payload['client_id'] = (int) $client->id;
                $payload['upstream_url'] = $client->getActiveUrl() . '/' . config('products-package.route_prefix', 'api/v1');
                $payload['upstream_token'] = decrypt($client->access_token);
            }

            $json = json_encode($payload);
            $b64 = base64_encode($json);
            $signature = hash_hmac('sha256', $b64, config('app.key'));
            $token = $b64 . '.' . $signature;

            Log::info('SSE token generated', [
                'mode' => $mode,
                'expires_in' => $ttl,
            ]);

            return response()->json([
                'token' => $token,
                'expires_in' => $ttl,
            ]);
        } catch (\Exception $e) {
            Log::error('SSE token generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to generate token',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}