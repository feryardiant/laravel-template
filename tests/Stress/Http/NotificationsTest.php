<?php

declare(strict_types=1);

use Tests\Stress\TestCase;

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->requireStress();
});

it('serves the notification inbox', function (int $concurrency): void {
    /** @var TestCase $this */
    $this->assertStressResult($this->stressPage('/notifications', 'benchowner', 'text/html', $concurrency));
})->with([1, 5, 20])->group('stress');

it('accepts the session cookie on the poller', function (): void {
    /** @var TestCase $this */
    $result = $this->stressPage('/notifications/latest', 'benchowner', 'application/json', 1);

    $this->assertStressResult($result);
    expect($result->requests()->count())->toBeGreaterThan(0);
})->group('stress');

it('rejects the poller without a session', function (): void {
    /** @var TestCase $this */
    $result = $this->stressPage('/notifications/latest', null, 'application/json', 1);

    expect($result->requests()->failed()->count())->toBeGreaterThan(0);
})->group('stress');
