<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kinds of kinship a person can have with another person.
 */
enum PersonRelativeType: string
{
    /**
     * Whether the personnel is registered as a children of the employee.
     */
    case Child = 'child';

    /**
     * Whether the personnel is registered as a spouse of the employee.
     */
    case Spouse = 'spouse';

    /**
     * Whether the personnel is registered as a sibling of the employee.
     */
    case Sibling = 'sibling';

    /**
     * Whether the personnel is registered as a siblingchild of the employee.
     */
    case SiblingsChild = 'siblings-child';

    /**
     * Whether the personnel is registered as a cousin of the employee.
     */
    case Cousin = 'cousin';

    /**
     * Whether the personnel is registered as a parent of the employee.
     */
    case Parent = 'parent';

    /**
     * Whether the personnel is registered as a parent's sibling of the employee.
     */
    case ParentsSibling = 'parents-sibling';

    /**
     * Whether the personnel is registered as a grandparent of the employee.
     */
    case Grandparent = 'grand-parent';

    /**
     * Whether the personnel is registered as a grandchildren of the employee.
     */
    case Grandchild = 'grand-child';
}
