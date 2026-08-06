@props([
    'isTrash' => false,
])

<div
    x-show="contextMenu.open"
    x-cloak
    data-media-context
    x-on:click.outside="contextMenu.open = false"
    x-bind:style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }"
    class="fixed z-[80] min-w-56 rounded-xl border border-zinc-200 bg-white p-1.5 shadow-xl dark:border-white/10 dark:bg-zinc-950"
>
    <template x-if="selected.length === 1">
        <div>
            <button type="button" class="media-context-item" x-on:click="openContextItem()">
                <x-lucide-folder-open class="size-4" stroke-width="1.2" />
                <span>Open</span>
            </button>

            <button
                type="button"
                class="media-context-item"
                x-show="contextMenu.item?.type === 'media' && ! filters.trash"
                x-on:click="
                    contextMenu.open = false
                    $wire.mountAction('edit', {
                        id: contextMenu.item.id,
                    })
                "
            >
                <x-lucide-square-pen class="size-4" stroke-width="1.2" />
                <span>Edit details</span>
            </button>

            <button
                type="button"
                class="media-context-item"
                x-on:click="
                    contextMenu.open = false
                    $wire.mountAction('rename', {
                        type: contextMenu.item.type,
                        id: contextMenu.item.id,
                        name: contextMenu.item.name,
                    })
                "
            >
                <x-lucide-pencil class="size-4" stroke-width="1.2" />
                <span>Rename</span>
            </button>
        </div>
    </template>

    <button type="button" class="media-context-item" x-on:click="downloadSelected()">
        <x-lucide-download class="size-4" stroke-width="1.2" />
        <span x-text="selected.length > 1 ? 'Download selected as ZIP' : 'Download'"></span>
    </button>

    @if (! $isTrash)
        <button
            type="button"
            class="media-context-item"
            x-on:click="
                contextMenu.open = false
                $wire.mountAction('move', actionArguments())
            "
        >
            <x-lucide-folder-input class="size-4" stroke-width="1.2" />
            <span>Move</span>
        </button>

        <button
            type="button"
            class="media-context-item"
            x-show="selectedMediaIds().length > 0"
            x-on:click="
                contextMenu.open = false
                $wire.mountAction('tags', { mediaIds: selectedMediaIds() })
            "
        >
            <x-lucide-tag class="size-4" stroke-width="1.2" />
            <span>Tags</span>
        </button>

        <div class="my-1 border-t border-zinc-200 dark:border-white/10"></div>

        <button
            type="button"
            class="media-context-item text-red-600"
            x-on:click="
                contextMenu.open = false
                $wire.mountAction('trash', actionArguments())
            "
        >
            <x-lucide-trash-2 class="size-4" stroke-width="1.2" />
            <span>Move to trash</span>
        </button>
    @else
        <button
            type="button"
            class="media-context-item"
            x-on:click="
                contextMenu.open = false
                $wire.mountAction('restore', actionArguments())
            "
        >
            <x-lucide-rotate-ccw class="size-4" stroke-width="1.2" />
            <span>Restore</span>
        </button>

        <button
            type="button"
            class="media-context-item text-red-600"
            x-on:click="
                contextMenu.open = false
                $wire.mountAction('forceDelete', actionArguments())
            "
        >
            <x-lucide-trash-2 class="size-4" stroke-width="1.2" />
            <span>Delete permanently</span>
        </button>
    @endif
</div>
