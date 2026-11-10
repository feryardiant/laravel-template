<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Database\Factories\OrganizationFactory;
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
    ->in('Feature', 'Browser');

pest()->extend(TestCase::class)
    ->in('Unit');

pest()->browser()->timeout(10_000);

/**
 * Count hidden form inputs matching one name and value in rendered HTML.
 *
 * The name and value assertions share this helper so a match cannot come from
 * two different elements.
 */
function hiddenFormInputCount(string $html, string $name, string $value): int
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $nodes = (new DOMXPath($document))->query(
        \sprintf('//input[@type="hidden"][@name="%s"][@value="%s"]', $name, $value),
    );

    return $nodes === false ? 0 : $nodes->length;
}

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

/**
 * Sign in a user who is a member of the given organization.
 */
function actingAsMember(
    OrganizationFactory|Organization $organization,
    Role $role = Role::Member,
    ?true $isPrimary = null
): User {
    $user = User::factory()
        ->asMemberOf($organization, $role, $isPrimary)
        ->create();

    test()->actingAs($user);

    session([CurrentOrganization::SESSION_KEY => $organization->getKey()]);

    return $user;
}

/**
 * Create a browser-test user who belongs to the test's shared organization.
 *
 * @param  array<string, mixed>  $attributes
 */
function browserMember(Role $role = Role::Member, array $attributes = [], bool $verified = true): User
{
    $organization = Organization::query()->first() ?? Organization::factory()->create();

    $factory = User::factory()->asMemberOf($organization, $role, $role->isOwner() ?: null);

    if (! $verified) {
        $factory = $factory->unverified();
    }

    return $factory->create($attributes);
}
