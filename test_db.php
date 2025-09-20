<?php
// Simple database connection test
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
        
        // Count users
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Number of users: " . $result['count'] . "<br>";
    } else {
        echo "Users table does not exist!<br>";
    }
    
} catch(PDOException $e) {
    echo "Database connection failed: " . $e->getMessage();
}
?>
