<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\FileStorage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoredFileController extends Controller
{
    /**
     * Stream a stored file through the application.
     */
    public function __invoke(string $path, FileStorage $fileStorage): StreamedResponse
    {
        abort_unless(preg_match('#^(avatars|photos|logos)/[A-Za-z0-9._-]+$#', $path) === 1, 404);

        // The browser never sees this internal pre-signed URL.
        $presignedUrl = $fileStorage->getTemporaryUrl($path);

        abort_unless(filled($presignedUrl), 404);

        return Response::streamDownload(function () use ($presignedUrl): void {
            if ($stream = fopen($presignedUrl, 'rb')) {
                while (! feof($stream)) {
                    echo fread($stream, 1024 * 8); // 8KB chunks
                    flush();
                }
                fclose($stream);
            }
        }, basename($path), [
            'Content-Type' => Storage::disk()->mimeType($path),
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }
}
