<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {

            // Renomeia active -> is_active
            $table->renameColumn('active', 'is_active');

            // Renomeia featured -> is_featured
            $table->renameColumn('featured', 'is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {

            $table->renameColumn('is_active', 'active');

            $table->renameColumn('is_featured', 'featured');
        });
    }
};