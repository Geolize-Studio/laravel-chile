<?php

declare(strict_types=1);

use GeolizeStudio\Chile\Clp;
use GeolizeStudio\Chile\Iva;
use GeolizeStudio\Chile\RoundingMode;
use GeolizeStudio\Chile\Rut;
use GeolizeStudio\Chile\Territory;

require dirname(__DIR__).'/vendor/autoload.php';

$territory = Territory::loadDefault();
$rate = Iva::standard();
$names = ['Ñuñoa', 'nunoa', 'Comuna de Vicuña', 'Concepción', '01101', 'Unknown place'];
$samples = [];
for ($index = 0; $index < 4; $index++) {
    $samples[$index] = ['batch' => $index + 1, 'rows' => 10_000, 'elapsed_ms' => 0.0, 'retained_bytes' => 0, 'allocated_bytes' => 0];
}
for ($batch = 0; $batch < 4; $batch++) {
    $start = hrtime(true);
    for ($row = 0; $row < 10_000; $row++) {
        $rut = Rut::parse('12.345.678-5');
        $place = $territory->resolveComuna($names[$row % count($names)]);
        $price = $rate->fromNet(Clp::fromPesos($row), RoundingMode::HalfAwayFromZero);
        if ($price->net->add($price->tax)->pesos() !== $price->gross->pesos()) {
            throw new RuntimeException('An import violated the accounting invariant.');
        }
    }
    unset($rut, $place, $price);
    gc_collect_cycles();
    $samples[$batch]['elapsed_ms'] = round((hrtime(true) - $start) / 1_000_000, 2);
    $samples[$batch]['retained_bytes'] = memory_get_usage();
    $samples[$batch]['allocated_bytes'] = memory_get_usage(true);
}

echo json_encode(['php' => PHP_VERSION, 'icu' => INTL_ICU_VERSION, 'samples' => $samples], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
