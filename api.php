<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Database configuration
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "smartwaste";

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}



// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and decode JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Log the raw input for debugging
    file_put_contents('debug.log', date('Y-m-d H:i:s')." - ".$json.PHP_EOL, FILE_APPEND);

    // Validate required fields
    $required = ['weight', 'distance', 'latitude', 'longitude'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Missing field: $field"]);
            exit;
        }
    }

    // Prepare data
    $weight = (float)$data['weight'];
    $distance = (float)$data['distance'];
    $latitude = (float)$data['latitude'];
    $longitude = (float)$data['longitude'];


    // Prepare and execute SQL statement
    $stmt = $conn->prepare("INSERT INTO waste_data (weight, distance, latitude, longitude) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Prepare failed: ".$conn->error]);
        exit;
    }

    $stmt->bind_param("dddd", $weight, $distance, $latitude, $longitude);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Execute failed: ".$stmt->error]);
        exit;
    }


    // Return success response
    echo json_encode([
        "status" => "ok",
        "message" => "Data saved successfully",
        "data" => [
            "weight" => $weight,
            "distance" => $distance,
            "latitude" => $latitude,
            "longitude" => $longitude,
        ]
    ]);
    exit;
}

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'read') {
    $result = $conn->query("SELECT * FROM waste_data ORDER BY created_at DESC LIMIT 50");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
    exit;
}

// Jika Method yang digunakan bukan GET atau POST 
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET')
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
exit;
?>