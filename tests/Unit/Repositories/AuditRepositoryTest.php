<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Repositories\AuditRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters audit logs by actor, event, and date', function () {
    $organization = Organization::factory()->create();
    $actor = User::factory()->asMemberOf($organization)->create();
    $other = User::factory()->asMemberOf($organization)->create();

    $today = AuditLog::create([
        'organization_id' => $organization->getKey(),
        'actor_id' => $actor->getKey(),
        'event' => AuditEvent::OrganizationUpdated,
        'created_at' => now(),
    ]);
    AuditLog::create([
        'organization_id' => $organization->getKey(),
        'actor_id' => $other->getKey(),
        'event' => AuditEvent::FeatureToggled,
        'created_at' => now()->subDays(3),
    ]);

    $logs = app(AuditRepository::class)->get($organization, [
        'actor' => $actor->getKey(),
        'start' => today()->toDateString(),
        'end' => today()->toDateString(),
    ], 'UTC');

    expect($logs->total())->toBe(1)
        ->and($logs->first()?->is($today))->toBeTrue();
});

it('puts the current actor first', function () {
    $organization = Organization::factory()->create();
    $current = User::factory()->asMemberOf($organization)->create(['name' => 'Zed Current']);
    User::factory()->asMemberOf($organization)->create(['name' => 'Alpha Other']);

    $actors = app(AuditRepository::class)->getActors($organization, $current);

    expect($actors->first()?->is($current->person))->toBeTrue();
});
