<?php
include 'config.php';
header("Content-Type: application/json");

// ===== HTTP METHOD =====
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ================== POST ==================
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);

        if (
            isset($data['device_id']) &&
            isset($data['is_detected']) &&
            isset($data['date_time'])
        ) {
            $device_id   = (int)$data['device_id'];
            $is_detected = (int)$data['is_detected'];
            $date_time   = $data['date_time'];

            // motion_id is AUTO_INCREMENT → do NOT insert it
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

            $stmt->bind_param(
                "iis",
                $device_id,
                $is_detected,
                $date_time
            );

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode([
                    "status" => "success",
                    "motion_id" => $stmt->insert_id
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

$conn->close();
?>
