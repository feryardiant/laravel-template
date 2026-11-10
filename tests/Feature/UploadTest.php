<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('streams a private image through the application', function () {
    Storage::fake();

    $content = 'private-image-content';

    Storage::disk()->put('logos/logo.jpg', $content);
    Storage::disk()->buildTemporaryUrlsUsing(
        fn (string $path): string => Storage::disk()->path($path),
    );

    actingAs(User::factory()->create());

    $response = get(route('uploads', ['path' => 'logos/logo.jpg']));

    $response->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=logo.jpg');

    expect($response->streamedContent())->toBe($content);
});

it('requires authentication to stream images', function () {
    Storage::fake();

    get(route('uploads', ['path' => 'logos/logo.jpg']))
        ->assertRedirect(route('login'));
});

it('only streams image paths', function () {
    Storage::fake();

    Storage::disk()->put('private/secret.txt', 'secret');

    actingAs(User::factory()->create());

    get(route('uploads', ['path' => 'private/secret.txt']))->assertNotFound();
});

it('returns not found for a missing image', function () {
    Storage::fake();

    actingAs(User::factory()->create());

    get(route('uploads', ['path' => 'logos/missing.jpg']))->assertNotFound();
});
