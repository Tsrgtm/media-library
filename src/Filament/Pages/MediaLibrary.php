<?php

namespace Tsrgtm\MediaLibrary\Filament\Pages;

use BackedEnum;
use Filament\Panel;
use Tsrgtm\MediaLibrary\Filament\Pages\MediaLibrary\BaseMediaLibraryPage;
use Tsrgtm\MediaLibrary\MediaLibraryPlugin;
use UnitEnum;

class MediaLibrary extends BaseMediaLibraryPage
{
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        try {
            return MediaLibraryPlugin::get()->getNavigationGroup();
        } catch (\Throwable) {
            return 'Content';
        }
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        try {
            return MediaLibraryPlugin::get()->getNavigationIcon();
        } catch (\Throwable) {
            return parent::getNavigationIcon();
        }
    }

    public static function getNavigationLabel(): string
    {
        try {
            return MediaLibraryPlugin::get()->getNavigationLabel() ?? 'Media Library';
        } catch (\Throwable) {
            return 'Media Library';
        }
    }

    public static function getNavigationSort(): ?int
    {
        try {
            return MediaLibraryPlugin::get()->getNavigationSort();
        } catch (\Throwable) {
            return 20;
        }
    }

    public static function getSlug(?Panel $panel = null): string
    {
        try {
            return MediaLibraryPlugin::get()->getSlug() ?? 'media-library';
        } catch (\Throwable) {
            return 'media-library';
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return MediaLibraryPlugin::get()->isNavigationRegistered();
        } catch (\Throwable) {
            return true;
        }
    }
}
