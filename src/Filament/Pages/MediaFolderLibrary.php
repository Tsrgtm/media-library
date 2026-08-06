<?php

namespace Tsrgtm\MediaLibrary\Filament\Pages;

use Tsrgtm\MediaLibrary\Filament\Pages\MediaLibrary\BaseMediaLibraryPage;
use Tsrgtm\MediaLibrary\MediaLibraryPlugin;
use Tsrgtm\MediaLibrary\Models\MediaFolder;

class MediaFolderLibrary extends BaseMediaLibraryPage
{
    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(): string
    {
        try {
            $baseSlug = MediaLibraryPlugin::get()->getSlug() ?? 'media-library';

            return "{$baseSlug}/folder/{folderSlug}";
        } catch (\Throwable) {
            return 'media-library/folder/{folderSlug}';
        }
    }

    public function mount(string $folderSlug): void
    {
        abort_unless(
            MediaFolder::query()
                ->where('slug', $folderSlug)
                ->exists(),
            404,
        );

        $this->folderSlug = $folderSlug;
        $this->isTrash = false;
    }
}
