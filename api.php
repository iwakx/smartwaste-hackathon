<?php
// Konfigurasi database
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "smartwaste";

// Koneksi database
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "DB connection failed"]));
}

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

// Ambil nilai dari JSON
$weight = (float) $data['weight'];
$distance = (float) $data['distance'];
$latitude = (float) $data['latitude'];
$longitude = (float) $data['longitude'];
$is_full = (bool) $data['is_full'];

// Simpan ke database
$stmt = $conn->prepare("INSERT INTO smartwaste (weight, distance, latitude, longitude, is_full) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ddddi", $weight, $distance, $latitude, $longitude, $is_full ? 1 : 0);
$stmt->execute();

// Kirim notifikasi jika penuh
if ($is_full) {
    $bot_token = "7699495817:AAHK6IdyQNnOhQH03XPnoSiA-_3bw-JIeg4";
    $chat_id = "93372553";
    $message = "🚨 Tempat sampah penuh!\nBerat: {$weight} kg\nJarak: {$distance} cm\nLokasi: https://maps.google.com/?q={$latitude},{$longitude}";
    file_get_contents("https://api.telegram.org/bot{$bot_token}/sendMessage?chat_id={$chat_id}&text=" . urlencode($message));
}

// Response ke ESP32
echo json_encode(["status" => "ok"]);
