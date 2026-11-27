<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

/*
 * Public registration measurement.
 *
 * Requires a seeded database with a root tenant (default: query_write_registration).
 * Run: QUERY_WRITE=1 DB_DATABASE=query_write_registration vendor/bin/pest tests/Stress --group=query-registration
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

$GLOBALS['registration_queries'] = [];

function registrationMeasure(string $label, Closure $callback): void
{
    $GLOBALS['registration_queries'] = [];
    $start = hrtime(true);
    $callback();
    $elapsed = (hrtime(true) - $start) / 1_000_000;
    /** @var string[] $queries */
    $queries = $GLOBALS['registration_queries'];

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

test('public registration tenant provisioning', function () {
    DB::listen(function (QueryExecuted $query): void {
        $GLOBALS['registration_queries'][] = $query->toRawSql();
    });

    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
    $email = "registered-{$suffix}@example.test";

    registrationMeasure('registration: user + public tenant', fn () => app(CreateNewUser::class)->create([
        'name' => "Registered {$suffix}",
        'handle' => "reg{$suffix}",
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]));

    $user = User::query()->where('email', $email)->firstOrFail();
    $person = Person::query()->where('user_id', $user->getKey())->firstOrFail();
    $tenant = Organization::query()
        ->where('contact_id', $person->getKey())
        ->where('is_tenant', true)
        ->firstOrFail();

    expect($tenant->root_organization_id)->not->toBeNull()
        ->and($tenant->members()->whereKey($person->getKey())->wherePivot('role', Role::Owner->value)->exists())->toBeTrue();
})->group('query-registration');
