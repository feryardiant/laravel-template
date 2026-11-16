<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function auditEntryForPrune(array $attributes = []): AuditLog
{
    $organization = Organization::factory()->create();

    $log = AuditLog::create([
        'organization_id' => $organization->getKey(),
        'event' => AuditEvent::ContactCreated,
    ]);

    $log->forceFill([
        'created_at' => now()->subDays(10),
        ...$attributes,
    ])->saveQuietly();

    return $log;
}

test('it deletes entries older than the keep window', function () {
    $old = auditEntryForPrune(['created_at' => now()->subDays(10)]);
    $recent = auditEntryForPrune(['created_at' => now()->subDays(2)]);

    /** @var TestCase $this */
    $this->artisan('app:audit-prune', ['--keep' => 7])
        ->assertExitCode(0);

    $this->assertDatabaseMissing('audit_logs', ['id' => $old->getKey()]);
    $this->assertDatabaseHas('audit_logs', ['id' => $recent->getKey()]);
});

test('it defaults to keeping thirty days', function () {
    $old = auditEntryForPrune(['created_at' => now()->subDays(31)]);
    $recent = auditEntryForPrune(['created_at' => now()->subDays(29)]);

    /** @var TestCase $this */
    $this->artisan('app:audit-prune')
        ->assertExitCode(0);

    $this->assertDatabaseMissing('audit_logs', ['id' => $old->getKey()]);
    $this->assertDatabaseHas('audit_logs', ['id' => $recent->getKey()]);
});

test('a dry run keeps every entry', function () {
    $old = auditEntryForPrune(['created_at' => now()->subDays(10)]);

    /** @var TestCase $this */
    $this->artisan('app:audit-prune', ['--keep' => 7, '--dry-run' => true])
        ->assertExitCode(0);

    $this->assertDatabaseHas('audit_logs', ['id' => $old->getKey()]);
});

test('a keep window below one day is rejected', function () {
    $log = auditEntryForPrune(['created_at' => now()->subDays(10)]);

    /** @var TestCase $this */
    $this->artisan('app:audit-prune', ['--keep' => 0])
        ->assertExitCode(1);

    $this->assertDatabaseHas('audit_logs', ['id' => $log->getKey()]);
});

test('a non-numeric keep window is rejected', function () {
    $log = auditEntryForPrune(['created_at' => now()->subDays(10)]);

    /** @var TestCase $this */
    $this->artisan('app:audit-prune', ['--keep' => 'seven'])
        ->assertExitCode(1);

    $this->assertDatabaseHas('audit_logs', ['id' => $log->getKey()]);
});
