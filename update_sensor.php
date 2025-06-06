<?php
$servername = "localhost";
$username = "test";
$password = "13769592Qa!";
$dbname = "K_route";

// connect to db(데이터베이스 연결)
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// get sensor value(POST로 받은 센서 값을 처리)
$sensor1 = isset($_POST['sensor1']) ? intval($_POST['sensor1']) : 0;
$sensor2 = isset($_POST['sensor2']) ? intval($_POST['sensor2']) : 0;
$sensor3 = isset($_POST['sensor3']) ? intval($_POST['sensor3']) : 0;
$sensor4 = isset($_POST['sensor4']) ? intval($_POST['sensor4']) : 0;

// update db(데이터베이스 업데이트)
$sql = "UPDATE trainroute SET sensor1 = ?, sensor2 = ?, sensor3 = ?, sensor4 = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $sensor1, $sensor2, $sensor3, $sensor4);
$stmt->execute();

$stmt->close();
$conn->close();

echo "Sensor values updated";
?>
