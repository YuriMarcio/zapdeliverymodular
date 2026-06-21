<?php

namespace App\Jobs;

use App\Domains\WhatsApp\Services\EvolutionService;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\WhatsappInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncWhatsAppHistoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 1;

    public function __construct(
        public readonly string $tenantId,
        public readonly int    $messagesPerChat = 50,
    ) {}

    public function handle(EvolutionService $evolution): void
    {
        $store = Tenant::find($this->tenantId);
        if (!$store?->whatsapp_instance) {
            Log::warning("SyncWhatsAppHistoryJob: tenant {$this->tenantId} sem instância");
            return;
        }

        $instance = $store->whatsapp_instance;

        Log::info("SyncWhatsAppHistory iniciado | tenant={$this->tenantId} instância={$instance}");

        // 1. Importar contatos
        $contacts = $evolution->fetchContacts($instance);
        $contactMap = [];
        foreach ($contacts as $contact) {
            $jid   = data_get($contact, 'remoteJid', '');
            $phone = str_replace(['@s.whatsapp.net', '@c.us'], '', $jid);
            if (!$phone || str_contains($phone, '@') || str_contains($phone, 'status')) {
                continue;
            }
            $name = data_get($contact, 'pushName') ?? data_get($contact, 'name') ?? null;
            $contactMap[$phone] = $name;
        }

        // 2. Importar chats
        $chats = $evolution->fetchChats($instance);

        $imported = 0;
        foreach ($chats as $chat) {
            $jid = data_get($chat, 'remoteJid', '');
            // Apenas chats individuais (não grupos, não @lid)
            if (!str_ends_with($jid, '@s.whatsapp.net')) {
                continue;
            }

            $phone = str_replace('@s.whatsapp.net', '', $jid);
            if (!$phone) continue;

            $name = $contactMap[$phone]
                ?? data_get($chat, 'name')
                ?? data_get($chat, 'pushName')
                ?? null;

            // Criar/atualizar cliente
            $customer = Customer::firstOrCreate(
                ['tenant_id' => $this->tenantId, 'phone' => $phone],
                ['name' => $name]
            );

            if ($name && !$customer->name) {
                $customer->update(['name' => $name]);
            }

            // Criar/recuperar conversa
            $conversation = Conversation::firstOrCreate(
                [
                    'tenant_id'   => $this->tenantId,
                    'customer_id' => $customer->id,
                    'status'      => 'open',
                ],
                [
                    'last_message_at' => now(),
                    'unread_count'    => 0,
                ]
            );

            // 3. Importar mensagens do chat
            $this->importMessages($evolution, $instance, $jid, $conversation);

            $imported++;
        }

        WhatsappInstance::updateOrCreate(
            ['tenant_id' => $this->tenantId, 'instance_name' => $instance],
            ['status' => 'connected', 'last_seen_at' => now()]
        );

        Log::info("SyncWhatsAppHistory concluído | {$imported} chats importados");
    }

    private function importMessages(
        EvolutionService $evolution,
        string $instance,
        string $remoteJid,
        Conversation $conversation
    ): void {
        $messages = $evolution->fetchMessages($instance, $remoteJid, $this->messagesPerChat);
        if (empty($messages)) return;

        $existingIds = Message::where('conversation_id', $conversation->id)
            ->whereNotNull('whatsapp_message_id')
            ->pluck('whatsapp_message_id')
            ->flip();

        $lastAt = null;

        foreach ($messages as $msg) {
            $msgId = data_get($msg, 'key.id');
            if ($msgId && isset($existingIds[$msgId])) {
                continue; // já importado
            }

            $fromMe = (bool) data_get($msg, 'key.fromMe', false);
            $body   = data_get($msg, 'message.conversation')
                   ?? data_get($msg, 'message.extendedTextMessage.text')
                   ?? data_get($msg, 'message.imageMessage.caption')
                   ?? null;

            $hasImage = !empty(data_get($msg, 'message.imageMessage'));
            $hasAudio = !empty(data_get($msg, 'message.audioMessage'));
            $hasDoc   = !empty(data_get($msg, 'message.documentMessage'));

            $type = match (true) {
                $hasImage => 'image',
                $hasAudio => 'audio',
                $hasDoc   => 'document',
                default   => 'text',
            };

            $timestamp = data_get($msg, 'messageTimestamp');
            $createdAt = $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp) : now();

            Message::create([
                'tenant_id'           => $conversation->tenant_id,
                'conversation_id'     => $conversation->id,
                'from_me'             => $fromMe,
                'message_type'        => $type,
                'body'                => $body,
                'whatsapp_message_id' => $msgId,
                'created_at'          => $createdAt,
                'updated_at'          => $createdAt,
            ]);

            if (!$lastAt || $createdAt > $lastAt) {
                $lastAt = $createdAt;
            }
        }

        if ($lastAt) {
            $conversation->update(['last_message_at' => $lastAt]);
        }
    }
}
