<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_steps', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('flow_id')->constrained('flows')->cascadeOnDelete();
            $table->string('type');
            $table->json('content');
            $table->integer('delay_seconds')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_steps');
    }
};
