<?php

use Tsrgtm\MediaLibrary\MediaLibraryPlugin;

it('creates the filament plugin', function (): void {
    expect(MediaLibraryPlugin::make()->getId())
        ->toBe('tsrgtm-media-library');
});
