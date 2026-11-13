<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Actions\Tenancy\ProvisionPublicTenant;
use App\Actions\Users\CreateUser;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        if (isset($input['email'])) {
            $input['email'] = Str::lower($input['email']);
        }

        Validator::make($input, [
            ...$this->profileRules(forRegistration: true),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = app(CreateUser::class)->handle($input['name'], $input['email'], $input['password']);

            app(ProvisionPublicTenant::class)->handle($user);

            session()->flash('status', __('Your username is :handle. Use it to log in.', [
                'handle' => $user->handle,
            ]));

            return $user;
        });
    }
}
