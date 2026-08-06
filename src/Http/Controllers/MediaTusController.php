<?php

namespace Tsrgtm\MediaLibrary\Http\Controllers;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tsrgtm\MediaLibrary\Models\MediaUploadSession;
use Tsrgtm\MediaLibrary\Services\Media\TusUploadManager;

class MediaTusController extends Controller
{
    public function __construct(
        private readonly TusUploadManager $uploads,
    ) {}

    public function options(): Response
    {
        return response('', Response::HTTP_NO_CONTENT)
            ->withHeaders($this->tusHeaders());
    }

    public function create(Request $request): Response
    {
        $uploadLength = (int) $request->header(
            'Upload-Length',
            0,
        );

        $metadata = $this->uploads->parseMetadata(
            $request->header('Upload-Metadata'),
        );

        try {
            $session = $this->uploads->create(
                $request->user(),
                $uploadLength,
                $metadata,
            );
        } catch (RuntimeException $exception) {
            return $this->error(
                $exception->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $session->load('media');

        return response('', Response::HTTP_CREATED)
            ->header(
                'Location',
                route('media.tus.patch', [
                    'uploadSession' => $session,
                ]),
            )
            ->header('Upload-Offset', '0')
            ->header(
                'Upload-Expires',
                $session->expires_at->toRfc7231String(),
            )
            ->header(
                'Media-Id',
                (string) $session->media_id,
            )
            ->header(
                'Media-Preview-Url',
                $session->media->preview_url,
            )
            ->withHeaders($this->tusHeaders());
    }

    public function head(
        Request $request,
        MediaUploadSession $uploadSession,
    ): Response {
        $this->authorizeSession($request, $uploadSession);

        return response('', Response::HTTP_NO_CONTENT)
            ->header(
                'Upload-Offset',
                (string) $this->uploads->offset($uploadSession),
            )
            ->header(
                'Upload-Length',
                (string) $uploadSession->size,
            )
            ->header(
                'Upload-Expires',
                $uploadSession->expires_at->toRfc7231String(),
            )
            ->header(
                'Media-Id',
                (string) $uploadSession->media_id,
            )
            ->withHeaders($this->tusHeaders());
    }

    public function patch(
        Request $request,
        MediaUploadSession $uploadSession,
    ): Response {
        $this->authorizeSession($request, $uploadSession);

        if (
            $request->header('Content-Type')
            !== 'application/offset+octet-stream'
        ) {
            return $this->error(
                'Invalid tus PATCH content type.',
                Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
            );
        }

        $offset = (int) $request->header(
            'Upload-Offset',
            -1,
        );

        try {
            $newOffset = $this->uploads->append(
                $uploadSession,
                $request,
                $offset,
            );
        } catch (RuntimeException $exception) {
            $status = str_contains(
                $exception->getMessage(),
                'offset mismatch',
            )
                ? Response::HTTP_CONFLICT
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return $this->error(
                $exception->getMessage(),
                $status,
            );
        }

        return response('', Response::HTTP_NO_CONTENT)
            ->header(
                'Upload-Offset',
                (string) $newOffset,
            )
            ->header(
                'Media-Id',
                (string) $uploadSession->media_id,
            )
            ->withHeaders($this->tusHeaders());
    }

    public function destroy(
        Request $request,
        MediaUploadSession $uploadSession,
    ): Response {
        $this->authorizeSession($request, $uploadSession);

        $this->uploads->delete($uploadSession);

        return response('', Response::HTTP_NO_CONTENT)
            ->withHeaders($this->tusHeaders());
    }

    private function authorizeSession(
        Request $request,
        MediaUploadSession $session,
    ): void {
        abort_unless(
            (int) $session->user_id
                === (int) $request->user()?->getAuthIdentifier(),
            Response::HTTP_FORBIDDEN,
        );
    }

    private function tusHeaders(): array
    {
        return [
            'Tus-Resumable' => '1.0.0',
            'Tus-Version' => '1.0.0',
            'Tus-Extension' => 'creation,termination',
            'Tus-Max-Size' => (string) config(
                'media-library.maximum_upload_size',
            ),
            'Cache-Control' => 'no-store',
        ];
    }

    private function error(
        string $message,
        int $status,
    ): Response {
        return response(
            json_encode(
                ['message' => $message],
                JSON_THROW_ON_ERROR,
            ),
            $status,
            [
                ...$this->tusHeaders(),
                'Content-Type' => 'application/json',
            ],
        );
    }
}
