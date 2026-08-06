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
        abort_if($media->trashed(), 404);
        abort_unless(filled($media->path), 404);

        return $this->fileResponse(
            media: $media,
            path: $media->path,
            downloadName: $media->original_name,
        );
    }

    /**
     * Public/current responsive variant route.
     *
     * Trashed variants must never be served through this route.
     */
    public function variant(
        Media $media,
        string $variant,
    ): StreamedResponse {
        abort_if($media->trashed(), 404);

        $path = data_get(
            $media->responsive_images,
            "{$variant}.path",
        );

        abort_unless(filled($path), 404);

        return $this->fileResponse(
            media: $media,
            path: $path,
            downloadName: "{$media->uuid}-{$variant}.webp",
        );
    }

    /**
     * Authenticated admin preview route.
     *
     * Route binding explicitly includes soft-deleted records.
     */
    public function adminShow(Media $media): StreamedResponse
    {
        abort_unless(filled($media->path), 404);

        return $this->fileResponse(
            media: $media,
            path: $media->path,
            downloadName: $media->original_name,
        );
    }

    /**
     * Authenticated admin responsive preview route.
     *
     * This allows the trash page to display thumbnails while normal
     * public routes still return 404.
     */
    public function adminVariant(
        Media $media,
        string $variant,
    ): StreamedResponse {
        $path = data_get(
            $media->responsive_images,
            "{$variant}.path",
        );

        abort_unless(filled($path), 404);

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
        $disk = Storage::disk($media->disk);

        if (! $disk->exists($path) && filled($media->path) && $disk->exists($media->path)) {
            $path = $media->path;
        }

        if (! $disk->exists($path)) {
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

            abort(404);
        }

        return $disk->response(
            $path,
            $downloadName,
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
