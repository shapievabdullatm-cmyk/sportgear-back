<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ежедневная чистка гостевых корзин и избранного старше 30 дней
Schedule::command('carts:cleanup')->dailyAt('03:30');
Schedule::command('wishlists:cleanup')->dailyAt('03:35');
