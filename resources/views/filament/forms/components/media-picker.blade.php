@php
    $statePath = $getStatePath();
    $componentId = $getId();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        wire:ignore
        x-data="mediaPicker({
            id: @js($componentId),
            state: $wire.$entangle(@js($statePath)),
            multiple: @js($field->isMultiple()),
            minItems: @js($field->getMinItems()),
            maxItems: @js($field->getMaxItems()),
            acceptedKinds: @js($field->getAcceptedKinds()),
            acceptedExtensions: @js($field->getAcceptedExtensions()),
            acceptedMimeTypes: @js($field->getAcceptedMimeTypes()),
            uploadable: @js($field->isUploadable()),
            searchable: @js($field->isSearchable()),
            reorderable: @js($field->isReorderable()),
            removable: @js($field->isRemovable()),
            replaceable: @js($field->isReplaceable()),
            showFolders: @js($field->shouldShowFolders()),
            showFileDetails: @js($field->shouldShowFileDetails()),
            defaultFolder: @js($field->getDefaultFolder()),
            browseUrl: @js(route('media.library.picker.browse')),
            resolveUrl: @js(route('media.library.picker.resolve')),
            tusEndpoint: @js(route('media.tus.create')),
            statusUrl: @js(route('media.status', ['media' => '__MEDIA__'])),
            csrfToken: @js(csrf_token()),
            inputAccept: @js($field->getInputAccept()),
        })"
        x-on:media-picker-refresh.window="
            if (
                !$event.detail?.id
                || $event.detail.id === id
            ) {
                resolveSelection()
            }
        "
        class="media-picker-field"
    >
        <div
            x-show="selectedItems.length === 0"
            class="media-picker-empty"
        >
            <div class="media-picker-empty-icon">
                <x-lucide-images
                    class="size-6"
                    stroke-width="1.2"
                />
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-950 dark:text-white">
                    {{ $field->getEmptyLabel() }}
                </p>

                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    Select from the media library
                    @if ($field->isUploadable())
                        or upload new files.
                    @else
                        .
                    @endif
                </p>
            </div>

            <x-filament::button
                type="button"
                size="sm"
                icon="heroicon-m-photo"
                x-on:click="openPicker()"
            >
                {{ $field->getSelectButtonLabel() }}
            </x-filament::button>
        </div>

        <div
            x-show="selectedItems.length > 0"
            x-cloak
            class="space-y-3"
        >
            <div
                class="media-picker-selected-grid"
                x-bind:class="{
                    'is-single': ! multiple,
                }"
            >
                <template
                    x-for="(item, index) in selectedItems"
                    :key="item.id"
                >
                    <article
                        class="media-picker-selected-card"
                        draggable="true"
                        x-on:dragstart="
                            if (reorderable) {
                                beginSelectedDrag(
                                    $event,
                                    index
                                )
                            }
                        "
                        x-on:dragover.prevent
                        x-on:drop.prevent="
                            if (reorderable) {
                                finishSelectedDrag(index)
                            }
                        "
                    >
                        <div class="media-picker-selected-preview">
                            <img
                                x-bind:src="
                                    item.thumbnail_url
                                    || item.fallback_url
                                "
                                x-bind:alt="item.name"
                                x-bind:class="
                                    item.thumbnail_mode === 'cover'
                                        ? 'object-cover'
                                        : 'object-contain p-3'
                                "
                                class="size-full"
                                x-on:error="
                                    $event.currentTarget.src =
                                        item.fallback_url
                                "
                            >
                        </div>

                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-medium text-gray-950 dark:text-white"
                                x-text="item.name"
                            ></p>

                            <p
                                x-show="showFileDetails"
                                class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400"
                            >
                                <span
                                    x-text="
                                        item.extension
                                            ?.toUpperCase()
                                    "
                                ></span>

                                <span aria-hidden="true">
                                    ·
                                </span>

                                <span
                                    x-text="
                                        formatBytes(
                                            item.size || 0
                                        )
                                    "
                                ></span>
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <button
                                x-show="
                                    replaceable
                                    && (
                                        ! multiple
                                        || selectedItems.length === 1
                                    )
                                "
                                type="button"
                                class="media-picker-icon-button"
                                x-on:click="
                                    openPicker({
                                        replaceIndex: index,
                                    })
                                "
                                aria-label="Replace media"
                            >
                                <x-lucide-refresh-cw
                                    class="size-4"
                                    stroke-width="1.2"
                                />
                            </button>

                            <button
                                x-show="removable"
                                type="button"
                                class="media-picker-icon-button is-danger"
                                x-on:click="removeSelected(index)"
                                aria-label="Remove media"
                            >
                                <x-lucide-x
                                    class="size-4"
                                    stroke-width="1.2"
                                />
                            </button>

                            <span
                                x-show="
                                    reorderable
                                    && selectedItems.length > 1
                                "
                                class="media-picker-drag-handle"
                                aria-hidden="true"
                            >
                                <x-lucide-grip-vertical
                                    class="size-4"
                                    stroke-width="1.2"
                                />
                            </span>
                        </div>
                    </article>
                </template>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-filament::button
                    type="button"
                    size="sm"
                    color="gray"
                    icon="heroicon-m-photo"
                    x-on:click="openPicker()"
                >
                    <span
                        x-text="
                            multiple
                                ? 'Manage selection'
                                : 'Change media'
                        "
                    ></span>
                </x-filament::button>

                <button
                    x-show="removable"
                    type="button"
                    class="text-xs font-medium text-danger-600 hover:text-danger-700 dark:text-danger-400"
                    x-on:click="clearSelection()"
                >
                    Clear
                </button>

                <span
                    x-show="multiple"
                    class="ml-auto text-xs text-gray-500 dark:text-gray-400"
                >
                    <span
                        x-text="selectedItems.length"
                    ></span>

                    selected
                </span>
            </div>
        </div>

        <template x-teleport="body">
            <div
                x-show="modalOpen"
                x-cloak
                x-trap.inert.noscroll="modalOpen"
                x-on:keydown.escape.window="closePicker()"
                class="media-picker-modal-root"
                role="dialog"
                aria-modal="true"
                aria-label="{{ $field->getPickerHeading() }}"
            >
                <div
                    class="media-picker-backdrop"
                    x-on:click="closePicker()"
                ></div>

                <section
                    class="media-picker-modal"
                    x-on:click.stop
                >
                    <header class="media-picker-modal-header">
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-base font-semibold text-gray-950 dark:text-white">
                                {{ $field->getPickerHeading() }}
                            </h2>

                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                <span
                                    x-show="multiple"
                                    x-text="
                                        maxItems
                                            ? `Choose up to ${maxItems} items`
                                            : 'Choose one or more items'
                                    "
                                ></span>

                                <span
                                    x-show="! multiple"
                                >
                                    Choose one item
                                </span>
                            </p>
                        </div>

                        <button
                            type="button"
                            class="media-picker-icon-button"
                            x-on:click="closePicker()"
                            aria-label="Close picker"
                        >
                            <x-lucide-x
                                class="size-5"
                                stroke-width="1.2"
                            />
                        </button>
                    </header>

                    <div class="media-picker-toolbar">
                        <div
                            x-show="searchable"
                            class="min-w-0 flex-1"
                        >
                            <x-filament::input.wrapper
                                prefix-icon="heroicon-m-magnifying-glass"
                            >
                                <x-filament::input
                                    type="search"
                                    x-model="search"
                                    x-on:input.debounce.300ms="
                                        browse({
                                            reset: true,
                                        })
                                    "
                                    placeholder="Search media"
                                />
                            </x-filament::input.wrapper>
                        </div>

                        <select
                            x-show="acceptedKinds.length !== 1"
                            x-model="kindFilter"
                            x-on:change="
                                browse({
                                    reset: true,
                                })
                            "
                            class="media-picker-native-select"
                            aria-label="Filter by media type"
                        >
                            <option value="">
                                All types
                            </option>

                            <template
                                x-for="kind in availableKinds"
                                :key="kind.value"
                            >
                                <option
                                    x-bind:value="kind.value"
                                    x-text="kind.label"
                                ></option>
                            </template>
                        </select>

                        <div class="media-picker-view-switch">
                            <button
                                type="button"
                                x-bind:class="{
                                    'is-active':
                                        viewMode === 'grid',
                                }"
                                x-bind:aria-pressed="
                                    viewMode === 'grid'
                                "
                                x-on:click="
                                    viewMode = 'grid'
                                "
                                aria-label="Grid view"
                            >
                                <x-lucide-layout-grid
                                    class="size-4"
                                    stroke-width="1.2"
                                />
                            </button>

                            <button
                                type="button"
                                x-bind:class="{
                                    'is-active':
                                        viewMode === 'list',
                                }"
                                x-bind:aria-pressed="
                                    viewMode === 'list'
                                "
                                x-on:click="
                                    viewMode = 'list'
                                "
                                aria-label="List view"
                            >
                                <x-lucide-list
                                    class="size-4"
                                    stroke-width="1.2"
                                />
                            </button>
                        </div>

                        <x-filament::button
                            x-show="uploadable"
                            type="button"
                            size="sm"
                            color="gray"
                            icon="heroicon-m-arrow-up-tray"
                            x-on:click="
                                $refs.pickerUpload.click()
                            "
                        >
                            Upload
                        </x-filament::button>

                        <input
                            x-ref="pickerUpload"
                            type="file"
                            class="hidden"
                            x-bind:accept="inputAccept"
                            x-bind:multiple="multiple"
                            x-on:change="
                                uploadFiles(
                                    $event.target.files
                                )
                            "
                        >
                    </div>

                    <div class="media-picker-breadcrumbs">
                        <button
                            type="button"
                            x-on:click="openFolder(null)"
                        >
                            <x-lucide-house
                                class="size-4"
                                stroke-width="1.2"
                            />

                            Media
                        </button>

                        <template
                            x-if="currentFolder"
                        >
                            <div class="flex min-w-0 items-center gap-1.5">
                                <x-lucide-chevron-right
                                    class="size-4 shrink-0 text-gray-400"
                                    stroke-width="1.2"
                                />

                                <button
                                    type="button"
                                    class="truncate font-medium text-gray-900 dark:text-white"
                                    x-text="currentFolder.name"
                                ></button>
                            </div>
                        </template>
                    </div>

                    <main
                        class="media-picker-browser"
                        x-on:scroll.passive="
                            maybeLoadMore($event)
                        "
                    >
                        <div
                            x-show="
                                loading
                                && items.length === 0
                            "
                            class="media-picker-skeleton-grid"
                        >
                            <template
                                x-for="index in 12"
                                :key="index"
                            >
                                <div class="media-picker-skeleton"></div>
                            </template>
                        </div>

                        <div
                            x-show="
                                ! loading
                                && folders.length === 0
                                && items.length === 0
                            "
                            class="media-picker-browser-empty"
                        >
                            <x-lucide-search-x
                                class="mx-auto size-8 text-gray-400"
                                stroke-width="1.1"
                            />

                            <p class="mt-3 text-sm font-medium text-gray-900 dark:text-white">
                                No matching media
                            </p>

                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Try another folder or search.
                            </p>
                        </div>

                        <div
                            x-show="folders.length > 0"
                            class="mb-5"
                        >
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Folders
                            </h3>

                            <div class="media-picker-folder-grid">
                                <template
                                    x-for="folder in folders"
                                    :key="folder.id"
                                >
                                    <button
                                        type="button"
                                        class="media-picker-folder-card"
                                        x-on:dblclick="
                                            openFolder(folder.slug)
                                        "
                                        x-on:click="
                                            openFolder(folder.slug)
                                        "
                                    >
                                        <img
                                            x-bind:src="
                                                folder.thumbnail_url
                                            "
                                            alt=""
                                            class="size-8 object-contain"
                                        >

                                        <span
                                            class="truncate"
                                            x-text="folder.name"
                                        ></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div
                            x-show="
                                items.length > 0
                                && viewMode === 'grid'
                            "
                            class="media-picker-grid"
                        >
                            <template
                                x-for="item in items"
                                :key="item.id"
                            >
                                <button
                                    type="button"
                                    class="media-picker-grid-card"
                                    x-bind:class="{
                                        'is-selected':
                                            isDraftSelected(
                                                item.id
                                            ),
                                    }"
                                    x-on:click="
                                        toggleDraft(item)
                                    "
                                    x-on:dblclick="
                                        chooseImmediately(item)
                                    "
                                >
                                    <div class="media-picker-grid-preview">
                                        <img
                                            x-bind:src="
                                                item.thumbnail_url
                                                || item.fallback_url
                                            "
                                            x-bind:alt="item.name"
                                            x-bind:class="
                                                item.thumbnail_mode === 'cover'
                                                    ? 'object-cover'
                                                    : 'object-contain p-5'
                                            "
                                            class="size-full"
                                            x-on:error="
                                                $event.currentTarget.src =
                                                    item.fallback_url
                                            "
                                        >
                                    </div>

                                    <div class="min-w-0 px-2.5 py-2 text-left">
                                        <p
                                            class="truncate text-sm font-medium text-gray-900 dark:text-white"
                                            x-text="item.name"
                                        ></p>

                                        <p
                                            class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            <span
                                                x-text="
                                                    item.extension
                                                        ?.toUpperCase()
                                                "
                                            ></span>

                                            <span aria-hidden="true">
                                                ·
                                            </span>

                                            <span
                                                x-text="
                                                    formatBytes(
                                                        item.size || 0
                                                    )
                                                "
                                            ></span>
                                        </p>
                                    </div>

                                    <span
                                        x-show="
                                            isDraftSelected(
                                                item.id
                                            )
                                        "
                                        class="media-picker-check"
                                    >
                                        <x-lucide-check
                                            class="size-4"
                                            stroke-width="2"
                                        />
                                    </span>
                                </button>
                            </template>
                        </div>

                        <div
                            x-show="
                                items.length > 0
                                && viewMode === 'list'
                            "
                            class="media-picker-list"
                        >
                            <template
                                x-for="item in items"
                                :key="item.id"
                            >
                                <button
                                    type="button"
                                    class="media-picker-list-row"
                                    x-bind:class="{
                                        'is-selected':
                                            isDraftSelected(
                                                item.id
                                            ),
                                    }"
                                    x-on:click="
                                        toggleDraft(item)
                                    "
                                    x-on:dblclick="
                                        chooseImmediately(item)
                                    "
                                >
                                    <img
                                        x-bind:src="
                                            item.thumbnail_url
                                            || item.fallback_url
                                        "
                                        x-bind:alt="item.name"
                                        class="size-10 rounded-lg object-contain"
                                    >

                                    <div class="min-w-0 flex-1 text-left">
                                        <p
                                            class="truncate text-sm font-medium text-gray-900 dark:text-white"
                                            x-text="item.name"
                                        ></p>

                                        <p
                                            class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400"
                                            x-text="
                                                item.mime_type
                                            "
                                        ></p>
                                    </div>

                                    <span
                                        class="hidden text-xs text-gray-500 sm:block"
                                        x-text="
                                            formatBytes(
                                                item.size || 0
                                            )
                                        "
                                    ></span>

                                    <span
                                        x-show="
                                            isDraftSelected(
                                                item.id
                                            )
                                        "
                                        class="media-picker-list-check"
                                    >
                                        <x-lucide-check
                                            class="size-4"
                                            stroke-width="2"
                                        />
                                    </span>
                                </button>
                            </template>
                        </div>

                        <div
                            x-show="
                                loading
                                && items.length > 0
                            "
                            class="flex justify-center py-5"
                        >
                            <x-filament::loading-indicator
                                class="size-6"
                            />
                        </div>
                    </main>

                    <div
                        x-show="uploads.length > 0"
                        class="media-picker-upload-dock"
                    >
                        <template
                            x-for="upload in uploads"
                            :key="upload.id"
                        >
                            <div class="media-picker-upload-row">
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-xs font-medium text-gray-900 dark:text-white"
                                        x-text="upload.file.name"
                                    ></p>

                                    <div class="mt-1 h-1 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                                        <div
                                            class="h-full rounded-full bg-primary-600"
                                            x-bind:style="{
                                                width:
                                                    `${upload.progress}%`,
                                            }"
                                        ></div>
                                    </div>

                                    <p
                                        class="mt-1 truncate text-[11px] text-gray-500 dark:text-gray-400"
                                        x-text="
                                            upload.error
                                            || upload.label
                                        "
                                    ></p>
                                </div>

                                <button
                                    x-show="upload.error"
                                    type="button"
                                    class="media-picker-icon-button is-danger"
                                    x-on:click="
                                        retryUpload(upload)
                                    "
                                    aria-label="Retry upload"
                                >
                                    <x-lucide-refresh-cw
                                        class="size-4"
                                        stroke-width="1.2"
                                    />
                                </button>
                            </div>
                        </template>
                    </div>

                    <footer class="media-picker-modal-footer">
                        <div class="min-w-0 flex-1 text-xs text-gray-500 dark:text-gray-400">
                            <span
                                x-text="
                                    `${draftItems.length} selected`
                                "
                            ></span>

                            <span
                                x-show="maxItems"
                                x-text="
                                    ` of ${maxItems}`
                                "
                            ></span>
                        </div>

                        <x-filament::button
                            type="button"
                            color="gray"
                            x-on:click="closePicker()"
                        >
                            Cancel
                        </x-filament::button>

                        <x-filament::button
                            type="button"
                            x-bind:disabled="
                                ! canConfirm()
                            "
                            x-on:click="confirmSelection()"
                        >
                            Use selected
                        </x-filament::button>
                    </footer>
                </section>
            </div>
        </template>
    </div>
</x-dynamic-component>
