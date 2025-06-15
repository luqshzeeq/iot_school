
<?php
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "language_monitor";


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add this line to set the connection's timezone to +08:00
$conn->query("SET time_zone = '+08:00'"); 
?>