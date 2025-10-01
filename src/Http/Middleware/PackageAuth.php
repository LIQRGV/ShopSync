<?php

namespace TheDiamondBox\ShopSync\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PackageAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if package auth is enabled
        if (!config('products-package.enable_package_auth', false)) {
            return $next($request);
        }

        // Get the authentication method
        $authMethod = $this->getAuthMethod($request);

        switch ($authMethod) {
            case 'bearer':
                return $this->handleBearerAuth($request, $next);
            case 'api_key':
                return $this->handleApiKeyAuth($request, $next);
            case 'basic':
                return $this->handleBasicAuth($request, $next);
            default:
                return $this->unauthorized('Authentication method not supported');
        }
    }

    /**
     * Determine the authentication method based on request headers
     */
    protected function getAuthMethod(Request $request)
    {
        // Check for Bearer token
        if ($request->bearerToken()) {
            return 'bearer';
        }

        // Check for API key in headers
        if ($request->hasHeader('X-API-Key') || $request->has('api_key')) {
            return 'api_key';
        }

        // Check for Basic auth
        if ($request->hasHeader('Authorization') &&
            substr($request->header('Authorization'), 0, 6) === 'Basic ') {
            return 'basic';
        }

        // Default to API key method
        return 'api_key';
    }

    /**
     * Handle Bearer token authentication
     */
    protected function handleBearerAuth(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $expectedToken = config('products-package.package_auth_key');

        if (!$expectedToken) {
            Log::warning('Package auth enabled but no auth key configured');
            return $this->unauthorized('Authentication not properly configured');
        }

        if (!$token || !hash_equals($expectedToken, $token)) {
            Log::warning('Invalid bearer token provided for package auth', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path()
            ]);
            return $this->unauthorized('Invalid authentication token');
        }

        Log::debug('Bearer token authentication successful', [
            'ip' => $request->ip(),
            'path' => $request->path()
        ]);

        return $next($request);
    }

    /**
     * Handle API key authentication
     */
    protected function handleApiKeyAuth(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-Key') ?? $request->get('api_key');
        $expectedKey = config('products-package.package_auth_key');

        if (!$expectedKey) {
            Log::warning('Package auth enabled but no auth key configured');
            return $this->unauthorized('Authentication not properly configured');
        }

        if (!$apiKey || !hash_equals($expectedKey, $apiKey)) {
            Log::warning('Invalid API key provided for package auth', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path()
            ]);
            return $this->unauthorized('Invalid API key');
        }

        Log::debug('API key authentication successful', [
            'ip' => $request->ip(),
            'path' => $request->path()
        ]);

        return $next($request);
    }

    /**
     * Handle Basic authentication
     */
    protected function handleBasicAuth(Request $request, Closure $next)
    {
        $credentials = $this->parseBasicAuth($request);

        if (!$credentials) {
            return $this->unauthorized('Invalid Basic auth format');
        }

        $expectedKey = config('products-package.package_auth_key');

        if (!$expectedKey) {
            Log::warning('Package auth enabled but no auth key configured');
            return $this->unauthorized('Authentication not properly configured');
        }

        // For basic auth, we'll use the password field and ignore username
        if (!hash_equals($expectedKey, $credentials['password'])) {
            Log::warning('Invalid basic auth credentials provided for package auth', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
                'username' => $credentials['username']
            ]);
            return $this->unauthorized('Invalid credentials');
        }

        Log::debug('Basic authentication successful', [
            'ip' => $request->ip(),
            'path' => $request->path(),
            'username' => $credentials['username']
        ]);

        return $next($request);
    }

    /**
     * Parse Basic authentication header
     */
    protected function parseBasicAuth(Request $request)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || substr($authHeader, 0, 6) !== 'Basic ') {
            return null;
        }

        $credentials = base64_decode(substr($authHeader, 6));

        if (!$credentials || strpos($credentials, ':') === false) {
            return null;
        }

        [$username, $password] = explode(':', $credentials, 2);

        return [
            'username' => $username,
            'password' => $password
        ];
    }

    /**
     * Return an unauthorized response
     */
    protected function unauthorized($message = 'Unauthorized')
    {
        return response()->json([
            'message' => $message,
            'error' => 'Unauthorized'
        ], Response::HTTP_UNAUTHORIZED);
    }
}