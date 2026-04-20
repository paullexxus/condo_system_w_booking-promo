<?php
/**
 * Hardened Moderation Persistence Migration (PDO Version)
 * Fixes: "Unknown column review_locked_by"
 */
$host = '127.0.0.1';
$db   = 'condo_rental_reservation_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     echo "Connected to database via PDO successfully.\n";

     // 1. Add columns to units table
     $cols = [
         'review_locked_by' => 'INT NULL',
         'review_locked_at' => 'DATETIME NULL',
         'reviewed_at' => 'DATETIME NULL'
     ];

     foreach ($cols as $col => $def) {
         try {
             $pdo->exec("ALTER TABLE units ADD $col $def");
             echo "Added $col to units table.\n";
         } catch (PDOException $e) {
             if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                 echo "$col already exists.\n";
             } else {
                 throw $e;
             }
         }
     }

     // 2. Create unit_flags table
     $pdo->exec("CREATE TABLE IF NOT EXISTS unit_flags (
         id INT AUTO_INCREMENT PRIMARY KEY,
         unit_id INT NOT NULL,
         flag_type VARCHAR(50) NOT NULL,
         details TEXT,
         flagged_by INT NOT NULL,
         created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
         INDEX (unit_id)
     ) ENGINE=InnoDB;");
     echo "unit_flags table ensured.\n";

     echo "\nMigration Successful (PDO)!\n";

} catch (PDOException $e) {
     echo "Migration Failed: " . $e->getMessage() . "\n";
     exit(1);
}
?>
