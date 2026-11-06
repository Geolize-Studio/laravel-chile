<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile;

use Illuminate\Support\ServiceProvider;

final class ChileServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Territory::class, static fn (): Territory => Territory::loadDefault());
    }
}
