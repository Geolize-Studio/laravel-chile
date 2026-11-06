<?php

declare(strict_types=1);

namespace Tests;

use GeolizeStudio\Chile\ChileServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ChileServiceProvider::class];
    }
}
