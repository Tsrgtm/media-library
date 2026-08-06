<?php

namespace Tsrgtm\MediaLibrary\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class InstallMediaLibraryCommand extends Command
{
    protected $signature = 'media-library:install
        {--panel-provider=app/Providers/Filament/AdminPanelProvider.php}
        {--no-npm : Do not install frontend dependencies}
        {--force : Overwrite published frontend source files}';

    protected $description = 'Install the Tsrgtm Media Library package and Filament plugin';

    public function handle(Filesystem $files): int
    {
        $this->components->info('Installing tsrgtm/media-library…');

        $this->call('vendor:publish', [
            '--tag' => 'media-library-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'media-library-assets',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'media-library-migrations',
            '--force' => false,
        ]);

        $this->publishFrontend($files);
        $this->patchAppJs($files);
        $this->patchThemeCss($files);
        $this->patchPanelProvider($files);

        if (! $this->option('no-npm')) {
            $this->installNpmDependencies();
        }

        $this->call('optimize:clear');

        $this->newLine();
        $this->components->info('Media Library installed.');
        $this->line('Run: php artisan migrate');
        $this->line('Run: npm run build (or npm run dev)');

        return self::SUCCESS;
    }

    private function publishFrontend(Filesystem $files): void
    {
        $source = dirname(__DIR__, 2).'/resources';
        $targets = [
            $source.'/js/media-library.js' => resource_path('js/vendor/tsrgtm-media-library/media-library.js'),
            $source.'/js/media-preview.js' => resource_path('js/vendor/tsrgtm-media-library/media-preview.js'),
            $source.'/js/media-picker.js' => resource_path('js/vendor/tsrgtm-media-library/media-picker.js'),
            $source.'/css/media-library.css' => resource_path('css/vendor/tsrgtm-media-library/media-library.css'),
        ];

        foreach ($targets as $from => $to) {
            if ($files->exists($to) && ! $this->option('force')) {
                $this->components->warn("Preserved existing file: {$to}");

                continue;
            }

            $files->ensureDirectoryExists(dirname($to));
            $files->copy($from, $to);
            $this->components->task("Published {$to}");
        }
    }

    private function patchAppJs(Filesystem $files): void
    {
        $path = resource_path('js/app.js');

        if (! $files->exists($path)) {
            $this->components->warn('resources/js/app.js was not found; import media-library.js manually.');

            return;
        }

        $content = $files->get($path);
        $imports = [
            "import { mediaDrive } from './vendor/tsrgtm-media-library/media-library';",
            "import { mediaPicker } from './vendor/tsrgtm-media-library/media-picker';",
        ];

        $globals = [
            'window.mediaDrive = mediaDrive;',
            'window.mediaPicker = mediaPicker;',
        ];

        $changed = false;

        foreach ($imports as $import) {
            if (! Str::contains($content, $import)) {
                $content = rtrim($content)."\n\n{$import}\n";
                $changed = true;
            }
        }

        foreach ($globals as $global) {
            if (! Str::contains($content, $global)) {
                $content = rtrim($content)."\n{$global}\n";
                $changed = true;
            }
        }

        if ($changed) {
            $files->put($path, $content);
            $this->components->task('Updated resources/js/app.js');
        }
    }

    private function patchThemeCss(Filesystem $files): void
    {
        $candidates = [
            resource_path('css/filament/admin/theme.css') => '../../vendor/tsrgtm-media-library/media-library.css',
            resource_path('css/filament/theme.css') => '../vendor/tsrgtm-media-library/media-library.css',
        ];

        foreach ($candidates as $path => $relative) {
            if (! $files->exists($path)) {
                continue;
            }

            $content = $files->get($path);
            $import = "@import \"{$relative}\";";

            if (! Str::contains($content, $import)) {
                $files->put($path, rtrim($content)."\n\n{$import}\n");
                $this->components->task("Updated {$path}");
            }

            return;
        }

        $this->components->warn('Filament theme.css was not found; import the package CSS manually.');
    }

    private function patchPanelProvider(Filesystem $files): void
    {
        $relative = (string) $this->option('panel-provider');
        $path = base_path($relative);

        if (! $files->exists($path)) {
            $this->components->warn("Panel provider not found: {$relative}");
            $this->line('Add ->plugin(\\Tsrgtm\\MediaLibrary\\MediaLibraryPlugin::make()) to your Filament panel manually.');

            return;
        }

        $content = $files->get($path);
        $use = 'use Tsrgtm\\MediaLibrary\\MediaLibraryPlugin;';

        if (! Str::contains($content, $use)) {
            $content = preg_replace('/(namespace App\\\\Providers\\\\Filament;\\R)/', "$1\n{$use}\n", $content, 1) ?? $content;
        }

        if (! Str::contains($content, 'MediaLibraryPlugin::make()')) {
            $position = strrpos($content, '->');
            $needle = '->plugins([';

            if (Str::contains($content, $needle)) {
                $content = str_replace($needle, "->plugins([\n                MediaLibraryPlugin::make(),", $content);
            } else {
                $content = preg_replace('/(->middleware\\s*\\()/m', "->plugin(MediaLibraryPlugin::make())\n            $1", $content, 1) ?? $content;
            }
        }

        $files->put($path, $content);
        $this->components->task("Updated {$relative}");
    }

    private function installNpmDependencies(): void
    {
        $packages = [
            'tus-js-client',
            'video.js',
            'pdfjs-dist',
            'docx-preview',
            'xlsx',
            'marked',
            'dompurify',
            'highlight.js',
        ];

        $process = new Process(['npm', 'install', ...$packages], base_path());
        $process->setTimeout(600);
        $process->run(fn ($type, $buffer) => $this->output->write($buffer));

        if (! $process->isSuccessful()) {
            $this->components->warn('npm install failed. Run it manually.');
        }
    }
}
