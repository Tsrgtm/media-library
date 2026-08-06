<?php

use Illuminate\Support\Facades\Route;
use Tsrgtm\MediaLibrary\Http\Controllers\MediaArchiveController;
use Tsrgtm\MediaLibrary\Http\Controllers\MediaFileController;
use Tsrgtm\MediaLibrary\Http\Controllers\MediaLibraryController;
use Tsrgtm\MediaLibrary\Http\Controllers\MediaPickerController;
use Tsrgtm\MediaLibrary\Http\Controllers\MediaStatusController;
use Tsrgtm\MediaLibrary\Http\Controllers\MediaTusController;
use Tsrgtm\MediaLibrary\Http\Controllers\MediaUploadFailureController;

Route::middleware(config('media-library.middleware', ['web', 'auth']))->group(function (): void {
    Route::prefix(trim((string) config('media-library.route_prefix', 'media'), '/').'/tus')
        ->name('media.tus.')
        ->group(function (): void {
            Route::options('/', [MediaTusController::class, 'options'])
                ->name('options');

            Route::post('/', [MediaTusController::class, 'create'])
                ->name('create');

            Route::match(
                ['HEAD'],
                '/{uploadSession}',
                [MediaTusController::class, 'head'],
            )->name('head');

            Route::patch(
                '/{uploadSession}',
                [MediaTusController::class, 'patch'],
            )->name('patch');

            Route::delete(
                '/{uploadSession}',
                [MediaTusController::class, 'destroy'],
            )->name('destroy');
        });

    Route::get(
        '/'.trim((string) config('media-library.route_prefix', 'media'), '/').'/{media}/status',
        MediaStatusController::class,
    )->name('media.status');

    Route::get(
        '/'.trim((string) config('media-library.route_prefix', 'media'), '/').'/library/files/{media:uuid}',
        [MediaFileController::class, 'adminShow'],
    )
        ->withTrashed()
        ->name('media.library.files.show');

    Route::get(
        '/'.trim((string) config('media-library.route_prefix', 'media'), '/').'/library/files/{media:uuid}/{variant}',
        [MediaFileController::class, 'adminVariant'],
    )
        ->withTrashed()
        ->name('media.library.files.variant');

    Route::prefix(trim((string) config('media-library.route_prefix', 'media'), '/').'/library')
        ->name('media.library.')
        ->group(function (): void {
            Route::get('/', [MediaLibraryController::class, 'index'])
                ->name('index');

            Route::get(
                '/picker/browse',
                [MediaPickerController::class, 'browse'],
            )->name('picker.browse');

            Route::post(
                '/picker/resolve',
                [MediaPickerController::class, 'resolve'],
            )->name('picker.resolve');

            Route::post(
                '/folders',
                [MediaLibraryController::class, 'createFolder'],
            )->name('folders.create');

            Route::post(
                '/rename',
                [MediaLibraryController::class, 'rename'],
            )->name('rename');

            Route::post(
                '/move',
                [MediaLibraryController::class, 'move'],
            )->name('move');

            Route::post(
                '/trash',
                [MediaLibraryController::class, 'trash'],
            )->name('trash');

            Route::post(
                '/restore',
                [MediaLibraryController::class, 'restore'],
            )->name('restore');

            Route::post(
                '/force-delete',
                [MediaLibraryController::class, 'forceDelete'],
            )->name('force-delete');

            Route::post(
                '/empty-trash',
                [MediaLibraryController::class, 'emptyTrash'],
            )->name('empty-trash');

            Route::get(
                '/tags',
                [MediaLibraryController::class, 'tags'],
            )->name('tags');

            Route::post(
                '/tags',
                [MediaLibraryController::class, 'syncTags'],
            )->name('tags.sync');

            Route::post(
                '/download',
                MediaArchiveController::class,
            )->name('download');

            Route::delete(
                '/upload-failures/{media}',
                MediaUploadFailureController::class,
            )->name('upload-failures.destroy');

            Route::get(
                '/{media}',
                [MediaLibraryController::class, 'show'],
            )->name('show');
        });
});

Route::get(
    '/'.trim((string) config('media-library.route_prefix', 'media'), '/').'/files/{media:uuid}',
    [MediaFileController::class, 'show'],
)->name('media.files.show');

Route::get(
    '/'.trim((string) config('media-library.route_prefix', 'media'), '/').'/files/{media:uuid}/{variant}',
    [MediaFileController::class, 'variant'],
)->name('media.files.variant');
