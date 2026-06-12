<?php

namespace Database\Seeders;

use App\Enums\Role\RoleEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole  = Role::firstOrCreate(['title' => RoleEnum::ADMIN->value]);
        $clientRole = Role::firstOrCreate(['title' => RoleEnum::CLIENT->value]);

        // Admin (как было — минимальные поля)
        $admin = User::firstOrCreate(
            ['email' => 'user@mail.ru'],
            [
                'email'    => 'user@mail.ru',
                'password' => Hash::make(12345678),
            ]
        );
        $admin->roles()->sync($adminRole->id);

        // Clients — реалистичные данные
        $clients = [
            [
                'first_name' => 'Тест',
                'last_name'  => 'Пользователь',
                'phone'      => '+79999999999',
                'email'      => null,
                'gender'     => 'other',
                'birth_date' => '2000-01-01',
            ],
            [
                'first_name' => 'Иван',
                'last_name'  => 'Петров',
                'phone'      => '+79161234567',
                'email'      => 'ivan.petrov@mail.ru',
                'gender'     => 'male',
                'birth_date' => '1990-05-15',
            ],
            [
                'first_name' => 'Анна',
                'last_name'  => 'Смирнова',
                'phone'      => '+79262345678',
                'email'      => 'anna.smirnova@gmail.com',
                'gender'     => 'female',
                'birth_date' => '1995-08-22',
            ],
            [
                'first_name' => 'Дмитрий',
                'last_name'  => 'Соколов',
                'phone'      => '+79153456789',
                'email'      => 'd.sokolov@yandex.ru',
                'gender'     => 'male',
                'birth_date' => '1985-12-03',
            ],
            [
                'first_name' => 'Мария',
                'last_name'  => 'Кузнецова',
                'phone'      => '+79254567890',
                'email'      => 'maria.k@mail.ru',
                'gender'     => 'female',
                'birth_date' => '1998-03-17',
            ],
            [
                'first_name' => 'Алексей',
                'last_name'  => 'Васильев',
                'phone'      => '+79165678901',
                'email'      => 'aleksey.v@gmail.com',
                'gender'     => 'male',
                'birth_date' => '1992-11-08',
            ],
            [
                'first_name' => 'Екатерина',
                'last_name'  => 'Новикова',
                'phone'      => '+79296789012',
                'email'      => 'kate.novikova@yandex.ru',
                'gender'     => 'female',
                'birth_date' => '2001-07-29',
            ],
        ];

        foreach ($clients as $data) {
            $data['password'] = Hash::make(12345678);

            $client = User::updateOrCreate(
                ['phone' => $data['phone']],
                $data
            );

            $client->roles()->sync($clientRole->id);
        }
    }
}