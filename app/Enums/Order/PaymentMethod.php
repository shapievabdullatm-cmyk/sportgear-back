<?php

namespace App\Enums\Order;

enum PaymentMethod: string
{
    case CASH             = 'cash';
    case CARD_ON_DELIVERY = 'card_on_delivery';

    public function label(): string
    {
        return match ($this) {
            self::CASH             => 'Наличными при получении',
            self::CARD_ON_DELIVERY => 'Картой при получении',
        };
    }
}