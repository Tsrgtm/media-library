<?php

namespace Tsrgtm\MediaLibrary\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Throwable;

class MediaSqliteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        try {
            $pdo = DB::connection('sqlite')->getPdo();

            $timeout = max(
                1000,
                (int) config(
                    'media-library.sqlite_busy_timeout_ms',
                    30000,
                ),
            );

            $pdo->exec("PRAGMA busy_timeout = {$timeout}");
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->exec('PRAGMA foreign_keys = ON');
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
