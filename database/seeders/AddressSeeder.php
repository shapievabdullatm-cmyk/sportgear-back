<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;

class AddressSeeder extends Seeder
{
    public function run(): void
    {
        $addressesByPhone = [
            '+79999999999' => [
                [
                    'title'        => 'Дом',
                    'full_address' => 'Республика Дагестан, г. Махачкала, ул. Гагарина, д. 12, кв. 5',
                    'city'         => 'Махачкала',
                    'street'       => 'ул. Гагарина',
                    'house'        => '12',
                    'apartment'    => '5',
                    'entrance'     => '1',
                    'floor'        => '2',
                    'lat'          => 42.984500,
                    'lon'          => 47.504700,
                    'is_default'   => true,
                ],
            ],

            '+79161234567' => [
                [
                    'title'        => 'Дом',
                    'full_address' => 'г. Москва, ул. Тверская, д. 7, кв. 124',
                    'city'         => 'Москва',
                    'street'       => 'ул. Тверская',
                    'house'        => '7',
                    'apartment'    => '124',
                    'entrance'     => '2',
                    'floor'        => '8',
                    'intercom'     => '124К1234',
                    'lat'          => 55.764700,
                    'lon'          => 37.605900,
                    'comment'      => 'Домофон работает только до 22:00',
                    'is_default'   => true,
                ],
                [
                    'title'        => 'Работа',
                    'full_address' => 'г. Москва, Пресненская наб., д. 12, БЦ Москва-Сити, офис 4501',
                    'city'         => 'Москва',
                    'street'       => 'Пресненская наб.',
                    'house'        => '12',
                    'apartment'    => 'офис 4501',
                    'floor'        => '45',
                    'lat'          => 55.749700,
                    'lon'          => 37.539600,
                    'comment'      => 'Передать на ресепшен',
                    'is_default'   => false,
                ],
            ],

            '+79262345678' => [
                [
                    'title'        => 'Дом',
                    'full_address' => 'г. Санкт-Петербург, Невский пр., д. 85, кв. 14',
                    'city'         => 'Санкт-Петербург',
                    'street'       => 'Невский пр.',
                    'house'        => '85',
                    'apartment'    => '14',
                    'entrance'     => '3',
                    'floor'        => '4',
                    'intercom'     => '14',
                    'lat'          => 59.934300,
                    'lon'          => 30.335100,
                    'is_default'   => true,
                ],
            ],

            '+79153456789' => [
                [
                    'title'        => 'Дом',
                    'full_address' => 'г. Москва, ул. Профсоюзная, д. 56, кв. 218',
                    'city'         => 'Москва',
                    'street'       => 'ул. Профсоюзная',
                    'house'        => '56',
                    'apartment'    => '218',
                    'entrance'     => '4',
                    'floor'        => '12',
                    'intercom'     => '218',
                    'lat'          => 55.681600,
                    'lon'          => 37.546800,
                    'is_default'   => true,
                ],
                [
                    'title'        => 'Дача',
                    'full_address' => 'Московская обл., г. Подольск, ул. Народная, д. 23',
                    'city'         => 'Подольск',
                    'street'       => 'ул. Народная',
                    'house'        => '23',
                    'lat'          => 55.429700,
                    'lon'          => 37.554700,
                    'comment'      => 'Калитка справа, собаки нет',
                    'is_default'   => false,
                ],
            ],

            '+79254567890' => [
                [
                    'title'        => 'Дом',
                    'full_address' => 'Республика Дагестан, г. Махачкала, ул. Имама Шамиля, д. 47, кв. 89',
                    'city'         => 'Махачкала',
                    'street'       => 'ул. Имама Шамиля',
                    'house'        => '47',
                    'apartment'    => '89',
                    'entrance'     => '2',
                    'floor'        => '6',
                    'intercom'     => '89',
                    'lat'          => 42.978500,
                    'lon'          => 47.496500,
                    'is_default'   => true,
                ],
            ],

            '+79165678901' => [
                [
                    'title'        => 'Дом',
                    'full_address' => 'г. Москва, Ленинский пр., д. 32, кв. 47',
                    'city'         => 'Москва',
                    'street'       => 'Ленинский пр.',
                    'house'        => '32',
                    'apartment'    => '47',
                    'entrance'     => '1',
                    'floor'        => '5',
                    'lat'          => 55.703100,
                    'lon'          => 37.566200,
                    'is_default'   => true,
                ],
            ],

            '+79296789012' => [
                [
                    'title'        => 'Дом',
                    'full_address' => 'Республика Дагестан, г. Каспийск, ул. Ленина, д. 14, кв. 27',
                    'city'         => 'Каспийск',
                    'street'       => 'ул. Ленина',
                    'house'        => '14',
                    'apartment'    => '27',
                    'entrance'     => '1',
                    'floor'        => '3',
                    'intercom'     => '27',
                    'lat'          => 42.879500,
                    'lon'          => 47.625200,
                    'is_default'   => true,
                ],
                [
                    'title'        => 'Работа',
                    'full_address' => 'Республика Дагестан, г. Махачкала, пр. Расула Гамзатова, д. 71',
                    'city'         => 'Махачкала',
                    'street'       => 'пр. Расула Гамзатова',
                    'house'        => '71',
                    'floor'        => '3',
                    'lat'          => 42.974830,
                    'lon'          => 47.487260,
                    'comment'      => 'Позвонить за 10 минут до приезда',
                    'is_default'   => false,
                ],
            ],
        ];

        foreach ($addressesByPhone as $phone => $addresses) {
            $user = User::where('phone', $phone)->first();
            if (!$user) {
                $this->command?->warn("User with phone {$phone} not found, skipping addresses");
                continue;
            }

            Address::where('user_id', $user->id)->delete();

            foreach ($addresses as $data) {
                $data['user_id'] = $user->id;
                Address::create($data);
            }
        }
    }
}