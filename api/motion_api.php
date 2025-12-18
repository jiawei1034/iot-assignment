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
            isset($data['motion_id']) &&
            isset($data['device_id']) &&
            isset($data['is_detected']) &&
            isset($data['detected_at'])
        ) {
            $motion_id   = (int)$data['motion_id'];
            $device_id   = $data['device_id'];
            $is_detected = (int)$data['is_detected'];
            $detected_at = $data['detected_at'];

            $stmt = $conn->prepare(
                "INSERT INTO motion_data (motion_id, device_id, is_detected, detected_at)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "isis",
                $motion_id,
                $device_id,
                $is_detected,
                $detected_at
            );

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode([
                    "status" => "success",
                    "message" => "Motion data inserted successfully"
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    "status" => "error",
                    "message" => "Failed to insert data"
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
            "SELECT * FROM motion_data ORDER BY detected_at DESC"
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
