<?php

declare(strict_types=1);

use GeolizeStudio\Chile\ChileServiceProvider;
use GeolizeStudio\Chile\Clp;
use GeolizeStudio\Chile\Comuna;
use GeolizeStudio\Chile\Iva;
use GeolizeStudio\Chile\Laravel\Rules\ClpRule;
use GeolizeStudio\Chile\Laravel\Rules\RutRule;
use GeolizeStudio\Chile\Province;
use GeolizeStudio\Chile\Region;
use GeolizeStudio\Chile\RoundingMode;
use GeolizeStudio\Chile\Rut;
use GeolizeStudio\Chile\Territory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$expectedFormat = $argv[1] ?? 'canonical';
$expectedRut = match ($expectedFormat) {
    'canonical' => '12345678-5',
    'compact' => '123456785',
    default => throw new RuntimeException('Unknown consumer RUT format.'),
};
if ($app['config']->get('chile.rut.format', 'canonical') !== $expectedFormat) {
    throw new RuntimeException('The published RUT configuration was not loaded.');
}

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

class ConsumerRecord extends Model
{
    public $timestamps = false;

    protected $connection = 'chile_smoke';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => Clp::class,
            'rut' => Rut::class,
            'iva_rate' => Iva::class,
            'rounding' => RoundingMode::class,
            'region' => Region::class,
            'province' => Province::class,
            'comuna' => Comuna::class,
        ];
    }
}

$app['config']->set('database.connections.chile_smoke', [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
]);
$connection = $app['db']->connection('chile_smoke');
$connection->getSchemaBuilder()->create('consumer_records', function (Blueprint $table): void {
    $table->id();
    $table->bigInteger('amount')->nullable();
    $table->string('rut')->nullable();
    $table->json('iva_rate')->nullable();
    $table->string('rounding')->nullable();
    $table->string('region', 2)->nullable();
    $table->string('province', 3)->nullable();
    $table->string('comuna', 5)->nullable();
});

$record = ConsumerRecord::create([
    'amount' => Clp::fromPesos(-50),
    'rut' => Rut::parse('12.345.678-5'),
    'iva_rate' => Iva::standard(),
    'rounding' => RoundingMode::HalfAwayFromZero,
    'region' => Region::Tarapaca,
    'province' => Province::Iquique,
    'comuna' => Comuna::Iquique,
])->fresh();

if ($record === null || $record->amount->pesos() !== -50
    || $record->rut->toString() !== $expectedRut
    || (string) $record->rut !== $expectedRut
    || json_encode($record->rut, JSON_THROW_ON_ERROR) !== json_encode($expectedRut, JSON_THROW_ON_ERROR)
    || $record->iva_rate->toArray() !== ['numerator' => 19, 'denominator' => 100]
    || $record->rounding !== RoundingMode::HalfAwayFromZero
    || $record->region !== Region::Tarapaca
    || $record->province !== Province::Iquique
    || $record->comuna !== Comuna::Iquique
    || $record->iva_rate->fromNet($record->amount, $record->rounding)->tax->pesos() !== -10) {
    throw new RuntimeException('Production Eloquent value object or enum hydration failed.');
}

$expected = [
    'amount' => -50,
    'rut' => $expectedRut,
    'iva_rate' => ['numerator' => 19, 'denominator' => 100],
    'rounding' => 'half_away_from_zero',
    'region' => '01',
    'province' => '011',
    'comuna' => '01101',
];
$serialized = json_decode($record->toJson(), true, flags: JSON_THROW_ON_ERROR);
$stored = (array) $connection->table('consumer_records')->where('id', $record->id)->first();
foreach ($expected as $attribute => $value) {
    $storedValue = $attribute === 'iva_rate' ? json_encode($value, JSON_THROW_ON_ERROR) : $value;
    if ($serialized[$attribute] !== $value || $record->toArray()[$attribute] !== $value
        || $stored[$attribute] !== $storedValue) {
        throw new RuntimeException('Production Eloquent storage or serialization failed: '.$attribute);
    }
}

$record->amount = '-$50';
$record->rut = '12.345.678-5';
$record->iva_rate = ['denominator' => 10000, 'numerator' => 1900];
$record->region = '01';
$record->province = '011';
$record->comuna = '01101';
if ($record->isDirty()) {
    throw new RuntimeException('Equivalent Eloquent value objects or enum codes were marked dirty.');
}
$record->save();

foreach (array_keys($expected) as $attribute) {
    $record->{$attribute} = null;
}
if (! $record->isDirty('iva_rate')) {
    throw new RuntimeException('An explicit null assignment did not mark the IVA rate dirty.');
}
$record->save();
$record->refresh();
$serialized = json_decode($record->toJson(), true, flags: JSON_THROW_ON_ERROR);
$stored = (array) $connection->table('consumer_records')->where('id', $record->id)->first();
foreach (array_keys($expected) as $attribute) {
    if ($record->{$attribute} !== null || $serialized[$attribute] !== null || $stored[$attribute] !== null) {
        throw new RuntimeException('Production Eloquent null preservation failed: '.$attribute);
    }
}

$package = __DIR__.'/vendor/geolize-studio/laravel-chile';
foreach (['resources/boost/guidelines/core.blade.php', 'resources/boost/skills/chile-development/SKILL.md', 'resources/territory/catalog-manifest.json'] as $resource) {
    if (! is_file($package.'/'.$resource)) {
        throw new RuntimeException('A required package resource is missing: '.$resource);
    }
}
require $package.'/examples/quickstart.php';
echo 'Consumer passed on PHP '.PHP_VERSION.' / Laravel '.$app->version().".\n";
