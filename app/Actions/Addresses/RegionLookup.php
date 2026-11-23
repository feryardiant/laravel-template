<?php

declare(strict_types=1);

namespace App\Actions\Addresses;

use Creasi\Nusa\Models\District;
use Creasi\Nusa\Models\Province;
use Creasi\Nusa\Models\Regency;
use Creasi\Nusa\Models\Village;
use Illuminate\Database\Eloquent\Collection;

final class RegionLookup
{
    /**
     * @return Collection<int, Province>
     */
    public function provinces(): Collection
    {
        return Province::query()->orderBy('name')->get(['code', 'name']);
    }

    /**
     * @return Collection<int, Regency>
     */
    public function regencies(?string $provinceCode): Collection
    {
        if (blank($provinceCode)) {
            return new Collection;
        }

        return Regency::query()->where('province_code', $provinceCode)->orderBy('name')->get(['code', 'name']);
    }

    /**
     * @return Collection<int, District>
     */
    public function districts(?string $regencyCode): Collection
    {
        if (blank($regencyCode)) {
            return new Collection;
        }

        return District::query()->where('regency_code', $regencyCode)->orderBy('name')->get(['code', 'name']);
    }

    /**
     * @return Collection<int, Village>
     */
    public function villages(?string $districtCode): Collection
    {
        if (blank($districtCode)) {
            return new Collection;
        }

        return Village::query()->where('district_code', $districtCode)->orderBy('name')->get(['code', 'name']);
    }

    /**
     * Resolve the first village matching a five-digit postal code.
     */
    public function villageByPostalCode(string $postalCode): ?Village
    {
        if (preg_match('/^\d{5}$/', $postalCode) !== 1) {
            return null;
        }

        // shortcut: a postal code can cover several villages; take the first.
        return Village::query()
            ->where('postal_code', $postalCode)
            ->orderBy('code')
            ->first(['code', 'district_code', 'regency_code', 'province_code']);
    }

    /**
     * Resolve a village's postal code.
     */
    public function postalCodeForVillage(string $villageCode): ?string
    {
        if ($villageCode === '') {
            return null;
        }

        $postalCode = Village::query()->where('code', $villageCode)->value('postal_code');

        return $postalCode === null ? null : (string) $postalCode;
    }
}
