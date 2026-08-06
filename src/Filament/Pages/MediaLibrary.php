<?php

namespace Tsrgtm\MediaLibrary\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Tsrgtm\MediaLibrary\Filament\Pages\MediaLibrary\BaseMediaLibraryPage;
use UnitEnum;

class MediaLibrary extends BaseMediaLibraryPage
{
    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup =
        'Content';

    protected static ?string $navigationLabel =
        'Media Library';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'media-library';
}
