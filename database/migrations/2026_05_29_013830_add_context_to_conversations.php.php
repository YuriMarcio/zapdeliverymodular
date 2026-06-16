<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Etapa atual do fluxo: ex: "waiting_email", "waiting_address", "waiting_reference"
            $table->string('step')->nullable()->after('status');
            // Dados temporários da conversa (carrinho em montagem, dados coletados, etc)
            $table->json('context')->nullable()->after('step');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['step', 'context']);
        });
    }
};