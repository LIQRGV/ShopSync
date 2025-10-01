<?php

namespace TheDiamondBox\ShopSync\Helpers;

/**
 * JSON API Error Response Helper
 *
 * Helper class for creating JSON API compliant error responses
 * according to the JSON API specification (https://jsonapi.org/format/#errors)
 */
class JsonApiErrorResponse
{
    /**
     * Create a single error response
     */
    public static function single(
        string $status,
        string $title,
        string $detail = null,
        string $code = null,
        array $source = [],
        array $meta = []
    ): array {
        $error = [
            'status' => $status,
            'title' => $title,
        ];

        if ($detail !== null) {
            $error['detail'] = $detail;
        }

        if ($code !== null) {
            $error['code'] = $code;
        }

        if (!empty($source)) {
            $error['source'] = $source;
        }

        if (!empty($meta)) {
            $error['meta'] = $meta;
        }

        return ['errors' => [$error]];
    }

    /**
     * Create multiple errors response
     */
    public static function multiple(array $errors): array
    {
        return ['errors' => $errors];
    }

    /**
     * Create a validation error response
     */
    public static function validation(array $validationErrors): array
    {
        $errors = [];

        foreach ($validationErrors as $field => $messages) {
            foreach ((array) $messages as $message) {
                $errors[] = [
                    'status' => '422',
                    'title' => 'Validation Error',
                    'detail' => $message,
                    'source' => ['pointer' => "/data/attributes/{$field}"]
                ];
            }
        }

        return ['errors' => $errors];
    }

    /**
     * Create an invalid include parameter error
     */
    public static function invalidInclude(string $include, array $availableIncludes = []): array
    {
        $detail = "The include parameter '{$include}' is not supported.";

        if (!empty($availableIncludes)) {
            $detail .= ' Available includes: ' . implode(', ', $availableIncludes);
        }

        return self::single(
            '400',
            'Invalid include parameter',
            $detail,
            'INVALID_INCLUDE',
            ['parameter' => 'include']
        );
    }

    /**
     * Create a resource not found error
     */
    public static function notFound(string $resourceType = 'resource', string $id = null): array
    {
        $detail = "The requested {$resourceType}";

        if ($id !== null) {
            $detail .= " with ID '{$id}'";
        }

        $detail .= ' could not be found.';

        return self::single(
            '404',
            'Resource not found',
            $detail,
            'RESOURCE_NOT_FOUND'
        );
    }

    /**
     * Create an unauthorized error
     */
    public static function unauthorized(string $detail = 'Authentication is required to access this resource.'): array
    {
        return self::single(
            '401',
            'Unauthorized',
            $detail,
            'UNAUTHORIZED'
        );
    }

    /**
     * Create a forbidden error
     */
    public static function forbidden(string $detail = 'You do not have permission to access this resource.'): array
    {
        return self::single(
            '403',
            'Forbidden',
            $detail,
            'FORBIDDEN'
        );
    }

    /**
     * Create a bad request error
     */
    public static function badRequest(string $detail = 'The request is invalid.'): array
    {
        return self::single(
            '400',
            'Bad Request',
            $detail,
            'BAD_REQUEST'
        );
    }

    /**
     * Create an internal server error
     */
    public static function internalError(string $detail = 'An internal server error occurred.'): array
    {
        return self::single(
            '500',
            'Internal Server Error',
            $detail,
            'INTERNAL_ERROR'
        );
    }

    /**
     * Create a method not allowed error
     */
    public static function methodNotAllowed(array $allowedMethods = []): array
    {
        $detail = 'The requested method is not allowed for this resource.';

        if (!empty($allowedMethods)) {
            $detail .= ' Allowed methods: ' . implode(', ', $allowedMethods);
        }

        return self::single(
            '405',
            'Method Not Allowed',
            $detail,
            'METHOD_NOT_ALLOWED'
        );
    }

    /**
     * Create a conflict error
     */
    public static function conflict(string $detail = 'The request conflicts with the current state of the resource.'): array
    {
        return self::single(
            '409',
            'Conflict',
            $detail,
            'CONFLICT'
        );
    }

    /**
     * Create an unprocessable entity error (typically for validation)
     */
    public static function unprocessableEntity(string $detail = 'The request contains invalid data.'): array
    {
        return self::single(
            '422',
            'Unprocessable Entity',
            $detail,
            'UNPROCESSABLE_ENTITY'
        );
    }

    /**
     * Create a rate limit exceeded error
     */
    public static function rateLimitExceeded(int $retryAfter = null): array
    {
        $meta = [];
        if ($retryAfter !== null) {
            $meta['retry_after'] = $retryAfter;
        }

        return self::single(
            '429',
            'Rate Limit Exceeded',
            'Too many requests. Please try again later.',
            'RATE_LIMIT_EXCEEDED',
            [],
            $meta
        );
    }

    /**
     * Create a service unavailable error
     */
    public static function serviceUnavailable(string $detail = 'The service is temporarily unavailable.'): array
    {
        return self::single(
            '503',
            'Service Unavailable',
            $detail,
            'SERVICE_UNAVAILABLE'
        );
    }

    /**
     * Create an invalid query parameter error
     */
    public static function invalidQueryParameter(string $parameter, string $reason = null): array
    {
        $detail = "The query parameter '{$parameter}' is invalid.";

        if ($reason !== null) {
            $detail .= " {$reason}";
        }

        return self::single(
            '400',
            'Invalid Query Parameter',
            $detail,
            'INVALID_QUERY_PARAMETER',
            ['parameter' => $parameter]
        );
    }

    /**
     * Create an invalid sort parameter error
     */
    public static function invalidSort(string $sortField, array $availableSorts = []): array
    {
        $detail = "The sort field '{$sortField}' is not supported.";

        if (!empty($availableSorts)) {
            $detail .= ' Available sort fields: ' . implode(', ', $availableSorts);
        }

        return self::single(
            '400',
            'Invalid Sort Parameter',
            $detail,
            'INVALID_SORT',
            ['parameter' => 'sort']
        );
    }

    /**
     * Create an invalid filter parameter error
     */
    public static function invalidFilter(string $filterField, array $availableFilters = []): array
    {
        $detail = "The filter field '{$filterField}' is not supported.";

        if (!empty($availableFilters)) {
            $detail .= ' Available filter fields: ' . implode(', ', $availableFilters);
        }

        return self::single(
            '400',
            'Invalid Filter Parameter',
            $detail,
            'INVALID_FILTER',
            ['parameter' => 'filter']
        );
    }

    /**
     * Create a database connection error
     */
    public static function databaseError(string $detail = 'A database error occurred.'): array
    {
        return self::single(
            '500',
            'Database Error',
            $detail,
            'DATABASE_ERROR'
        );
    }

    /**
     * Create a timeout error
     */
    public static function timeout(string $detail = 'The request timed out.'): array
    {
        return self::single(
            '408',
            'Request Timeout',
            $detail,
            'TIMEOUT'
        );
    }

    /**
     * Convert Laravel validation errors to JSON API format
     */
    public static function fromLaravelValidation($validator): array
    {
        if (is_object($validator) && method_exists($validator, 'errors')) {
            return self::validation($validator->errors()->toArray());
        }

        if (is_array($validator)) {
            return self::validation($validator);
        }

        return self::unprocessableEntity('Validation failed.');
    }

    /**
     * Convert exception to JSON API error
     */
    public static function fromException(\Exception $exception, bool $debug = false): array
    {
        $error = [
            'status' => '500',
            'title' => 'Internal Server Error',
            'code' => 'EXCEPTION'
        ];

        if ($debug) {
            $error['detail'] = $exception->getMessage();
            $error['meta'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        } else {
            $error['detail'] = 'An unexpected error occurred.';
        }

        return ['errors' => [$error]];
    }

    /**
     * Check if response is an error response
     */
    public static function isErrorResponse(array $response): bool
    {
        return isset($response['errors']) && is_array($response['errors']);
    }

    /**
     * Get HTTP status code from error response
     */
    public static function getHttpStatusFromErrorResponse(array $errorResponse): int
    {
        if (!self::isErrorResponse($errorResponse)) {
            return 200;
        }

        $errors = $errorResponse['errors'];

        if (empty($errors)) {
            return 500;
        }

        // Return the status of the first error
        $firstError = $errors[0];
        return isset($firstError['status']) ? (int) $firstError['status'] : 500;
    }
}