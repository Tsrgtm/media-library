<?php

namespace Tsrgtm\MediaLibrary;

use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Tsrgtm\MediaLibrary\Filament\Pages\MediaFolderLibrary;
use Tsrgtm\MediaLibrary\Filament\Pages\MediaLibrary;
use Tsrgtm\MediaLibrary\Filament\Pages\MediaTrash;
use Tsrgtm\MediaLibrary\Filament\Pages\MediaTrashFolder;
use UnitEnum;

final class MediaLibraryPlugin implements Plugin
{
    protected string|UnitEnum|null $navigationGroup = 'Content';

    protected string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected ?string $navigationLabel = 'Media Library';

    protected ?int $navigationSort = 20;

    protected ?string $slug = 'media-library';

    protected bool|Closure $shouldRegisterNavigation = true;

    public function getId(): string
    {
        return 'tsrgtm-media-library';
    }

    public function navigationGroup(string|UnitEnum|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->navigationGroup;
    }

    public function navigationIcon(string|BackedEnum|null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function getNavigationIcon(): string|BackedEnum|null
    {
        return $this->navigationIcon;
    }

    public function navigationLabel(?string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function slug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function shouldRegisterNavigation(bool|Closure $condition = true): static
    {
        $this->shouldRegisterNavigation = $condition;

        return $this;
    }

    public function isNavigationRegistered(): bool
    {
        return (bool) value($this->shouldRegisterNavigation);
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

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament('tsrgtm-media-library');

        return $plugin;
    }
}
