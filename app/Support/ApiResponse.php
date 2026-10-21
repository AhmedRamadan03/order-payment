<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

final class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Request completed successfully.',
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'type' => 'success',
            'message' => $message,
            'data' => self::resolveData($data),
            'errors' => (object) [],
            'code' => null,
        ], $status);
    }

    public static function warning(
        mixed $data = null,
        string $message = 'The request completed with a warning.',
        int $status = 200,
        ?string $code = null,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'type' => 'warning',
            'message' => $message,
            'data' => self::resolveData($data),
            'errors' => (object) [],
            'code' => $code,
        ], $status);
    }

    public static function error(
        string $message,
        string $code,
        int $status,
        array $errors = [],
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'type' => 'error',
            'message' => $message,
            'data' => null,
            'errors' => (object) $errors,
            'code' => $code,
        ], $status);
    }

    private static function resolveData(mixed $data): mixed
    {
        if ($data instanceof ResourceCollection && $data->resource instanceof AbstractPaginator) {
            $paginatedData = $data->resource->toArray();
            $paginatedData['data'] = $data->resolve(request());

            return $paginatedData;
        }

        if ($data instanceof JsonResource) {
            return $data->resolve(request());
        }

        if (is_array($data)) {
            return array_map(fn (mixed $value): mixed => self::resolveData($value), $data);
        }

        return $data;
    }
}
