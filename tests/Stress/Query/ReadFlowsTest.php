<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Livewire\Contacts\Form as ContactForm;
use App\Livewire\Contacts\Index as ContactIndex;
use App\Livewire\Contacts\View as ContactView;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Members\Form as MemberForm;
use App\Livewire\Members\Index as MemberIndex;
use App\Livewire\Members\View as MemberView;
use App\Livewire\Notifications\Index as NotificationIndex;
use App\Livewire\OrgSettings\ApprovalHistory;
use App\Livewire\OrgSettings\Audit as AuditPage;
use App\Livewire\OrgSettings\Features;
use App\Livewire\OrgSettings\General;
use App\Livewire\OrgSettings\Subsidiaries;
use App\Livewire\Settings\Organizations as SettingsOrganizations;
use App\Livewire\Settings\Preferences;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Security;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Repositories\ApprovalRepository;
use App\Repositories\ContactRepository;
use App\Repositories\MembershipRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\OrganizationRepository;
use App\Support\CurrentOrganization;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

/*
 * Read-only flow query/timing measurements.
 *
 * Requires a seeded database (default: query_baseline).
 * Run: QUERY=1 DB_DATABASE=query_baseline vendor/bin/pest tests/Stress --group=query-read
 */

beforeEach(function (): void {
    if (getenv('QUERY') !== '1') {
        $this->markTestSkipped('Set QUERY=1 and DB_DATABASE to a disposable database.');
    }

    if (getenv('DB_DATABASE') === false) {
        $this->markTestSkipped('Set DB_DATABASE to the database under measurement.');
    }

    expect(DB::connection()->getDatabaseName())->toBe(getenv('DB_DATABASE'));
});

$GLOBALS['baseline_queries'] = [];

function baselineReset(): void
{
    $GLOBALS['baseline_queries'] = [];
}

/** @return list<string> */
function baselineCapture(Closure $callback): array
{
    baselineReset();
    $callback();

    return $GLOBALS['baseline_queries'];
}

function baselineSql(string $sql): string
{
    $sql = preg_replace("/'[^']*'/", '?', $sql) ?? $sql;
    $sql = preg_replace('/\b\d+\b/', '?', $sql) ?? $sql;

    return preg_replace('/\s+/', ' ', $sql) ?? $sql;
}

function baselineMeasure(string $label, Closure $callback): void
{
    $times = [];
    $captured = [];

    for ($run = 0; $run < 3; $run++) {
        baselineReset();
        $start = hrtime(true);
        $callback();
        $times[] = (hrtime(true) - $start) / 1_000_000;

        if ($run === 0) {
            $captured = $GLOBALS['baseline_queries'];
        }
    }

    sort($times);

    fwrite(STDERR, \sprintf("### %-42s %8.1f ms %5d q\n", $label, $times[1], \count($captured)));

    $counts = [];

    foreach ($captured as $sql) {
        $key = baselineSql($sql);
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    arsort($counts);

    foreach (\array_slice($counts, 0, 4, true) as $sql => $count) {
        if ($count < 2) {
            continue;
        }

        fwrite(STDERR, \sprintf("    %2dx %s\n", $count, mb_substr($sql, 0, 150)));
    }
}

function baselineExplain(string $label, string $sql): void
{
    if (! str_starts_with(strtolower(ltrim($sql)), 'select')) {
        fwrite(STDERR, "    EXPLAIN {$label}: skipped (not a select)\n");

        return;
    }

    try {
        $rows = DB::select('EXPLAIN (ANALYZE, BUFFERS) '.$sql);
    } catch (Throwable $exception) {
        fwrite(STDERR, "    EXPLAIN {$label}: failed: {$exception->getMessage()}\n");

        return;
    }

    $lines = array_map(fn (object $row): string => (string) $row->{'QUERY PLAN'}, $rows);
    $plan = implode("\n", $lines);
    preg_match('/Execution Time: ([0-9.]+) ms/', $plan, $matches);

    fwrite(STDERR, \sprintf("    EXPLAIN %s: %s ms\n", $label, $matches[1] ?? '?'));

    foreach ($lines as $line) {
        if (preg_match('/Seq Scan|Index Scan|Bitmap|Sort Method|Execution Time/', $line) === 1) {
            fwrite(STDERR, '      '.trim($line)."\n");
        }
    }
}

test('multi-tenant query baseline', function () {
    DB::listen(function (QueryExecuted $query): void {
        $GLOBALS['baseline_queries'][] = $query->toRawSql();
    });

    $label = (string) env('AUDIT_LABEL', 'baseline');
    $database = DB::connection()->getDatabaseName();
    fwrite(STDERR, \sprintf("\n===== %s :: %s =====\n", $label, $database));

    $benchOwner = User::query()->where('handle', 'benchowner')->firstOrFail();
    $rootOwner = User::query()->where('handle', 'rootowner')->firstOrFail();
    $bench = Organization::query()->findOrFail(2);
    $root = Organization::query()->findOrFail(1);

    $benchContact = OrganizationRelationship::query()
        ->where('organization_id', 2)
        ->where('stakeholder_type', Organization::class)
        ->whereIn('type', OrganizationRelationshipType::externals())
        ->whereHas('stakeholder', fn ($query) => $query->where('name', 'like', 'Org 2-%'))
        ->firstOrFail();

    $benchPerson = Person::query()
        ->whereIn('id', $bench->members()->pluck('people.id'))
        ->whereKeyNot($benchOwner->person->getKey())
        ->firstOrFail();

    $switchTo = function (User $user, Organization $organization): void {
        test()->actingAs($user);
        session([CurrentOrganization::SESSION_KEY => $organization->getKey()]);
        app(CurrentOrganization::class)->forget();
    };

    $switchTo($benchOwner, $bench);

    baselineMeasure('bench dashboard', fn () => Livewire::test(DashboardIndex::class));
    baselineMeasure('bench contacts index', fn () => Livewire::test(ContactIndex::class));
    baselineMeasure('bench contacts page 2', fn () => Livewire::test(ContactIndex::class)->call('setPage', 2));
    baselineMeasure('bench contacts search', fn () => Livewire::test(ContactIndex::class)->set('search', 'Org 2-'));
    baselineMeasure('bench contact view', fn () => Livewire::test(ContactView::class, [
        'stakeholder' => $benchContact->stakeholder->uuid,
    ]));
    baselineMeasure('bench contact edit', fn () => Livewire::test(ContactForm::class)->call('openEdit', $benchContact->getKey()));
    baselineMeasure('bench members index', fn () => Livewire::test(MemberIndex::class));
    baselineMeasure('bench members search', fn () => Livewire::test(MemberIndex::class)->set('search', 'Member 2-'));
    baselineMeasure('bench member view', fn () => Livewire::test(MemberView::class, ['person' => $benchPerson]));
    baselineMeasure('bench member edit', fn () => Livewire::test(MemberForm::class, ['person' => $benchPerson]));
    baselineMeasure('bench notifications', fn () => Livewire::test(NotificationIndex::class));
    baselineMeasure('bench notification poll', fn () => test()->getJson(route('notifications.latest')));
    baselineMeasure('bench contact lookup', fn () => test()->getJson(route('contacts.lookup', ['q' => 'Shared'])));
    baselineMeasure('bench org general', fn () => Livewire::test(General::class));
    baselineMeasure('bench org features', fn () => Livewire::test(Features::class));
    baselineMeasure('bench approval history', fn () => Livewire::test(ApprovalHistory::class));
    baselineMeasure('bench audit', fn () => Livewire::test(AuditPage::class));
    baselineMeasure('bench settings organizations', fn () => Livewire::test(SettingsOrganizations::class));
    baselineMeasure('bench settings profile', fn () => Livewire::test(Profile::class));
    baselineMeasure('bench settings preferences', fn () => Livewire::test(Preferences::class));
    baselineMeasure('bench settings security', fn () => Livewire::test(Security::class));

    $switchTo($rootOwner, $root);

    baselineMeasure('root dashboard', fn () => Livewire::test(DashboardIndex::class));
    baselineMeasure('root contacts index', fn () => Livewire::test(ContactIndex::class));
    baselineMeasure('root members index', fn () => Livewire::test(MemberIndex::class));
    baselineMeasure('root subsidiaries', fn () => Livewire::test(Subsidiaries::class));
    baselineMeasure('root audit', fn () => Livewire::test(AuditPage::class));
    baselineMeasure('root approval history', fn () => Livewire::test(ApprovalHistory::class));
    baselineMeasure('root org general', fn () => Livewire::test(General::class));

    fwrite(STDERR, "\n--- EXPLAIN plans ---\n");

    $switchTo($benchOwner, $bench);

    $sql = baselineCapture(fn () => app(ContactRepository::class)->get($bench, ''));
    baselineExplain('contacts count', $sql[0] ?? '');
    baselineExplain('contacts page', $sql[1] ?? '');

    $sql = baselineCapture(fn () => app(ContactRepository::class)->get($bench, 'Org 2-'));
    baselineExplain('contacts search count', $sql[0] ?? '');

    $sql = baselineCapture(fn () => app(MembershipRepository::class)->get($bench, ''));
    baselineExplain('members count', $sql[0] ?? '');
    baselineExplain('members page', $sql[1] ?? '');

    $sql = baselineCapture(fn () => app(NotificationRepository::class)->get($benchOwner, false));
    baselineExplain('notifications page', $sql[1] ?? $sql[0] ?? '');

    $sql = baselineCapture(fn () => $benchOwner->unreadNotifications()->latest()->get());
    baselineExplain('notification poll', $sql[0] ?? '');

    $sql = baselineCapture(fn () => app(ApprovalRepository::class)->getForOrganization($bench));
    baselineExplain('approval history', $sql[0] ?? '');

    $sql = baselineCapture(fn () => app(ContactRepository::class)->countExpiring($bench, [
        'start' => now()->startOfWeek()->toDateString(),
        'end' => now()->endOfWeek()->toDateString(),
    ]));
    baselineExplain('dashboard expiring count', $sql[0] ?? '');

    $sql = baselineCapture(fn () => app(MembershipRepository::class)->countRecent($bench, [
        'start' => now()->startOfWeek()->toDateString(),
        'end' => now()->endOfWeek()->toDateString(),
    ]));
    baselineExplain('dashboard recent members', $sql[0] ?? '');

    $switchTo($rootOwner, $root);

    $sql = baselineCapture(fn () => app(OrganizationRepository::class)->getSubsidiaries($root));
    baselineExplain('subsidiary edges', $sql[0] ?? '');
    baselineExplain('subsidiary organizations', $sql[1] ?? '');

    expect(true)->toBeTrue();
})->group('query-read');
