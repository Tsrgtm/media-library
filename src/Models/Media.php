<?php

namespace Tsrgtm\MediaLibrary\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Tsrgtm\MediaLibrary\Enums\MediaKind;
use Tsrgtm\MediaLibrary\Enums\MediaStatus;

class Media extends Model
{
    use SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'uuid',
        'folder_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'file_name',
        'mime_type',
        'extension',
        'kind',
        'status',
        'size',
        'width',
        'height',
        'duration',
        'title',
        'alt',
        'caption',
        'checksum',
        'error_message',
        'metadata',
        'responsive_images',
    ];

    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
            'status' => MediaStatus::class,
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
            'metadata' => 'array',
            'responsive_images' => 'array',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MediaTag::class, 'media_tag');
    }

    public function getUrlAttribute(): ?string
    {
        return filled($this->uuid)
            ? route('media.files.show', ['media' => $this->uuid])
            : null;
    }

    protected static array $placeholderCache = [];

    protected static array $kindPlaceholderCache = [];

    public static function clearPlaceholderCache(): void
    {
        static::$placeholderCache = [];
        static::$kindPlaceholderCache = [];
    }

    public function getPlaceholderUrlAttribute(): string
    {
        $extension = strtolower(ltrim(
            (string) (
                $this->extension
                ?: pathinfo(
                    (string) $this->original_name,
                    PATHINFO_EXTENSION,
                )
            ),
            '.',
        ));

        $kind = $this->kind instanceof MediaKind
            ? $this->kind->value
            : strtolower((string) $this->kind);

        $cacheKey = "{$extension}:{$kind}";

        if (isset(static::$placeholderCache[$cacheKey])) {
            return static::$placeholderCache[$cacheKey];
        }

        $extensionPlaceholders = (array) config(
            'media-library.placeholders.extensions',
            [],
        );

        $kindPlaceholders = (array) config(
            'media-library.placeholders.kinds',
            [],
        );

        $placeholder = $extensionPlaceholders[$extension]
            ?? $kindPlaceholders[$kind]
            ?? config(
                'media-library.placeholders.default',
                '/images/media-placeholders/file.svg',
            );

        return static::$placeholderCache[$cacheKey] = asset(ltrim((string) $placeholder, '/'));
    }

    public function getKindPlaceholderUrlAttribute(): string
    {
        $kind = $this->kind instanceof MediaKind
            ? $this->kind->value
            : strtolower((string) $this->kind);

        if (isset(static::$kindPlaceholderCache[$kind])) {
            return static::$kindPlaceholderCache[$kind];
        }

        $placeholder = (array) config(
            'media-library.placeholders.kinds',
            [],
        );

        $path = $placeholder[$kind]
            ?? config(
                'media-library.placeholders.default',
                '/images/media-placeholders/file.png',
            );

        return static::$kindPlaceholderCache[$kind] = asset(ltrim((string) $path, '/'));
    }

    public function getThumbnailUrlAttribute(): string
    {
        if ($this->kind !== MediaKind::Image) {
            return $this->placeholder_url;
        }

        return $this->conversionUrl('thumbnail')
            ?? $this->url
            ?? $this->kind_placeholder_url;
    }

    public function getPreviewUrlAttribute(): string
    {
        if ($this->kind !== MediaKind::Image) {
            return $this->placeholder_url;
        }

        // Full previews use the processed source directly.
        return $this->url
            ?? $this->kind_placeholder_url;
    }

    public function conversionUrl(
        string $conversion,
        bool $fallbackToOriginal = false,
    ): ?string {
        if ($this->trashed()) {
            return null;
        }

        $path = data_get(
            $this->responsive_images,
            "{$conversion}.path",
        );

        if (filled($path)) {
            return route('media.files.variant', [
                'media' => $this->uuid,
                'variant' => $conversion,
            ]);
        }

        return $fallbackToOriginal
            ? $this->url
            : null;
    }

    public function responsiveUrl(string $variant): ?string
    {
        return $this->conversionUrl(
            conversion: $variant,
            fallbackToOriginal: true,
        );
    }

    protected static function booted(): void
    {
        static::forceDeleted(function (Media $media): void {
            Storage::disk($media->disk)->deleteDirectory(
                "media/{$media->uuid}",
            );
        });
    }
}
