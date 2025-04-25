<?php
// Konfigurasi database
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "smartwaste";

// Koneksi Database
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "DB connection failed"]));
}


//
// ==== Handle GET request (untuk dashboard fetch data) ====
//

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


//
// ==== Handle POST request dari ESP32 ====
//

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ambil JSON dari ESP32
    $data = json_decode(file_get_contents("php://input"), true);

    // Validasi semua field
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
    if ($weight < 0 || $weight > 10000) { // Berat maks : 10 kg
        echo json_encode(["status" => "error", "message" => "Berat telah melebihi batas maksimal (maks. : 10 kg)"]);
        exit;
    }
    if ($distance < 0 || $distance > 10) { // Jarak maks : 10 cm
        echo json_encode(["status" => "error", "message" => "Jarak telah melewati batas maksimal (maks. : 10 cm)"]);
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

    // Kalkulasi status is_full berdasarkan jarak dan berat
    if ($weight > 10000 && $distance > 10) {
        $is_full = true;
    } else {
        $is_full = false;
    }

    // Kirim notifikasi jika status is_full = true
    if ($is_full = true) {
        $bot_token = "7699495817:AAHK6IdyQNnOhQH03XPnoSiA-_3bw-JIeg4";  // Token Bot
        $chat_id = "93372553";  // Chat ID Telegram
        $message = "🚨 Tempat sampah penuh!\nBerat: {$weight} kg\nJarak: {$distance} cm\nLokasi: https://maps.google.com/?q={$latitude},{$longitude}";
        file_get_contents("https://api.telegram.org/bot{$bot_token}/sendMessage?chat_id={$chat_id}&text=" . urlencode($message));
    }

    // Balas ke ESP32
    echo json_encode(["status" => "ok", "message" => "Data tersimpan"]);

exit;
}


// 
// ====== Jika Method yang digunakan bukan GET dan POST ======
//

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Metode tidak diizinkan"]);
    exit;
}

?>