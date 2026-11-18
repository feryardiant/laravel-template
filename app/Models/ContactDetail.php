<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactDetailType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Communication record attached to an entity.
 *
 * @property int $id
 * @property string $contactable_type
 * @property int $contactable_id
 * @property ContactDetailType $type
 * @property string $value
 * @property-read Model $contactable
 */
#[Fillable(['type', 'value'])]
class ContactDetail extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContactDetailType::class,
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function contactable(): MorphTo
    {
        return $this->morphTo('contactable');
    }
}
