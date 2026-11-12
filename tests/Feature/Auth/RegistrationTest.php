<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\User;
use App\Support\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration is disabled by default', function () {
    Organization::factory()->asRootTenant()->create();

    get(route('register'))->assertNotFound();

    post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('registration is disabled without a root tenant', function () {
    get(route('register'))->assertNotFound();
});

test('registration screen can be rendered when the root tenant enables it', function () {
    $root = Organization::factory()->asRootTenant()->create();

    Registration::activate($root);

    get(route('register'))->assertOk();
});

test('new users register as members with a tenant organization owned by their person', function () {
    $root = Organization::factory()->asRootTenant()->create();

    Registration::activate($root);

    $response = post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    /** @var TestCase $this */
    $this->assertAuthenticated();

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->role)->toBe(Role::Member);

    $response->assertSessionHas('status', fn (string $status) => str_contains($status, $user->handle));

    $person = $user->person;

    expect($person)->not->toBeNull()
        ->and($person->name)->toBe('John Doe')
        ->and($person->gender)->toBeNull()
        ->and($person->summary)->toBeNull();

    $organization = $person->organizations()->firstOrFail();

    expect($organization->name)->toBe('John Doe Org')
        ->and($organization->is_tenant)->toBeTrue()
        ->and($organization->root_organization_id)->toBe($root->getKey())
        ->and($user->roleIn($organization))->toBe(Role::Owner);

    expect(OrganizationRelationship::query()
        ->forOrganization($root)
        ->whereStakeholder($organization)
        ->where('type', OrganizationRelationshipType::Customer)
        ->exists())->toBeTrue();
});
