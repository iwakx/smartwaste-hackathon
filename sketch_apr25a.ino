#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include "HX711.h"
#include <TinyGPSPlus.h>
#include <HardwareSerial.h>

// --- WiFi Settings ---
const char* ssid = "relz";          // Ganti dengan SSID WiFi
const char* password = "88888888";  // Ganti dengan password WiFi
const String serverUrl = "http://192.168.122.117/SmartWaste/smartwaste-hackathon/api.php";  // Ganti dengan IP server

 // 2. Set IP statis (sesuaikan dengan jaringan Anda)
IPAddress local_ip(192, 168, 122, 117);  // Ganti IP lokal ESP32 sesuai jaringan router
IPAddress gateway(192, 168, 122, 1);
IPAddress subnet(255, 255, 255, 0);

// --- Ultrasonic Sensor ---
const int trigPin = 23;
const int echoPin = 22;
#define SOUND_SPEED 0.034

// --- Load Cell (HX711) ---
const int LOADCELL_DOUT_PIN = 4;
const int LOADCELL_SCK_PIN = 2;
HX711 scale;
float calibration_factor = -19850.0;  // Kalibrasi sesuai sensor

// --- GPS (TinyGPS++) ---
TinyGPSPlus gps;
HardwareSerial gpsSerial(2);  // UART2 (GPIO16=RX, GPIO17=TX)

void setup() {
  Serial.begin(115200);
  gpsSerial.begin(9600, SERIAL_8N1, 16, 17);  // RX=16, TX=17

  // Inisialisasi Ultrasonic
  pinMode(trigPin, OUTPUT);
  pinMode(echoPin, INPUT);

  // Inisialisasi Load Cell
  scale.begin(LOADCELL_DOUT_PIN, LOADCELL_SCK_PIN);
  scale.set_scale(calibration_factor);
  scale.tare();

  // 1. Konfigurasi WiFi pertama
  WiFi.config(local_ip, gateway, subnet);
  WiFi.begin(ssid, password, 6);
  
  Serial.print("Connecting to WiFi");
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < 30000) {
    delay(500);
    Serial.print(".");
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nConnected! IP: " + WiFi.localIP().toString());
  } else {
    Serial.println("\nFailed! Status: " + String(WiFi.status()));
    ESP.restart(); // Auto-restart jika gagal
}
}

void loop() {
  // Baca data GPS
  while (gpsSerial.available() > 0) {
    gps.encode(gpsSerial.read());
  }

  // Baca data Ultrasonic
  float distance = getDistance();

  // Baca data Load Cell
  float weight = 0;
  if (scale.is_ready()) {
    weight = scale.get_units(5);  // Ambil rata-rata 5 pembacaan
    if (weight < 0) weight = 0;  // Pastikan tidak negatif
  }

  // Jika GPS valid, kirim data ke server
  if (gps.location.isValid()) {
    sendToServer(weight, distance, gps.location.lat(), gps.location.lng());
  } else {
    Serial.println("GPS belum siap...");
  }

  delay(5000);  // Kirim data setiap 5 detik
}

// Fungsi baca jarak (Ultrasonic)
float getDistance() {
  digitalWrite(trigPin, LOW);
  delayMicroseconds(2);
  digitalWrite(trigPin, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin, LOW);
  long duration = pulseIn(echoPin, HIGH);
  return duration * SOUND_SPEED / 2;
}

// Fungsi kirim data ke server
void sendToServer(float weight, float distance, float lat, float lng) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi terputus! Mencoba reconnect...");
    WiFi.begin(ssid, password);
    delay(5000);
    return;
  }

  HTTPClient http;
  http.begin(serverUrl);
  http.addHeader("Content-Type", "application/json");

  // Format data JSON sesuai API
  DynamicJsonDocument doc(256);
  doc["weight"] = weight;
  doc["distance"] = distance;
  doc["latitude"] = lat;
  doc["longitude"] = lng;

  String payload;
  serializeJson(doc, payload);

  // Kirim data ke server
Serial.print("Payload: ");
  Serial.println(payload);

  int httpCode = http.POST(payload);
  String response = http.getString();

  if (httpCode == HTTP_CODE_OK) {
    Serial.print("Response: ");
    Serial.println(response);
    
    // Parse response
    DynamicJsonDocument res(128);
    deserializeJson(res, response);
    
    if (res["status"] == "ok") {
      Serial.println("Data benar-benar tersimpan!");
    } else {
      Serial.println("Gagal di server: " + response);
    }
  } else {
    Serial.println("HTTP Error: " + String(httpCode));
    Serial.println("Response: " + response);
  }

  http.end();
}