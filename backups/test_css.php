<?php
// Test CSS Loading
echo "<h2>CSS Loading Test</h2>";

// Check if CSS file exists
$cssFile = '../assets/css/login.css';
if (file_exists($cssFile)) {
    echo "<p style='color: green;'>✓ CSS file exists: " . $cssFile . "</p>";
    echo "<p>File size: " . filesize($cssFile) . " bytes</p>";
} else {
    echo "<p style='color: red;'>✗ CSS file not found: " . $cssFile . "</p>";
}

// Check current directory
echo "<p>Current directory: " . getcwd() . "</p>";
echo "<p>CSS file path: " . realpath($cssFile) . "</p>";

// Test CSS loading
echo "<hr>";
echo "<h3>CSS Preview:</h3>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 5px;'>";
echo "<p>If the CSS is loading properly, you should see styled elements below:</p>";

echo "<div class='login-container' style='max-width: 300px; margin: 20px auto;'>";
echo "<div class='login-header'>";
echo "<h2><i class='fas fa-building'></i> BookIT</h2>";
echo "<p>Multi-Branch Condo Rental System</p>";
echo "</div>";
echo "<div class='form-control' style='padding: 10px; margin: 10px 0;'>Sample Input Field</div>";
echo "<button class='btn-login' style='padding: 10px; margin: 10px 0;'>Sample Button</button>";
echo "</div>";

echo "</div>";

echo "<hr>";
echo "<p><a href='public/login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
?>


