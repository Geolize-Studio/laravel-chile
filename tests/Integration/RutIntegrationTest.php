<?php

declare(strict_types=1);

use GeolizeStudio\Chile\Exceptions\InvalidRut;
use GeolizeStudio\Chile\Laravel\Casts\RutCast;
use GeolizeStudio\Chile\Laravel\Rules\RutRule;
use GeolizeStudio\Chile\Rut;
use GeolizeStudio\Chile\RutFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class RutTestRecord extends Model
{
    public $timestamps = false;

    protected $table = 'rut_test_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rut' => RutCast::class];
    }
}

beforeEach(function (): void {
    Schema::create('rut_test_records', function (Blueprint $table): void {
        $table->id();
        $table->string('rut')->nullable();
    });
});

it('validates real Laravel request values', function (): void {
    expect(Validator::make(['rut' => '12.345.678-5'], ['rut' => ['required', new RutRule]])->passes())->toBeTrue();

    $validator = Validator::make(['rut' => '12345678-4'], ['rut' => ['required', new RutRule]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('rut'))->toBe('The rut field must be a valid RUT.')
        ->and(Validator::make(['rut' => 123456785], ['rut' => [new RutRule]])->fails())->toBeTrue();
});

it('validates every supported presentation independently of the configured output', function (string $input): void {
    config()->set('chile.rut.format', 'compact');

    $validator = Validator::make(['rut' => $input], ['rut' => ['required', new RutRule]]);

    expect($validator->passes())->toBeTrue()
        ->and($validator->validated()['rut'])->toBe($input);
})->with(['12345670k', '12345670-k', '12.345.670-k', '12.345.670k']);

it('lets Laravel required and nullable rules decide whether absence is allowed', function (): void {
    expect(Validator::make(['rut' => null], ['rut' => ['nullable', new RutRule]])->passes())->toBeTrue()
        ->and(Validator::make([], ['rut' => ['required', new RutRule]])->fails())->toBeTrue()
        ->and(Validator::make(['rut' => ''], ['rut' => ['required', new RutRule]])->fails())->toBeTrue()
        ->and(Validator::make(['rut' => null], ['rut' => [new RutRule]])->fails())->toBeTrue();
});

it('stores canonical text and returns immutable values through Eloquent', function (): void {
    $record = RutTestRecord::create(['rut' => '12.345.670-k'])->fresh();

    expect(DB::table('rut_test_records')->value('rut'))->toBe('12345670-K')
        ->and($record->rut)->toBeInstanceOf(Rut::class)
        ->and($record->rut->format())->toBe('12.345.670-K')
        ->and($record->toArray()['rut'])->toBe('12345670-K')
        ->and(json_decode($record->toJson(), true, flags: JSON_THROW_ON_ERROR)['rut'])->toBe('12345670-K');

    $record->rut = Rut::parse('12345678-5');
    $record->save();

    expect($record->fresh()->rut->toString())->toBe('12345678-5');
});

it('preserves null on writes reads and serialization', function (): void {
    $record = RutTestRecord::create(['rut' => null])->fresh();

    expect($record->rut)->toBeNull()
        ->and($record->toArray()['rut'])->toBeNull()
        ->and(DB::table('rut_test_records')->value('rut'))->toBeNull();

    $record->rut = '12345678-5';
    $record->save();
    $record->rut = null;
    $record->save();

    expect($record->fresh()->rut)->toBeNull();
});

it('compares dirty attributes using canonical stored values', function (): void {
    $record = RutTestRecord::create(['rut' => '12345678-5'])->fresh();
    $record->rut = '12.345.678-5';

    expect($record->isDirty('rut'))->toBeFalse();

    $record->rut = Rut::parse('12345678-5');

    expect($record->isDirty('rut'))->toBeFalse();

    $record->rut = '12345670-K';

    expect($record->isDirty('rut'))->toBeTrue();
});

it('rejects invalid writes without changing an existing value', function (mixed $invalid): void {
    $record = RutTestRecord::create(['rut' => '12345678-5']);

    expect(fn () => $record->rut = $invalid)->toThrow(InvalidRut::class)
        ->and($record->getRawOriginal('rut'))->toBe('12345678-5')
        ->and($record->isDirty('rut'))->toBeFalse()
        ->and(DB::table('rut_test_records')->value('rut'))->toBe('12345678-5');
})->with(['wrong DV' => ['12345678-4'], 'missing DV' => ['12345678'], 'integer' => [123456785], 'empty' => ['']]);

it('reports malformed historical data when reading or serializing', function (): void {
    $id = DB::table('rut_test_records')->insertGetId(['rut' => '12345678-4']);
    $record = RutTestRecord::findOrFail($id);

    expect(fn () => $record->rut)->toThrow(InvalidRut::class)
        ->and(fn () => $record->toArray())->toThrow(InvalidRut::class)
        ->and(DB::table('rut_test_records')->where('id', $id)->value('rut'))->toBe('12345678-4');
});

it('does not rewrite historical presentation merely by reading and saving a model', function (): void {
    $id = DB::table('rut_test_records')->insertGetId(['rut' => '12.345.670-k']);
    $record = RutTestRecord::findOrFail($id);

    expect($record->rut->toString())->toBe('12345670-K')
        ->and($record->toArray()['rut'])->toBe('12345670-K')
        ->and($record->isDirty('rut'))->toBeFalse();

    $record->save();

    expect(DB::table('rut_test_records')->where('id', $id)->value('rut'))->toBe('12.345.670-k');

    $record->rut = $record->rut;
    $record->save();

    expect(DB::table('rut_test_records')->where('id', $id)->value('rut'))->toBe('12345670-K');
});

it('uses the configured format for storage and every cast output', function (string $format, string $expected, string $input): void {
    config()->set('chile.rut.format', $format);
    $record = new RutTestRecord;
    $record->mergeCasts(['rut' => Rut::class]);

    $record->rut = $input;
    $record->save();
    $record = RutTestRecord::query()->withCasts(['rut' => Rut::class])->findOrFail($record->id);

    expect(DB::table('rut_test_records')->value('rut'))->toBe($expected)
        ->and($record->rut->toString())->toBe($expected)
        ->and((string) $record->rut)->toBe($expected)
        ->and(json_encode($record->rut, JSON_THROW_ON_ERROR))->toBe('"'.$expected.'"')
        ->and($record->toArray()['rut'])->toBe($expected)
        ->and(json_decode($record->toJson(), true, flags: JSON_THROW_ON_ERROR)['rut'])->toBe($expected);

    $record->rut = Rut::parse('12345670-K')->withFormat(RutFormat::Formatted);

    expect($record->isDirty('rut'))->toBeFalse();
})->with([
    'canonical' => ['canonical', '12345670-K'],
    'compact' => ['compact', '12345670K'],
    'formatted' => ['formatted', '12.345.670-K'],
])->with(['12345670k', '12345670-k', '12.345.670-k', '12.345.670k']);

it('lets an attribute override the application format', function (string $cast): void {
    config()->set('chile.rut.format', 'formatted');
    $record = new RutTestRecord;
    $record->mergeCasts(['rut' => $cast.':compact']);

    $record->rut = '12.345.678-5';
    $record->save();
    $reloaded = RutTestRecord::query()->withCasts(['rut' => $cast.':compact'])->findOrFail($record->id);

    expect(DB::table('rut_test_records')->value('rut'))->toBe('123456785')
        ->and($reloaded->rut->toString())->toBe('123456785')
        ->and($reloaded->toArray()['rut'])->toBe('123456785');
})->with([Rut::class, RutCast::class]);

it('preserves historical storage until an attribute is explicitly assigned', function (string $historical): void {
    config()->set('chile.rut.format', 'formatted');
    $id = DB::table('rut_test_records')->insertGetId(['rut' => $historical]);
    $record = RutTestRecord::findOrFail($id);

    expect($record->rut->toString())->toBe('12.345.670-K')
        ->and($record->toArray()['rut'])->toBe('12.345.670-K')
        ->and($record->isDirty('rut'))->toBeFalse();
    $record->save();

    expect(DB::table('rut_test_records')->where('id', $id)->value('rut'))->toBe($historical);

    $record->rut = $record->rut;
    $record->save();

    expect(DB::table('rut_test_records')->where('id', $id)->value('rut'))->toBe('12.345.670-K');
})->with(['12345670k', '12.345.670-k']);

it('does not leak an earlier configuration into later cast reads', function (): void {
    config()->set('chile.rut.format', 'compact');
    $record = RutTestRecord::create(['rut' => '12345670-K']);
    $compact = $record->rut;

    config()->set('chile.rut.format', 'formatted');

    expect($record->rut->toString())->toBe('12.345.670-K')
        ->and($record->toArray()['rut'])->toBe('12.345.670-K')
        ->and($compact->toString())->toBe('12345670K')
        ->and(DB::table('rut_test_records')->value('rut'))->toBe('12345670K')
        ->and(Rut::parse('12345670k')->toString())->toBe('12345670-K');
});

it('preserves null under a configured output format', function (): void {
    config()->set('chile.rut.format', 'compact');

    $record = RutTestRecord::create(['rut' => null])->fresh();

    expect($record->rut)->toBeNull()
        ->and($record->toArray()['rut'])->toBeNull()
        ->and(DB::table('rut_test_records')->value('rut'))->toBeNull();
});

it('rejects an invalid configured format before overwriting a stored identifier', function (mixed $format): void {
    $record = RutTestRecord::create(['rut' => '12345678-5']);
    config()->set('chile.rut.format', $format);

    expect(fn () => $record->rut = '12345670K')->toThrow(InvalidArgumentException::class);

    expect(DB::table('rut_test_records')->value('rut'))->toBe('12345678-5');
})->with(['unknown name' => ['invalid'], 'integer' => [123], 'null' => [null], 'array' => [[]]]);
