<?php

declare(strict_types=1);

use App\Actions\Files\StoreImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('stores an image and returns its path', function () {
    Storage::fake('s3');

    $path = app(StoreImage::class)->handle(UploadedFile::fake()->image('avatar.jpg'), 'avatars');

    expect($path)->toContain('avatars/')
        ->and(Storage::disk('s3')->exists($path))->toBeTrue();
});

it('replaces and deletes the previous image', function () {
    Storage::fake('s3');

    $action = app(StoreImage::class);

    $first = $action->handle(UploadedFile::fake()->image('first.jpg'), 'photos');
    $second = $action->replace(UploadedFile::fake()->image('second.jpg'), 'photos', $first);

    expect(Storage::disk('s3')->exists($first))->toBeFalse()
        ->and(Storage::disk('s3')->exists($second))->toBeTrue();
});

it('ignores external image urls when deleting', function () {
    Storage::fake('s3');

    app(StoreImage::class)->delete('https://example.com/avatar.jpg');

    expect(Storage::disk('s3')->allFiles())->toBe([]);
});
