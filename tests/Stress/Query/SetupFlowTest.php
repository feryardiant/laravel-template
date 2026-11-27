<?php

declare(strict_types=1);

use App\Actions\Tenancies\ProvisionTenant;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

/*
 * First-install provisioning measurement.
 *
 * Requires an empty migrated database (default: query_write_setup).
 * Run: QUERY_WRITE=1 DB_DATABASE=query_write_setup vendor/bin/pest tests/Stress --group=query-setup
 */

beforeEach(function (): void {
    if (getenv('QUERY_WRITE') !== '1') {
        $this->markTestSkipped('Set QUERY_WRITE=1 and DB_DATABASE to a disposable database.');
    }

    if (getenv('DB_DATABASE') === false) {
        $this->markTestSkipped('Set DB_DATABASE to the database under measurement.');
    }

    expect(DB::connection()->getDatabaseName())->toBe(getenv('DB_DATABASE'));
});

$GLOBALS['setup_queries'] = [];

function setupMeasure(string $label, Closure $callback): void
{
    $GLOBALS['setup_queries'] = [];
    $start = hrtime(true);
    $callback();
    $elapsed = (hrtime(true) - $start) / 1_000_000;
    /** @var string[] $queries */
    $queries = $GLOBALS['setup_queries'];

    fwrite(STDERR, \sprintf("### %-40s %8.1f ms %5d q\n", $label, $elapsed, \count($queries)));

    $counts = [];

    foreach ($queries as $sql) {
        $key = preg_replace('/\b\d+\b/', '?', preg_replace("/'[^']*'/", '?', $sql) ?? $sql) ?? $sql;
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    arsort($counts);

    foreach (\array_slice($counts, 0, 6, true) as $sql => $count) {
        if ($count >= 2) {
            fwrite(STDERR, \sprintf("    %2dx %s\n", $count, mb_substr($sql, 0, 140)));
        }
    }
}

test('setup provisioning', function () {
    DB::listen(function (QueryExecuted $query): void {
        $GLOBALS['setup_queries'][] = $query->toRawSql();
    });

    User::factory()->withoutProfile()->create([
        'name' => 'Placeholder',
        'email' => config('setup.initial_email'),
        'handle' => 'setup',
    ]);

    setupMeasure('setup: provision root tenant', fn () => app(ProvisionTenant::class)
        ->handle('First Owner', 'first-owner@example.test', 'password'));

    $root = Organization::query()->rootTenant()->firstOrFail();
    $owner = Person::query()->where('user_id', User::query()->where('email', 'first-owner@example.test')->value('id'))->firstOrFail();

    expect($root->isRootTenant())->toBeTrue()
        ->and($root->members()->whereKey($owner->getKey())->exists())->toBeTrue();

    expect(true)->toBeTrue();
})->group('query-setup');
