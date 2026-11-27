<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

use function Pest\Stressless\stress;

uses(TestCase::class);

beforeEach(function (): void {
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
});

/**
 * The URL of the staging container under test.
 */
function stressBaseUrl(): string
{
    return rtrim(getenv('STRESS_BASE_URL') ?: 'http://localhost:8080', '/');
}

/**
 * Log in through the real login route and return the session as a Cookie header.
 *
 * `actingAs()` only authenticates the current test request; it does not write
 * the auth key to the session payload, so a separate HTTP client cannot use it.
 */
function stressSessionCookie(string $handle, string $password = 'password'): string
{
    static $cache = [];

    if (isset($cache[$handle])) {
        return $cache[$handle];
    }

    $response = test()->post('/login', [
        'handle' => $handle,
        'password' => $password,
    ]);

    $cookies = collect($response->headers->getCookies())
        ->map(fn (Cookie $cookie): string => $cookie->getName().'='.$cookie->getValue())
        ->implode('; ');

    if ($cookies === '') {
        $response = test()->get('/dashboard');

        $cookies = collect($response->headers->getCookies())
            ->map(fn (Cookie $cookie): string => $cookie->getName().'='.$cookie->getValue())
            ->implode('; ');
    }

    if ($cookies === '') {
        throw new RuntimeException('The login request did not set a session cookie.');
    }

    return $cache[$handle] = $cookies;
}

test('authenticated notification poller accepts the session cookie', function (): void {
    $result = stress(stressBaseUrl().'/notifications/latest')
        ->headers([
            'Cookie' => stressSessionCookie('benchowner'),
            'Accept' => 'application/json',
        ])
        ->concurrently(requests: 1)
        ->for(3)->seconds();

    expect($result->requests()->failed()->count())->toBe(0)
        ->and($result->requests()->count())->toBeGreaterThan(0);
})->group('stress');

test('unauthenticated notification poller is rejected', function (): void {
    $result = stress(stressBaseUrl().'/notifications/latest')
        ->headers(['Accept' => 'application/json'])
        ->concurrently(requests: 1)
        ->for(2)->seconds();

    expect($result->requests()->failed()->count())->toBeGreaterThan(0);
})->group('stress');

/**
 * The read-only matrix: each row is one endpoint at one concurrency.
 *
 * @return array<string, array{0: string, 1: ?string, 2: string, 3: int}>
 */
function stressMatrix(): array
{
    $targets = [
        '/up' => [null, 'text/html', [1, 5, 20]],
        '/login' => [null, 'text/html', [1, 5, 20]],
        '/dashboard' => ['benchowner', 'text/html', [1, 5, 20]],
        '/contacts' => ['benchowner', 'text/html', [1, 5, 20]],
        '/members' => ['benchowner', 'text/html', [1, 5, 20]],
        '/notifications' => ['benchowner', 'text/html', [1, 5, 20]],
        // The lookup route is throttled at 60 requests/minute; it is measured
        // separately at c=1 and is not part of the load matrix.
        '/org-settings/audit' => ['benchowner', 'text/html', [1, 5, 20]],
        '/org-settings/approval-history' => ['benchowner', 'text/html', [1, 5, 20]],
        '/org-settings/subsidiaries' => ['rootowner', 'text/html', [1, 5, 20]],
    ];

    $rows = [];
    $skip = array_filter(array_map('trim', explode(',', getenv('STRESS_SKIP') ?: '')));

    foreach ($targets as $path => [$user, $accept, $concurrencies]) {
        foreach ($skip as $needle) {
            if ($needle !== '' && str_contains($path, $needle)) {
                continue 2;
            }
        }

        foreach ($concurrencies as $concurrency) {
            $rows[$path.' @ '.$concurrency] = [$path, $user, $accept, $concurrency];
        }
    }

    return $rows;
}

dataset('read-only matrix', stressMatrix());

test('serves a read-only endpoint under load', function (string $path, ?string $user, string $accept, int $concurrency): void {
    $headers = ['Accept' => $accept];

    if ($user !== null) {
        $headers['Cookie'] = stressSessionCookie($user);
    }

    $result = stress(stressBaseUrl().$path)
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

    $failed = $result->requests()->failed()->count();
    $allowed = (int) (getenv('STRESS_ALLOW_FAILED') ?: 0);

    expect($failed)->toBeLessThanOrEqual($allowed);
})->with('read-only matrix')->group('stress');
