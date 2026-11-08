<?php

declare(strict_types=1);

namespace App\Livewire\Ui;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('ui.layout')]
class FileUpload extends Component
{
    use WithFileUploads;

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $photos = [];

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $documents = [];

    /**
     * @var mixed
     */
    public $avatar = null;

    /**
     * @var mixed
     */
    public $customAvatar = null;

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);

        $this->photos = array_values($this->photos);
    }

    public function removeDocument(int $index): void
    {
        unset($this->documents[$index]);

        $this->documents = array_values($this->documents);
    }

    public function clearAvatar(): void
    {
        $this->avatar = null;
    }

    public function clearCustomAvatar(): void
    {
        $this->customAvatar = null;
    }

    public function render(): View
    {
        return view('ui.file-upload');
    }
}
