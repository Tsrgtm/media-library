<?php

namespace Tsrgtm\MediaLibrary\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Tsrgtm\MediaLibrary\Models\Media;
use Tsrgtm\MediaLibrary\Models\MediaTag;

class MediaStatusController extends Controller
{
    public function __invoke(Media $media): JsonResponse
    {
        $media->loadMissing(['folder', 'tags']);

        return response()->json([
            'id' => $media->getKey(),
            'type' => 'media',
            'uuid' => $media->uuid,
            'name' => $media->original_name,
            'title' => $media->title,
            'status' => $media->status->value,
            'kind' => $media->kind->value,
            'size' => $media->size,
            'mime_type' => $media->mime_type,
            'extension' => $media->extension,
            'width' => $media->width,
            'height' => $media->height,
            'thumbnail_url' => $media->thumbnail_url,
            'preview_url' => $media->preview_url,
            'fallback_url' => $media->kind_placeholder_url,
            'thumbnail_mode' => $media->kind->value === 'image'
                ? 'cover'
                : 'contain',
            'url' => $media->url,
            'folder_id' => $media->folder_id,
            'folder_slug' => $media->folder?->slug,
            'error_message' => $media->error_message,
            'tags' => $media->tags
                ->map(fn (MediaTag $tag): array => [
                    'id' => $tag->getKey(),
                    'name' => $tag->name,
                ])
                ->values(),
            'created_at' => $media->created_at?->toIso8601String(),
            'updated_at' => $media->updated_at?->toIso8601String(),
        ]);
    }
}
