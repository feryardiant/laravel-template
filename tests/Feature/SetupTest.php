<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Setup;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\Setup as SetupState;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->app->detectEnvironment(fn () => 'production');

    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
});

afterEach(function () {
    $this->app->detectEnvironment(fn () => 'testing');
});

test('setup is required while the seeded user exists and no root tenant does', function () {
    expect(SetupState::required())->toBeFalse();

    User::factory()->create(['email' => config('setup.initial_email')]);

    expect(SetupState::required())->toBeTrue();

    Organization::factory()->create(['is_tenant' => true, 'root_organization_id' => null]);

    expect(SetupState::required())->toBeFalse();
});

test('setup is skipped in local and testing environments', function () {
    $this->app->detectEnvironment(fn () => 'testing');

    User::factory()->create(['email' => config('setup.initial_email')]);

    expect(SetupState::required())->toBeFalse();
});

test('the app redirects to setup while pending', function () {
    User::factory()->create(['email' => config('setup.initial_email')]);

    get('/')->assertRedirect(route('setup'));
    get(route('dashboard'))->assertRedirect(route('setup'));
});

test('the setup page renders while pending', function () {
    User::factory()->create(['email' => config('setup.initial_email')]);

    get(route('setup'))
        ->assertOk()
        ->assertSee(__('Set up your account'));
});

test('setup provisions the root tenant and owner', function () {
    $seeded = User::factory()->create([
        'email' => config('setup.initial_email'),
        'role' => Role::Owner,
    ]);

    Livewire::test(Setup::class)
        ->set('name', 'Root Owner')
        ->set('email', 'root@example.com')
        ->set('password', 'Str0ng!Password#2026')
        ->set('password_confirmation', 'Str0ng!Password#2026')
        ->call('setup')
        ->assertHasNoErrors();

    $user = $seeded->fresh();
    $person = Person::query()->where('user_id', $user->getKey())->firstOrFail();
    $organization = Organization::query()->rootTenant()->firstOrFail();

    expect($user->name)->toBe('Root Owner')
        ->and($user->email)->toBe('root@example.com')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($organization->name)->toBe("Root Owner's Org")
        ->and($organization->contact_id)->toBe($person->getKey())
        ->and($organization->is_tenant)->toBeTrue()
        ->and($organization->root_organization_id)->toBeNull()
        ->and($person->roleIn($organization))->toBe(Role::Owner)
        ->and($person->primaryOrganization()?->is($organization))->toBeTrue()
        ->and(Auth::id())->toBe($user->getKey())
        ->and(session(CurrentOrganization::SESSION_KEY))->toBe($organization->getKey());
});

test('setup rejects the seeded email address', function () {
    User::factory()->create(['email' => config('setup.initial_email')]);

    Livewire::test(Setup::class)
        ->set('name', 'Root Owner')
        ->set('email', config('setup.initial_email'))
        ->set('password', 'Str0ng!Password#2026')
        ->set('password_confirmation', 'Str0ng!Password#2026')
        ->call('setup')
        ->assertHasErrors('email');
});

test('the setup page redirects to the dashboard when setup is complete', function () {
    Organization::factory()->create(['is_tenant' => true, 'root_organization_id' => null]);

    get(route('setup'))->assertRedirect(route('dashboard'));
});
