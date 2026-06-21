<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // tenant_id precisa aceitar NULL para o super_admin (não pertence a nenhum tenant)
        DB::statement('ALTER TABLE users MODIFY tenant_id VARCHAR(255) NULL');

        DB::statement("ALTER TABLE users MODIFY role ENUM('owner','manager','atendente','super_admin') NOT NULL");

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });

        DB::statement("ALTER TABLE users MODIFY role ENUM('owner','manager','atendente') NOT NULL");
        DB::statement('ALTER TABLE users MODIFY tenant_id VARCHAR(255) NOT NULL');
    }
};
