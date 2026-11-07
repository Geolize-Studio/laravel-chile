<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile;

use Illuminate\Support\ServiceProvider;

final class ChileServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/chile.php', 'chile');

        $this->app->singleton(Territory::class, static fn (): Territory => Territory::loadDefault());
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__).'/config/chile.php' => config_path('chile.php'),
        ], 'chile-config');
    }
}
