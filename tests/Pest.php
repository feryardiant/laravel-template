<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\ServerManager;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser', 'Unit');

pest()->browser()->timeout(10_000);

/**
 * Assert the browser test's last backend request did not throw.
 *
 * The browser plugin only mentions server exceptions when another assertion
 * already fails, so a 500 from a Livewire round-trip can otherwise go unseen.
 */
function assertNoBackendErrors(): void
{
    $throwable = ServerManager::instance()->http()->lastThrowable();

    expect($throwable)->toBeNull(
        'The last backend request threw: '.($throwable?->getMessage() ?? 'unknown'),
    );
}

/**
 * Sign in through the real login form, optionally on a given viewport.
 */
function browserLogin(User $user, string $device = 'desktop'): AwaitableWebpage
{
    return visit('/login')
        ->on()
        ->{$device}()
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('@login-button');
}
