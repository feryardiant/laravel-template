<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\User;
use App\Support\Feature\Registration;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Tests\TestCase;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->skipUnlessFortifyHas(Features::registration());

    RateLimiter::clear('registration:127.0.0.1');
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

    $response->assertSessionHas('status', fn (string $status) => str_contains($status, $user->handle));

    $person = $user->person;

    expect($person)->not->toBeNull()
        ->and($person->name)->toBe('John Doe')
        ->and($person->gender)->toBeNull()
        ->and($person->summary)->toBeNull();

    $organization = $person->organizations()->firstOrFail();

    expect($organization->name)->toBe(__('Organization for :name', ['name' => 'John Doe']))
        ->and($organization->is_tenant)->toBeTrue()
        ->and($organization->root_organization_id)->toBe($root->getKey())
        ->and($user->roleIn($organization))->toBe(Role::Owner);

    expect(OrganizationRelationship::query()
        ->forOrganization($root)
        ->whereStakeholder($organization)
        ->where('type', OrganizationRelationshipType::Customer)
        ->exists())->toBeTrue();
});

test('registration rejects an email that differs only by case', function () {
    $root = Organization::factory()->asRootTenant()->create();

    Registration::activate($root);

    User::factory()->create(['email' => 'test@example.com']);

    post(route('register.store'), [
        'name' => 'Case User',
        'email' => 'TEST@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'test@example.com')->count())->toBe(1);
});

test('a long name is truncated in the generated organization name', function () {
    $root = Organization::factory()->asRootTenant()->create();

    Registration::activate($root);

    post(route('register.store'), [
        'name' => str_repeat('a', 255),
        'email' => 'long@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $organization = User::query()->where('email', 'long@example.com')->firstOrFail()
        ->person->organizations()->firstOrFail();

    expect(mb_strlen($organization->name))->toBeLessThanOrEqual(255);
});

test('the verification screen shows the generated handle', function () {
    $root = Organization::factory()->asRootTenant()->create();

    Registration::activate($root);

    post(route('register.store'), [
        'name' => 'Verify Handle',
        'email' => 'verify@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'verify@example.com')->firstOrFail();

    get(route('verification.notice'))
        ->assertOk()
        ->assertSee($user->handle);
});

test('the verification mail includes the generated handle', function () {
    Notification::fake();

    $root = Organization::factory()->asRootTenant()->create();

    Registration::activate($root);

    post(route('register.store'), [
        'name' => 'Mail Handle',
        'email' => 'mail@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'mail@example.com')->firstOrFail();

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
        return str_contains((string) $notification->toMail($user)->render(), $user->handle);
    });
});

test('registration is throttled per ip', function () {
    $root = Organization::factory()->asRootTenant()->create();

    Registration::activate($root);

    for ($i = 1; $i <= 3; $i++) {
        post(route('register.store'), [
            'name' => "Throttle {$i}",
            'email' => "throttle{$i}@example.com",
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        auth()->logout();
    }

    post(route('register.store'), [
        'name' => 'Throttle 4',
        'email' => 'throttle4@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(429);
});
