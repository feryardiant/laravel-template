<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Addresses\RegionLookup;
use Creasi\Nusa\Models\District;
use Creasi\Nusa\Models\Province;
use Creasi\Nusa\Models\Regency;
use Creasi\Nusa\Models\Village;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * Reusable address form for contacts, members, and organizations.
 *
 * The parent owns validation and persistence; this component only renders the
 * fields and resolves the region cascade.
 *
 * @property-read Collection<int, Province> $provinces
 * @property-read Collection<int, Regency> $regencies
 * @property-read Collection<int, District> $districts
 * @property-read Collection<int, Village> $villages
 */
class Address extends Component
{
    /**
     * @var array{line: string, rt: string, rw: string, postal_code: string, province_code: string, regency_code: string, district_code: string, village_code: string}
     */
    #[Modelable]
    public array $address = [
        'line' => '',
        'rt' => '',
        'rw' => '',
        'postal_code' => '',
        'province_code' => '',
        'regency_code' => '',
        'district_code' => '',
        'village_code' => '',
    ];

    public function render(): View
    {
        return view('livewire.address');
    }

    /**
     * @return Collection<int, Province>
     */
    #[Computed]
    public function provinces(): Collection
    {
        return app(RegionLookup::class)->provinces();
    }

    /**
     * @return Collection<int, Regency>
     */
    #[Computed]
    public function regencies(): Collection
    {
        return app(RegionLookup::class)->regencies($this->address['province_code']);
    }

    /**
     * @return Collection<int, District>
     */
    #[Computed]
    public function districts(): Collection
    {
        return app(RegionLookup::class)->districts($this->address['regency_code']);
    }

    /**
     * @return Collection<int, Village>
     */
    #[Computed]
    public function villages(): Collection
    {
        return app(RegionLookup::class)->villages($this->address['district_code']);
    }

    /**
     * Clear the levels below a changed field and keep the postal code in sync.
     */
    public function updatedAddress(string $value, ?string $key): void
    {
        $children = [
            'postal_code' => ['province_code', 'regency_code', 'district_code', 'village_code'],
            'province_code' => ['regency_code', 'district_code', 'village_code', 'postal_code'],
            'regency_code' => ['district_code', 'village_code', 'postal_code'],
            'district_code' => ['village_code', 'postal_code'],
        ];

        foreach ($children[$key ?? ''] ?? [] as $child) {
            $this->address[$child] = '';
        }

        if ($key === 'postal_code') {
            $this->resolvePostalCode(trim($value));
        }

        if ($key === 'village_code') {
            $this->fillPostalCode(trim($value));
        }
    }

    /**
     * Fill the postal code from the selected village, clearing it otherwise.
     */
    private function fillPostalCode(string $villageCode): void
    {
        $this->address['postal_code'] = app(RegionLookup::class)->postalCodeForVillage($villageCode) ?? '';
    }

    /**
     * Fill the region chain from a five-digit postal code.
     */
    private function resolvePostalCode(string $postalCode): void
    {
        $village = app(RegionLookup::class)->villageByPostalCode($postalCode);

        if ($village === null) {
            return;
        }

        $this->address['province_code'] = (string) $village->province_code;
        $this->address['regency_code'] = (string) $village->regency_code;
        $this->address['district_code'] = (string) $village->district_code;
        $this->address['village_code'] = (string) $village->code;
    }
}
