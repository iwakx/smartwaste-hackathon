<?php
// api.php

// Konfigurasi database
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "smartwaste";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "DB connection failed"]));
}

// Handle GET request (untuk dashboard fetch data)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'read') {
    header('Content-Type: application/json');
    $result = $conn->query("SELECT * FROM smartwaste ORDER BY created_at DESC LIMIT 50");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
    exit;
}

// Handle POST request dari ESP32
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil JSON dari ESP32
    $data = json_decode(file_get_contents("php://input"), true);

    // Validasi: semua field harus ada
    $required = ['weight', 'distance', 'latitude', 'longitude', 'is_full'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            echo json_encode(["status" => "error", "message" => "Field '$field' is missing"]);
            exit;
        }
    }

    // Ambil nilai & ubah tipe data
    $weight = (float) $data['weight'];
    $distance = (float) $data['distance'];
    $latitude = (float) $data['latitude'];
    $longitude = (float) $data['longitude'];
    $is_full = filter_var($data['is_full'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    // Cek apakah parsing boolean gagal
    if (!is_bool($is_full)) {
        echo json_encode(["status" => "error", "message" => "is_full harus boolean"]);
        exit;
    }

    // Validasi nilai masuk akal
    if ($weight < 0 || $weight > 100) {
        echo json_encode(["status" => "error", "message" => "Berat tidak valid"]);
        exit;
    }
    if ($distance < 0 || $distance > 100) {
        echo json_encode(["status" => "error", "message" => "Jarak tidak valid"]);
        exit;
    }
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        echo json_encode(["status" => "error", "message" => "Koordinat GPS tidak valid"]);
        exit;
    }

    // Simpan ke database
    $stmt = $conn->prepare("INSERT INTO smartwaste (weight, distance, latitude, longitude, is_full) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ddddi", $weight, $distance, $latitude, $longitude, $is_full ? 1 : 0);
    $stmt->execute();

// Kirim notifikasi jika penuh
if ($is_full) {
    $bot_token = "YOUR_BOT_TOKEN"; // Ganti token
    $chat_id = "YOUR_CHAT_ID";     // Ganti chat ID
    $message = "🚨 Tempat sampah penuh!\nBerat: {$weight} kg\nJarak: {$distance} cm\nLokasi: https://maps.google.com/?q={$latitude},{$longitude}";
    file_get_contents("https://api.telegram.org/bot{$bot_token}/sendMessage?chat_id={$chat_id}&text=" . urlencode($message));
}

    // Balas ke ESP32
    echo json_encode(["status" => "ok", "message" => "Data tersimpan"]);
    exit;
}

// Jika bukan GET atau POST
http_response_code(405);
echo json_encode(["status" => "error", "message" => "Metode tidak diizinkan"]);
