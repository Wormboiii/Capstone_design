#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>

const int trigPins[] = {D0, D2, D3, D4}; //set sensors' triger pins(센서의 트리거 핀 할당)
const int echoPins[] = {D5, D6, D7, D8}; //set sensors' echo pins(센서의 에코 핀 할당)

#define SOUND_VELOCITY 0.034 // set sound velocity(음속 설정)
#define SSID "Wormboy"
#define PWD "Walter0528"

// Server address
WiFiClient Client;
const char* serverUrl = "http://52.78.206.167/update_sensor.php"; //route of the server(서버의 PHP 파일 경로)

long durations[4];
float distancesCm[4];

void setup() {
  Serial.begin(9600); //start serial communication(시리얼통신 시작)
  WiFi.begin(SSID, PWD); //connect to WiFi(와이파이 연결)

  while(WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.println("Connecting...");
  }
  Serial.println("Connected!");

 for (int i = 0; i < 4; i++) {
    pinMode(trigPins[i], OUTPUT);
    pinMode(echoPins[i], INPUT);
  }
}

void loop() {
  for (int i = 0; i < 4; i++) {
    digitalWrite(trigPins[i], LOW);
    delayMicroseconds(2);

    digitalWrite(trigPins[i], HIGH);
    delayMicroseconds(10);
    digitalWrite(trigPins[i], LOW);

    durations[i] = pulseIn(echoPins[i], HIGH);
    distancesCm[i] = durations[i] * SOUND_VELOCITY / 2;

    Serial.print("Sensor ");
    Serial.print(i + 1);
    Serial.print(" Distance (cm): ");
    Serial.println(distancesCm[i]);
  }

 if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(Client, serverUrl); //connect to server(서버와 연결)
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    String postData = "sensor1=" + String(distancesCm[0] < 10 ? 1 : 0) + //POST sensor data(센서값 서버로 전송)
                      "&sensor2=" + String(distancesCm[1] < 10 ? 1 : 0) +
                      "&sensor3=" + String(distancesCm[2] < 10 ? 1 : 0) +
                      "&sensor4=" + String(distancesCm[3] < 10 ? 1 : 0);
    int httpResponseCode = http.POST(postData);
    http.end();
  }

  delay(2000);
}