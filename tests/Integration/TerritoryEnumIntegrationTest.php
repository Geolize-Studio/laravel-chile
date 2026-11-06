<?php

declare(strict_types=1);

use GeolizeStudio\Chile\Comuna;
use GeolizeStudio\Chile\Province;
use GeolizeStudio\Chile\Region;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TerritoryEnumAddress extends Model
{
    protected $table = 'territory_enum_addresses';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'region_code' => Region::class,
            'province_code' => Province::class,
            'comuna_code' => Comuna::class,
        ];
    }
}

beforeEach(function () {
    Schema::create('territory_enum_addresses', function (Blueprint $table): void {
        $table->id();
        $table->string('region_code', 2)->nullable();
        $table->string('province_code', 3)->nullable();
        $table->string('comuna_code', 5)->nullable();
    });
});

it('roundtrips native enum casts through VARCHAR columns without losing leading zeroes', function () {
    $address = TerritoryEnumAddress::create([
        'region_code' => Region::Tarapaca,
        'province_code' => Province::Iquique,
        'comuna_code' => Comuna::Iquique,
    ])->fresh();

    expect($address->region_code)->toBe(Region::Tarapaca)
        ->and($address->province_code)->toBe(Province::Iquique)
        ->and($address->comuna_code)->toBe(Comuna::Iquique)
        ->and($address->getRawOriginal('region_code'))->toBe('01')
        ->and($address->getRawOriginal('province_code'))->toBe('011')
        ->and($address->getRawOriginal('comuna_code'))->toBe('01101')
        ->and(json_decode($address->toJson(), true, flags: JSON_THROW_ON_ERROR))->toBe([
            'id' => $address->id,
            'region_code' => '01',
            'province_code' => '011',
            'comuna_code' => '01101',
        ]);
});

it('preserves null through native enum persistence and model serialization', function () {
    $address = TerritoryEnumAddress::create([
        'region_code' => null,
        'province_code' => null,
        'comuna_code' => null,
    ])->fresh();

    expect($address->region_code)->toBeNull()
        ->and($address->province_code)->toBeNull()
        ->and($address->comuna_code)->toBeNull()
        ->and($address->toArray())->toBe([
            'id' => $address->id,
            'region_code' => null,
            'province_code' => null,
            'comuna_code' => null,
        ])
        ->and($address->isDirty())->toBeFalse();
});

it('keeps unchanged casts clean and persists explicit changes as string CUT codes', function () {
    $address = TerritoryEnumAddress::create(['comuna_code' => '01101'])->fresh();
    expect($address->comuna_code)->toBe(Comuna::Iquique)
        ->and($address->isDirty())->toBeFalse();

    $address->comuna_code = Comuna::Iquique;
    expect($address->isDirty('comuna_code'))->toBeFalse();
    $address->save();
    expect($address->fresh()->getRawOriginal('comuna_code'))->toBe('01101');

    $address->comuna_code = Comuna::Nunoa;
    expect($address->isDirty('comuna_code'))->toBeTrue();
    $address->save();
    expect($address->fresh()->comuna_code)->toBe(Comuna::Nunoa)
        ->and($address->fresh()->getRawOriginal('comuna_code'))->toBe('13120');
});

it('rejects invalid enum assignments without changing the stored value', function () {
    $address = TerritoryEnumAddress::create(['comuna_code' => Comuna::Iquique]);
    expect(fn () => $address->comuna_code = '99999')->toThrow(ValueError::class)
        ->and(fn () => $address->comuna_code = Region::Tarapaca)->toThrow(ValueError::class)
        ->and($address->fresh()->comuna_code)->toBe(Comuna::Iquique);
});

it('makes invalid historical values visible instead of silently rewriting them', function () {
    $id = DB::table('territory_enum_addresses')->insertGetId(['comuna_code' => '1101']);
    $address = TerritoryEnumAddress::findOrFail($id);

    expect(fn () => $address->comuna_code)->toThrow(ValueError::class)
        ->and(fn () => $address->toArray())->toThrow(ValueError::class)
        ->and(DB::table('territory_enum_addresses')->where('id', $id)->value('comuna_code'))->toBe('1101');
});

it('works with Laravel enum validation while keeping parent consistency explicit', function () {
    $rules = ['comuna_code' => ['nullable', 'string', Rule::enum(Comuna::class)]];
    expect(Validator::make(['comuna_code' => '01101'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['comuna_code' => null], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['comuna_code' => 13120], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['comuna_code' => '1101'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['comuna_code' => '99999'], $rules)->fails())->toBeTrue();
});
