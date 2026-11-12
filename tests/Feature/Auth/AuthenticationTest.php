<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = get(route('login'));

    $response->assertOk();
});

test('guests are redirected to the login page', function () {
    $response = get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('users can authenticate using the login screen', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = post(route('login.store'), [
        'handle' => $user->handle,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = post(route('login.store'), [
        'handle' => $user->handle,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('handle');

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    /** @var TestCase $this */
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = post(route('login.store'), [
        'handle' => $user->handle,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('login'));

    $this->assertGuest();
});
