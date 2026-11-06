<?php

declare(strict_types=1);

use GeolizeStudio\Chile\Clp;
use GeolizeStudio\Chile\Exceptions\InvalidAmount;
use GeolizeStudio\Chile\Laravel\Casts\ClpCast;
use GeolizeStudio\Chile\Laravel\Rules\ClpRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class MoneyInvoice extends Model
{
    protected function casts(): array
    {
        return ['amount' => ClpCast::class];
    }
}

it('validates CLP request data without rewriting its representation', function () {
    $validator = Validator::make(['amount' => '$1.234'], ['amount' => ['required', new ClpRule]]);

    expect($validator->passes())->toBeTrue()
        ->and($validator->validated())->toBe(['amount' => '$1.234']);
});

it('rejects invalid CLP request values', function (mixed $value) {
    $validator = Validator::make(['amount' => $value], ['amount' => ['required', new ClpRule]]);
    expect($validator->fails())->toBeTrue();
})->with([[1.5], [true], ['1.234,56'], ['bad'], [''], ['9223372036854775808']]);

it('leaves null and required validation to the Laravel boundary', function () {
    expect(Validator::make(['amount' => null], ['amount' => ['nullable', new ClpRule]])->passes())->toBeTrue()
        ->and(Validator::make(['amount' => null], ['amount' => ['required', new ClpRule]])->fails())->toBeTrue();
});

it('casts amounts to integer storage and immutable domain values', function () {
    $model = new MoneyInvoice;
    $model->amount = '$1.234';

    expect($model->getAttributes()['amount'])->toBe(1234)
        ->and($model->amount)->toBeInstanceOf(Clp::class)
        ->and($model->amount->pesos())->toBe(1234)
        ->and($model->toArray()['amount'])->toBe(1234)
        ->and(json_decode($model->toJson(), true)['amount'])->toBe(1234);

    $model->amount = Clp::fromPesos(-500);
    expect($model->getAttributes()['amount'])->toBe(-500);

    $model->amount = null;
    expect($model->amount)->toBeNull()
        ->and($model->getAttributes()['amount'])->toBeNull()
        ->and($model->toArray()['amount'])->toBeNull();
});

it('does not canonicalize legacy display strings just by reading the cast', function () {
    $model = new MoneyInvoice;
    $model->setRawAttributes(['amount' => '$1.234'], true);

    expect($model->amount->pesos())->toBe(1234)
        ->and($model->getAttributes()['amount'])->toBe('$1.234')
        ->and($model->isDirty('amount'))->toBeFalse();
});

it('reads database integer strings without marking an unchanged value dirty', function () {
    $model = new MoneyInvoice;
    $model->setRawAttributes(['amount' => '1234'], true);

    expect($model->amount->pesos())->toBe(1234)
        ->and($model->isDirty('amount'))->toBeFalse();

    $model->amount = Clp::fromPesos(1234);
    expect($model->isDirty('amount'))->toBeFalse();

    $model->amount = Clp::fromPesos(1235);
    expect($model->isDirty('amount'))->toBeTrue();
});

it('makes malformed historical amounts visible instead of rewriting them', function () {
    $model = new MoneyInvoice;
    $model->setRawAttributes(['amount' => 'CLP 1234 invalid'], true);

    expect(fn () => $model->amount)->toThrow(InvalidAmount::class)
        ->and($model->getRawOriginal('amount'))->toBe('CLP 1234 invalid')
        ->and(fn () => $model->amount = 1234.0)->toThrow(InvalidAmount::class);
});
