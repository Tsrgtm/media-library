<?php

namespace Tsrgtm\MediaLibrary\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class UpdateMediaLibraryCommand extends Command
{
    protected $signature = 'media-library:update
        {--force : Force overwrite all published assets and configs}';

    protected $description = 'Update Tsrgtm Media Library assets, sync configuration changes, and run pending migrations';

    public function handle(Filesystem $files): int
    {
        $this->components->info('Updating tsrgtm/media-library…');

        $force = (bool) $this->option('force');

        $this->call('vendor:publish', [
            '--tag' => 'media-library-config',
            '--force' => $force,
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'media-library-assets',
            '--force' => true,
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'media-library-migrations',
            '--force' => false,
        ]);

        $this->syncFrontendSourceFiles($files, $force);

        $this->components->task('Running database migrations', function (): void {
            try {
                $this->callSilent('migrate', ['--force' => true]);
            } catch (\Throwable) {
                // Migrations already applied or present
            }
        });

        $this->call('optimize:clear');

        $this->newLine();
        $this->components->info('Media Library updated successfully.');

        return self::SUCCESS;
    }

    private function syncFrontendSourceFiles(Filesystem $files, bool $force): void
    {
        $source = dirname(__DIR__, 2).'/resources';
        $targets = [
            $source.'/js/media-library.js' => resource_path('js/vendor/tsrgtm-media-library/media-library.js'),
            $source.'/js/media-preview.js' => resource_path('js/vendor/tsrgtm-media-library/media-preview.js'),
            $source.'/js/media-picker.js' => resource_path('js/vendor/tsrgtm-media-library/media-picker.js'),
            $source.'/css/media-library.css' => resource_path('css/vendor/tsrgtm-media-library/media-library.css'),
        ];

        foreach ($targets as $from => $to) {
            if (! $files->exists($from)) {
                continue;
            }

            $needsUpdate = ! $files->exists($to) || $force || sha1_file($from) !== sha1_file($to);

            if ($needsUpdate) {
                $files->ensureDirectoryExists(dirname($to));
                $files->copy($from, $to);
                $this->components->task("Updated {$to}");
            } else {
                $this->components->task("Up to date: {$to}");
            }
        }
    }
}
