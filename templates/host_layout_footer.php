<?php
/**
 * host_layout_footer.php
 * Hardened Layout Footer for Host Dashboard
 * Enforces JS Loading Order and Design Consistency
 */
?>
        <!-- End of Page Content -->
    </main>
</div>

<!-- 1. Core Frameworks -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- 2. Mapping Infrastructure (Leaflet) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
<script src="<?php echo SITE_URL; ?>/assets/js/map.js?v=<?php echo time(); ?>"></script>

<!-- 3. Dashboard Interactivity -->
<script>
    // Universal Dashboard Functionality
    $(document).ready(function() {
        // Handle Sidebar Mobile Toggle
        $('.sidebar-toggle').on('click', function() {
            $('#sidebar').toggleClass('active');
        });

        // Initialize all Tooltips & Popovers
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
</script>

<?php if (isset($extra_js)): ?>
    <!-- 4. Page Specific Scripts -->
    <?php echo $extra_js; ?>
<?php endif; ?>

</body>
</html>
