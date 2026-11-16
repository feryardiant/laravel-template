<?php

declare(strict_types=1);

namespace App\Livewire;

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
        $postalCode = $villageCode === ''
            ? null
            : Village::query()->where('code', $villageCode)->value('postal_code');

        $this->address['postal_code'] = $postalCode === null ? '' : (string) $postalCode;
    }

    /**
     * Fill the region chain from a five-digit postal code.
     */
    private function resolvePostalCode(string $postalCode): void
    {
        if (preg_match('/^\d{5}$/', $postalCode) !== 1) {
            return;
        }

        // shortcut: a postal code can cover several villages; take the first.
        $village = Village::query()
            ->where('postal_code', $postalCode)
            ->orderBy('code')
            ->first(['code', 'district_code', 'regency_code', 'province_code']);

        if ($village === null) {
            return;
        }

        $this->address['province_code'] = (string) $village->province_code;
        $this->address['regency_code'] = (string) $village->regency_code;
        $this->address['district_code'] = (string) $village->district_code;
        $this->address['village_code'] = (string) $village->code;
    }

    /**
     * @return Collection<int, Province>
     */
    #[Computed]
    public function provinces(): Collection
    {
        return Province::query()->orderBy('name')->get(['code', 'name']);
    }

    /**
     * @return Collection<int, Regency>
     */
    #[Computed]
    public function regencies(): Collection
    {
        $province = $this->address['province_code'];

        if (blank($province)) {
            return new Collection;
        }

        return Regency::query()->where('province_code', $province)->orderBy('name')->get(['code', 'name']);
    }

    /**
     * @return Collection<int, District>
     */
    #[Computed]
    public function districts(): Collection
    {
        $regency = $this->address['regency_code'];

        if (blank($regency)) {
            return new Collection;
        }

        return District::query()->where('regency_code', $regency)->orderBy('name')->get(['code', 'name']);
    }

    /**
     * @return Collection<int, Village>
     */
    #[Computed]
    public function villages(): Collection
    {
        $district = $this->address['district_code'];

        if (blank($district)) {
            return new Collection;
        }

        return Village::query()->where('district_code', $district)->orderBy('name')->get(['code', 'name']);
    }

    public function render(): View
    {
        return view('livewire.address');
    }
}
