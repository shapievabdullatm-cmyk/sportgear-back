<?php

namespace App\Enums\Order;

enum DeliveryMethod: string
{
    case COURIER       = 'courier';
    case PICKUP        = 'pickup';
    case CDEK          = 'cdek';
    case RUSSIAN_POST  = 'russian_post';

    public function label(): string
    {
        return match ($this) {
            self::COURIER      => 'Курьер',
            self::PICKUP       => 'Самовывоз из магазина',
            self::CDEK         => 'СДЭК',
            self::RUSSIAN_POST => 'Почта России',
        };
    }
}
