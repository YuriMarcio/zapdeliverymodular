<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domains\WhatsApp\Services\EvolutionService;
use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminClientResource;
use App\Mail\NewClientCredentials;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    use ApiResponse;

    public function __construct(protected EvolutionService $evolution) {}

    public function index(Request $request): JsonResponse
    {
        $limit = (int) ($request->input('limit', 20));
        $page  = (int) ($request->input('page', 1));

        $query = Tenant::query()
            ->with([
                'users',
                'whatsappInstances',
                'subscriptions' => fn ($q) => $q->latest('id')->limit(1),
                'orders' => fn ($q) => $q->whereMonth('created_at', now()->month)->where('payment_status', 'paid'),
            ])
            ->latest();

        if ($request->input('status') === 'expiring') {
            $query->where('status', 'active')
                ->whereHas('subscriptions', fn ($q) => $q->where('status', 'active')
                    ->whereBetween('expires_at', [now(), now()->addDays(15)]));
        } elseif ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhereHas('users', fn ($u) => $u->where('email', 'like', "%{$search}%")));
        }

        $total  = $query->count();
        $tenants = $query->forPage($page, $limit)->get();

        return $this->paginated(AdminClientResource::collection($tenants), $total, $page, $limit);
    }

    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::with(['users', 'whatsappInstances', 'subscriptions', 'orders' => fn ($q) => $q->whereMonth('created_at', now()->month)->where('payment_status', 'paid')])
            ->find($id);

        if (!$tenant) {
            return $this->error('NOT_FOUND', 'Cliente não encontrado.', [], 404);
        }

        return $this->success(new AdminClientResource($tenant));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:150',
            'ownerName'  => 'required|string|max:150',
            'ownerEmail' => 'required|email|unique:users,email',
            'ownerPhone' => 'nullable|string|max:30',
            'plan'       => ['required', 'string', Rule::exists('plans', 'slug')],
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Dados inválidos.', $validator->errors()->toArray(), 422);
        }

        $tenantId = (string) Str::uuid();
        $slug     = $this->uniqueSlug($request->input('name'));
        $password = Str::random(12);

        $tenant = Tenant::create([
            'id'                 => $tenantId,
            'name'               => $request->input('name'),
            'slug'               => $slug,
            'plan'               => $request->input('plan'),
            'status'             => 'trialing',
            'whatsapp_connected' => false,
        ]);

        User::create([
            'tenant_id'             => $tenantId,
            'name'                  => $request->input('ownerName'),
            'email'                 => $request->input('ownerEmail'),
            'phone'                 => $request->input('ownerPhone'),
            'password'              => Hash::make($password),
            'role'                  => 'owner',
            'must_change_password'  => true,
        ]);

        TenantSubscription::create([
            'tenant_id'     => $tenantId,
            'plan_name'     => $request->input('plan'),
            'status'        => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $instanceName = $slug . '_' . $tenantId;
        $this->evolution->createInstance($instanceName);
        $this->evolution->setWebhook($instanceName);
        $tenant->update(['whatsapp_instance' => $instanceName]);

        $qr = $this->evolution->getQrCode($instanceName);
        $qrCode = data_get($qr, 'base64') ?? data_get($qr, 'qrcode.base64') ?? data_get($qr, 'code');

        $this->sendWelcomeCredentials(
            $request->input('name'),
            $request->input('ownerName'),
            $request->input('ownerEmail'),
            $request->input('ownerPhone'),
            $password,
        );

        $tenant->load(['users', 'whatsappInstances', 'subscriptions']);

        return $this->created([
            'client'       => new AdminClientResource($tenant),
            'status'       => $qrCode ? 'awaiting_scan' : 'pending',
            'qr_code'      => $qrCode,
            'instance'     => $instanceName,
            'ownerPassword'=> $password,
        ]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:trialing,active,suspended,inactive',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Status inválido.', $validator->errors()->toArray(), 422);
        }

        $tenant = Tenant::find($id);
        if (!$tenant) {
            return $this->error('NOT_FOUND', 'Cliente não encontrado.', [], 404);
        }

        $tenant->update(['status' => $request->input('status')]);
        $tenant->load(['users', 'whatsappInstances', 'subscriptions']);

        return $this->success(new AdminClientResource($tenant));
    }

    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);
        if (!$tenant) {
            return $this->error('NOT_FOUND', 'Cliente não encontrado.', [], 404);
        }

        $tenant->delete();

        return $this->success(['id' => $id]);
    }

    private function sendWelcomeCredentials(
        string $storeName,
        string $ownerName,
        string $ownerEmail,
        ?string $ownerPhone,
        string $password,
    ): void {
        try {
            Mail::to($ownerEmail)->send(new NewClientCredentials($storeName, $ownerName, $ownerEmail, $password));
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar e-mail de boas-vindas ao novo cliente.', ['error' => $e->getMessage()]);
        }

        $masterInstance = Setting::get('master_whatsapp_instance');
        if (!$masterInstance || !$ownerPhone) {
            return;
        }

        try {
            $message = "Olá, {$ownerName}! Seu acesso ao painel SINAL da loja {$storeName} foi criado.\n\n"
                . "E-mail: {$ownerEmail}\nSenha temporária: {$password}\n\n"
                . "No primeiro acesso você vai precisar trocar essa senha.";
            $this->evolution->sendText($masterInstance, $ownerPhone, $message);
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar credenciais por WhatsApp ao novo cliente.', ['error' => $e->getMessage()]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i    = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = "{$base}-" . (++$i);
        }

        return $slug;
    }
}
