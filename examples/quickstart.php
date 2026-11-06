<?php

declare(strict_types=1);

use GeolizeStudio\Chile\Clp;
use GeolizeStudio\Chile\Iva;
use GeolizeStudio\Chile\RoundingMode;
use GeolizeStudio\Chile\Rut;
use GeolizeStudio\Chile\Territory;
use GeolizeStudio\Chile\Territory\ResolutionStatus;

// A consumer may load its own Composer autoloader before including this example.
if (! class_exists(Rut::class)) {
    require dirname(__DIR__).'/vendor/autoload.php';
}

$rut = Rut::parse('12.345.678-5');
$territory = Territory::loadDefault();
$place = $territory->resolveComuna('Ñuñoa');
$price = Iva::standard()->fromNet(Clp::fromPesos(10_000), RoundingMode::HalfAwayFromZero);

if ($rut->toString() !== '12345678-5'
    || Rut::tryParse('12.345.678-9') !== null
    || $place->status !== ResolutionStatus::Matched
    || $place->match?->code !== '13120'
    || $territory->comuna('01101')?->name !== 'Iquique'
    || Clp::tryParse('1.234,56') !== null
    || $price->tax->pesos() !== 1_900
    || $price->gross->pesos() !== 11_900) {
    throw new RuntimeException('The documented quickstart did not produce its expected results.');
}

echo "Quickstart passed.\n";
