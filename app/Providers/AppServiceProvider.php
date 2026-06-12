<?php

namespace App\Providers;

use App\Services\SmsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsService::class, fn() => new SmsService());
    }
}
