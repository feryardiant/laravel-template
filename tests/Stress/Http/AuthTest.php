<?php

declare(strict_types=1);

use Tests\Stress\TestCase;

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->requireStress();
});

it('serves the health endpoint', function (int $concurrency): void {
    /** @var TestCase $this */
    $this->assertStressResult($this->stressPage('/up', null, 'text/html', $concurrency));
})->with([1, 5, 20])->group('stress');

it('serves the login page', function (int $concurrency): void {
    /** @var TestCase $this */
    $this->assertStressResult($this->stressPage('/login', null, 'text/html', $concurrency));
})->with([1, 5, 20])->group('stress');
