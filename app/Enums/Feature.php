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
     * Get the human-readable description for the feature.
     */
    public function description(): string
    {
        return match ($this) {
            self::Registration => __('Allow visitors to create an account and a tenant organization.'),
            self::ContactGender => __('Show this field in contacts form.'),
            self::ProfileSelfEdit => __('Let org-owned people edit their own name, email, and photo.'),
            self::MemberDeletion => __('Allow managers to remove members from this workspace.'),
            self::ContactDeletion => __('Allow managers to delete contacts from this workspace.'),
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
        $organization ??= $this->isRootOnly()
            ? once(fn (): ?Organization => Organization::query()->rootTenant()->first())
            : null;

        return $organization && PennantFeature::for($organization)->active($this->value);
    }
}
