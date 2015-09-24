#define DEBUG 1 //1->serial output on, 2-> serial output off //TBC
#define DUMMY 0//1->no sensors connected, dummy sensors and tmperatures are used
#define ONE_WIRE_BUS 5 //Sensors are connected to PIN 5 (data line) //TBC
#define TEMPERATURE_PRECISION 12//precision of temperature measurement will be 12 Bit
#define MAX_SENSOR_NUMBER 10 //Maximum number of sensors (used for array allocation) //TBC

/*Networking options*/
#define SSID        "YOURSSID"
#define PASSWORD    "YOURWIFIPASSWORD"

#define SEKRET "YOURADDSEKRET(API-KEY)" //TBC
#define GETRequestPrefix "/yourWebsiteDirOnGivenServer/index.php?SEKRET=" //Prefix for every GET-Request to transmit data //TBC

#define HOST_NAME    "www.yourservername.com"
#define HOST_IP      "334.231.123.211"
#define HOST_PORT   80


//Where are the wires connected to
#define PINBuzzer 4 //Buzzer-PIN //TBC
#define PINLED 13 //The PIN of internal LED to blink while active
#define WIFIPORT Serial1 //HW-Serialport where ESP8266 is connected to
#define PINWIFIRESET 22 //Reset-PIN of ESP8266


