<?php

declare(strict_types=1);

use App\Actions\ProvisionTenant;
use App\Enums\Role;
use App\Livewire\Setup;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->app->detectEnvironment(fn () => 'production');

    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
});

afterEach(function () {
    /** @var TestCase $this */
    $this->app->detectEnvironment(fn () => 'testing');
});

test('setup is required while the seeded user exists and no root tenant does', function () {
    $provisioning = app(ProvisionTenant::class);

    expect($provisioning->isRequired())->toBeFalse();

    User::factory()->create(['email' => config('setup.initial_email')]);

    expect($provisioning->isRequired())->toBeTrue();

    Organization::factory()->create(['is_tenant' => true, 'root_organization_id' => null]);

    expect($provisioning->isRequired())->toBeFalse();
});

test('setup is skipped in local and testing environments', function () {
    /** @var TestCase $this */
    $this->app->detectEnvironment(fn () => 'testing');

    User::factory()->create(['email' => config('setup.initial_email')]);

    expect(app(ProvisionTenant::class)->isRequired())->toBeFalse();
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

    expect($user->name)->toBe('Root Owner');
    expect($user->email)->toBe('root@example.com');
    expect($user->hasVerifiedEmail())->toBeTrue();
    expect($organization->name)->toBe("Root Owner's Org");
    expect($organization->contact_id)->toBe($person->getKey());
    expect($organization->is_tenant)->toBeTrue();
    expect($organization->root_organization_id)->toBeNull();
    expect($person->roleIn($organization))->toBe(Role::Owner);
    expect($person->primaryOrganization()?->is($organization))->toBeTrue();
    expect(Auth::id())->toBe($user->getKey());
    expect(session(CurrentOrganization::SESSION_KEY))->toBe($organization->getKey());
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
