<?php

namespace Tsrgtm\MediaLibrary\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Tsrgtm\MediaLibrary\MediaLibraryServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MediaLibraryServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
