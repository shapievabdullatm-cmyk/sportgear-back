<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ShopSeeder extends Seeder
{
    public function run(): void
    {
        $warehouseMkala    = Warehouse::firstOrCreate(['title' => 'Главный склад Махачкала'], ['is_active' => true]);
        $warehouseKaspiysk = Warehouse::firstOrCreate(['title' => 'Склад Каспийск'],          ['is_active' => true]);
        $warehouseDerbent  = Warehouse::firstOrCreate(['title' => 'Склад Дербент'],           ['is_active' => true]);

        $workingHours = [
            ['day' => 1, 'is_open' => true,  'open' => '10:00', 'close' => '22:00', 'break' => []],
            ['day' => 2, 'is_open' => true,  'open' => '10:00', 'close' => '22:00', 'break' => []],
            ['day' => 3, 'is_open' => true,  'open' => '10:00', 'close' => '22:00', 'break' => []],
            ['day' => 4, 'is_open' => true,  'open' => '10:00', 'close' => '22:00', 'break' => []],
            ['day' => 5, 'is_open' => true,  'open' => '10:00', 'close' => '22:00', 'break' => []],
            ['day' => 6, 'is_open' => true,  'open' => '10:00', 'close' => '23:00', 'break' => []],
            ['day' => 7, 'is_open' => true,  'open' => '10:00', 'close' => '21:00', 'break' => []],
        ];

        $shops = [
            [
                'name'        => 'Rage Махачкала Центр',
                'description' => 'Флагманский магазин в самом центре Махачкалы. Полный ассортимент брендов и сезонных коллекций.',
                'address'     => 'пр. Имама Шамиля, 13',
                'city'        => 'Махачкала',
                'latitude'    => 42.984500,
                'longitude'   => 47.504700,
                'phone'       => '+7 (8722) 55-00-13',
                'email'       => 'mkala-center@rage.ru',
                'sort_order'  => 10,
                'warehouse'   => $warehouseMkala,
            ],
            [
                'name'        => 'Rage Махачкала «Этажи»',
                'description' => 'Магазин в ТРЦ «Этажи» — удобный паркинг, фуд-корт рядом.',
                'address'     => 'ул. Ярагского, 71, ТРЦ «Этажи», 2 этаж',
                'city'        => 'Махачкала',
                'latitude'    => 42.974830,
                'longitude'   => 47.487260,
                'phone'       => '+7 (8722) 55-00-21',
                'email'       => 'mkala-etazhi@rage.ru',
                'sort_order'  => 20,
                'warehouse'   => $warehouseMkala,
            ],
            [
                'name'        => 'Rage Каспийск',
                'description' => 'Магазин на главной улице Каспийска.',
                'address'     => 'ул. Ленина, 53',
                'city'        => 'Каспийск',
                'latitude'    => 42.879500,
                'longitude'   => 47.625200,
                'phone'       => '+7 (87246) 5-12-34',
                'email'       => 'kaspiysk@rage.ru',
                'sort_order'  => 30,
                'warehouse'   => $warehouseKaspiysk,
            ],
            [
                'name'        => 'Rage Дербент',
                'description' => 'Магазин в историческом центре Дербента.',
                'address'     => 'ул. Гагарина, 32',
                'city'        => 'Дербент',
                'latitude'    => 42.058200,
                'longitude'   => 48.290100,
                'phone'       => '+7 (87240) 4-56-78',
                'email'       => 'derbent@rage.ru',
                'sort_order'  => 40,
                'warehouse'   => $warehouseDerbent,
            ],
            [
                'name'        => 'Rage Хасавюрт',
                'description' => 'Магазин в центре Хасавюрта.',
                'address'     => 'ул. Тотурбиева, 86',
                'city'        => 'Хасавюрт',
                'latitude'    => 43.252300,
                'longitude'   => 46.594800,
                'phone'       => '+7 (87231) 5-67-89',
                'email'       => 'hasavyurt@rage.ru',
                'sort_order'  => 50,
                'warehouse'   => $warehouseMkala,
            ],
            [
                'name'        => 'Rage Кизилюрт',
                'description' => 'Магазин на центральной улице Кизилюрта.',
                'address'     => 'ул. Гагарина, 24',
                'city'        => 'Кизилюрт',
                'latitude'    => 43.211200,
                'longitude'   => 46.866300,
                'phone'       => '+7 (87234) 2-34-56',
                'email'       => 'kizilyurt@rage.ru',
                'sort_order'  => 60,
                'warehouse'   => $warehouseMkala,
            ],
            [
                'name'        => 'Rage Буйнакск',
                'description' => 'Магазин на улице Ленина в Буйнакске.',
                'address'     => 'ул. Ленина, 67',
                'city'        => 'Буйнакск',
                'latitude'    => 42.823100,
                'longitude'   => 47.121400,
                'phone'       => '+7 (87237) 2-78-90',
                'email'       => 'buynaksk@rage.ru',
                'sort_order'  => 70,
                'warehouse'   => $warehouseMkala,
            ],
        ];

        foreach ($shops as $data) {
            $warehouse = $data['warehouse'];
            unset($data['warehouse']);

            $data['working_hours'] = $workingHours;
            $data['is_active']     = true;
            $data['slug']          = Str::slug($data['name']);

            $shop = Shop::updateOrCreate(
                ['name' => $data['name']],
                $data
            );

            $shop->warehouses()->sync([
                $warehouse->id => ['is_primary' => true, 'sort_order' => 0],
            ]);
        }
    }
}