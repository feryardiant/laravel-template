<?php

declare(strict_types=1);

use App\Actions\FileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
});

it('stores an image and retrieves its temporary URL', function () {
    $action = app(FileStorage::class);

    $path = $action->handle(UploadedFile::fake()->image('avatar.jpg'), 'avatars');

    expect($path)->toContain('avatars/');

    $queryString = parse_url($action->getTemporaryUrl($path), PHP_URL_QUERY);
    parse_str($queryString, $query);

    expect($query)->toHaveKey('expiration');
});

it('replaces and deletes the previous image', function () {
    $action = app(FileStorage::class);

    $first = $action->handle(UploadedFile::fake()->image('first.jpg'), 'photos');
    $second = $action->replace(UploadedFile::fake()->image('second.jpg'), 'photos', $first);

    expect(Storage::disk()->exists($first))->toBeFalse();
    expect(Storage::disk()->exists($second))->toBeTrue();
});

it('ignores external image URLs when deleting', function () {
    app(FileStorage::class)->delete('https://example.com/avatar.jpg');

    expect(Storage::disk()->allFiles())->toBe([]);
});

it('reports a failed deletion', function () {
    Log::spy();

    // A directory at the object path cannot be unlinked, which forces the failure.
    Storage::disk()->makeDirectory('photos/blocked');

    expect(app(FileStorage::class)->delete('photos/blocked'))->toBeFalse();

    Log::shouldHaveReceived('warning')->once();
});

it('keeps the replacement when the previous image cannot be deleted', function () {
    Log::spy();

    Storage::disk()->makeDirectory('photos/blocked');

    $path = app(FileStorage::class)
        ->replace(UploadedFile::fake()->image('new.jpg'), 'photos', 'photos/blocked');

    expect(Storage::disk()->exists($path))->toBeTrue();
    expect(Storage::disk()->directoryExists('photos/blocked'))->toBeTrue();
    Log::shouldHaveReceived('warning')->once();
});
