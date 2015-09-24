# Sensorwatch
A combination of arduino and web to log/view sensor data (i.e. temperature).

### Basic usage
Several Sensors (DS18B20 temp sensors) are coupled via One-Wire to your arduino. It will read the sensor values frequently and upload them to your Webserver.
Via http:... you can access this sensor values as (hopefully) neat graphs or simple as values.


### Used hardware
* [Arduino DUE](https://www.arduino.cc/en/Main/ArduinoBoardDue) (former versions used an Arudino UNO)
* ESP8266 Wifi-module (former Versions used a Wifly-shield)
* Sensor-shield (not necessary but it makes some connections easier)
* DS18B20 temperature sensors


### Demo
To see if it fits your needs, have a look at this demo installation:

[Desktop view](http://cbck.de/~deubach/sensorwatchdemo/index.php?SEKRET=DEMO#)

[Simpe View, for mobile](http://cbck.de/~deubach/sensorwatchdemo/index.php?SEKRET=DEMO&SIMPLE=YES)


### Installation
1. Solder/connect your sensors to arduino
2. Install the necessary arduino libraries
3. Make your changes in arduino-project (_preferences.h_)
4. Copy php-css-js files to your webspace and make corresponding changes

#### Changes to be made for installation
There are few things to be changed before you can start.
##### Arduino side
* All points to be changed are in _preferences.h_
* Network config (I used no DHCP because the WiFly-shield had issues in combination with my router)
* SEKRET You need to set a simple string to obfuscate the URL so that not everybody can add faulty sensor values. Sometimes this is called this API-Key.
This is __not secure__, so don`t use this SW to control critical Systems!

##### Server side
* VIEWSEKRET is needed to see the website incl. the sensor values
* ADDSEKRET is needed to add new sensor values --> this is the same like in arduino project
* Raw and readable names of your sensors are entered as an array at the beginning of index.php (Every unknown sensor will be displayed as such beside the graph.)
* There are many things to be changed for your use case. They are marked with "TBC" in arduino and php source code.



### Used third party software (Website)
This Project uses __materialize__ design and __Chart.js__ for graphs.


For materialize documentation, see the [materialize website.](http://materializecss.com)

For complete Chart.js documentation, see the 
[Chart.js home page.](http://www.chartjs.org/)

### Used Arduino libraries
* [OneWire](https://www.pjrc.com/teensy/td_libs_OneWire.html) for basic communication with DS18B20-Sensors
* [Dallas Temperature library](http://milesburton.com/Main_Page?title=Dallas_Temperature_Control_Library) to init sensors with resolution etc. and read values
* [Metro](https://github.com/thomasfredericks/Metro-Arduino-Wiring) For pseudo scheduling of tasks
* [ITEADLIB_Arduino_WeeESP8266](https://github.com/itead/ITEADLIB_Arduino_WeeESP8266) to use the Wifi-module


### Important HW-Issues to keep in mind
* Some __Arduio DUE don`t start after power-up__ until reset is pressed. You can use a capacitor to make a simple workaround, thanks to martinyim from [arduino-forum](http://forum.arduino.cc/index.php?topic=256771.15)
* The Wifi-module will need high currents during data-transfer. So it`s a good idea to not power it through arduino but with a secondary power source. I used the lazy solution and added some big capacitors to overcome the short times of intense power consumption.
* I __use the watchdog__ to reset the whole system if any transmission problems occur. But in the last Arduino-IDE the watchdog for arduino DUE is disabled. You need to enable it. That´s easy once you found the relevant files on your system. [See here](http://forum.arduino.cc/index.php?topic=233175.0)


### Known Issues and ideas for your fork
* Authentication is not supported now because Arduino Wifly lib or Wifly-Shield does not support https and I'm not aware of another solution with similar security.
* HTTPS is not available, looks like the used hardware does not suport this.
* Gaps of sensor-values. When there is not sensor value/signal for some time, you will not notice this in the graph, because all available values are taken into account without checking the available frequency of sensor values
* Instead of the built in watchdog you could use a hardware watchdog to completely reset arduino and WiFly in case of any error. My last version used this and it was a pretty good idea for a system you cannot touch for a long time.