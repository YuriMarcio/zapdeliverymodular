<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $totalClients      = Tenant::count();
        $activeClients     = Tenant::where('status', 'active')->count();
        $trialingClients   = Tenant::where('status', 'trialing')->count();
        $suspendedClients  = Tenant::where('status', 'suspended')->count();

        $instancesConnected    = Tenant::where('whatsapp_connected', true)->count();
        $instancesDisconnected = $totalClients - $instancesConnected;

        // MRR depende do preço por plano — placeholder até existir tabela de preços
        $mrr = $this->resolveMrr();

        $newClientsThisMonth = Tenant::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return $this->success([
            'totalClients'          => $totalClients,
            'activeClients'         => $activeClients,
            'trialingClients'       => $trialingClients,
            'suspendedClients'      => $suspendedClients,
            'instancesConnected'    => $instancesConnected,
            'instancesDisconnected' => $instancesDisconnected,
            'mrr'                   => $mrr,
            'newClientsThisMonth'   => $newClientsThisMonth,
        ]);
    }

    private function resolveMrr(): float
    {
        $planPrices = config('billing.plan_prices', [
            'basic'      => 99.00,
            'pro'        => 199.00,
            'enterprise' => 399.00,
        ]);

        $activeTenants = Tenant::where('status', 'active')->pluck('plan');

        return round($activeTenants->sum(fn ($plan) => $planPrices[$plan] ?? 0), 2);
    }
}
