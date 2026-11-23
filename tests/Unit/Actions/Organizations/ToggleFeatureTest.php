<?php

declare(strict_types=1);

use App\Actions\Organizations\Audit\FeatureToggledPayload;
use App\Actions\Organizations\ToggleFeature;
use App\Events\FeatureToggled;
use App\Models\Organization;
use App\Models\User;
use App\Support\Feature\ContactGender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('toggles a feature and records the audit event', function () {
    Event::fake([FeatureToggled::class]);

    $organization = Organization::factory()->create();
    $actor = User::factory()->asMemberOf($organization)->create();

    $changed = app(ToggleFeature::class)->handle(
        $organization,
        ContactGender::class,
        true,
        $actor,
    );

    expect($changed)->toBeTrue()
        ->and(ContactGender::enabled($organization))->toBeTrue();

    Event::assertDispatched(
        FeatureToggled::class,
        fn (FeatureToggled $event): bool => $event->organizationId === $organization->getKey()
            && $event->actorId === $actor->getKey()
            && $event->payload instanceof FeatureToggledPayload
            && $event->payload->feature === ContactGender::FEATURE
            && $event->payload->enabled === true,
    );
});

it('does nothing when the feature already has the requested state', function () {
    Event::fake([FeatureToggled::class]);

    $organization = Organization::factory()->create();

    $changed = app(ToggleFeature::class)->handle(
        $organization,
        ContactGender::class,
        false,
    );

    expect($changed)->toBeFalse();

    Event::assertNotDispatched(FeatureToggled::class);
});
