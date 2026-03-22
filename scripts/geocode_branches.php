<?php
/**
 * Geocode branches and update `branches.latitude` and `branches.longitude`.
 * Usage (CLI): php geocode_branches.php
 * Requires GOOGLE_MAPS_API_KEY defined in config/constants.php for automatic geocoding.
 * If no API key, the script will output SQL UPDATE statements you can run manually.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';

// Only allow from CLI or local dev environment for safety
if (php_sapi_name() !== 'cli') {
    echo "This script is intended to be run from CLI only.\n";
}

$useApi = defined('GOOGLE_MAPS_API_KEY') && !empty(GOOGLE_MAPS_API_KEY);

$stmt = $conn->prepare("SELECT branch_id, branch_name, address, city, latitude, longitude FROM branches WHERE latitude IS NULL OR longitude IS NULL");
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "No branches need geocoding.\n";
    exit;
}

echo "Found " . $res->num_rows . " branches to geocode.\n";

while ($row = $res->fetch_assoc()) {
    $branchId = $row['branch_id'];
    $address = trim(($row['address'] ?? '') . ', ' . ($row['city'] ?? ''));

    if (empty($address)) {
        echo "Branch {$branchId} ('{$row['branch_name']}') has no address; skipping.\n";
        continue;
    }

    if ($useApi) {
        $apiKey = GOOGLE_MAPS_API_KEY;
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . $apiKey;

        // make request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $body = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http !== 200 || !$body) {
            echo "Failed to geocode branch {$branchId} ('{$row['branch_name']}'): HTTP {$http}\n";
            continue;
        }

        $data = json_decode($body, true);
        if (!isset($data['results'][0]['geometry']['location'])) {
            echo "No geocode result for branch {$branchId} ('{$row['branch_name']}').\n";
            continue;
        }

        $loc = $data['results'][0]['geometry']['location'];
        $lat = (float)$loc['lat'];
        $lng = (float)$loc['lng'];

        // update DB
        $u = $conn->prepare("UPDATE branches SET latitude = ?, longitude = ? WHERE branch_id = ?");
        $u->bind_param('ddi', $lat, $lng, $branchId);
        if ($u->execute()) {
            echo "Updated branch {$branchId} ('{$row['branch_name']}'): lat={$lat}, lng={$lng}\n";
        } else {
            echo "DB update failed for branch {$branchId}: " . $conn->error . "\n";
        }

    } else {
        // Output SQL for manual update
        echo "-- Branch {$branchId} ('{$row['branch_name']}') address: {$address}\n";
        echo "-- Run geocode manually and then execute:\n";
        echo "-- UPDATE branches SET latitude = <LAT>, longitude = <LNG> WHERE branch_id = {$branchId};\n\n";
    }
}

echo "Done.\n";
