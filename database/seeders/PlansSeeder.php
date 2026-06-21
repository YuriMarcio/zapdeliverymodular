<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        Plan::firstOrCreate(
            ['slug' => 'whatsapp'],
            [
                'name'     => 'WhatsApp',
                'features' => ['dashboard', 'conversas', 'pedidos', 'produtos', 'marketing'],
            ]
        );

        Plan::firstOrCreate(
            ['slug' => 'consultoria'],
            [
                'name'     => 'Consultoria',
                'features' => [
                    'dashboard', 'conversas', 'pedidos', 'produtos', 'marketing',
                    'financeiro', 'automacao', 'integracoes_externas', 'pedidos_multi_canal',
                ],
            ]
        );

        $this->command->info('✅ Planos: whatsapp, consultoria');
    }
}
