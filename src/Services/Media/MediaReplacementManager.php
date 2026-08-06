<?php

namespace Tsrgtm\MediaLibrary\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tsrgtm\MediaLibrary\Enums\MediaKind;
use Tsrgtm\MediaLibrary\Enums\MediaStatus;
use Tsrgtm\MediaLibrary\Jobs\ProcessMedia;
use Tsrgtm\MediaLibrary\Models\Media;

class MediaReplacementManager
{
    public function replace(Media $media, UploadedFile $file): Media
    {
        $newKind = MediaKind::fromMimeType($file->getMimeType());

        if ($newKind !== $media->kind) {
            throw ValidationException::withMessages([
                'file' => "A {$media->kind->value} can only be replaced with another {$media->kind->value}.",
            ]);
        }

        $disk = Storage::disk($media->disk);
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $newPath = "media/{$media->uuid}/original.{$extension}";

        $disk->deleteDirectory("media/{$media->uuid}/responsive");
        $disk->putFileAs("media/{$media->uuid}", $file, "original.{$extension}");

        $media->forceFill([
            'path' => $newPath,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($newPath),
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'status' => MediaStatus::Uploaded,
            'error_message' => null,
            'responsive_images' => null,
        ])->save();

        ProcessMedia::dispatch($media->getKey());

        return $media->fresh();
    }
}
