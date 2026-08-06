<?php

use Tsrgtm\MediaLibrary\Tests\TestCase;

uses(TestCase::class);

it('executes install command successfully', function (): void {
    $this->artisan('media-library:install', [
        '--no-npm' => true,
        '--force' => true,
    ])->assertSuccessful();
});

it('executes update command successfully', function (): void {
    $this->artisan('media-library:update', [
        '--force' => true,
    ])->assertSuccessful();
});

it('executes uninstall command cleanly', function (): void {
    $this->artisan('media-library:uninstall', [
        '--force' => true,
    ])->assertSuccessful();
});
