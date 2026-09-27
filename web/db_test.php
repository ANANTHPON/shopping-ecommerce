<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Database credentials (Default for local XAMPP)
$host = '127.0.0.1';
$db   = 'drupal10_db'; // Replace with your database name
$user = 'root';
$pass = '';     // Default XAMPP password is empty

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    // 2. Connect to the database
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connected successfully!<br>";

    // 3. Create a table (if it doesn't exist)
    $pdo->exec("CREATE TABLE IF NOT EXISTS sample_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        email VARCHAR(50) NOT NULL
    )");

    // 4. Insert data
    $stmt = $pdo->prepare("INSERT INTO sample_users (name, email) VALUES (?, ?)");
    $stmt->execute(['Charlie', 'charlie@example.com']);
    
    // 5. Query and display the data
    $stmt = $pdo->query("SELECT * FROM sample_users");
    echo "<h3>--- Data from MySQL ---</h3>";
    while ($row = $stmt->fetch()) {
        echo "ID: " . $row['id'] . ", Name: " . htmlspecialchars($row['name']) . ", Email: " . htmlspecialchars($row['email']) . "<br>";
    }

} catch (\PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
