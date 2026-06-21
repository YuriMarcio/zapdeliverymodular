<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $tenantId   = auth('api')->user()->tenant_id;
        $categories = Category::where('tenant_id', $tenantId)->orderBy('sort_order')->get();

        return $this->success(CategoryResource::collection($categories));
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'image_url'  => 'nullable|url',
            'sort_order' => 'nullable|integer',
            'active'     => 'boolean',
        ]);

        $data['tenant_id'] = $tenantId;
        $data['slug']      = Str::slug($data['name']) . '-' . Str::random(4);

        $category = Category::create($data);

        return $this->created(new CategoryResource($category));
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;
        $category = Category::where('tenant_id', $tenantId)->find($id);

        if (!$category) {
            return $this->error('NOT_FOUND', 'Categoria não encontrada.', [], 404);
        }

        $category->delete();

        return $this->noContent();
    }
}
