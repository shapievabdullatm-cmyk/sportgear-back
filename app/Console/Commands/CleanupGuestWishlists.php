<?php

namespace App\Console\Commands;

use App\Models\Wishlist;
use Illuminate\Console\Command;

class CleanupGuestWishlists extends Command
{
    protected $signature = 'wishlists:cleanup
                            {--days=30 : Удалять гостевое избранное старше указанного числа дней}
                            {--dry-run : Только показать сколько будет удалено}';

    protected $description = 'Удалить устаревшие гостевые wishlist (без user_id) и их позиции';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('--days должно быть >= 1');
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $query = Wishlist::query()
            ->whereNull('user_id')
            ->where('updated_at', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info("Нет гостевого избранного старше {$days} дней.");
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("DRY RUN: будет удалено {$count} гостевых wishlist (старше {$days} дней).");
            return self::SUCCESS;
        }

        // wishlist_items удалятся каскадно благодаря FK constraint
        $deleted = $query->delete();

        $this->info("Удалено {$deleted} гостевых wishlist (старше {$days} дней).");
        return self::SUCCESS;
    }
}