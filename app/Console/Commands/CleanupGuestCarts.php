<?php

namespace App\Console\Commands;

use App\Models\Cart;
use Illuminate\Console\Command;

class CleanupGuestCarts extends Command
{
    protected $signature = 'carts:cleanup
                            {--days=30 : Удалять гостевые корзины старше указанного числа дней}
                            {--dry-run : Только показать сколько будет удалено}';

    protected $description = 'Удалить устаревшие гостевые корзины (без user_id) и их позиции';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('--days должно быть >= 1');
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $query = Cart::query()
            ->whereNull('user_id')
            ->where('updated_at', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info("Нет гостевых корзин старше {$days} дней.");
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("DRY RUN: будет удалено {$count} гостевых корзин (старше {$days} дней).");
            return self::SUCCESS;
        }

        // cart_items удалятся каскадно благодаря FK constraint
        $deleted = $query->delete();

        $this->info("Удалено {$deleted} гостевых корзин (старше {$days} дней).");
        return self::SUCCESS;
    }
}