<?php

declare(strict_types=1);

use App\Enums\Role;
use Creasi\Nusa\Models\District;
use Creasi\Nusa\Models\Province;
use Creasi\Nusa\Models\Regency;
use Creasi\Nusa\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('a postal code fills the address cascade and enables the selects', function () {
    $owner = browserMember(Role::Admin);

    actingAs($owner);

    $village = Village::query()->orderBy('code')->firstOrFail();
    $resolved = Village::query()
        ->where('postal_code', $village->postal_code)
        ->orderBy('code')
        ->firstOrFail();
    $province = Province::query()->where('code', $resolved->province_code)->firstOrFail();
    $regency = Regency::query()->where('code', $resolved->regency_code)->firstOrFail();

    visit('/contacts')
        ->click('Tambah kontak')
        ->wait(0.6)
        ->fill('input[autocomplete="postal-code"]', (string) $village->postal_code)
        ->wait(1.2)
        ->assertScript(
            'document.querySelector(\'input[aria-label="Provinsi"]\').value === '.json_encode($province->name),
        )
        ->assertScript(
            'document.querySelector(\'input[aria-label="Kabupaten/Kota"]\').value === '.json_encode($regency->name),
        )
        ->assertScript(
            'document.querySelector(\'input[aria-label="Kelurahan/Desa"]\').value === '.json_encode($resolved->name),
        )
        ->assertScript(
            '[...document.querySelectorAll("[data-ui-select]")].every((el) => ! el.hasAttribute("data-disabled"))',
        );
});

test('choosing a province unlocks each level of the address cascade', function () {
    $owner = browserMember(Role::Admin);

    actingAs($owner);

    $province = Province::query()->orderBy('code')->firstOrFail();
    $regency = Regency::query()->where('province_code', $province->code)->orderBy('code')->firstOrFail();
    $district = District::query()->where('regency_code', $regency->code)->orderBy('code')->firstOrFail();
    $village = Village::query()
        ->where('district_code', $district->code)
        ->whereNotNull('postal_code')
        ->orderBy('code')
        ->firstOrFail();

    $enabledWithOptions = fn (string $label): string => <<<JS
        (() => {
            const el = document.querySelector('input[aria-label="{$label}"]');
            const root = el?.closest('[data-ui-select]');

            return root !== null && root !== undefined
                && ! root.hasAttribute('data-disabled')
                && root.querySelectorAll('[data-ui-option]').length > 0;
        })()
        JS;

    visit('/contacts')
        ->click('Tambah kontak')
        ->wait(0.6)
        ->assertScript($enabledWithOptions('Provinsi'))
        ->assertScript('document.querySelector(\'input[aria-label="Kabupaten/Kota"]\').closest("[data-ui-select]").hasAttribute("data-disabled")')
        ->click('input[aria-label="Provinsi"]')
        ->click('[data-ui-option][data-value="'.$province->code.'"]')
        ->wait(0.8)
        ->assertScript($enabledWithOptions('Kabupaten/Kota'))
        ->click('input[aria-label="Kabupaten/Kota"]')
        ->click('[data-ui-option][data-value="'.$regency->code.'"]')
        ->wait(0.8)
        ->assertScript($enabledWithOptions('Kecamatan'))
        ->click('input[aria-label="Kecamatan"]')
        ->click('[data-ui-option][data-value="'.$district->code.'"]')
        ->wait(0.8)
        ->assertScript($enabledWithOptions('Kelurahan/Desa'))
        ->click('input[aria-label="Kelurahan/Desa"]')
        ->click('[data-ui-option][data-value="'.$village->code.'"]')
        ->wait(0.8)
        ->assertScript(
            'document.querySelector(\'input[autocomplete="postal-code"]\').value === '.json_encode((string) $village->postal_code),
        );
});
