<?php

namespace Tsrgtm\MediaLibrary\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tsrgtm\MediaLibrary\Models\Media;
use Tsrgtm\MediaLibrary\Models\MediaAttachment;

trait HasMedia
{
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', 'mediables')
            ->using(MediaAttachment::class)
            ->withPivot(['id', 'collection', 'sort_order', 'custom_properties'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function mediaCollection(string $collection = 'default'): MorphToMany
    {
        return $this->media()->wherePivot('collection', $collection);
    }

    public function getMedia(string $collection = 'default'): Collection
    {
        return $this->mediaCollection($collection)->get();
    }

    public function getFirstMedia(string $collection = 'default'): ?Media
    {
        return $this->mediaCollection($collection)->first();
    }

    public function attachMedia(
        Media|int $media,
        string $collection = 'default',
        array $customProperties = [],
        ?int $sortOrder = null,
    ): void {
        $mediaId = $media instanceof Media ? $media->getKey() : $media;

        $sortOrder ??= ((int) $this->mediaCollection($collection)
            ->max('mediables.sort_order')) + 1;

        DB::table('mediables')->updateOrInsert(
            [
                'media_id' => $mediaId,
                'mediable_type' => $this->getMorphClass(),
                'mediable_id' => $this->getKey(),
                'collection' => $collection,
            ],
            [
                'sort_order' => $sortOrder,
                'custom_properties' => json_encode($customProperties, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->unsetRelation('media');
    }

    public function replaceMedia(
        Media|int $media,
        string $collection,
        array $customProperties = [],
    ): void {
        DB::transaction(function () use ($media, $collection, $customProperties): void {
            $this->clearMediaCollection($collection);
            $this->attachMedia($media, $collection, $customProperties, 0);
        });
    }

    public function detachMedia(
        Media|int $media,
        ?string $collection = null,
    ): void {
        $mediaId = $media instanceof Media
            ? $media->getKey()
            : $media;

        $query = DB::table('mediables')
            ->where('media_id', $mediaId)
            ->where(
                'mediable_type',
                $this->getMorphClass(),
            )
            ->where(
                'mediable_id',
                $this->getKey(),
            );

        if (filled($collection)) {
            $query->where(
                'collection',
                $collection,
            );
        }

        $query->delete();
        $this->unsetRelation('media');
    }

    public function syncMedia(
        array $mediaIds,
        string $collection = 'default',
        array $customProperties = [],
    ): void {
        DB::transaction(function () use (
            $mediaIds,
            $collection,
            $customProperties,
        ): void {
            $this->clearMediaCollection($collection);

            foreach (
                array_values(
                    array_unique(
                        array_map('intval', $mediaIds),
                    ),
                ) as $index => $mediaId
            ) {
                $this->attachMedia(
                    media: $mediaId,
                    collection: $collection,
                    customProperties: $customProperties,
                    sortOrder: $index,
                );
            }
        });
    }

    public function clearMediaCollection(string $collection = 'default'): void
    {
        DB::table('mediables')
            ->where('mediable_type', $this->getMorphClass())
            ->where('mediable_id', $this->getKey())
            ->where('collection', $collection)
            ->delete();

        $this->unsetRelation('media');
    }
}
