<?php

declare(strict_types=1);

use App\Actions\Users\DeleteUser;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('deletes a user without an organization', function () {
    $user = User::factory()->create();

    app(DeleteUser::class)->handle($user);

    expect($user->fresh())->toBeNull();
});

it('refuses to delete the last owner', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->asMemberOf($organization, Role::Owner, isPrimary: true)->create();

    expect(fn () => app(DeleteUser::class)->handle($owner))
        ->toThrow(ValidationException::class);
});
