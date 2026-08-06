<?php

namespace Tsrgtm\MediaLibrary\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tsrgtm\MediaLibrary\Models\Media;
use Tsrgtm\MediaLibrary\Models\MediaFolder;
use Tsrgtm\MediaLibrary\Models\MediaTag;

class MediaPickerController extends Controller
{
    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['nullable', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $ids = collect($validated['ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return response()->json([
                'media' => [],
            ]);
        }

        $order = array_flip($ids);

        $media = Media::query()
            ->with('tags')
            ->whereKey($ids)
            ->whereNull('deleted_at')
            ->get()
            ->sortBy(
                fn (Media $item): int => $order[$item->getKey()] ?? PHP_INT_MAX,
            )
            ->map(
                fn (Media $item): array => $this->serializeMedia($item),
            )
            ->values();

        return response()->json([
            'media' => $media,
        ]);
    }

    public function browse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'folder' => ['nullable', 'string', 'max:160'],
            'search' => ['nullable', 'string', 'max:255'],
            'kinds' => ['nullable', 'array'],
            'kinds.*' => ['string', 'max:30'],
            'extensions' => ['nullable', 'array'],
            'extensions.*' => ['string', 'max:30'],
            'mime_types' => ['nullable', 'array'],
            'mime_types.*' => ['string', 'max:120'],
            'cursor' => ['nullable', 'string'],
            'show_folders' => ['nullable', 'boolean'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $folderSlug = $validated['folder'] ?? null;
        $showFolders = (bool) ($validated['show_folders'] ?? true);

        $currentFolder = filled($folderSlug)
            ? MediaFolder::query()
                ->with('parent')
                ->where('slug', $folderSlug)
                ->firstOrFail()
            : null;

        $folders = collect();

        if ($showFolders) {
            $folders = MediaFolder::query()
                ->where(
                    'parent_id',
                    $currentFolder?->getKey(),
                )
                ->when(
                    $search !== '',
                    fn (Builder $query): Builder => $query->where(
                        'name',
                        'like',
                        "%{$search}%",
                    ),
                )
                ->orderBy('name')
                ->get()
                ->map(fn (MediaFolder $folder): array => [
                    'type' => 'folder',
                    'id' => $folder->getKey(),
                    'name' => $folder->name,
                    'slug' => $folder->slug,
                    'parent_id' => $folder->parent_id,
                    'thumbnail_url' => $folder->thumbnail_url,
                ])
                ->values();
        }

        $query = Media::query()
            ->with('tags')
            ->where('status', 'ready')
            ->where('folder_id', $currentFolder?->getKey());

        $query
            ->when(
                $search !== '',
                function (Builder $query) use ($search): void {
                    $like = "%{$search}%";

                    $query->where(function (
                        Builder $query,
                    ) use ($like): void {
                        $query
                            ->where('original_name', 'like', $like)
                            ->orWhere('title', 'like', $like)
                            ->orWhere('caption', 'like', $like)
                            ->orWhereHas(
                                'tags',
                                fn (Builder $tagQuery): Builder => $tagQuery->where(
                                    'name',
                                    'like',
                                    $like,
                                ),
                            );
                    });
                },
            )
            ->when(
                filled($validated['kinds'] ?? []),
                fn (Builder $query): Builder => $query->whereIn(
                    'kind',
                    $validated['kinds'],
                ),
            )
            ->when(
                filled($validated['extensions'] ?? []),
                fn (Builder $query): Builder => $query->whereIn(
                    'extension',
                    collect($validated['extensions'])
                        ->map(
                            fn ($extension): string => strtolower(
                                ltrim(
                                    (string) $extension,
                                    '.',
                                ),
                            ),
                        )
                        ->all(),
                ),
            );

        $mimeTypes = $validated['mime_types'] ?? [];

        if ($mimeTypes !== []) {
            $query->where(function (
                Builder $query,
            ) use ($mimeTypes): void {
                foreach ($mimeTypes as $index => $mimeType) {
                    $method = $index === 0
                        ? 'where'
                        : 'orWhere';

                    if (str_ends_with($mimeType, '/*')) {
                        $query->{$method}(
                            'mime_type',
                            'like',
                            substr($mimeType, 0, -1).'%',
                        );
                    } else {
                        $query->{$method}(
                            'mime_type',
                            $mimeType,
                        );
                    }
                }
            });
        }

        $media = $query
            ->latest()
            ->cursorPaginate(
                perPage: min(
                    80,
                    max(
                        12,
                        (int) config(
                            'media-library.picker.page_size',
                            40,
                        ),
                    ),
                ),
            );

        return response()->json([
            'current_folder' => $currentFolder
                ? [
                    'id' => $currentFolder->getKey(),
                    'name' => $currentFolder->name,
                    'slug' => $currentFolder->slug,
                    'parent' => $currentFolder->parent
                        ? [
                            'id' => $currentFolder->parent->getKey(),
                            'name' => $currentFolder->parent->name,
                            'slug' => $currentFolder->parent->slug,
                        ]
                        : null,
                ]
                : null,
            'folders' => $folders,
            'media' => collect($media->items())
                ->map(
                    fn (Media $item): array => $this->serializeMedia($item),
                )
                ->values(),
            'next_cursor' => $media->nextCursor()?->encode(),
            'has_more' => $media->hasMorePages(),
        ]);
    }

    private function serializeMedia(Media $media): array
    {
        return [
            'type' => 'media',
            'id' => $media->getKey(),
            'uuid' => $media->uuid,
            'name' => $media->original_name,
            'title' => $media->title,
            'alt' => $media->alt,
            'caption' => $media->caption,
            'kind' => $media->kind->value,
            'status' => $media->status->value,
            'size' => $media->size,
            'mime_type' => $media->mime_type,
            'extension' => $media->extension,
            'width' => $media->width,
            'height' => $media->height,
            'duration' => $media->duration,
            'thumbnail_url' => $media->thumbnail_url,
            'preview_url' => $media->preview_url,
            'fallback_url' => $media->kind_placeholder_url,
            'thumbnail_mode' => $media->kind->value === 'image'
                    ? 'cover'
                    : 'contain',
            'url' => $media->url,
            'folder_id' => $media->folder_id,
            'tags' => $media->tags
                ->map(fn (MediaTag $tag): array => [
                    'id' => $tag->getKey(),
                    'name' => $tag->name,
                ])
                ->values(),
        ];
    }
}
