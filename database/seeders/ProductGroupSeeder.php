<?php

namespace Database\Seeders;

use App\Models\ProductGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductGroupSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $groups = [
            'Новинки',
            'Хиты продаж',
            'Акции',
            'Распродажа',
            'Эксклюзив',
        ];

        foreach ($groups as $title) {
            ProductGroup::firstOrCreate(['title' => $title]);
        }

        echo "✅ Группы продуктов успешно созданы!\n";
    }
}