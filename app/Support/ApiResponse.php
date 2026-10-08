<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Create a standardized success JSON response.
     *
     * @param  array<string, mixed>  $headers
     */
    public static function success(
        mixed $data = null,
        string $message = 'Request successful.',
        int $status = 200,
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status, $headers);
    }

    /**
     * Create a standardized error JSON response.
     *
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $headers
     */
    public static function error(
        string $message,
        array $errors = [],
        int $status = 400,
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => empty($errors) ? (object) [] : $errors,
        ], $status, $headers);
    }
}
