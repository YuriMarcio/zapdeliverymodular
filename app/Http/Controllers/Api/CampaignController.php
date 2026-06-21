<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    use ApiResponse;

    public function plans(): JsonResponse
    {
        return $this->success([]);
    }

    public function index(): JsonResponse
    {
        return $this->success([]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->success([]);
    }

    public function analytics(int $id): JsonResponse
    {
        return $this->success([
            'impressions' => 0,
            'reach'       => 0,
            'clicks'      => 0,
            'spend'       => 0,
            'ctr'         => 0,
            'timeline'    => [],
        ]);
    }
}
