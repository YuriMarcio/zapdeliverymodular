<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data, ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];
        if ($message !== null) {
            $payload['message'] = $message;
        }
        return response()->json($payload, $status);
    }

    protected function created(mixed $data, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function paginated(mixed $items, int $total, int $page, int $limit): JsonResponse
    {
        return response()->json([
            'data'  => $items,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    protected function error(string $code, string $message, array $details = [], int $status = 400): JsonResponse
    {
        $payload = ['code' => $code, 'message' => $message];
        if (!empty($details)) {
            $payload['details'] = $details;
        }
        return response()->json($payload, $status);
    }
}
