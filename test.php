<?php
echo "PHP is working!<br>";
echo "Current directory: " . __DIR__ . "<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Test database connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'license';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connection successful!<br>";
    
    // Test if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "Users table exists!<br>";
    } else {
        echo "Users table does not exist!<br>";
    }
    
} catch(PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "<br>";
}
?>
