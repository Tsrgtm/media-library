<x-filament-panels::page>
    <div
        x-data="mediaDrive({
            listUrl: @js(route('media.library.index')),
            moveUrl: @js(route('media.library.move')),
            downloadUrl: @js(route('media.library.download')),
            tusEndpoint: @js(route('media.tus.create')),
            statusUrl: @js(route('media.status', ['media' => '__MEDIA__'])),
            failedUploadDeleteUrl: @js(route('media.library.upload-failures.destroy', ['media' => '__MEDIA__'])),
            csrfToken: @js(csrf_token()),
            rootUrl: @js(\Tsrgtm\MediaLibrary\Filament\Pages\MediaLibrary::getUrl()),
            trashUrl: @js(\Tsrgtm\MediaLibrary\Filament\Pages\MediaTrash::getUrl()),
            folderBaseUrl: @js(\Tsrgtm\MediaLibrary\Filament\Pages\MediaLibrary::getUrl() . '/folder'),
            trashFolderBaseUrl: @js(\Tsrgtm\MediaLibrary\Filament\Pages\MediaTrash::getUrl() . '/folder'),
            initialFolder: @js($folderSlug),
            initialTrash: @js($isTrash),
            initialSearch: @js($search),
            initialType: @js($type),
            initialStatus: @js($status),
            initialSort: @js($sort),
            initialView: @js($viewMode),
            chunkSize: @js(config('media-library.chunk_size')),
            smallFileThreshold: @js(config('media-library.small_file_threshold')),
            statusInterval: @js(config('media-library.status_check_interval_ms')),
            statusMaxAttempts: @js(config('media-library.status_check_max_attempts')),
            concurrentUploads: @js(config('media-library.concurrent_uploads', 4)),
            placeholderExtensions: @js(collect(config('media-library.placeholders.extensions', []))->map(fn ($path) => asset(ltrim((string) $path, '/')))->all()),
            placeholderKinds: @js(collect(config('media-library.placeholders.kinds', []))->map(fn ($path) => asset(ltrim((string) $path, '/')))->all()),
            defaultPlaceholder: @js(asset(ltrim((string) config('media-library.placeholders.default', '/images/media-placeholders/file.png'), '/'))),
        })"
        x-init="init()"
        x-on:media-library-reload.window="reload({ silent: true })"
        x-on:media-filters-applied.window="
            filters.type = $event.detail.filters.type ?? ''
            filters.status = $event.detail.filters.status ?? ''
            filters.sort = $event.detail.filters.sort ?? 'newest'
            syncQuery()
            reload({ silent: true })
        "
        x-on:click.window="handleWindowClick($event)"
        x-on:keydown.window="handleShortcut($event)"
        x-on:dragenter.window.prevent="handleExternalDragEnter($event)"
        x-on:dragleave.window.prevent="handleExternalDragLeave($event)"
        x-on:dragover.window.prevent
        x-on:drop.window.prevent="handleExternalDrop($event)"
        class="media-drive-shell relative flex min-h-[calc(100vh-10rem)] flex-col overflow-visible rounded-2xl border border-zinc-200 bg-white outline-none ring-0 dark:border-white/10 dark:bg-zinc-950"
    >
        <input
            id="media-library-file-input"
            x-ref="fileInput"
            type="file"
            multiple
            class="sr-only"
            x-on:change="queueFiles($event.target.files)"
        >

        <x-media-library::media.toolbar :is-trash="$isTrash" />
        <x-media-library::media.bulk-toolbar :is-trash="$isTrash" />

        <div
            x-ref="selectionCanvas"
            data-media-selection-canvas
            x-on:pointerdown="startMarquee($event)"
            class="relative flex min-h-0 flex-1 flex-col space-y-6 px-4 pt-4 pb-0 outline-none ring-0"
        >
            <template x-if="showInitialSkeleton && folders.length === 0 && media.length === 0">
                <div class="space-y-5">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <template x-for="index in 4" :key="'folder-skeleton-' + index">
                            <div class="h-16 animate-pulse rounded-xl bg-zinc-100 dark:bg-zinc-900"></div>
                        </template>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-8">
                        <template x-for="index in 12" :key="'media-skeleton-' + index">
                            <div class="aspect-square animate-pulse rounded-xl bg-zinc-100 dark:bg-zinc-900"></div>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="! loading && ! showInitialSkeleton && folders.length === 0 && media.length === 0">
                <div class="py-20 text-center">
                    <x-lucide-images class="mx-auto size-12 text-zinc-400" stroke-width="1.2" />
                    <h3 class="mt-4 text-base font-semibold text-zinc-900 dark:text-white">
                        <span x-text="filters.trash ? 'Trash is empty' : filters.search ? 'No matching media' : 'Nothing here yet'"></span>
                    </h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        <span x-text="filters.trash ? 'Deleted media will appear here.' : filters.search ? 'Try another search or filter.' : 'Upload files or create a folder to begin.'"></span>
                    </p>
                </div>
            </template>

            <section x-show="folders.length > 0">
                <h2 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-white"><span x-text="filters.trash ? 'Deleted folders' : 'Folders'"></span></h2>

                <div x-show="viewMode === 'grid'" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <template x-for="(folderItem, folderIndex) in folders" :key="'folder-grid-' + folderItem.id">
                        <article
                            data-media-selectable
                            x-bind:data-media-key="itemKey(folderItem)"
                            draggable="true"
                            x-on:dragend="finishItemDrag()"
                            x-on:dragstart.stop="beginItemDrag($event, folderItem)"
                            x-on:dragover.prevent="dragOverFolderId = folderItem.id"
                            x-on:dragleave="dragOverFolderId = null"
                            x-on:drop.prevent.stop="dropSelectionIntoFolder(folderItem.id)"
                            x-on:click.stop="selectItem($event, folderItem, folderIndex)"
                            x-on:dblclick.stop="openItem(folderItem)"
                            x-on:contextmenu.prevent.stop="openContextMenu($event, folderItem)"
                            x-bind:class="[
                                isSelected(folderItem)
                                    ? 'border-primary-500 bg-primary-50 dark:border-primary-500 dark:bg-primary-950/30'
                                    : 'border-zinc-200 bg-white hover:bg-zinc-50 dark:border-white/10 dark:bg-zinc-950 dark:hover:bg-white/[0.03]',
                                dragOverFolderId === folderItem.id ? 'ring-2 ring-emerald-500' : '',
                            ]"
                            class="flex min-w-0 items-center gap-3 rounded-xl border px-3 py-3 transition"
                        >
                            <div class="grid size-10 shrink-0 place-items-center rounded-lg bg-zinc-100 dark:bg-zinc-900">
                                <img
                                    x-bind:src="folderItem.thumbnail_url"
                                    x-on:error="imageFallback($event, folderItem)"
                                    alt=""
                                    class="size-7 object-contain"
                                >
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-white" x-text="folderItem.name"></p>
                                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">Folder</p>
                            </div>
                            <button type="button" class="grid size-8 shrink-0 place-items-center rounded-lg text-zinc-500 hover:bg-zinc-100 dark:hover:bg-white/5" x-on:click.stop="openContextMenu($event, folderItem)">
                                <x-lucide-ellipsis class="size-4" stroke-width="1.2" />
                            </button>
                        </article>
                    </template>
                </div>

                <div x-show="viewMode === 'list'" class="overflow-hidden rounded-xl border border-zinc-200 dark:border-white/10">
                    <template x-for="(folderItem, folderIndex) in folders" :key="'folder-list-' + folderItem.id">
                        <article
                            data-media-selectable
                            x-bind:data-media-key="itemKey(folderItem)"
                            draggable="true"
                            x-on:dragend="finishItemDrag()"
                            x-on:dragstart.stop="beginItemDrag($event, folderItem)"
                            x-on:dragover.prevent="dragOverFolderId = folderItem.id"
                            x-on:dragleave="dragOverFolderId = null"
                            x-on:drop.prevent.stop="dropSelectionIntoFolder(folderItem.id)"
                            x-on:click.stop="selectItem($event, folderItem, folderIndex)"
                            x-on:dblclick.stop="openItem(folderItem)"
                            x-on:contextmenu.prevent.stop="openContextMenu($event, folderItem)"
                            x-bind:class="isSelected(folderItem) ? 'bg-primary-50 dark:bg-primary-950/30' : 'bg-white hover:bg-zinc-50 dark:bg-zinc-950 dark:hover:bg-white/[0.03]'"
                            class="flex items-center gap-3 border-b border-zinc-200 p-3 transition last:border-b-0 dark:border-white/10"
                        >
                            <div class="grid size-11 shrink-0 place-items-center rounded-lg bg-zinc-100 dark:bg-zinc-900">
                                <img x-bind:src="folderItem.thumbnail_url" x-on:error="imageFallback($event, folderItem)" alt="" class="size-7 object-contain">
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-white" x-text="folderItem.name"></p>
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Folder</p>
                            </div>
                            <button type="button" class="grid size-8 place-items-center rounded-lg hover:bg-zinc-100 dark:hover:bg-white/5" x-on:click.stop="openContextMenu($event, folderItem)">
                                <x-lucide-ellipsis class="size-4" stroke-width="1.2" />
                            </button>
                        </article>
                    </template>
                </div>
            </section>

            <section x-show="media.length > 0">
                <h2 x-show="folders.length > 0" class="mb-3 text-sm font-semibold text-zinc-900 dark:text-white">Files</h2>

                <div x-show="viewMode === 'grid'" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-8">
                    <template x-for="(mediaItem, mediaIndex) in media" :key="'media-grid-' + mediaItem.id">
                        <article
                            data-media-selectable
                            x-bind:data-media-key="itemKey(mediaItem)"
                            draggable="true"
                            x-on:dragend="finishItemDrag()"
                            x-on:dragstart.stop="beginItemDrag($event, mediaItem)"
                            x-on:click.stop="selectItem($event, mediaItem, mediaIndex)"
                            x-on:dblclick.stop="openItem(mediaItem)"
                            x-on:contextmenu.prevent.stop="openContextMenu($event, mediaItem)"
                            x-bind:class="isSelected(mediaItem)
                                ? 'border-primary-500 bg-primary-50 dark:border-primary-500 dark:bg-primary-950/30'
                                : 'border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-950'"
                            class="group overflow-hidden rounded-xl border transition"
                        >
                            <div class="relative aspect-square overflow-hidden bg-zinc-100 dark:bg-zinc-900">
                                <img
                                    x-bind:src="mediaItem.thumbnail_url"
                                    x-on:error="imageFallback($event, mediaItem)"
                                    alt=""
                                    loading="lazy"
                                    x-bind:class="mediaItem.thumbnail_mode === 'cover'
                                        ? 'size-full object-cover'
                                        : 'size-full object-contain p-7'"
                                >

                                <div x-show="mediaItem.status !== 'ready'" class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-white/70 dark:bg-black/60">
                                    <x-filament::loading-indicator class="size-6 text-primary-600" />
                                    <span class="text-xs font-medium capitalize" x-text="mediaItem.status"></span>
                                </div>

                                <button type="button" class="absolute right-2 top-2 grid size-8 place-items-center rounded-lg bg-black/45 text-white opacity-0 transition group-hover:opacity-100" x-on:click.stop="openContextMenu($event, mediaItem)">
                                    <x-lucide-ellipsis class="size-4" stroke-width="1.2" />
                                </button>
                            </div>
                            <div class="p-2.5">
                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-white" x-text="mediaItem.name"></p>
                                <p class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400" x-text="`${mediaItem.kind} · ${formatBytes(mediaItem.size)}`"></p>
                            </div>
                        </article>
                    </template>
                </div>

                <div x-show="viewMode === 'list'" class="overflow-hidden rounded-xl border border-zinc-200 dark:border-white/10">
                    <template x-for="(mediaItem, mediaIndex) in media" :key="'media-list-' + mediaItem.id">
                        <article
                            data-media-selectable
                            x-bind:data-media-key="itemKey(mediaItem)"
                            draggable="true"
                            x-on:dragend="finishItemDrag()"
                            x-on:dragstart.stop="beginItemDrag($event, mediaItem)"
                            x-on:click.stop="selectItem($event, mediaItem, mediaIndex)"
                            x-on:dblclick.stop="openItem(mediaItem)"
                            x-on:contextmenu.prevent.stop="openContextMenu($event, mediaItem)"
                            x-bind:class="isSelected(mediaItem)
                                ? 'bg-primary-50 dark:bg-primary-950/30'
                                : 'bg-white hover:bg-zinc-50 dark:bg-zinc-950 dark:hover:bg-white/[0.03]'"
                            class="flex items-center gap-3 border-b border-zinc-200 p-3 transition last:border-b-0 dark:border-white/10"
                        >
                            <div class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-900">
                                <img
                                    x-bind:src="mediaItem.thumbnail_url"
                                    x-on:error="imageFallback($event, mediaItem)"
                                    alt=""
                                    loading="lazy"
                                    x-bind:class="mediaItem.thumbnail_mode === 'cover'
                                        ? 'size-full object-cover'
                                        : 'size-full object-contain p-2.5'"
                                >
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-white" x-text="mediaItem.name"></p>
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" x-text="`${mediaItem.kind} · ${formatBytes(mediaItem.size)} · ${mediaItem.status}`"></p>
                            </div>
                            <button type="button" class="grid size-8 place-items-center rounded-lg hover:bg-zinc-100 dark:hover:bg-white/5" x-on:click.stop="openContextMenu($event, mediaItem)">
                                <x-lucide-ellipsis class="size-4" stroke-width="1.2" />
                            </button>
                        </article>
                    </template>
                </div>
            </section>

            <div x-ref="infiniteSentinel" class="h-px"></div>

            <div x-show="loadingMore" class="flex justify-center py-4">
                <x-filament::loading-indicator class="size-6 text-primary-600" />
            </div>
        </div>

        <div
            x-show="marquee.active"
            x-cloak
            class="media-drive-selection-box fixed z-40 border border-primary-500 bg-primary-500/15"
            x-bind:style="marqueeStyle()"
        ></div>

        <div x-show="dragActive && ! filters.trash" x-cloak class="pointer-events-none absolute inset-0 z-40 grid place-items-center bg-primary-600/10 backdrop-blur-sm">
            <div class="rounded-2xl border-2 border-dashed border-primary-600 bg-white px-10 py-8 text-center shadow-xl dark:bg-zinc-950">
                <x-lucide-upload-cloud class="mx-auto size-12 text-primary-600" stroke-width="1.2" />
                <p class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">Drop files to upload</p>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Files will be added to this folder.</p>
            </div>
        </div>

        <x-media-library::media.upload-queue />
        <x-media-library::media.context-menu :is-trash="$isTrash" />
        <x-media-library::media.lightbox />
    </div>
</x-filament-panels::page>
