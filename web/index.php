<?php
/*
 * ----------------------------------------------------------------------------
 * "THE BEER-WARE LICENSE" (Revision 42):
 * <info@cbck.de> schrieb diese Datei. Solange Sie diesen Vermerk nicht entfernen, können
 * Sie mit dem Material machen, was Sie möchten. Wenn wir uns eines Tages treffen und Sie
 * denken, das Material ist es wert, können Sie mir dafür ein Bier ausgeben. Christoph Bodenstein
 * ----------------------------------------------------------------------------

 * ----------------------------------------------------------------------------
 * "THE BEER-WARE LICENSE" (Revision 42):
 * <info@cbck.de> wrote this file. As long as you retain this notice you
 * can do whatever you want with this stuff. If we meet some day, and you think
 * this stuff is worth it, you can buy me a beer in return. Christoph Bodenstein
 * ----------------------------------------------------------------------------

 */

/***  Begin of some defines, global vars and inits ***/


//TBC Enter your Sensor raw names and readable names
//First Sensor will be default and printed first
//Raw names and readable names MUST be unique!
$sensorNames = array(
    "401024818130031" => "Heating",
    "40226108181300157" => "Oven",
    "40238120181300131" => "Primary",
    "4013565181300161" => "Secondary",
    "403075128300242" => "Solar",
);

//chart-colors for known sensors
$sensorColors = array(
    "401024818130031" => "065,105,225",
    "40226108181300157" => "188,143,143",
    "40238120181300131" => "097,097,097",
    "4013565181300161" => "169,169,169",
    "403075128300242" => "255,165,000",
);

//This is a workaround and should be removed soon
foreach ($sensorNames as $key => $value) {
    $sensorNamesRaw[] = $key;
    $sensorNamesNice[] = $value;
}

$DefaultNumberOfLabels = 4; //Number of labels in chart //TBC
$DefaultResolution = 50; //Number of data points to show in chart //TBC
$DefaultReloadTime = 30000; //Time in milliseconds after Page or graph is reloaded //TBC
$DefaultPrecision = 1; //Precision of numbers to be printed
$DefaultScaleDivider = 4; //Number of labels on scale
$ADDSEKRET = "InsertYourSecretTextHere"; //TBC "Secret", needed to add sensor values (to make it a little difficult for strangers to set wrong values) //TBC
$VIEWSEKRET = "InsertAnotherSecretHere"; //TBC "Secret", needed to view the website
$TITLE = "Heizungsüberwachung";//TBC
define("SQLITENAME", "sensors");
define("DEBUG", FALSE);
define("DEMOMODE", FALSE);

//Set default timezone
$DefaultTimeZone = "Europe/Amsterdam"; //In PHP5.1 or newer this can be replaced
date_default_timezone_set($DefaultTimeZone);

$FakeTimeStamp = 1434405435; //This timestamp is used in demo mode
/*
 * List of Sensor not to be shown in Graph, even if they are found in db
 * initially filed with random sensornames/numbers. TBC: Empty this array or enter your sensors to be ignored
 */
$ListOfSensorsToIgnore=array("23874623486", "2384762346", "40135651813001612_4");

/*
 * Unknown Sensors will be stored in this list to be shown beside chart
 */
$listOfUnknownSensors;

/***  End of some defines and global vars ***/

/***  Begin of function definition/implementation ***/
/**
 * Wrapper to output some debug data into the page
 * Outputs with echo if $DEBUG=true
 */
function debugEcho($s) {
    if (DEBUG) {
        echo($s);
    }
}

/**
 * Get actual time (localized!)
 * TBC- change $DefaultTimeZone to yours!
 * It will fake the actual date if necessary (DEMOMODE==TRUE)
 */
function getTime() {
    global $DefaultTimeZone;
    global $FakeTimeStamp;
    $date = new DateTime(null, new DateTimeZone($DefaultTimeZone));
    if (DEMOMODE) {
        return $FakeTimeStamp;
    } else {
        return ($date->getTimestamp());
        //return ($date->getTimestamp() + $date->getOffset());
    }
}

/**
 * Returns the value rounded to x decimal numbers
 * Wrapper-function for php round
 */
function getRoundedValue($value) {
    global $DefaultPrecision;
    return round($value, $DefaultPrecision);
}


/*
 * Returns a default color for Chart.js dataset of each sensor
 * The colors are defined in array $sensorColors
 * If raw name of sensor is not in array it will return a default color
 */
function getColorForSensor($sensorNameRaw) {
    global $sensorColors;
    if ($returnValue = $sensorColors[$sensorNameRaw]) {
        return $returnValue;
    } else {
        return("220,220,220");
    }
}


/**
 * Init the DB if sqlite-file does not exist
 * Maybe Create db without any example sensorname?
 */
function initDB() {
	echo("Will try to init the database.");	
    try {
        //create or open the database
		// Create (connect to) SQLite database in file
		    $file_db = new PDO('sqlite:'.SQLITENAME.'.sqlite3');
		    // Set errormode to exceptions
		    $file_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
        $query = 'CREATE TABLE sensorvalues (TIME DATE PRIMARY KEY UNIQUE, "401024818130031" REAL)';
		$file_db->exec($query);

    } catch (Exception $e) {
		echo("Error initializing the database.");
		echo($e);
        die($e);
    }
}

/**
 * Echo the data object for Chart.js for all chosen sensors given by $arrayOfSensorNamesRaw
 * It echos a complete javascript datastructure which must be "evaled" be receiver to get a
 * variable called "dataReal".
 * Chart.js will be reinitialized with this as data-object.
 */
function echoDatasetForChart($arrayOfSensorNamesRaw, $startTime, $endTime, $resolution, $mode) {
//Get Array of timestamps to check if values are available and to get the real number of data points
    ////Check for the real time contraints possible with data from db. Needed to have a correct scale
    //echo("From: ".$startTime. " to: ".$endTime." a difference of: ".($endTime-$startTime));
    $timeDataArray = getDataAsArray("TIME", $startTime, $endTime, $resolution, "TIME");
    if (count($timeDataArray) < 1) {
        //Eject and echo null
        json_encode(null);
        exit();
    } else {
        //var_dump($timeDataArray);
        $startTime = $timeDataArray[0];
        $endTime = $timeDataArray[count($timeDataArray) - 1];
        //echo("From: ".$startTime. " to: ".$endTime." a difference of: ".($endTime-$startTime));
    }
//Build array of datasets as javscript variable
    ?>
    var dataReal={
    labels:
    <?php
    global $DefaultResolution;
    echoLabelArrayAsJson($resolution, $timeDataArray);
    ?>,
    datasets: [
    <?php
    global $sensorNames;
    foreach ($arrayOfSensorNamesRaw as $keyRaw => $valueRaw) {
        $sensorNameRaw = $valueRaw;
        $sensorNameReadable = $sensorNames[$valueRaw];
        ?>
        {
        label: "<?php echo($sensorNameReadable); ?>",
        fillColor: "rgba(<?php echo(getColorForSensor($sensorNameRaw)); ?>,0.01)",
        strokeColor: "rgba(<?php echo(getColorForSensor($sensorNameRaw)); ?>,1)",
        pointColor: "rgba(<?php echo(getColorForSensor($sensorNameRaw)); ?>,1)",
        pointStrokeColor: "#fff",
        pointHighlightFill: "#fff",
        pointHighlightStroke: "rgba(<?php echo(getColorForSensor($sensorNameRaw)); ?>,1)",
        data: <?php
        global $DefaultResolution;
        echoDataAsJson($sensorNameRaw, $startTime, $endTime, $DefaultResolution, $mode);
        ?>
        },

        <?php
    }
    ?>
    ]
    }<?php
}


/**
 * Add one row of data/time for one sensor.
 * If sensor-column is not there, it will be created.
 * 	If row is already there (time is key), field is updated
 * @param $sensorNameRaw Raw name of sensor
 * @param $value measured sensor value
 * @param $time Timestamp of measured sensor value
 */
function addSensorValue($sensorNameRaw, $value, $time) {
    //$database = new SQLiteDatabase(SQLITENAME, 0666, $error);
	if(!file_exists(SQLITENAME.'.sqlite3')){
		initDB();
	}
	
    $database = new PDO('sqlite:'.SQLITENAME.'.sqlite3');
    // Set errormode to exceptions
    $database->setAttribute(PDO::ATTR_ERRMODE, 
                            PDO::ERRMODE_EXCEPTION);

    //Get info about table and check if column-name exists
    $query = "PRAGMA table_info('sensorvalues')";
    $sensorNameExists = false;
    $i = 0;
    if ($result = $database->query($query)) {
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $colnames[$i] = $row['name'];
            $coltypes[$i] = $row['type'];
            $i++;
            if ($row['name'] == $sensorNameRaw) {
                $sensorNameExists = true;
            }
        }
        //If sensorname column doesnt exist, create it
        if (!$sensorNameExists) {
            //Try to delete temp_table if needed
            $query = 'DROP TABLE temp_table;';
            executeQuery($database, $query);
        	
        	//Select everything from old table into new temporary table
            $query = 'CREATE TABLE temp_table AS SELECT * FROM sensorvalues;';
            //echo("<br> will query:".$query);
			$res=$database->query($query);
			//echo(": Result was: ".$res);
			//var_dump($res);
            $query = 'DROP TABLE sensorvalues;';
            executeQuery($database, $query);
            $query = 'CREATE TABLE sensorvalues (TIME DATE PRIMARY KEY UNIQUE, ';
            $iMax = count($colnames);
            for ($i = 1; $i < $iMax; $i++) {
                $query.='"' . $colnames[$i] . '" ' . $coltypes[$i] . ',';
            }
            $query.='"' . $sensorNameRaw . '" REAL);';
            executeQuery($database, $query);

            $query = 'INSERT INTO sensorvalues (';
            for ($i = 0; $i < $iMax; $i++) {
                $query.='"' . $colnames[$i] . '"';
                if ($i < $iMax - 1) {
                    $query.=',';
                } else {
                    $query.=')';
                }
            }

            $query.=' SELECT ';
            for ($i = 0; $i < $iMax; $i++) {
                $query.='"' . $colnames[$i] . '"';
                if ($i < $iMax - 1) {
                    $query.=',';
                }
            }

            $query.=' FROM temp_table;';
            executeQuery($database, $query);
            $query = 'DROP TABLE temp_table;';
            executeQuery($database, $query);
        }

        //Insert the row if it doesn`t exist
        $query = 'INSERT OR IGNORE INTO sensorvalues (TIME) VALUES (' . $time . ');';
        executeQuery($database, $query);
        //Now add the data for the given sensor
        $query = 'UPDATE sensorvalues SET "' . $sensorNameRaw . '"=' . $value . ' WHERE TIME=' . $time . ';';
        executeQuery($database, $query);
    } else {
        //There was an error checking the table info
        echo("There was an error checking the table info.");
        exit;
    }
}

/**
 * Executes a db-query.
 * This function is needed if your want to output your db-queries for debug-reasons.
 */
function executeQuery($database, $query) {
    debugEcho("executing: " . $query . "...");
    try{
		if ($result = $database->query($query)) {
			debugEcho("OK.");
			return TRUE;
		} else {
			debugEcho("Error!");
			return FALSE;
    	}
    debugEcho("</br>");
    }catch (Exception $e) {
        debugEcho("<br>Error executing Query.".$query."<br>");
    }
}

/**
 * Add all sensor values from GET-Variables to database.
 * Use actual timestamp as key.
 */
function addAllSensorValuesFromGET() {
    $timestamp = getTime();
    foreach ($_GET as $key => $value) {
        //Check if $key is a sensor-name, if yes, add this value
        if (isSensorName($key)) {
            addSensorValue($key, $value, $timestamp);
        }
    }
    /* Delete all data older than one year */
    //$database = new SQLiteDatabase(SQLITENAME, 0666, $error);
    $database = new PDO('sqlite:'.SQLITENAME.'.sqlite3');
    // Set errormode to exceptions
    $database->setAttribute(PDO::ATTR_ERRMODE, 
                            PDO::ERRMODE_EXCEPTION);
    $startTime = getTime() - (3600 * 24 * 365);
    $query = "DELETE FROM sensorvalues WHERE TIME < '$startTime' ";
    try{
	$database->exec($query);
    }catch (Exception $e) {
        echo("Error deleting old data.");
		die($error);
    }
}

/**
 * Validates name of sensor.
 * Returns true, if given $testString is a valid sensorName
 * TBC: Enter your own rules of name validation!
 */
function isSensorName($testString) {
    //If $testString can be converted to int and this is greater than 32000, then true, else false
    if (intval($testString) > 0) {
        return true;
    } else {
        return false;
    }
}

/**
 * Get list of raw sensornames and
 * check if there are unknown sensor-columns or
 *      if there are sensors in readable name array that are not available
 */
function checkSensorNameList() {
    //$database = new SQLiteDatabase(SQLITENAME, 0666, $error);
	if(!file_exists(SQLITENAME.'.sqlite3')){
		initDB();
	}
    $database = new PDO('sqlite:'.SQLITENAME.'.sqlite3');
    // Set errormode to exceptions
    $database->setAttribute(PDO::ATTR_ERRMODE, 
                            PDO::ERRMODE_EXCEPTION);
							
    //Get info about table and check if column-name exists
    $query = "PRAGMA table_info('sensorvalues')";

    $sensorNameExists = false;
    $i = 0;
    if ($result = $database->query($query)) {
        $firstLine = TRUE;
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            if ($firstLine) {
                $firstLine = FALSE;
                continue;
            }
            $colnames[] = $row['name'];
        }

        global $sensorNames;
        foreach ($sensorNames as $key => $value) {
            $knownSensors[] = $key;
        }

        global $listOfUnknownSensors;
        $listOfUnknownSensors = array_diff($colnames, $knownSensors); //Sensors that are not in list of known ones
        $listOfSensorsNotToDisplay = array_diff($knownSensors, $colnames); //Sensors that are in list of known ones but not found in db
        //Remove Sensore that are named but not found in db from list, so they cannot be chosen with sensor-checkbox
        global $ListOfSensorsToIgnore;
        $listOfSensorsNotToDisplay=array_merge($ListOfSensorsToIgnore, $listOfSensorsNotToDisplay);
        foreach ($listOfSensorsNotToDisplay as $key => $value) {
            unset($sensorNames[$value]);
        }
    }
}

/**
 * Creates an Array of Labels based on requested Resolution and time
 * @param $startTime Timestamp where the labels (and probably the datapoints) start
 * @param $endTime Timestamp where the labels (and probably the datapoints) end
 * @param $resolution number of requested data-points, as many label-ticks have to be generated
 */
function echoLabelArrayAsJson($resolution, $timeDataArray) {
    global $DefaultScaleDivider;

    $maxValues = min(count($timeDataArray), $resolution);

    //Factor for calculate the position in timedata-array while we iterate through a number of resolution values for labels
    $factor = count($timeDataArray) / $resolution;

    $startTime = $timeDataArray[0];
    $endTime = $timeDataArray[count($timeDataArray) - 1];

    $timeDifference = $endTime - $startTime;
    $tmpDivider = ceil($maxValues / ($DefaultScaleDivider - 1));

    $labelCounter = 0;
    for ($i = 0; $i < $maxValues; $i++) {
        if (($i % $tmpDivider == 0) || (($maxValues - 1) == $i )) {
            $labelCounter++;
            if ($i == ($maxValues - 1)) {
                $timeDataArrayPosition = count($timeDataArray) - 1;
            } else {
                $timeDataArrayPosition = floor($i * $factor);
            }
            $date = $timeDataArray[$timeDataArrayPosition];
            if ($timeDifference <= 3600) {
                //If time difference is less than an hour, show minutes (and hours)
                $labels[] = date("H:i", $date);
            } elseif ($timeDifference <= (3600 * 24)) {
                //If time difference is less than a day, show hours (and day)
                $labels[] = date("D, H:i", $date);
            } elseif ($timeDifference <= (3600 * 24 * 7)) {
                //If time difference is less than a week, show weekdays
                $labels[] = date("D", $date);
            } elseif ($timeDifference <= (3600 * 24 * 30)) {
                //If time difference is less than a month, show days
                $labels[] = date("d.M", $date);
            } elseif ($timeDifference <= (3600 * 24 * 365)) {
                //If time difference is less than a year, show months
                $labels[] = date("M", $date);
            } else {
                //Default case, show everything
                $labels[] = date("d.m.Y H:i", $date);
            }
        } else {
            $labels[] = '';
        }
    }
    echo(json_encode($labels));
}

/**
 * Outputs all datasets within the requested time as json string. Used for Ajax chart-update
 */
function echoDataAsJson($sensorNameRaw, $startTime, $endTime, $resolution, $mode) {
    echo json_encode(getDataAsArray($sensorNameRaw, $startTime, $endTime, $resolution, $mode));
}

/**
 * Returns all datasets within the requested time(in seconds) as 2D-Array, with size $resolution
 * @param $sensorNameRaw Raw name of sensor to get the datapoints
 * @param $startTime Start of time frame to get datapoints from
 * @param $endTime End of time frame to get datapoints from
 * @param $resolution Number of requested data points within the time-frame
 * @param $mode Mode of calculated datapoints, either AVG or EXTREMA
 */
function getDataAsArray($sensorNameRaw, $startTime, $endTime, $resolution, $mode) {
    //$mode="avg" (default) or "extrema"
    $returnValue=array();
    if (!($mode == "AVG") && !($mode == "EXTREMA") && !($mode == "TIME")) {
        $mode = "AVG";
    }
	global $DefaultResolution;
    $resolution = max($resolution, $DefaultResolution);

    $database = new PDO('sqlite:'.SQLITENAME.'.sqlite3');
    // Set errormode to exceptions
    $database->setAttribute(PDO::ATTR_ERRMODE, 
                            PDO::ERRMODE_EXCEPTION);
							
    $query = 'SELECT "' . $sensorNameRaw . '" FROM sensorvalues WHERE TIME >= ' . $startTime . ' AND TIME <= ' . $endTime . ' ORDER BY TIME ASC;';
    if ($result = $database->query($query)) {
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $returnValue[] = $row[0];
//            echo(" - ".$row[0]);
        }

        $numberOfFoundDataPoints = count($returnValue);
        //If number of found datapoints <= $resolution
        if ($numberOfFoundDataPoints <= $resolution) {
            return $returnValue;
        }

        //Return array of timestamps if requested
        if ($mode == "TIME") {
            return $returnValue;
        }

        if ($mode == "AVG") {
            //Return the avg value per block
            //Determine number of possible data blocks for average of min/max calculation
            $numberOfDataPointsPerBlock = floor($numberOfFoundDataPoints / $resolution);
            $outputArray = array_slice($returnValue, (-1)*$numberOfDataPointsPerBlock*$resolution);
			unlink($returnVal);
			
            for ($i = 0; $i < $resolution; $i++) {
                $sum = 0;
                for ($i1 = 1; $i1 <= $numberOfDataPointsPerBlock; $i1++) {
                    $sum+=$outputArray[$i1 * $i];
                }
                $returnVal[] = getRoundedValue($sum / $numberOfDataPointsPerBlock);
            }
            return $returnVal;
        } else {
            //Extrema-calculation per block.
            //Return the min and max value per double-block
            $resolution = $resolution / 2;
            $numberOfDataPointsPerBlock = floor($numberOfFoundDataPoints / $resolution);
            $outputArray = array_slice($returnValue, $numberOfFoundDataPoints - ($numberOfDataPointsPerBlock * $resolution));
            for ($i = 1; $i <= $resolution; $i++) {
                $min = $outputArray[($i)*$numberOfDataPointsPerBlock];
                $max = $outputArray[($i)*$numberOfDataPointsPerBlock];
                for ($i1 = 1; $i1 <= $numberOfDataPointsPerBlock; $i1++) {
                    $min = min($min, $outputArray[$i1 + ($i-1)*$numberOfDataPointsPerBlock]);
                    $max = max($max, $outputArray[$i1 + ($i-1)*$numberOfDataPointsPerBlock]);
                }
                $returnVal[] = getRoundedValue($min);
                $returnVal[] = getRoundedValue($max);
            }
            return $returnVal;
        }
    } else {
        die($error);
    }
}

/**
 * Outputs the last row of db as assoc-array with TIME, sensornames-Raw as keys
 */
function sendLastDataPointAsJson() {
    echo(json_encode(getLastRowOfDatapointsAsArray()));
}

/**
 * Returns the last datapoint of given sensor
 * TODO: what if this sensor has no valid last datapoint but every other?
 */
function getLastDataPoint($sensorNameRaw) {
    $row = getLastRowOfDatapointsAsArray();
    return $row[$sensorNameRaw];
}

/**
 * Returns last line of db as assoc array
 */
function getLastRowOfDatapointsAsArray() {
    //$database = new SQLiteDatabase(SQLITENAME, 0666, $error);
    $database = new PDO('sqlite:'.SQLITENAME.'.sqlite3');
    // Set errormode to exceptions
    $database->setAttribute(PDO::ATTR_ERRMODE, 
                            PDO::ERRMODE_EXCEPTION);
							
    $query = 'SELECT * FROM sensorvalues ORDER BY TIME DESC LIMIT 1;';

    if ($result = $database->query($query)) {
        if ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            return $row;
        }
    } else {
        die($error);
    }
}

/**
 * Returns "OK" and closes connection
 */
function closeConnection() {
    echo("OK");
    exit;
}

/**
 * Prints the standard website incl. Chart.js graph of data
 */
function printStandardWebsite() {
    ?>
    <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
    <html>
        <head>
            <!--Let browser know website is optimized for mobile-->
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

            <!--Import materialize.css-->
            <link type="text/css" rel="stylesheet" href="css/materialize.min.css"  media="screen,projection"/>

            <title><?php global $TITLE; echo($TITLE); ?></title>
            <!--
            <script type="text/JavaScript">function timedRefresh(zeit) {setTimeout("location.reload(true);",zeit);}</script>
            -->

            <!--Import jQuery before materialize.js-->
            <script type="text/javascript" src="js/jquery-2.1.4.min.js"></script>
            <script type="text/javascript" src="js/materialize.min.js"></script>
            <script type="text/javascript" src="js/Chart.min.js"></script>
            <script type="text/javascript">
                var myLineChart;
                //Reload data every 30 seconds, so we need a timer object
                var preLoadTimer;

                /**
                 * Function to make 0 to 90 degree temperature to
                 * a color
                 * Sauce: http://jsfiddle.net/bcronin/kGqbR/18/
                 */
                var F = function (t)
                {
                    // Map the temperature to a 0-1 range
                    var a = (t + 0) / 90;
                    a = (a < 0) ? 0 : ((a > 1) ? 1 : a);

                    // Scrunch the green/cyan range in the middle
                    var sign = (a < .5) ? -1 : 1;
                    a = sign * Math.pow(2 * Math.abs(a - .5), .35) / 2 + .5;

                    // Linear interpolation between the cold and hot
                    var h0 = 259;
                    var h1 = 12;
                    var h = (h0) * (1 - a) + (h1) * (a);

                    return pusher.color("hsv", h, 75, 90).hex6();
                };

                Chart.defaults.global = {
                    // Boolean - Whether to animate the chart
                    animation: true,
                    // Number - Number of animation steps
                    animationSteps: 30,
                    // String - Animation easing effect
                    // Possible effects are:
                    // [easeInOutQuart, linear, easeOutBounce, easeInBack, easeInOutQuad,
                    //  easeOutQuart, easeOutQuad, easeInOutBounce, easeOutSine, easeInOutCubic,
                    //  easeInExpo, easeInOutBack, easeInCirc, easeInOutElastic, easeOutBack,
                    //  easeInQuad, easeInOutExpo, easeInQuart, easeOutQuint, easeInOutCirc,
                    //  easeInSine, easeOutExpo, easeOutCirc, easeOutCubic, easeInQuint,
                    //  easeInElastic, easeInOutSine, easeInOutQuint, easeInBounce,
                    //  easeOutElastic, easeInCubic]
                    animationEasing: "easeOutQuart",
                    // Boolean - If we should show the scale at all
                    showScale: true,
                    // Boolean - If we want to override with a hard coded scale
                    scaleOverride: false,
                    // ** Required if scaleOverride is true **
                    // Number - The number of steps in a hard coded scale
                    scaleSteps: null,
                    // Number - The value jump in the hard coded scale
                    scaleStepWidth: null,
                    // Number - The scale starting value
                    scaleStartValue: null,
                    // String - Colour of the scale line
                    scaleLineColor: "rgba(0,0,0,.1)",
                    // Number - Pixel width of the scale line
                    scaleLineWidth: 1,
                    // Boolean - Whether to show labels on the scale
                    scaleShowLabels: true,
                    // Interpolated JS string - can access value
                    scaleLabel: "<%=value%>",
                    // Boolean - Whether the scale should stick to integers, not floats even if drawing space is there
                    scaleIntegersOnly: true,
                    // Boolean - Whether the scale should start at zero, or an order of magnitude down from the lowest value
                    scaleBeginAtZero: false,
                    // String - Scale label font declaration for the scale label
                    scaleFontFamily: "'Helvetica Neue', 'Helvetica', 'Arial', sans-serif",
                    // Number - Scale label font size in pixels
                    scaleFontSize: 12,
                    // String - Scale label font weight style
                    scaleFontStyle: "normal",
                    // String - Scale label font colour
                    scaleFontColor: "#666",
                    // Boolean - whether or not the chart should be responsive and resize when the browser does.
                    responsive: true,
                    // Boolean - whether to maintain the starting aspect ratio or not when responsive, if set to false, will take up entire container
                    maintainAspectRatio: true,
                    // Boolean - Determines whether to draw tooltips on the canvas or not
                    showTooltips: true,
                    // Function - Determines whether to execute the customTooltips function instead of drawing the built in tooltips (See [Advanced - External Tooltips](#advanced-usage-custom-tooltips))
                    customTooltips: false,
                    // Array - Array of string names to attach tooltip events
                    tooltipEvents: ["mousemove", "touchstart", "touchmove"],
                    // String - Tooltip background colour
                    tooltipFillColor: "rgba(0,0,0,0.8)",
                    // String - Tooltip label font declaration for the scale label
                    tooltipFontFamily: "'Helvetica Neue', 'Helvetica', 'Arial', sans-serif",
                    // Number - Tooltip label font size in pixels
                    tooltipFontSize: 14,
                    // String - Tooltip font weight style
                    tooltipFontStyle: "normal",
                    // String - Tooltip label font colour
                    tooltipFontColor: "#fff",
                    // String - Tooltip title font declaration for the scale label
                    tooltipTitleFontFamily: "'Helvetica Neue', 'Helvetica', 'Arial', sans-serif",
                    // Number - Tooltip title font size in pixels
                    tooltipTitleFontSize: 14,
                    // String - Tooltip title font weight style
                    tooltipTitleFontStyle: "bold",
                    // String - Tooltip title font colour
                    tooltipTitleFontColor: "#fff",
                    // Number - pixel width of padding around tooltip text
                    tooltipYPadding: 6,
                    // Number - pixel width of padding around tooltip text
                    tooltipXPadding: 6,
                    // Number - Size of the caret on the tooltip
                    tooltipCaretSize: 8,
                    // Number - Pixel radius of the tooltip border
                    tooltipCornerRadius: 6,
                    // Number - Pixel offset from point x to tooltip edge
                    tooltipXOffset: 10,
                    // String - Template string for single tooltips
                    tooltipTemplate: "<%if (label){%><%=label%>: <%}%><%= value %>",
                    // String - Template string for multiple tooltips
                    multiTooltipTemplate: "<%=datasetLabel%> - <%= value %>",
                    //Number - amount extra to add to the radius to cater for hit detection outside the drawn point
                    pointHitDetectionRadius: 3,
                    // Function - Will fire on animation progression.
                    onAnimationProgress: function () {
                    },
                    // Function - Will fire on animation completion.
                    onAnimationComplete: function () {
                    }
                }

                //Demo dataset to initialize the chart
                var dataSample = {
                    labels: ["DEMO", "DEMO", "DEMO", "DEMO", "DEMO", "DEMO", "DEMO"],
                    datasets: [
                        {
                            label: "Empty dataset",
                            fillColor: "rgba(220,220,220,0.2)",
                            strokeColor: "rgba(220,220,220,1)",
                            pointColor: "rgba(220,220,220,1)",
                            pointStrokeColor: "#fff",
                            pointHighlightFill: "#fff",
                            pointHighlightStroke: "rgba(220,220,220,1)",
                            data: [0, 0, 0, 0, 0, 0, 0]
                        }
                    ]

                };

                /**
                 * Ensure that at least one sensor is chosen to be displayed
                 * And return the list of chosen sensors
                 * */
                function getSelectedSensors() {
                    var inputList = $("#namesList :input");
                    var index = 0;
                    var sensorNameRawArray = [];
                    inputList.each(function () {
                        if ($(this).is(':checked')) {
                            sensorNameRawArray[index] = $(this).attr('id');
                            index++;
                        }
                    });
                    //No chosen sensor found --> select the first one and return its id
                    if (index < 1) {
                        inputList.first().prop('checked', true);
                        sensorNameRawArray[0] = inputList.first().attr('id');
                    }
                    return sensorNameRawArray;
                }


                /**
                 * Fetch data for all sensors
                 * After getting the new data update the graph
                 * @param resolution number of datapoint you want to display
                 * */
                function updateDataForAllSensors(resolution) {
                    //Get selected button
                    nameOfPressedButton = $(".MenuButton.active").attr("id");
                    //console.log("Name of Button:" + nameOfPressedButton);
                    var timeToDisplay = 3600;//in Seconds
                    switch (nameOfPressedButton) {
                        case "hourButton":
                            timeToDisplay = 3600;
                            break;
                        case "dayButton":
                            timeToDisplay = 3600 * 24;
                            break;
                        case "weekButton":
                            timeToDisplay = 3600 * 24 * 7;
                            break;
                        case "monthButton":
                            timeToDisplay = 3600 * 24 * 30;
                            break;
                        case "yearButton":
                            timeToDisplay = 3600 * 24 * 365;
                            break;
                    }

                    //Mode of data point calculation (AVG or EXTREMA)
                    var mode = "AVG"
                    if ($("#modeSwitch").is(':checked')) {
                        mode = "EXTREMA";
                    }


                    sensorNameRawArray = getSelectedSensors();
                    if (sensorNameRawArray.length < 1) {
                        //If No Sensor is selected, eject here
                        console.log("Eject because no sensor is selected.");
                        alert("No Sensor! Check your list of readable sensor names and db entries.");
                        return;
                    }
                    var getString = 'index.php?GETDATA=YES&SEKRET=<?php
    global $VIEWSEKRET;
    echo($VIEWSEKRET);
    ?>';
                    getString += '&TIMEDIFF=' + timeToDisplay + '&RES=' + resolution + '&MODE=' + mode;
                    getString += '&SENSOR=' + JSON.stringify(sensorNameRawArray);
                    //console.log(getString);
                    $.get(getString, null, function (data) {
                        //console.log(data);
                        myLineChart.destroy();
                        if (!data) {
                            //No datapoint available (possible sensor error)
                            console.log("No Data available to display.");
                            alert("No Data to display. Maybe Sensors are offline.");
                            //Exit update function becasue there are no data to display
                            return;
                        }
                        eval(data);//Set new value for dataReal (passed from php-script)
                        var ctx = $("#myChart").get(0).getContext("2d");
                        myLineChart = new Chart(ctx).Line(dataReal, Chart.defaults.global);
                    });
                    restartTimer();
                }


                /**
                 * Restart the reload timer. Is called if mouse is moved or data is updated
                 * */
                function restartTimer() {
                    clearTimeout(preLoadTimer);
                    preLoadTimer = setTimeout("updateDataForAllSensors(<?php global $DefaultResolution;
    echo($DefaultResolution);
    ?>)", <?php global $DefaultReloadTime;
    echo($DefaultReloadTime); ?>);
                }

                $(document).ready(function () {
                    // Get context with jQuery - using jQuery's .get() method.
                    var ctx = $("#myChart").get(0).getContext("2d");

                    //Fill chart with demo data
                    myLineChart = new Chart(ctx).Line(dataSample, Chart.defaults.global);

                    //Add click handlers to buttons
                    $(".MenuButton").click(function (event) {
                        var pressedButton = jQuery(this);
                        $(".MenuButton").removeClass("active");
                        pressedButton.addClass("active");
                        updateDataForAllSensors(<?php
    global $DefaultResolution;
    echo($DefaultResolution);
    ?>);
                    });

                    $(".button-collapse").sideNav({
                        closeOnClick: true
                    });

                    $("#modeSwitch").click(function () {
                        updateDataForAllSensors(<?php
    global $DefaultResolution;
    echo($DefaultResolution);
    ?>);
                    });

                    $(".sensorCheckbox").click(function () {
                        updateDataForAllSensors(<?php
    global $DefaultResolution;
    echo($DefaultResolution);
    ?>);
                    });

                    updateDataForAllSensors(<?php
    global $DefaultResolution;
    echo($DefaultResolution);
    ?>);



                    //Reset Timer everytime mouse is moved
                    $(this).mousemove(function (e) {
                        restartTimer();
                    });
                    
                    //console.log("PHP-Timestamp is: <?php echo(getTime());?>");
                    //console.log("JS-TimeStamp is: ");
                });
            </script>

        </head>
        <body class="#e3f2fd blue lighten-5 home blog">
        <nav class="#bbdefb blue lighten-3" role="navigation">
            <div class="nav-wrapper container">
                <ul class="left hide-on-med-and-down">
                    <li class="active MenuButton" id="hourButton"><a href="#">Stunde</a></li>
                    <li class="MenuButton" id="dayButton"><a href="#">Tag</a></li>
                    <li class="MenuButton" id="weekButton"><a href="#">Woche</a></li>
                    <li class="MenuButton" id="monthButton"><a href="#">Monat</a></li>
                    <li class="MenuButton" id="yearButton"><a href="#">Jahr</a></li>
                </ul>
                <ul class="right hide-on-med-and-down">
                    <li >
                        <div class="switch">
                            <label>
                                AVG
                                <input type="checkbox" id="modeSwitch">
                                <span class="lever"></span>
                                EXTREMA
                            </label>
                        </div>
                    </li>
                </ul>

                <ul id="slide-out" class="side-nav">
                    <li class="active MenuButton" id="hourButton"><a href="#">Stunde</a></li>
                    <li class="MenuButton" id="dayButton"><a href="#">Tag</a></li>
                    <li class="MenuButton" id="weekButton"><a href="#">Woche</a></li>
                    <li class="MenuButton" id="monthButton"><a href="#">Monat</a></li>
                    <li class="MenuButton" id="yearButton"><a href="#">Jahr</a></li>
                </ul>
                <a href="#" data-activates="slide-out" class="button-collapse"><i class="mdi-navigation-menu"></i></a>
            </div>
        </nav>

        <div class="row">
            <div class="col s7 offset-s2">
                <canvas id="myChart" width="800" height="500"></canvas>
            </div>
            <div class="col s2 offset-s1" >
                <form action="#" id="namesList">
                    <?php
                    global $sensorNames;
                    foreach ($sensorNames as $key => $value) {
                        ?>
                        <p>
                            <input class="sensorCheckbox" type="checkbox" id="<?php echo($key); ?>" name="<?php echo($value); ?>" 
                            <?php
                            $checkFlag=false;
                           if (!$checkFlag) {
                               $checkFlag = true;
                               echo('checked="checked"');
                           }
                        ?>/>
                            <label for="<?php echo($key); ?>"><?php echo($value); ?></label>
                        </p>

                    <?php
                }
                ?>
                </form>
                <?php
                global $listOfUnknownSensors;
                if (count($listOfUnknownSensors) >= 1) {

                    echo("<div>Unkown sensors found:</div>");
                    foreach ($listOfUnknownSensors as $key => $value) {
                        echo("<p>" . $value . "</p>");
                    }
                }
                ?>
            </div>
        </div>
        <footer class="page-footer blue lighten-3">
            <div class="footer-copyright blue lighten-3">
				<div class="container">
				<a class="grey-text text-lighten-3 left" href="index.php?SEKRET=<?php
				global $VIEWSEKRET;
				echo($VIEWSEKRET);
				 ?>&SIMPLE=YES">Mobile Website</a>
                <a class="grey-text text-lighten-3 right" href="https://github.com/ChristophBodenstein/sensorwatch" target="_blank">Source-Code</a>
				</div>
            </div>
        </footer>


    </body>
    </html>

    <?php
}

/**
 * Prints a simple html page with all current sensor values.
 * For those who don`t like JS
 */
function printSimplePage() {
    //$database = new SQLiteDatabase(SQLITENAME, 0666, $error);
    $database = new PDO('sqlite:'.SQLITENAME.'.sqlite3');
    // Set errormode to exceptions
    $database->setAttribute(PDO::ATTR_ERRMODE, 
                            PDO::ERRMODE_EXCEPTION);
							
    $query = "SELECT * FROM sensorvalues ORDER BY TIME DESC LIMIT 1";
    global $row;
    if ($result = $database->query($query)) {
        $row = $result->fetch();
    }
    ?>
    <html>
        <head>
            <!--Let browser know website is optimized for mobile-->
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes"/>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

            <!--Import materialize.css-->
            <link type="text/css" rel="stylesheet" href="css/materialize.min.css"  media="screen,projection"/>
            <title><?php global $TITLE; echo($TITLE); ?></title>

            <script type="text/JavaScript">setTimeout("location.reload(true);",<?php
    global $DefaultReloadTime;
    echo($DefaultReloadTime);
    ?>);</script>

        </head>
        <body class="#e3f2fd blue lighten-5 home blog">
            <div class="container">
                <?php
                global $sensorNames;
                $lastRow = getLastRowOfDatapointsAsArray();
				if($lastRow==NULL){
                    ?>
                    <div
                        style="text-align: center; font-weight: bold; text-decoration: underline; font-size: large; color: red">
						No data available!
                    </div>
                            <?php
					exit;}
                if ($lastRow['TIME'] <= (getTime() - 300)) {
                    ?>
                    <div
                        style="text-align: center; font-weight: bold; text-decoration: underline; font-size: large; color: red">
                        OLD DATA! Please restart device!
                    </div>
                            <?php
                        }
                        ?>
                <div>
                    <h2 class="header center">
    <?php echo(date("H:i", $lastRow['TIME'])); ?></h2>
                </div>

                <table class="hoverable #e3f2fd blue lighten-5">
                    <thead>
                       <!-- <tr>
                            <th data-field="id">Name</th>
                            <th data-field="name">Value</th>
                        </tr>
                        -->
                    </thead>
                    <tbody>

                        <?php
                        $firstLine = TRUE;
                        foreach ($lastRow as $key => $value) {
                            //Don`t print the first dataset, it only contains the time
                            if ($firstLine == TRUE) {
                                $firstLine = FALSE;
                                continue;
                            }
							
                            if ($sensorNames[$key]) {
                                $name = $sensorNames[$key];
                            } else {
                                $name = $key;
                            }
                            
                            ?>
                            <tr>
                                <td><?php echo($name); ?></td>
                                <td><?php echo($value . "°C"); ?></td>
                            </tr>

        <?php
    }//End of Loop
    ?>
                    </tbody>
                </table>
            </div>
        <footer class="page-footer blue lighten-3">
            <div class="footer-copyright blue lighten-3">
				<div class="container">
				<a class="grey-text text-lighten-3 left" href="index.php?SEKRET=<?php
				global $VIEWSEKRET;
				echo($VIEWSEKRET);
				 ?>&SIMPLE=NO">Desktop Website</a>
                <a class="grey-text text-lighten-3 right" href="https://github.com/ChristophBodenstein/sensorwatch" target="_blank">Source-Code</a>
				</div>
            </div>
        </footer>
    </body>
    </html>
    <?php
}
/***  End of function definition/implementation ***/

/*** Begin of handling requests **/

/* Check for unkown sensors */
checkSensorNameList();


/*  Check for $_GET-Parameters and trigger the corresponding actions */

/*
 * If secret is $ADDSEKRET, then we add the data from GET-Parameters into sensor-db
 * This is used by arduino to push sensor values
 */
if(isset($_GET['SEKRET'])){
if ($_GET['SEKRET'] == $ADDSEKRET) {
    debugEcho("Will add data");
    addAllSensorValuesFromGET();
    closeConnection();
}
}

/*
 * TBC - Remove this test if you want to show the site to everybody
 * If given Viewsekret is not correct --> connection will be closed
 * VIEWSEKRET is case sensitive at the moment
 * All following actions are only possible if VIEWSEKRET was correct!
 *
 */
if(isset($_GET['SEKRET'])){
	if ($_GET['SEKRET'] != $VIEWSEKRET) {
    //If ViewSekret is not set correctly don`t show anything and Exit here!
    closeConnection();
	}
}else{closeConnection();}

/*
 * Return the dataset for Chart.js
 */
if(isset($_GET['GETDATA'])){
	if (strtoupper($_GET['GETDATA']) == 'YES') {
	    $timeDiff = strtoupper($_GET['TIMEDIFF']);
	    $resolution = strtoupper($_GET['RES']);
	    $mode = strtoupper($_GET['MODE']);
	    $arrayOfSensorNamesRaw = json_decode($_GET['SENSOR']);
	    $endTime = getTime();
	    $startTime = $endTime - $timeDiff;
	    echoDatasetForChart($arrayOfSensorNamesRaw, $startTime, $endTime, $resolution, $mode);
	    exit();
	}
}


/*
 * Init db if sql file does not exist or init is requested
 * Probably this call is not needed becasue the sqlite-file is automatically
 * created if it does not exist.
 */
if(isset($_GET['INIT'])){
	if ((($_GET['INIT'] == 'YES') && ($_GET['SEKRET'] == $VIEWSEKRET))) {
	echo("INIT the DB.");
    initDB();
    debugEcho("init of DB");
}
}
/*
 * Print simple page for mobile use if requested,
 * else print normal page with chart.js etc.
 */
if(isset($_GET['SIMPLE'])){
	if (strtoupper($_GET['SIMPLE']) == 'YES') {
    printSimplePage();
	exit;
	}
}
printStandardWebsite();

exit;

?>
