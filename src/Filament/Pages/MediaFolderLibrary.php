<?php

namespace Tsrgtm\MediaLibrary\Filament\Pages;

use Tsrgtm\MediaLibrary\Filament\Pages\MediaLibrary\BaseMediaLibraryPage;
use Tsrgtm\MediaLibrary\Models\MediaFolder;

class MediaFolderLibrary extends BaseMediaLibraryPage
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug =
        'media-library/folder/{folderSlug}';

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
