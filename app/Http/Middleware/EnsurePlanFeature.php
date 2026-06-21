<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenantId = auth('api')->user()?->tenant_id;
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        if (!$tenant || !$tenant->hasFeature($feature)) {
            return response()->json([
                'success' => false,
                'error'   => 'FEATURE_NOT_AVAILABLE',
                'message' => 'Recurso não disponível no seu plano.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
