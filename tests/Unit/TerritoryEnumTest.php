<?php

declare(strict_types=1);

use GeolizeStudio\Chile\Comuna;
use GeolizeStudio\Chile\Province;
use GeolizeStudio\Chile\Region;
use GeolizeStudio\Chile\Territory;

it('represents every accepted territory record with its exact label code and parents', function () {
    $territory = Territory::loadDefault();

    expect(Region::cases())->toHaveCount(16)
        ->and(Province::cases())->toHaveCount(56)
        ->and(Comuna::cases())->toHaveCount(346);

    foreach ($territory->regions() as $record) {
        $region = Region::from($record->code);
        expect($region->code())->toBe($record->code)->toBeString()
            ->and($region->label())->toBe($record->name)
            ->and(array_column($region->provinces(), 'value'))->toBe(array_column($territory->provinces($record->code), 'code'))
            ->and(array_column($region->comunas(), 'value'))->toBe(array_column($territory->comunas($record->code), 'code'));
    }

    foreach ($territory->provinces() as $record) {
        $province = Province::from($record->code);
        expect($province->code())->toBe($record->code)->toBeString()
            ->and($province->label())->toBe($record->name)
            ->and($province->region())->toBe(Region::from($record->regionCode))
            ->and(array_column($province->comunas(), 'value'))->toBe(array_column($territory->comunas(provinceCode: $record->code), 'code'));
    }

    foreach ($territory->comunas() as $record) {
        $comuna = Comuna::from($record->code);
        expect($comuna->code())->toBe($record->code)->toBeString()
            ->and($comuna->label())->toBe($record->name)
            ->and($comuna->region())->toBe(Region::from($record->regionCode))
            ->and($comuna->province())->toBe(Province::from($record->provinceCode));
    }
});

it('provides stable symbols while preserving the accepted spelling of official labels', function () {
    expect(Region::Metropolitana->code())->toBe('13')
        ->and(Region::OHiggins->label())->toBe("Libertador General Bernardo O'Higgins")
        ->and(Comuna::Nunoa->code())->toBe('13120')
        ->and(Comuna::Nunoa->label())->toBe('Ñuñoa')
        ->and(Comuna::Nunoa->province())->toBe(Province::Santiago)
        ->and(Comuna::Nunoa->region())->toBe(Region::Metropolitana)
        ->and(Comuna::LosAngeles->label())->toBe('Los Angeles')
        ->and(Comuna::LosAlamos->label())->toBe('Los Alamos')
        ->and(Comuna::Calera->label())->toBe('Calera');
});

it('returns CUT ordered select options with intersecting parent filters', function () {
    expect(Region::options())->toHaveCount(16)
        ->and(Region::options()['01'])->toBe('Tarapacá')
        ->and(Province::options())->toHaveCount(56)
        ->and(Province::options(Region::Tarapaca))->toBe(['011' => 'Iquique', '014' => 'Tamarugal'])
        ->and(Comuna::options())->toHaveCount(346)
        ->and(Comuna::options(province: Province::Iquique))->toBe(['01101' => 'Iquique', '01107' => 'Alto Hospicio'])
        ->and(Comuna::options(Region::Tarapaca))->toHaveCount(7)
        ->and(Comuna::options(Region::Tarapaca, Province::Iquique))->toBe(Comuna::options(province: Province::Iquique))
        ->and(Comuna::options(Region::Metropolitana, Province::Iquique))->toBe([]);

    foreach (Region::cases() as $region) {
        expect(array_map(strval(...), array_keys(Province::options($region))))->toBe(array_column($region->provinces(), 'value'))
            ->and(array_map(strval(...), array_keys(Comuna::options($region))))->toBe(array_column($region->comunas(), 'value'));
    }
});

it('preserves string enum values despite PHP numeric string array key coercion', function () {
    expect(array_key_first(Region::options()))->toBe('01')
        ->and(array_keys(Region::options())[12])->toBe(13)
        ->and(Region::Metropolitana->value)->toBe('13')
        ->and(Comuna::Iquique->value)->toBe('01101')
        ->and(json_encode(Comuna::Iquique, JSON_THROW_ON_ERROR))->toBe('"01101"');
});

it('rejects unknown unpadded and label inputs without normalization', function () {
    expect(Region::tryFrom('1'))->toBeNull()
        ->and(Region::tryFrom('99'))->toBeNull()
        ->and(Province::tryFrom('11'))->toBeNull()
        ->and(Comuna::tryFrom('1101'))->toBeNull()
        ->and(Comuna::tryFrom('Ñuñoa'))->toBeNull()
        ->and(Comuna::tryFrom('99999'))->toBeNull()
        ->and(fn () => Comuna::from('99999'))->toThrow(ValueError::class);
});
