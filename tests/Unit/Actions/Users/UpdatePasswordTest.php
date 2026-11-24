<?php

declare(strict_types=1);

use App\Actions\Users\UpdatePassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('updates the user password', function () {
    $user = User::factory()->create();

    app(UpdatePassword::class)->handle($user, 'new-password');

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});
