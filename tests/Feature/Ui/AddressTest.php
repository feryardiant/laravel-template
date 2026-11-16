<?php

declare(strict_types=1);

use App\Livewire\Address;
use Creasi\Nusa\Models\Province;
use Creasi\Nusa\Models\Regency;
use Creasi\Nusa\Models\Village;
use Livewire\Livewire;

it('loads regencies for the selected province', function () {
    $fields = validAddress();
    $regency = Regency::query()->where('code', $fields['regency_code'])->firstOrFail();

    Livewire::test(Address::class)
        ->set('address.province_code', $fields['province_code'])
        ->assertSet('address.province_code', $fields['province_code'])
        ->assertSee($regency->name);
});

it('clears the dependent region codes when a parent changes', function () {
    $fields = validAddress();
    $otherProvince = Province::query()
        ->where('code', '!=', $fields['province_code'])
        ->value('code');

    Livewire::test(Address::class)
        ->set('address.province_code', $fields['province_code'])
        ->set('address.regency_code', $fields['regency_code'])
        ->set('address.district_code', $fields['district_code'])
        ->set('address.village_code', $fields['village_code'])
        ->assertNotSet('address.postal_code', '')
        ->set('address.province_code', $otherProvince)
        ->assertSet('address.regency_code', '')
        ->assertSet('address.district_code', '')
        ->assertSet('address.village_code', '')
        ->assertSet('address.postal_code', '');
});

it('fills the region chain from a postal code and enables every select', function () {
    $village = Village::query()->orderBy('code')->firstOrFail();
    $resolved = Village::query()
        ->where('postal_code', $village->postal_code)
        ->orderBy('code')
        ->firstOrFail();

    $component = Livewire::test(Address::class);

    expect(substr_count($component->html(), 'data-disabled'))->toBe(3);

    $component
        ->set('address.postal_code', (string) $village->postal_code)
        ->assertSet('address.province_code', (string) $resolved->province_code)
        ->assertSet('address.regency_code', (string) $resolved->regency_code)
        ->assertSet('address.district_code', (string) $resolved->district_code)
        ->assertSet('address.village_code', (string) $resolved->code);

    expect(substr_count($component->html(), 'data-disabled'))->toBe(0);
});

it('clears the region chain for an unknown postal code', function () {
    $fields = validAddress();

    Livewire::test(Address::class)
        ->set('address.province_code', $fields['province_code'])
        ->set('address.regency_code', $fields['regency_code'])
        ->set('address.postal_code', '00000')
        ->assertSet('address.province_code', '')
        ->assertSet('address.regency_code', '')
        ->assertSet('address.district_code', '')
        ->assertSet('address.village_code', '');
});

it('fills the postal code from the selected village', function () {
    $village = Village::query()->whereNotNull('postal_code')->orderBy('code')->firstOrFail();

    Livewire::test(Address::class)
        ->set('address.postal_code', '00000')
        ->set('address.district_code', (string) $village->district_code)
        ->set('address.village_code', (string) $village->code)
        ->assertSet('address.postal_code', (string) $village->postal_code)
        ->set('address.village_code', '')
        ->assertSet('address.postal_code', '');
});
