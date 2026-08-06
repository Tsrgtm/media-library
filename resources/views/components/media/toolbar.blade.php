@props([
    'isTrash' => false,
])

<div
    data-media-toolbar
    class="sticky top-0 z-40 overflow-visible rounded-t-2xl border-b border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-950"
>
    <div class="flex flex-col gap-3 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0 flex-1">
            <x-filament::input.wrapper
                class="max-w-2xl"
                prefix-icon="heroicon-m-magnifying-glass"
            >
                <x-filament::input
                    type="search"
                    x-model.debounce.300ms="filters.search"
                    x-on:input.debounce.350ms="
                        syncQuery()
                        reload({ silent: true })
                    "
                    placeholder="Search media, folders, and tags"
                />
            </x-filament::input.wrapper>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-filament::dropdown
                placement="bottom-end"
                width="xs"
                shift
                teleport
            >
                <x-slot name="trigger">
                    <x-filament::button
                        color="gray"
                        icon="heroicon-m-adjustments-horizontal"
                    >
                        Filters

                        <span
                            x-show="activeFilterCount() > 0"
                            x-cloak
                            class="ml-1 inline-flex min-w-5 items-center justify-center rounded-full bg-primary-600 px-1.5 py-0.5 text-[10px] font-semibold text-white"
                            x-text="activeFilterCount()"
                        ></span>
                    </x-filament::button>
                </x-slot>

                <div class="w-72 max-w-[calc(100vw-1.5rem)] p-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">
                            Filters
                        </p>

                        <button
                            type="button"
                            class="rounded-md px-1.5 py-1 text-[11px] font-medium text-zinc-500 hover:bg-zinc-100 hover:text-zinc-950 dark:hover:bg-white/5 dark:hover:text-white"
                            x-show="activeFilterCount() > 0"
                            x-on:click="clearFilters()"
                        >
                            Clear all
                        </button>
                    </div>

                    <div class="mt-3 space-y-3">
                        <div>
                            <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                                Type
                            </p>

                            <div class="grid grid-cols-2 gap-1">
                                <template
                                    x-for="option in filterTypeOptions"
                                    :key="option.value"
                                >
                                    <button
                                        type="button"
                                        class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-sm transition"
                                        x-bind:class="filters.type === option.value
                                            ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300'
                                            : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5'"
                                        x-on:click="setFilter('type', option.value)"
                                    >
                                        <span x-text="option.label"></span>

                                        <x-lucide-check
                                            x-show="filters.type === option.value"
                                            class="size-3.5"
                                            stroke-width="1.5"
                                        />
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div>
                            <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                                Status
                            </p>

                            <div class="grid grid-cols-2 gap-1">
                                <template
                                    x-for="option in filterStatusOptions"
                                    :key="option.value"
                                >
                                    <button
                                        type="button"
                                        class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-sm transition"
                                        x-bind:class="filters.status === option.value
                                            ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300'
                                            : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5'"
                                        x-on:click="setFilter('status', option.value)"
                                    >
                                        <span x-text="option.label"></span>

                                        <x-lucide-check
                                            x-show="filters.status === option.value"
                                            class="size-3.5"
                                            stroke-width="1.5"
                                        />
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div>
                            <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                                Sort
                            </p>

                            <div class="space-y-0.5">
                                <template
                                    x-for="option in filterSortOptions"
                                    :key="option.value"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-sm transition"
                                        x-bind:class="filters.sort === option.value
                                            ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300'
                                            : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5'"
                                        x-on:click="setFilter('sort', option.value)"
                                    >
                                        <span x-text="option.label"></span>

                                        <x-lucide-check
                                            x-show="filters.sort === option.value"
                                            class="size-3.5"
                                            stroke-width="1.5"
                                        />
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </x-filament::dropdown>

            <x-filament::button
                color="gray"
                icon="heroicon-m-trash"
                x-on:click="navigateTrash()"
            >
                {{ $isTrash ? 'Exit trash' : 'Trash' }}
            </x-filament::button>

            @if ($isTrash)
                {{ $this->emptyTrashAction }}
            @endif

            <div class="inline-flex rounded-lg border border-zinc-200 p-1 dark:border-white/10">
                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-md transition"
                    x-bind:class="viewMode === 'grid'
                        ? 'bg-zinc-100 text-zinc-950 dark:bg-white/10 dark:text-white'
                        : 'text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-white/5'"
                    x-on:click="setViewMode('grid')"
                    x-bind:aria-pressed="viewMode === 'grid'"
                    aria-label="Grid view"
                >
                    <x-lucide-layout-grid class="size-4" stroke-width="1.2" />
                </button>

                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-md transition"
                    x-bind:class="viewMode === 'list'
                        ? 'bg-zinc-100 text-zinc-950 dark:bg-white/10 dark:text-white'
                        : 'text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-white/5'"
                    x-on:click="setViewMode('list')"
                    x-bind:aria-pressed="viewMode === 'list'"
                    aria-label="List view"
                >
                    <x-lucide-list class="size-4" stroke-width="1.2" />
                </button>
            </div>
        </div>
    </div>

    <div
        x-show="
            currentFolder
            || filters.search
            || activeFilterCount() > 0
        "
        x-cloak
        class="flex flex-wrap items-center gap-1.5 border-t border-zinc-100 px-4 py-2 dark:border-white/5"
    >
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5"
            x-on:click="navigateRoot()"
        >
            <x-lucide-house class="size-4" stroke-width="1.2" />
            Media
        </button>

        <template x-if="currentFolder">
            <div class="flex items-center gap-1.5">
                <x-lucide-chevron-right
                    class="size-4 text-zinc-400"
                    stroke-width="1.2"
                />

                <span
                    class="text-sm font-medium text-zinc-900 dark:text-white"
                    x-text="currentFolder.name"
                ></span>
            </div>
        </template>

        <template x-if="filters.type">
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2 py-1 text-xs text-zinc-700 hover:bg-zinc-200 dark:bg-white/10 dark:text-zinc-200 dark:hover:bg-white/15"
                x-on:click="removeFilter('type')"
            >
                <span x-text="filterLabel(filterTypeOptions, filters.type)"></span>
                <x-lucide-x class="size-3" stroke-width="1.5" />
            </button>
        </template>

        <template x-if="filters.status">
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2 py-1 text-xs text-zinc-700 hover:bg-zinc-200 dark:bg-white/10 dark:text-zinc-200 dark:hover:bg-white/15"
                x-on:click="removeFilter('status')"
            >
                <span x-text="filterLabel(filterStatusOptions, filters.status)"></span>
                <x-lucide-x class="size-3" stroke-width="1.5" />
            </button>
        </template>

        <template x-if="filters.sort !== 'newest'">
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2 py-1 text-xs text-zinc-700 hover:bg-zinc-200 dark:bg-white/10 dark:text-zinc-200 dark:hover:bg-white/15"
                x-on:click="removeFilter('sort')"
            >
                <span x-text="filterLabel(filterSortOptions, filters.sort)"></span>
                <x-lucide-x class="size-3" stroke-width="1.5" />
            </button>
        </template>

        <button
            type="button"
            x-show="activeFilterCount() > 1"
            class="rounded px-1.5 py-1 text-[10px] font-medium text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-white/5 dark:hover:text-white"
            x-on:click="clearFilters()"
        >
            Clear all
        </button>
    </div>
</div>
