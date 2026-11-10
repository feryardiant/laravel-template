<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('streams a private image through the application', function () {
    Storage::fake('s3');

    $content = 'private-image-content';

    Storage::disk('s3')->put('logos/logo.jpg', $content);
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path): string => Storage::disk('s3')->path($path),
    );

    actingAs(User::factory()->create());

    $response = get(route('uploads', ['path' => 'logos/logo.jpg']));

    $response->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=logo.jpg');

    expect($response->streamedContent())->toBe($content);
});

it('requires authentication to stream images', function () {
    Storage::fake('s3');

    get(route('uploads', ['path' => 'logos/logo.jpg']))
        ->assertRedirect(route('login'));
});
