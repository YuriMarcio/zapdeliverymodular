<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = null;

        // 1. Check if Stancl Tenancy is already initialized
        if (function_exists('tenancy') && tenancy()->initialized) {
            $tenant = tenancy()->tenant;
        }

        // 2. Try to get tenant_id from header
        if (!$tenant && $request->hasHeader('X-Tenant-ID')) {
            $tenantId = $request->header('X-Tenant-ID');
            $tenant = Tenant::find($tenantId);
        }

        // 3. Try to get tenant from route parameter (e.g. {tenant} or {tenant_id})
        if (!$tenant) {
            $tenantParam = $request->route('tenant') ?? $request->route('tenant_id');
            if ($tenantParam) {
                $tenant = $tenantParam instanceof Tenant 
                    ? $tenantParam 
                    : Tenant::where('id', $tenantParam)->orWhere('slug', $tenantParam)->first();
            }
        }

        // 4. Try to get tenant from body payload or query params
        if (!$tenant) {
            $tenantId = $request->input('tenant_id') ?? $request->input('tenant');
            if ($tenantId) {
                $tenant = Tenant::where('id', $tenantId)->orWhere('slug', $tenantId)->first();
            }
        }

        // 5. Specially for WhatsApp Evolution API webhook: try to identify tenant by whatsapp_instance
        if (!$tenant) {
            // Check body parameters or JSON payoad for instance name or instance id
            $instanceName = $request->input('instance') ?? $request->input('instance_name') ?? $request->input('instanceName');
            if ($instanceName) {
                $tenant = Tenant::where('whatsapp_instance', $instanceName)->first();
            }
        }

        // If no tenant could be identified, block request
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'error' => 'Tenant could not be identified.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if there is an active or trialing subscription for this tenant
        $activeSubscriptionExists = TenantSubscription::where('tenant_id', $tenant->id)
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>=', now());
            })
            ->exists();

        if (!$activeSubscriptionExists) {
            return response()->json([
                'success' => false,
                'error' => 'Tenant subscription is inactive, expired, or unpaid.'
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
