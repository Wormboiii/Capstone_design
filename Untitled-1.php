<?php
$servername = "JHSG";
$username = "root";
$password = "159800";
$dbname = "K_route";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $station = $_POST['Station'];
    $seated = $_POST['Seated'] == 'true' ? 1 : 0;

    $sql = "UPDATE trainroute SET Seated = $seated WHERE Station = '$station'";

    if ($conn->query($sql) === TRUE) {
        echo "Record updated successfully";
    } else {
        echo "Error updating record: " . $conn->error;
    }
}

$sql = "SELECT * FROM trainroute";
$result = $conn->query($sql);
?>