<?php

declare(strict_types=1);

use App\Actions\Addresses\RegionLookup;

it('returns empty lists without parent codes', function () {
    $lookup = app(RegionLookup::class);

    expect($lookup->regencies(null))->toBeEmpty()
        ->and($lookup->districts(''))->toBeEmpty()
        ->and($lookup->villages(null))->toBeEmpty();
});

it('rejects invalid postal data', function () {
    $lookup = app(RegionLookup::class);

    expect($lookup->villageByPostalCode('abc'))->toBeNull()
        ->and($lookup->postalCodeForVillage(''))->toBeNull();
});
