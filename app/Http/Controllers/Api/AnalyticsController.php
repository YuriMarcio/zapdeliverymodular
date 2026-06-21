<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    use ApiResponse;

    public function summary(): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;
        $today    = now()->toDateString();

        $salesToday = Order::where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereDate('created_at', $today)
            ->sum('total');

        $avgTicket = Order::where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->avg('total') ?? 0;

        $activeOrders = Order::where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'confirmed', 'preparing', 'delivery'])
            ->count();

        $completedOrders = Order::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->count();

        $cancelledOrders = Order::where('tenant_id', $tenantId)
            ->where('status', 'cancelled')
            ->count();

        $activeConversations = Conversation::where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->count();

        $paidOrders = Order::where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->count();

        $conversionRate = $activeConversations > 0
            ? round(($paidOrders / max($activeConversations, 1)) * 100, 1)
            : 0;

        return $this->success([
            'salesToday'          => (float) $salesToday,
            'avgTicket'           => (float) $avgTicket,
            'activeOrders'        => $activeOrders,
            'responseTimeMin'     => 0,
            'activeConversations' => $activeConversations,
            'conversionRate'      => $conversionRate,
            'completedOrders'     => $completedOrders,
            'cancelledOrders'     => $cancelledOrders,
        ]);
    }

    public function sales(Request $request): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;
        $period   = $request->get('period', 'week');

        $groupFormat = match ($period) {
            'day'   => '%Y-%m-%d %H:00',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',   // week
        };

        $startDate = match ($period) {
            'day'   => now()->subDay(),
            'month' => now()->subMonth(),
            default => now()->subWeek(),
        };

        $sales = Order::where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $startDate)
            ->select(DB::raw("DATE_FORMAT(created_at, '{$groupFormat}') as day"), DB::raw('SUM(total) as vendas'))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $conversations = Conversation::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->select(DB::raw("DATE_FORMAT(created_at, '{$groupFormat}') as day"), DB::raw('COUNT(*) as conversas'))
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('conversas', 'day');

        $data = $sales->map(fn ($row) => [
            'day'       => $row->day,
            'vendas'    => (float) $row->vendas,
            'conversas' => (int) ($conversations[$row->day] ?? 0),
        ]);

        return $this->success($data);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;
        $limit    = (int) ($request->limit ?? 5);

        $startDate = match ($request->get('period', 'month')) {
            'week'  => now()->subWeek(),
            'day'   => now()->subDay(),
            default => now()->subMonth(),
        };

        $products = OrderItem::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->select('name', DB::raw('SUM(quantity) as vendas'))
            ->groupBy('name')
            ->orderByDesc('vendas')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'vendas' => (int) $row->vendas]);

        return $this->success($products);
    }
}
