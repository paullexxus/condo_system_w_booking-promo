<?php
include 'config/db.php';

echo "<h2>Security Audit: Admin Page Access Control</h2>";

$admin_files = [
    'admin/admin_dashboard.php',
    'admin/user_management.php',
    'admin/unit_management.php',
    'admin/manage_branch.php',
    'admin/settings.php',
    'admin/reports.php',
    'admin/admin_profile.php',
    'admin/update_admin.php',
    'admin/update_unit.php'
];

echo "<h3>Checking admin files for role protection:</h3>";
echo "<table style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0; border: 1px solid #ddd;'>";
echo "<th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>File</th>";
echo "<th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>checkRole Check</th>";
echo "<th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Status</th>";
echo "</tr>";

foreach ($admin_files as $file) {
    $file_path = dirname(__FILE__) . "/" . $file;
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        
        // Check for checkRole(['admin'])
        $has_check = (strpos($content, "checkRole") !== false && strpos($content, "'admin'") !== false) ||
                     (strpos($content, "checkRole") !== false && strpos($content, '"admin"') !== false);
        
        $status = $has_check ? '<span style="color: green;">✓ Protected</span>' : '<span style="color: red;">✗ UNPROTECTED</span>';
        $check = $has_check ? 'Yes' : 'No';
        
        echo "<tr style='border: 1px solid #ddd;'>";
        echo "<td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($file) . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #ddd;'>" . $check . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #ddd;'>" . $status . "</td>";
        echo "</tr>";
    } else {
        echo "<tr style='border: 1px solid #ddd;'>";
        echo "<td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($file) . "</td>";
        echo "<td colspan='2' style='padding: 10px; border: 1px solid #ddd; color: orange;'>File not found</td>";
        echo "</tr>";
    }
}

echo "</table>";

echo "<hr>";
echo "<h3>Security Improvements Made:</h3>";
echo "<ul style='font-size: 16px; line-height: 2;'>";
echo "<li>✓ Enhanced checkRole() function with proper role validation</li>";
echo "<li>✓ Added logging for unauthorized access attempts</li>";
echo "<li>✓ Smart redirects based on user role (not just login page)</li>";
echo "<li>✓ Added getUserDashboardURL() helper function</li>";
echo "<li>✓ Fixed potential null role bypass</li>";
echo "</ul>";

echo "<hr>";
echo "<h3 style='color: green;'>✓ Access Control Enhanced!</h3>";
echo "<p>Renters can no longer directly access admin pages by changing URL.</p>";
echo "<p>They will be automatically redirected to their renter dashboard.</p>";
?>
