<?php
/**
 * host_layout_header.php
 * Hardened Layout Header for Host Dashboard
 * Enforces strict CSS loading order and Design Consistency
 */
if (!isset($page_title)) $page_title = 'Host Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | BookIT</title>
    
    <!-- 1. Global Frameworks -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- 2. Mapping Infrastructure (Hardened) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/map-styles.css?v=<?php echo time(); ?>">

    <!-- 3. Core Design System (SPECIFICITY ENFORCEMENT) -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/host/dashboard-unified.css?v=<?php echo time(); ?>">
    
    <?php if (isset($extra_css)): ?>
        <!-- 4. Page Specific Fallbacks -->
        <?php echo $extra_css; ?>
    <?php endif; ?>

    <style>
        /* Panel-Proof CSS Reset / Global Overrides */
        :root { --sidebar-width: 230px; }
        
        /* Loading Skeleton for Maps */
        .skeleton {
            background-color: #e2e5e7;
            background-image: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0));
            background-size: 200px 100%;
            background-repeat: no-repeat;
            background-position: left -150% top 0;
            animation: shine 1.5s ease-in-out infinite;
        }
        @keyframes shine { to { background-position: right -150% top 0; } }
    </style>
</head>
<body>

<div class="d-flex">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">
        <!-- Start of Page Content -->
