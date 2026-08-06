<?php

namespace Tsrgtm\MediaLibrary\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tsrgtm\MediaLibrary\Models\Media;

class MediaFileController extends Controller
{
    /**
     * Public/current media route.
     *
     * Trashed files must never be served through this route.
     */
    public function show(Media $media): StreamedResponse
    {
        return $this->fileResponse(
            media: $media,
            path: (string) ($media->path ?? ''),
            downloadName: $media->original_name ?: "file.{$media->extension}",
        );
    }

    public function variant(
        Media $media,
        string $variant,
    ): StreamedResponse {
        $path = (string) data_get(
            $media->responsive_images,
            "{$variant}.path",
            $media->path ?? '',
        );

        return $this->fileResponse(
            media: $media,
            path: $path,
            downloadName: "{$media->uuid}-{$variant}.webp",
        );
    }

    public function adminShow(Media $media): StreamedResponse
    {
        return $this->fileResponse(
            media: $media,
            path: (string) ($media->path ?? ''),
            downloadName: $media->original_name ?: "file.{$media->extension}",
        );
    }

    public function adminVariant(
        Media $media,
        string $variant,
    ): StreamedResponse {
        $path = (string) data_get(
            $media->responsive_images,
            "{$variant}.path",
            $media->path ?? '',
        );

        return $this->fileResponse(
            media: $media,
            path: $path,
            downloadName: "{$media->uuid}-{$variant}.webp",
        );
    }

    private function fileResponse(
        Media $media,
        string $path,
        string $downloadName,
    ): StreamedResponse {
        $disks = array_unique(array_filter([
            $media->disk,
            config('media-library.disk', 'public'),
            config('filesystems.default', 'local'),
            'public',
            'local',
        ]));

        $foundDisk = null;
        $foundPath = null;

        foreach ($disks as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                if (filled($path) && $disk->exists($path)) {
                    $foundDisk = $disk;
                    $foundPath = $path;
                    break;
                }
                if (filled($media->path) && $disk->exists($media->path)) {
                    $foundDisk = $disk;
                    $foundPath = $media->path;
                    break;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if ($foundDisk && $foundPath) {
            return $foundDisk->response(
                $foundPath,
                $downloadName,
                [
                    'Cache-Control' => 'private, no-store, max-age=0',
                    'X-Content-Type-Options' => 'nosniff',
                ],
            );
        }

        $placeholder = public_path("images/media-placeholders/{$media->extension}.svg");
        if (! file_exists($placeholder)) {
            $placeholder = public_path('images/media-placeholders/file.svg');
        }

        if (file_exists($placeholder)) {
            return response()->streamDownload(
                fn () => readfile($placeholder),
                'placeholder.svg',
                ['Content-Type' => 'image/svg+xml']
            );
        }

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300" fill="none">
  <rect width="400" height="300" rx="12" fill="#F4F4F5"/>
  <path d="M160 120H240M160 150H240M160 180H200" stroke="#9CA3AF" stroke-width="6" stroke-linecap="round"/>
</svg>
SVG;

        return response()->streamDownload(
            fn () => print($svg),
            'placeholder.svg',
            ['Content-Type' => 'image/svg+xml']
        );
    }
}
