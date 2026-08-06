<?php

namespace Tsrgtm\MediaLibrary\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Tsrgtm\MediaLibrary\Models\Media;
use Tsrgtm\MediaLibrary\Models\MediaFolder;
use Tsrgtm\MediaLibrary\Models\MediaTag;

class MediaLibraryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'folder' => ['nullable', 'string', 'max:160'],
            'search' => ['nullable', 'string', 'max:255'],
            'kind' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'max:30'],
            'tag_id' => ['nullable', 'integer'],
            'sort' => [
                'nullable',
                Rule::in([
                    'newest',
                    'oldest',
                    'name_asc',
                    'name_desc',
                    'size_asc',
                    'size_desc',
                    'updated',
                ]),
            ],
            'trash' => ['nullable', 'boolean'],
            'cursor' => ['nullable', 'string'],
        ]);

        $trash = (bool) ($validated['trash'] ?? false);
        $search = trim((string) ($validated['search'] ?? ''));
        $sort = $validated['sort'] ?? 'newest';
        $folderSlug = $validated['folder'] ?? null;

        $currentFolder = filled($folderSlug)
            ? MediaFolder::query()
                ->with('parent')
                ->when(
                    $trash,
                    fn (Builder $query): Builder => $query->onlyTrashed(),
                )
                ->where('slug', $folderSlug)
                ->firstOrFail()
            : null;

        $folderQuery = MediaFolder::query()
            ->when(
                $trash,
                fn (Builder $query): Builder => $query->onlyTrashed(),
            );

        if ($currentFolder) {
            $folderQuery->where(
                'parent_id',
                $currentFolder->getKey(),
            );
        } elseif ($trash) {
            $trashedIds = MediaFolder::onlyTrashed()
                ->select('id');

            $folderQuery->where(function (
                Builder $query,
            ) use ($trashedIds): void {
                $query
                    ->whereNull('parent_id')
                    ->orWhereNotIn(
                        'parent_id',
                        $trashedIds,
                    );
            });
        } else {
            $folderQuery->whereNull('parent_id');
        }

        if ($search !== '') {
            $folderQuery->where(
                'name',
                'like',
                "%{$search}%",
            );
        }

        $showFolders = blank($validated['kind'] ?? null)
            && blank($validated['status'] ?? null)
            && blank($validated['tag_id'] ?? null);

        $folders = $showFolders
            ? $folderQuery
                ->orderBy('name')
                ->get()
                ->map(
                    fn (MediaFolder $folder): array => $this->serializeFolder($folder),
                )
                ->values()
            : collect();

        $query = Media::query()
            ->with('tags')
            ->when(
                $trash,
                fn (Builder $query): Builder => $query->onlyTrashed(),
            );

        if ($currentFolder) {
            $query->where(
                'folder_id',
                $currentFolder->getKey(),
            );
        } elseif (! $trash) {
            $query->whereNull('folder_id');
        } else {
            /*
             * Root trash shows individually deleted media. Media that belongs
             * to a deleted folder is shown inside that folder instead.
             */
            $deletedFolderIds = MediaFolder::onlyTrashed()
                ->select('id');

            $query->where(function (
                Builder $query,
            ) use ($deletedFolderIds): void {
                $query
                    ->whereNull('folder_id')
                    ->orWhereNotIn(
                        'folder_id',
                        $deletedFolderIds,
                    );
            });
        }

        $query
            ->when(
                $search !== '',
                function (Builder $query) use ($search): void {
                    $like = "%{$search}%";

                    $query->where(
                        function (Builder $query) use ($like): void {
                            $query
                                ->where(
                                    'original_name',
                                    'like',
                                    $like,
                                )
                                ->orWhere(
                                    'title',
                                    'like',
                                    $like,
                                )
                                ->orWhere(
                                    'caption',
                                    'like',
                                    $like,
                                )
                                ->orWhereHas(
                                    'tags',
                                    fn (Builder $query): Builder => $query->where(
                                        'name',
                                        'like',
                                        $like,
                                    ),
                                );
                        },
                    );
                },
            )
            ->when(
                filled($validated['kind'] ?? null),
                fn (Builder $query): Builder => $query->where(
                    'kind',
                    $validated['kind'],
                ),
            )
            ->when(
                filled($validated['status'] ?? null),
                fn (Builder $query): Builder => $query->where(
                    'status',
                    $validated['status'],
                ),
            )
            ->when(
                filled($validated['tag_id'] ?? null),
                fn (Builder $query): Builder => $query->whereHas(
                    'tags',
                    fn (Builder $query): Builder => $query->whereKey(
                        $validated['tag_id'],
                    ),
                ),
            );

        match ($sort) {
            'oldest' => $query->oldest(),
            'name_asc' => $query->orderBy('original_name'),
            'name_desc' => $query->orderByDesc('original_name'),
            'size_asc' => $query->orderBy('size'),
            'size_desc' => $query->orderByDesc('size'),
            'updated' => $query->latest('updated_at'),
            default => $query->latest(),
        };

        $media = $query->cursorPaginate(
            perPage: (int) config(
                'media-library.page_size',
                48,
            ),
        );

        return response()->json([
            'current_folder' => $currentFolder
                ? $this->serializeCurrentFolder(
                    $currentFolder,
                )
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

    public function move(Request $request): JsonResponse
    {
        $data = $request->validate([
            'media_ids' => ['nullable', 'array'],
            'media_ids.*' => ['integer'],
            'folder_ids' => ['nullable', 'array'],
            'folder_ids.*' => ['integer'],
            'target_folder_id' => [
                'required',
                'integer',
                'exists:media_folders,id',
            ],
        ]);

        $mediaIds = array_values(array_unique(array_map(
            'intval',
            $data['media_ids'] ?? [],
        )));

        $folderIds = array_values(array_unique(array_map(
            'intval',
            $data['folder_ids'] ?? [],
        )));

        $targetFolderId = (int) $data['target_folder_id'];

        abort_if(
            in_array($targetFolderId, $folderIds, true),
            422,
            'A folder cannot be moved into itself.',
        );

        DB::transaction(function () use (
            $mediaIds,
            $folderIds,
            $targetFolderId,
        ): void {
            if ($mediaIds !== []) {
                Media::query()
                    ->whereKey($mediaIds)
                    ->update([
                        'folder_id' => $targetFolderId,
                    ]);
            }

            if ($folderIds === []) {
                return;
            }

            foreach ($folderIds as $folderId) {
                $descendants = $this->descendantFolderIds(
                    [$folderId],
                );

                abort_if(
                    in_array(
                        $targetFolderId,
                        $descendants,
                        true,
                    ),
                    422,
                    'A folder cannot be moved into one of its descendants.',
                );
            }

            MediaFolder::query()
                ->whereKey($folderIds)
                ->update([
                    'parent_id' => $targetFolderId,
                ]);
        });

        return response()->json([
            'moved' => true,
            'media_ids' => $mediaIds,
            'folder_ids' => $folderIds,
            'target_folder_id' => $targetFolderId,
        ]);
    }

    private function descendantFolderIds(array $rootIds): array
    {
        $all = array_values(array_unique(array_map(
            'intval',
            $rootIds,
        )));

        $pending = $all;

        while ($pending !== []) {
            $children = MediaFolder::query()
                ->whereIn('parent_id', $pending)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $pending = array_values(array_diff(
                $children,
                $all,
            ));

            $all = array_values(array_unique([
                ...$all,
                ...$children,
            ]));
        }

        return $all;
    }

    public function show(Media $media): JsonResponse
    {
        $media->load('tags');

        return response()->json([
            'media' => $this->serializeMedia($media),
        ]);
    }

    private function thumbnailUrl(Media $media): string
    {
        if (! $media->trashed()) {
            return $media->thumbnail_url;
        }

        if ($media->kind->value !== 'image') {
            return $media->placeholder_url;
        }

        foreach (['thumbnail', 'small'] as $variant) {
            if (
                filled(
                    data_get(
                        $media->responsive_images,
                        "{$variant}.path",
                    ),
                )
            ) {
                return route(
                    'media.library.files.variant',
                    [
                        'media' => $media->uuid,
                        'variant' => $variant,
                    ],
                );
            }
        }

        return filled($media->path)
            ? route(
                'media.library.files.show',
                ['media' => $media->uuid],
            )
            : $media->kind_placeholder_url;
    }

    private function previewUrl(Media $media): string
    {
        if (! $media->trashed()) {
            return $media->preview_url;
        }

        if ($media->kind->value !== 'image') {
            return $media->placeholder_url;
        }

        return filled($media->path)
            ? route(
                'media.library.files.show',
                ['media' => $media->uuid],
            )
            : $media->kind_placeholder_url;
    }

    private function serializeFolder(
        MediaFolder $folder,
    ): array {
        return [
            'type' => 'folder',
            'id' => $folder->getKey(),
            'name' => $folder->name,
            'slug' => $folder->slug,
            'parent_id' => $folder->parent_id,
            'thumbnail_url' => $folder->thumbnail_url,
            'fallback_url' => $folder->thumbnail_url,
            'thumbnail_mode' => 'contain',
            'deleted_at' => $folder->deleted_at?->toIso8601String(),
        ];
    }

    private function serializeCurrentFolder(
        MediaFolder $folder,
    ): array {
        return [
            'id' => $folder->getKey(),
            'name' => $folder->name,
            'slug' => $folder->slug,
            'deleted_at' => $folder->deleted_at?->toIso8601String(),
            'parent' => $folder->parent
                ? [
                    'id' => $folder->parent->getKey(),
                    'name' => $folder->parent->name,
                    'slug' => $folder->parent->slug,
                    'deleted_at' => $folder->parent
                        ->deleted_at?->toIso8601String(),
                ]
                : null,
        ];
    }

    private function serializeMedia(Media $media): array
    {
        return [
            'type' => 'media',
            'id' => $media->getKey(),
            'uuid' => $media->uuid,
            'name' => $media->original_name,
            'title' => $media->title,
            'kind' => $media->kind->value,
            'status' => $media->status->value,
            'size' => $media->size,
            'mime_type' => $media->mime_type,
            'extension' => $media->extension,
            'width' => $media->width,
            'height' => $media->height,
            'thumbnail_url' => $this->thumbnailUrl($media) ?: $media->kind_placeholder_url,
            'preview_url' => $this->previewUrl($media) ?: route('media.library.files.show', ['media' => $media->uuid]),
            'fallback_url' => $media->kind_placeholder_url,
            'thumbnail_mode' => $media->kind->value === 'image'
                ? 'cover'
                : 'contain',
            'url' => $media->url ?: route('media.library.files.show', ['media' => $media->uuid]),
            'folder_id' => $media->folder_id,
            'deleted_at' => $media->deleted_at?->toIso8601String(),
            'tags' => $media->tags
                ->map(fn (MediaTag $tag): array => [
                    'id' => $tag->getKey(),
                    'name' => $tag->name,
                ])
                ->values(),
            'created_at' => $media->created_at?->toIso8601String(),
            'updated_at' => $media->updated_at?->toIso8601String(),
        ];
    }
}
