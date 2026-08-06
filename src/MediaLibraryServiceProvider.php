<?php

namespace Tsrgtm\MediaLibrary;

use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tsrgtm\MediaLibrary\Commands\InstallMediaLibraryCommand;
use Tsrgtm\MediaLibrary\Providers\MediaSqliteServiceProvider;

class MediaLibraryServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('media-library')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoutes('media')
            ->hasMigrations([
                '2026_08_05_000001_create_media_folders_table',
                '2026_08_05_000002_create_media_table',
                '2026_08_05_000003_create_media_tags_table',
                '2026_08_05_000004_create_mediables_table',
                '2026_08_05_000005_create_media_upload_sessions_table',
                '2026_08_06_000006_add_soft_deletes_to_media_folders_table',
            ])
            ->hasCommand(InstallMediaLibraryCommand::class);
    }

    public function packageBooted(): void
    {
        Blade::anonymousComponentPath(
            __DIR__.'/../resources/views/components',
            'media-library',
        );

        $this->app->register(MediaSqliteServiceProvider::class);

        $this->publishes([
            __DIR__.'/../resources/images/media-placeholders' => public_path('images/media-placeholders'),
        ], 'media-library-assets');
    }
}
