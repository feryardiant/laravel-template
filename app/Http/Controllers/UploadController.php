<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadController extends Controller
{
    /**
     * Stream a private S3 object through the application.
     */
    public function __invoke(string $path): StreamedResponse
    {
        $storage = Storage::disk();

        // The browser never sees this internal presigned URL.
        $presignedUrl = $storage->temporaryUrl($path, now()->addMinutes(60));

        return Response::streamDownload(function () use ($presignedUrl): void {
            if ($s3Stream = fopen($presignedUrl, 'rb')) {
                while (! feof($s3Stream)) {
                    echo fread($s3Stream, 1024 * 8); // 8KB chunks
                    flush();
                }
                fclose($s3Stream);
            }
        }, basename($path), [
            'Content-Type' => $storage->mimeType($path),
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }
}
