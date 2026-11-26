<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Organization;
use Laravel\Pennant\Feature as PennantFeature;

/**
 * The feature flags available to a tenant.
 */
enum Feature: string
{
    case Registration = 'registration';
    case ContactGender = 'contact-gender';
    case ProfileSelfEdit = 'profile-self-edit';
    case MemberDeletion = 'member-deletion';
    case ContactDeletion = 'contact-deletion';

    /**
     * Get the human-readable label for the feature.
     */
    public function label(): string
    {
        return match ($this) {
            self::Registration => __('Public registration'),
            self::ContactGender => __('Gender field in contact'),
            self::ProfileSelfEdit => __('Profile self-edit'),
            self::MemberDeletion => __('Member removal'),
            self::ContactDeletion => __('Contact deletion'),
        };
    }

    /**
     * Determine whether the flag lives on the root tenant instead of the current workspace.
     */
    public function isRootOnly(): bool
    {
        return $this === self::Registration;
    }

    /**
     * Determine whether the flag is on when the tenant has not stored a value.
     */
    public function default(): bool
    {
        return match ($this) {
            self::MemberDeletion, self::ContactDeletion => true,
            default => false,
        };
    }

    /**
     * Determine whether the tenant has the feature enabled.
     */
    public function enabled(?Organization $organization = null): bool
    {
        $scope = $this->isRootOnly()
            ? Organization::query()->rootTenant()->first()
            : $organization;

        return $scope !== null && PennantFeature::for($scope)->active($this->value);
    }
}
