<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('number')->unique();
            $table->enum('status', ['pending', 'confirmed', 'preparing', 'delivery', 'completed', 'cancelled']);
            $table->string('payment_method'); // pix, credit_card, etc.
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded']);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->text('notes')->nullable();
            $table->json('address')->nullable(); // Snapshot of address
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
