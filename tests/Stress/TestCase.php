<?php

declare(strict_types=1);

namespace Tests\Stress;

use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Pest\Stressless\Factory;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase as BaseTestCase;
use Throwable;

use function Pest\Stressless\stress;

/**
 * Shared helpers for the stress and query-measurement suites.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Queries captured by the DB listener.
     *
     * @var list<QueryExecuted>
     */
    private array $queries = [];

    /**
     * Per-handle session cookie cache.
     *
     * @var array<string, string>
     */
    private static array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();

        DB::listen(function (QueryExecuted $query): void {
            $this->queries[] = $query;
        });
    }

    /**
     * Run a callback and print its median duration and repeated queries.
     */
    protected function measure(string $label, Closure $callback, int $runs = 1): void
    {
        $times = [];
        $captured = [];

        for ($run = 0; $run < $runs; $run++) {
            $this->queries = [];
            $start = hrtime(true);
            $callback();
            $times[] = (hrtime(true) - $start) / 1_000_000;

            if ($run === 0) {
                $captured = $this->queries;
            }
        }

        sort($times);

        $raw = array_map(fn (QueryExecuted $query): string => $query->toRawSql(), $captured);

        fwrite(STDERR, \sprintf(
            "### %-42s %8.1f ms %5d q\n",
            $label,
            $times[intdiv(\count($times), 2)],
            \count($raw),
        ));

        $counts = [];

        foreach ($raw as $sql) {
            $key = $this->normalizeSql($sql);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        arsort($counts);

        foreach (\array_slice($counts, 0, 6, true) as $sql => $count) {
            if ($count >= 2) {
                fwrite(STDERR, \sprintf("    %2dx %s\n", $count, mb_substr($sql, 0, 140)));
            }
        }
    }

    /**
     * Run a callback and return the SQL it issued.
     *
     * @return list<string>
     */
    protected function capture(Closure $callback): array
    {
        $this->queries = [];
        $callback();

        return array_map(fn (QueryExecuted $query): string => $query->toRawSql(), $this->queries);
    }

    /**
     * Print the EXPLAIN plan for a SELECT.
     */
    protected function explain(string $label, string $sql): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            fwrite(STDERR, "    EXPLAIN {$label}: skipped (driver is not pgsql)\n");

            return;
        }

        if (! str_starts_with(strtolower(ltrim($sql)), 'select')) {
            $this->fail("EXPLAIN [{$label}] needs a SELECT, got: ".($sql === '' ? '(empty)' : mb_substr($sql, 0, 80)));
        }

        try {
            $rows = DB::select('EXPLAIN (ANALYZE, BUFFERS) '.$sql);
        } catch (Throwable $exception) {
            $this->fail("EXPLAIN [{$label}] failed: {$exception->getMessage()}");
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

    /**
     * Run one Stressless page load and print its metrics.
     */
    protected function stressPage(string $path, ?string $handle, string $accept, int $concurrency): Factory
    {
        $headers = ['Accept' => $accept];

        if ($handle !== null) {
            $cookie = $this->sessionCookie($handle);
            $probe = Http::withHeaders(['Cookie' => $cookie, 'Accept' => $accept])
                ->withOptions(['allow_redirects' => false])
                ->get($this->baseUrl().$path);

            if (! $probe->successful()) {
                $this->fail("Authenticated probe for [{$path}] returned [{$probe->status()}].");
            }

            $headers['Cookie'] = $cookie;
        }

        $result = stress($this->baseUrl().$path)
            ->headers($headers)
            ->concurrently(requests: $concurrency)
            ->for(5)->seconds();

        fwrite(STDERR, \sprintf(
            "%-38s c=%-2d p50=%7.1fms p95=%7.1fms ttfb95=%7.1fms failed=%d rate=%6.1f/s\n",
            $path,
            $concurrency,
            $result->requests()->duration()->med(),
            $result->requests()->duration()->p95(),
            $result->requests()->ttfb()->duration()->p95(),
            $result->requests()->failed()->count(),
            $result->requests()->rate(),
        ));

        return $result;
    }

    /**
     * Assert a page load did not exceed the allowed failure count.
     */
    protected function assertStressResult(Factory $result): void
    {
        expect($result->requests()->failed()->count())
            ->toBeLessThanOrEqual((int) (getenv('STRESS_ALLOW_FAILED') ?: 0));
    }

    /**
     * The staging container URL.
     */
    protected function baseUrl(): string
    {
        return rtrim(getenv('STRESS_BASE_URL') ?: 'http://localhost:8080', '/');
    }

    /**
     * Log in through the real login route and return a Cookie header.
     */
    protected function sessionCookie(string $handle, string $password = 'password'): string
    {
        if (isset(self::$cookies[$handle])) {
            return self::$cookies[$handle];
        }

        $response = $this->post('/login', [
            'handle' => $handle,
            'password' => $password,
        ]);

        $cookies = collect($response->headers->getCookies())
            ->map(fn (Cookie $cookie): string => $cookie->getName().'='.$cookie->getValue())
            ->implode('; ');

        if ($cookies === '') {
            $response = $this->get('/dashboard');

            $cookies = collect($response->headers->getCookies())
                ->map(fn (Cookie $cookie): string => $cookie->getName().'='.$cookie->getValue())
                ->implode('; ');
        }

        if ($cookies === '') {
            throw new \RuntimeException('The login request did not set a session cookie.');
        }

        return self::$cookies[$handle] = $cookies;
    }

    /**
     * Skip HTTP tests unless the staging environment is configured.
     */
    protected function requireStress(): void
    {
        if (getenv('STRESS') !== '1') {
            $this->markTestSkipped('Run the stress suite with `composer stress`.');
        }

        $expected = getenv('DB_DATABASE');

        if ($expected === false) {
            $this->markTestSkipped('Set DB_DATABASE to the database served by the staging container.');
        }

        if (config('session.driver') !== 'database') {
            $this->markTestSkipped('Run the stress suite with `composer stress` (SESSION_DRIVER=database).');
        }

        expect(DB::connection()->getDatabaseName())->toBe($expected);
    }

    /**
     * Skip query tests unless an explicit database is configured.
     */
    protected function requireQuery(): void
    {
        $expected = getenv('DB_DATABASE');

        if ($expected === false) {
            $this->markTestSkipped('Set DB_DATABASE to the database under measurement.');
        }

        expect(DB::connection()->getDatabaseName())->toBe($expected);
    }

    /**
     * Skip write tests unless the configured database is disposable.
     */
    protected function requireWriteQuery(): void
    {
        $this->requireQuery();

        $database = DB::connection()->getDatabaseName();

        if (! str_contains($database, 'write') && ! str_contains($database, 'bulk')) {
            $this->fail("Refusing to run a write benchmark against [{$database}]; use a disposable write or bulk database.");
        }
    }

    /**
     * Switch the authenticated user and current organization.
     */
    protected function switchTo(User $user, Organization $organization): void
    {
        $this->actingAs($user);
        session([CurrentOrganization::SESSION_KEY => $organization->getKey()]);
        app(CurrentOrganization::class)->forget();
    }

    protected function benchOwner(): User
    {
        return User::query()->where('handle', 'benchowner')->firstOrFail();
    }

    protected function rootOwner(): User
    {
        return User::query()->where('handle', 'rootowner')->firstOrFail();
    }

    protected function bench(): Organization
    {
        return Organization::query()->findOrFail(2);
    }

    protected function root(): Organization
    {
        return Organization::query()->findOrFail(1);
    }

    /**
     * Resolve a seeded benchmark contact relationship by stakeholder name.
     */
    protected function benchContact(string $name = 'Org 2-1'): OrganizationRelationship
    {
        return OrganizationRelationship::query()
            ->where('organization_id', 2)
            ->where('stakeholder_type', Organization::class)
            ->whereHas('stakeholder', fn ($query) => $query->where('name', $name))
            ->firstOrFail();
    }

    /**
     * Resolve a seeded benchmark member by person name.
     */
    protected function benchMember(string $name = 'Member 2-3'): Person
    {
        return Person::query()->where('name', $name)->firstOrFail();
    }

    protected function suffix(): string
    {
        return substr(bin2hex(random_bytes(4)), 0, 8);
    }

    private function normalizeSql(string $sql): string
    {
        $sql = preg_replace("/'[^']*'/", '?', $sql) ?? $sql;
        $sql = preg_replace('/\b\d+\b/', '?', $sql) ?? $sql;

        return preg_replace('/\s+/', ' ', $sql) ?? $sql;
    }
}
