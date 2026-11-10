<?php

declare(strict_types=1);

namespace App\Actions\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StoreImage
{
    /**
     * Store an uploaded image and return its object path.
     */
    public function handle(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory);

        if ($path === false) {
            throw new RuntimeException('Unable to store the image.');
        }

        return $path;
    }

    /**
     * Replace a previously stored image with a new upload.
     */
    public function replace(UploadedFile $file, string $directory, ?string $previousPath = null): string
    {
        $path = $this->handle($file, $directory);

        $this->delete($previousPath);

        return $path;
    }

    /**
     * Delete a previously stored image.
     */
    public function delete(?string $path): void
    {
        if ($path === null || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        Storage::disk()->delete($path);
    }
}
