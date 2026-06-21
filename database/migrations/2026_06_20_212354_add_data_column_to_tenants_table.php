<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Coluna exigida pelo trait Stancl\VirtualColumn (sempre gravada em save/create/update,
            // mesmo com getCustomColumns() cobrindo todas as colunas reais — fica sempre vazia: {}).
            $table->json('data')->nullable()->after('primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('data');
        });
    }
};
