<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Users\CreateUser;
use App\Enums\Locale;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property string $email
 * @property Locale|null $locale
 * @property string|null $timezone
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ?Person $person
 */
#[Fillable(['name', 'handle', 'email', 'password', 'locale', 'timezone', 'avatar_url'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[RouteKey('handle')]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (blank($user->handle)) {
                $user->handle = CreateUser::generateHandle($user->email);
            }
        });

        static::deleting(function (User $user): void {
            $user->notifications()->delete();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locale' => Locale::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's person profile.
     *
     * @return HasOne<Person, $this>
     */
    public function person(): HasOne
    {
        return $this->hasOne(Person::class);
    }

    /**
     * Get the organizations the user is currently a member of.
     *
     * @return Collection<int, Organization>
     */
    public function organizations(): Collection
    {
        return $this->person?->organizations()->get() ?? new Collection;
    }

    /**
     * Get the user's primary organization.
     */
    public function primaryOrganization(): ?Organization
    {
        return $this->person?->primaryOrganization();
    }

    /**
     * Get the user's role in the given organization.
     */
    public function roleIn(Organization $organization): ?Role
    {
        return $this->person?->roleIn($organization);
    }

    /**
     * Get the user's preferred locale for notifications and mail.
     */
    public function preferredLocale(): string
    {
        $locale = $this->locale;

        return $locale === null
            ? (string) config('app.default_locale')
            : $locale->value;
    }

    /**
     * Get the user's avatar URL.
     *
     * @return Attribute<?string, ?string>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value ? route('uploads', ['path' => $value]) : null,
            set: fn (?string $value): array => ['avatar_url' => $value],
        );
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
