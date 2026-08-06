import videojs from 'video.js';
import 'video.js/dist/video-js.css';

import * as pdfjsLib from 'pdfjs-dist';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

import { renderAsync as renderDocx } from 'docx-preview';
import * as XLSX from 'xlsx';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import hljs from 'highlight.js/lib/common';
import 'highlight.js/styles/github-dark.css';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

/*
|--------------------------------------------------------------------------
| Runtime storage
|--------------------------------------------------------------------------
|
| PDFDocumentProxy and Video.js contain private/internal state that must not
| be wrapped by Alpine's reactive Proxy. They are therefore kept in this
| module-level map and never assigned to Alpine component state.
|
*/

const runtime = new Map();

const clamp = (value, minimum, maximum) =>
    Math.min(maximum, Math.max(minimum, value));

const extensionOf = (item) =>
    String(item?.extension || item?.name?.split('.').pop() || '')
        .toLowerCase()
        .replace(/^\./, '');

const fetchBlob = async (url, signal) => {
    const response = await fetch(url, {
        credentials: 'same-origin',
        signal,
    });

    if (! response.ok) {
        throw new Error(
            `Preview request failed (${response.status}).`,
        );
    }

    return response.blob();
};

const fetchArrayBuffer = async (url, signal) =>
    (await fetchBlob(url, signal)).arrayBuffer();

const fetchText = async (url, signal) =>
    (await fetchBlob(url, signal)).text();

export class MediaPreviewManager {
    constructor(options = {}) {
        this.options = options;
        this.instanceId = crypto.randomUUID();
        this.currentToken = 0;
        this.zoom = 1;
        this.pdfPage = 1;
        this.pdfPages = 0;
        this.pdfRendering = false;
        this.pan = {
            active: false,
            startX: 0,
            startY: 0,
            scrollLeft: 0,
            scrollTop: 0,
        };

        runtime.set(this.instanceId, {
            player: null,
            pdf: null,
            pdfLoadingTask: null,
            abortController: null,
            imageBaseWidth: 0,
            imageBaseHeight: 0,
        });
    }

    state() {
        if (! runtime.has(this.instanceId)) {
            runtime.set(this.instanceId, {
                player: null,
                pdf: null,
                pdfLoadingTask: null,
                abortController: null,
                imageBaseWidth: 0,
                imageBaseHeight: 0,
            });
        }

        return runtime.get(this.instanceId);
    }

    supports(item) {
        const extension = extensionOf(item);

        if (['image', 'video', 'audio'].includes(item?.kind)) {
            return true;
        }

        return [
            'pdf',
            'docx',
            'xlsx',
            'xls',
            'xlsm',
            'xlsb',
            'csv',
            'tsv',
            'txt',
            'log',
            'md',
            'markdown',
            'json',
            'xml',
            'html',
            'htm',
            'css',
            'js',
            'mjs',
            'cjs',
            'ts',
            'tsx',
            'jsx',
            'php',
            'py',
            'java',
            'c',
            'cpp',
            'h',
            'hpp',
            'cs',
            'go',
            'rs',
            'sql',
            'yaml',
            'yml',
            'ini',
            'env',
            'sh',
            'ps1',
            'bat',
        ].includes(extension);
    }

    async open(item, elements) {
        await this.destroyRuntime();

        this.currentToken++;
        const token = this.currentToken;
        this.zoom = 1;
        this.pdfPage = 1;
        this.pdfPages = 0;

        const state = this.state();
        state.abortController = new AbortController();

        elements?.setError?.(null);

        if (! this.supports(item)) {
            elements?.setMode?.('unsupported');
            elements?.setLoading?.(false);
            elements?.setError?.(
                'This file type does not have an in-browser preview.',
            );
            return;
        }

        if (item.kind === 'image') {
            elements?.setMode?.('image');
            elements?.setLoading?.(false);
        } else {
            elements?.setMode?.('loading');
            elements?.setLoading?.(true);
        }

        try {
            if (item.kind === 'image') {
                await this.renderImage(item, elements);
            } else if (item.kind === 'video') {
                await this.renderVideo(item, elements);
            } else if (item.kind === 'audio') {
                await this.renderAudio(item, elements);
            } else {
                await this.renderDocument(item, elements);
            }

            if (token !== this.currentToken) {
                return;
            }

            elements?.setLoading?.(false);
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }

            console.error('Media preview failed:', error);

            elements?.setLoading?.(false);
            elements?.setMode?.('unsupported');
            elements?.setError?.(
                error?.message
                || 'This file could not be previewed.',
            );
        }
    }

    async renderImage(item, elements) {
        elements.setMode('image');

        const image = elements.image;
        const viewport = elements.imageViewport;

        if (! image || ! viewport) {
            throw new Error(
                'Image preview elements are unavailable.',
            );
        }

        const state = this.state();

        image.alt = item.alt || item.name || '';
        image.src = item.preview_url || item.url;

        await image.decode().catch(() => {
            return new Promise((resolve, reject) => {
                image.addEventListener('load', resolve, {
                    once: true,
                });
                image.addEventListener('error', reject, {
                    once: true,
                });
            });
        });

        const availableWidth = Math.max(
            120,
            viewport.clientWidth - 32,
        );

        const availableHeight = Math.max(
            120,
            viewport.clientHeight - 32,
        );

        const fitScale = Math.min(
            1,
            availableWidth / image.naturalWidth,
            availableHeight / image.naturalHeight,
        );

        state.imageBaseWidth = Math.max(
            1,
            Math.round(image.naturalWidth * fitScale),
        );

        state.imageBaseHeight = Math.max(
            1,
            Math.round(image.naturalHeight * fitScale),
        );

        this.applyZoom(elements);

        viewport.scrollTo({
            top: 0,
            left: 0,
        });
    }

    async renderVideo(item, elements) {
        elements.setMode('video');

        const mediaElement = elements.video;

        if (! mediaElement) {
            throw new Error(
                'Video preview element is unavailable.',
            );
        }

        await this.createPlayer({
            element: mediaElement,
            item,
            audio: false,
        });
    }

    async renderAudio(item, elements) {
        elements.setMode('audio');

        const mediaElement = elements.audio;

        if (! mediaElement) {
            throw new Error(
                'Audio preview element is unavailable.',
            );
        }

        await this.createPlayer({
            element: mediaElement,
            item,
            audio: true,
        });
    }

    async createPlayer({
        element,
        item,
        audio,
    }) {
        const state = this.state();

        element.controls = true;
        element.preload = 'metadata';
        element.playsInline = true;

        /*
         * Use Video.js' bundled/default skin. No custom control-bar skin is
         * applied by this media library.
         */
        element.className = [
            'video-js',
            'vjs-default-skin',
            audio ? 'vjs-audio-only' : 'vjs-big-play-centered',
        ].filter(Boolean).join(' ');

        const player = videojs(element, {
            controls: true,
            autoplay: false,
            preload: 'metadata',
            fluid: ! audio,
            responsive: true,
            audioOnlyMode: audio,
            audioPosterMode: false,
            aspectRatio: audio ? undefined : '16:9',
            inactivityTimeout: audio ? 0 : 2200,
            liveui: true,
            userActions: {
                hotkeys: true,
            },
            controlBar: {
                volumePanel: {
                    inline: false,
                },
                pictureInPictureToggle: ! audio,
                fullscreenToggle: ! audio,
            },
        });

        state.player = player;

        const source = {
            src: item.url,
        };

        if (item.mime_type) {
            source.type = item.mime_type;
        }

        await new Promise((resolve, reject) => {
            let settled = false;

            const rejectPlayback = () => {
                if (settled) {
                    return;
                }

                settled = true;

                const mediaError = player.error();

                reject(
                    new Error(
                        mediaError?.message
                        || 'The browser could not play this media file.',
                    ),
                );
            };

            player.one('error', rejectPlayback);

            player.ready(() => {
                if (settled) {
                    return;
                }

                try {
                    player.src(source);
                    player.load();
                    settled = true;
                    resolve();
                } catch (error) {
                    rejectPlayback(error);
                }
            });
        });
    }

    async renderDocument(item, elements) {
        const extension = extensionOf(item);

        if (extension === 'pdf') {
            await this.renderPdf(item, elements);
            return;
        }

        if (extension === 'docx') {
            await this.renderWord(item, elements);
            return;
        }

        if (
            [
                'xlsx',
                'xls',
                'xlsm',
                'xlsb',
                'csv',
                'tsv',
            ].includes(extension)
        ) {
            await this.renderSpreadsheet(item, elements);
            return;
        }

        if (['md', 'markdown'].includes(extension)) {
            await this.renderMarkdown(item, elements);
            return;
        }

        if (this.isTextExtension(extension)) {
            await this.renderText(item, elements);
            return;
        }

        throw new Error(
            `In-browser preview is not available for .${extension || 'this'} files.`,
        );
    }

    async renderPdf(item, elements) {
        elements.setMode('pdf');

        const state = this.state();

        const buffer = await fetchArrayBuffer(
            item.url,
            state.abortController.signal,
        );

        /*
         * Never expose this object through Alpine state. PDF.js uses private
         * class members that throw when accessed through a JavaScript Proxy.
         */
        state.pdfLoadingTask = pdfjsLib.getDocument({
            data: buffer,
            isEvalSupported: false,
            useSystemFonts: true,
            verbosity: 0,
        });

        state.pdf = await state.pdfLoadingTask.promise;

        this.pdfPages = state.pdf.numPages;
        this.pdfPage = 1;

        elements?.setPdfState?.({
            page: this.pdfPage,
            pages: this.pdfPages,
        });

        await this.renderPdfPage(elements);
    }

    async renderPdfPage(elements) {
        const state = this.state();
        const pdf = state.pdf;

        if (! pdf || this.pdfRendering) {
            return;
        }

        this.pdfRendering = true;

        try {
            const canvas = elements.pdfCanvas;

            if (! canvas) {
                throw new Error(
                    'PDF canvas is unavailable.',
                );
            }

            const page = await pdf.getPage(this.pdfPage);
            const baseViewport = page.getViewport({
                scale: 1,
            });

            const availableWidth = Math.max(
                320,
                elements.documentViewport?.clientWidth
                || 900,
            );

            const fitScale = Math.min(
                2,
                (availableWidth - 48)
                / baseViewport.width,
            );

            const scale = clamp(
                fitScale * this.zoom,
                0.25,
                5,
            );

            const viewport = page.getViewport({
                scale,
            });

            const pixelRatio = Math.min(
                window.devicePixelRatio || 1,
                2,
            );

            const context = canvas.getContext(
                '2d',
                {
                    alpha: false,
                },
            );

            canvas.width = Math.floor(
                viewport.width * pixelRatio,
            );

            canvas.height = Math.floor(
                viewport.height * pixelRatio,
            );

            canvas.style.width =
                `${viewport.width}px`;

            canvas.style.height =
                `${viewport.height}px`;

            context.setTransform(
                pixelRatio,
                0,
                0,
                pixelRatio,
                0,
                0,
            );

            await page.render({
                canvasContext: context,
                viewport,
            }).promise;

            elements?.setPdfState?.({
                page: this.pdfPage,
                pages: this.pdfPages,
            });
        } finally {
            this.pdfRendering = false;
        }
    }

    async setPdfPage(page, elements) {
        const pdf = this.state().pdf;

        if (! pdf) {
            return;
        }

        this.pdfPage = clamp(
            Number(page) || 1,
            1,
            this.pdfPages,
        );

        await this.renderPdfPage(elements);
    }

    async renderWord(item, elements) {
        elements.setMode('document');

        const state = this.state();

        const buffer = await fetchArrayBuffer(
            item.url,
            state.abortController.signal,
        );

        const container =
            elements.documentContent;

        container.replaceChildren();

        await renderDocx(
            buffer,
            container,
            null,
            {
                className: 'media-docx',
                inWrapper: true,
                ignoreWidth: false,
                ignoreHeight: false,
                ignoreFonts: false,
                breakPages: true,
                renderHeaders: true,
                renderFooters: true,
                renderFootnotes: true,
                useBase64URL: true,
            },
        );

        this.applyZoom(elements);
    }

    async renderSpreadsheet(item, elements) {
        elements.setMode('spreadsheet');

        const state = this.state();

        const buffer = await fetchArrayBuffer(
            item.url,
            state.abortController.signal,
        );

        const workbook = XLSX.read(buffer, {
            type: 'array',
            cellDates: true,
            cellText: true,
        });

        const container =
            elements.documentContent;

        container.replaceChildren();

        const tabs = document.createElement('div');
        tabs.className = 'media-sheet-tabs';

        const body = document.createElement('div');
        body.className = 'media-sheet-body';

        const showSheet = (sheetName) => {
            body.innerHTML = DOMPurify.sanitize(
                XLSX.utils.sheet_to_html(
                    workbook.Sheets[sheetName],
                    {
                        id: 'media-sheet-table',
                        editable: false,
                    },
                ),
                {
                    USE_PROFILES: {
                        html: true,
                    },
                },
            );

            tabs
                .querySelectorAll('button')
                .forEach((button) => {
                    button.classList.toggle(
                        'is-active',
                        button.dataset.sheet
                            === sheetName,
                    );
                });
        };

        workbook.SheetNames.forEach(
            (sheetName, index) => {
                const button =
                    document.createElement('button');

                button.type = 'button';
                button.dataset.sheet = sheetName;
                button.textContent = sheetName;

                button.addEventListener(
                    'click',
                    () => showSheet(sheetName),
                );

                tabs.appendChild(button);

                if (index === 0) {
                    showSheet(sheetName);
                }
            },
        );

        container.append(tabs, body);
        this.applyZoom(elements);
    }

    async renderMarkdown(item, elements) {
        elements.setMode('document');

        const state = this.state();

        const source = await fetchText(
            item.url,
            state.abortController.signal,
        );

        const container =
            elements.documentContent;

        container.innerHTML = DOMPurify.sanitize(
            marked.parse(source, {
                gfm: true,
                breaks: true,
            }),
            {
                USE_PROFILES: {
                    html: true,
                },
            },
        );

        container.classList.add(
            'media-markdown-preview',
        );

        this.applyZoom(elements);
    }

    async renderText(item, elements) {
        elements.setMode('code');

        const state = this.state();

        const source = await fetchText(
            item.url,
            state.abortController.signal,
        );

        const extension = extensionOf(item);
        const language =
            this.languageForExtension(extension);

        const pre = document.createElement('pre');
        pre.className = 'media-code-preview';

        const code = document.createElement('code');

        code.className = language
            ? `language-${language}`
            : '';

        code.textContent = source;
        pre.appendChild(code);

        const container =
            elements.documentContent;

        container.replaceChildren(pre);

        try {
            hljs.highlightElement(code);
        } catch {
            // Ignore syntax highlighter errors on un-registered languages
        }
        this.applyZoom(elements);
    }

    isTextExtension(extension) {
        return [
            'txt',
            'log',
            'json',
            'xml',
            'html',
            'htm',
            'css',
            'js',
            'mjs',
            'cjs',
            'ts',
            'tsx',
            'jsx',
            'php',
            'py',
            'java',
            'c',
            'cpp',
            'h',
            'hpp',
            'cs',
            'go',
            'rs',
            'sql',
            'yaml',
            'yml',
            'ini',
            'env',
            'sh',
            'ps1',
            'bat',
        ].includes(extension);
    }

    languageForExtension(extension) {
        return {
            js: 'javascript',
            mjs: 'javascript',
            cjs: 'javascript',
            ts: 'typescript',
            tsx: 'typescript',
            jsx: 'javascript',
            html: 'xml',
            htm: 'xml',
            xml: 'xml',
            php: 'php',
            py: 'python',
            yml: 'yaml',
            env: 'ini',
            sh: 'bash',
            ps1: 'powershell',
            bat: 'dos',
            h: 'c',
            hpp: 'cpp',
        }[extension] || extension;
    }

    setZoom(value, elements) {
        this.zoom = clamp(
            Number(value) || 1,
            0.25,
            5,
        );

        if (elements?.mode?.() === 'pdf') {
            this.renderPdfPage(elements);
            return;
        }

        this.applyZoom(elements);
    }

    zoomBy(delta, elements) {
        this.setZoom(
            this.zoom + delta,
            elements,
        );
    }

    resetZoom(elements) {
        this.setZoom(1, elements);

        const viewport =
            elements.documentViewport
            || elements.imageViewport;

        if (viewport) {
            viewport.scrollTo({
                top: 0,
                left: 0,
                behavior: 'smooth',
            });
        }
    }

    handleWheel(event, elements) {
        event.preventDefault();
        event.stopPropagation();

        const magnitude = Math.min(
            0.2,
            Math.max(
                0.06,
                Math.abs(event.deltaY) / 700,
            ),
        );

        this.zoomBy(
            event.deltaY > 0
                ? -magnitude
                : magnitude,
            elements,
        );

        return true;
    }

    applyZoom(elements) {
        const mode = elements?.mode?.();
        const target = elements.zoomTarget;

        if (! target) {
            return;
        }

        if (mode === 'image') {
            const state = this.state();
            const image = elements.image;
            const viewport = elements.imageViewport;

            if (
                ! image
                || ! viewport
                || state.imageBaseWidth < 1
                || state.imageBaseHeight < 1
            ) {
                return;
            }

            const width = Math.max(
                1,
                Math.round(
                    state.imageBaseWidth * this.zoom,
                ),
            );

            const height = Math.max(
                1,
                Math.round(
                    state.imageBaseHeight * this.zoom,
                ),
            );

            image.style.width = `${width}px`;
            image.style.height = `${height}px`;
            image.style.maxWidth = 'none';
            image.style.maxHeight = 'none';
            image.style.transform = 'none';

            target.style.width =
                `${Math.max(viewport.clientWidth, width + 32)}px`;

            target.style.height =
                `${Math.max(viewport.clientHeight, height + 32)}px`;

            target.style.removeProperty(
                '--media-preview-zoom',
            );

            viewport.classList.toggle(
                'is-pannable',
                this.zoom > 1,
            );

            return;
        }

        target.style.setProperty(
            '--media-preview-zoom',
            String(this.zoom),
        );
    }

    beginPan(event, viewport) {
        if (
            event.button !== 0
            || ! viewport
            || this.zoom <= 1
        ) {
            return false;
        }

        this.pan = {
            active: true,
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            scrollLeft: viewport.scrollLeft,
            scrollTop: viewport.scrollTop,
        };

        if (
            event.pointerId !== undefined
            && viewport.setPointerCapture
        ) {
            try {
                viewport.setPointerCapture(
                    event.pointerId,
                );
            } catch {
                // Pointer capture is optional.
            }
        }

        viewport.classList.add(
            'is-grabbing',
        );

        event.preventDefault();
        event.stopPropagation();

        return true;
    }

    movePan(event, viewport) {
        if (! this.pan.active || ! viewport) {
            return;
        }

        const deltaX =
            event.clientX - this.pan.startX;

        const deltaY =
            event.clientY - this.pan.startY;

        viewport.scrollLeft =
            this.pan.scrollLeft - deltaX;

        viewport.scrollTop =
            this.pan.scrollTop - deltaY;

        event.preventDefault();
    }

    endPan(viewport) {
        const pointerId = this.pan.pointerId;

        this.pan.active = false;

        if (
            viewport
            && pointerId !== undefined
            && viewport.releasePointerCapture
            && viewport.hasPointerCapture?.(pointerId)
        ) {
            try {
                viewport.releasePointerCapture(
                    pointerId,
                );
            } catch {
                // Pointer capture may already be released.
            }
        }

        viewport?.classList.remove(
            'is-grabbing',
        );
    }

    pause() {
        const player = this.state().player;

        if (player) {
            player.pause();
        }
    }

    async destroyRuntime() {
        const state = this.state();

        state.abortController?.abort();
        state.abortController = null;

        if (state.player) {
            try {
                state.player.pause();
                state.player.currentTime(0);
                state.player.dispose();
            } catch (error) {
                console.warn(
                    'Unable to dispose Video.js player:',
                    error,
                );
            }

            state.player = null;
        }

        if (state.pdfLoadingTask) {
            try {
                if (
                    typeof state.pdfLoadingTask.destroy
                    === 'function'
                ) {
                    await state.pdfLoadingTask.destroy();
                }
            } catch (error) {
                console.warn(
                    'Unable to destroy PDF loading task:',
                    error,
                );
            }

            state.pdfLoadingTask = null;
            state.pdf = null;
        } else if (state.pdf) {
            try {
                if (
                    typeof state.pdf.cleanup
                    === 'function'
                ) {
                    state.pdf.cleanup();
                }

                if (
                    typeof state.pdf.destroy
                    === 'function'
                ) {
                    await state.pdf.destroy();
                }
            } catch (error) {
                console.warn(
                    'Unable to clean up PDF document:',
                    error,
                );
            }

            state.pdf = null;
        }

        state.imageBaseWidth = 0;
        state.imageBaseHeight = 0;
    }

    async destroy() {
        this.currentToken++;

        await this.destroyRuntime();

        this.pdfPage = 1;
        this.pdfPages = 0;
        this.zoom = 1;

        runtime.delete(this.instanceId);
    }
}

export default MediaPreviewManager;
