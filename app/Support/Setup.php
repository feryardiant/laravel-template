<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Organization;
use App\Models\User;

final class Setup
{
    /**
     * Determine whether the first-install setup flow is still required.
     */
    public static function required(): bool
    {
        if (app()->environment(['local', 'testing'])) {
            return false;
        }

        return Organization::query()->rootTenant()->doesntExist()
            && User::query()->where('email', config('setup.initial_email'))->exists();
    }
}
