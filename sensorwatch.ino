/**
 --SensorWatch--
 Measure temperature with several DS18B20-Sensors on one Input and send the data to your website

 Author: Christoph Bodenstein
 License: BSD, see EOF

 QUICK START: Please search for lines with "TBC"-Comment (To be changed), these lines might be changed for your use case

*/


#include <avr/dtostrf.h>
#include <ESP8266.h>
#include <Metro.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include "preferences.h"


int foundDevices = 0; //Number of found sensors (maximum is MAX_SENSOR_NUMBER)
char tempstring[32];//Temporary String for data conversion
String paramstring;//Parameterstring to be sent to Wifi-module


OneWire oneWire(ONE_WIRE_BUS); //Init the One-Wire object for communication
DallasTemperature sensors(&oneWire);//Init sensors


//Addresses of dummy-sensors for testing String conversion and website
uint8_t temp[5][8] = {{0x28, 0x66, 0x30, 0xB5, 0x03, 0x00, 0x00, 0x1F},
  {0x28, 0xE2, 0x6C, 0xB5, 0x03, 0x00, 0x00, 0x9D},
  {0x28, 0xEE, 0x78, 0xB5, 0x03, 0x00, 0x00, 0x83},
  {0x28, 0x87, 0x41, 0xB5, 0x03, 0x00, 0x00, 0xA1},
  {0x28, 0x1E, 0x4B, 0x80, 0x03, 0x00, 0x00, 0xF2}
};

float tempValue[MAX_SENSOR_NUMBER];//Array to contain the sensor values later
uint8_t tempSensorName[MAX_SENSOR_NUMBER][8];//Array to contain the sensor names(addresses)
String SensorName[MAX_SENSOR_NUMBER];

int i = 0; //Counter for later use
boolean blinkValue=TRUE; //Boolean helper for blinking LED

//Metro-Objects for Pseudo-Multithreading, siehe loop()
Metro SensorMetro = Metro(10000); //query sensors every 10 seconds
Metro WifiMetro = Metro(60000); //transmit data to server every 60 seconds


/**
 * Wrapper for watchdog reset, you could add debug outputs or led blinking here
*/
void watchdogReset() {
  WDT_Restart( WDT );
}

/**
 * Function to toogle one LED as reading an output port seems not to work
*/
void blink(){
  if(blinkValue){
    digitalWrite(PINLED, HIGH);
    blinkValue=FALSE;
    }else{
      digitalWrite(PINLED, LOW);
      blinkValue=TRUE;
      }
 }

/**
 * Setup all conected sensors
 * remeber to set MAX_SENSOR_NUMBER
 * -look for sensors
 * -collect there addresses
 * -init the sensors
*/
void setupSensors() {
  watchdogReset();
  //start up the temperature-library
  sensors.begin();
  watchdogReset();
  //locate devices on the bus
  Serial.print("Locating devices...");
  Serial.print("Found ");
  Serial.print(sensors.getDeviceCount(), DEC);
  Serial.println(" devices.");
  //report parasite power requirements
  Serial.print("Parasite power is: ");
  if (sensors.isParasitePowerMode()) Serial.println("ON");
  else Serial.println("OFF");
  watchdogReset();
  foundDevices = sensors.getDeviceCount();
  for (i = 0; i < foundDevices; i++) {
    tempValue[i] = 0; //Init the Values
    sensors.getAddress(tempSensorName[i], i); //Get Address of sensor
    sensors.setResolution(tempSensorName[i], TEMPERATURE_PRECISION);
    watchdogReset();
  }
  watchdogReset();
}

/**
 * Init the Wify(ESP8266)-module
*/
void setupWifi() {
  watchdogReset();
  Serial.println("Setting up wifi...");
  ESP8266 wifi(WIFIPORT);

  Serial.print("FW Version:");
  Serial.println(wifi.getVersion().c_str());

  if (wifi.setOprToStation()) {
    Serial.print("to station ok\r\n");
  } else {
    Serial.print("to station err\r\n");
  }
  watchdogReset();

  if (wifi.joinAP(SSID, PASSWORD)) {
    Serial.print("Join AP success\r\n");

    Serial.print("IP:");
    Serial.println( wifi.getLocalIP().c_str());
  } else {
    Serial.println("Join AP failure. Will restart system in 16 sec.");
    while (1);
  }
  watchdogReset();
  if (wifi.disableMUX()) {
    Serial.print("single ok\r\n");
  } else {
    Serial.print("single err\r\n");
  }

  Serial.print("setup end\r\n");


}

/**
 * Test all outputs, switch on/off
*/
void testOutputs() {
  watchdogReset();
  digitalWrite(PINBuzzer, HIGH);//Buzzer on
  digitalWrite(PINLED, HIGH);//LED on
  delay(1000);
  digitalWrite(PINBuzzer, LOW);//Buzzer off
  digitalWrite(PINLED, LOW);//LED off
}



/**
 * Send data over wifi/call http-Address
*/
void sendData() {
  watchdogReset();
  String paramstring = "";

  ESP8266 wifi(WIFIPORT);
  uint8_t buffer[1024] = {0};

  if (wifi.createTCP(HOST_NAME, HOST_PORT)) {
    Serial.print("Create tcp ok\r\n");
    //Starting to build Request-String for Wifi-shield
    paramstring = "GET ";
    paramstring.concat(GETRequestPrefix);
    paramstring.concat(SEKRET);
    for (i = 0; i < foundDevices; i++) {
      watchdogReset();
      paramstring += "&";
      dtostrf(tempValue[i], 3, 1, tempstring); //convert Float to String
      paramstring.concat(SensorName[i]);
      paramstring.concat("=");
      paramstring.concat(tempstring);
    }
    paramstring.concat(" HTTP/1.1\r\nHost: ");
    paramstring.concat(HOST_NAME);
    paramstring.concat("\r\nConnection: close\r\n\r\n");
    Serial.print("Output to Wifi-shield:");
    Serial.println(paramstring);

    //Will send to Wifi-shield
    wifi.send((const uint8_t*)paramstring.c_str(), paramstring.length());

    uint32_t len = wifi.recv(buffer, sizeof(buffer), 10000);
    if (len > 0) {
      Serial.print("Received:[");
      for (uint32_t i = 0; i < len; i++) {
        Serial.print((char)buffer[i]);
      }
      Serial.print("]\r\n");
    }

    if (wifi.releaseTCP()) {
      Serial.print("release tcp ok\r\n");
    } else {
      Serial.print("release tcp err\r\n");
    }


  } else {
    Serial.println("Failed to connect, will restart System in 16 sec.");
    while (1);
  }

}

/**
 * Setup-routine.
 * Init Wifi-shield
 * Search and init sensors
*/
void setup() {
  // start serial port
  Serial.begin(9600);
  Serial.println("Startup of system...");

  pinMode(PINBuzzer, OUTPUT);
  pinMode(PINWIFIRESET, OUTPUT);
  pinMode(PINLED, OUTPUT);
  watchdogReset();

  digitalWrite(PINLED, HIGH);
  delay(3000);//Wait some sec to power up WiFi-capacitors
  digitalWrite(PINLED, LOW);

  //Reset Wifi-module
  digitalWrite(PINWIFIRESET, LOW);
  delay(1000);
  digitalWrite(PINWIFIRESET, HIGH);
  digitalWrite(PINLED, HIGH);

  watchdogReset();

  testOutputs();//test all outputs
  setupWifi();//start WiFi
  if (DUMMY) {
    int i, j;
    for (i = 0; i < 5; i++) {
      for (j = 0; j < 8; j++) {
        tempSensorName[i][j] = temp[i][j];
      }
    }

    foundDevices = 5;
  }   else {
    setupSensors();//init sensors
  }

  if (!(DEBUG))Serial.end();
}


/**
 * Request all Temperatures and write the value to the global array
*/
void getTemperatures() {
  watchdogReset();
  for (i = 0; i < foundDevices; i++) {
    blink();
    sensors.requestTemperatures();//let sensors start the measurement. You could try to use this once for all sensors.
    delay(1000);//Wait until all sensors ended measuring
    watchdogReset();

    if (DUMMY) {
      //Set Dummy-Temperature
      //tempSensorName is already set in setup()
      tempValue[i] = 22.34;
    }   else {
      sensors.getAddress(tempSensorName[i], i); //Get Address of sensor every time, in case order has changed
      tempValue[i] = sensors.getTempC(tempSensorName[i]);
    }

    SensorName[i] = "";
    int c = 0;
    for (c = 0; c < 8; c++) {
      SensorName[i].concat(tempSensorName[i][c]);
    }

    Serial.print("Temp of Sensor: "); Serial.print(SensorName[i]);
    Serial.print(" is "); Serial.println(tempValue[i]); //Print sensor value
    delay(350);//Have a break to wait for voltage on signal line come up again (maybe not needed in other setups)
  }

}


/**
 * Main Loop of arduino program
*/
void loop()
{

  watchdogReset();
  if (SensorMetro.check() == 1) { //Time to check sensors? -->read sensor data
    Serial.println("Checking Temps");
    blink();
    getTemperatures();
    blink();
  }
  if (WifiMetro.check() == 1) { //Time to send sensor values to server?
    sendData();
  }
}


/**
 Copyright (c) <2015>, <Christoph Bodenstein>
 All rights reserved.

 Redistribution and use in source and binary forms, with or without modification,
 are permitted provided that the following conditions are met:

 1. Redistributions of source code must retain the above copyright notice,
 this list of conditions and the following disclaimer.

 2. Redistributions in binary form must reproduce the above copyright notice,
 this list of conditions and the following disclaimer in the documentation and/or
 other materials provided with the distribution.

 THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS;
 OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
 EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

 */


