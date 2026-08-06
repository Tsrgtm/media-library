<?php

namespace Tsrgtm\MediaLibrary\Enums;

enum MediaKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Archive = 'archive';
    case File = 'file';

    public static function fromMimeType(?string $mimeType): self
    {
        $mimeType = strtolower((string) $mimeType);

        return match (true) {
            str_starts_with($mimeType, 'image/') => self::Image,
            str_starts_with($mimeType, 'video/') => self::Video,
            str_starts_with($mimeType, 'audio/') => self::Audio,
            in_array($mimeType, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'text/csv',
            ], true) => self::Document,
            in_array($mimeType, [
                'application/zip',
                'application/x-7z-compressed',
                'application/x-rar-compressed',
                'application/gzip',
            ], true) => self::Archive,
            default => self::File,
        };
    }
}
