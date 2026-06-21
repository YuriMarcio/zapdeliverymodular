<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\WalletTransactionResource;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    use ApiResponse;

    public function entries(Request $request): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $query = WalletTransaction::where('tenant_id', $tenantId)->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $limit        = (int) ($request->limit ?? 20);
        $page         = (int) ($request->page ?? 1);
        $total        = $query->count();
        $transactions = $query->forPage($page, $limit)->get();

        return $this->paginated(WalletTransactionResource::collection($transactions), $total, $page, $limit);
    }

    public function summary(): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $wallet = Wallet::where('tenant_id', $tenantId)->first();

        $now        = now();
        $startMonth = $now->copy()->startOfMonth();

        $monthlyRevenue = Order::where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$startMonth, $now])
            ->sum('total');

        $monthlyFees = WalletTransaction::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startMonth, $now])
            ->sum('platform_fee');

        return $this->success([
            'balance'        => (float) ($wallet?->available_balance ?? 0),
            'toReceive'      => (float) ($wallet?->pending_balance ?? 0),
            'monthlyRevenue' => (float) $monthlyRevenue,
            'monthlyFees'    => (float) $monthlyFees,
        ]);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $data = $request->validate([
            'amount'  => 'required|numeric|min:1',
            'pixKey'  => 'required|string|max:255',
        ]);

        $wallet = Wallet::where('tenant_id', $tenantId)->first();

        if (!$wallet || $wallet->available_balance < $data['amount']) {
            return $this->error('INSUFFICIENT_BALANCE', 'Saldo insuficiente para saque.', [], 422);
        }

        $withdrawal = Withdrawal::create([
            'tenant_id'           => $tenantId,
            'wallet_id'           => $wallet->id,
            'amount'              => $data['amount'],
            'bank_account_details'=> ['pix_key' => $data['pixKey']],
            'status'              => 'pending',
        ]);

        $wallet->decrement('available_balance', $data['amount']);

        return $this->created(['transactionId' => $withdrawal->id]);
    }
}
