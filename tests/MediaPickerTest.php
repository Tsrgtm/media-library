<?php

use Tsrgtm\MediaLibrary\Filament\Forms\Components\MediaPicker;

it('configures a single image picker', function (): void {
    $picker = MediaPicker::make('featured')
        ->collection('featured')
        ->images();

    expect($picker->getCollection())
        ->toBe('featured')
        ->and($picker->isMultiple())
        ->toBeFalse()
        ->and($picker->getAcceptedKinds())
        ->toBe(['image'])
        ->and($picker->getMaxItems())
        ->toBe(1);
});

it('configures an ordered multiple picker', function (): void {
    $picker = MediaPicker::make('gallery')
        ->collection('gallery')
        ->multiple()
        ->minItems(1)
        ->maxItems(10)
        ->acceptedKinds([
            'image',
            'video',
        ]);

    expect($picker->isMultiple())
        ->toBeTrue()
        ->and($picker->isReorderable())
        ->toBeTrue()
        ->and($picker->getMinItems())
        ->toBe(1)
        ->and($picker->getMaxItems())
        ->toBe(10);
});
