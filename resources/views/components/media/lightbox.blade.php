<div
    x-show="lightbox.open"
    x-cloak
    x-trap.inert.noscroll="lightbox.open"
    x-on:keydown.escape.window="closeLightbox()"
    x-on:keydown.arrow-left.window="
        if (! lightbox.isEditingControl) {
            previousPreview()
        }
    "
    x-on:keydown.arrow-right.window="
        if (! lightbox.isEditingControl) {
            nextPreview()
        }
    "
    class="fixed inset-0 z-[100] flex flex-col bg-zinc-950/98 text-white"
    role="dialog"
    aria-modal="true"
>
    <header class="flex shrink-0 items-center gap-3 border-b border-white/10 px-3 py-2.5 sm:px-4">
        <div class="min-w-0 flex-1">
            <p
                class="truncate text-sm font-semibold"
                x-text="lightbox.item?.name"
            ></p>

            <p class="mt-0.5 truncate text-xs text-zinc-400">
                <span
                    x-text="
                        lightbox.item?.mime_type
                        || lightbox.item?.kind
                    "
                ></span>

                <span aria-hidden="true"> · </span>

                <span
                    x-text="
                        formatBytes(
                            lightbox.item?.size || 0
                        )
                    "
                ></span>
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-1">
            <a
                x-bind:href="lightbox.item?.url"
                download
                class="media-preview-icon-btn"
                aria-label="Download file"
            >
                <x-lucide-download
                    class="size-5"
                    stroke-width="1.2"
                />
            </a>

            <button
                type="button"
                class="media-preview-icon-btn"
                x-on:click="closeLightbox()"
                aria-label="Close preview"
            >
                <x-lucide-x
                    class="size-5"
                    stroke-width="1.2"
                />
            </button>
        </div>
    </header>

    <div class="relative flex min-h-0 flex-1 overflow-hidden">
        <button
            type="button"
            class="absolute left-2 top-1/2 z-20 inline-flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/45 text-white shadow-lg backdrop-blur hover:bg-black/65 sm:left-4"
            x-on:click="previousPreview()"
            aria-label="Previous file"
        >
            <x-lucide-chevron-left
                class="size-6"
                stroke-width="1.2"
            />
        </button>

        <main
            x-ref="previewViewport"
            class="relative flex min-h-0 min-w-0 flex-1 items-center justify-center overflow-hidden p-3 sm:px-16 sm:py-5"
            x-on:pointerdown.stop
            x-on:pointerup.stop
            x-on:click.stop
        >
            <div
                x-show="lightbox.loading"
                class="absolute inset-0 z-30 grid place-items-center bg-zinc-950/72"
            >
                <div class="text-center">
                    <x-filament::loading-indicator
                        class="mx-auto size-8"
                    />

                    <p class="mt-3 text-sm text-zinc-300">
                        Preparing preview…
                    </p>
                </div>
            </div>

            <div
                x-show="lightbox.mode === 'image'"
                x-ref="previewImageViewport"
                class="media-image-preview-viewport"
                x-on:wheel="handlePreviewWheel($event)"
                x-on:pointerdown="
                    beginPreviewPan(
                        $event,
                        $refs.previewImageViewport
                    )
                "
                x-on:pointermove.window="
                    movePreviewPan(
                        $event,
                        $refs.previewImageViewport
                    )
                "
                x-on:pointerup.window="
                    endPreviewPan(
                        $refs.previewImageViewport
                    )
                "
                x-on:pointercancel.window="
                    endPreviewPan(
                        $refs.previewImageViewport
                    )
                "
            >
                <div
                    x-ref="previewZoomTarget"
                    class="media-preview-zoom-stage"
                >
                    <img
                        x-ref="previewImage"
                        alt=""
                        draggable="false"
                        class="block select-none object-contain"
                    >
                </div>
            </div>

            <div
                x-show="lightbox.mode === 'video'"
                class="w-full max-w-6xl overflow-hidden rounded-2xl bg-black shadow-2xl ring-1 ring-white/10"
                x-on:pointerdown.stop
                x-on:pointerup.stop
                x-on:click.stop
                x-on:wheel.stop
            >
                <video
                    x-ref="previewVideo"
                    class="video-js vjs-default-skin vjs-big-play-centered"
                ></video>
            </div>

            <div
                x-show="lightbox.mode === 'audio'"
                class="w-full max-w-2xl overflow-hidden rounded-2xl border border-white/10 bg-zinc-900/85 p-5 shadow-2xl backdrop-blur"
                x-on:pointerdown.stop
                x-on:pointerup.stop
                x-on:click.stop
                x-on:wheel.stop
            >
                <div class="mb-4 flex items-center gap-3">
                    <div class="grid size-11 shrink-0 place-items-center rounded-xl bg-white/10">
                        <x-lucide-music-2
                            class="size-5 text-zinc-200"
                            stroke-width="1.2"
                        />
                    </div>

                    <div class="min-w-0">
                        <p
                            class="truncate text-sm font-semibold text-white"
                            x-text="lightbox.item?.name"
                        ></p>

                        <p class="mt-0.5 text-xs text-zinc-400">
                            Audio preview
                        </p>
                    </div>
                </div>

                <audio
                    x-ref="previewAudio"
                    class="video-js vjs-default-skin vjs-audio-only"
                ></audio>
            </div>

            <div
                x-show="lightbox.mode === 'pdf'"
                x-ref="previewDocumentViewport"
                class="media-document-viewport"
                x-on:wheel="handlePreviewWheel($event)"
                x-on:pointerdown="
                    beginPreviewPan(
                        $event,
                        $refs.previewDocumentViewport
                    )
                "
                x-on:pointermove.window="
                    movePreviewPan(
                        $event,
                        $refs.previewDocumentViewport
                    )
                "
                x-on:pointerup.window="
                    endPreviewPan(
                        $refs.previewDocumentViewport
                    )
                "
                x-on:pointercancel.window="
                    endPreviewPan(
                        $refs.previewDocumentViewport
                    )
                "
            >
                <div class="media-pdf-page">
                    <canvas
                        x-ref="previewPdfCanvas"
                    ></canvas>
                </div>
            </div>

            <div
                x-show="
                    [
                        'document',
                        'spreadsheet',
                        'code',
                    ].includes(lightbox.mode)
                "
                x-ref="previewDocumentViewport"
                class="media-document-viewport"
                x-on:wheel="handlePreviewWheel($event)"
                x-on:pointerdown="
                    beginPreviewPan(
                        $event,
                        $refs.previewDocumentViewport
                    )
                "
                x-on:pointermove.window="
                    movePreviewPan(
                        $event,
                        $refs.previewDocumentViewport
                    )
                "
                x-on:pointerup.window="
                    endPreviewPan(
                        $refs.previewDocumentViewport
                    )
                "
                x-on:pointercancel.window="
                    endPreviewPan(
                        $refs.previewDocumentViewport
                    )
                "
            >
                <article
                    x-ref="previewDocumentContent"
                    class="media-document-content"
                ></article>
            </div>

            <div
                x-show="lightbox.mode === 'unsupported'"
                class="max-w-lg rounded-2xl border border-white/10 bg-white/5 p-8 text-center shadow-2xl"
            >
                <img
                    x-bind:src="
                        lightbox.item?.thumbnail_url
                    "
                    alt=""
                    class="mx-auto size-20 object-contain"
                >

                <h3 class="mt-4 text-base font-semibold">
                    Preview unavailable
                </h3>

                <p
                    class="mt-2 text-sm text-zinc-400"
                    x-text="
                        lightbox.error
                        || 'This format cannot be rendered safely in the browser.'
                    "
                ></p>

                <a
                    x-bind:href="lightbox.item?.url"
                    download
                    class="mt-5 inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-zinc-950 hover:bg-zinc-200"
                >
                    <x-lucide-download
                        class="size-4"
                        stroke-width="1.2"
                    />

                    Download file
                </a>
            </div>

            <div
                x-show="lightbox.zoomable"
                x-cloak
                class="media-preview-floating-zoom"
            >
                <button
                    type="button"
                    class="media-preview-icon-btn"
                    x-on:click="zoomPreview(-0.15)"
                    aria-label="Zoom out"
                >
                    <x-lucide-zoom-out
                        class="size-4"
                        stroke-width="1.2"
                    />
                </button>

                <button
                    type="button"
                    class="min-w-14 rounded-lg px-2 py-1.5 text-xs font-medium text-white hover:bg-white/10"
                    x-on:click="resetPreviewZoom()"
                    x-text="
                        `${Math.round(
                            lightbox.zoom * 100
                        )}%`
                    "
                ></button>

                <button
                    type="button"
                    class="media-preview-icon-btn"
                    x-on:click="zoomPreview(0.15)"
                    aria-label="Zoom in"
                >
                    <x-lucide-zoom-in
                        class="size-4"
                        stroke-width="1.2"
                    />
                </button>
            </div>
        </main>

        <button
            type="button"
            class="absolute right-2 top-1/2 z-20 inline-flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/45 text-white shadow-lg backdrop-blur hover:bg-black/65 sm:right-4"
            x-on:click="nextPreview()"
            aria-label="Next file"
        >
            <x-lucide-chevron-right
                class="size-6"
                stroke-width="1.2"
            />
        </button>
    </div>

    <footer
        x-show="lightbox.mode === 'pdf'"
        class="flex shrink-0 items-center justify-center gap-2 border-t border-white/10 px-4 py-2.5"
    >
        <button
            type="button"
            class="media-preview-icon-btn"
            x-bind:disabled="
                lightbox.pdfPage <= 1
            "
            x-on:click="
                setPreviewPdfPage(
                    lightbox.pdfPage - 1
                )
            "
        >
            <x-lucide-chevron-left
                class="size-4"
                stroke-width="1.2"
            />
        </button>

        <label class="flex items-center gap-2 text-xs text-zinc-300">
            Page

            <input
                type="number"
                min="1"
                x-bind:max="lightbox.pdfPages"
                x-model.number="lightbox.pdfPage"
                x-on:change="
                    setPreviewPdfPage(
                        lightbox.pdfPage
                    )
                "
                class="w-16 rounded-md border border-white/10 bg-white/5 px-2 py-1 text-center text-white outline-none focus:border-white/30"
            >

            of

            <span
                x-text="lightbox.pdfPages"
            ></span>
        </label>

        <button
            type="button"
            class="media-preview-icon-btn"
            x-bind:disabled="
                lightbox.pdfPage
                >= lightbox.pdfPages
            "
            x-on:click="
                setPreviewPdfPage(
                    lightbox.pdfPage + 1
                )
            "
        >
            <x-lucide-chevron-right
                class="size-4"
                stroke-width="1.2"
            />
        </button>
    </footer>
</div>
