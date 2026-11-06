<?php

declare(strict_types=1);

use GeolizeStudio\Chile\ChileServiceProvider;
use GeolizeStudio\Chile\Clp;
use GeolizeStudio\Chile\Laravel\Rules\ClpRule;
use GeolizeStudio\Chile\Laravel\Rules\RutRule;
use GeolizeStudio\Chile\Territory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Validation\Factory;
use Tests\TestCase;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->getProvider(ChileServiceProvider::class)
    || ! $app->bound(Territory::class)
    || $app->make(Territory::class) !== $app->make(Territory::class)) {
    throw new RuntimeException('Automatic provider discovery or singleton binding failed.');
}

$validator = $app->make(Factory::class);
if (! $validator->make(['rut' => '12.345.678-5', 'price' => '$1.234'], [
    'rut' => ['required', new RutRule],
    'price' => ['required', new ClpRule],
])->passes() || ! $validator->make(['price' => 1.5], ['price' => [new ClpRule]])->fails()) {
    throw new RuntimeException('Production validation integration failed.');
}

if (class_exists(TestCase::class) || class_exists(Orchestra\Testbench\TestCase::class)
    || Clp::fromPesos(0)->pesos() !== 0) {
    throw new RuntimeException('Production isolation failed.');
}

$package = __DIR__.'/vendor/geolize-studio/laravel-chile';
foreach (['resources/boost/guidelines/core.blade.php', 'resources/boost/skills/chile-development/SKILL.md', 'resources/territory/catalog-manifest.json'] as $resource) {
    if (! is_file($package.'/'.$resource)) {
        throw new RuntimeException('A required package resource is missing: '.$resource);
    }
}
require $package.'/examples/quickstart.php';
echo 'Consumer passed on PHP '.PHP_VERSION.' / Laravel '.$app->version().".\n";
