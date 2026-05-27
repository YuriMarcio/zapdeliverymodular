<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->boolean('from_me');
            $table->string('message_type');
            $table->text('body')->nullable();
            $table->string('media_url')->nullable(); // external URL
            $table->string('whatsapp_message_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
