import MediaPreviewManager from './media-preview';

import * as tus from 'tus-js-client';

function mediaDrive(options) {
    return {
        ...options,

        folders: [],
        media: [],
        currentFolder: null,

        selected: [],
        selectionAnchor: null,

        viewMode: ['grid', 'list'].includes(options.initialView)
            ? options.initialView
            : 'grid',

        loading: false,
        showInitialSkeleton: false,
        skeletonTimer: null,
        loadingMore: false,
        nextCursor: null,
        hasMore: true,
        requestGeneration: 0,
        requestController: null,

        filters: {
            search: options.initialSearch || '',
            type: options.initialType || '',
            status: options.initialStatus || '',
            sort: options.initialSort || 'newest',
            trash: Boolean(options.initialTrash),
        },

        filterTypeOptions: [
            { value: '', label: 'All types' },
            { value: 'image', label: 'Images' },
            { value: 'video', label: 'Videos' },
            { value: 'audio', label: 'Audio' },
            { value: 'document', label: 'Documents' },
            { value: 'archive', label: 'Archives' },
            { value: 'file', label: 'Other files' },
        ],

        filterStatusOptions: [
            { value: '', label: 'All statuses' },
            { value: 'ready', label: 'Ready' },
            { value: 'uploading', label: 'Uploading' },
            { value: 'uploaded', label: 'Uploaded' },
            { value: 'processing', label: 'Processing' },
            { value: 'failed', label: 'Failed' },
        ],

        filterSortOptions: [
            { value: 'newest', label: 'Newest first' },
            { value: 'oldest', label: 'Oldest first' },
            { value: 'name_asc', label: 'Name A–Z' },
            { value: 'name_desc', label: 'Name Z–A' },
            { value: 'size_desc', label: 'Largest first' },
            { value: 'size_asc', label: 'Smallest first' },
            { value: 'updated', label: 'Recently modified' },
        ],

        folderSlug: options.initialFolder || null,

        uploads: [],
        uploadQueueOpen: true,
        uploadProgressFrames: new Map(),
        uploadReloadTimer: null,
        activeUploadCount: 0,

        dragActive: false,
        dragDepth: 0,
        dragOverFolderId: null,
        draggingItems: [],
        internalItemDrag: false,

        contextMenu: {
            open: false,
            x: 0,
            y: 0,
            item: null,
        },

        lightbox: {
            open: false,
            item: null,
            index: -1,
            mode: 'loading',
            loading: false,
            error: null,
            zoom: 1,
            zoomable: false,
            pdfPage: 1,
            pdfPages: 0,
            isEditingControl: false,
        },

        previewManager: null,

        marquee: {
            active: false,
            moved: false,
            suppressNextClick: false,
            startX: 0,
            startY: 0,
            currentX: 0,
            currentY: 0,
            additive: false,
            baseSelection: [],
            hitKeys: [],
            autoScrollFrame: null,
            pointerX: 0,
            pointerY: 0,
        },

        observer: null,
        cleanupCallbacks: [],

        init() {
            this.ensurePreviewManager();
            this.reload();

            this.$nextTick(() => {
                this.observer = new IntersectionObserver(
                    (entries) => {
                        if (
                            entries[0]?.isIntersecting
                            && this.hasMore
                            && ! this.loadingMore
                            && ! this.loading
                        ) {
                            this.loadMore();
                        }
                    },
                    { rootMargin: '500px' },
                );

                if (this.$refs.infiniteSentinel) {
                    this.observer.observe(this.$refs.infiniteSentinel);
                }
            });

            const pointerMove = (event) => this.updateMarquee(event);
            const pointerUp = (event) => this.finishMarquee(event);

            document.addEventListener('pointermove', pointerMove);
            document.addEventListener('pointerup', pointerUp);

            this.cleanupCallbacks.push(
                () => document.removeEventListener('pointermove', pointerMove),
                () => document.removeEventListener('pointerup', pointerUp),
            );
        },

        destroy() {
            this.requestController?.abort();
            this.observer?.disconnect();
            clearTimeout(this.skeletonTimer);
            clearTimeout(this.uploadReloadTimer);
            this.cleanupCallbacks.forEach((callback) => callback());
            this.cleanupCallbacks = [];

            for (
                const frame
                of this.uploadProgressFrames.values()
            ) {
                cancelAnimationFrame(frame);
            }

            this.uploadProgressFrames.clear();
            document.body.style.userSelect = '';
        },

        allItems() {
            return [...this.folders, ...this.media];
        },

        groupItems(type) {
            return type === 'folder' ? this.folders : this.media;
        },

        async reload(options = {}) {
            const silent = Boolean(options.silent);
            const generation = ++this.requestGeneration;

            this.requestController?.abort();
            this.requestController = new AbortController();

            this.loading = true;
            this.loadingMore = false;
            this.clearSelection();
            this.nextCursor = null;
            this.hasMore = true;
            this.folders = [];
            this.media = [];
            this.currentFolder = null;

            if (! silent) {
                clearTimeout(this.skeletonTimer);
                this.skeletonTimer = setTimeout(() => {
                    if (this.loading && generation === this.requestGeneration) {
                        this.showInitialSkeleton = true;
                    }
                }, 250);
            }

            try {
                await this.fetchPage(false, generation);
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    console.error('Media library reload failed:', error);
                }
            } finally {
                if (generation === this.requestGeneration) {
                    clearTimeout(this.skeletonTimer);
                    this.loading = false;
                    this.showInitialSkeleton = false;
                }
            }
        },

        async loadMore() {
            if (! this.hasMore || this.loadingMore || this.loading) {
                return;
            }

            const generation = this.requestGeneration;
            this.loadingMore = true;

            try {
                await this.fetchPage(true, generation);
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    console.error('Media lazy loading failed:', error);
                }
            } finally {
                if (generation === this.requestGeneration) {
                    this.loadingMore = false;
                }
            }
        },

        async fetchPage(append, generation) {
            const url = new URL(this.listUrl, window.location.origin);

            if (this.folderSlug) {
                url.searchParams.set('folder', this.folderSlug);
            }

            if (this.filters.search) {
                url.searchParams.set('search', this.filters.search);
            }

            if (this.filters.type) {
                url.searchParams.set('kind', this.filters.type);
            }

            if (this.filters.status) {
                url.searchParams.set('status', this.filters.status);
            }

            url.searchParams.set('sort', this.filters.sort || 'newest');

            if (this.filters.trash) {
                url.searchParams.set('trash', '1');
            }

            if (append && this.nextCursor) {
                url.searchParams.set('cursor', this.nextCursor);
            }

            const payload = await this.json(url.toString(), {
                signal: this.requestController?.signal,
            });

            if (generation !== this.requestGeneration) {
                return;
            }

            const folders = Array.isArray(payload.folders)
                ? payload.folders.filter((item) => item?.type === 'folder')
                : [];

            const media = Array.isArray(payload.media)
                ? payload.media.filter((item) => item?.type === 'media')
                : [];

            this.currentFolder = payload.current_folder ?? null;

            if (append) {
                const existingIds = new Set(this.media.map((item) => item.id));
                this.media = [
                    ...this.media,
                    ...media.filter((item) => ! existingIds.has(item.id)),
                ];
            } else {
                this.folders = folders;
                this.media = media;
            }

            this.nextCursor = payload.next_cursor ?? null;
            this.hasMore = Boolean(payload.has_more && this.nextCursor);
        },


        activeFilterCount() {
            let count = 0;

            if (this.filters.type) count++;
            if (this.filters.status) count++;
            if (this.filters.sort !== 'newest') count++;

            return count;
        },

        filterLabel(options, value) {
            return options.find(
                (option) => option.value === value,
            )?.label ?? value;
        },

        setFilter(key, value) {
            this.filters = {
                ...this.filters,
                [key]: value,
            };

            this.syncQuery();
            this.reload({ silent: true });
        },

        clearFilters() {
            this.filters = {
                ...this.filters,
                type: '',
                status: '',
                sort: 'newest',
            };

            this.syncQuery();
            this.reload({ silent: true });
        },

        removeFilter(key) {
            this.filters = {
                ...this.filters,
                [key]: key === 'sort'
                    ? 'newest'
                    : '',
            };

            this.syncQuery();
            this.reload({ silent: true });
        },

        setViewMode(mode) {
            if (! ['grid', 'list'].includes(mode)) {
                return;
            }

            this.viewMode = mode;
            this.syncQuery();
        },

        syncQuery() {
            const url = new URL(window.location.href);

            const values = {
                q: this.filters.search || null,
                type: this.filters.type || null,
                status: this.filters.status || null,
                sort: this.filters.sort !== 'newest' ? this.filters.sort : null,
                view: this.viewMode !== 'grid' ? this.viewMode : null,
            };

            Object.entries(values).forEach(([key, value]) => {
                if (value === null || value === '') {
                    url.searchParams.delete(key);
                } else {
                    url.searchParams.set(key, String(value));
                }
            });

            window.history.replaceState({}, '', url);

            window.Livewire?.dispatch('media-page-state', {
                search: this.filters.search,
                type: this.filters.type,
                status: this.filters.status,
                sort: this.filters.sort,
                view: this.viewMode,
            });
        },

        queryString() {
            const params = new URLSearchParams();

            if (this.filters.search) params.set('q', this.filters.search);
            if (this.filters.type) params.set('type', this.filters.type);
            if (this.filters.status) params.set('status', this.filters.status);
            if (this.filters.sort !== 'newest') params.set('sort', this.filters.sort);
            if (this.viewMode !== 'grid') params.set('view', this.viewMode);

            const query = params.toString();
            return query ? `?${query}` : '';
        },

        navigateRoot() {
            window.location.assign(`${this.rootUrl}${this.queryString()}`);
        },

        navigateTrash() {
            const target = this.filters.trash ? this.rootUrl : this.trashUrl;
            window.location.assign(`${target}${this.queryString()}`);
        },

        navigateFolder(slug) {
            const base = this.filters.trash
                ? this.trashFolderBaseUrl
                : this.folderBaseUrl;

            window.location.assign(
                `${base}/${encodeURIComponent(slug)}${this.queryString()}`,
            );
        },

        selectItem(event, item, groupIndex) {
            const key = this.itemKey(item);
            const ctrl = event.ctrlKey || event.metaKey;
            const shift = event.shiftKey;
            const group = this.groupItems(item.type);

            if (
                shift
                && this.selectionAnchor
                && this.selectionAnchor.type === item.type
            ) {
                const start = Math.min(this.selectionAnchor.index, groupIndex);
                const end = Math.max(this.selectionAnchor.index, groupIndex);
                const range = group
                    .slice(start, end + 1)
                    .map((entry) => this.itemKey(entry));

                this.selected = ctrl
                    ? Array.from(new Set([...this.selected, ...range]))
                    : range;
            } else if (ctrl) {
                this.selected = this.selected.includes(key)
                    ? this.selected.filter((value) => value !== key)
                    : [...this.selected, key];

                this.selectionAnchor = {
                    type: item.type,
                    index: groupIndex,
                };
            } else {
                this.selected = [key];
                this.selectionAnchor = {
                    type: item.type,
                    index: groupIndex,
                };
            }
        },

        clearSelection() {
            this.selected = [];
            this.selectionAnchor = null;
        },

        isSelected(item) {
            return this.selected.includes(this.itemKey(item));
        },

        itemKey(item) {
            return `${item.type}:${item.id}`;
        },

        selectedItems() {
            return this.allItems().filter((item) =>
                this.selected.includes(this.itemKey(item)),
            );
        },

        selectedMediaIds() {
            return this.selectedItems()
                .filter((item) => item.type === 'media')
                .map((item) => item.id);
        },

        selectedFolderIds() {
            return this.selectedItems()
                .filter((item) => item.type === 'folder')
                .map((item) => item.id);
        },

        actionArguments() {
            return {
                mediaIds: this.selectedMediaIds(),
                folderIds: this.selectedFolderIds(),
            };
        },

        openItem(item) {
            if (item.type === 'folder') {
                this.navigateFolder(item.slug);
                return;
            }

            this.openLightbox(item);
        },

        handleWindowClick(event) {
            if (this.marquee.suppressNextClick) {
                this.marquee.suppressNextClick = false;
                return;
            }

            const target = event.target;
            const protectedArea =
                target.closest('[data-media-selectable]')
                || target.closest('[data-media-toolbar]')
                || target.closest('[data-media-context]')
                || target.closest('.fi-modal')
                || target.closest('.fi-dropdown-panel');

            if (! protectedArea && ! this.marquee.active) {
                this.clearSelection();
                this.contextMenu.open = false;
            }
        },

        handleShortcut(event) {
            const target = event.target;

            if (
                target instanceof HTMLInputElement
                || target instanceof HTMLTextAreaElement
                || target instanceof HTMLSelectElement
                || target?.isContentEditable
            ) {
                return;
            }

            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'a') {
                event.preventDefault();
                event.stopPropagation();
                this.selected = this.allItems().map((item) => this.itemKey(item));
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                this.clearSelection();
                this.contextMenu.open = false;
                this.closeLightbox();
                return;
            }

            if (event.key === 'Enter' && this.selected.length === 1) {
                event.preventDefault();
                const item = this.selectedItems()[0];
                if (item) this.openItem(item);
                return;
            }

            if (event.key === 'F2' && this.selected.length === 1) {
                event.preventDefault();
                const item = this.selectedItems()[0];
                if (item) {
                    this.$wire.mountAction('rename', {
                        type: item.type,
                        id: item.id,
                        name: item.name,
                    });
                }
                return;
            }

            if (
                event.key === 'Delete'
                && this.selectedMediaIds().length > 0
                && ! this.filters.trash
            ) {
                event.preventDefault();
                this.$wire.mountAction('trash', {
                    mediaIds: this.selectedMediaIds(),
                });
            }
        },

        startMarquee(event) {
            if (
                event.button !== 0
                || event.target.closest('[data-media-selectable]')
                || event.target.closest('button, a, input, select, textarea, [role="button"]')
            ) {
                return;
            }

            this.marquee = {
                active: true,
                moved: false,
                startX: event.clientX,
                startY: event.clientY,
                currentX: event.clientX,
                currentY: event.clientY,
                pointerX: event.clientX,
                pointerY: event.clientY,
                autoScrollFrame: null,
                additive: event.ctrlKey || event.metaKey,
                baseSelection: event.ctrlKey || event.metaKey
                    ? [...this.selected]
                    : [],
                hitKeys: [],
            };

            if (! this.marquee.additive) {
                this.clearSelection();
            }

            document.body.style.userSelect = 'none';
        },

        updateMarquee(event) {
            if (! this.marquee.active) return;

            this.marquee.pointerX = event.clientX;
            this.marquee.pointerY = event.clientY;
            this.marquee.currentX = event.clientX;
            this.marquee.currentY = event.clientY;

            if (
                Math.abs(this.marquee.currentX - this.marquee.startX) > 4
                || Math.abs(this.marquee.currentY - this.marquee.startY) > 4
            ) {
                this.marquee.moved = true;
            }

            this.updateMarqueeSelection();
            this.scheduleMarqueeAutoScroll();
        },

        updateMarqueeSelection() {
            if (! this.marquee.active) return;

            const rectangle = this.marqueeRect();
            const keys = [];

            document.querySelectorAll(
                '[data-media-selectable][data-media-key]',
            ).forEach((element) => {
                const itemRect = element.getBoundingClientRect();

                const intersects = ! (
                    itemRect.right < rectangle.left
                    || itemRect.left > rectangle.right
                    || itemRect.bottom < rectangle.top
                    || itemRect.top > rectangle.bottom
                );

                if (intersects) {
                    keys.push(element.dataset.mediaKey);
                }
            });

            this.marquee.hitKeys = Array.from(new Set([
                ...this.marquee.hitKeys,
                ...keys,
            ]));

            this.selected = Array.from(new Set([
                ...this.marquee.baseSelection,
                ...this.marquee.hitKeys,
            ]));
        },

        scheduleMarqueeAutoScroll() {
            if (
                ! this.marquee.active
                || this.marquee.autoScrollFrame
            ) {
                return;
            }

            const step = () => {
                this.marquee.autoScrollFrame = null;

                if (! this.marquee.active) {
                    return;
                }

                const edge = 72;
                const maxSpeed = 24;
                const y = this.marquee.pointerY;
                let delta = 0;

                if (y < edge) {
                    delta = -Math.ceil(
                        ((edge - y) / edge) * maxSpeed,
                    );
                } else if (y > window.innerHeight - edge) {
                    delta = Math.ceil(
                        (
                            (y - (window.innerHeight - edge))
                            / edge
                        ) * maxSpeed,
                    );
                }

                if (delta !== 0) {
                    window.scrollBy(0, delta);
                    this.marquee.currentY = Math.max(
                        0,
                        Math.min(
                            window.innerHeight,
                            this.marquee.pointerY,
                        ),
                    );

                    this.updateMarqueeSelection();
                    this.marquee.autoScrollFrame =
                        requestAnimationFrame(step);
                }
            };

            this.marquee.autoScrollFrame =
                requestAnimationFrame(step);
        },

        finishMarquee() {
            if (! this.marquee.active) return;

            const moved = this.marquee.moved;

            if (this.marquee.autoScrollFrame) {
                cancelAnimationFrame(
                    this.marquee.autoScrollFrame,
                );
                this.marquee.autoScrollFrame = null;
            }

            this.marquee.active = false;
            document.body.style.userSelect = '';

            if (moved) {
                this.marquee.suppressNextClick = true;

                setTimeout(() => {
                    this.marquee.suppressNextClick = false;
                }, 0);

                return;
            }

            if (! this.marquee.additive) {
                this.clearSelection();
            }
        },

        marqueeRect() {
            return {
                left: Math.min(this.marquee.startX, this.marquee.currentX),
                top: Math.min(this.marquee.startY, this.marquee.currentY),
                right: Math.max(this.marquee.startX, this.marquee.currentX),
                bottom: Math.max(this.marquee.startY, this.marquee.currentY),
            };
        },

        marqueeStyle() {
            const rect = this.marqueeRect();
            return {
                left: `${rect.left}px`,
                top: `${rect.top}px`,
                width: `${rect.right - rect.left}px`,
                height: `${rect.bottom - rect.top}px`,
            };
        },

        openContextMenu(event, item) {
            if (! this.isSelected(item)) {
                this.selected = [this.itemKey(item)];
            }

            this.contextMenu = {
                open: true,
                x: Math.min(event.clientX, window.innerWidth - 240),
                y: Math.min(event.clientY, window.innerHeight - 330),
                item,
            };
        },

        openContextItem() {
            const item = this.contextMenu.item;
            this.contextMenu.open = false;
            if (item) this.openItem(item);
        },

        ensurePreviewManager() {
            if (! this.previewManager) {
                const manager = new MediaPreviewManager();

                this.previewManager = window.Alpine?.raw
                    ? window.Alpine.raw(manager)
                    : manager;
            }

            return this.previewManager;
        },

        canPreview(item) {
            return this.ensurePreviewManager().supports(item);
        },

        async openLightbox(item) {
            if (item.type !== 'media') {
                return;
            }

            const manager = this.ensurePreviewManager();
            const supported = manager.supports(item);
            const image = item.kind === 'image';

            this.lightbox = {
                ...this.lightbox,
                open: true,
                item,
                index: this.media.findIndex(
                    (entry) => entry.id === item.id,
                ),
                mode: supported
                    ? (image ? 'image' : 'loading')
                    : 'unsupported',
                loading: supported && ! image,
                error: supported
                    ? null
                    : 'This file type does not have an in-browser preview.',
                zoom: 1,
                zoomable: supported && image,
                pdfPage: 1,
                pdfPages: 0,
            };

            await this.$nextTick();

            if (! supported) {
                return;
            }

            await this.renderCurrentPreview();
        },

        async closeLightbox() {
            this.lightbox.open = false;
            await this.ensurePreviewManager().destroy();

            this.lightbox = {
                ...this.lightbox,
                item: null,
                index: -1,
                mode: 'loading',
                loading: false,
                error: null,
                zoom: 1,
                zoomable: false,
                pdfPage: 1,
                pdfPages: 0,
            };
        },

        async previousPreview() {
            if (this.media.length === 0) {
                return;
            }

            this.lightbox.index = (
                this.lightbox.index - 1 + this.media.length
            ) % this.media.length;

            this.lightbox.item = this.media[this.lightbox.index];
            await this.renderCurrentPreview();
        },

        async nextPreview() {
            if (this.media.length === 0) {
                return;
            }

            this.lightbox.index = (
                this.lightbox.index + 1
            ) % this.media.length;

            this.lightbox.item = this.media[this.lightbox.index];
            await this.renderCurrentPreview();
        },

        previewElements() {
            return {
                image: this.$refs.previewImage,
                video: this.$refs.previewVideo,
                audio: this.$refs.previewAudio,
                pdfCanvas: this.$refs.previewPdfCanvas,
                pdfViewport: this.$refs.previewPdfViewport,
                documentViewport:
                    this.$refs.previewDocumentViewport,
                imageViewport:
                    this.$refs.previewImageViewport,
                documentContent:
                    this.$refs.previewDocumentContent,
                zoomTarget: this.lightbox.mode === 'image'
                    ? this.$refs.previewZoomTarget
                    : this.$refs.previewDocumentContent,
                mode: () => this.lightbox.mode,
                setMode: (mode) => {
                    this.lightbox.mode = mode;
                    this.lightbox.zoomable = [
                        'image',
                        'pdf',
                        'document',
                        'spreadsheet',
                        'code',
                    ].includes(mode);
                },
                setLoading: (loading) => {
                    this.lightbox.loading = loading;
                },
                setError: (error) => {
                    this.lightbox.error = error;
                },
                setPdfState: ({ page, pages }) => {
                    this.lightbox.pdfPage = page;
                    this.lightbox.pdfPages = pages;
                },
            };
        },

        async renderCurrentPreview() {
            if (! this.lightbox?.open || ! this.lightbox?.item) {
                return;
            }

            const manager = this.ensurePreviewManager();
            const item = this.lightbox.item;

            if (! manager.supports(item)) {
                this.lightbox.mode = 'unsupported';
                this.lightbox.loading = false;
                this.lightbox.zoomable = false;
                this.lightbox.error =
                    'This file type does not have an in-browser preview.';
                return;
            }

            const image = item.kind === 'image';

            this.lightbox.loading = ! image;
            this.lightbox.mode = image ? 'image' : 'loading';
            this.lightbox.error = null;
            this.lightbox.zoom = 1;

            await this.$nextTick();

            if (! this.lightbox?.open || this.lightbox?.item?.id !== item.id) {
                return;
            }

            await manager.open(
                item,
                this.previewElements(),
            );

            if (this.lightbox?.open && this.lightbox?.item?.id === item.id) {
                this.lightbox.zoom = manager.zoom;
            }
        },

        zoomPreview(delta) {
            this.previewManager?.zoomBy(
                delta,
                this.previewElements(),
            );

            this.lightbox.zoom =
                this.previewManager?.zoom || 1;
        },

        resetPreviewZoom() {
            this.previewManager?.resetZoom(
                this.previewElements(),
            );

            this.lightbox.zoom = 1;
        },

        handlePreviewWheel(event) {
            const handled =
                this.previewManager?.handleWheel(
                    event,
                    this.previewElements(),
                );

            if (handled) {
                this.lightbox.zoom =
                    this.previewManager?.zoom || 1;
            }
        },


        beginPreviewPan(event, viewport) {
            this.ensurePreviewManager().beginPan(
                event,
                viewport,
            );
        },

        movePreviewPan(event, viewport) {
            this.ensurePreviewManager().movePan(
                event,
                viewport,
            );
        },

        endPreviewPan(viewport) {
            this.ensurePreviewManager().endPan(
                viewport,
            );
        },

        async setPreviewPdfPage(page) {
            await this.previewManager?.setPdfPage(
                page,
                this.previewElements(),
            );

            this.lightbox.pdfPage =
                this.previewManager?.pdfPage || 1;
        },

        beginItemDrag(event, item) {
            this.internalItemDrag = true;
            this.dragActive = false;
            this.dragDepth = 0;

            if (! this.isSelected(item)) {
                this.selected = [
                    this.itemKey(item),
                ];
            }

            this.draggingItems =
                this.selectedItems();

            event.dataTransfer.effectAllowed =
                'move';

            const payload = JSON.stringify(
                this.draggingItems.map(
                    (entry) => ({
                        type: entry.type,
                        id: entry.id,
                    }),
                ),
            );

            event.dataTransfer.setData(
                'application/x-media-library-items',
                payload,
            );

            event.dataTransfer.setData(
                'text/plain',
                'media-library-internal-drag',
            );
        },

        async dropSelectionIntoFolder(targetFolderId) {
            if (! this.internalItemDrag) {
                return;
            }

            const movingItems = [...this.draggingItems];

            const mediaIds = movingItems
                .filter((item) => item.type === 'media')
                .map((item) => item.id);

            const folderIds = movingItems
                .filter(
                    (item) =>
                        item.type === 'folder'
                        && item.id !== targetFolderId,
                )
                .map((item) => item.id);

            if (
                mediaIds.length === 0
                && folderIds.length === 0
            ) {
                this.finishItemDrag();
                return;
            }

            // Remove moved records immediately so the source view cannot
            // display a stale duplicate while the request is completing.
            const movedMedia = new Set(mediaIds);
            const movedFolders = new Set(folderIds);

            this.media = this.media.filter(
                (item) => ! movedMedia.has(item.id),
            );

            this.folders = this.folders.filter(
                (item) => ! movedFolders.has(item.id),
            );

            this.clearSelection();
            this.contextMenu.open = false;
            this.dragOverFolderId = null;

            try {
                await this.json(this.moveUrl, {
                    method: 'POST',
                    body: JSON.stringify({
                        media_ids: mediaIds,
                        folder_ids: folderIds,
                        target_folder_id: targetFolderId,
                    }),
                });

                await this.reload({ silent: true });
            } catch (error) {
                console.error('Move failed:', error);

                // Restore the authoritative server state if the move failed.
                await this.reload({ silent: true });
            } finally {
                this.finishItemDrag();
            }
        },

        uploadEntry(localId) {
            return this.uploads.find(
                (entry) => entry.localId === localId,
            ) ?? null;
        },

        patchUpload(localId, values) {
            const entry = this.uploadEntry(localId);

            if (! entry) {
                return;
            }

            Object.assign(entry, values);
        },

        scheduleUploadProgress(
            localId,
            uploaded,
            total,
        ) {
            const previousFrame =
                this.uploadProgressFrames.get(localId);

            if (previousFrame) {
                cancelAnimationFrame(previousFrame);
            }

            const frame = requestAnimationFrame(() => {
                const entry = this.uploadEntry(localId);

                if (! entry) {
                    return;
                }

                const now = performance.now();
                const elapsed = Math.max(
                    0.001,
                    (now - entry.lastSampleAt) / 1000,
                );

                const transferred = Math.max(
                    0,
                    uploaded - entry.lastSampleBytes,
                );

                const instantSpeed =
                    transferred / elapsed;

                const smoothedSpeed =
                    entry.speed > 0
                        ? (
                            entry.speed * 0.65
                            + instantSpeed * 0.35
                        )
                        : instantSpeed;

                Object.assign(entry, {
                    statusLabel: 'Uploading',
                    uploadedBytes: uploaded,
                    progress: total > 0
                        ? Math.min(
                            100,
                            (uploaded / total) * 100,
                        )
                        : 0,
                    speed: Number.isFinite(
                        smoothedSpeed,
                    )
                        ? Math.max(0, smoothedSpeed)
                        : 0,
                    lastSampleAt: now,
                    lastSampleBytes: uploaded,
                });

                this.uploadProgressFrames.delete(
                    localId,
                );
            });

            this.uploadProgressFrames.set(
                localId,
                frame,
            );
        },

        async queueFiles(fileList) {
            const files = Array.from(fileList ?? []);

            if (
                files.length === 0
                || this.filters.trash
            ) {
                return;
            }

            const concurrency = Math.max(
                1,
                Math.min(
                    4,
                    Number(this.concurrentUploads || 4),
                    files.length,
                ),
            );

            let nextIndex = 0;

            const worker = async () => {
                while (nextIndex < files.length) {
                    const index = nextIndex;
                    nextIndex++;

                    await this.uploadFile(files[index]);
                }
            };

            await Promise.all(
                Array.from(
                    { length: concurrency },
                    () => worker(),
                ),
            );

            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = '';
            }

        },

        uploadFile(file) {
            return new Promise((resolve) => {
                const localId =
                    crypto.randomUUID();

                const previewUrl =
                    file.type.startsWith('image/')
                        ? URL.createObjectURL(file)
                        : this.placeholderFor(
                            file.type,
                            file.name,
                        );

                this.uploads.unshift({
                    localId,
                    file,
                    previewUrl,
                    progress: 0,
                    uploadedBytes: 0,
                    speed: 0,
                    statusLabel: 'Starting',
                    mediaId: null,
                    failed: false,
                    errorMessage: null,
                    uploadClient: null,
                    lastSampleAt:
                        performance.now(),
                    lastSampleBytes: 0,
                });

                this.uploadQueueOpen = true;

                const configuredChunk = Number(
                    this.chunkSize,
                );

                const progressChunk = Number(
                    this.browserProgressChunkSize
                    || 2 * 1024 * 1024,
                );

                const uploadChunkSize = Math.max(
                    256 * 1024,
                    Math.min(
                        file.size || progressChunk,
                        configuredChunk,
                        progressChunk,
                    ),
                );

                const upload = new tus.Upload(
                    file,
                    {
                        endpoint: this.tusEndpoint,

                        chunkSize: uploadChunkSize,

                        retryDelays: [
                            0,
                            1000,
                            3000,
                            5000,
                            10000,
                        ],

                        uploadDataDuringCreation:
                            file.size <= this.smallFileThreshold,

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

                            const entry =
                                this.uploadEntry(localId);

                            if (
                                mediaId
                                && entry
                                && ! entry.mediaId
                            ) {
                                entry.mediaId = mediaId;
                            }
                        },

                        onProgress: (
                            uploaded,
                            total,
                        ) => {
                            this.scheduleUploadProgress(
                                localId,
                                uploaded,
                                total,
                            );
                        },

                        onChunkComplete: (
                            _chunkSize,
                            bytesAccepted,
                            bytesTotal,
                        ) => {
                            this.scheduleUploadProgress(
                                localId,
                                bytesAccepted,
                                bytesTotal,
                            );
                        },

                        onError: (error) => {
                            this.patchUpload(
                                localId,
                                {
                                    statusLabel: 'Failed',
                                    errorMessage:
                                        error?.message
                                        || 'Upload failed',
                                    failed: true,
                                    speed: 0,
                                },
                            );

                            console.error(error);
                            resolve();
                        },

                        onSuccess: async () => {
                            this.scheduleUploadProgress(
                                localId,
                                file.size,
                                file.size,
                            );

                            await new Promise(
                                (done) =>
                                    requestAnimationFrame(
                                        done,
                                    ),
                            );

                            this.patchUpload(
                                localId,
                                {
                                    statusLabel:
                                        'Processing',
                                    progress: 100,
                                    uploadedBytes:
                                        file.size,
                                    speed: 0,
                                },
                            );

                            const entry =
                                this.uploadEntry(localId);

                            if (entry?.mediaId) {
                                await this.watchProcessing(
                                    entry.mediaId,
                                    entry,
                                );
                            }

                            resolve();
                        },
                    },
                );

                upload.findPreviousUploads()
                    .then((previous) => {
                        if (previous.length > 0) {
                            upload
                                .resumeFromPreviousUpload(
                                    previous[0],
                                );
                        }

                        upload.start();
                    })
                    .catch((error) => {
                        this.patchUpload(
                            localId,
                            {
                                statusLabel: 'Failed',
                                errorMessage:
                                    error?.message
                                    || 'Unable to start',
                                failed: true,
                                speed: 0,
                            },
                        );

                        resolve();
                    });
            });
        },

        async watchProcessing(mediaId, uploadItem) {
            for (
                let attempt = 0;
                attempt < this.statusMaxAttempts;
                attempt++
            ) {
                try {
                    const item = await this.json(
                        this.statusUrl.replace(
                            '__MEDIA__',
                            String(mediaId),
                        ),
                    );

                    if (item.status === 'ready') {
                        uploadItem.statusLabel = 'Ready';
                        uploadItem.failed = false;
                        uploadItem.errorMessage = null;

                        this.upsertCompletedMedia(item);

                        setTimeout(() => {
                            this.uploads = this.uploads.filter(
                                (upload) =>
                                    upload.localId
                                    !== uploadItem.localId,
                            );
                        }, 2000);

                        return;
                    }

                    if (item.status === 'failed') {
                        uploadItem.statusLabel = 'Failed';
                        uploadItem.failed = true;
                        uploadItem.errorMessage =
                            item.error_message
                            || 'Media processing failed';
                        return;
                    }
                } catch (error) {
                    console.warn(error);
                }

                await this.wait(this.statusInterval);
            }

            uploadItem.statusLabel = 'Uploaded';
        },

        upsertCompletedMedia(item) {
            if (! this.mediaMatchesCurrentView(item)) {
                return;
            }

            const index = this.media.findIndex(
                (entry) => entry.id === item.id,
            );

            if (index >= 0) {
                this.media.splice(index, 1, item);
            } else {
                this.media.unshift(item);
            }

            this.sortCurrentMedia();
        },

        mediaMatchesCurrentView(item) {
            if (this.filters.trash) {
                return false;
            }

            const currentFolder =
                this.folderSlug || null;

            if (
                (item.folder_slug || null)
                !== currentFolder
            ) {
                return false;
            }

            if (
                this.filters.type
                && item.kind !== this.filters.type
            ) {
                return false;
            }

            if (
                this.filters.status
                && item.status !== this.filters.status
            ) {
                return false;
            }

            const search = String(
                this.filters.search || '',
            ).trim().toLowerCase();

            if (search) {
                const haystack = [
                    item.name,
                    item.title,
                    item.extension,
                    ...(item.tags || []).map(
                        (tag) => tag.name,
                    ),
                ]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase();

                if (! haystack.includes(search)) {
                    return false;
                }
            }

            return true;
        },

        sortCurrentMedia() {
            const sorter = {
                oldest: (a, b) =>
                    new Date(a.created_at)
                    - new Date(b.created_at),
                name_asc: (a, b) =>
                    String(a.name).localeCompare(
                        String(b.name),
                    ),
                name_desc: (a, b) =>
                    String(b.name).localeCompare(
                        String(a.name),
                    ),
                size_asc: (a, b) =>
                    Number(a.size) - Number(b.size),
                size_desc: (a, b) =>
                    Number(b.size) - Number(a.size),
                updated: (a, b) =>
                    new Date(b.updated_at)
                    - new Date(a.updated_at),
                newest: (a, b) =>
                    new Date(b.created_at)
                    - new Date(a.created_at),
            }[this.filters.sort || 'newest'];

            this.media = [...this.media].sort(sorter);
        },

        async retryUpload(uploadItem) {
            const file = uploadItem.file;

            await this.purgeFailedMedia(uploadItem);
            this.uploads = this.uploads.filter(
                (entry) =>
                    entry.localId !== uploadItem.localId,
            );

            await this.uploadFile(file);
        },

        async removeFailedUpload(uploadItem) {
            await this.purgeFailedMedia(uploadItem);

            this.uploads = this.uploads.filter(
                (entry) =>
                    entry.localId !== uploadItem.localId,
            );

            if (
                uploadItem.previewUrl
                    ?.startsWith('blob:')
            ) {
                URL.revokeObjectURL(
                    uploadItem.previewUrl,
                );
            }
        },

        async purgeFailedMedia(uploadItem) {
            if (! uploadItem.mediaId) {
                return;
            }

            try {
                await this.json(
                    this.failedUploadDeleteUrl.replace(
                        '__MEDIA__',
                        String(uploadItem.mediaId),
                    ),
                    {
                        method: 'DELETE',
                    },
                );
            } catch (error) {
                console.warn(
                    'Unable to remove failed media:',
                    error,
                );
            }
        },

        placeholderFor(mimeType, filename) {
            const extension = filename.includes('.')
                ? filename.split('.').pop().toLowerCase()
                : '';

            if (this.placeholderExtensions[extension]) {
                return this.placeholderExtensions[extension];
            }

            const type = String(mimeType || '');
            let kind = 'file';

            if (type.startsWith('image/')) kind = 'image';
            else if (type.startsWith('video/')) kind = 'video';
            else if (type.startsWith('audio/')) kind = 'audio';
            else if (
                type.includes('zip')
                || type.includes('rar')
                || type.includes('7z')
                || type.includes('tar')
            ) kind = 'archive';
            else if (
                type.includes('pdf')
                || type.includes('document')
                || type.includes('text')
                || type.includes('sheet')
                || type.includes('presentation')
            ) kind = 'document';

            return this.placeholderKinds[kind] || this.defaultPlaceholder;
        },

        formatBytes(bytes) {
            if (! Number.isFinite(bytes) || bytes <= 0) return '0 B';

            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            const index = Math.min(
                Math.floor(Math.log(bytes) / Math.log(1024)),
                units.length - 1,
            );

            return `${(bytes / (1024 ** index)).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
        },

        formatSpeed(bytes) {
            return bytes > 0 ? `${this.formatBytes(bytes)}/s` : '0 B/s';
        },

        async json(url, options = {}) {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    ...(options.headers ?? {}),
                },
                ...options,
            });

            const payload = await response.json().catch(() => ({}));

            if (! response.ok) {
                throw new Error(
                    payload.message
                    ?? Object.values(payload.errors ?? {}).flat().join(' ')
                    ?? 'The request failed.',
                );
            }

            return payload;
        },

        wait(milliseconds) {
            return new Promise((resolve) => setTimeout(resolve, milliseconds));
        },
    };
}

window.mediaDrive = mediaDrive;

export { mediaDrive };
export default mediaDrive;
