<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $query = Product::where('tenant_id', $tenantId)->with('category');

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        if ($request->filled('active')) {
            $query->where('is_active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return $this->success(ProductResource::collection($query->get()));
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'category_id'     => 'required|integer',
            'price'           => 'required|numeric|min:0',
            'promotion_price' => 'nullable|numeric|min:0',
            'description'     => 'nullable|string',
            'image_url'       => 'nullable|url',
            'is_active'       => 'boolean',
        ]);

        $data['tenant_id'] = $tenantId;
        $data['slug']      = Str::slug($data['name']) . '-' . Str::random(6);

        $product = Product::create($data);
        $product->load('category');

        return $this->created(new ProductResource($product));
    }

    public function show(int $id): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;
        $product  = Product::where('tenant_id', $tenantId)->with('category')->find($id);

        if (!$product) {
            return $this->error('NOT_FOUND', 'Produto não encontrado.', [], 404);
        }

        return $this->success(new ProductResource($product));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;
        $product  = Product::where('tenant_id', $tenantId)->find($id);

        if (!$product) {
            return $this->error('NOT_FOUND', 'Produto não encontrado.', [], 404);
        }

        $data = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'category_id'     => 'sometimes|integer',
            'price'           => 'sometimes|numeric|min:0',
            'promotion_price' => 'nullable|numeric|min:0',
            'description'     => 'nullable|string',
            'image_url'       => 'nullable|url',
            'is_active'       => 'boolean',
        ]);

        $product->update($data);
        $product->load('category');

        return $this->success(new ProductResource($product));
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;
        $product  = Product::where('tenant_id', $tenantId)->find($id);

        if (!$product) {
            return $this->error('NOT_FOUND', 'Produto não encontrado.', [], 404);
        }

        $product->delete();

        return $this->noContent();
    }
}
