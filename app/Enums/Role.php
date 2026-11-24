<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The role a person holds inside an organization.
 */
enum Role: int
{
    case Owner = 0;
    case Admin = 1;
    case Member = 2;

    /**
     * Determine whether the role is the organization owner.
     */
    public function isOwner(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Determine whether the role is an organization admin.
     */
    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Determine whether the role is a regular member.
     */
    public function isMember(): bool
    {
        return $this === self::Member;
    }

    /**
     * Determine whether the role can manage an organization.
     */
    public function isManager(): bool
    {
        return $this->isOwner() || $this->isAdmin();
    }

    /**
     * Get the badge color for the role.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Owner => 'amber',
            self::Admin => 'indigo',
            self::Member => 'sky',
        };
    }

    /**
     * Get the human-readable label for the kind.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Admin => __('Admin'),
            self::Member => __('Member'),
        };
    }
}
