<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Actions\Memberships\SetDefaultOrganization;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('moves the default to the chosen organization', function () {
    $person = Person::factory()->create();
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();

    app(CreateMember::class)->attach($first, $person);
    app(CreateMember::class)->attach($second, $person);

    expect($person->defaultOrganization()?->is($first))->toBeTrue();

    expect(app(SetDefaultOrganization::class)->handle($person->user, $second))->toBeTrue();

    expect($person->fresh()->defaultOrganization()?->is($second))->toBeTrue()
        ->and($first->members()->whereKey($person->getKey())->firstOrFail()->pivot->is_primary)->toBeNull()
        ->and($second->members()->whereKey($person->getKey())->firstOrFail()->pivot->is_primary)->toBeTrue();
});

it('keeps the role and membership dates untouched', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()->create();
    $start = Carbon::parse('2026-01-01');
    $finish = Carbon::parse('2026-12-31');

    app(CreateMember::class)->attach(
        $organization,
        $person,
        startDate: $start,
        finishDate: $finish,
        role: Role::Admin,
    );

    app(SetDefaultOrganization::class)->handle($person->user, $organization);

    $pivot = $organization->members()->whereKey($person->getKey())->firstOrFail()->pivot;

    expect($pivot->role)->toBe(Role::Admin)
        ->and($pivot->start_date->toDateString())->toBe($start->toDateString())
        ->and($pivot->finish_date->toDateString())->toBe($finish->toDateString())
        ->and($pivot->is_primary)->toBeTrue();
});

it('rejects an organization the person does not belong to', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()->create();
    $foreign = Organization::factory()->create();

    app(CreateMember::class)->attach($organization, $person);

    expect(app(SetDefaultOrganization::class)->handle($person->user, $foreign))->toBeFalse()
        ->and($person->fresh()->defaultOrganization()?->is($organization))->toBeTrue();
});

it('is idempotent when the organization is already the default', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()->create();

    app(CreateMember::class)->attach($organization, $person);

    expect(app(SetDefaultOrganization::class)->handle($person->user, $organization))->toBeTrue()
        ->and(app(SetDefaultOrganization::class)->handle($person->user, $organization))->toBeTrue();

    expect($organization->members()->wherePivot('is_primary', true)->count())->toBe(1);
});

it('returns false when the user has no person profile', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    expect(app(SetDefaultOrganization::class)->handle($user, $organization))->toBeFalse();
});
