<?php

namespace Tsrgtm\MediaLibrary\Filament\Pages\MediaLibrary;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Tsrgtm\MediaLibrary\Models\Media;
use Tsrgtm\MediaLibrary\Models\MediaFolder;
use Tsrgtm\MediaLibrary\Models\MediaTag;

abstract class BaseMediaLibraryPage extends Page
{
    protected string $view = 'media-library::filament.pages.media-library';

    protected static ?string $title = 'Media Library';

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'type', history: true)]
    public string $type = '';

    #[Url(as: 'status', history: true)]
    public string $status = '';

    #[Url(as: 'sort', history: true)]
    public string $sort = 'newest';

    #[Url(as: 'view', history: true)]
    public string $viewMode = 'grid';

    public ?string $folderSlug = null;

    public bool $isTrash = false;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function getHeading(): string
    {
        if ($this->isTrash) {
            return 'Trash';
        }

        if (filled($this->folderSlug)) {
            return MediaFolder::query()
                ->withTrashed()
                ->where('slug', $this->folderSlug)
                ->value('name') ?? 'Media Library';
        }

        return 'Media Library';
    }

    public function getSubheading(): ?string
    {
        if ($this->isTrash) {
            return 'Restore files or delete them permanently.';
        }

        if (filled($this->folderSlug)) {
            return 'Files and folders in the current folder.';
        }

        return 'Upload, organize, search, and manage media.';
    }

    #[On('media-page-state')]
    public function updatePageState(
        string $search = '',
        string $type = '',
        string $status = '',
        string $sort = 'newest',
        string $view = 'grid',
    ): void {
        $this->search = $search;
        $this->type = $type;
        $this->status = $status;
        $this->sort = $sort;
        $this->viewMode = $view;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createFolder')
                ->label('New folder')
                ->icon(Heroicon::FolderPlus)
                ->color('gray')
                ->visible(fn (): bool => ! $this->isTrash)
                ->modalHeading('Create folder')
                ->modalAlignment(Alignment::Left)
                ->modalWidth(Width::Small)
                ->schema([
                    TextInput::make('name')
                        ->label('Folder name')
                        ->required()
                        ->maxLength(120)
                        ->autofocus(),
                ])
                ->action(function (array $data): void {
                    MediaFolder::query()->create([
                        'parent_id' => $this->currentFolderId(),
                        'name' => trim($data['name']),
                        'slug' => $this->uniqueFolderSlug(
                            trim($data['name']),
                        ),
                    ]);

                    $this->notifyAndReload('Folder created');
                }),

            Action::make('createTag')
                ->label('New tag')
                ->icon(Heroicon::Tag)
                ->color('gray')
                ->visible(fn (): bool => ! $this->isTrash)
                ->modalHeading('Create tag')
                ->modalAlignment(Alignment::Left)
                ->modalWidth(Width::Small)
                ->schema([
                    TextInput::make('name')
                        ->label('Tag name')
                        ->helperText(
                            'Tags are reusable labels for finding, filtering, '
                            .'and grouping media across folders.',
                        )
                        ->required()
                        ->maxLength(80)
                        ->autofocus(),
                ])
                ->action(function (array $data): void {
                    $this->createTag(
                        (string) $data['name'],
                    );

                    $this->notifyAndReload('Tag created');
                }),

            Action::make('upload')
                ->label('Upload')
                ->icon(Heroicon::ArrowUpTray)
                ->visible(fn (): bool => ! $this->isTrash)
                ->extraAttributes([
                    'x-on:click.prevent.stop' => "document.getElementById('media-library-file-input')?.click()",
                ])
                ->action(function (): void {
                    // The browser click handler opens the native picker.
                }),
        ];
    }

    public function renameAction(): Action
    {
        return Action::make('rename')
            ->modalHeading('Rename')
            ->modalAlignment(Alignment::Left)
            ->modalWidth(Width::Small)
            ->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255)
                    ->autofocus(),
            ])
            ->fillForm(fn (array $arguments): array => [
                'name' => (string) ($arguments['name'] ?? ''),
            ])
            ->action(function (array $data, array $arguments): void {
                if (($arguments['type'] ?? null) === 'folder') {
                    $folder = MediaFolder::query()->findOrFail(
                        (int) $arguments['id'],
                    );

                    $folder->update([
                        'name' => trim($data['name']),
                        'slug' => $this->uniqueFolderSlug(
                            trim($data['name']),
                            $folder->getKey(),
                        ),
                    ]);
                } else {
                    $media = Media::withTrashed()->findOrFail(
                        (int) $arguments['id'],
                    );

                    $media->update([
                        'original_name' => trim($data['name']),
                        'title' => pathinfo(
                            trim($data['name']),
                            PATHINFO_FILENAME,
                        ),
                    ]);
                }

                $this->notifyAndReload('Item renamed');
            });
    }

    public function moveAction(): Action
    {
        return Action::make('move')
            ->label('Move')
            ->icon('lucide-folder-input')
            ->modalHeading('Move selected items')
            ->modalAlignment(Alignment::Left)
            ->modalWidth(Width::Medium)
            ->schema([
                Select::make('folder_id')
                    ->label('Destination')
                    ->placeholder('Media library root')
                    ->options(
                        MediaFolder::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all(),
                    )
                    ->searchable()
                    ->native(false),
            ])
            ->action(function (array $data, array $arguments): void {
                $target = filled($data['folder_id'] ?? null)
                    ? (int) $data['folder_id']
                    : null;

                $this->moveItems(
                    mediaIds: $arguments['mediaIds'] ?? [],
                    folderIds: $arguments['folderIds'] ?? [],
                    targetFolderId: $target,
                );
            });
    }

    public function tagsAction(): Action
    {
        return Action::make('tags')
            ->modalHeading('Set tags')
            ->modalAlignment(Alignment::Left)
            ->modalDescription(
                'Tags are reusable labels used for search, filtering, '
                .'bulk organization, and finding the same media across '
                .'different folders and collections.',
            )
            ->modalWidth(Width::Medium)
            ->schema([
                Select::make('tag_ids')
                    ->label('Tags')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(
                        MediaTag::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all(),
                    )
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('New tag name')
                            ->required()
                            ->maxLength(80),
                    ])
                    ->createOptionUsing(
                        fn (array $data): int => $this->createTag(
                            (string) $data['name'],
                        )->getKey(),
                    ),
            ])
            ->action(function (
                array $data,
                array $arguments,
            ): void {
                Media::query()
                    ->whereKey($arguments['mediaIds'] ?? [])
                    ->get()
                    ->each(
                        fn (Media $media) => $media->tags()->sync(
                            $data['tag_ids'] ?? [],
                        ),
                    );

                $this->notifyAndReload('Tags updated');
            });
    }

    public function editAction(): Action
    {
        return Action::make('edit')
            ->label('Edit details')
            ->icon(Heroicon::PencilSquare)
            ->slideOver()
            ->modalWidth(Width::ExtraLarge)
            ->modalHeading('Edit media')
            ->modalAlignment(Alignment::Left)
            ->modalDescription(
                'Update searchable details, placement, tags, and optionally '
                .'replace the underlying file with another file of the same type.',
            )
            ->fillForm(function (array $arguments): array {
                $media = Media::query()
                    ->with('tags')
                    ->findOrFail((int) $arguments['id']);

                return [
                    'original_name' => $media->original_name,
                    'title' => $media->title,
                    'alt' => $media->alt,
                    'caption' => $media->caption,
                    'folder_id' => $media->folder_id,
                    'tag_ids' => $media->tags->modelKeys(),
                    'replacement' => null,
                    'mime_type' => $media->mime_type,
                    'extension' => $media->extension,
                    'size_display' => $this->humanFileSize($media->size),
                    'dimensions' => $media->width && $media->height
                        ? "{$media->width} × {$media->height}"
                        : null,
                    'status_display' => $media->status->value,
                ];
            })
            ->schema(function (array $arguments): array {
                $media = Media::query()->findOrFail(
                    (int) $arguments['id'],
                );

                return [
                    Grid::make(2)
                        ->schema([
                            TextInput::make('original_name')
                                ->label('Display file name')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('title')
                                ->label('Title')
                                ->maxLength(255),

                            TextInput::make('alt')
                                ->label('Alternative text')
                                ->helperText(
                                    'Describe image content for accessibility and SEO.',
                                )
                                ->maxLength(255)
                                ->visible(
                                    fn (): bool => $media->kind->value === 'image',
                                ),

                            Textarea::make('caption')
                                ->label('Caption / description')
                                ->rows(4)
                                ->columnSpanFull(),

                            Select::make('folder_id')
                                ->label('Folder')
                                ->placeholder('Media library root')
                                ->options(
                                    MediaFolder::query()
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all(),
                                )
                                ->searchable()
                                ->native(false),

                            Select::make('tag_ids')
                                ->label('Tags')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->options(
                                    MediaTag::query()
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all(),
                                )
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label('New tag name')
                                        ->required()
                                        ->maxLength(80),
                                ])
                                ->createOptionUsing(
                                    fn (array $data): int => $this->createTag(
                                        (string) $data['name'],
                                    )->getKey(),
                                ),

                            FileUpload::make('replacement')
                                ->label('Replace file')
                                ->helperText(
                                    "Only another {$media->kind->value} file is allowed. "
                                    .'The media ID, URL, tags, and model relations remain unchanged.',
                                )
                                ->acceptedFileTypes(
                                    $this->acceptedMimeTypes(
                                        $media->kind->value,
                                    ),
                                )
                                ->storeFiles(false)
                                ->maxSize(
                                    (int) ceil(
                                        (int) config(
                                            'media-library.maximum_upload_size',
                                            5 * 1024 * 1024 * 1024,
                                        ) / 1024,
                                    ),
                                )
                                ->columnSpanFull(),

                            TextInput::make('mime_type')
                                ->label('MIME type')
                                ->disabled(),

                            TextInput::make('extension')
                                ->label('Extension')
                                ->disabled(),

                            TextInput::make('size_display')
                                ->label('File size')
                                ->disabled(),

                            TextInput::make('dimensions')
                                ->label('Dimensions')
                                ->disabled()
                                ->visible(
                                    fn (): bool => $media->kind->value === 'image',
                                ),

                            TextInput::make('status_display')
                                ->label('Processing status')
                                ->disabled(),                        ]),
                ];
            })
            ->action(function (
                array $data,
                array $arguments,
            ): void {
                $media = Media::query()->findOrFail(
                    (int) $arguments['id'],
                );

                DB::transaction(function () use (
                    $media,
                    $data,
                ): void {
                    $media->update([
                        'original_name' => trim(
                            (string) $data['original_name'],
                        ),
                        'title' => filled($data['title'] ?? null)
                            ? trim((string) $data['title'])
                            : null,
                        'alt' => filled($data['alt'] ?? null)
                            ? trim((string) $data['alt'])
                            : null,
                        'caption' => filled($data['caption'] ?? null)
                            ? trim((string) $data['caption'])
                            : null,
                        'folder_id' => filled($data['folder_id'] ?? null)
                            ? (int) $data['folder_id']
                            : null,
                    ]);

                    $media->tags()->sync(
                        $data['tag_ids'] ?? [],
                    );
                });

                $replacement = $data['replacement'] ?? null;

                if ($replacement instanceof TemporaryUploadedFile) {
                    app(MediaReplacementManager::class)
                        ->replace($media, $replacement);
                }

                $this->notifyAndReload('Media updated');
            });
    }

    public function trashAction(): Action
    {
        return Action::make('trash')
            ->color('danger')
            ->icon(Heroicon::Trash)
            ->requiresConfirmation()
            ->modalHeading('Move selected items to trash?')
            ->modalAlignment(Alignment::Left)
            ->modalDescription(
                'Selected folders keep their full hierarchy in Trash. '
                .'Every nested folder and associated file is moved with them.',
            )
            ->action(function (array $arguments): void {
                $mediaIds = $this->integerIds(
                    $arguments['mediaIds'] ?? [],
                );

                $folderIds = $this->integerIds(
                    $arguments['folderIds'] ?? [],
                );

                DB::transaction(function () use (
                    $mediaIds,
                    $folderIds,
                ): void {
                    if ($mediaIds !== []) {
                        Media::query()
                            ->whereKey($mediaIds)
                            ->delete();
                    }

                    if ($folderIds === []) {
                        return;
                    }

                    $treeIds = $this->descendantFolderIds(
                        $folderIds,
                        withTrashed: false,
                    );

                    Media::query()
                        ->whereIn('folder_id', $treeIds)
                        ->delete();

                    MediaFolder::query()
                        ->whereIn('id', $treeIds)
                        ->get()
                        ->each
                        ->delete();
                });

                $this->notifyAndReload('Moved to trash');
            });
    }

    public function restoreAction(): Action
    {
        return Action::make('restore')
            ->icon(Heroicon::ArrowPath)
            ->requiresConfirmation()
            ->modalHeading('Restore selected items?')
            ->modalAlignment(Alignment::Left)
            ->action(function (array $arguments): void {
                $mediaIds = $this->integerIds(
                    $arguments['mediaIds'] ?? [],
                );

                $folderIds = $this->integerIds(
                    $arguments['folderIds'] ?? [],
                );

                DB::transaction(function () use (
                    $mediaIds,
                    $folderIds,
                ): void {
                    if ($folderIds !== []) {
                        $treeIds = $this->descendantFolderIds(
                            $folderIds,
                            withTrashed: true,
                        );

                        $trashedParents = MediaFolder::onlyTrashed()
                            ->whereIn(
                                'id',
                                MediaFolder::onlyTrashed()
                                    ->whereIn('id', $folderIds)
                                    ->pluck('parent_id')
                                    ->filter(),
                            )
                            ->pluck('id')
                            ->all();

                        MediaFolder::onlyTrashed()
                            ->whereIn('id', $folderIds)
                            ->whereIn('parent_id', $trashedParents)
                            ->update(['parent_id' => null]);

                        MediaFolder::onlyTrashed()
                            ->whereIn('id', $treeIds)
                            ->restore();

                        Media::onlyTrashed()
                            ->whereIn('folder_id', $treeIds)
                            ->restore();
                    }

                    if ($mediaIds !== []) {
                        $trashedFolderIds = MediaFolder::onlyTrashed()
                            ->pluck('id');

                        Media::onlyTrashed()
                            ->whereKey($mediaIds)
                            ->whereIn(
                                'folder_id',
                                $trashedFolderIds,
                            )
                            ->update(['folder_id' => null]);

                        Media::onlyTrashed()
                            ->whereKey($mediaIds)
                            ->restore();
                    }
                });

                $this->notifyAndReload('Items restored');
            });
    }

    public function forceDeleteAction(): Action
    {
        return Action::make('forceDelete')
            ->color('danger')
            ->icon(Heroicon::Trash)
            ->requiresConfirmation()
            ->modalHeading('Permanently delete selected items?')
            ->modalAlignment(Alignment::Left)
            ->modalDescription(
                'Folder trees, database records, originals, thumbnails, '
                .'and all generated conversions will be removed.',
            )
            ->action(function (array $arguments): void {
                $mediaIds = $this->integerIds(
                    $arguments['mediaIds'] ?? [],
                );

                $folderIds = $this->integerIds(
                    $arguments['folderIds'] ?? [],
                );

                DB::transaction(function () use (
                    $mediaIds,
                    $folderIds,
                ): void {
                    if ($folderIds !== []) {
                        $treeIds = $this->descendantFolderIds(
                            $folderIds,
                            withTrashed: true,
                        );

                        Media::onlyTrashed()
                            ->whereIn('folder_id', $treeIds)
                            ->get()
                            ->each
                            ->forceDelete();

                        MediaFolder::onlyTrashed()
                            ->whereIn('id', $treeIds)
                            ->get()
                            ->each
                            ->forceDelete();
                    }

                    if ($mediaIds !== []) {
                        Media::onlyTrashed()
                            ->whereKey($mediaIds)
                            ->get()
                            ->each
                            ->forceDelete();
                    }
                });

                $this->notifyAndReload(
                    'Items permanently deleted',
                );
            });
    }

    public function emptyTrashAction(): Action
    {
        return Action::make('emptyTrash')
            ->label('Empty trash')
            ->color('danger')
            ->icon(Heroicon::Trash)
            ->requiresConfirmation()
            ->modalHeading('Empty trash?')
            ->modalAlignment(Alignment::Left)
            ->modalDescription(
                'All deleted folders, files, and generated conversions '
                .'will be permanently removed.',
            )
            ->action(function (): void {
                DB::transaction(function (): void {
                    Media::onlyTrashed()
                        ->get()
                        ->each
                        ->forceDelete();

                    MediaFolder::onlyTrashed()
                        ->get()
                        ->each
                        ->forceDelete();
                });

                $this->notifyAndReload('Trash emptied');
            });
    }

    public function moveItemsToFolder(
        array $mediaIds,
        array $folderIds,
        int $targetFolderId,
    ): void {
        $this->moveItems(
            mediaIds: $mediaIds,
            folderIds: $folderIds,
            targetFolderId: $targetFolderId,
        );
    }

    protected function currentFolderId(): ?int
    {
        if (blank($this->folderSlug)) {
            return null;
        }

        return MediaFolder::query()
            ->withTrashed()
            ->where('slug', $this->folderSlug)
            ->value('id');
    }

    protected function moveItems(
        array $mediaIds,
        array $folderIds,
        ?int $targetFolderId,
    ): void {
        Media::query()
            ->whereKey($mediaIds)
            ->update(['folder_id' => $targetFolderId]);

        $folderQuery = MediaFolder::query()
            ->whereKey($folderIds);

        if ($targetFolderId !== null) {
            $folderQuery->where('id', '!=', $targetFolderId);
        }

        $folderQuery->update([
            'parent_id' => $targetFolderId,
        ]);

        $this->notifyAndReload('Items moved');
    }

    protected function descendantFolderIds(
        array $rootIds,
        bool $withTrashed = false,
    ): array {
        $all = $this->integerIds($rootIds);
        $pending = $all;

        while ($pending !== []) {
            $query = MediaFolder::query();

            if ($withTrashed) {
                $query->withTrashed();
            }

            $children = $query
                ->whereIn('parent_id', $pending)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $pending = array_values(array_diff(
                $children,
                $all,
            ));

            $all = array_values(array_unique([
                ...$all,
                ...$children,
            ]));
        }

        return $all;
    }

    protected function integerIds(array $ids): array
    {
        return array_values(array_unique(array_map(
            'intval',
            $ids,
        )));
    }

    protected function createTag(string $name): MediaTag
    {
        $name = trim($name);
        $base = Str::slug($name) ?: 'tag';
        $slug = $base;
        $counter = 2;

        while (
            MediaTag::query()
                ->where('slug', $slug)
                ->where('name', '!=', $name)
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return MediaTag::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name],
        );
    }

    protected function acceptedMimeTypes(string $kind): array
    {
        return match ($kind) {
            'image' => ['image/*'],
            'video' => ['video/*'],
            'audio' => ['audio/*'],
            'archive' => [
                'application/zip',
                'application/x-7z-compressed',
                'application/vnd.rar',
                'application/x-rar-compressed',
                'application/gzip',
                'application/x-tar',
            ],
            'document' => [
                'application/pdf',
                'text/plain',
                'text/csv',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
            default => [],
        };
    }

    protected function humanFileSize(int $bytes): string
    {
        if ($bytes < 1) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min(
            (int) floor(log($bytes, 1024)),
            count($units) - 1,
        );

        return round(
            $bytes / (1024 ** $power),
            $power === 0 ? 0 : 1,
        ).' '.$units[$power];
    }

    protected function uniqueFolderSlug(
        string $name,
        ?int $ignoreId = null,
    ): string {
        $base = Str::slug($name) ?: 'folder';
        $slug = $base;
        $counter = 2;

        while (
            MediaFolder::query()
                ->when(
                    $ignoreId,
                    fn ($query) => $query->where('id', '!=', $ignoreId),
                )
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    protected function notifyAndReload(string $title): void
    {
        Notification::make()
            ->success()
            ->title($title)
            ->send();

        $this->dispatch('media-library-reload');
    }
}
