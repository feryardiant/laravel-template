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

it('renders and clears a custom avatar uploader', function () {
    Livewire::test(FileUpload::class)
        ->set('customAvatar', UploadedFile::fake()->image('custom.png'))
        ->assertSee('custom.png')
        ->call('clearCustomAvatar')
        ->assertSet('customAvatar', null);
});

it('receives files from the plain multipart form', function () {
    $response = $this->post(route('ui.file-upload.plain'), [
        'plain_files' => [
            UploadedFile::fake()->create('one.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('two.pdf', 10, 'application/pdf'),
        ],
    ]);

    $response->assertRedirect(route('ui.file-upload'));
    $response->assertSessionHas('plain_upload_count', 2);
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
