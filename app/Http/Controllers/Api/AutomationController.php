<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success([]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->success([]);
    }

    public function toggle(int $id): JsonResponse
    {
        return $this->success([]);
    }

    public function preview(Request $request): JsonResponse
    {
        return $this->success(['rendered' => $request->get('body', '')]);
    }
}
