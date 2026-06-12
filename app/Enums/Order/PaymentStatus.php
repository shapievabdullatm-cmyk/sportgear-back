<?php

namespace App\Enums\Order;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PAID    = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Ожидает оплаты',
            self::PAID    => 'Оплачен',
        };
    }
}