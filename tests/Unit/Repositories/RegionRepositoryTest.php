<?php

declare(strict_types=1);

use App\Repositories\RegionRepository;

it('returns empty lists without parent codes', function () {
    $regions = app(RegionRepository::class);

    expect($regions->getRegencies(null))->toBeEmpty()
        ->and($regions->getDistricts(''))->toBeEmpty()
        ->and($regions->getVillages(null))->toBeEmpty();
});

it('rejects invalid postal data', function () {
    $regions = app(RegionRepository::class);

    expect($regions->getVillageByPostalCode('abc'))->toBeNull()
        ->and($regions->getPostalCodeForVillage(''))->toBeNull();
});
