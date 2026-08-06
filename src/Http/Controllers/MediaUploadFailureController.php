<?php

namespace Tsrgtm\MediaLibrary\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Tsrgtm\MediaLibrary\Models\Media;
use Tsrgtm\MediaLibrary\Models\MediaUploadSession;

class MediaUploadFailureController extends Controller
{
    public function __invoke(Media $media): JsonResponse
    {
        MediaUploadSession::query()
            ->where('media_id', $media->getKey())
            ->delete();

        $media->forceDelete();

        return response()->json([
            'deleted' => true,
        ]);
    }
}
