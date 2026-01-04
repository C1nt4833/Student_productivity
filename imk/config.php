<?php
$host = 'localhost';
$user = 'root';
$pass = ''; // Default XAMPP password
$db   = 'imk_db';


// Set Timezone agar sinkron dengan waktu User (WITA/Asia/Makassar +8)
date_default_timezone_set('Asia/Makassar'); 

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
