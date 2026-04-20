<?php
// admin/admin_map_view.php - MASTER PORTFOLIO MAP
include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['admin']);

// Fetch all units with location data from active branches
$sql = "SELECT u.unit_id, u.unit_name, u.latitude, u.longitude, u.approval_status, b.branch_name, u.price_per_night, u.price_per_month, u.pricing_type
        FROM units u 
        JOIN branches b ON u.branch_id = b.branch_id 
        WHERE b.is_active = 1 AND u.latitude IS NOT NULL AND u.longitude IS NOT NULL";
$units = get_multiple_results($sql);

// Map statuses to BoookIT.Map status colors
$unitsForMap = array_map(function($u) {
    $status = 'pending';
    if ($u['approval_status'] === 'approved') $status = 'approved';
    if ($u['approval_status'] === 'rejected') $status = 'rejected';
    
    return [
        'unit_id' => $u['unit_id'],
        'title' => $u['unit_name'],
        'lat' => $u['latitude'],
        'lng' => $u['longitude'],
        'status' => $status,
        'branch' => $u['branch_name'],
        'price' => ($u['pricing_type'] === 'monthly' ? $u['price_per_month'] : $u['price_per_night'])
    ];
}, $units);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Portfolio Map | Admin | BookIT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar-common.css">
    <link rel="stylesheet" href="../assets/css/map-styles.css">
    <!-- Leaflet Infrastructure -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: white; }
        .main-content { margin-left: 280px; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        #masterMap { flex: 1; width: 100%; }
        .admin-map-overlay {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 10;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 16px;
            width: 300px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-approved { background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
        .status-pending { background: rgba(234, 179, 8, 0.2); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.3); }
        .status-rejected { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
    </style>
</head>
<body class="bg-slate-950">
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-content relative">
            <!-- Header -->
            <div class="absolute top-0 left-0 right-0 z-10 px-8 py-6 bg-gradient-to-b from-slate-950/80 to-transparent pointer-events-none">
                <h1 class="text-2xl font-bold tracking-tight text-white pointer-events-auto">Master Portfolio Map</h1>
                <p class="text-slate-400 text-sm pointer-events-auto">Visualizing all property assets across active branches</p>
            </div>

            <!-- Dashboard Overlay -->
            <div class="admin-map-overlay">
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                    <i class="fas fa-layer-group text-blue-400"></i> Portfolio Status
                </h3>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-slate-900/50 rounded-xl border border-white/5">
                        <span class="status-pill status-approved"><i class="fas fa-check-circle"></i> Approved</span>
                        <span class="text-lg font-bold" id="approvedCount">0</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-slate-900/50 rounded-xl border border-white/5">
                        <span class="status-pill status-pending"><i class="fas fa-clock"></i> Pending</span>
                        <span class="text-lg font-bold" id="pendingCount">0</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-slate-900/50 rounded-xl border border-white/5">
                        <span class="status-pill status-rejected"><i class="fas fa-times-circle"></i> Rejected</span>
                        <span class="text-lg font-bold" id="rejectedCount">0</span>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-white/10">
                    <button onclick="zoomToExtents()" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg shadow-blue-900/20">
                        <i class="fas fa-compress-arrows-alt"></i> Show Entire City
                    </button>
                    <button onclick="window.location.reload()" class="w-full mt-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg transition-all flex items-center justify-center gap-2">
                        <i class="fas fa-sync-alt"></i> Refresh Data
                    </button>
                </div>
            </div>

            <!-- Map Container -->
            <div id="masterMap"></div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <script src="../assets/js/map.js?v=<?php echo time(); ?>"></script>

    <script>
        const unitsData = <?php echo json_encode($unitsForMap); ?>;
        
        function initMasterMap() {
            BookIT.Map.loadScript(null, () => {
                window.adminMap = BookIT.Map.init('masterMap', {
                    center: [14.5995, 120.9842],
                    zoom: 12
                });

                if (window.adminMap) {
                    // Add units with status-coded markers
                    BookIT.Map.addMarkers(unitsData, {
                        type: 'status',
                        cluster: true,
                        fitBounds: true,
                        onClick: (unit) => {
                            alert(`Unit: ${unit.title}\nBranch: ${unit.branch}\nStatus: ${unit.status.toUpperCase()}`);
                        }
                    });

                    // Update counts
                    document.getElementById('approvedCount').textContent = unitsData.filter(u => u.status === 'approved').length;
                    document.getElementById('pendingCount').textContent = unitsData.filter(u => u.status === 'pending').length;
                    document.getElementById('rejectedCount').textContent = unitsData.filter(u => u.status === 'rejected').length;
                }
            });
        }



        document.addEventListener('DOMContentLoaded', initMasterMap);

        function zoomToExtents() {
            if (window.adminMap) {
                const markerNodes = unitsData.map(u => [parseFloat(u.lat), parseFloat(u.lng)]);
                if (markerNodes.length > 0) {
                    window.adminMap.fitBounds(L.latLngBounds(markerNodes));
                }
            }
        }
    </script>
</body>
</html>
