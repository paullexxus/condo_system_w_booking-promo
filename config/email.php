<?php
// config/email.php
return [
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'officialbookitmanager@gmail.com', // Palitan ng actual email
        'password' => 'nddefzlrysdjowju',    // Google App Password
        'encryption' => 'tls'
    ],
    'from' => [
        'email' => 'officialbookitmanager@gmail.com',
        'name' => 'BookIT'
    ]
];
?>