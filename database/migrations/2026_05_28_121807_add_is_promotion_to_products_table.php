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
        Schema::table('products', function (Blueprint $table) {
            // Adiciona a coluna do tipo booleano. 
            // O default(false) garante que os produtos antigos não fiquem em promoção acidentalmente.
            $table->boolean('is_promotion')->default(false)->after('price'); 
            // Nota: Mude 'price' para o nome de outra coluna se quiser escolher onde a nova coluna vai aparecer na tabela. 
            // Se preferir que fique no final, basta apagar a parte ->after('price')
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
