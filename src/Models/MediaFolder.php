<?php

namespace Tsrgtm\MediaLibrary\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaFolder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
    ];

    public function parent(): BelongsTo
    {
        return $this
            ->belongsTo(self::class, 'parent_id')
            ->withTrashed();
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function childrenWithTrashed(): HasMany
    {
        return $this
            ->hasMany(self::class, 'parent_id')
            ->withTrashed();
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }

    public function mediaWithTrashed(): HasMany
    {
        return $this
            ->hasMany(Media::class, 'folder_id')
            ->withTrashed();
    }

    protected static ?string $thumbnailUrlCache = null;

    public static function clearThumbnailUrlCache(): void
    {
        static::$thumbnailUrlCache = null;
    }

    public function getThumbnailUrlAttribute(): string
    {
        if (static::$thumbnailUrlCache !== null) {
            return static::$thumbnailUrlCache;
        }

        $kinds = (array) config(
            'media-library.placeholders.kinds',
            [],
        );

        $path = $kinds['folder']
            ?? '/images/media-placeholders/folder.png';

        return static::$thumbnailUrlCache = asset(ltrim((string) $path, '/'));
    }
}
