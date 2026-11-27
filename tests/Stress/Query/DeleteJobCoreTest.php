<?php

declare(strict_types=1);

use App\Actions\FileStorage;
use App\Jobs\DeleteOrganization;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class);

/*
 * DeleteOrganization job core measurement (notifications faked).
 *
 * Mutates data; run against a disposable clone.
 * Run: QUERY_WRITE=1 DB_DATABASE=query_write_delete_core vendor/bin/pest tests/Stress --group=query-delete-core
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

$GLOBALS['job_core_queries'] = [];

function jobCoreMeasure(string $label, Closure $callback): void
{
    $GLOBALS['job_core_queries'] = [];
    $start = hrtime(true);
    $callback();
    $elapsed = (hrtime(true) - $start) / 1_000_000;
    $queries = $GLOBALS['job_core_queries'];

    fwrite(STDERR, \sprintf("### %-40s %8.1f ms %5d q\n", $label, $elapsed, \count($queries)));
}

test('delete organization job core', function () {
    DB::listen(function (QueryExecuted $query): void {
        $GLOBALS['job_core_queries'][] = $query->toRawSql();
    });

    Notification::fake();

    $rootOwner = User::query()->where('handle', 'rootowner')->firstOrFail();
    $organization = Organization::query()->findOrFail(10);

    jobCoreMeasure('delete job core (notifications faked)', fn () => (new DeleteOrganization($organization, $rootOwner->getKey()))
        ->handle(app(FileStorage::class)));

    $deleted = Organization::withTrashed()->find(10);

    expect($deleted === null || $deleted->trashed())->toBeTrue();
})->group('query-delete-core');
