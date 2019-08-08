<?php
/**
 *
 * Created by Cheryl Handsaker
 * For Zilkha Center mileage calculations
 *
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
$travels = fopen('input/travel.csv', 'r');
$airports = csv_to_array('airports.csv');
$amtrak = csv_to_array('amtrak.csv');
$to = '';
$from = '';
$dept = '';
$id = '';
$total = 0.0;
$lineNum = 0;
$errorLines = 0;

// Process each line of the provided file
while (($line = fgetcsv($travels)) !== FALSE) {

    //$line is an array of the csv elements

    //Process the header
    if ($lineNum == 0) {

        // Get the indexes of the columns based on column name
        $from = array_search('MC_CITYOFORIGIN', $line);
        $to = array_search('MC_CITYOFDEST', $line);
        $dept = array_search('Dept', $line);
        $id = array_search('EMPLID', $line);
        $merchant = array_search('MERCHANT', $line);
        $org = array_search('ORIGINCITY', $line);
        $dest = array_search('DESTINATION', $line);

        // Count the line numbers to make it easy to check errors
        $lineNum = 1;

        // Set up the headers in the output and error files
        $outputHeader = "Dept,Trip Id,Mileage\n";
        fwrite($mileageOutput, $outputHeader);
        $errorHeader = "line,id,origin,destination\n";
        fwrite($errorOutput, $errorHeader);

    } else {

        // Process each line in the file

        //Increment the line number
        $lineNum = $lineNum+1;

        // Assume it is aire travel unless AMTRAK is specified
        $mileageSource = $airports;
        if (strpos( $line[$merchant], 'AMTRAK' ) !== false) {
            $mileageSource = $amtrak;
        }

        // Confirm that we have an origin and destination code for lookup
        if (array_key_exists($line[$from], $mileageSource) && array_key_exists($line[$to], $mileageSource)) {

            // Make sure that the origin and destination are not the same
            if ($line[$from] != $line[$to]) {

                //Get the latitude and longitude for the origin and destination
                $fromLat = $mileageSource[$line[$from]]['lat'];
                $fromLon = $mileageSource[$line[$from]]['lng'];
                $toLat = $mileageSource[$line[$to]]['lat'];
                $toLon = $mileageSource[$line[$to]]['lng'];

                //Do the distance calculation
                $tripMileage = getDistanceBetweenPoints($fromLat, $fromLon, $toLat, $toLon);

                //Write the record
                $outputTrip = $line[$dept] . ", " . $line[$id] . ", " . $line[$from] . ", " . $line[$to] . ", " .$tripMileage . "\n";
                fwrite($mileageOutput, $outputTrip);

                // Update the total mileage
                $total = $total + $tripMileage;
            }
        }

        else {
            // We have the same source and destination, is it valid?
            if (($line[$org] != 'n/a') && ($line[$org] != ' ')) {

                // This is badly formatted or needs a lookup so write it to the file
                $errorLines = $errorLines + 1;
                $outputLine = $lineNum . ',' . $line[$id] . ',' . $line[$from] . ',' . $line[$to] . ',' . $line[$org] . ',' . $line[$dest] . "\n";
                fwrite($errorOutput, $outputLine);
            }
        }
    }
}

//Write the total mileage to the bottom of the mileage file
$outputTotal = "\n\n\nTotal Miles: " . $total . "\n";

// If there are error lines, write the total out to the bottom of the mileage file
if ($errorLines > 0) {
    $outputTotal .= $errorLines . " Unprocessed Lines\n";
}

echo $outputTotal;
fwrite($mileageOutput, $outputTotal);

// Close all the files
fclose($travels);
fclose($mileageOutput);
fclose($errorOutput);

