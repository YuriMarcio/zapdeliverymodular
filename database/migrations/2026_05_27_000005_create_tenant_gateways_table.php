<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('provider')->default('mercado_pago');
            $table->string('mp_user_id')->nullable();
            $table->string('access_token')->nullable();
            $table->string('refresh_token')->nullable();
            $table->string('public_key')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_gateways');
    }
};
