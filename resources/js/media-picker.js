import * as tus from 'tus-js-client';

const normalizeIds = (state, multiple) => {
    const values = Array.isArray(state)
        ? state
        : [state];

    const ids = Array.from(
        new Set(
            values
                .map((value) => Number(value))
                .filter(
                    (value) =>
                        Number.isInteger(value)
                        && value > 0,
                ),
        ),
    );

    return multiple ? ids : (ids[0] ?? null);
};

const wait = (milliseconds) =>
    new Promise((resolve) =>
        setTimeout(resolve, milliseconds),
    );

export function mediaPicker(options) {
    return {
        id: options.id,
        state: options.state,
        multiple: Boolean(options.multiple),
        minItems: options.minItems === null
            ? null
            : Number(options.minItems),
        maxItems: options.maxItems === null
            ? null
            : Number(options.maxItems),
        acceptedKinds: options.acceptedKinds ?? [],
        acceptedExtensions:
            options.acceptedExtensions ?? [],
        acceptedMimeTypes:
            options.acceptedMimeTypes ?? [],
        uploadable: Boolean(options.uploadable),
        searchable: Boolean(options.searchable),
        reorderable: Boolean(options.reorderable),
        removable: Boolean(options.removable),
        replaceable: Boolean(options.replaceable),
        showFolders: Boolean(options.showFolders),
        showFileDetails:
            Boolean(options.showFileDetails),
        defaultFolder: options.defaultFolder ?? null,
        browseUrl: options.browseUrl,
        resolveUrl: options.resolveUrl,
        tusEndpoint: options.tusEndpoint,
        statusUrl: options.statusUrl,
        csrfToken: options.csrfToken,
        inputAccept: options.inputAccept ?? '',

        modalOpen: false,
        loading: false,
        selectedItems: [],
        draftItems: [],
        items: [],
        folders: [],
        currentFolder: null,
        folderSlug: null,
        search: '',
        kindFilter: '',
        viewMode: 'grid',
        nextCursor: null,
        hasMore: false,
        requestGeneration: 0,
        replaceIndex: null,
        selectedDragIndex: null,
        uploads: [],

        availableKinds: [
            { value: 'image', label: 'Images' },
            { value: 'video', label: 'Videos' },
            { value: 'audio', label: 'Audio' },
            { value: 'document', label: 'Documents' },
            { value: 'archive', label: 'Archives' },
            { value: 'file', label: 'Other files' },
        ],

        async init() {
            this.state = normalizeIds(
                this.state,
                this.multiple,
            );

            if (this.acceptedKinds.length === 1) {
                this.kindFilter =
                    this.acceptedKinds[0];
            }

            await this.resolveSelection();

            this.$watch('state', async () => {
                await this.resolveSelection();
            });
        },

        stateIds() {
            return this.multiple
                ? normalizeIds(this.state, true)
                : Array.from(
                    new Set(
                        [normalizeIds(this.state, false)]
                            .filter(Boolean),
                    ),
                );
        },

        setStateFromItems(items) {
            const ids = items.map(
                (item) => Number(item.id),
            );

            this.state = this.multiple
                ? ids
                : (ids[0] ?? null);

            this.selectedItems = [...items];
        },

        async resolveSelection() {
            const ids = this.stateIds();

            if (ids.length === 0) {
                this.selectedItems = [];
                return;
            }

            try {
                const response = await this.json(
                    this.resolveUrl,
                    {
                        method: 'POST',
                        body: JSON.stringify({
                            ids,
                        }),
                    },
                );

                this.selectedItems =
                    response.media ?? [];

                const resolvedIds =
                    this.selectedItems.map(
                        (item) => item.id,
                    );

                if (
                    JSON.stringify(resolvedIds)
                    !== JSON.stringify(ids)
                ) {
                    this.state = this.multiple
                        ? resolvedIds
                        : (resolvedIds[0] ?? null);
                }
            } catch (error) {
                console.error(
                    'Unable to resolve media selection:',
                    error,
                );
            }
        },

        async openPicker({
            replaceIndex = null,
        } = {}) {
            this.replaceIndex = replaceIndex;
            this.modalOpen = true;
            this.draftItems = replaceIndex === null
                ? [...this.selectedItems]
                : [];

            if (! this.multiple) {
                this.draftItems =
                    replaceIndex === null
                        ? [...this.selectedItems]
                        : [];
            }

            this.folderSlug =
                this.defaultFolder ?? null;
            this.search = '';

            await this.$nextTick();
            await this.browse({
                reset: true,
            });
        },

        closePicker() {
            this.modalOpen = false;
            this.replaceIndex = null;
            this.uploads = this.uploads.filter(
                (upload) => upload.running,
            );
        },

        async browse({
            reset = false,
        } = {}) {
            if (
                this.loading
                || (! reset && ! this.hasMore)
            ) {
                return;
            }

            const generation = reset
                ? ++this.requestGeneration
                : this.requestGeneration;

            if (reset) {
                this.items = [];
                this.folders = [];
                this.nextCursor = null;
                this.hasMore = true;
            }

            this.loading = true;

            const url = new URL(
                this.browseUrl,
                window.location.origin,
            );

            if (this.folderSlug) {
                url.searchParams.set(
                    'folder',
                    this.folderSlug,
                );
            }

            if (this.search.trim()) {
                url.searchParams.set(
                    'search',
                    this.search.trim(),
                );
            }

            const kinds = this.kindFilter
                ? [this.kindFilter]
                : this.acceptedKinds;

            kinds.forEach((kind) => {
                url.searchParams.append(
                    'kinds[]',
                    kind,
                );
            });

            this.acceptedExtensions.forEach(
                (extension) => {
                    url.searchParams.append(
                        'extensions[]',
                        extension,
                    );
                },
            );

            this.acceptedMimeTypes.forEach(
                (mimeType) => {
                    url.searchParams.append(
                        'mime_types[]',
                        mimeType,
                    );
                },
            );

            url.searchParams.set(
                'show_folders',
                this.showFolders ? '1' : '0',
            );

            if (! reset && this.nextCursor) {
                url.searchParams.set(
                    'cursor',
                    this.nextCursor,
                );
            }

            try {
                const response =
                    await this.json(url.toString());

                if (generation !== this.requestGeneration) {
                    return;
                }

                this.currentFolder =
                    response.current_folder ?? null;

                if (reset) {
                    this.folders =
                        response.folders ?? [];
                    this.items =
                        response.media ?? [];
                } else {
                    this.items = this.uniqueItems([
                        ...this.items,
                        ...(response.media ?? []),
                    ]);
                }

                this.nextCursor =
                    response.next_cursor ?? null;

                this.hasMore =
                    Boolean(response.has_more);
            } catch (error) {
                console.error(
                    'Unable to browse media:',
                    error,
                );
            } finally {
                if (generation === this.requestGeneration) {
                    this.loading = false;
                }
            }
        },

        async openFolder(slug) {
            this.folderSlug = slug;
            this.search = '';

            await this.browse({
                reset: true,
            });
        },

        maybeLoadMore(event) {
            const element = event.currentTarget;

            if (
                element.scrollTop
                + element.clientHeight
                >= element.scrollHeight - 160
            ) {
                this.browse();
            }
        },

        isDraftSelected(id) {
            return this.draftItems.some(
                (item) =>
                    Number(item.id) === Number(id),
            );
        },

        toggleDraft(item) {
            const existingIndex =
                this.draftItems.findIndex(
                    (entry) =>
                        Number(entry.id)
                        === Number(item.id),
                );

            if (! this.multiple) {
                this.draftItems =
                    existingIndex >= 0
                        ? []
                        : [item];

                return;
            }

            if (existingIndex >= 0) {
                this.draftItems.splice(
                    existingIndex,
                    1,
                );

                return;
            }

            if (
                this.maxItems
                && this.draftItems.length
                    >= this.maxItems
            ) {
                return;
            }

            this.draftItems.push(item);
        },

        chooseImmediately(item) {
            if (this.multiple) {
                this.toggleDraft(item);
                return;
            }

            this.draftItems = [item];
            this.confirmSelection();
        },

        canConfirm() {
            const count = this.draftItems.length;

            if (
                this.minItems !== null
                && count < this.minItems
            ) {
                return false;
            }

            if (
                this.maxItems !== null
                && count > this.maxItems
            ) {
                return false;
            }

            return this.multiple
                ? true
                : count <= 1;
        },

        confirmSelection() {
            if (! this.canConfirm()) {
                return;
            }

            if (
                this.replaceIndex !== null
                && this.selectedItems[
                    this.replaceIndex
                ]
                && this.draftItems[0]
            ) {
                const items = [
                    ...this.selectedItems,
                ];

                items.splice(
                    this.replaceIndex,
                    1,
                    this.draftItems[0],
                );

                this.setStateFromItems(
                    this.multiple
                        ? this.uniqueItems(items)
                        : [this.draftItems[0]],
                );
            } else {
                this.setStateFromItems(
                    this.multiple
                        ? this.draftItems
                        : this.draftItems.slice(0, 1),
                );
            }

            this.closePicker();
        },

        removeSelected(index) {
            const items = [
                ...this.selectedItems,
            ];

            items.splice(index, 1);
            this.setStateFromItems(items);
        },

        clearSelection() {
            this.setStateFromItems([]);
        },

        beginSelectedDrag(event, index) {
            this.selectedDragIndex = index;
            event.dataTransfer.effectAllowed =
                'move';

            event.dataTransfer.setData(
                'text/plain',
                String(index),
            );
        },

        finishSelectedDrag(index) {
            if (
                this.selectedDragIndex === null
                || this.selectedDragIndex === index
            ) {
                this.selectedDragIndex = null;
                return;
            }

            const items = [
                ...this.selectedItems,
            ];

            const [moved] = items.splice(
                this.selectedDragIndex,
                1,
            );

            items.splice(index, 0, moved);

            this.selectedDragIndex = null;
            this.setStateFromItems(items);
        },

        async uploadFiles(fileList) {
            const files = Array.from(
                fileList ?? [],
            );

            if (files.length === 0) {
                return;
            }

            const concurrency = Math.min(
                3,
                files.length,
            );

            let nextIndex = 0;

            const worker = async () => {
                while (nextIndex < files.length) {
                    const index = nextIndex++;
                    await this.uploadFile(
                        files[index],
                    );
                }
            };

            await Promise.all(
                Array.from(
                    { length: concurrency },
                    () => worker(),
                ),
            );

            if (this.$refs.pickerUpload) {
                this.$refs.pickerUpload.value = '';
            }

            await this.browse({
                reset: true,
            });
        },

        uploadFile(file) {
            return new Promise((resolve) => {
                const uploadState = {
                    id: crypto.randomUUID(),
                    file,
                    progress: 0,
                    label: 'Preparing…',
                    error: null,
                    running: true,
                    mediaId: null,
                };

                this.uploads.unshift(uploadState);

                const upload = new tus.Upload(
                    file,
                    {
                        endpoint: this.tusEndpoint,
                        retryDelays: [
                            0,
                            1000,
                            3000,
                            5000,
                        ],
                        uploadDataDuringCreation:
                            file.size
                            <= 10 * 1024 * 1024,
                        removeFingerprintOnSuccess:
                            true,
                        headers: {
                            'X-CSRF-TOKEN':
                                this.csrfToken,
                            Accept:
                                'application/json',
                        },
                        metadata: {
                            filename: file.name,
                            filetype:
                                file.type
                                || 'application/octet-stream',
                            folder_slug:
                                this.folderSlug ?? '',
                        },
                        onAfterResponse: (
                            _request,
                            response,
                        ) => {
                            const mediaId = Number(
                                response.getHeader(
                                    'Media-Id',
                                ),
                            );

                            if (mediaId) {
                                uploadState.mediaId =
                                    mediaId;
                            }
                        },
                        onProgress: (
                            uploaded,
                            total,
                        ) => {
                            uploadState.progress =
                                total > 0
                                    ? Math.round(
                                        uploaded
                                        / total
                                        * 100,
                                    )
                                    : 0;

                            uploadState.label =
                                `${uploadState.progress}%`;
                        },
                        onError: (error) => {
                            uploadState.error =
                                error?.message
                                || 'Upload failed';

                            uploadState.label =
                                'Failed';

                            uploadState.running =
                                false;

                            resolve();
                        },
                        onSuccess: async () => {
                            uploadState.progress = 100;
                            uploadState.label =
                                'Processing…';

                            const media =
                                await this.waitForMedia(
                                    uploadState.mediaId,
                                );

                            uploadState.running = false;

                            if (media) {
                                uploadState.label =
                                    'Ready';

                                if (
                                    this.acceptsItem(media)
                                ) {
                                    if (this.multiple) {
                                        if (
                                            ! this.maxItems
                                            || this.draftItems.length
                                                < this.maxItems
                                        ) {
                                            this.draftItems =
                                                this.uniqueItems([
                                                    ...this.draftItems,
                                                    media,
                                                ]);
                                        }
                                    } else {
                                        this.draftItems =
                                            [media];
                                    }
                                }

                                setTimeout(() => {
                                    this.uploads =
                                        this.uploads.filter(
                                            (entry) =>
                                                entry.id
                                                !== uploadState.id,
                                        );
                                }, 1600);
                            } else {
                                uploadState.error =
                                    'Processing did not finish.';
                                uploadState.label =
                                    'Failed';
                            }

                            resolve();
                        },
                    },
                );

                upload.start();
            });
        },

        async retryUpload(uploadState) {
            this.uploads = this.uploads.filter(
                (entry) =>
                    entry.id !== uploadState.id,
            );

            await this.uploadFile(
                uploadState.file,
            );
        },

        async waitForMedia(mediaId) {
            if (! mediaId) {
                return null;
            }

            for (
                let attempt = 0;
                attempt < 120;
                attempt++
            ) {
                try {
                    const response = await this.json(
                        this.statusUrl.replace(
                            '__MEDIA__',
                            String(mediaId),
                        ),
                    );

                    if (response.status === 'ready') {
                        const resolved =
                            await this.json(
                                this.resolveUrl,
                                {
                                    method: 'POST',
                                    body: JSON.stringify({
                                        ids: [mediaId],
                                    }),
                                },
                            );

                        return resolved.media?.[0]
                            ?? null;
                    }

                    if (response.status === 'failed') {
                        return null;
                    }
                } catch {
                    // Retry transient status failures.
                }

                await wait(800);
            }

            return null;
        },

        acceptsItem(item) {
            if (
                this.acceptedKinds.length > 0
                && ! this.acceptedKinds.includes(
                    item.kind,
                )
            ) {
                return false;
            }

            if (
                this.acceptedExtensions.length > 0
                && ! this.acceptedExtensions.includes(
                    String(
                        item.extension || '',
                    ).toLowerCase(),
                )
            ) {
                return false;
            }

            if (
                this.acceptedMimeTypes.length > 0
                && ! this.acceptedMimeTypes.some(
                    (accepted) =>
                        accepted.endsWith('/*')
                            ? item.mime_type?.startsWith(
                                accepted.slice(0, -1),
                            )
                            : item.mime_type
                                === accepted,
                )
            ) {
                return false;
            }

            return true;
        },

        uniqueItems(items) {
            const seen = new Set();

            return items.filter((item) => {
                const id = Number(item.id);

                if (seen.has(id)) {
                    return false;
                }

                seen.add(id);
                return true;
            });
        },

        formatBytes(bytes) {
            const value = Number(bytes || 0);

            if (value < 1024) {
                return `${value} B`;
            }

            const units = [
                'KB',
                'MB',
                'GB',
                'TB',
            ];

            let size = value / 1024;
            let index = 0;

            while (
                size >= 1024
                && index < units.length - 1
            ) {
                size /= 1024;
                index++;
            }

            return `${size.toFixed(
                size >= 10 ? 0 : 1,
            )} ${units[index]}`;
        },

        async json(url, options = {}) {
            const response = await fetch(url, {
                credentials: 'same-origin',
                ...options,
                headers: {
                    Accept: 'application/json',
                    'Content-Type':
                        'application/json',
                    'X-CSRF-TOKEN':
                        this.csrfToken,
                    ...(options.headers ?? {}),
                },
            });

            if (! response.ok) {
                const body =
                    await response.json()
                        .catch(() => ({}));

                throw new Error(
                    body.message
                    || `Request failed (${response.status}).`,
                );
            }

            return response.json();
        },
    };
}

export default mediaPicker;
