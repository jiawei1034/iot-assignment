#include <SoftwareSerial.h>

/* =====================================================
   DEVICE INFORMATION
===================================================== */
String deviceId = "DEVICE_01";

/* =====================================================
   ESP8266 CONFIG
===================================================== */
SoftwareSerial esp(2, 3);   // RX, TX

String ssid     = "jw_iphone";
String password = "Hello123";
String server   = "18.143.120.77";

/* =====================================================
   PIR SENSOR CONFIG
===================================================== */
int calibrationTime = 30;
long unsigned int lowIn;
long unsigned int pause = 5000;

boolean lockLow = true;
boolean takeLowTime;

int pirPin    = 7;
int ledPin    = 5;
int buzzerPin = 6;

/* =====================================================
   SHOCK SENSOR CONFIG
===================================================== */
int shockPin = 8;
boolean shockLock = false;

/* =====================================================
   COMMON VARIABLES
===================================================== */
int motionDetected = 0;
int shockDetected  = 0;

/* =====================================================
   WIFI CONNECTION WITH STATUS CHECK
===================================================== */
bool connectWiFi() {

  Serial.println("Connecting to WiFi...");

  esp.println("AT+RST");
  delay(2000);
  esp.flush();

  esp.println("AT+CWMODE=1");
  delay(1000);

  String cmd = "AT+CWJAP=\"" + ssid + "\",\"" + password + "\"";
  esp.println(cmd);

  unsigned long startTime = millis();
  bool connected = false;

  while (millis() - startTime < 15000) {
    if (esp.available()) {
      String response = esp.readString();
      Serial.println(response);

      if (response.indexOf("WIFI CONNECTED") >= 0 ||
          response.indexOf("OK") >= 0) {
        connected = true;
        break;
      }

      if (response.indexOf("FAIL") >= 0) {
        break;
      }
    }
  }

  if (connected) {
    Serial.println("WiFi Connected");
  } else {
    Serial.println("WiFi NOT Connected");
  }

  return connected;
}

/* =====================================================
   SEND JSON DATA
===================================================== */
void sendSensorData(String sensorType, int detectedValue) {

  unsigned long deviceTimestamp = millis() / 1000;
  unsigned long uptimeSeconds   = deviceTimestamp;
  int wifiRSSI = -60;   // placeholder

  String jsonPayload =
    "{"
    "\"device_id\":\"" + deviceId + "\","
    "\"sensor_type\":\"" + sensorType + "\","
    "\"is_detected\":" + String(detectedValue) + ","
    "\"device_timestamp\":" + String(deviceTimestamp) + ","
    "\"uptime_seconds\":" + String(uptimeSeconds) + ","
    "\"wifi_rssi\":" + String(wifiRSSI) +
    "}";

  String url = "/iot-test/api/sensor_data.php";

  esp.println("AT+CIPSTART=\"TCP\",\"" + server + "\",80");
  delay(2000);

  String request = "POST " + url + " HTTP/1.1\r\n";
  request += "Host: " + server + "\r\n";
  request += "Content-Type: application/json\r\n";
  request += "Content-Length: " + String(jsonPayload.length()) + "\r\n";
  request += "Connection: close\r\n\r\n";
  request += jsonPayload;

  esp.println("AT+CIPSEND=" + String(request.length()));
  delay(500);
  esp.print(request);
  delay(2000);
  esp.println("AT+CIPCLOSE");

  Serial.println("Data sent:");
  Serial.println(jsonPayload);
}

/* =====================================================
   SETUP
===================================================== */
void setup() {
  Serial.begin(9600);
  esp.begin(9600);

  pinMode(pirPin, INPUT);
  pinMode(shockPin, INPUT);
  pinMode(ledPin, OUTPUT);
  pinMode(buzzerPin, OUTPUT);

  digitalWrite(ledPin, LOW);
  digitalWrite(buzzerPin, LOW);

  Serial.print("Calibrating PIR and Shock ");
  for (int i = 0; i < calibrationTime; i++) {
    Serial.print(".");
    delay(1000);
  }
  Serial.println(" DONE");
  Serial.println("SENSORS ACTIVE");

  connectWiFi();
}

/* =====================================================
   LOOP
===================================================== */
void loop() {

  /* ---------- PIR SENSOR ---------- */
  if (digitalRead(pirPin) == HIGH) {
    digitalWrite(ledPin, HIGH);
    tone(buzzerPin, 500);
    motionDetected = 1;

    if (lockLow) {
      lockLow = false;
      Serial.println("Motion detected");
      sendSensorData("motion", 1);
    }
    takeLowTime = true;
  }

  if (digitalRead(pirPin) == LOW) {
    motionDetected = 0;

    if (takeLowTime) {
      lowIn = millis();
      takeLowTime = false;
    }

    if (!lockLow && millis() - lowIn > pause) {
      lockLow = true;
      Serial.println("Motion ended");
      digitalWrite(ledPin, LOW);
      noTone(buzzerPin);
      sendSensorData("motion", 0);
    }
  }

  /* ---------- SHOCK SENSOR ---------- */
  if (digitalRead(shockPin) == HIGH && !shockLock) {
    shockDetected = 1;
    shockLock = true;

    Serial.println("Shock detected");
    digitalWrite(ledPin, HIGH);
    tone(buzzerPin, 800);

    sendSensorData("shock", 1);
  }

  if (digitalRead(shockPin) == LOW && shockLock) {
    shockDetected = 0;
    shockLock = false;

    Serial.println("Shock ended");

    if (!motionDetected) {
      digitalWrite(ledPin, LOW);
      noTone(buzzerPin);
    }

    sendSensorData("shock", 0);
  }

  delay(100);
}