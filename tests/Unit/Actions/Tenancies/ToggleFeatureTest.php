<?php

declare(strict_types=1);

use App\Actions\Organizations\Audit\FeatureToggledPayload;
use App\Actions\Tenancies\ToggleFeature;
use App\Enums\Feature;
use App\Events\FeatureToggled;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

uses(RefreshDatabase::class);

it('toggles a feature and records the audit event', function () {
    Event::fake([FeatureToggled::class]);

    $organization = Organization::factory()->create();
    $actor = User::factory()->asMemberOf($organization)->create();

    $this->actingAs($actor);

    $changed = app(ToggleFeature::class)->handle(
        $organization,
        Feature::ContactGender,
        true,
    );

    expect($changed)->toBeTrue()
        ->and(Feature::ContactGender->enabled($organization))->toBeTrue();

    Event::assertDispatched(
        FeatureToggled::class,
        fn (FeatureToggled $event): bool => $event->organizationId === $organization->getKey()
            && $event->actorId === $actor->getKey()
            && $event->payload instanceof FeatureToggledPayload
            && $event->payload->feature === Feature::ContactGender->value
            && $event->payload->enabled === true,
    );
});

it('rejects a root-only feature on a child tenant', function () {
    $root = Organization::factory()->asRootTenant()->create();
    $tenant = Organization::factory()->asTenantOf($root)->create();

    expect(fn () => app(ToggleFeature::class)->handle($tenant, Feature::Registration, true))
        ->toThrow(InvalidArgumentException::class);

    expect(Feature::Registration->enabled())->toBeFalse();
});

it('does nothing when the feature already has the requested state', function () {
    Event::fake([FeatureToggled::class]);

    $organization = Organization::factory()->create();

    $changed = app(ToggleFeature::class)->handle(
        $organization,
        Feature::ContactGender,
        false,
    );

    expect($changed)->toBeFalse();

    Event::assertNotDispatched(FeatureToggled::class);
});
