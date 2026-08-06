<?php

namespace Tsrgtm\MediaLibrary\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UninstallMediaLibraryCommand extends Command
{
    protected $signature = 'media-library:uninstall
        {--force : Force uninstall without interactive prompts}
        {--keep-data : Preserve database tables and config file}';

    protected $description = 'Revert changes made by Tsrgtm Media Library, clean published assets, and remove plugin registration safely';

    public function handle(Filesystem $files): int
    {
        if (
            ! $this->option('force')
            && ! $this->confirm('Are you sure you want to uninstall Tsrgtm Media Library and revert published files?', false)
        ) {
            $this->components->info('Uninstall cancelled.');

            return self::SUCCESS;
        }

        $this->components->info('Uninstalling tsrgtm/media-library…');

        $this->unpatchPanelProvider($files);
        $this->unpatchAppJs($files);
        $this->unpatchThemeCss($files);
        $this->cleanPublishedAssets($files);

        if (! $this->option('keep-data')) {
            $this->cleanDatabaseTables();
            $this->cleanConfigFile($files);
        } else {
            $this->components->info('Preserved database tables and config file.');
        }

        $this->call('optimize:clear');

        $this->newLine();
        $this->components->info('Media Library changes reverted cleanly.');
        $this->line('To complete removal, run: composer remove tsrgtm/media-library');

        return self::SUCCESS;
    }

    private function unpatchPanelProvider(Filesystem $files): void
    {
        $paths = [
            base_path('app/Providers/Filament/AdminPanelProvider.php'),
        ];

        foreach ($paths as $path) {
            if (! $files->exists($path)) {
                continue;
            }

            $content = $files->get($path);
            $original = $content;

            $content = Str::replace("use Tsrgtm\\MediaLibrary\\MediaLibraryPlugin;\n", '', $content);
            $content = Str::replace('use Tsrgtm\\MediaLibrary\\MediaLibraryPlugin;', '', $content);
            $content = Str::replace("->plugin(MediaLibraryPlugin::make())\n", '', $content);
            $content = Str::replace('->plugin(MediaLibraryPlugin::make())', '', $content);
            $content = Str::replace("MediaLibraryPlugin::make(),\n", '', $content);
            $content = Str::replace('MediaLibraryPlugin::make(),', '', $content);

            if ($content !== $original) {
                $files->put($path, $content);
                $this->components->task("Reverted Filament Panel Provider ({$path})");
            }
        }
    }

    private function unpatchAppJs(Filesystem $files): void
    {
        $path = resource_path('js/app.js');

        if (! $files->exists($path)) {
            return;
        }

        $content = $files->get($path);
        $original = $content;

        $linesToRemove = [
            "import { mediaDrive } from './vendor/tsrgtm-media-library/media-library';",
            "import { mediaPicker } from './vendor/tsrgtm-media-library/media-picker';",
            'window.mediaDrive = mediaDrive;',
            'window.mediaPicker = mediaPicker;',
        ];

        foreach ($linesToRemove as $line) {
            $content = Str::replace($line, '', $content);
        }

        $content = preg_replace("/\n\s*\n\s*\n/", "\n\n", $content);

        if ($content !== $original) {
            $files->put($path, trim((string) $content)."\n");
            $this->components->task('Reverted resources/js/app.js');
        }
    }

    private function unpatchThemeCss(Filesystem $files): void
    {
        $candidates = [
            resource_path('css/filament/admin/theme.css'),
            resource_path('css/filament/theme.css'),
        ];

        foreach ($candidates as $path) {
            if (! $files->exists($path)) {
                continue;
            }

            $content = $files->get($path);
            $original = $content;

            $linesToRemove = [
                '@import "../../vendor/tsrgtm-media-library/media-library.css";',
                '@import "../vendor/tsrgtm-media-library/media-library.css";',
                "@source '../../../../vendor/tsrgtm/media-library/resources/views/**/*.blade.php';",
                "@source '../../../../vendor/tsrgtm/media-library/src/**/*.php';",
                "@source '../../../../vendor/tsrgtm/media-library/resources/js/**/*.js';",
            ];

            foreach ($linesToRemove as $line) {
                $content = Str::replace($line, '', $content);
            }

            if ($content !== $original) {
                $files->put($path, trim((string) $content)."\n");
                $this->components->task("Reverted {$path}");
            }
        }
    }

    private function cleanPublishedAssets(Filesystem $files): void
    {
        $directories = [
            resource_path('js/vendor/tsrgtm-media-library'),
            resource_path('css/vendor/tsrgtm-media-library'),
            public_path('css/vendor/media-library'),
            public_path('images/media-placeholders'),
        ];

        foreach ($directories as $dir) {
            if ($files->exists($dir)) {
                $files->deleteDirectory($dir);
                $this->components->task("Deleted directory: {$dir}");
            }
        }
    }

    private function cleanConfigFile(Filesystem $files): void
    {
        $path = config_path('media-library.php');

        if ($files->exists($path)) {
            $files->delete($path);
            $this->components->task('Deleted config/media-library.php');
        }
    }

    private function cleanDatabaseTables(): void
    {
        $tables = [
            'media_upload_sessions',
            'mediables',
            'media_tag',
            'media_tags',
            'media',
            'media_folders',
        ];

        Schema::disableForeignKeyConstraints();

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
                $this->components->task("Dropped database table: {$table}");
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}
