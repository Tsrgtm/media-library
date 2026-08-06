@props([
    'isTrash' => false,
])

<div
    x-show="selected.length > 0"
    x-cloak
    data-media-toolbar
    class="media-drive-dock fixed bottom-5 left-1/2 z-50 flex max-w-[calc(100vw-2rem)] -translate-x-1/2 flex-wrap items-center justify-center gap-2 rounded-2xl border border-zinc-200 bg-white/95 p-2 shadow-2xl backdrop-blur dark:border-white/10 dark:bg-zinc-950/95"
>
    <div class="flex items-center gap-2 px-2">
        <span class="text-sm font-semibold text-zinc-900 dark:text-white">
            <span x-text="selected.length"></span>
            selected
        </span>

        <button
            type="button"
            class="text-xs text-zinc-500 hover:text-zinc-950 dark:hover:text-white"
            x-on:click="clearSelection()"
        >
            Clear
        </button>
    </div>

    <div class="h-7 w-px bg-zinc-200 dark:bg-white/10"></div>

    <x-filament::button
        color="gray"
        size="sm"
        icon="heroicon-m-arrow-down-tray"
        x-on:click="downloadSelected()"
    >
        Download ZIP
    </x-filament::button>

    @if (! $isTrash)
        <x-filament::button
            color="gray"
            size="sm"
            x-on:click="
                $wire.mountAction(
                    'move',
                    actionArguments()
                )
            "
        >
            <x-lucide-folder-input
                class="size-4"
                stroke-width="1.2"
            />
            Move
        </x-filament::button>

        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-m-tag"
            x-bind:disabled="
                selectedMediaIds().length === 0
            "
            x-on:click="
                $wire.mountAction('tags', {
                    mediaIds: selectedMediaIds(),
                })
            "
        >
            Tags
        </x-filament::button>

        <x-filament::button
            color="danger"
            size="sm"
            icon="heroicon-m-trash"
            x-on:click="
                $wire.mountAction(
                    'trash',
                    actionArguments()
                )
            "
        >
            Trash
        </x-filament::button>
    @else
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-m-arrow-path"
            x-on:click="
                $wire.mountAction('restore', actionArguments())
            "
        >
            Restore
        </x-filament::button>

        <x-filament::button
            color="danger"
            size="sm"
            icon="heroicon-m-trash"
            x-on:click="
                $wire.mountAction('forceDelete', actionArguments())
            "
        >
            Delete
        </x-filament::button>
    @endif
</div>
