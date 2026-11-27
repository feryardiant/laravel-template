<?php

declare(strict_types=1);

use Tests\Stress\TestCase;

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->requireStress();
});

it('serves the members index', function (int $concurrency): void {
    /** @var TestCase $this */
    $this->assertStressResult($this->stressPage('/members', 'benchowner', 'text/html', $concurrency));
})->with([1, 5, 20])->group('stress');
