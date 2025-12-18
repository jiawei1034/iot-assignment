<?php
// ================== TIMEZONE ==================
date_default_timezone_set('Asia/Kuala_Lumpur'); // CHANGE IF NEEDED

// ================== ERROR REPORTING (DEBUG) ==================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ================== HEADERS ==================
header("Content-Type: application/json");

// ================== DATABASE CONFIG ==================
$servername  = "localhost";
$db_username = "admin";
$db_password = "P@ssword";
$db_name     = "intruderSystem";
$port        = 3306;

// ================== DATABASE CONNECTION ==================
$conn = new mysqli($servername, $db_username, $db_password, $db_name, $port);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "DB connection failed: " . $conn->connect_error
    ]);
    exit;
}

// ✅ Force MySQL timezone to match PHP
$conn->query("SET time_zone = '+08:00'");

// ================== HTTP METHOD ==================
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ================== POST ==================
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);

        if (
            isset($data['device_id']) &&
            isset($data['is_detected'])
        ) {
            $device_id   = (int)$data['device_id'];
            $is_detected = (int)$data['is_detected'];

            // ✅ PHP-generated local datetime
            $date_time = date('Y-m-d H:i:s');

            $stmt = $conn->prepare(
                "INSERT INTO motion_sensor (device_id, is_detected, date_time)
                 VALUES (?, ?, ?)"
            );

            if (!$stmt) {
                http_response_code(500);
                echo json_encode([
                    "status" => "error",
                    "message" => $conn->error
                ]);
                exit;
            }

            $stmt->bind_param("iis", $device_id, $is_detected, $date_time);

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode([
                    "status" => "success",
                    "motion_id" => $stmt->insert_id,
                    "date_time" => $date_time
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    "status" => "error",
                    "message" => $stmt->error
                ]);
            }

            $stmt->close();

        } else {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Missing required fields"
            ]);
        }
        break;

    // ================== GET ==================
    case 'GET':
        $result = $conn->query(
            "SELECT * FROM motion_sensor ORDER BY date_time DESC"
        );

        if (!$result) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => $conn->error
            ]);
            exit;
        }

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $data
        ]);
        break;

    // ================== DEFAULT ==================
    default:
        http_response_code(405);
        echo json_encode([
            "status" => "error",
            "message" => "Method not allowed"
        ]);
        break;
}

// ================== CLOSE CONNECTION ==================
$conn->close();
?>
