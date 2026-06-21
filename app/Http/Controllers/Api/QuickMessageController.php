<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\QuickMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickMessageController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $items = QuickMessage::where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->get(['id', 'title', 'body', 'sort_order']);

        return $this->success($items);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $validated = $request->validate([
            'title'      => 'required|string|max:100',
            'body'       => 'required|string',
            'sort_order' => 'integer|min:0',
        ]);

        $item = QuickMessage::create([
            'tenant_id'  => $tenantId,
            'title'      => $validated['title'],
            'body'       => $validated['body'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return $this->created($item->only(['id', 'title', 'body', 'sort_order']));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $item = QuickMessage::where('tenant_id', $tenantId)->find($id);
        if (!$item) {
            return $this->error('NOT_FOUND', 'Mensagem rápida não encontrada.', [], 404);
        }

        $item->update($request->only(['title', 'body', 'sort_order']));

        return $this->success($item->only(['id', 'title', 'body', 'sort_order']));
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $item = QuickMessage::where('tenant_id', $tenantId)->find($id);
        if (!$item) {
            return $this->error('NOT_FOUND', 'Mensagem rápida não encontrada.', [], 404);
        }

        $item->delete();

        return $this->noContent();
    }
}
