<?php

declare(strict_types=1);

use App\Actions\Users\GetRecoveryCodes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reads the encrypted recovery codes', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => encrypt(json_encode(['code-one', 'code-two'])),
    ]);

    expect(app(GetRecoveryCodes::class)->handle($user))
        ->toBe(['code-one', 'code-two']);
});

it('returns null when two-factor is not enabled', function () {
    $user = User::factory()->create();

    expect(app(GetRecoveryCodes::class)->handle($user))->toBeNull();
});
