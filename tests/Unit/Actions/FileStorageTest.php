<?php

declare(strict_types=1);

use App\Actions\FileStorage;
use Illuminate\Http\UploadedFile;
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
