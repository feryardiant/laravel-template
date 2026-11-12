<?php

declare(strict_types=1);

use App\Actions\FileStorage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake();
    actingAs(User::factory()->create());
});

it('returns not found for a missing file', function () {
    get(route('uploads', ['path' => 'avatars/not-exists.jpg']))->assertNotFound();
});

it('returns not found for invalid paths', function () {
    app(FileStorage::class)->handle(UploadedFile::fake()->image('avatar.jpg'), 'avatars');

    get(route('uploads', ['path' => 'not-avatar/image.jpg']))->assertNotFound();
});

it('streams the file', function () {
    $path = app(FileStorage::class)->handle(UploadedFile::fake()->image('avatar.jpg'), 'avatars');

    get(route('uploads', ['path' => $path]))
        ->assertStreamed()
        ->assertOk();
});
