<?php

use Filament\Support\Icons\Heroicon;
use Tsrgtm\MediaLibrary\MediaLibraryPlugin;
use Tsrgtm\MediaLibrary\Tests\TestCase;

uses(TestCase::class);

it('allows customizing navigation group, icon, label, sort, and slug on plugin', function (): void {
    $plugin = MediaLibraryPlugin::make()
        ->navigationGroup('Assets Management')
        ->navigationIcon(Heroicon::OutlinedFolder)
        ->navigationLabel('Media Drive')
        ->navigationSort(5)
        ->slug('media-drive')
        ->shouldRegisterNavigation(true);

    expect($plugin->getNavigationGroup())->toBe('Assets Management')
        ->and($plugin->getNavigationIcon())->toBe(Heroicon::OutlinedFolder)
        ->and($plugin->getNavigationLabel())->toBe('Media Drive')
        ->and($plugin->getNavigationSort())->toBe(5)
        ->and($plugin->getSlug())->toBe('media-drive')
        ->and($plugin->isNavigationRegistered())->toBeTrue();
});
