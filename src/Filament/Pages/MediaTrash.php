<?php

namespace Tsrgtm\MediaLibrary\Filament\Pages;

use Tsrgtm\MediaLibrary\Filament\Pages\MediaLibrary\BaseMediaLibraryPage;
use Tsrgtm\MediaLibrary\MediaLibraryPlugin;

class MediaTrash extends BaseMediaLibraryPage
{
    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(): string
    {
        try {
            $baseSlug = MediaLibraryPlugin::get()->getSlug() ?? 'media-library';

            return "{$baseSlug}/trash";
        } catch (\Throwable) {
            return 'media-library/trash';
        }
    }

    public function mount(): void
    {
        $this->folderSlug = null;
        $this->isTrash = true;
    }
}
