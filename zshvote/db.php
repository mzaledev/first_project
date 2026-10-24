
<?php
$host = "localhost";
$dbname = "college_voting";
$username = "root";
$password = "";

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $conn->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());

    die("Unable to connect to the database. Please check your database settings.");
}
?>
