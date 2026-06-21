<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\PromotionResource;
use App\Models\PromotionCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PromotionController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $tenantId   = auth('api')->user()->tenant_id;
        $promotions = PromotionCampaign::where('tenant_id', $tenantId)
            ->with('products')
            ->latest()
            ->get();

        return $this->success(PromotionResource::collection($promotions));
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'banner_url'  => 'nullable|url',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date|after_or_equal:starts_at',
            'is_active'   => 'boolean',
            'products'    => 'nullable|array',
            'products.*.id'              => 'integer',
            'products.*.promotion_price' => 'nullable|numeric|min:0',
        ]);

        $data['tenant_id'] = $tenantId;
        $data['slug']      = Str::slug($data['name']) . '-' . Str::random(4);

        $promotion = PromotionCampaign::create($data);

        if (!empty($data['products'])) {
            $sync = collect($data['products'])->mapWithKeys(fn ($p) => [
                $p['id'] => ['promotion_price' => $p['promotion_price'] ?? null],
            ])->toArray();
            $promotion->products()->sync($sync);
        }

        $promotion->load('products');

        return $this->created(new PromotionResource($promotion));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId  = auth('api')->user()->tenant_id;
        $promotion = PromotionCampaign::where('tenant_id', $tenantId)->find($id);

        if (!$promotion) {
            return $this->error('NOT_FOUND', 'Promoção não encontrada.', [], 404);
        }

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'banner_url'  => 'nullable|url',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date',
            'is_active'   => 'boolean',
            'products'    => 'nullable|array',
            'products.*.id'              => 'integer',
            'products.*.promotion_price' => 'nullable|numeric|min:0',
        ]);

        $promotion->update($data);

        if (isset($data['products'])) {
            $sync = collect($data['products'])->mapWithKeys(fn ($p) => [
                $p['id'] => ['promotion_price' => $p['promotion_price'] ?? null],
            ])->toArray();
            $promotion->products()->sync($sync);
        }

        $promotion->load('products');

        return $this->success(new PromotionResource($promotion));
    }

    public function toggle(int $id): JsonResponse
    {
        $tenantId  = auth('api')->user()->tenant_id;
        $promotion = PromotionCampaign::where('tenant_id', $tenantId)->find($id);

        if (!$promotion) {
            return $this->error('NOT_FOUND', 'Promoção não encontrada.', [], 404);
        }

        $promotion->update(['is_active' => !$promotion->is_active]);
        $promotion->load('products');

        return $this->success(new PromotionResource($promotion));
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId  = auth('api')->user()->tenant_id;
        $promotion = PromotionCampaign::where('tenant_id', $tenantId)->find($id);

        if (!$promotion) {
            return $this->error('NOT_FOUND', 'Promoção não encontrada.', [], 404);
        }

        $promotion->delete();

        return $this->noContent();
    }
}
