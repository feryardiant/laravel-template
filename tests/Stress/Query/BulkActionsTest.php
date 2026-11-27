<?php

declare(strict_types=1);

use App\Livewire\Contacts\Index as ContactIndex;
use App\Livewire\Contacts\Partials\DeleteContactsModal;
use App\Livewire\Members\Index as MemberIndex;
use App\Livewire\Members\Partials\RemoveMembersModal;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

/*
 * Bulk action query/timing measurements.
 *
 * Mutates data; run against a disposable clone.
 * Run: QUERY_WRITE=1 DB_DATABASE=query_baseline_bulk vendor/bin/pest tests/Stress --group=query-bulk
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

$GLOBALS['bulk_queries'] = [];

function bulkReset(): void
{
    $GLOBALS['bulk_queries'] = [];
}

function bulkMeasure(string $label, Closure $callback): void
{
    $times = [];
    $captured = [];

    for ($run = 0; $run < 2; $run++) {
        bulkReset();
        $start = hrtime(true);
        $callback();
        $times[] = (hrtime(true) - $start) / 1_000_000;

        if ($run === 0) {
            $captured = $GLOBALS['bulk_queries'];
        }
    }

    sort($times);

    fwrite(STDERR, \sprintf("### %-40s %8.1f ms %5d q\n", $label, $times[0], \count($captured)));

    $counts = [];

    foreach ($captured as $sql) {
        $key = preg_replace('/\b\d+\b/', '?', preg_replace("/'[^']*'/", '?', $sql) ?? $sql) ?? $sql;
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    arsort($counts);

    foreach (\array_slice($counts, 0, 5, true) as $sql => $count) {
        if ($count < 2) {
            continue;
        }

        fwrite(STDERR, \sprintf("    %2dx %s\n", $count, mb_substr($sql, 0, 140)));
    }
}

test('bulk action baseline', function () {
    DB::listen(function (QueryExecuted $query): void {
        $GLOBALS['bulk_queries'][] = $query->toRawSql();
    });

    $owner = User::query()->where('handle', 'benchowner')->firstOrFail();
    $bench = Organization::query()->findOrFail(2);

    test()->actingAs($owner);
    session([CurrentOrganization::SESSION_KEY => $bench->getKey()]);
    app(CurrentOrganization::class)->forget();

    $contactIds = OrganizationRelationship::query()
        ->where('organization_id', 2)
        ->where('stakeholder_type', Organization::class)
        ->whereIn('type', ['customer', 'supplier', 'vendor'])
        ->orderBy('id')
        ->limit(10)
        ->pluck('id')
        ->all();

    $memberIds = Person::query()
        ->whereIn('id', $bench->members()->pluck('people.id'))
        ->whereKeyNot($owner->person->getKey())
        ->orderBy('id')
        ->limit(5)
        ->pluck('id')
        ->all();

    bulkMeasure('contacts toggleSelectAll (10 rows)', fn () => Livewire::test(ContactIndex::class)->call('toggleSelectAll'));
    bulkMeasure('members toggleSelectAll (10 rows)', fn () => Livewire::test(MemberIndex::class)->call('toggleSelectAll'));
    bulkMeasure('contacts bulk delete (10 rows)', fn () => Livewire::test(DeleteContactsModal::class)
        ->call('confirm', $contactIds)
        ->call('delete'));
    bulkMeasure('members bulk remove (5 rows)', fn () => Livewire::test(RemoveMembersModal::class)
        ->call('confirm', $memberIds)
        ->call('delete'));

    expect(true)->toBeTrue();
})->group('query-bulk');
