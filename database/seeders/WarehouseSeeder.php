<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            ['title' => 'Главный склад Махачкала', 'is_active' => true],
            ['title' => 'Склад Каспийск',          'is_active' => true],
            ['title' => 'Склад Дербент',           'is_active' => true],
            ['title' => 'Резервный склад',         'is_active' => false],
        ];

        foreach ($warehouses as $data) {
            Warehouse::updateOrCreate(
                ['title' => $data['title']],
                $data
            );
        }
    }
}