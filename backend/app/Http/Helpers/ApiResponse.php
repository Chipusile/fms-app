<?php

namespace App\Http\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Standardised API response builder.
 *
 * Ensures every API response follows the same envelope format:
 * { "data": ..., "message": "...", "meta": {...} }
 */
class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $response = [
            'message' => $message,
            'data' => $data,
        ];

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $status);
    }

    public static function created(
        mixed $data = null,
        string $message = 'Created successfully',
    ): JsonResponse {
        return static::success($data, $message, 201);
    }

    public static function noContent(string $message = 'Deleted successfully'): JsonResponse
    {
        return response()->json(['message' => $message], 200);
    }

    public static function error(
        string $message = 'An error occurred',
        int $status = 400,
        ?array $errors = null,
        ?string $code = null,
    ): JsonResponse {
        $response = [
            'message' => $message,
        ];

        if ($code) {
            $response['code'] = $code;
        }

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    public static function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return static::error($message, 403, code: 'FORBIDDEN');
    }

    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return static::error($message, 404, code: 'NOT_FOUND');
    }

    public static function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return static::error($message, 401, code: 'UNAUTHORIZED');
    }

    public static function validationError(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return static::error($message, 422, $errors, 'VALIDATION_ERROR');
    }
}
