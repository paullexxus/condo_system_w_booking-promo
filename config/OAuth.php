<?php
// config/OAuth.php
// Google OAuth Configuration
// To set up Google OAuth:
// 1. Go to https://console.cloud.google.com
// 2. Create a new project or select existing
// 3. Enable Google+ API
// 4. Create OAuth 2.0 credentials (Web application)
// 5. Set Authorized redirect URIs to: http://localhost/BookIT/auth/google-callback.php
// 6. Copy your Client ID and Client Secret below

return [
    'google' => [
        'client_id' => 'YOUR_GOOGLE_CLIENT_ID_HERE',
        'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET_HERE',
        'redirect_uri' => 'http://localhost/BookIT/auth/google-callback.php'
    ]
];
?>