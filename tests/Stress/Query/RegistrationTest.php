<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Tests\Stress\TestCase;

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->requireQuery();
});

it('provisions a public tenant', function (): void {
    /** @var TestCase $this */
    $suffix = $this->suffix();
    $email = 'registered-'.$suffix.'@example.test';

    $this->measure('registration: user + public tenant', fn () => app(CreateNewUser::class)->create([
        'name' => 'Registered '.$suffix,
        'handle' => 'reg'.$suffix,
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]));

    $user = User::query()->where('email', $email)->firstOrFail();
    $person = Person::query()->where('user_id', $user->getKey())->firstOrFail();
    $tenant = Organization::query()
        ->where('contact_id', $person->getKey())
        ->where('is_tenant', true)
        ->firstOrFail();

    expect($tenant->root_organization_id)->not->toBeNull()
        ->and($tenant->members()->whereKey($person->getKey())->wherePivot('role', Role::Owner->value)->exists())->toBeTrue();
})->group('query-registration');
