<?php

declare(strict_types=1);

use App\Livewire\Ui\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(FileUploadConfiguration::disk());
});

it('renders and removes a single uploaded image', function () {
    Livewire::test(FileUpload::class)
        ->set('avatar', UploadedFile::fake()->image('photo.png'))
        ->assertSee('photo.png')
        ->call('clearAvatar')
        ->assertSet('avatar', null);
});

it('renders and removes multiple uploaded photos', function () {
    Livewire::test(FileUpload::class)
        ->set('photos', [
            UploadedFile::fake()->image('first.png'),
            UploadedFile::fake()->image('second.png'),
        ])
        ->assertSee('first.png')
        ->assertSee('second.png')
        ->call('removePhoto', 0)
        ->assertCount('photos', 1)
        ->assertSee('second.png');
});
