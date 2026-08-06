<?php

use Tsrgtm\MediaLibrary\Enums\MediaKind;
use Tsrgtm\MediaLibrary\Models\Media;
use Tsrgtm\MediaLibrary\Models\MediaFolder;
use Tsrgtm\MediaLibrary\Tests\TestCase;

uses(TestCase::class);

it('caches media placeholder url in static memory', function (): void {
    Media::clearPlaceholderCache();

    $media = new Media([
        'extension' => 'pdf',
        'kind' => MediaKind::Document,
    ]);

    $firstCall = $media->placeholder_url;
    $secondCall = $media->placeholder_url;

    expect($firstCall)->toBeString()
        ->and($firstCall)->toBe($secondCall);

    Media::clearPlaceholderCache();
});

it('caches folder thumbnail url in static memory', function (): void {
    MediaFolder::clearThumbnailUrlCache();

    $folder = new MediaFolder([
        'name' => 'Documents',
    ]);

    $firstCall = $folder->thumbnail_url;
    $secondCall = $folder->thumbnail_url;

    expect($firstCall)->toBeString()
        ->and($firstCall)->toBe($secondCall);

    MediaFolder::clearThumbnailUrlCache();
});
