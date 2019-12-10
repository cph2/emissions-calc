<?php
/**
 *
 * Created by Cheryl Handsaker
 * For Zilkha Center mileage calculations
 * Updated by Sam Gilman (2019)
 * ***
 *
 * Distance calculator grabbed from https://stackoverflow.com/questions/29003118/get-driving-distance-between-two-points-using-google-maps-api
 * @param $lat1
 * @param $lon1
 * @param $lat2
 * @param $lon2
 * @return float|int
 */
function getDistanceBetweenPoints($lat1, $lon1, $lat2, $lon2) {
    $theta = $lon1 - $lon2;
    $miles = (sin(deg2rad($lat1)) * sin(deg2rad($lat2))) + (cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta)));
    $miles = acos($miles);
    $miles = rad2deg($miles);
    $miles = $miles * 60 * 1.1515;
    return round($miles);
}

function calculateMileage ($amt, $from, $to, $source) {

    $mileageMultiplier = 1;

    if ($amt > 0) {
        $mileageMultiplier = 1;
    } else if ($amt > -100) {
        $mileageMultiplier = 0;
    }  else if ($amt <= -100) {
        $mileageMultiplier = -1;
    }

    //Get the latitude and longitude for the origin and destination
    $fromLat = $source[$from]['lat'];
    $fromLon = $source[$from]['lng'];
    $toLat = $source[$to]['lat'];
    $toLon = $source[$to]['lng'];

    //Do the distance calculation
    return (getDistanceBetweenPoints($fromLat, $fromLon, $toLat, $toLon) * $mileageMultiplier);
}

/**
 *  Based on @link http://gist.github.com/385876
 *  Uses the first column as the index of the associative array
 * @param string $filename
 * @param string $delimiter
 * @return array|bool
 */

function csv_to_array($filename='', $delimiter=',')
{
    if(!file_exists($filename) || !is_readable($filename))
        return FALSE;

    $header = NULL;
    $data = array();
    if (($handle = fopen($filename, 'r')) !== FALSE)
    {
        while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE)
        {
            if (!$header)
                $header = $row;
            else {
                    $code = $row[0];
                    $data[$code] = array_combine($header, $row);
                }
        }
        fclose($handle);
    }
    return $data;
}



/**
 *
 * Airport data from http://ourairports.com/data/
 * Amtrak data converted from geojson found here: https://data.world/albert/amtrak
 *
**/
$mileageOutput = fopen("output/mileage.csv", "w") or die("Unable to open mileage file!");
$errorOutput = fopen("output/unprocessed.csv", "w") or die("Unable to open error file!");
$manualOutput = fopen("output/manualreview.csv", "w") or die("Unable to open error file!");
$processedOutput = fopen("output/processed.csv", "w") or die("Unable to open error file!");
$travels = fopen('input/travel.csv', 'r');
$airports = csv_to_array('airports.csv');
$amtrak = csv_to_array('amtrak.csv');
$to = '';
$from = '';
$dept = '';
$merchant = '';
$totalAir = 0.0;
$totalTrain = 0.0;
$lineNum = 0;
$errorLines = 0;
$manualLines = 0;
$amt = 0;
$prevName = "";
$prevTo = "";
$prevFrom = "";
$prevAmount = 0;
$prevMerchant = "";
$mileage = 0;
$statusCode = "U";


// Process each line of the provided file
while (($line = fgetcsv($travels)) !== FALSE) {

    //$line is an array of the csv elements

    //Process the header
    if ($lineNum == 0) {

        /* Check the column names against the CSV */
        if (array_search('City Of Origin', $line) === FALSE) {
            echo('Please replace the origin city code column name with "City Of Origin"
');
            exit;
        }

        if (array_search('City Of Dest', $line) === FALSE) {
            echo('Please replace the destination city code column name with "City Of Dest"
');
            exit;
        }

        if (array_search('Dept', $line) === FALSE) {
            echo('Please replace the department name column name with Dept
');
            exit;
        }

        if (array_search('Merchant', $line) === FALSE) {
            echo('Please replace the merchant id column name with Merchant
');
            exit;
        }

        if (array_search('Amount', $line) === FALSE) {
            echo('Please replace the transaction amount column name with "Amount"
');
            exit;
        }

        if (array_search('Passenger Name', $line) === FALSE) {
            echo('Please replace the passenger name column name with "Passenger Name"
');
            exit;
        }

        // Get the indexes of the columns based on column name
        /*
        * THESE ITEMS MIGHT CHANGE, YOU SHOULD CHECK TO MAKE SURE THEY MATCH
        * IF NOT, CHANGE THE TEXT BELOW
        */
        $from = array_search('City Of Origin', $line);
        $to = array_search('City Of Dest', $line);
        $dept = array_search('Dept', $line);
        $merchant = array_search('Merchant', $line);
        $amt = array_search('Amount', $line);
        $name = array_search('Passenger Name', $line);

        // Count the line numbers to make it easy to check errors
        $lineNum = 1;

        // Set up the headers in the output and error files
        $outputHeader = "Dept,Passenger,Origin,Destination,Mileage,Amount\n";
        fwrite($mileageOutput, $outputHeader);
        $errorHeader = "line, dept,passenger,origin,destination,amount\n";
        fwrite($errorOutput, $errorHeader);
        $manualHeader = "line,dept,passenger,origin,destination,amount\n";
        fwrite($manualOutput, $manualHeader);
        $processedHeader = "";
        foreach ($line as $linefield) {
            $processedHeader .= ($linefield . ",");
        }
        $processedHeader .= "mileage, process-code\n";
        fwrite($processedOutput, $processedHeader);


    } else {  // Process each line in the file

        //Increment the line number
        $lineNum = $lineNum + 1;

        // Assume it is air travel unless AMTRAK is specified
        $mileageSource = $airports;
        if (strpos($line[$merchant], 'AMTRAK') !== false) {
            $mileageSource = $amtrak;
        }

        // Confirm that we have an origin and destination code for lookup
        if (array_key_exists($line[$from], $mileageSource) && array_key_exists($line[$to], $mileageSource)) {

            // Make sure that the origin and destination are not the same
            if ($line[$from] != $line[$to]) {

                // Identify lines for manual inspection
                if (($line[$name] == $prevName) && ($line[$from] == $prevFrom) && ($line[$to] == $prevTo) && ($merchant == $prevMerchant) && ($line[$amt] != $prevAmount)  && ($mileageSource != $amtrak)) {
                    if ($line[$amt] > 100) { //Should be checked manually
                        $manualLines = $manualLines + 1;
                        $outputLine = $lineNum . ',' . str_replace(",", " ", $line[$dept]) . ',' . $line[$name] . ',' . $line[$from] . ',' . $line[$to] . ',$' . $line[$amt] . "\n";
                        fwrite($manualOutput, $outputLine);
                        $statusCode = "M";
                    } else if (($line[$amt] <= 100) && ($line[$amt] >= -100)){  //Assume these are travel incidentals
                        $errorLines = $errorLines + 1;
                        $outputLine = $lineNum . ',' . str_replace(",", " ", $line[$dept]) . ',' . $line[$name] . ',' . $line[$from] . ',' . $line[$to] . ',$' . $line[$amt] . "\n";
                        fwrite($errorOutput, $outputLine);
                        $statusCode = "I";
                    } else if ($line[$amt] < -100) { //Assume these are refunds
                        $tripMileage = calculateMileage($line[$amt], $line[$from], $line[$to], $mileageSource);

                        // Save the mileage to write to the processing file
                        $mileage = $tripMileage;
                        $statusCode = "P";

                        //Write the record
                        $outputTrip = str_replace(",", " ", $line[$dept]) . ", " . $line[$name] . ", " . $line[$from] . ", " . $line[$to] . ", " . $tripMileage . ", $" . $line[$amt] . "\n";
                        fwrite($mileageOutput, $outputTrip);

                        // Update the total mileage
                        if ($mileageSource == $amtrak) {
                            $totalTrain = $totalTrain + $tripMileage;
                        } else {
                            $totalAir = $totalAir + $tripMileage;
                        }
                    }

                } else { // Calculate the mileage

                    if ($line[$amt] > 0) {

                        $tripMileage = calculateMileage($line[$amt], $line[$from], $line[$to], $mileageSource);

                        // Save the mileage to write to the processing file
                        $mileage = $tripMileage;
                        $statusCode = "P";

                        //Write the record
                        $outputTrip = str_replace(",", " ", $line[$dept]) . ", " . $line[$name] . ", " . $line[$from] . ", " . $line[$to] . ", " . $tripMileage . ", $" . $line[$amt] . "\n";
                        fwrite($mileageOutput, $outputTrip);

                        // Update the total mileage
                        if ($mileageSource == $amtrak) {
                            $totalTrain = $totalTrain + $tripMileage;
                        } else {
                            $totalAir = $totalAir + $tripMileage;
                        }
                    }
                    else {
                        $statusCode = "C"; //Assume credit for travel not taken
                    }
                }
            } else {
                $statusCode = "N"; //Source and destination are the same, no mileage calculated.
            }
        } else {
            // Remove fees and other similar things from data
            /*
            * THIS MAY NEED TO BE UPDATED
            * IF A LOT OF UNPROCESSED LINES ARE OCCURING AND THEY SEEM TO BE FEES/SOMETHING SIMILAR THEN:
            *     ADD SOMETHING TO THE IF STATEMENT IN THE SAME FORMAT AS BELOW
            */
            if (($line[$from] != "XAA") && ($line[$to] != "XAO") && ($line[$to] != "FEE") && ($line[$from] != "FEE")) {

            // This is badly formatted or needs a lookup so write it to the file
            $errorLines = $errorLines + 1;
            $outputLine = $lineNum . ',' . str_replace(",", " ", $line[$dept]) . ',' . $line[$name] . ',' . $line[$from] . ',' . $line[$to] . ',$' . $line[$amt] . "\n";
            fwrite($errorOutput, $outputLine);

            $statusCode = "E"; // Origin and/or destination missing.

            } else {
               $statusCode = "X"; // One of the fake codes entered for fees and incidentals.
            }

        }

        // Write out the processed record
        $procLine = "";
        foreach ($line as $linefield) {
            $procLine .= (str_replace(",", " ", $linefield) . ",");
        }
        $procLine .= $mileage. ",". $statusCode ."\n";
        fwrite($processedOutput, $procLine);


        $prevName = $line[$name];
        $prevAmount = $line[$amt];
        $prevFrom = $line[$from];
        $prevTo = $line[$to];
        $prevMerchant = $merchant;
        $mileage = 0;
        $statusCode = 'U';
    }
}

//Write the total mileage to the bottom of the mileage file
    $outputTotal = "Results:\nAir Miles: " . $totalAir . "\nTrain Miles: " . $totalTrain . "\n";

// If there are error lines, write the total out to the bottom of the mileage file
    if ($errorLines > 0) {
        $outputTotal .= $errorLines . " Unprocessed Lines\n";
    }

// If there are error lines, write the total out to the bottom of the mileage file
    if ($manualLines > 0) {
        $outputTotal .= $manualLines . " Manual Review Lines\n";
    }


    echo $outputTotal;
    fwrite($mileageOutput, $outputTotal);

// Close all the files
    fclose($travels);
    fclose($mileageOutput);
    fclose($errorOutput);
    fclose($manualOutput);
