<?php

namespace App\Http\Controllers\Api;

use App\Domains\WhatsApp\Services\EvolutionService;
use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $query = Conversation::where('tenant_id', $tenantId)
            ->with([
                'customer.orders' => fn ($q) => $q->where('payment_status', 'paid')->select('id', 'customer_id', 'total'),
                'messages'        => fn ($q) => $q->latest()->limit(1),
            ])
            ->latest('last_message_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('customer', fn ($c) =>
                $c->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
            );
        }

        $conversations = $query->get();

        return $this->success(ConversationResource::collection($conversations));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $conversation = Conversation::where('tenant_id', $tenantId)->find($id);
        if (!$conversation) {
            return $this->error('NOT_FOUND', 'Conversa não encontrada.', [], 404);
        }

        $conversation->update($request->only(['status', 'tag']));
        $conversation->load([
            'customer.orders' => fn ($q) => $q->where('payment_status', 'paid')->select('id', 'customer_id', 'total'),
            'messages'        => fn ($q) => $q->latest()->limit(1),
        ]);

        return $this->success(new ConversationResource($conversation));
    }

    public function markRead(int $id): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $conversation = Conversation::where('tenant_id', $tenantId)->find($id);
        if (!$conversation) {
            return $this->error('NOT_FOUND', 'Conversa não encontrada.', [], 404);
        }

        $conversation->update(['unread_count' => 0]);

        return $this->success(null, 'ok');
    }

    public function messages(Request $request, int $id): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $conversation = Conversation::where('tenant_id', $tenantId)->find($id);
        if (!$conversation) {
            return $this->error('NOT_FOUND', 'Conversa não encontrada.', [], 404);
        }

        $limit    = (int) ($request->limit ?? 30);
        $page     = (int) ($request->page ?? 1);
        $total    = $conversation->messages()->count();
        $messages = $conversation->messages()->latest()->forPage($page, $limit)->get()->reverse()->values();

        return $this->paginated(MessageResource::collection($messages), $total, $page, $limit);
    }

    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;

        $conversation = Conversation::where('tenant_id', $tenantId)
            ->with('customer')
            ->find($id);

        if (!$conversation) {
            return $this->error('NOT_FOUND', 'Conversa não encontrada.', [], 404);
        }

        if (!$request->filled('text')) {
            return $this->error('VALIDATION_ERROR', 'O campo text é obrigatório.', [], 422);
        }

        $message = $conversation->messages()->create([
            'tenant_id'    => $tenantId,
            'from_me'      => true,
            'message_type' => 'text',
            'body'         => $request->text,
        ]);

        $conversation->update(['last_message_at' => now()]);

        // Envia pelo WhatsApp
        $store = Tenant::find($tenantId);
        if ($store?->whatsapp_instance) {
            try {
                app(EvolutionService::class)->sendText(
                    $store->whatsapp_instance,
                    $conversation->customer->phone,
                    $request->text
                );
            } catch (\Throwable) {
                // Log silencioso — mensagem já foi salva no BD
            }
        }

        return $this->created(new MessageResource($message));
    }
}
