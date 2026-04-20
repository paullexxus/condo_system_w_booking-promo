<?php
/**
 * [DEFENSE-GRADE] OpenStreetMap / Leaflet Configuration
 * Replaced Google Maps with a fully API-keyless architecture.
 */

// 🌍 1. PROVIDER SETTINGS
if (!defined('MAP_PROVIDER')) define('MAP_PROVIDER', 'leaflet');

// 🗺️ 2. TILE SERVERS (With Failover Strategy)
if (!defined('TILE_SERVER_PRIMARY')) {
    // Standard OSM tiles
    define('TILE_SERVER_PRIMARY', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
}
if (!defined('TILE_SERVER_FALLBACK')) {
    // CartoDB Voyager - Fast, clean and reliable fallback
    define('TILE_SERVER_FALLBACK', 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png');
}

if (!defined('TILE_ATTRIBUTION')) {
    define('TILE_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors');
}

// 🛡️ 3. MAP STATUS
if (!defined('MAP_ENABLED')) define('MAP_ENABLED', true);

// 📍 4. GLOBAL DEFAULTS
if (!defined('DEFAULT_LAT')) define('DEFAULT_LAT', (float)(getenv('DEFAULT_LAT') ?: 14.5995));
if (!defined('DEFAULT_LNG')) define('DEFAULT_LNG', (float)(getenv('DEFAULT_LNG') ?: 120.9842));
if (!defined('DEFAULT_ZOOM')) define('DEFAULT_ZOOM', 12);

// ⚡ 5. PERFORMANCE & LIMITS
if (!defined('MAX_MAP_MARKERS')) define('MAX_MAP_MARKERS', 500); // Leaflet handles more markers with clustering

/**
 * Coordinate Sanitization
 * Enforces strict range-based validation and prevents "Atlantic Ocean" (0,0) bug.
 * Unified data layer: This remains unchanged from the Google implementation.
 */
function normalize_coordinates($lat, $lng) {
    if ($lat === null || $lng === null) return ['lat' => null, 'lng' => null];
    
    $lat = (float)$lat;
    $lng = (float)$lng;

    // Reject Absolute Zero (common bug/failure marker)
    if (abs($lat) < 0.000001 && abs($lng) < 0.000001) {
        return ['lat' => null, 'lng' => null];
    }

    // Strict Geographic Ranges
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return ['lat' => null, 'lng' => null];
    }

    return ['lat' => $lat, 'lng' => $lng];
}
?>
