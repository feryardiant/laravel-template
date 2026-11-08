<?php

declare(strict_types=1);

namespace App\Models;

use Creasi\Nusa\Contracts\Address as AddressContract;
use Creasi\Nusa\Models\Concerns\WithDistrict;
use Creasi\Nusa\Models\Concerns\WithProvince;
use Creasi\Nusa\Models\Concerns\WithRegency;
use Creasi\Nusa\Models\Concerns\WithVillage;
use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Postal address attached to a person or an organization.
 *
 * @property int $id
 * @property string $line
 * @property string $village_code
 * @property string $district_code
 * @property string $regency_code
 * @property string $province_code
 * @property int $postal_code
 *
 * @implements AddressContract<$this>
 */
#[Fillable(['id', 'line', 'rt', 'rw', 'village_code', 'district_code', 'regency_code', 'province_code', 'postal_code'])]
class Address extends Model implements AddressContract
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    /** @use WithDistrict<static> */
    use WithDistrict;

    /** @use WithProvince<static> */
    use WithProvince;

    /** @use WithRegency<static> */
    use WithRegency;

    /** @use WithVillage<static> */
    use WithVillage;

    /**
     * {@inheritdoc}
     */
    protected static function booted(): void
    {
        static::saving(function (Address $address) {
            $address->rt = $address->rt ? str_pad((string) $address->rt, 3, '0', \STR_PAD_LEFT) : null;
            $address->rw = $address->rw ? str_pad((string) $address->rw, 3, '0', \STR_PAD_LEFT) : null;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'postal_code' => 'int',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function addressable(): MorphTo
    {
        return $this->morphTo('addressable');
    }
}
