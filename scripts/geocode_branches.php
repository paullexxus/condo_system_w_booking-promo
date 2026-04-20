<?php
/**
 * [ELITE HARDENED] BookIT Geocoding Engine (Nominatim / OSM Edition)
 * Purpose: Geocode branches/units using OpenStreetMap Nominatim.
 * Features: Multi-Layer Caching, Rate Limit Compliance (1s), WGS84 Precision.
 */

// Load core dependencies
require_once __DIR__ . '/../config/db.php';

// Nominatim requires a descriptive User-Agent
$USER_AGENT = "BookIT-Condo-System/1.0 (contact: admin@yourdomain.com)";

/**
 * Get coordinates for an address with caching
 */
function get_coordinates_hardened($address, $conn) {
    global $USER_AGENT;
    $address = trim($address);
    if (empty($address)) return null;

    $address_hash = hash('sha256', strtolower($address));

    // 1. Check Local Cache
    $stmt = $conn->prepare("SELECT latitude, longitude FROM geocoding_cache WHERE address_hash = ?");
    $stmt->bind_param("s", $address_hash);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        return ['lat' => (float)$row['latitude'], 'lng' => (float)$row['longitude'], 'source' => 'cache'];
    }

    // 2. Nominatim API Call (Compliance: 1 request per second)
    echo "   [API] Calling Nominatim for: $address\n";
    usleep(1000000); // 1 second delay

    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($address) . "&limit=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $USER_AGENT);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || empty($response)) {
        return null;
    }

    $data = json_decode($response, true);
    if (empty($data) || !isset($data[0]['lat'])) {
        return null;
    }

    $lat = (float)$data[0]['lat'];
    $lng = (float)$data[0]['lon'];

    // 3. Save to Local Cache
    $save = $conn->prepare("INSERT INTO geocoding_cache (address, address_hash, latitude, longitude, provider) VALUES (?, ?, ?, ?, 'nominatim')");
    $save->bind_param("ssdd", $address, $address_hash, $lat, $lng);
    $save->execute();

    return ['lat' => $lat, 'lng' => $lng, 'source' => 'api'];
}

// MAIN EXECUTION LOOP
echo "--- BookIT Geocoding Engine Started ---\n";

if (php_sapi_name() !== 'cli') {
    echo "ERROR: This script must be run from CLI.\n";
    exit(1);
}

// Process Branches
$branches = $conn->query("SELECT branch_id, branch_name, address FROM branches WHERE latitude IS NULL OR longitude IS NULL");
echo "Found " . $branches->num_rows . " branches needing geocoding.\n";

while ($row = $branches->fetch_assoc()) {
    echo "Processing Branch #{$row['branch_id']} ('{$row['branch_name']}')...\n";
    $coords = get_coordinates_hardened($row['address'], $conn);

    if ($coords) {
        $update = $conn->prepare("UPDATE branches SET latitude = ?, longitude = ? WHERE branch_id = ?");
        $update->bind_param("ddi", $coords['lat'], $coords['lng'], $row['branch_id']);
        if ($update->execute()) {
            echo "   SUCCESS: [{$coords['source']}] lat={$coords['lat']}, lng={$coords['lng']}\n";
        }
    } else {
        echo "   FAILED: No results found for '{$row['address']}'\n";
    }
}

echo "--- Geocoding Complete ---\n";
?>
