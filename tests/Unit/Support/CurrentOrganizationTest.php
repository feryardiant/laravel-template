<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Actions\Memberships\SetDefaultOrganization;
use App\Models\Organization;
use App\Models\Person;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the organization stored in the session', function () {
    $person = Person::factory()->create();
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();

    app(CreateMember::class)->attach($first, $person, isPrimary: true);
    app(CreateMember::class)->attach($second, $person);

    $this->actingAs($person->user);
    session([CurrentOrganization::SESSION_KEY => $second->getKey()]);

    expect(app(CurrentOrganization::class)->get()?->is($second))->toBeTrue();
});

it('falls back to the person default instead of the first organization', function () {
    $decoy = Organization::factory()->create();
    $person = Person::factory()->create();
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();

    app(CreateMember::class)->attach($first, $person, isPrimary: true);
    app(CreateMember::class)->attach($second, $person);
    app(SetDefaultOrganization::class)->handle($person->user, $second);

    $this->actingAs($person->user);

    expect($decoy->getKey())->toBeLessThan($first->getKey())
        ->and(app(CurrentOrganization::class)->get()?->is($second))->toBeTrue();
});

it('falls back to the default when the session organization is no longer a membership', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()->create();
    $foreign = Organization::factory()->create();

    app(CreateMember::class)->attach($organization, $person, isPrimary: true);

    $this->actingAs($person->user);
    session([CurrentOrganization::SESSION_KEY => $foreign->getKey()]);

    expect(app(CurrentOrganization::class)->get()?->is($organization))->toBeTrue();
});

it('returns null for guests', function () {
    expect(app(CurrentOrganization::class)->get())->toBeNull()
        ->and(app(CurrentOrganization::class)->switch(1))->toBeFalse();
});

it('memoizes the current organization for the request', function () {
    $person = Person::factory()->create();
    $organization = Organization::factory()->create();

    app(CreateMember::class)->attach($organization, $person, isPrimary: true);

    $this->actingAs($person->user);

    $current = app(CurrentOrganization::class)->get();

    expect($current)->toBeInstanceOf(Organization::class)
        ->and(app(CurrentOrganization::class)->get())->toBe($current);
});
