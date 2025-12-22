<?php
header("Content-Type: application/json");

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

// Connect to MySQL
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}

// Determine HTTP method
$method = $_SERVER['REQUEST_METHOD'];

switch($method){
    case 'POST':
        // Create a new sensor record
        $data = json_decode(file_get_contents("php://input"), true);

        if(isset($data['temperature']) && isset($data['humidity'])){
            $temperature = $data['temperature'];
            $humidity = $data['humidity'];

            $stmt = $conn->prepare("INSERT INTO sensor_data (temperature, humidity) VALUES (?, ?)");
            $stmt->bind_param("dd", $temperature, $humidity);

            if($stmt->execute()){
                http_response_code(201); // Created
                echo json_encode(["status" => "success", "message" => "Data inserted successfully"]);
            } else {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Failed to insert data"]);
            }
            $stmt->close();
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(["status" => "error", "message" => "Missing temperature or humidity"]);
        }
        break;

    case 'GET':
        // Retrieve all sensor records
        $result = $conn->query("SELECT * FROM sensor_data ORDER BY created_at DESC");
        $data = [];
        while($row = $result->fetch_assoc()){
            $data[] = $row;
        }
        http_response_code(200);
        echo json_encode(["status" => "success", "data" => $data]);
        break;

    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        break;
}

$conn->close();
?>
