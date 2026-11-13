@props([
    'media',
])

<script>
if (!window.mediaImageEditor) {
    window.mediaImageEditor = function(config) {
        return {
            id: config.id,
            streamUrl: config.streamUrl,
            applyUrl: config.applyUrl,
            previewUrl: config.previewUrl,
            csrf: config.csrf,

            cropper: null,
            currentImageSrc: config.streamUrl,
            rotation: 0,
            aspectRatioKey: 'free',
            portraitMode: false,
            blurRadius: 20,

            isLoadingBokeh: false,
            isSaving: false,
            statusMessage: '',
            errorMessage: '',

            initEditor() {
                setTimeout(() => {
                    this.setupCropper();
                }, 200);
            },

            setupCropper() {
                const imgElement = document.getElementById('editor-img-' + this.id);
                if (!imgElement || typeof window.Cropper === 'undefined') {
                    setTimeout(() => this.setupCropper(), 150);
                    return;
                }

                const container = imgElement.closest('.media-editor-canvas-wrap');
                if (!container || container.offsetWidth < 100) {
                    setTimeout(() => this.setupCropper(), 150);
                    return;
                }

                const createInstance = () => {
                    if (this.cropper) {
                        this.cropper.destroy();
                    }

                    this.cropper = new window.Cropper(imgElement, {
                        template: '<cropper-canvas background>'
                            + '<cropper-image rotatable scalable skewable translatable></cropper-image>'
                            + '<cropper-shade hidden></cropper-shade>'
                            + '<cropper-handle action="select" plain></cropper-handle>'
                            + '<cropper-selection initial-coverage="0.95" movable resizable>'
                            + '<cropper-grid role="grid" bordered covered></cropper-grid>'
                            + '<cropper-crosshair centered></cropper-crosshair>'
                            + '<cropper-handle action="move" theme-color="rgba(255, 255, 255, 0.35)"></cropper-handle>'
                            + '<cropper-handle action="n-resize"></cropper-handle>'
                            + '<cropper-handle action="e-resize"></cropper-handle>'
                            + '<cropper-handle action="s-resize"></cropper-handle>'
                            + '<cropper-handle action="w-resize"></cropper-handle>'
                            + '<cropper-handle action="ne-resize"></cropper-handle>'
                            + '<cropper-handle action="nw-resize"></cropper-handle>'
                            + '<cropper-handle action="se-resize"></cropper-handle>'
                            + '<cropper-handle action="sw-resize"></cropper-handle>'
                            + '</cropper-selection>'
                            + '</cropper-canvas>'
                    });
                };

                if (imgElement.complete && imgElement.naturalWidth > 0) {
                    createInstance();
                } else {
                    imgElement.onload = () => createInstance();
                }
            },

            rotate(deg) {
                this.rotation = (this.rotation + deg) % 360;
                const cropperImage = this.cropper?.getCropperImage();
                if (cropperImage) {
                    cropperImage.$rotate(deg);
                }
            },

            setAspectRatio(ratio, key) {
                this.aspectRatioKey = key;
                const selection = this.cropper?.getCropperSelection();
                if (selection) {
                    selection.aspectRatio = isNaN(ratio) ? 0 : ratio;
                }
            },

            reset() {
                this.rotation = 0;
                this.aspectRatioKey = 'free';
                this.portraitMode = false;
                this.currentImageSrc = this.streamUrl;
                this.errorMessage = '';
                const cropperImage = this.cropper?.getCropperImage();
                const selection = this.cropper?.getCropperSelection();
                if (cropperImage) {
                    cropperImage.$resetTransform();
                    cropperImage.src = this.streamUrl;
                }
                if (selection) {
                    selection.aspectRatio = 0;
                    selection.$reset();
                }
            },

            async previewBokeh() {
                if (this.isLoadingBokeh) return;
                this.isLoadingBokeh = true;
                this.statusMessage = 'AI is segmenting subject and generating bokeh...';
                this.errorMessage = '';

                try {
                    const response = await fetch(this.previewUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            blur_radius: parseInt(this.blurRadius)
                        })
                    });

                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.message || data.error || 'Failed to generate bokeh preview');
                    }

                    if (data.preview_data_url) {
                        const cropperImage = this.cropper?.getCropperImage();
                        if (cropperImage) {
                            cropperImage.src = data.preview_data_url;
                        }
                    }
                } catch (err) {
                    this.errorMessage = err.message;
                } finally {
                    this.isLoadingBokeh = false;
                }
            },

            getCropData() {
                const cropperImage = this.cropper?.getCropperImage();
                const cropperSelection = this.cropper?.getCropperSelection();
                if (!cropperImage || !cropperSelection) {
                    return { x: 0, y: 0, width: 1, height: 1 };
                }

                const imgElement = cropperImage.$image || cropperImage;
                const naturalWidth = imgElement.naturalWidth || 1;
                const naturalHeight = imgElement.naturalHeight || 1;

                const imgRect = cropperImage.getBoundingClientRect();
                const selRect = cropperSelection.getBoundingClientRect();

                if (imgRect.width > 0 && imgRect.height > 0) {
                    const scaleX = naturalWidth / imgRect.width;
                    const scaleY = naturalHeight / imgRect.height;
                    return {
                        x: Math.max(0, Math.round((selRect.left - imgRect.left) * scaleX)),
                        y: Math.max(0, Math.round((selRect.top - imgRect.top) * scaleY)),
                        width: Math.max(1, Math.round(selRect.width * scaleX)),
                        height: Math.max(1, Math.round(selRect.height * scaleY)),
                    };
                }

                return { x: 0, y: 0, width: naturalWidth, height: naturalHeight };
            },

            async save() {
                if (this.isSaving || !this.cropper) return;
                this.isSaving = true;
                this.statusMessage = 'Processing and saving image...';
                this.errorMessage = '';

                const cropData = this.getCropData();

                try {
                    const response = await fetch(this.applyUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            rotation: this.rotation,
                            crop: {
                                x: Math.max(0, cropData.x),
                                y: Math.max(0, cropData.y),
                                width: Math.max(1, cropData.width),
                                height: Math.max(1, cropData.height),
                            },
                            portrait_mode: this.portraitMode,
                            blur_radius: parseInt(this.blurRadius),
                        })
                    });

                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.message || data.error || 'Failed to save edited image');
                    }

                    window.location.reload();
                } catch (err) {
                    this.errorMessage = err.message;
                    this.isSaving = false;
                }
            }
        };
    };
}

if (window.Alpine) {
    window.Alpine.data('mediaImageEditor', window.mediaImageEditor);
} else {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('mediaImageEditor', window.mediaImageEditor);
    });
}
</script>

<div x-data="mediaImageEditor({
    id: {{ $media->id }},
    streamUrl: '{{ route('media.stream', ['mediaFile' => $media->uuid]) }}?v={{ $media->updated_at?->timestamp }}',
    applyUrl: '{{ route('admin.media.apply-edit', ['mediaFile' => $media->id]) }}',
    previewUrl: '{{ route('admin.media.preview-bokeh', ['mediaFile' => $media->id]) }}',
    csrf: '{{ csrf_token() }}'
})" x-init="initEditor()" class="flex flex-col gap-3.5 w-full text-sm text-slate-800 dark:text-slate-100">

    <!-- Toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-2 p-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-lg">
        <!-- Rotation & Reset -->
        <div class="flex items-center gap-1">
            <button type="button" @click="rotate(-90)" class="px-2.5 py-1.5 bg-white dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded text-xs font-semibold shadow-xs transition cursor-pointer" title="Rotate counter-clockwise (-90°)">
                ↺ -90°
            </button>
            <button type="button" @click="rotate(90)" class="px-2.5 py-1.5 bg-white dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded text-xs font-semibold shadow-xs transition cursor-pointer" title="Rotate clockwise (+90°)">
                ↻ +90°
            </button>
            <button type="button" @click="reset()" class="px-2.5 py-1.5 bg-white dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded text-xs font-semibold shadow-xs transition cursor-pointer" title="Reset all changes">
                Reset
            </button>
        </div>

        <!-- Aspect Ratio -->
        <div class="flex items-center gap-1">
            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium mr-1">Aspect Ratio:</span>
            <button type="button" @click="setAspectRatio(NaN, 'free')" :class="aspectRatioKey === 'free' ? 'bg-primary text-white shadow-xs' : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600'" class="px-2 py-1 rounded text-xs font-semibold shadow-xs transition cursor-pointer">
                Free
            </button>
            <button type="button" @click="setAspectRatio(1, '1:1')" :class="aspectRatioKey === '1:1' ? 'bg-primary text-white shadow-xs' : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600'" class="px-2 py-1 rounded text-xs font-semibold shadow-xs transition cursor-pointer">
                1:1
            </button>
            <button type="button" @click="setAspectRatio(4/3, '4:3')" :class="aspectRatioKey === '4:3' ? 'bg-primary text-white shadow-xs' : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600'" class="px-2 py-1 rounded text-xs font-semibold shadow-xs transition cursor-pointer">
                4:3
            </button>
            <button type="button" @click="setAspectRatio(16/9, '16:9')" :class="aspectRatioKey === '16:9' ? 'bg-primary text-white shadow-xs' : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600'" class="px-2 py-1 rounded text-xs font-semibold shadow-xs transition cursor-pointer">
                16:9
            </button>
        </div>
    </div>

    <!-- AI Portrait Mode Settings -->
    <div class="flex flex-wrap items-center justify-between gap-3 p-3 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-lg">
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" x-model="portraitMode" class="rounded border-slate-300 dark:border-slate-600 text-primary focus:ring-primary h-4 w-4">
                <span class="font-semibold text-xs text-slate-700 dark:text-slate-200">✨ Portrait Mode (AI Bokeh)</span>
            </label>

            <div x-show="portraitMode" class="flex items-center gap-2 pl-3 border-l border-slate-300 dark:border-slate-600">
                <span class="text-xs text-slate-500 dark:text-slate-400">Blur Intensity:</span>
                <input type="range" min="5" max="45" step="5" x-model="blurRadius" class="w-24 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-lg appearance-none cursor-pointer accent-primary">
                <span class="text-xs font-mono font-medium text-slate-600 dark:text-slate-300" x-text="blurRadius + 'px'"></span>
            </div>
        </div>

        <div x-show="portraitMode">
            <button type="button" @click="previewBokeh()" :disabled="isLoadingBokeh" class="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-semibold shadow-xs disabled:opacity-50 transition cursor-pointer">
                <svg x-show="isLoadingBokeh" width="16" height="16" style="width: 16px; height: 16px; min-width: 16px; min-height: 16px; display: inline-block;" class="animate-spin -ml-1 mr-1 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span x-text="isLoadingBokeh ? 'Segmenting subject...' : 'Preview Bokeh'"></span>
            </button>
        </div>
    </div>

    <!-- Canvas Area with fixed explicit height -->
    <div class="media-editor-canvas-wrap h-[520px] w-full relative bg-slate-950 rounded-lg overflow-hidden flex items-center justify-center border border-slate-200 dark:border-slate-800">
        <img :id="'editor-img-' + id" :src="currentImageSrc" alt="Image to edit" crossOrigin="anonymous" />

        <!-- Loading Overlay -->
        <div x-show="isSaving || isLoadingBokeh" class="absolute inset-0 bg-black/65 backdrop-blur-xs flex flex-col items-center justify-center gap-2 z-20 text-white">
            <svg width="32" height="32" style="width: 32px; height: 32px; min-width: 32px; min-height: 32px;" class="animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <span class="text-sm font-medium" x-text="statusMessage"></span>
        </div>
    </div>

    <!-- Error message banner -->
    <div x-show="errorMessage" x-text="errorMessage" class="p-2.5 bg-red-500/10 border border-red-500/25 text-red-600 dark:text-red-400 text-xs rounded-md"></div>

    <!-- Modal Actions Footer -->
    <div class="flex items-center justify-between border-t border-slate-200 dark:border-slate-700 pt-3 gap-3">
        <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">
            ⚠️ Saving will permanently overwrite the original image file on disk
        </span>

        <div class="flex items-center gap-2">
            <button type="button" @click="$dispatch('modal-close')" class="px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition cursor-pointer">
                Cancel
            </button>
            <button type="button" @click="save()" :disabled="isSaving" class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-semibold shadow-xs disabled:opacity-50 transition cursor-pointer">
                <svg x-show="isSaving" width="16" height="16" style="width: 16px; height: 16px; min-width: 16px; min-height: 16px; display: inline-block;" class="animate-spin -ml-1 mr-1 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span x-text="isSaving ? 'Saving...' : 'Save Changes'"></span>
            </button>
        </div>
    </div>
</div>
