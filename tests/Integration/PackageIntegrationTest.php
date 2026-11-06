<?php

declare(strict_types=1);

use GeolizeStudio\Chile\Territory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

it('registers a lazy reusable territory catalog without network or database operations', function (): void {
    Http::preventStrayRequests();
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query;
    });

    expect(app()->resolved(Territory::class))->toBeFalse();
    $territory = app(Territory::class);
    expect(app(Territory::class))->toBe($territory)
        ->and($territory->comuna('01101')?->name)->toBe('Iquique')
        ->and($queries)->toBe([]);
    Http::assertNothingSent();
});

it('executes the shipped quickstart', function (): void {
    ob_start();
    try {
        require dirname(__DIR__, 2).'/examples/quickstart.php';
        expect(ob_get_contents())->toBe("Quickstart passed.\n");
    } finally {
        ob_end_clean();
    }
});
