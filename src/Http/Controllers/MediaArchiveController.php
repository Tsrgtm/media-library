<?php

namespace Tsrgtm\MediaLibrary\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tsrgtm\MediaLibrary\Models\Media;
use Tsrgtm\MediaLibrary\Models\MediaFolder;
use ZipArchive;

class MediaArchiveController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $data = $request->validate([
            'media_ids' => ['nullable', 'array'],
            'media_ids.*' => ['integer'],
            'folder_ids' => ['nullable', 'array'],
            'folder_ids.*' => ['integer'],
        ]);

        $mediaIds = collect($data['media_ids'] ?? [])
            ->map(fn ($id): int => (int) $id);

        foreach ($data['folder_ids'] ?? [] as $folderId) {
            $mediaIds = $mediaIds->merge(
                $this->mediaIdsInFolder((int) $folderId),
            );
        }

        $items = Media::query()
            ->whereKey($mediaIds->unique()->values())
            ->whereNotNull('path')
            ->get();

        abort_if($items->isEmpty(), 422, 'No downloadable files were selected.');

        $directory = storage_path('app/media-downloads');
        File::ensureDirectoryExists($directory);

        $zipPath = $directory.'/media-'.Str::uuid().'.zip';
        $zip = new ZipArchive;

        abort_unless($zip->open($zipPath, ZipArchive::CREATE) === true, 500);

        foreach ($items as $media) {
            $disk = Storage::disk($media->disk);

            if (! $disk->exists($media->path)) {
                continue;
            }

            $entry = $this->uniqueEntryName(
                $zip,
                $media->original_name,
            );

            $zip->addFile($disk->path($media->path), $entry);
        }

        $zip->close();

        return response()
            ->download($zipPath, 'media-download.zip')
            ->deleteFileAfterSend(true);
    }

    private function mediaIdsInFolder(int $folderId): array
    {
        $folderIds = [$folderId];
        $pending = [$folderId];

        while ($pending !== []) {
            $children = MediaFolder::query()
                ->whereIn('parent_id', $pending)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $pending = array_values(array_diff($children, $folderIds));
            $folderIds = array_values(array_unique([
                ...$folderIds,
                ...$children,
            ]));
        }

        return Media::query()
            ->whereIn('folder_id', $folderIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function uniqueEntryName(
        ZipArchive $zip,
        string $name,
    ): string {
        $name = basename($name);
        $candidate = $name;
        $counter = 2;
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);

        while ($zip->locateName($candidate) !== false) {
            $candidate = $extension !== ''
                ? "{$base}-{$counter}.{$extension}"
                : "{$base}-{$counter}";

            $counter++;
        }

        return $candidate;
    }
}
