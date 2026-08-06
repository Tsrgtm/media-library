<?php

return [
    'route_prefix' => env('MEDIA_LIBRARY_ROUTE_PREFIX', 'media'),
    'middleware' => ['web', 'auth'],
    'navigation' => [
        'group' => 'Content',
        'sort' => 20,
        'icon' => 'heroicon-o-photo',
    ],
    'disk' => env('MEDIA_DISK', 'public'),
    'temporary_disk' => env('MEDIA_TEMP_DISK', 'local'),

    'small_file_threshold' => 10 * 1024 * 1024,
    'chunk_size' => 8 * 1024 * 1024,
    'browser_progress_chunk_size' => 2 * 1024 * 1024,
    'maximum_upload_size' => 5 * 1024 * 1024 * 1024,
    'upload_session_lifetime_minutes' => 120,

    'status_check_interval_ms' => 1000,
    'status_check_max_attempts' => 120,
    'page_size' => 48,
    'concurrent_uploads' => 4,
    'sqlite_busy_timeout_ms' => 30000,
    'queue_connection' => env(
        'MEDIA_QUEUE_CONNECTION',
        env('DB_CONNECTION') === 'sqlite'
            ? 'sync'
            : env('QUEUE_CONNECTION', 'database'),
    ),

    'picker' => [
        'page_size' => 40,
    ],

    'image' => [
        'quality' => 82,

        /*
        | The source is used directly for full preview.
        | A conversion is created only when the source is wider than
        | the configured target width.
        */
        'conversions' => [
            'thumbnail' => 320,
            'small' => 640,
            'medium' => 1024,
            'large' => 1600,
        ],
    ],

    'placeholders' => [
        'extensions' => [
            // Images
            'jpg' => '/images/media-placeholders/jpg.svg',
            'jpeg' => '/images/media-placeholders/jpeg.svg',
            'png' => '/images/media-placeholders/png.svg',
            'webp' => '/images/media-placeholders/webp.svg',
            'avif' => '/images/media-placeholders/avif.svg',
            'gif' => '/images/media-placeholders/gif.svg',
            'svg' => '/images/media-placeholders/svg.svg',
            'bmp' => '/images/media-placeholders/bmp.svg',
            'tiff' => '/images/media-placeholders/tiff.svg',
            'ico' => '/images/media-placeholders/ico.svg',
            'heic' => '/images/media-placeholders/heic.svg',

            // Videos
            'mp4' => '/images/media-placeholders/mp4.svg',
            'mov' => '/images/media-placeholders/mov.svg',
            'mkv' => '/images/media-placeholders/mkv.svg',
            'webm' => '/images/media-placeholders/webm.svg',
            'avi' => '/images/media-placeholders/avi.svg',
            'flv' => '/images/media-placeholders/flv.svg',
            'wmv' => '/images/media-placeholders/wmv.svg',
            'm4v' => '/images/media-placeholders/m4v.svg',

            // Audio
            'mp3' => '/images/media-placeholders/mp3.svg',
            'wav' => '/images/media-placeholders/wav.svg',
            'flac' => '/images/media-placeholders/flac.svg',
            'aac' => '/images/media-placeholders/aac.svg',
            'ogg' => '/images/media-placeholders/ogg.svg',
            'm4a' => '/images/media-placeholders/m4a.svg',
            'wma' => '/images/media-placeholders/wma.svg',

            // Documents & Office
            'pdf' => '/images/media-placeholders/pdf.svg',
            'doc' => '/images/media-placeholders/doc.svg',
            'docx' => '/images/media-placeholders/docx.svg',
            'xls' => '/images/media-placeholders/xls.svg',
            'xlsx' => '/images/media-placeholders/xlsx.svg',
            'ppt' => '/images/media-placeholders/ppt.svg',
            'pptx' => '/images/media-placeholders/pptx.svg',
            'txt' => '/images/media-placeholders/txt.svg',
            'csv' => '/images/media-placeholders/csv.svg',
            'rtf' => '/images/media-placeholders/rtf.svg',
            'odt' => '/images/media-placeholders/odt.svg',
            'ods' => '/images/media-placeholders/ods.svg',
            'odp' => '/images/media-placeholders/odp.svg',
            'epub' => '/images/media-placeholders/epub.svg',

            // Archives
            'zip' => '/images/media-placeholders/zip.svg',
            'rar' => '/images/media-placeholders/rar.svg',
            '7z' => '/images/media-placeholders/7z.svg',
            'tar' => '/images/media-placeholders/tar.svg',
            'gz' => '/images/media-placeholders/gz.svg',
            'iso' => '/images/media-placeholders/iso.svg',
            'bz2' => '/images/media-placeholders/bz2.svg',
            'xz' => '/images/media-placeholders/xz.svg',

            // Code & Dev
            'php' => '/images/media-placeholders/php.svg',
            'js' => '/images/media-placeholders/js.svg',
            'ts' => '/images/media-placeholders/ts.svg',
            'jsx' => '/images/media-placeholders/jsx.svg',
            'tsx' => '/images/media-placeholders/tsx.svg',
            'vue' => '/images/media-placeholders/vue.svg',
            'blade' => '/images/media-placeholders/blade.svg',
            'css' => '/images/media-placeholders/css.svg',
            'html' => '/images/media-placeholders/html.svg',
            'json' => '/images/media-placeholders/json.svg',
            'sql' => '/images/media-placeholders/sql.svg',
            'sh' => '/images/media-placeholders/sh.svg',
            'yaml' => '/images/media-placeholders/yaml.svg',
            'yml' => '/images/media-placeholders/yml.svg',
            'xml' => '/images/media-placeholders/xml.svg',

            // Fonts
            'ttf' => '/images/media-placeholders/ttf.svg',
            'woff' => '/images/media-placeholders/woff.svg',
            'woff2' => '/images/media-placeholders/woff2.svg',
            'otf' => '/images/media-placeholders/otf.svg',

            // Executables
            'exe' => '/images/media-placeholders/exe.svg',
            'dmg' => '/images/media-placeholders/dmg.svg',
            'apk' => '/images/media-placeholders/apk.svg',
        ],

        'kinds' => [
            'image' => '/images/media-placeholders/image.svg',
            'video' => '/images/media-placeholders/video.svg',
            'audio' => '/images/media-placeholders/audio.svg',
            'document' => '/images/media-placeholders/document.svg',
            'archive' => '/images/media-placeholders/archive.svg',
            'code' => '/images/media-placeholders/code.svg',
            'spreadsheet' => '/images/media-placeholders/spreadsheet.svg',
            'presentation' => '/images/media-placeholders/presentation.svg',
            'font' => '/images/media-placeholders/font.svg',
            'database' => '/images/media-placeholders/database.svg',
            'executable' => '/images/media-placeholders/executable.svg',
            'file' => '/images/media-placeholders/file.svg',
            'folder' => '/images/media-placeholders/folder.svg',
        ],

        'default' => '/images/media-placeholders/file.svg',
    ],
];
