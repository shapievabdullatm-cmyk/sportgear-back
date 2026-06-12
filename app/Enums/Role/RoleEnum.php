<?php
// app/Enums/Role/RoleEnum.php

namespace App\Enums\Role;

enum RoleEnum: string
{
    case ADMIN = 'admin';
    case CLIENT  = 'client';

    public function label(): string
    {
        return match($this) {
            RoleEnum::ADMIN => 'Администратор',
            RoleEnum::CLIENT  => 'Пользователь',
        };
    }
}
