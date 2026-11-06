<?php

declare(strict_types=1);

use GeolizeStudio\Chile\Exceptions\InvalidTerritoryData;
use GeolizeStudio\Chile\Territory;
use GeolizeStudio\Chile\Territory\MatchMethod;
use GeolizeStudio\Chile\Territory\ResolutionStatus;

it('loads the complete hash-verified official baseline with string CUT identities', function () {
    $territory = Territory::loadDefault();
    expect($territory->datasetVersion)->toBe('subdere-cut-2018-v04')
        ->and($territory->regions())->toHaveCount(16)
        ->and($territory->provinces())->toHaveCount(56)
        ->and($territory->comunas())->toHaveCount(346)
        ->and($territory->region('01')->name)->toBe('Tarapacá')
        ->and($territory->province('011')->regionCode)->toBe('01')
        ->and($territory->comuna('01101')->provinceCode)->toBe('011')
        ->and($territory->comuna('01101')->code)->toBe('01101')
        ->and($territory->comuna('08301')->name)->toBe('Los Angeles')
        ->and($territory->comuna('16303')->name)->toBe('Ñiquén');
    foreach ($territory->comunas() as $comuna) {
        expect($comuna->code)->toBeString()->toMatch('/^[0-9]{5}$/D')
            ->and($territory->province($comuna->provinceCode)->regionCode)->toBe($comuna->regionCode)
            ->and($territory->region($comuna->regionCode))->not->toBeNull()
            ->and($territory->resolveComuna($comuna->code)->match)->toBe($comuna)
            ->and($territory->resolveComuna($comuna->name)->match)->toBe($comuna);
    }
    expect(array_unique(array_column($territory->comunas(), 'code')))->toHaveCount(346);
});

it('returns null for unknown and unpadded identities and scopes lists without guessing', function () {
    $territory = Territory::loadDefault();
    expect($territory->region('1'))->toBeNull()
        ->and($territory->province('11'))->toBeNull()
        ->and($territory->comuna('1101'))->toBeNull()
        ->and($territory->comuna('99999'))->toBeNull()
        ->and($territory->provinces('01'))->toHaveCount(2)
        ->and($territory->comunas('01'))->toHaveCount(7)
        ->and($territory->comunas(provinceCode: '011'))->toHaveCount(2)
        ->and($territory->comunas('13', '011'))->toBe([])
        ->and($territory->comunas('99'))->toBe([])
        ->and($territory->resolveComuna('01101')->method)->toBe(MatchMethod::Code)
        ->and($territory->resolveComuna('01101', '13')->status)->toBe(ResolutionStatus::NotFound)
        ->and($territory->resolveComuna('99999')->status)->toBe(ResolutionStatus::NotFound);
});

it('reports each normalization and never silently repairs mojibake or approximate spelling', function () {
    $territory = Territory::loadDefault();
    $fixtures = json_decode(file_get_contents(__DIR__.'/../fixtures/territory/encoding.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($fixtures as $fixture) {
        $result = $territory->resolveComuna($fixture['input']);
        expect($result->original)->toBe($fixture['input'])
            ->and($result->match?->code)->toBe($fixture['code'])
            ->and($result->method?->value)->toBe($fixture['method'])
            ->and($result->transformations)->toBe($fixture['transformations']);
    }
    expect($territory->resolveComuna('La Serena')->match?->name)->toBe('La Serena')
        ->and($territory->resolveComuna('Serena')->status)->toBe(ResolutionStatus::NotFound)
        ->and($territory->resolveComuna('')->status)->toBe(ResolutionStatus::NotFound)
        ->and($territory->resolveComuna('   ')->status)->toBe(ResolutionStatus::NotFound);
});

it('makes aliases explicit, isolated, contextual and ambiguous when necessary', function () {
    $territory = Territory::loadDefault(['Port' => ['13101', '01101'], 'PÓRT' => ['01101'], 'La Calera' => ['05502'], 'Santiago' => ['01101']]);
    $result = $territory->resolveComuna('port');
    expect($result->status)->toBe(ResolutionStatus::Ambiguous)
        ->and($result->method)->toBe(MatchMethod::Alias)
        ->and($result->match)->toBeNull()
        ->and(array_column($result->candidates, 'code'))->toBe(['01101', '13101'])
        ->and($territory->resolveComuna('port', '01')->match?->code)->toBe('01101')
        ->and($territory->resolveComuna('port', '16')->status)->toBe(ResolutionStatus::NotFound)
        ->and($territory->resolveComuna('La Calera')->match?->name)->toBe('Calera')
        ->and($territory->resolveComuna('Santiago')->method)->toBe(MatchMethod::Exact)
        ->and($territory->resolveComuna('SANTIAGO')->match?->code)->toBe('13101')
        ->and(Territory::loadDefault()->resolveComuna('port')->status)->toBe(ResolutionStatus::NotFound);
});

it('rejects alias configurations that could hide invalid identities', function (array $aliases) {
    expect(fn () => Territory::loadDefault($aliases))->toThrow(InvalidTerritoryData::class);
})->with([
    'unknown code' => [['port' => ['99999']]],
    'integer code' => [['port' => [13101]]],
    'empty list' => [['port' => []]],
    'scalar target' => [['port' => '13101']],
    'empty name' => [['' => ['13101']]],
    'numeric name' => [['13101' => ['01101']]],
    'prefix only' => [['comuna de ' => ['13101']]],
]);

it('rejects malformed UTF-8 and unknown resolution context', function () {
    $territory = Territory::loadDefault();
    expect(fn () => $territory->resolveComuna("\xff"))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $territory->resolveComuna('Santiago', '99'))->toThrow(InvalidArgumentException::class);
});

it('validates custom catalogs and reports real name collisions', function () {
    $data = json_decode(file_get_contents(__DIR__.'/../fixtures/territory/minimal.json'), true, flags: JSON_THROW_ON_ERROR);
    $data['comunas'][0]['name'] = 'Central';
    $data['comunas'][2]['name'] = 'Central';
    $path = tempnam(sys_get_temp_dir(), 'chile-test-');
    try {
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
        $territory = Territory::fromFile($path);
        expect($territory->resolveComuna('Central')->status)->toBe(ResolutionStatus::Ambiguous)
            ->and($territory->resolveComuna('CENTRAL')->status)->toBe(ResolutionStatus::Ambiguous)
            ->and($territory->resolveComuna('Central', '01')->match?->code)->toBe('01101');
    } finally {
        unlink($path);
    }
});

it('fails closed on schema, code, text and hierarchy corruption', function (Closure $change) {
    $data = json_decode(file_get_contents(__DIR__.'/../fixtures/territory/minimal.json'), true, flags: JSON_THROW_ON_ERROR);
    $change($data);
    $path = tempnam(sys_get_temp_dir(), 'chile-test-');
    try {
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
        expect(fn () => Territory::fromFile($path))->toThrow(InvalidTerritoryData::class);
    } finally {
        unlink($path);
    }
})->with([
    'schema version' => [function (&$d) {
        $d['schema_version'] = 2;
    }],
    'extra field' => [function (&$d) {
        $d['surprise'] = true;
    }],
    'missing version' => [function (&$d) {
        unset($d['dataset_version']);
    }],
    'empty table' => [function (&$d) {
        $d['comunas'] = [];
    }],
    'table object' => [function (&$d) {
        $d['comunas'] = ['record' => $d['comunas'][0]];
    }],
    'invalid row' => [function (&$d) {
        $d['comunas'][0] = 'not an object';
    }],
    'integer code' => [function (&$d) {
        $d['comunas'][0]['code'] = 1101;
    }],
    'unpadded code' => [function (&$d) {
        $d['comunas'][0]['code'] = '1101';
    }],
    'non ASCII digit' => [function (&$d) {
        $d['comunas'][0]['code'] = '０1101';
    }],
    'duplicate comuna' => [function (&$d) {
        $d['comunas'][] = $d['comunas'][0];
    }],
    'duplicate region' => [function (&$d) {
        $d['regions'][] = $d['regions'][0];
    }],
    'duplicate province' => [function (&$d) {
        $d['provinces'][] = $d['provinces'][0];
    }],
    'wrong prefix' => [function (&$d) {
        $d['comunas'][0]['code'] = '13199';
    }],
    'missing parent' => [function (&$d) {
        $d['comunas'][0]['province_code'] = '012';
    }],
    'wrong region' => [function (&$d) {
        $d['comunas'][0]['region_code'] = '13';
    }],
    'province region mismatch' => [function (&$d) {
        $d['provinces'][0]['region_code'] = '13';
    }],
    'orphan region' => [function (&$d) {
        $d['regions'][] = ['code' => '02', 'name' => 'Example'];
    }],
    'orphan province' => [function (&$d) {
        $d['provinces'][] = ['code' => '012', 'name' => 'Example', 'region_code' => '01'];
    }],
    'extra row field' => [function (&$d) {
        $d['comunas'][0]['alias'] = 'Port';
    }],
    'control character' => [function (&$d) {
        $d['comunas'][0]['name'] = "Iqui\0que";
    }],
    'replacement character' => [function (&$d) {
        $d['comunas'][0]['name'] = 'Iqui�que';
    }],
    'invisible character' => [function (&$d) {
        $d['comunas'][0]['name'] = "Iqui\u{200B}que";
    }],
    'padded name' => [function (&$d) {
        $d['comunas'][0]['name'] = ' Iquique';
    }],
    'empty name' => [function (&$d) {
        $d['comunas'][0]['name'] = '';
    }],
]);

it('does not accept remote sources or malformed JSON at runtime', function () {
    expect(fn () => Territory::fromFile('https://example.com/catalog.json'))->toThrow(InvalidTerritoryData::class)
        ->and(fn () => Territory::fromFile('/missing/chile/catalog.json'))->toThrow(InvalidTerritoryData::class);
    $path = tempnam(sys_get_temp_dir(), 'chile-test-');
    try {
        foreach (['{', '[]', 'null', '"catalog"'] as $contents) {
            file_put_contents($path, $contents);
            expect(fn () => Territory::fromFile($path))->toThrow(InvalidTerritoryData::class);
        }
    } finally {
        unlink($path);
    }
});

it('exposes immutable records and detached lists', function () {
    $territory = Territory::loadDefault();
    $comuna = $territory->comuna('01101');
    expect(fn () => $comuna->name = 'Changed')->toThrow(Error::class);
    $list = $territory->comunas();
    array_pop($list);
    expect($territory->comunas())->toHaveCount(346);
});
