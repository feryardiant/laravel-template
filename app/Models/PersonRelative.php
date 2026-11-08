<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PersonRelativeType;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot linking a person to one of their relatives.
 *
 * @property null|PersonRelativeType $type
 */
class PersonRelative extends Pivot
{
    protected $table = 'people_relatives';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'person_id' => 'int',
            'relative_id' => 'int',
            'type' => PersonRelativeType::class,
        ];
    }
}
