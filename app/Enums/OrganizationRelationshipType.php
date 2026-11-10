<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kinds of relationships an organization has with its stakeholders.
 */
enum OrganizationRelationshipType: string
{
    /**
     * A organization that act as subsidiary or child organization.
     */
    case Subsidiary = 'subsidiary';

    /**
     * A individual that act as member of the organization.
     */
    case Member = 'member';

    /**
     * A organization or individual that purchase goods from the organization.
     */
    case Customer = 'customer';

    /**
     * A organization or individual who provides raw materials for the organization so they can
     * produce their goods.
     */
    case Supplier = 'supplier';

    /**
     * A organization or individual who provides pre-made or even ready-made goods for the organization
     * so they can proceed the raw materials into goods.
     */
    case Vendor = 'vendor';

    /**
     * Get the internal relationship types.
     *
     * @return list<self>
     */
    public static function internals(): array
    {
        return [self::Subsidiary, self::Member];
    }

    /**
     * Get the external relationship types.
     *
     * @return list<self>
     */
    public static function externals(): array
    {
        return [self::Customer, self::Supplier, self::Vendor];
    }

    /**
     * Determine whether the relationship is internal.
     */
    public function isInternal(): bool
    {
        return \in_array($this, self::internals(), true);
    }

    /**
     * Determine whether the relationship is external.
     */
    public function isExternal(): bool
    {
        return \in_array($this, self::externals(), true);
    }

    /**
     * Get the human-readable label for the relationship type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Subsidiary => __('Subsidiary'),
            self::Member => __('Member'),
            self::Customer => __('Customer'),
            self::Supplier => __('Supplier'),
            self::Vendor => __('Vendor'),
        };
    }
}
