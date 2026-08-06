<?php

namespace Tsrgtm\MediaLibrary\Services\Media;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tsrgtm\MediaLibrary\Enums\MediaKind;
use Tsrgtm\MediaLibrary\Enums\MediaStatus;
use Tsrgtm\MediaLibrary\Jobs\ProcessMedia;
use Tsrgtm\MediaLibrary\Models\Media;
use Tsrgtm\MediaLibrary\Models\MediaFolder;
use Tsrgtm\MediaLibrary\Models\MediaUploadSession;

class TusUploadManager
{
    public function create(
        Authenticatable $user,
        int $uploadLength,
        array $metadata,
    ): MediaUploadSession {
        $maximum = (int) config('media-library.maximum_upload_size');

        if ($uploadLength < 1 || $uploadLength > $maximum) {
            throw new RuntimeException('The upload size is not allowed.');
        }

        $name = trim((string) ($metadata['filename'] ?? 'upload.bin'));
        $mimeType = trim((string) ($metadata['filetype'] ?? ''));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $folderSlug = trim((string) ($metadata['folder_slug'] ?? ''));

        $folderId = $folderSlug !== ''
            ? MediaFolder::query()
                ->where('slug', $folderSlug)
                ->value('id')
            : null;

        return DB::transaction(function () use (
            $user,
            $uploadLength,
            $name,
            $mimeType,
            $extension,
            $folderId,
        ): MediaUploadSession {
            $media = Media::query()->create([
                'uuid' => (string) Str::uuid(),
                'folder_id' => $folderId,
                'uploaded_by' => $user->getAuthIdentifier(),
                'disk' => config('media-library.disk'),
                'path' => null,
                'original_name' => $name,
                'file_name' => null,
                'mime_type' => $mimeType ?: null,
                'extension' => $extension ?: null,
                'kind' => MediaKind::fromMimeType($mimeType),
                'status' => MediaStatus::Uploading,
                'size' => $uploadLength,
                'title' => pathinfo($name, PATHINFO_FILENAME),
            ]);

            $sessionId = (string) Str::uuid();

            return MediaUploadSession::query()->create([
                'id' => $sessionId,
                'media_id' => $media->getKey(),
                'user_id' => $user->getAuthIdentifier(),
                'size' => $uploadLength,
                'chunk_size' => (int) config('media-library.chunk_size'),
                'total_chunks' => 1,
                'temporary_directory' => "media-uploads/{$sessionId}",
                'status' => 'uploading',
                'expires_at' => now()->addMinutes(
                    (int) config(
                        'media-library.upload_session_lifetime_minutes',
                    ),
                ),
            ]);
        }, 5);
    }

    public function offset(MediaUploadSession $session): int
    {
        $disk = Storage::disk(config('media-library.temporary_disk'));
        $path = $this->temporaryFilePath($session);

        return $disk->exists($path)
            ? (int) $disk->size($path)
            : 0;
    }

    public function append(
        MediaUploadSession $session,
        Request $request,
        int $expectedOffset,
    ): int {
        $currentOffset = $this->offset($session);

        if ($expectedOffset !== $currentOffset) {
            throw new RuntimeException(
                "Upload offset mismatch. Expected {$currentOffset}.",
            );
        }

        $input = $request->getContent(true);

        if (! is_resource($input)) {
            throw new RuntimeException(
                'Unable to read the upload request body.',
            );
        }

        $disk = Storage::disk(config('media-library.temporary_disk'));
        $path = $this->temporaryFilePath($session);
        $absolutePath = $disk->path($path);
        $directory = dirname($absolutePath);

        if (
            ! is_dir($directory)
            && ! mkdir($directory, 0775, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                'Unable to create the temporary upload directory.',
            );
        }

        $output = fopen($absolutePath, 'ab');

        if ($output === false) {
            throw new RuntimeException(
                'Unable to open the temporary upload file.',
            );
        }

        try {
            stream_copy_to_stream($input, $output);
        } finally {
            fclose($output);
            fclose($input);
        }

        clearstatcache(true, $absolutePath);

        $newOffset = (int) filesize($absolutePath);

        if ($newOffset > $session->size) {
            throw new RuntimeException(
                'The upload exceeded its declared size.',
            );
        }

        if ($newOffset === $session->size) {
            $this->finalize($session);
        }

        return $newOffset;
    }

    public function finalize(MediaUploadSession $session): Media
    {
        $session->refresh();

        if ($session->status === 'completed') {
            return $session->media()->firstOrFail();
        }

        $temporaryDisk = Storage::disk(
            config('media-library.temporary_disk'),
        );

        $temporaryPath = $this->temporaryFilePath($session);

        if (
            ! $temporaryDisk->exists($temporaryPath)
            || (int) $temporaryDisk->size($temporaryPath) !== $session->size
        ) {
            throw new RuntimeException(
                'The temporary upload is incomplete.',
            );
        }

        $media = $session->media()->firstOrFail();
        $extension = $media->extension ?: 'bin';
        $finalPath = "media/{$media->uuid}/original.{$extension}";
        $destinationDisk = Storage::disk($media->disk);

        $input = fopen($temporaryDisk->path($temporaryPath), 'rb');

        if ($input === false) {
            throw new RuntimeException(
                'Unable to read the completed upload.',
            );
        }

        try {
            $destinationDisk->writeStream($finalPath, $input);
        } finally {
            fclose($input);
        }

        $absoluteFinalPath = $destinationDisk->path($finalPath);
        $detectedMimeType = mime_content_type($absoluteFinalPath)
            ?: $media->mime_type;

        $media->forceFill([
            'path' => $finalPath,
            'file_name' => basename($finalPath),
            'mime_type' => $detectedMimeType,
            'kind' => MediaKind::fromMimeType($detectedMimeType),
            'status' => MediaStatus::Uploaded,
            'checksum' => hash_file('sha256', $absoluteFinalPath),
            'error_message' => null,
        ])->save();

        $session->update([
            'status' => 'completed',
        ]);

        $temporaryDisk->deleteDirectory(
            $session->temporary_directory,
        );

        ProcessMedia::dispatch($media->getKey())
            ->onConnection(
                (string) config(
                    'media-library.queue_connection',
                    'sync',
                ),
            );

        return $media->fresh();
    }

    public function delete(MediaUploadSession $session): void
    {
        Storage::disk(config('media-library.temporary_disk'))
            ->deleteDirectory($session->temporary_directory);

        $session->media()->delete();
        $session->delete();
    }

    public function parseMetadata(?string $header): array
    {
        if (blank($header)) {
            return [];
        }

        $metadata = [];

        foreach (explode(',', $header) as $item) {
            $parts = preg_split('/\s+/', trim($item), 2);

            $key = $parts[0] ?? null;
            $encodedValue = $parts[1] ?? '';

            if (! filled($key)) {
                continue;
            }

            $decoded = base64_decode($encodedValue, true);

            $metadata[$key] = $decoded === false
                ? ''
                : $decoded;
        }

        return $metadata;
    }

    private function temporaryFilePath(
        MediaUploadSession $session,
    ): string {
        return "{$session->temporary_directory}/upload.bin";
    }
}
