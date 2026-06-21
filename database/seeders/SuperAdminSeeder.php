<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'yurigomesdds@gmail.com'],
            [
                'tenant_id' => null,
                'name'      => 'Yuri Gomes',
                'password'  => Hash::make('admin123'),
                'role'      => 'super_admin',
            ]
        );

        $this->command->info("✅ Super admin: {$user->email} / admin123");
    }
}
