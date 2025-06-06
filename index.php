<?php
session_start();

$servername = "localhost";
$username = "test";
$password = "13769592Qa!";
$dbname = "K_route";

// create connection(mysql과 연결)
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// generate randome int(랜덤 값 생성 함수)
function generateRandomSeated() {
    return rand(0, 1);
}

$generatedStat = null;
$stationName = null;

// randomizing button(랜덤버튼_PRG 패턴 적용))
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate'])) {
    // randomize stat value(stat 랜덤 값 1~6까지 생성)
    $generatedStat = rand(1, 6);
    $_SESSION['generatedStat'] = $generatedStat;

    // randomize seated value(각 station별로 seated1~4 랜덤 값 생성)
    $seated = [];
    $sql = "SELECT station, stat FROM trainroute";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $station = $row['station'];
            $stat = $row['stat'];
            if ($stat >= $generatedStat) { // limit range to over current stat value(현재 stat 이상인 station만 랜덤 seated 값 생성)
                $seated[$station] = [
                    'seated1' => generateRandomSeated(),
                    'seated2' => generateRandomSeated(),
                    'seated3' => generateRandomSeated(),
                    'seated4' => generateRandomSeated(),
                ];

                // apply to mysql(MySQL 데이터베이스 업데이트)
                $updateSql = "UPDATE trainroute SET seated1 = ?, seated2 = ?, seated3 = ?, seated4 = ? WHERE station = ?";
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param(
                    "iiiis",
                    $seated[$station]['seated1'],
                    $seated[$station]['seated2'],
                    $seated[$station]['seated3'],
                    $seated[$station]['seated4'],
                    $station
                );
                $stmt->execute();
            }
        }
    }
    $_SESSION['seated'] = $seated;

    // prg pattern(PRG 패턴 적용)
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
} else {
    // prg session part(세션에서 저장된 값을 복원)
    $generatedStat = isset($_SESSION['generatedStat']) ? $_SESSION['generatedStat'] : null;
    $seated = isset($_SESSION['seated']) ? $_SESSION['seated'] : null;
}

// get informations of current station from mysql(현재 station 정보 가져오기)
if ($generatedStat !== null) {
    $sql = "SELECT station FROM trainroute WHERE stat = $generatedStat";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stationName = $row['station'];
    } else {
        $stationName = "No station found";
    }
}

// get all informations of station from mysql(모든 station 정보 가져오기)
$stations = [];
$sql = "SELECT station, stat, sensor1, sensor2, sensor3, sensor4, seated1, seated2, seated3, seated4 FROM trainroute";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $stations[] = $row;
    }
}

// when reserve button is clicked(좌석 예약 버튼을 눌렀을 때 처리)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reserve'])) {
    $destinationStat = intval($_POST['destinationStat']);

    if ($generatedStat !== null && $destinationStat > $generatedStat) {
        // get data from db(도착 역까지의 좌석 정보 가져오기)
        $sql = "SELECT station, stat, seated1, seated2, seated3, seated4 FROM trainroute WHERE stat >= ? AND stat <= ? ORDER BY stat";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $generatedStat, $destinationStat);
        $stmt->execute();
        $result = $stmt->get_result();

        $availableSeats = ['seat1' => [], 'seat2' => [], 'seat3' => [], 'seat4' => []];

        while ($row = $result->fetch_assoc()) {
            $station = $row['station'];
            if ($row['seated1'] == 0) $availableSeats['seated1'][] = $station;
            if ($row['seated2'] == 0) $availableSeats['seated2'][] = $station;
            if ($row['seated3'] == 0) $availableSeats['seated3'][] = $station;
            if ($row['seated4'] == 0) $availableSeats['seated4'][] = $station;
        }

        // select available seat(예약 가능한 좌석 선택)
        $seatToReserve = null;
        foreach ($availableSeats as $seat => $stations) {
            if (count($stations) == ($destinationStat - $generatedStat + 1)) {
                $seatToReserve = $seat;
                break;
            }
        }

        // if the seat is not available linearly(좌석이 연속적으로 예약할 수 없을 때, 가능한 좌석 선택)
        if (!$seatToReserve) {
            foreach ($availableSeats as $seat => $stations) {
                if (count($stations) > 0) {
                    $seatToReserve = $seat;
                    break;
                }
            }
        }

        if ($seatToReserve) {
            foreach ($availableSeats[$seatToReserve] as $station) {
                // query debug(쿼리 오류 발생할 경우 에러 메시지 출력)
                $updateSql = "UPDATE trainroute SET $seatToReserve = 1 WHERE station = ?";
                $updateStmt = $conn->prepare($updateSql);
        
                if (!$updateStmt) {
                    die("MySQL prepare error: " . $conn->error);
                }
        
                $updateStmt->bind_param("s", $station);
                $updateStmt->execute();
            }
        
            // redirect(좌석 예약 후 리다이렉트)
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "<script>alert('No available seats could be reserved.');</script>";
        }
    } else {
        echo "<script>alert('Invalid station selection or destination before current station.');</script>";
    }
}

// seat swap(좌석 교환 버튼을 눌렀을 때)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['swap'])) {
    $station1 = $_POST['station1'];
    $seat1 = $_POST['seat1'];
    $station2 = $_POST['station2'];
    $seat2 = $_POST['seat2'];

    // swap seated value(선택한 자리의 seated 값을 교환)
    $conn->query("UPDATE trainroute SET $seat1 = 0 WHERE station = '$station1'");
    $conn->query("UPDATE trainroute SET $seat2 = 1 WHERE station = '$station2'");


    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$conn->close();
?>

<!-- create an html file(html 파일 생성) -->
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Train Route</title>
    <!-- create style(html 내에서 사용할 style 함수 생성) -->
    <style>
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .seat-grid {
            display: grid;
            grid-template-columns: repeat(2, 100px);
            gap: 10px;
            margin: 20px 0;
        }
        .seat {
            width: 100px;
            height: 100px;
            background-color: red;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            cursor: pointer;
            position: relative;
        }
        .seat.green {
            background-color: green;
        }
        .seat:hover::after {
            content: attr(data-seated-info);
            position: absolute;
            top: -25px;
            font-size: 14px;
            color: black;
            background-color: white;
            padding: 3px;
            border-radius: 5px;
            border: 1px solid #ddd;
            white-space: nowrap;
        }
        .info-button {
            position: absolute;
            top: 20px;
            left: 20px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #3498db;
            color: white;
            font-size: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            font-weight: bold;
        }
        .info-popup {
            display: none;
            position: absolute;
            top: 70px;
            left: 20px;
            width: 250px;
            background-color: white;
            color: black;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ddd;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
        }
        .info-button:hover + .info-popup {
            display: block;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
        }
        p {
            font-size: 18px;
            font-weight: bold;
        }
        .highlight-green {
            background-color: lightgreen;
        }
        .highlight-yellow {
            background-color: yellow;
        }
        .highlight-red{
            background-color: red;
            color: white;
        }
        .highlight-blue{
            background-color: blue;
            color: white;
        }
        table {
            width: 80%;
            border-collapse: collapse;
            margin: 25px 0;
            font-size: 16px;
            text-align: left;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <div class="info-button">i</div>    <!-- information button(정보 버튼 호버시 내용) -->
    <div class="info-popup"> 
        <p>This page allows standing room users to check if the seats are available.</p>
        <p>In middle of the screan, there is a 2X2 seat visualizer.</p>
        <p>If the seat is <span style="color: green;">green</span>, it means that the seat is available in current location.</p>
        <p>If the seat is <span style="color: red;">red</span>, it means that the seat is already taken in current location.</p>
        <p>Bottom of the screan, there is a table that shows reservation and availability after this station.</p>
        <p>If it is <span style="color: green;">green</span>, it means that the seat is not taken, and neither reserved.</p>
        <p>If it is <span style="color: yellow;">yellow</span>, it means that the seat is not taken, but reserved.</p>
        <p>If it is <span style="color: blue;">blue</span>, it means that the seat is taken, but not reserved.</p>
        <p>If it is <span style="color: red;">red</span>, it means that the seat is taken and reserved</p>
    </div>

    <h1>Train Route</h1>
    <form method="POST">
        <button type="submit" name="generate">Randomize Route</button>
    </form>

    <?php if ($generatedStat !== null): ?>
        <p>Current Station: <strong><?php echo htmlspecialchars($stationName); ?></strong></p>
    <?php endif; ?>

    <!-- current seat availibity(현재 역의 좌석 상황) -->
    <?php if ($seated && isset($seated[$stationName])): ?>
        <div class="seat-grid">
            <?php foreach (['seated1', 'seated2', 'seated3', 'seated4'] as $seatIndex => $seatName): ?>
                <?php
                 $sensorValue = $stations[array_search($stationName, array_column($stations, 'station'))]['sensor' . ($seatIndex + 1)];
                 $seatedValue = $seated[$stationName][$seatName];
                 $availabilityInfo = ($sensorValue == 0 && $seatedValue == 0) ? 'available' : 'not available';
                
                ?>
                 <div class="seat <?php echo ($sensorValue == 0 && $seatedValue == 0) ? 'green' : ''; ?>"
                     data-seated-info="<?php echo htmlspecialchars($stationName) . ": " . $availabilityInfo; ?>">
                    Seat <?php echo $seatIndex + 1; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<!-- automatic seat reservation(목적지 선택 및 좌석 예약 버튼) -->
<form method="POST">
        <label for="destinationStat">Select Destination Station:</label>
        <select name="destinationStat" required>
            <?php foreach ($stations as $station): ?>
                <?php if ($station['stat'] > $generatedStat): ?>
                    <option value="<?php echo $station['stat']; ?>"><?php echo $station['station']; ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="reserve">Reserve All</button>
    </form>

 <!-- seat swap(현재 좌석 교환 기능) -->
 <form method="POST">
        <h2>Swap Seats</h2>

        <label for="station1">Select Station (Seated):</label>
        <select name="station1" required>
            <?php foreach ($stations as $station): ?>
                <?php if ($station['seated1'] == 1 || $station['seated2'] == 1 || $station['seated3'] == 1 || $station['seated4'] == 1): ?>
                    <option value="<?php echo $station['station']; ?>"><?php echo $station['station']; ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>

        <label for="seat1">Select Seat (Seated):</label>
        <select name="seat1" required>
            <option value="seated1">Seat 1</option>
            <option value="seated2">Seat 2</option>
            <option value="seated3">Seat 3</option>
            <option value="seated4">Seat 4</option>
        </select>

        <label for="station2">Select Station (Available Seat):</label>
        <select name="station2" required>
            <?php foreach ($stations as $station): ?>
                <?php if ($station['seated1'] == 0 || $station['seated2'] == 0 || $station['seated3'] == 0 || $station['seated4'] == 0): ?>
                    <option value="<?php echo $station['station']; ?>"><?php echo $station['station']; ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>

        <label for="seat2">Select Seat (Available):</label>
        <select name="seat2" required>
            <option value="seated1">Seat 1</option>
            <option value="seated2">Seat 2</option>
            <option value="seated3">Seat 3</option>
            <option value="seated4">Seat 4</option>
        </select>

        <button type="submit" name="swap">Swap Seats</button>
    </form>

    <!-- seat availability table(현재 stat 이후의 station에 대한 상태를 표로 표시) -->
    <h2>Reservation and availability</h2>
    <table>
        <thead>
            <tr>
                <th>Station</th>
                <th>Seat 1</th>
                <th>Seat 2</th>
                <th>Seat 3</th>
                <th>Seat 4</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stations as $station): ?>
                <?php if ($station['stat'] >= $generatedStat): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($station['station']); ?></td>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <?php
                            $seatedValue = $station['seated' . $i];
                            $sensorValue = $station['sensor' . $i]; 
                            $class = '';
                            if ($seatedValue == 0 && $sensorValue == 0) {
                                $class = 'highlight-green'; // highlight green when all values are o(둘 다 0이면 초록색)
                            } elseif ($seatedValue == 1 && $sensorValue == 0) {
                                $class = 'highlight-yellow'; // highlight yellow when seated is 1 and sensor is 0(seated는 1이고 sensor는 0이면 노란색)
                            } elseif ($seatedValue == 1 && $sensorValue == 1) {
                                $class = 'highlight-red'; // highlight red when all values are 1(둘 다 1이면 빨간색)
                            } elseif ($seatedValue == 0 && $sensorValue == 1) {
                                $class = 'highlight-blue'; // highlight blue when seatead is 0 and sensor is 0)seated는 0이고 sensor는 1이면 파란색
                            }
                            ?>
                            <td class="<?php echo $class; ?>">
                                Seated: <?php echo $seatedValue; ?>, Sensor: <?php echo $sensorValue; ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>