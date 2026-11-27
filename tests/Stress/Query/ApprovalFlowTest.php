<?php

declare(strict_types=1);

use App\Actions\Approvals\OwnershipTransfer;
use App\Enums\ApprovalRequestStatus;
use App\Enums\Role;
use App\Livewire\Settings\Organizations\TransferApproval;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

/*
 * Ownership-transfer accept/decline measurement.
 *
 * Mutates data; run against a disposable clone.
 * Run: QUERY_WRITE=1 DB_DATABASE=query_write_approvals vendor/bin/pest tests/Stress --group=query-approval
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

$GLOBALS['approval_queries'] = [];

function approvalMeasure(string $label, Closure $callback): void
{
    $GLOBALS['approval_queries'] = [];
    $start = hrtime(true);
    $callback();
    $elapsed = (hrtime(true) - $start) / 1_000_000;
    /** @var string[] $queries */
    $queries = $GLOBALS['approval_queries'];

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

test('approval accept and decline', function () {
    DB::listen(function (QueryExecuted $query): void {
        $GLOBALS['approval_queries'][] = $query->toRawSql();
    });

    $rootOwner = User::query()->where('handle', 'rootowner')->firstOrFail();
    $root = Organization::query()->findOrFail(1);
    $bench = Organization::query()->findOrFail(2);

    $switchTo = function (User $user, Organization $organization): void {
        test()->actingAs($user);
        session([CurrentOrganization::SESSION_KEY => $organization->getKey()]);
        app(CurrentOrganization::class)->forget();
    };

    $recipient = $root->members()
        ->whereKeyNot($rootOwner->person->getKey())
        ->whereHas('user')
        ->firstOrFail();

    $recipientUser = $recipient->user;
    \assert($recipientUser instanceof User);

    app(OwnershipTransfer::class)->request($root, $rootOwner, $recipient);

    $switchTo($recipientUser, $root);

    approvalMeasure('ownership transfer accept', fn () => Livewire::test(TransferApproval::class)
        ->call('respond', $root->getKey())
        ->call('accept'));

    expect($root->fresh()->members()->wherePivot('role', Role::Owner->value)->value('people.id'))->toBe($recipient->getKey());

    $pending = ApprovalRequest::query()
        ->where('organization_id', $bench->getKey())
        ->where('status', ApprovalRequestStatus::Pending)
        ->firstOrFail();

    $approver = Person::query()->findOrFail($pending->approver_person_id);
    $approverUser = $approver->user;
    \assert($approverUser instanceof User);

    $switchTo($approverUser, $bench);

    approvalMeasure('ownership transfer decline', fn () => Livewire::test(TransferApproval::class)
        ->call('respond', $bench->getKey())
        ->call('decline'));

    expect($pending->fresh()->status)->toBe(ApprovalRequestStatus::Declined);

    expect(true)->toBeTrue();
})->group('query-approval');
