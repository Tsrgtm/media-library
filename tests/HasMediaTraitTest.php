<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tsrgtm\MediaLibrary\Concerns\HasMedia;
use Tsrgtm\MediaLibrary\Enums\MediaKind;
use Tsrgtm\MediaLibrary\Enums\MediaStatus;
use Tsrgtm\MediaLibrary\Models\Media;
use Tsrgtm\MediaLibrary\Tests\TestCase;

uses(TestCase::class);

class TestPost extends Model
{
    use HasMedia;

    protected $table = 'test_posts';

    protected $guarded = [];
}

beforeEach(function (): void {
    Schema::create('test_posts', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });
});

it('attaches, retrieves, syncs, and clears media on Eloquent models', function (): void {
    $post = TestPost::create(['title' => 'Sample Article']);

    $media1 = Media::create([
        'uuid' => '11111111-1111-1111-1111-111111111111',
        'disk' => 'public',
        'original_name' => 'image1.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'kind' => MediaKind::Image,
        'status' => MediaStatus::Ready,
    ]);

    $media2 = Media::create([
        'uuid' => '22222222-2222-2222-2222-222222222222',
        'disk' => 'public',
        'original_name' => 'image2.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'kind' => MediaKind::Image,
        'status' => MediaStatus::Ready,
    ]);

    $post->attachMedia($media1, 'gallery');
    expect($post->getMedia('gallery'))->toHaveCount(1)
        ->and($post->getFirstMedia('gallery')->id)->toBe($media1->id);

    $post->syncMedia([$media1->id, $media2->id], 'gallery');
    expect($post->getMedia('gallery'))->toHaveCount(2);

    $post->replaceMedia($media2, 'featured');
    expect($post->getFirstMedia('featured')->id)->toBe($media2->id);

    $post->clearMediaCollection('gallery');
    expect($post->getMedia('gallery'))->toHaveCount(0);
});
