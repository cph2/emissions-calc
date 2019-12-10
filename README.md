# emissions-calc
Calculates the distance traveled based on amtrac and airport lookup tables, included. 

Both detailed output and error output are in the output directory. 

## Developer details
Repository: https://github.com/williamscollege/emissions-calc

Script location: Run locally 

Developers: Cheryl Handsaker (2018), Updated by Sam Gilman (2019)

## Stakeholders
Zilkha Center

# Instructions
## Input
Replace the file travel.csv in the /input folder with your file.
Maggie Koperniak is the source for the data.
Data includes baggage fees marked with "XAO", "XAA", and "FEE".

__IMPORTANT:__ Data should be sorted in the following order:
1. Passenger Name ASC then by
2. Merchant (ASC) then by
3. Origin City (ASC) then by
4. Desination City (ASC) then by
5. Amount (__DESC__)

## Execute
Run on the command line "php index.php"

Produces a summary of the mileage, broken out by train and airline travel.

#Output
There are four detailed files produced in the /output directory

**processed.csv** is the file that outputs each line in the input file with a status code indicating how it was interpreted.
- __P__ - Processed and included in the mileage calculation
- __C__ - Credit without a corresponding charge. No mileage is added or subtracted.
- __N__ - No miles calculated. This indicates the origin and destination city are the same.
- __I__ - Incidentals. This line assumes these charges are for baggage fees, seat upgrades and meals.
- __M__ - Manual review needed.
- __X__ - Coded incidenentals using "XAO", "XAA", and "FEE".
- __E__ - Error indicates that one of the fields required for processing is missing. Typically this is the origin or destination city.
- __U__ - Unused. Indicated this line was somehow missed in the processing.

**manualreview.csv** is a file for items that require human review in context. 

**unprocessed.csv** is a file of the errors encountered, such as missing origin or destination city. 
 
**mileage.csv** is a file of the rows that go into the calculated mileage.


