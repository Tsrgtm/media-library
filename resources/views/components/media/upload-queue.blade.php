<div
    x-show="uploads.length > 0"
    x-cloak
    class="fixed bottom-4 right-4 z-[60] w-[min(30rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-2xl dark:border-white/10 dark:bg-zinc-950"
>
    <button
        type="button"
        class="flex w-full items-center justify-between border-b border-zinc-200 px-4 py-3 text-left dark:border-white/10"
        x-on:click="uploadQueueOpen = ! uploadQueueOpen"
    >
        <span class="text-sm font-semibold text-zinc-900 dark:text-white">
            Uploads
            (<span x-text="uploads.length"></span>)
        </span>

        <x-lucide-chevron-down
            class="size-4 transition"
            x-bind:class="{ 'rotate-180': uploadQueueOpen }"
            stroke-width="1.2"
        />
    </button>

    <div
        x-show="uploadQueueOpen"
        class="max-h-96 space-y-2 overflow-y-auto p-3"
    >
        <template x-for="upload in uploads" :key="upload.localId">
            <div class="flex items-center gap-3 rounded-xl border border-zinc-200 p-2.5 dark:border-white/10">
                <img
                    x-bind:src="upload.previewUrl"
                    alt=""
                    class="size-12 shrink-0 rounded-lg object-contain p-1"
                >

                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-medium text-zinc-900 dark:text-white"
                                x-text="upload.file.name"
                            ></p>

                            <p
                                x-show="upload.failed"
                                class="mt-0.5 truncate text-xs text-red-600 dark:text-red-400"
                                x-bind:title="upload.errorMessage || upload.statusLabel"
                                x-text="upload.errorMessage || upload.statusLabel"
                            ></p>
                        </div>

                        <span
                            x-show="! upload.failed"
                            class="shrink-0 text-xs text-zinc-500"
                            x-text="upload.statusLabel"
                        ></span>
                    </div>

                    <div
                        x-show="! upload.failed"
                        class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800"
                    >
                        <div
                            class="h-full rounded-full bg-primary-600 transition-[width] duration-150"
                            x-bind:style="{ width: `${Math.max(0, Math.min(100, upload.progress))}%` }"
                        ></div>
                    </div>

                    <div
                        x-show="! upload.failed"
                        class="mt-1.5 flex items-center justify-between gap-2 text-xs text-zinc-500"
                    >
                        <span
                            x-text="`${formatBytes(upload.uploadedBytes)} / ${formatBytes(upload.file.size)}`"
                        ></span>

                        <span>
                            <span x-text="formatSpeed(upload.speed)"></span>
                            ·
                            <span x-text="Math.round(upload.progress) + '%'"></span>
                        </span>
                    </div>

                    <div
                        x-show="upload.failed"
                        class="mt-2 flex items-center gap-2"
                    >
                        <x-filament::button
                            color="gray"
                            size="xs"
                            icon="heroicon-m-arrow-path"
                            x-on:click="retryUpload(upload)"
                        >
                            Retry
                        </x-filament::button>

                        <x-filament::button
                            color="danger"
                            size="xs"
                            icon="heroicon-m-trash"
                            x-on:click="removeFailedUpload(upload)"
                        >
                            Remove
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
