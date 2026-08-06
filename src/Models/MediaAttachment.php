<?php

namespace Tsrgtm\MediaLibrary\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

class MediaAttachment extends MorphPivot
{
    protected $table = 'mediables';

    public $incrementing = true;

    protected $fillable = [
        'media_id',
        'mediable_type',
        'mediable_id',
        'collection',
        'sort_order',
        'custom_properties',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'custom_properties' => 'array',
        ];
    }
}
