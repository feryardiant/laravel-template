<?php

declare(strict_types=1);

use Tests\Stress\TestCase;

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->requireStress();
});

it('serves the audit log', function (int $concurrency): void {
    /** @var TestCase $this */
    $this->assertStressResult($this->stressPage('/org-settings/audit', 'benchowner', 'text/html', $concurrency));
})->with([1, 5, 20])->group('stress');

it('serves the approval history', function (int $concurrency): void {
    /** @var TestCase $this */
    $this->assertStressResult($this->stressPage('/org-settings/approval-history', 'benchowner', 'text/html', $concurrency));
})->with([1, 5, 20])->group('stress');

it('serves the subsidiary tree', function (int $concurrency): void {
    /** @var TestCase $this */
    $this->assertStressResult($this->stressPage('/org-settings/subsidiaries', 'rootowner', 'text/html', $concurrency));
})->with([1, 5, 20])->group('stress');
