<?php

namespace Tsrgtm\MediaLibrary;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Tsrgtm\MediaLibrary\Filament\Pages\MediaFolderLibrary;
use Tsrgtm\MediaLibrary\Filament\Pages\MediaLibrary;
use Tsrgtm\MediaLibrary\Filament\Pages\MediaTrash;
use Tsrgtm\MediaLibrary\Filament\Pages\MediaTrashFolder;

final class MediaLibraryPlugin implements Plugin
{
    public function getId(): string
    {
        return 'tsrgtm-media-library';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            MediaLibrary::class,
            MediaFolderLibrary::class,
            MediaTrash::class,
            MediaTrashFolder::class,
        ]);
    }

    public function boot(Panel $panel): void {}

    public static function make(): self
    {
        return app(self::class);
    }
}
