@php
    $sources = \App\Models\MediaSource::query()->where('is_active', true)->get();
    $photosService = app(\App\Services\Google\GooglePhotosService::class);
    $isConnected = $photosService->isConnected();
@endphp

@if(!$isConnected)
    <div class="p-8 text-center space-y-4">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Google Photos Not Connected</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto">
            Please connect your Google account in Feature Settings to import photos directly into your Lumina gallery.
        </p>
        <div class="pt-2">
            <a href="/admin/page/feature-settings-page" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary/90 transition shadow-sm">
                Open Feature Settings
            </a>
        </div>
    </div>
@else
<div x-data="googlePhotosImporter({
    createSessionUrl: '{{ route('admin.google-photos.session.create') }}',
    sessionStatusUrl: '{{ url('/admin/google-photos/session') }}',
    itemsUrl: '{{ route('admin.google-photos.items') }}',
    thumbnailUrl: '{{ route('admin.google-photos.thumbnail') }}',
    importUrl: '{{ route('admin.google-photos.import') }}',
    csrf: '{{ csrf_token() }}',
    defaultSourceId: {{ $sources->first()?->id ?? 0 }}
})" x-init="initImporter()" class="space-y-4 text-slate-800 dark:text-slate-100">

    <!-- Error Banner -->
    <template x-if="errorMessage">
        <div class="p-4 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 text-sm flex items-start justify-between gap-3">
            <div class="flex items-start gap-2">
                <svg class="h-5 w-5 shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div x-html="errorMessage"></div>
            </div>
            <button type="button" @click="errorMessage = ''" class="font-bold text-lg leading-none">&times;</button>
        </div>
    </template>

    <!-- Success Banner -->
    <template x-if="successMessage">
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-600 dark:text-emerald-400 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span x-text="successMessage"></span>
            </div>
            <button type="button" @click="successMessage = ''" class="font-bold text-lg leading-none">&times;</button>
        </div>
    </template>

    <!-- Step 1: Initial Picker Prompt -->
    <div x-show="step === 'initial'" class="py-8 px-4 text-center space-y-6">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-primary/10 text-primary flex items-center justify-center">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
        </div>

        <div class="max-w-md mx-auto space-y-2">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Import from Google Photos</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Click below to open Google Photos, mark the photos or albums you need, and click <strong>Done</strong>. Lumina will automatically download them in the background.
            </p>
        </div>

        <div class="max-w-xs mx-auto text-left space-y-3">
            <div class="space-y-1.5">
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Destination Folder</label>
                <select x-model="selectedSourceId" class="w-full rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm py-2 px-3 focus:ring-2 focus:ring-primary focus:border-transparent">
                    @foreach($sources as $source)
                        <option value="{{ $source->id }}">{{ $source->name }} ({{ $source->path }})</option>
                    @endforeach
                </select>
            </div>

            <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-600 dark:text-slate-300">
                <input type="checkbox" x-model="autoImport" class="rounded border-slate-300 text-primary focus:ring-primary h-4 w-4">
                <span><strong>Fast Mode:</strong> Auto-import immediately after selecting</span>
            </label>
        </div>

        <div class="pt-2">
            <button
                type="button"
                @click="openPicker()"
                :disabled="isCreatingSession"
                class="inline-flex items-center gap-2.5 px-6 py-3 rounded-xl bg-primary hover:bg-primary/90 text-white font-medium text-sm transition shadow-md disabled:opacity-50"
            >
                <svg x-show="!isCreatingSession" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                <svg x-show="isCreatingSession" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span x-text="isCreatingSession ? 'Opening Google Photos...' : 'Open Google Photos & Select'"></span>
            </button>
        </div>
    </div>

    <!-- Step 2: Picking in progress (waiting for user selection) -->
    <div x-show="step === 'picking'" class="py-10 px-4 text-center space-y-6">
        <div class="relative w-16 h-16 mx-auto">
            <div class="absolute inset-0 rounded-full bg-primary/20 animate-ping"></div>
            <div class="relative w-16 h-16 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                <svg class="w-8 h-8 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
            </div>
        </div>

        <div class="max-w-md mx-auto space-y-2">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Google Photos Selection in Progress</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                1. Pick your photos in the opened Google Photos window.<br>
                2. Click <strong>Done</strong> in Google Photos when you're finished.
            </p>
            <p class="text-xs text-primary font-medium" x-show="autoImport">
                ⚡ Fast Mode: Lumina will automatically import them as soon as you click Done!
            </p>
        </div>

        <div class="flex items-center justify-center gap-3 pt-2">
            <button
                type="button"
                @click="checkSessionStatus(true)"
                class="px-4 py-2 rounded-xl bg-primary hover:bg-primary/90 text-white text-xs font-semibold transition shadow-sm"
            >
                I Finished Selecting
            </button>

            <button
                type="button"
                @click="reopenPickerWindow()"
                class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-medium transition"
            >
                Reopen Window
            </button>

            <button
                type="button"
                @click="cancelPicking()"
                class="px-4 py-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 text-xs font-medium transition"
            >
                Cancel
            </button>
        </div>
    </div>

    <!-- Step 3: Fast Mode Importing Spinner -->
    <div x-show="step === 'importing'" class="py-12 px-4 text-center space-y-4">
        <div class="w-14 h-14 mx-auto rounded-full bg-primary/10 text-primary flex items-center justify-center">
            <svg class="animate-spin w-8 h-8" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
        </div>
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Starting Background Import...</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400">Receiving selected photos from Google Photos and scheduling background downloads.</p>
    </div>

    <!-- Step 4: Imported Success View -->
    <div x-show="step === 'imported'" class="py-8 px-4 text-center space-y-6">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <div class="max-w-md mx-auto space-y-2">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Import Successfully Started!</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                <span class="font-semibold text-slate-700 dark:text-slate-200" x-text="`${importedCount} photo(s)`"></span> are now downloading in the background. They will appear in your gallery shortly.
            </p>
        </div>

        <div class="flex items-center justify-center gap-3 pt-2">
            <button
                type="button"
                @click="openPicker()"
                class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white text-xs font-semibold transition shadow-sm flex items-center gap-2"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Import More Photos</span>
            </button>

            <button
                type="button"
                @click="window.location.reload()"
                class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-medium transition"
            >
                Done & Refresh Gallery
            </button>
        </div>
    </div>

    <!-- Step 5: Manual Preview and Selection (when Fast Mode is disabled) -->
    <div x-show="step === 'preview'" class="space-y-4">
        <!-- Top Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
            <div class="flex items-center gap-3">
                <label class="text-sm font-medium text-slate-600 dark:text-slate-300">Destination:</label>
                <select x-model="selectedSourceId" class="rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm py-1.5 px-3 focus:ring-2 focus:ring-primary focus:border-transparent">
                    @foreach($sources as $source)
                        <option value="{{ $source->id }}">{{ $source->name }} ({{ $source->path }})</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-3">
                <label class="inline-flex items-center gap-2 cursor-pointer text-sm">
                    <input type="checkbox" x-model="onlyUnimported" class="rounded border-slate-300 text-primary focus:ring-primary">
                    <span>Hide imported</span>
                </label>

                <button type="button" @click="selectAllNew()" class="text-xs px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 font-medium transition">
                    Select All New
                </button>

                <button type="button" @click="deselectAll()" class="text-xs px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 font-medium transition">
                    Deselect All
                </button>

                <button type="button" @click="openPicker()" class="text-xs px-2.5 py-1.5 rounded-lg bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 font-medium transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Pick More</span>
                </button>
            </div>
        </div>

        <!-- Photos Grid -->
        <div class="relative min-h-[300px]">
            <template x-if="isLoading && items.length === 0">
                <div class="flex flex-col items-center justify-center py-16 gap-3 text-slate-400">
                    <svg class="animate-spin h-8 w-8 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span class="text-sm">Loading picked photos...</span>
                </div>
            </template>

            <template x-if="!isLoading && visibleItems().length === 0 && !errorMessage">
                <div class="flex flex-col items-center justify-center py-16 gap-3 text-slate-400">
                    <svg class="h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-sm font-medium">No photos selected in this batch</span>
                    <button type="button" @click="openPicker()" class="text-xs px-3 py-1.5 rounded-lg bg-primary text-white font-medium">
                        Open Google Photos Picker
                    </button>
                </div>
            </template>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 max-h-[500px] overflow-y-auto p-1">
                <template x-for="item in visibleItems()" :key="item.id">
                    <div
                        @click="toggleItem(item)"
                        class="group relative rounded-xl border overflow-hidden cursor-pointer transition select-none bg-slate-900/5 dark:bg-slate-900/40"
                        :class="{
                            'border-primary ring-2 ring-primary/40': selectedIds.includes(item.id),
                            'border-slate-200 dark:border-slate-700 opacity-60': item.is_imported,
                            'border-slate-200 dark:border-slate-700 hover:border-slate-300': !selectedIds.includes(item.id) && !item.is_imported
                        }"
                    >
                        <div class="aspect-square w-full relative bg-slate-900/10 dark:bg-slate-900/60 flex items-center justify-center overflow-hidden">
                            <img
                                :src="thumbnailSrc(item.baseUrl)"
                                :alt="item.filename"
                                loading="lazy"
                                class="w-full h-full object-cover transition group-hover:scale-105 duration-200"
                            />
                        </div>

                        <!-- Badge: Already Imported -->
                        <template x-if="item.is_imported">
                            <div class="absolute top-2 left-2 bg-slate-900/80 backdrop-blur-sm text-white text-[10px] font-semibold px-2 py-0.5 rounded-full flex items-center gap-1 shadow-sm">
                                <svg class="h-3 w-3 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                <span>Imported</span>
                            </div>
                        </template>

                        <!-- Checkbox overlay -->
                        <div class="absolute top-2 right-2">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(item.id)"
                                @click.stop="toggleItem(item)"
                                class="rounded border-slate-300 text-primary focus:ring-primary shadow-sm h-4 w-4"
                            />
                        </div>

                        <!-- Filename footer -->
                        <div class="p-1.5 text-[11px] truncate text-slate-600 dark:text-slate-400 bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm border-t border-slate-100 dark:border-slate-800" :title="item.filename" x-text="item.filename"></div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Bottom Actions & Pagination -->
        <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-slate-200 dark:border-slate-700">
            <div>
                <template x-if="nextPageToken">
                    <button
                        type="button"
                        @click="loadItems(nextPageToken)"
                        :disabled="isLoading"
                        class="text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 font-medium transition flex items-center gap-2"
                    >
                        <span x-show="!isLoading">Load More Photos</span>
                        <span x-show="isLoading">Loading...</span>
                    </button>
                </template>
            </div>

            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-500" x-text="`${selectedIds.length} photo(s) selected`"></span>
                <button
                    type="button"
                    @click="startImport()"
                    :disabled="selectedIds.length === 0 || isImporting"
                    class="px-4 py-2 rounded-lg bg-primary hover:bg-primary/90 text-white font-medium text-sm transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 shadow-sm"
                >
                    <span x-show="!isImporting" x-text="`Start Import (${selectedIds.length})`"></span>
                    <span x-show="isImporting">Starting Background Job...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
if (!window.googlePhotosImporter) {
    window.googlePhotosImporter = function(config) {
        return {
            createSessionUrl: config.createSessionUrl,
            sessionStatusUrl: config.sessionStatusUrl,
            itemsUrl: config.itemsUrl,
            thumbnailUrl: config.thumbnailUrl,
            importUrl: config.importUrl,
            csrf: config.csrf,
            selectedSourceId: config.defaultSourceId,

            step: 'initial', // 'initial', 'picking', 'importing', 'imported', 'preview'
            sessionId: null,
            pickerUri: null,
            pollInterval: null,
            pickerWindowRef: null,
            autoImport: true,
            importedCount: 0,

            items: [],
            selectedIds: [],
            onlyUnimported: false,
            nextPageToken: null,

            isCreatingSession: false,
            isLoading: false,
            isImporting: false,
            errorMessage: '',
            successMessage: '',

            initImporter() {
                // Initial state
            },

            async openPicker() {
                this.isCreatingSession = true;
                this.errorMessage = '';
                this.successMessage = '';

                // Open window on direct user click to avoid browser popup blockers
                this.pickerWindowRef = window.open('about:blank', 'google_photos_picker', 'width=960,height=720,menubar=no,toolbar=no');

                try {
                    const response = await fetch(this.createSessionUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        try { if (this.pickerWindowRef) this.pickerWindowRef.close(); } catch (e) {}
                        this.pickerWindowRef = null;
                        const errText = data.error || 'Failed to create Google Photos picker session';
                        if (errText.includes('insufficient authentication scopes') || errText.includes('PERMISSION_DENIED')) {
                            throw new Error('Google Photos permissions need to be updated. Please <a href="/admin/page/feature-settings-page" class="font-bold underline text-primary">click here to Reconnect Account</a> in Feature Settings.');
                        }
                        throw new Error(errText);
                    }

                    this.sessionId = data.id;
                    this.pickerUri = data.pickerUri;

                    if (this.pickerWindowRef) {
                        this.pickerWindowRef.location.href = data.pickerUri;
                    } else {
                        window.open(data.pickerUri, '_blank');
                    }

                    this.step = 'picking';
                    this.startPollingSession();
                } catch (err) {
                    try { if (this.pickerWindowRef) this.pickerWindowRef.close(); } catch (e) {}
                    this.pickerWindowRef = null;
                    this.errorMessage = err.message || 'Error opening Google Photos picker';
                } finally {
                    this.isCreatingSession = false;
                }
            },

            reopenPickerWindow() {
                if (this.pickerUri) {
                    this.pickerWindowRef = window.open(this.pickerUri, 'google_photos_picker', 'width=960,height=720,menubar=no,toolbar=no');
                }
            },

            cancelPicking() {
                this.stopPollingSession();
                this.step = this.items.length > 0 ? 'preview' : 'initial';
            },

            startPollingSession() {
                this.stopPollingSession();
                this.pollInterval = setInterval(async () => {
                    await this.checkSessionStatus(false);
                }, 2000);
            },

            stopPollingSession() {
                if (this.pollInterval) {
                    clearInterval(this.pollInterval);
                    this.pollInterval = null;
                }
            },

            async checkSessionStatus(manual = false) {
                if (!this.sessionId) return;

                try {
                    const res = await fetch(`${this.sessionStatusUrl}/${this.sessionId}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await res.json();

                    if (res.ok && data.mediaItemsSet) {
                        this.stopPollingSession();

                        try {
                            if (this.pickerWindowRef) {
                                this.pickerWindowRef.close();
                            }
                        } catch (e) {
                            // Cross-origin restriction on popup close
                        }
                        this.pickerWindowRef = null;

                        if (this.autoImport) {
                            await this.importDirectFromSession();
                        } else {
                            this.step = 'preview';
                            await this.loadItems();
                        }
                    } else if (manual) {
                        this.errorMessage = 'Google Photos selection is not finished yet. Please select photos and click Done in the Google window.';
                    }
                } catch (e) {
                    console.error('Session status check error:', e);
                    if (manual) {
                        this.errorMessage = 'Could not check session status. Please try again.';
                    }
                }
            },

            async importDirectFromSession() {
                this.step = 'importing';
                this.errorMessage = '';

                try {
                    const response = await fetch(this.importUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            media_source_id: this.selectedSourceId,
                            session_id: this.sessionId
                        })
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.error || data.message || 'Failed to start import job');
                    }

                    this.importedCount = data.count || 0;
                    this.step = 'imported';
                } catch (err) {
                    this.errorMessage = err.message || 'Error dispatching import job';
                    this.step = 'initial';
                }
            },

            thumbnailSrc(baseUrl) {
                if (!baseUrl) return '';
                return `${this.thumbnailUrl}?url=${encodeURIComponent(baseUrl)}&size=300`;
            },

            visibleItems() {
                if (this.onlyUnimported) {
                    return this.items.filter(i => !i.is_imported);
                }
                return this.items;
            },

            async loadItems(pageToken = null) {
                this.isLoading = true;
                this.errorMessage = '';

                try {
                    const url = new URL(this.itemsUrl, window.location.origin);
                    if (this.sessionId) {
                        url.searchParams.set('sessionId', this.sessionId);
                    }
                    if (pageToken) {
                        url.searchParams.set('pageToken', pageToken);
                    }

                    const response = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.error || 'Failed to fetch photos from Google Photos');
                    }

                    const newItems = data.items || [];

                    if (pageToken) {
                        this.items = [...this.items, ...newItems];
                    } else {
                        this.items = newItems;
                        // Pre-select new unimported items
                        const unimported = newItems.filter(i => !i.is_imported).map(i => i.id);
                        this.selectedIds = [...new Set([...this.selectedIds, ...unimported])];
                    }

                    this.nextPageToken = data.nextPageToken || null;
                } catch (err) {
                    this.errorMessage = err.message || 'Error connecting to Google Photos API';
                } finally {
                    this.isLoading = false;
                }
            },

            toggleItem(item) {
                const index = this.selectedIds.indexOf(item.id);
                if (index > -1) {
                    this.selectedIds.splice(index, 1);
                } else {
                    this.selectedIds.push(item.id);
                }
            },

            selectAllNew() {
                const newItems = this.items.filter(i => !i.is_imported);
                this.selectedIds = [...new Set([...this.selectedIds, ...newItems.map(i => i.id)])];
            },

            deselectAll() {
                this.selectedIds = [];
            },

            async startImport() {
                if (this.selectedIds.length === 0 || !this.selectedSourceId) {
                    return;
                }

                this.isImporting = true;
                this.errorMessage = '';
                this.successMessage = '';

                const selectedItems = this.items.filter(i => this.selectedIds.includes(i.id));

                try {
                    const response = await fetch(this.importUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            media_source_id: this.selectedSourceId,
                            items: selectedItems
                        })
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Failed to start import job');
                    }

                    this.successMessage = `Import of ${selectedItems.length} photos dispatched to background queue!`;
                    this.items.forEach(i => {
                        if (this.selectedIds.includes(i.id)) {
                            i.is_imported = true;
                        }
                    });
                    this.selectedIds = [];
                } catch (err) {
                    this.errorMessage = err.message || 'Error dispatching import job';
                } finally {
                    this.isImporting = false;
                }
            }
        };
    };
}
</script>
@endif
