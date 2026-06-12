<?php

namespace App\Enums\Order;

enum OrderEventType: string
{
    case CREATED        = 'created';
    case STATUS_CHANGED = 'status_changed';
    case ITEM_ADDED     = 'item_added';
    case ITEM_UPDATED   = 'item_updated';
    case ITEM_REMOVED   = 'item_removed';
    case ITEM_RESTORED  = 'item_restored';
}