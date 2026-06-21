<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StoreAndUserSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = (string) Str::uuid();

        // Insere diretamente para evitar conflito com coluna 'data' do Stancl Tenancy
        DB::table('tenants')->insert([
            'id'                 => $tenantId,
            'name'               => 'Minha Loja',
            'slug'               => 'minha-loja',
            'plan'               => 'basic',
            'status'             => 'active',
            'phone'              => '5511999999999',
            'whatsapp_connected' => false,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $user = User::create([
            'tenant_id' => $tenantId,
            'name'      => 'Admin',
            'email'     => 'admin@minhaloja.com',
            'password'  => Hash::make('password123'),
            'role'      => 'owner',
        ]);

        $this->command->info("✅ Loja criada: Minha Loja");
        $this->command->info("   ID:       {$tenantId}");
        $this->command->info("✅ Usuário criado:");
        $this->command->info("   E-mail:   {$user->email}");
        $this->command->info("   Senha:    password123");
    }
}
