<?php

namespace App\Domains\WhatsApp\Actions;

use App\Domains\WhatsApp\Flows\MainMenuFlow;
use App\Domains\WhatsApp\Flows\CategoryFlow;

class HandleIncomingMessage
{
    public function __construct(
        protected MainMenuFlow $mainMenuFlow,
        protected CategoryFlow $categoryFlow
    ) {}

    public function execute(array $payload): void
    {
        $event = data_get($payload, 'event');

        if ($event !== 'messages.upsert') {
            return;
        }

        $fromMe = data_get($payload, 'data.key.fromMe');

        if ($fromMe) {
            return;
        }

        $instance = data_get($payload, 'instance');

        $remoteJid = data_get($payload, 'data.key.remoteJid');

        $number = str_replace(
            '@s.whatsapp.net',
            '',
            $remoteJid
        );

        $text =
            data_get($payload, 'data.message.conversation')
            ?? data_get($payload, 'data.message.extendedTextMessage.text');

        /*
        |--------------------------------------------------------------------------
        | CLIQUE EM BOTÃO
        |--------------------------------------------------------------------------
        */

        $buttonId =
            data_get($payload, 'data.message.buttonsResponseMessage.selectedButtonId')
            ?? data_get($payload, 'data.message.interactiveResponseMessage.nativeFlowResponseMessage.paramsJson');

        /*
        |--------------------------------------------------------------------------
        | PRIMEIRA INTERAÇÃO
        |--------------------------------------------------------------------------
        */

        if (!$text && !$buttonId) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | MENU INICIAL
        |--------------------------------------------------------------------------
        */

        if (
            strtolower($text) === 'oi' ||
            strtolower($text) === 'menu'
        ) {
            $this->mainMenuFlow->handle(
                $instance,
                $number
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | CATEGORIA
        |--------------------------------------------------------------------------
        */

        if ($buttonId && str_contains($buttonId, 'category_')) {

            $categoryId = str_replace(
                'category_',
                '',
                $buttonId
            );

            $this->categoryFlow->handle(
                $instance,
                $number,
                $categoryId
            );
        }
    }
}