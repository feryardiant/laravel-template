<?php

declare(strict_types=1);

use App\Actions\Users\ResendEmailVerification;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('sends a verification email to an unverified user', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    expect(app(ResendEmailVerification::class)->handle($user))->toBeTrue();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('does nothing for a verified user', function () {
    Notification::fake();

    $user = User::factory()->create();

    expect(app(ResendEmailVerification::class)->handle($user))->toBeFalse();

    Notification::assertNothingSent();
});
