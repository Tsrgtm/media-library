<?php

namespace Tsrgtm\MediaLibrary\Models;

use Illuminate\Database\Eloquent\Model;
use Tsrgtm\MediaLibrary\Concerns\HasMedia;

class Article extends Model
{
    use HasMedia;
}

// Usage:
// $article->replaceMedia($media, 'featured_image');
// $article->attachMedia($media, 'gallery');
// $article->getFirstMedia('featured_image');
// $article->getMedia('gallery');
