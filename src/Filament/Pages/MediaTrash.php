<?php

namespace Tsrgtm\MediaLibrary\Filament\Pages;

use Tsrgtm\MediaLibrary\Filament\Pages\MediaLibrary\BaseMediaLibraryPage;

class MediaTrash extends BaseMediaLibraryPage
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'media-library/trash';

    public function mount(): void
    {
        $this->folderSlug = null;
        $this->isTrash = true;
    }
}
