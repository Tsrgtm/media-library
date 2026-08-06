<?php

namespace Tsrgtm\MediaLibrary\Services\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;
use Tsrgtm\MediaLibrary\Models\Media;

class MediaImageProcessor
{
    private ImageManager $images;

    public function __construct()
    {
        $this->images = ImageManager::usingDriver(
            Driver::class,
        );
    }

    public function process(Media $media): void
    {
        $disk = Storage::disk($media->disk);

        if (
            blank($media->path)
            || ! $disk->exists($media->path)
        ) {
            throw new RuntimeException(
                'The source image does not exist.',
            );
        }

        $sourcePath = $disk->path($media->path);

        $quality = max(
            1,
            min(
                100,
                (int) config(
                    'media-library.image.quality',
                    82,
                ),
            ),
        );

        try {
            $source = $this->images->decodePath(
                $sourcePath,
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'The uploaded image cannot be decoded.',
                previous: $exception,
            );
        }

        $width = $source->width();
        $height = $source->height();
        $previousPath = $media->path;
        $mainPath = "media/{$media->uuid}/original.webp";

        $main = (string) $source->encodeUsingFormat(
            format: Format::WEBP,
            quality: $quality,
        );

        if ($main === '') {
            throw new RuntimeException(
                'WebP conversion returned no data.',
            );
        }

        $disk->put($mainPath, $main);

        // Remove all stale conversions before regenerating.
        $disk->deleteDirectory(
            "media/{$media->uuid}/responsive",
        );

        $conversions = [];

        foreach (
            (array) config(
                'media-library.image.conversions',
                [],
            ) as $name => $targetWidth
        ) {
            $targetWidth = (int) $targetWidth;

            /*
             * Never upscale. When a requested conversion does not exist,
             * Media::responsiveUrl() can return the original source.
             */
            if (
                $targetWidth < 1
                || $targetWidth >= $width
            ) {
                continue;
            }

            $this->makeConversion(
                disk: $disk,
                sourcePath: $sourcePath,
                media: $media,
                name: (string) $name,
                targetWidth: $targetWidth,
                quality: $quality,
                conversions: $conversions,
            );
        }

        $media->forceFill([
            'path' => $mainPath,
            'file_name' => 'original.webp',
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'width' => $width,
            'height' => $height,
            'size' => strlen($main),
            'responsive_images' => $conversions,
            'error_message' => null,
        ])->save();

        if (
            $previousPath !== $mainPath
            && $disk->exists($previousPath)
        ) {
            $disk->delete($previousPath);
        }
    }

    private function makeConversion(
        Filesystem $disk,
        string $sourcePath,
        Media $media,
        string $name,
        int $targetWidth,
        int $quality,
        array &$conversions,
    ): void {
        $safeName = trim(
            (string) preg_replace(
                '/[^a-z0-9_-]+/i',
                '-',
                strtolower($name),
            ),
            '-_',
        );

        if ($safeName === '') {
            return;
        }

        try {
            $image = $this->images
                ->decodePath($sourcePath)
                ->scaleDown(width: $targetWidth);

            $contents = (string) $image->encodeUsingFormat(
                format: Format::WEBP,
                quality: $quality,
            );
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        if ($contents === '') {
            return;
        }

        $path = implode('/', [
            'media',
            $media->uuid,
            'responsive',
            "{$safeName}.webp",
        ]);

        $disk->put($path, $contents);

        $conversions[$safeName] = [
            'path' => $path,
            'width' => $image->width(),
            'height' => $image->height(),
            'size' => strlen($contents),
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'quality' => $quality,
        ];
    }
}
