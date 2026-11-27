<?php

declare(strict_types=1);

use App\Actions\FileStorage;
use App\Jobs\DeleteOrganization;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

/*
 * DeleteOrganization job measurement.
 *
 * Mutates data; run against a disposable clone.
 * Run: QUERY_WRITE=1 DB_DATABASE=query_write_delete vendor/bin/pest tests/Stress --group=query-delete
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

$GLOBALS['job_queries'] = [];

function jobMeasure(string $label, Closure $callback): void
{
    $GLOBALS['job_queries'] = [];
    $start = hrtime(true);
    $callback();
    $elapsed = (hrtime(true) - $start) / 1_000_000;
    /** @var string[] $queries */
    $queries = $GLOBALS['job_queries'];

    fwrite(STDERR, \sprintf("### %-40s %8.1f ms %5d q\n", $label, $elapsed, \count($queries)));

    $counts = [];

    foreach ($queries as $sql) {
        $key = preg_replace('/\b\d+\b/', '?', preg_replace("/'[^']*'/", '?', $sql) ?? $sql) ?? $sql;
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    arsort($counts);

    foreach (\array_slice($counts, 0, 8, true) as $sql => $count) {
        if ($count >= 2) {
            fwrite(STDERR, \sprintf("    %2dx %s\n", $count, mb_substr($sql, 0, 140)));
        }
    }
}

test('delete organization job', function () {
    DB::listen(function (QueryExecuted $query): void {
        $GLOBALS['job_queries'][] = $query->toRawSql();
    });

    $rootOwner = User::query()->where('handle', 'rootowner')->firstOrFail();
    $organization = Organization::query()->findOrFail(10);

    jobMeasure('delete organization job (tenant 10)', fn () => (new DeleteOrganization($organization, $rootOwner->getKey()))
        ->handle(app(FileStorage::class)));

    $deleted = Organization::withTrashed()->find(10);

    expect($deleted === null || $deleted->trashed())->toBeTrue();
})->group('query-delete');
