<?php

declare(strict_types=1);

use App\Actions\Files\StoreImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('stores an image and returns its path', function () {
    Storage::fake();

    $path = app(StoreImage::class)->handle(UploadedFile::fake()->image('avatar.jpg'), 'avatars');

    expect($path)->toContain('avatars/');
    expect(Storage::disk()->exists($path))->toBeTrue();
});

it('replaces and deletes the previous image', function () {
    Storage::fake();

    $action = app(StoreImage::class);

    $first = $action->handle(UploadedFile::fake()->image('first.jpg'), 'photos');
    $second = $action->replace(UploadedFile::fake()->image('second.jpg'), 'photos', $first);

    expect(Storage::disk()->exists($first))->toBeFalse();
    expect(Storage::disk()->exists($second))->toBeTrue();
});

it('ignores external image urls when deleting', function () {
    Storage::fake();

    app(StoreImage::class)->delete('https://example.com/avatar.jpg');

    expect(Storage::disk()->allFiles())->toBe([]);
});
