/**
 * [ELITE HARDENED] BookIT Map Engine (Leaflet / OSM Edition)
 * Replaced Google Maps with a fully API-keyless architecture.
 * Features: Multi-Tile Failover, Marker Clustering, Performance Viewport Optimization, WGS84 Standardization.
 */

window.BookIT = window.BookIT || {};

BookIT.Map = (function() {
    let map = null;
    let markers = [];
    let clusterGroup = null;
    let currentTileLayer = null;
    let pickerMarker = null;
    let fallbackLevel = 0;

    const DEFAULT_CONFIG = {
        zoom: 12,
        center: [14.5995, 120.9842], // Manila default [lat, lng]
        scrollWheelZoom: true,
        fadeAnimation: true,
        markerZoomAnimation: true
    };

    /**
     * TILE RELIABILITY STRATEGY
     * Multi-provider fallback to ensure 99.9% map availability.
     */
    const TILE_SERVERS = [
        { 
            url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', 
            attribution: '&copy; OpenStreetMap' 
        },
        { 
            url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', 
            attribution: '&copy; CartoDB' 
        },
        { 
            url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', 
            attribution: '&copy; OpenTopoMap' 
        }
    ];

    return {
        /**
         * [CORE] Leaflet Dependency Loader
         * Hardened with polling and timeout to prevent race conditions.
         */
        loadScript: function(key_not_used, callback) {
            if (window.L) {
                if (callback) callback();
                return;
            }

            console.log("BookIT.Map: Waiting for Leaflet runtime...");
            let attempts = 0;
            const maxAttempts = 50; // 5 seconds total
            const interval = setInterval(() => {
                attempts++;
                if (window.L) {
                    clearInterval(interval);
                    console.log("BookIT.Map: Runtime detected. Initializing callbacks.");
                    if (callback) callback();
                } else if (attempts >= maxAttempts) {
                    clearInterval(interval);
                    console.error("BookIT.Map: Leaflet load timeout.");
                }
            }, 100);
        },

        /**
         * Initialize the map with Multi-Tile Failover
         */
        init: function(containerId, options = {}) {
            const container = document.getElementById(containerId);
            if (!container) return null;

            // Remove legacy skeletons
            container.classList.remove('map-loading');
            container.innerHTML = '';

            // 1. WGS84 COORD STANDARDIZATION
            const config = { ...DEFAULT_CONFIG, ...options };
            if (config.center && typeof config.center === 'object' && !Array.isArray(config.center)) {
                config.center = [parseFloat(config.center.lat), parseFloat(config.center.lng)];
            }

            try {
                if (!window.L) throw new Error("Leaflet is not defined.");
                map = L.map(containerId, config);
                this.addTileLayer(0);
                return map;
            } catch (error) {
                console.error("BookIT.Map: Initialization Failed ->", error);
                this.showError(container, "Map engine failed to load. Please check your internet connection.");
                return null;
            }
        },

        /**
         * Tile Layer management with Failover logic
         */
        addTileLayer: function(level) {
            if (!map || !window.L) return;
            if (level >= TILE_SERVERS.length) {
                console.error("BookIT.Map: All tile providers failed.");
                return;
            }

            const server = TILE_SERVERS[level];
            if (currentTileLayer) map.removeLayer(currentTileLayer);

            currentTileLayer = L.tileLayer(server.url, {
                attribution: server.attribution,
                crossOrigin: true
            }).addTo(map);

            currentTileLayer.on('tileerror', () => {
                console.warn(`BookIT.Map: Provider ${level} failed. Attempting fallback...`);
                this.addTileLayer(level + 1);
            });
        },

        /**
         * Specialized Markers with Performance Clustering
         */
        addMarkers: function(locations, options = {}) {
            if (!map || !window.L || !window.L.markerClusterGroup) {
                console.warn("BookIT.Map: MarkerCluster dependency missing.");
                return;
            }
            this.clearMarkers();

            clusterGroup = L.markerClusterGroup({
                chunkedLoading: true,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false,
                zoomToBoundsOnClick: true,
                maxClusterRadius: 50
            });

            locations.forEach(loc => {
                // FORCE WGS84 PRECISION
                const lat = parseFloat(loc.lat);
                const lng = parseFloat(loc.lng);
                if (isNaN(lat) || isNaN(lng)) return;

                const pos = [lat, lng];
                let icon;

                if (options.type === 'pricing') {
                    const priceLabel = loc.displayPrice || `₱${Math.round(loc.price).toLocaleString()}`;
                    icon = L.divIcon({
                        className: 'bookit-leaflet-marker',
                        html: `<div class="price-tag ${options.activeUnitId == loc.unit_id ? 'active' : ''}">${priceLabel}</div>`,
                        iconSize: [60, 30],
                        iconAnchor: [30, 15]
                    });
                } else if (options.type === 'status') {
                    const statusColor = loc.status === 'approved' ? '#22c55e' : (loc.status === 'pending' ? '#eab308' : '#ef4444');
                    icon = L.divIcon({
                        className: 'bookit-leaflet-marker',
                        html: `<div class="status-marker" style="background: ${statusColor}"><i class="fas fa-home"></i></div>`,
                        iconSize: [32, 32],
                        iconAnchor: [16, 16]
                    });
                } else {
                    icon = new L.Icon.Default();
                }

                const marker = L.marker(pos, { icon });
                if (options.onClick) marker.on('click', () => options.onClick(loc, marker));
                
                if (loc.title || loc.unit_name) {
                    const content = `<b>${loc.title || loc.unit_name}</b><br>${loc.branch_name || ''}`;
                    marker.bindPopup(content);
                }

                clusterGroup.addLayer(marker);
                markers.push(marker);
            });

            map.addLayer(clusterGroup);

            if (options.fitBounds && markers.length > 0) {
                const group = new L.featureGroup(markers);
                map.fitBounds(group.getBounds(), { padding: [50, 50] });
            }
        },

        /**
         * Admin/Host Location Picker
         */
        enablePicker: function(callback, initialCoords = null) {
            if (!map || !window.L) return;

            let pos = initialCoords ? 
                    (Array.isArray(initialCoords) ? initialCoords : [parseFloat(initialCoords.lat), parseFloat(initialCoords.lng)]) : 
                    map.getCenter();
            
            if (pickerMarker) map.removeLayer(pickerMarker);

            pickerMarker = L.marker(pos, {
                draggable: true,
                autoPan: true,
                zIndexOffset: 1000
            }).addTo(map);

            const emit = (latlng) => {
                callback({ lat: latlng.lat.toFixed(8), lng: latlng.lng.toFixed(8) });
            };

            pickerMarker.on('dragend', (e) => emit(e.target.getLatLng()));
            map.on('click', (e) => {
                pickerMarker.setLatLng(e.latlng);
                emit(e.latlng);
            });
        },

        /**
         * [HARDENED] Geolocation with UX Error Handling
         */
        getUserLocation: function(callback) {
            if (!map || !window.L) {
                if (callback) callback(null, "Map engine not ready.");
                return;
            }

            console.log("BookIT.Map: Requesting user location...");
            
            map.locate({ setView: true, maxZoom: 16 });
            
            map.once('locationfound', (e) => {
                const pos = { lat: e.latlng.lat, lng: e.latlng.lng };
                console.log("BookIT.Map: Location found ->", pos);
                if (callback) callback(pos, null);
            });

            map.once('locationerror', (e) => {
                let msg = "Location access denied. Please enable location permissions.";
                if (e.message.indexOf('denied') !== -1) {
                    msg = "Location permission denied by user.";
                } else if (e.message.indexOf('timeout') !== -1) {
                    msg = "Location request timed out.";
                }
                console.warn("BookIT.Map: Geolocation error ->", msg);
                if (callback) callback(null, msg);
            });
        },

        showError: function(container, message) {
            container.innerHTML = `
                <div class="bookit-map-fallback">
                    <div class="fallback-content">
                        <i class="fas fa-map-marked-alt"></i>
                        <h3>Map Engine Restricted</h3>
                        <p>${message}</p>
                        <button onclick="location.reload()" class="btn btn-primary btn-sm">Reload Engine</button>
                    </div>
                </div>
            `;
        },

        clearMarkers: function() {
            if (clusterGroup) map.removeLayer(clusterGroup);
            markers = [];
        },

        // Legacy Stubs for Backward Compatibility
        setHeatmapData: function() { console.warn("Heatmap disabled in OSM mode."); },
        toggleHeatmap: function() { },
        showNearbyPOIs: function() { },
        clearPOIs: function() { }
    };
})();
