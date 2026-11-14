<div x-data="mediaLocationPicker({
    searchUrl: '{{ route('admin.geo.search') }}',
    reverseUrl: '{{ route('admin.geo.reverse') }}',
    latestUrl: '{{ route('admin.geo.latest') }}',
})" x-init="init()" class="mb-4">

    <!-- Trigger Bar -->
    <div class="flex flex-wrap items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-lg">
        <button type="button" @click="openModal()" class="btn btn-primary !py-1.5 !px-3.5 !text-xs cursor-pointer inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span>Pick on Map</span>
        </button>

        <div class="flex items-center gap-2 text-xs">
            <template x-if="currentLat && currentLon">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 font-mono border border-indigo-200 dark:border-indigo-800/60">
                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                    <span x-text="Number(currentLat).toFixed(5) + ', ' + Number(currentLon).toFixed(5)"></span>
                </span>
            </template>
            <template x-if="!currentLat || !currentLon">
                <span class="text-slate-400 dark:text-slate-500 italic">No coordinates specified yet</span>
            </template>
        </div>
    </div>

    <template x-if="appliedNotice">
        <div class="mt-2 text-xs font-medium text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/50 px-3 py-2 rounded-lg flex items-center justify-between gap-2 animate-pulse">
            <span class="flex items-center gap-1.5">
                <span>✓</span>
                <span>Coordinates and address filled into the form. <strong>Please click "Save" at the bottom of the page to save changes!</strong></span>
            </span>
            <button type="button" @click="appliedNotice = false" class="text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 font-bold px-1.5 cursor-pointer">&times;</button>
        </div>
    </template>

    <!-- Modal Window -->
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-5"
         style="display: none;"
         @keydown.escape.window="closeModal()">

        <div @click.outside="closeModal()"
             class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[92vh]">

            <!-- Modal Header -->
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3 bg-slate-50 dark:bg-slate-800/60">
                <div class="flex items-center gap-2">
                    <span class="text-lg">📍</span>
                    <h3 class="font-semibold text-sm sm:text-base text-slate-800 dark:text-slate-100">
                        Select Location on Map
                    </h3>
                </div>

                <!-- Search Bar with Autocomplete Dropdown -->
                <div class="relative flex-1 max-w-md mx-2">
                    <div class="relative">
                        <input type="text"
                               x-model="searchQuery"
                               @input.debounce.350ms="performSearch()"
                               @keydown.enter.prevent="performSearch()"
                               placeholder="Search city, address or landmark..."
                               class="form-input !py-1.5 !pl-8 !pr-7 !text-xs w-full bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-lg">
                        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <button type="button" x-show="searchQuery" @click="searchQuery = ''; searchResults = []" class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs">✕</button>
                    </div>

                    <!-- Search dropdown results -->
                    <div x-show="searchResults.length > 0"
                         @click.outside="searchResults = []"
                         class="absolute left-0 right-0 mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl z-30 max-h-56 overflow-y-auto py-1">
                        <template x-for="(res, idx) in searchResults" :key="idx">
                            <button type="button"
                                    @click="selectSearchResult(res)"
                                    class="w-full text-left px-3 py-2 text-xs hover:bg-indigo-50 dark:hover:bg-slate-700 flex flex-col gap-0.5 border-b border-slate-100 dark:border-slate-700/50 last:border-0 transition">
                                <span class="font-medium text-slate-800 dark:text-slate-200" x-text="res.place_name || res.display_name"></span>
                                <span class="text-[11px] text-slate-500 dark:text-slate-400 truncate" x-text="res.display_name"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-800 transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Map Container -->
            <div class="relative w-full h-[460px] bg-slate-950">
                <div :id="mapElementId" class="w-full h-full"></div>

                <!-- Searching / Loading Indicator -->
                <div x-show="isSearching || isGeocoding"
                     class="absolute top-3 right-3 z-[1000] bg-white/90 dark:bg-slate-900/90 backdrop-blur-xs px-3 py-1.5 rounded-md shadow-md border border-slate-200 dark:border-slate-700 text-xs flex items-center gap-2 text-slate-700 dark:text-slate-200 font-medium">
                    <svg class="animate-spin h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span x-text="isSearching ? 'Searching places...' : 'Resolving address...'"></span>
                </div>
            </div>

            <!-- Location Details & Action Footer -->
            <div class="p-3 sm:p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <template x-if="markerLat && markerLon">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-xs sm:text-sm text-slate-800 dark:text-slate-100 truncate" x-text="resolvedPlace || resolvedCity || 'Selected Location'"></span>
                                <span class="text-xs text-slate-500 dark:text-slate-400 font-mono" x-text="'(' + markerLat.toFixed(5) + ', ' + markerLon.toFixed(5) + ')'"></span>
                            </div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-2 truncate">
                                <span x-show="resolvedCity" x-text="'City: ' + resolvedCity"></span>
                                <span x-show="resolvedCity && resolvedCountry">•</span>
                                <span x-show="resolvedCountry" x-text="'Country: ' + resolvedCountry"></span>
                            </div>
                        </div>
                    </template>
                    <template x-if="!markerLat || !markerLon">
                        <span class="text-xs text-slate-500 dark:text-slate-400 italic">
                            Click on the map or use search to place a location marker.
                        </span>
                    </template>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                    <button type="button" @click="clearLocation()" class="btn !py-1.5 !px-3 !text-xs text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 border-red-200 dark:border-red-800/50 cursor-pointer">
                        Clear Location
                    </button>
                    <button type="button" @click="closeModal()" class="btn !py-1.5 !px-3 !text-xs cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" @click="applyLocation()" :disabled="!markerLat || !markerLon" class="btn btn-primary !py-1.5 !px-4 !text-xs cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                        Apply to Form
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
if (!window.mediaLocationPicker) {
    window.mediaLocationPicker = function(config) {
        return {
            searchUrl: config.searchUrl,
            reverseUrl: config.reverseUrl,
            latestUrl: config.latestUrl,

            mapElementId: 'map-picker-' + Math.random().toString(36).substring(2, 9),
            isOpen: false,
            appliedNotice: false,

            map: null,
            marker: null,

            currentLat: null,
            currentLon: null,

            markerLat: null,
            markerLon: null,
            resolvedCountry: '',
            resolvedCity: '',
            resolvedPlace: '',

            searchQuery: '',
            searchResults: [],
            isSearching: false,
            isGeocoding: false,

            init() {
                this.syncCurrentFromForm();
            },

            syncCurrentFromForm() {
                const latInput = document.querySelector('input[name="latitude"]');
                const lonInput = document.querySelector('input[name="longitude"]');
                this.currentLat = latInput && latInput.value ? parseFloat(latInput.value) : null;
                this.currentLon = lonInput && lonInput.value ? parseFloat(lonInput.value) : null;
            },

            async openModal() {
                this.syncCurrentFromForm();
                this.isOpen = true;
                this.searchResults = [];
                this.searchQuery = '';

                this.markerLat = this.currentLat;
                this.markerLon = this.currentLon;

                const countryInput = document.querySelector('input[name="country"]');
                const cityInput = document.querySelector('input[name="city"]');
                const placeInput = document.querySelector('input[name="place_name"]');

                this.resolvedCountry = countryInput ? countryInput.value : '';
                this.resolvedCity = cityInput ? cityInput.value : '';
                this.resolvedPlace = placeInput ? placeInput.value : '';

                setTimeout(() => {
                    this.initMap();
                }, 200);
            },

            closeModal() {
                this.isOpen = false;
                if (this.map) {
                    this.map.remove();
                    this.map = null;
                    this.marker = null;
                }
            },

            async initMap() {
                const L = window.L;
                if (!L) {
                    setTimeout(() => this.initMap(), 100);
                    return;
                }

                const container = document.getElementById(this.mapElementId);
                if (!container) return;

                if (this.map) {
                    this.map.remove();
                    this.map = null;
                    this.marker = null;
                }

                let initialLat = this.markerLat;
                let initialLon = this.markerLon;
                let initialZoom = 15;

                // If no coordinates yet, fetch latest coordinates from gallery
                if (!initialLat || !initialLon) {
                    try {
                        const res = await fetch(this.latestUrl);
                        if (res.ok) {
                            const data = await res.json();
                            if (data.latitude && data.longitude) {
                                initialLat = data.latitude;
                                initialLon = data.longitude;
                                initialZoom = 13;
                            }
                        }
                    } catch (e) {
                        // Fallback
                    }
                }

                if (!initialLat || !initialLon) {
                    initialLat = 41.6938;
                    initialLon = 44.8015;
                    initialZoom = 12;
                }

                this.map = L.map(this.mapElementId, {
                    center: [initialLat, initialLon],
                    zoom: initialZoom,
                    zoomControl: true,
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(this.map);

                // If photo already had coordinates, put the marker immediately
                if (this.markerLat && this.markerLon) {
                    this.setMarker(this.markerLat, this.markerLon, false);
                }

                // Click on map to place or move marker
                this.map.on('click', (e) => {
                    this.setMarker(e.latlng.lat, e.latlng.lng, true);
                });

                setTimeout(() => {
                    this.map?.invalidateSize();
                }, 250);
            },

            setMarker(lat, lon, triggerGeocode = true) {
                const L = window.L;
                if (!L || !this.map) return;

                this.markerLat = lat;
                this.markerLon = lon;

                if (!this.marker) {
                    const customPin = L.divIcon({
                        className: 'custom-map-picker-pin',
                        html: `
                            <div style="transform: translate(-50%, -100%); filter: drop-shadow(0 4px 6px rgba(0,0,0,0.45)); cursor: pointer;">
                                <svg width="34" height="42" viewBox="0 0 34 42" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M17 0C7.61 0 0 7.61 0 17C0 29.75 17 42 17 42C17 42 34 29.75 34 17C34 7.61 26.39 0 17 0Z" fill="#4F46E5"/>
                                    <circle cx="17" cy="17" r="7" fill="white"/>
                                </svg>
                            </div>
                        `,
                        iconSize: [34, 42],
                        iconAnchor: [0, 0]
                    });

                    this.marker = L.marker([lat, lon], {
                        icon: customPin,
                        draggable: true
                    }).addTo(this.map);

                    this.marker.on('dragend', () => {
                        const pos = this.marker.getLatLng();
                        this.markerLat = pos.lat;
                        this.markerLon = pos.lng;
                        this.reverseGeocode(pos.lat, pos.lng);
                    });
                } else {
                    this.marker.setLatLng([lat, lon]);
                }

                if (triggerGeocode) {
                    this.reverseGeocode(lat, lon);
                }
            },

            async reverseGeocode(lat, lon) {
                if (this.isGeocoding) return;
                this.isGeocoding = true;

                try {
                    const res = await fetch(`${this.reverseUrl}?lat=${lat}&lon=${lon}`);
                    if (res.ok) {
                        const data = await res.json();
                        this.resolvedCountry = data.country || '';
                        this.resolvedCity = data.city || '';
                        this.resolvedPlace = data.place_name || '';
                    }
                } catch (e) {
                    // Ignore geocode error
                } finally {
                    this.isGeocoding = false;
                }
            },

            async performSearch() {
                const query = this.searchQuery.trim();
                if (query.length < 2) {
                    this.searchResults = [];
                    return;
                }

                this.isSearching = true;
                try {
                    const res = await fetch(`${this.searchUrl}?q=${encodeURIComponent(query)}`);
                    if (res.ok) {
                        const data = await res.json();
                        this.searchResults = data.results || [];
                    }
                } catch (e) {
                    this.searchResults = [];
                } finally {
                    this.isSearching = false;
                }
            },

            selectSearchResult(item) {
                this.searchResults = [];
                this.searchQuery = item.place_name || item.display_name;
                this.resolvedCountry = item.country || '';
                this.resolvedCity = item.city || '';
                this.resolvedPlace = item.place_name || item.display_name;

                this.setMarker(item.lat, item.lon, false);

                if (this.map) {
                    this.map.flyTo([item.lat, item.lon], 16, {
                        duration: 1.2
                    });
                }
            },

            applyLocation() {
                if (!this.markerLat || !this.markerLon) return;

                const setValue = (name, val) => {
                    const el = document.querySelector(`input[name="${name}"]`);
                    if (el) {
                        el.value = val;
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                };

                setValue('latitude', this.markerLat.toFixed(7));
                setValue('longitude', this.markerLon.toFixed(7));

                if (this.resolvedCountry) setValue('country', this.resolvedCountry);
                if (this.resolvedCity) setValue('city', this.resolvedCity);
                if (this.resolvedPlace) setValue('place_name', this.resolvedPlace);

                this.currentLat = this.markerLat;
                this.currentLon = this.markerLon;
                this.appliedNotice = true;

                this.closeModal();
            },

            clearLocation() {
                const clearValue = (name) => {
                    const el = document.querySelector(`input[name="${name}"]`);
                    if (el) {
                        el.value = '';
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                };

                clearValue('latitude');
                clearValue('longitude');
                clearValue('country');
                clearValue('city');
                clearValue('place_name');

                this.currentLat = null;
                this.currentLon = null;
                this.markerLat = null;
                this.markerLon = null;
                this.resolvedCountry = '';
                this.resolvedCity = '';
                this.resolvedPlace = '';

                this.closeModal();
            }
        };
    };
}
</script>
