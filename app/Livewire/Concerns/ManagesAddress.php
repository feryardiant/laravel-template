<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\Address as AddressModel;
use Creasi\Nusa\Models\District;
use Creasi\Nusa\Models\Province;
use Creasi\Nusa\Models\Regency;
use Creasi\Nusa\Models\Village;
use Illuminate\Validation\Rule;

/**
 * Address form state, validation, and persistence for the parent components
 * that embed `App\Livewire\Address`.
 */
trait ManagesAddress
{
    /**
     * @var array{line: string, rt: string, rw: string, postal_code: string, province_code: string, regency_code: string, district_code: string, village_code: string}
     */
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

    protected function resetAddress(): void
    {
        $this->address = $this->emptyAddress();
    }

    protected function fillAddress(?AddressModel $address): void
    {
        $this->address = $address === null ? $this->emptyAddress() : [
            'line' => $address->line,
            'rt' => (string) $address->rt,
            'rw' => (string) $address->rw,
            'postal_code' => (string) $address->postal_code,
            'province_code' => (string) $address->province_code,
            'regency_code' => (string) $address->regency_code,
            'district_code' => (string) $address->district_code,
            'village_code' => (string) $address->village_code,
        ];
    }

    /**
     * @return array{line: string, rt: string, rw: string, postal_code: string, province_code: string, regency_code: string, district_code: string, village_code: string}
     */
    protected function emptyAddress(): array
    {
        return [
            'line' => '',
            'rt' => '',
            'rw' => '',
            'postal_code' => '',
            'province_code' => '',
            'regency_code' => '',
            'district_code' => '',
            'village_code' => '',
        ];
    }

    /**
     * The address is optional, but a partly filled block requires a street line.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function addressRules(): array
    {
        $filled = $this->addressPayload() !== null;
        $province = $this->address['province_code'];
        $regency = $this->address['regency_code'];
        $district = $this->address['district_code'];

        return [
            'address.line' => [$filled ? 'required' : 'nullable', 'string', 'max:255'],
            'address.rt' => ['nullable', 'digits_between:1,3'],
            'address.rw' => ['nullable', 'digits_between:1,3'],
            'address.postal_code' => ['nullable', 'digits:5'],
            'address.province_code' => ['nullable', 'string', Rule::exists(Province::class, 'code')],
            'address.regency_code' => ['nullable', 'string', Rule::exists(Regency::class, 'code')->where('province_code', $province)],
            'address.district_code' => ['nullable', 'string', Rule::exists(District::class, 'code')->where('regency_code', $regency)],
            'address.village_code' => ['nullable', 'string', Rule::exists(Village::class, 'code')->where('district_code', $district)],
        ];
    }

    /**
     * @return array<string, string>|null
     */
    protected function addressPayload(): ?array
    {
        $address = array_map(
            fn (string $value): string => trim($value),
            $this->address,
        );

        if (array_filter($address, fn (string $value): bool => $value !== '') === []) {
            return null;
        }

        return [
            'line' => $address['line'],
            'rt' => $address['rt'] !== '' ? $address['rt'] : null,
            'rw' => $address['rw'] !== '' ? $address['rw'] : null,
            'postal_code' => $address['postal_code'] !== '' ? $address['postal_code'] : null,
            'province_code' => $address['province_code'] !== '' ? $address['province_code'] : null,
            'regency_code' => $address['regency_code'] !== '' ? $address['regency_code'] : null,
            'district_code' => $address['district_code'] !== '' ? $address['district_code'] : null,
            'village_code' => $address['village_code'] !== '' ? $address['village_code'] : null,
        ];
    }
}
