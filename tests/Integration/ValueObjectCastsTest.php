<?php

declare(strict_types=1);

use GeolizeStudio\Chile\Clp;
use GeolizeStudio\Chile\Exceptions\InvalidAmount;
use GeolizeStudio\Chile\Exceptions\InvalidRut;
use GeolizeStudio\Chile\Exceptions\InvalidTaxRate;
use GeolizeStudio\Chile\Iva;
use GeolizeStudio\Chile\RoundingMode;
use GeolizeStudio\Chile\Rut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ValueObjectRecord extends Model
{
    public $timestamps = false;

    protected $table = 'value_object_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => Clp::class,
            'rut' => Rut::class,
            'iva_rate' => Iva::class,
            'rounding' => RoundingMode::class,
        ];
    }
}

beforeEach(function (): void {
    Schema::create('value_object_records', function (Blueprint $table): void {
        $table->id();
        $table->bigInteger('amount')->nullable();
        $table->string('rut')->nullable();
        $table->json('iva_rate')->nullable();
        $table->string('rounding')->nullable();
    });
});

it('persists and serializes value objects directly through their class names', function (): void {
    $record = ValueObjectRecord::create([
        'amount' => Clp::fromPesos(-50),
        'rut' => Rut::parse('12.345.678-5'),
        'iva_rate' => Iva::standard(),
        'rounding' => RoundingMode::HalfAwayFromZero,
    ])->fresh();

    expect($record->amount->pesos())->toBe(-50)
        ->and($record->rut->toString())->toBe('12345678-5')
        ->and($record->iva_rate)->toEqual(Iva::standard())
        ->and($record->rounding)->toBe(RoundingMode::HalfAwayFromZero)
        ->and($record->iva_rate->fromNet($record->amount, $record->rounding)->tax->pesos())->toBe(-10)
        ->and(DB::table('value_object_records')->value('iva_rate'))->toBe('{"numerator":19,"denominator":100}')
        ->and($record->toArray())->toMatchArray([
            'amount' => -50,
            'rut' => '12345678-5',
            'iva_rate' => ['numerator' => 19, 'denominator' => 100],
            'rounding' => 'half_away_from_zero',
        ])
        ->and(json_decode($record->toJson(), true, flags: JSON_THROW_ON_ERROR))->toBe($record->toArray());

    $record->amount = '$1.234';
    $record->rut = '12.345.670-k';
    $record->iva_rate = ['numerator' => 1, 'denominator' => 10];
    $record->save();
    $record->refresh();

    expect($record->amount->pesos())->toBe(1234)
        ->and($record->rut->toString())->toBe('12345670-K')
        ->and($record->iva_rate->toArray())->toBe(['numerator' => 1, 'denominator' => 10]);
});

it('preserves null independently of zero rates in persistence and serialization', function (): void {
    $record = ValueObjectRecord::create(['amount' => null, 'rut' => null, 'iva_rate' => null])->fresh();

    expect($record->amount)->toBeNull()
        ->and($record->rut)->toBeNull()
        ->and($record->iva_rate)->toBeNull()
        ->and($record->toArray())->toMatchArray(['amount' => null, 'rut' => null, 'iva_rate' => null]);

    $record->iva_rate = Iva::fromRatio(0, 100);
    expect($record->isDirty('iva_rate'))->toBeTrue();
    $record->save();
    expect($record->fresh()->iva_rate->toArray())->toBe(['numerator' => 0, 'denominator' => 1]);

    $record->iva_rate = null;
    expect($record->isDirty('iva_rate'))->toBeTrue();
    $record->save();
    expect($record->fresh()->iva_rate)->toBeNull()
        ->and(DB::table('value_object_records')->value('iva_rate'))->toBeNull();
});

it('compares equivalent rates independently of JSON ordering and fraction representation', function (): void {
    $raw = '{ "denominator": 10000, "numerator": 1900 }';
    $id = DB::table('value_object_records')->insertGetId(['iva_rate' => $raw]);
    $record = ValueObjectRecord::findOrFail($id);

    expect($record->iva_rate->toArray())->toBe(['numerator' => 19, 'denominator' => 100])
        ->and($record->iva_rate)->not->toBe($record->iva_rate)
        ->and($record->isDirty('iva_rate'))->toBeFalse();
    $record->save();
    expect(DB::table('value_object_records')->value('iva_rate'))->toBe($raw);

    $record->iva_rate = Iva::standard();
    expect($record->isDirty('iva_rate'))->toBeFalse();
    $record->iva_rate = ['denominator' => 1000, 'numerator' => 190];
    expect($record->isDirty('iva_rate'))->toBeFalse();
    $record->save();
    expect(DB::table('value_object_records')->value('iva_rate'))->toBe($raw);

    $record->iva_rate = Iva::fromRatio(20, 100);
    expect($record->isDirty('iva_rate'))->toBeTrue();
    $record->save();
    expect($record->fresh()->iva_rate->toArray())->toBe(['numerator' => 1, 'denominator' => 5]);
});

it('rejects invalid rate assignments before changing the current value', function (mixed $invalid): void {
    $record = ValueObjectRecord::create(['iva_rate' => Iva::standard()])->fresh();

    expect(fn () => $record->iva_rate = $invalid)->toThrow(InvalidTaxRate::class)
        ->and($record->isDirty('iva_rate'))->toBeFalse()
        ->and($record->iva_rate)->toEqual(Iva::standard())
        ->and(DB::table('value_object_records')->value('iva_rate'))->toBe('{"numerator":19,"denominator":100}');
})->with([
    [19], [0.19], [true], ['19%'], ['{"numerator":19,"denominator":100}'], [[]], [new stdClass],
    [['numerator' => 19]], [['numerator' => 19.0, 'denominator' => 100]],
    [['numerator' => 19, 'denominator' => 0]], [['numerator' => -19, 'denominator' => 100]],
    [['numerator' => 1000001, 'denominator' => 100]],
    [['numerator' => 19, 'denominator' => 100, 'rounding' => 'half_even']],
]);

it('rejects malformed historical rate data without altering it', function (string $raw): void {
    $id = DB::table('value_object_records')->insertGetId(['iva_rate' => $raw]);
    $record = ValueObjectRecord::findOrFail($id);

    expect(fn () => $record->iva_rate)->toThrow(InvalidTaxRate::class)
        ->and(fn () => $record->toArray())->toThrow(InvalidTaxRate::class)
        ->and(DB::table('value_object_records')->value('iva_rate'))->toBe($raw);

    $record->iva_rate = Iva::standard();
    expect($record->isDirty('iva_rate'))->toBeTrue();
    $record->save();
    expect($record->fresh()->iva_rate)->toEqual(Iva::standard());
})->with([
    ['broken'], ['null'], ['19'], ['0.19'], ['[]'], ['{}'],
    ['{"numerator":19}'], ['{"numerator":19.0,"denominator":100}'],
    ['{"numerator":"19","denominator":100}'], ['{"numerator":19,"denominator":0}'],
    ['{"numerator":19,"denominator":100,"extra":1}'],
]);

it('retains strict CLP and RUT behavior through direct value object casts', function (): void {
    $record = ValueObjectRecord::create(['amount' => 1234, 'rut' => '12345678-5'])->fresh();
    $record->amount = '$1.234';
    $record->rut = '12.345.678-5';

    expect($record->isDirty())->toBeFalse()
        ->and(fn () => $record->amount = 1234.0)->toThrow(InvalidAmount::class)
        ->and(fn () => $record->rut = '12345678-4')->toThrow(InvalidRut::class);

    DB::table('value_object_records')->where('id', $record->id)->update(['rut' => '12.345.670-k']);
    $record->refresh();
    expect($record->rut->toString())->toBe('12345670-K');
    $record->save();
    expect(DB::table('value_object_records')->value('rut'))->toBe('12.345.670-k');
});
