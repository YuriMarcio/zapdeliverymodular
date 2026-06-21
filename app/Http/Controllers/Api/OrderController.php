<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $query = Order::where('tenant_id', $tenantId)
            ->with(['customer', 'items'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")
                                                         ->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        $limit   = (int) ($request->limit ?? 20);
        $page    = (int) ($request->page ?? 1);
        $total   = $query->count();
        $orders  = $query->forPage($page, $limit)->get();

        return $this->paginated(OrderResource::collection($orders), $total, $page, $limit);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $order = Order::where('tenant_id', $tenantId)->find($id);
        if (!$order) {
            return $this->error('NOT_FOUND', 'Pedido não encontrado.', [], 404);
        }

        $order->update($request->only(['status', 'notes']));
        $order->load(['customer', 'items']);

        return $this->success(new OrderResource($order));
    }
}
