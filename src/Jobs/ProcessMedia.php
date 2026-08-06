<?php

namespace Tsrgtm\MediaLibrary\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;
use Tsrgtm\MediaLibrary\Enums\MediaKind;
use Tsrgtm\MediaLibrary\Enums\MediaStatus;
use Tsrgtm\MediaLibrary\Models\Media;
use Tsrgtm\MediaLibrary\Services\Media\MediaImageProcessor;

class ProcessMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $mediaId) {}

    public function handle(MediaImageProcessor $images): void
    {
        $media = Media::query()->find($this->mediaId);

        if (! $media) {
            return;
        }

        $media->update([
            'status' => MediaStatus::Processing,
            'error_message' => null,
        ]);

        if ($media->kind === MediaKind::Image) {
            $images->process($media);
        }

        $media->refresh()->update([
            'status' => MediaStatus::Ready,
            'error_message' => null,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Media::query()
            ->whereKey($this->mediaId)
            ->update([
                'status' => MediaStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
    }
}
