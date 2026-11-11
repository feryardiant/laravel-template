<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('the dashboard exposes a single level one heading', function () {
    actingAs(User::factory()->create(['locale' => Locale::Indonesian]));

    $html = get(route('dashboard'))->assertOk()->getContent();

    preg_match_all('/<h1\b[^>]*>(.*?)<\/h1>/s', $html, $matches);

    expect($matches[1])->toHaveCount(1)
        ->and(trim(strip_tags($matches[1][0])))->toBe('Dasbor');
});
