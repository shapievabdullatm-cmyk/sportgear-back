<?php

namespace App\Enums\Order;

enum OrderStatus: string
{
    case NEW        = 'new';
    case CONFIRMED  = 'confirmed';
    case ASSEMBLING = 'assembling';
    case PACKED     = 'packed';
    case SHIPPED    = 'shipped';
    case DELIVERED  = 'delivered';
    case CANCELLED  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NEW        => 'Новый',
            self::CONFIRMED  => 'Подтверждён',
            self::ASSEMBLING => 'Собирается',
            self::PACKED     => 'Собран',
            self::SHIPPED    => 'Отправлен',
            self::DELIVERED  => 'Доставлен',
            self::CANCELLED  => 'Отменён',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::DELIVERED, self::CANCELLED], true);
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::NEW, self::CONFIRMED], true);
    }
}