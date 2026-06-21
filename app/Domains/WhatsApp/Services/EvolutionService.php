<?php

namespace App\Domains\WhatsApp\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('EVOLUTION_API_URL'), '/');
        $this->apiKey = env('EVOLUTION_API_KEY');
    }

    protected function request(string $endpoint, array $payload = [])
    {
        return Http::withHeaders([
            'apikey' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post("{$this->baseUrl}{$endpoint}", $payload);
    }

    protected function get(string $endpoint, array $query = [])
    {
        return Http::withHeaders([
            'apikey' => $this->apiKey,
        ])->get("{$this->baseUrl}{$endpoint}", $query);
    }

    public function fetchChats(string $instanceName): array
    {
        $response = $this->request("/chat/findChats/{$instanceName}");
        return $response->successful() ? ($response->json() ?? []) : [];
    }

    public function fetchMessages(string $instanceName, string $remoteJid, int $limit = 50): array
    {
        $response = $this->request("/chat/findMessages/{$instanceName}", [
            'where' => ['key' => ['remoteJid' => $remoteJid]],
            'limit' => $limit,
        ]);
        return $response->successful() ? ($response->json('messages.records') ?? []) : [];
    }

    public function fetchContacts(string $instanceName): array
    {
        $response = $this->request("/chat/findContacts/{$instanceName}");
        return $response->successful() ? ($response->json() ?? []) : [];
    }

    public function connectionState(string $instanceName): string
    {
        $response = $this->get("/instance/connectionState/{$instanceName}");
        return $response->json('instance.state') ?? 'unknown';
    }

    public function sendButtonsWithImage(
        string $instanceName,
        string $number,
        string $title,
        string $description,
        string $imageUrl,
        array $buttons
    ) {
        return $this->request(
            "/message/sendButtons/{$instanceName}",
            [
                'number'       => $number,
                'thumbnailUrl' => $imageUrl,
                'title'        => $title,
                'description'  => $description,
                'buttons'      => $buttons,
                'delay'        => 1000,
            ]
        );
    }

    public function createInstance(string $instanceName): array
    {
        return Http::withHeaders(['apikey' => $this->apiKey])
            ->post("{$this->baseUrl}/instance/create", [
                'instanceName' => $instanceName,
                'qrcode'       => true,
                'integration'  => 'WHATSAPP-BAILEYS'
            ])->json();
    }

    public function setWebhook(string $instanceName): array
    {
        return Http::withHeaders(['apikey' => $this->apiKey])
            ->post("{$this->baseUrl}/webhook/set/{$instanceName}", [
                'webhook' => [
                    'enabled' => true,
                    'url'     => env('WHATSAPP_WEBHOOK_URL'),
                    'events'  => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE', 'QRCODE_UPDATED'],
                ]
            ])->json();
    }

    public function getQrCode(string $instanceName): array
    {
        return Http::withHeaders(['apikey' => $this->apiKey])
            ->get("{$this->baseUrl}/instance/connect/{$instanceName}")
            ->json();
    }

    public function sendText(string $instanceName, string $number, string $text)
    {
        return $this->request("/message/sendText/{$instanceName}", [
            'number' => $number,
            'text'   => $text,
            'delay'  => 1200,
        ]);
    }

    public function sendCarousel(string $instanceName, string $number, string $body, array $cards)
    {
        Log::info("Enviando carrossel para {$number} com " . count($cards) . " cards");
        $response = $this->request("/message/sendCarousel/{$instanceName}", [
            'number' => $number,
            'body'   => $body,
            'cards'  => $cards,
            'delay'  => 1000,
        ]);
        Log::info("Resposta Evolution (Carrossel): " . $response->body());
        return $response;
    }

    public function sendButtons(
        string $instanceName,
        string $number,
        string $title,
        string $description,
        string $footer,
        array $buttons
    ) {
        return $this->request("/message/sendButtons/{$instanceName}", [
            'number'      => $number,
            'title'       => $title,
            'description' => $description,
            'footer'      => $footer,
            'buttons'     => $buttons,
            'delay'       => 1000,
        ]);
    }

    public function sendList(
        string $instanceName,
        string $number,
        string $title,
        string $description,
        string $buttonText,
        string $footer,
        array $sections
    ) {
        return $this->request("/message/sendList/{$instanceName}", [
            'number'      => $number,
            'title'       => $title,
            'description' => $description,
            'buttonText'  => $buttonText,
            'footerText'  => $footer,
            'sections'    => $sections,
        ]);
    }

    public function sendImage(string $instanceName, string $number, string $imageUrl, string $caption = '')
    {
        return $this->request("/message/sendMedia/{$instanceName}", [
            'number'    => $number,
            'mediatype' => 'image',
            'media'     => $imageUrl,
            'caption'   => $caption,
        ]);
    }

    public function sendAudio(string $instanceName, string $number, string $audioUrl)
    {
        return $this->request("/message/sendWhatsAppAudio/{$instanceName}", [
            'number' => $number,
            'audio'  => $audioUrl,
        ]);
    }

    public function sendDocument(string $instanceName, string $number, string $documentUrl, string $fileName)
    {
        return $this->request("/message/sendMedia/{$instanceName}", [
            'number'    => $number,
            'mediatype' => 'document',
            'media'     => $documentUrl,
            'fileName'  => $fileName,
        ]);
    }

    public function sendReaction(string $instanceName, string $number, string $messageId, string $emoji)
    {
        return $this->request("/message/sendReaction/{$instanceName}", [
            'reactionMessage' => [
                'key' => [
                    'remoteJid' => $number . '@s.whatsapp.net',
                    'fromMe'    => false,
                    'id'        => $messageId
                ],
                'reaction' => $emoji
            ]
        ]);
    }
}