<?php

declare(strict_types=1);

use App\Actions\Users\UpdateUserProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates the user and person profile', function () {
    $user = User::factory()->withProfile()->create(['email' => 'old@example.test']);
    $user->person->update(['name' => 'Old Name', 'summary' => 'Old summary']);

    $result = app(UpdateUserProfile::class)->handle(
        user: $user,
        data: [
            'name' => 'New Name',
            'handle' => 'newhandle',
            'email' => 'new@example.test',
            'summary' => 'New summary',
            'gender' => null,
            'gender_editable' => false,
            'details' => [],
            'can_edit' => true,
            'remove_avatar' => false,
        ],
        expected: [
            'name' => 'Old Name',
            'handle' => $user->handle,
            'email' => 'old@example.test',
            'summary' => 'Old summary',
            'details' => [],
            'avatar_url' => null,
        ],
    );

    expect($result['user']->person?->name)->toBe('New Name')
        ->and($result['user']->person?->summary)->toBe('New summary')
        ->and($result['user']->handle)->toBe('newhandle')
        ->and($result['user']->email)->toBe('new@example.test')
        ->and($result['user']->email_verified_at)->toBeNull();
});

it('creates the person profile when it is missing', function () {
    $user = User::factory()->create();

    $result = app(UpdateUserProfile::class)->handle(
        user: $user,
        data: [
            'name' => 'Profile Name',
            'handle' => $user->handle,
            'email' => $user->email,
            'summary' => '',
            'gender' => null,
            'gender_editable' => false,
            'details' => [],
            'can_edit' => true,
            'remove_avatar' => false,
        ],
        expected: [
            'name' => $user->name,
            'handle' => $user->handle,
            'email' => $user->email,
            'summary' => '',
            'details' => [],
            'avatar_url' => null,
        ],
    );

    expect($result['user']->person)->not->toBeNull()
        ->and($result['user']->person?->name)->toBe('Profile Name');
});

it('keeps a name saved by another session', function () {
    $user = User::factory()->withProfile()->create();
    $user->person->update(['name' => 'Old Name']);
    $user->person->update(['name' => 'Newer Name']);

    app(UpdateUserProfile::class)->handle(
        user: $user,
        data: [
            'name' => 'Old Name',
            'handle' => $user->handle,
            'email' => $user->email,
            'summary' => '',
            'gender' => null,
            'gender_editable' => false,
            'details' => [],
            'can_edit' => true,
            'remove_avatar' => false,
        ],
        expected: [
            'name' => 'Old Name',
            'handle' => $user->handle,
            'email' => $user->email,
            'summary' => '',
            'details' => [],
            'avatar_url' => null,
        ],
    );

    expect($user->person->fresh()?->name)->toBe('Newer Name');
});
