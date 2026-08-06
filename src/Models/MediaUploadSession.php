<?php

namespace Tsrgtm\MediaLibrary\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaUploadSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'media_id',
        'user_id',
        'size',
        'chunk_size',
        'total_chunks',
        'temporary_directory',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'chunk_size' => 'integer',
            'total_chunks' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
