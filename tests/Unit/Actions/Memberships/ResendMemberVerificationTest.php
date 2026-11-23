<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Actions\Memberships\ResendMemberVerification;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

it('sends a verification email to an unverified member', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $user = User::factory()->unverified()->asMemberOf($organization)->create();

    app(ResendMemberVerification::class)->handle($organization, $user->person->getKey());

    Notification::assertSentTo($user, VerifyEmailForNewUser::class);
});

it('rejects a member whose email is already verified', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->asMemberOf($organization)->create();

    expect(fn () => app(ResendMemberVerification::class)->handle($organization, $user->person->getKey()))
        ->toThrow(HttpException::class);
});

it('rejects a member without a credential', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->withoutCredential()->create();
    app(CreateMember::class)->attach($organization, $person, startDate: now(), role: Role::Member);

    expect(fn () => app(ResendMemberVerification::class)->handle($organization, $person->getKey()))
        ->toThrow(HttpException::class);
});
