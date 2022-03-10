<?php
try
{
    // included for debugging purposes - should be removed in any production code
    ini_set('display_errors', 1);
    date_default_timezone_set("UTC");

    // include the mongodb library
    require 'vendor/autoload.php';



    // connect to the database. replace "mongo-example.eur_usd" with where you imported the sample dataset
    //$manager = new MongoDB\Driver\Manager("mongodb://localhost:27017");
    
    $dsn = "mongodb://user:pass@127.0.0.1/db";
    if (file_exists("config-local.php")){
        require "config-local.php";
    }

    $manager = new MongoDB\Driver\Manager($dsn);
    $collection = new MongoDB\Collection($manager, "mongo-example.eur_usd");



    // set some constants that are used further in the code
    $limit = 1000;
    $timeField = 'date';
    $valueField = 'rate';



    // create the filter conditions. in real life application if you need to add any additional filters,
    // you should modify the $filter array, for example:
    // $filter['category'] = $_REQUEST["category"];
    $filter = [];
    if (isset($_GET["from"]) && $_GET["from"]) {
        if (!isset($filter[$timeField])) $filter[$timeField] = [];
        $filter[$timeField]['$gte'] = new MongoDB\BSON\UTCDateTime($_GET["from"]);
    }
    if (isset($_GET["to"]) && $_GET["to"]) {
        if (!isset($filter[$timeField])) $filter[$timeField] = [];
        $filter[$timeField]['$lt'] = new MongoDB\BSON\UTCDateTime($_GET["to"]);
    }



    // the chart always requests the data in specific time units
    // this switch statement will select the correct MongoDB format string for the aggregation.
    switch ($_GET["unit"])
    {
        case "ms": $dateFormat = "%Y-%m-%dT%H:%M:%S.%LZ"; break;
        case "s": $dateFormat = "%Y-%m-%dT%H:%M:%S.000Z"; break;
        case "m": $dateFormat = "%Y-%m-%dT%H:%M:00.000Z"; break;
        case "h": $dateFormat = "%Y-%m-%dT%H:00:00.000Z"; break;
        case "d": $dateFormat = "%Y-%m-%dT00:00:00.000Z"; break;
        case "M": $dateFormat = "%Y-%m-01T00:00:00.000Z"; break;
        case "y": $dateFormat = "%Y-01-01T00:00:00.000Z"; break;
        default: throw new Exception("Incorrect 'unit' specified in the URL.");
    }



    // execute the aggregation query against the MongoDB database.
    $queryResult = $collection->aggregate([
        [ '$match' => (object)$filter ],
        // this sort is required for the `first` and `last` aggregations
        [ '$sort' => [ $timeField => 1 ] ],
        [ '$group' => [
            // group by the previously calculated date unit. Note the '$date' part - this points to the
            // field that stores the timestamp in the database document.
            '_id' => [ '$dateToString' => [ 'format' => $dateFormat, 'date' => '$'. $timeField ] ],

            // multiple aggregated values can be calculated at once
            'first' => [ '$first' => '$' . $valueField ],
            'last' => [ '$last' => '$' . $valueField ],
            'min' => [ '$min' => '$' . $valueField ],
            'max' => [ '$max' => '$' . $valueField ],

            // see the following link on why `count` is recommended when using `avg` aggregation.
            // https://zoomcharts.com/developers/en/time-chart/api-reference/settings/chartTypes/candlestick/data.html#countIndex
            'count' => [ '$sum' => 1 ],
            'sum' => [ '$sum' => '$' . $valueField ],
        ] ],
        [ '$sort' => [ '_id' => 1 ] ],
        // the chart will issue multiple requests to load the rest of the data if needed
        [ '$limit' => $limit ]
    ]);



    // $from and $to are used to inform the chart about the data range this response covers
    // while in many scenarios it matches the timestamps of the first and last value, they 
    // might be different if it is known that there are empty time ranges either at the beginning
    // or the end.
    $from = (isset($_GET["from"]) && $_GET["from"]) ? (double)$_GET["from"] : null;
    $to = null;



    // now build the data in format that is supported by TimeChart
    $chartValues = [];
    $count = 0;
    foreach ($queryResult as $row) {
        // unfortunately PHP does not support retrieving timestamps with millisecond precision
        // so this workaround is used.
        $phpTime = DateTime::createFromFormat('Y-m-d\TH:i:s.uO', $row->_id);
        $jsTime = (double)($phpTime->getTimestamp() . substr($phpTime->format("u"), 0, 3));

        $chartValues[] = [ $jsTime, $row->min, $row->max, $row->first, $row->last, $row->sum, $row->count];

        if ($from === null) $from = $jsTime;
        $to = $jsTime + 1;
        $count++;
    }
    
    // the `to` value is only updated to the request value if the query returned less than the
    // maximum number of rows.
    if ($count < $limit && isset($_GET["to"]) && $_GET["to"]) {
        $to = (double)$_GET["to"];
    }
    
    $chartResult = [
        'from' => $from,
        'to' => $to,
        'unit' => $_GET["unit"],
        'values' => $chartValues
    ];



    // the chart also needs the `dataLimitFrom` and `dataLimitTo` fields to be filled so that it
    // knows what range the user is allowed to scroll the chart to.
    // these fields can often be hard coded or cached instead of querying them from the database.
    // for example, you might know that the data will always be available for the range 
    // 2013.01.01-today. 
    // in this case, the data limit is only returned for the initial query (that does not include
    // the time range) since it will not change for the following requests and the chart will cache
    // the original value.
    if (!isset($_GET["from"]) || !$_GET["from"]) {
        $dataLimitResult = $collection->aggregate([
            [ '$group' => [
                '_id' => 1,
                'min' => [ '$min' => '$' . $timeField ],
                'max' => [ '$max' => '$' . $timeField ]
            ]]
        ]);

        foreach ($dataLimitResult as $row) {
            $chartResult["dataLimitFrom"] = (double)$row->min->__toString();
            $chartResult["dataLimitTo"] = (double)$row->max->__toString();
        }
    }



    // depending on the data, different caching policies could be applied.
    // this is a demonstration of few simple approaches that enables the browser to cache 
    // historical values.
    if ($to === null) {
        // if there is no data currently available, allow caching of the empty response for 1 minute
        header("Cache-Control: private, max-age=60");
    } else if (time() - $to / 1000 > 60 * 60 * 24 * 366) {
        // if the data is being returned for time range older than one year, allow caching for 10 days
        header("Cache-Control: private, max-age=864000");
    } else if ($_GET["unit"] === "y") {
        // data that is aggregated by year is cacheable 5 minutes.
        header("Cache-Control: private, max-age=300");
    } else {
        // otherwise prevent caching
        header("Cache-Control: no-cache");
    }



    // write the result to the output
    header("Content-Type: application/json");
    print json_encode($chartResult /*, JSON_PRETTY_PRINT */);


} catch(Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    throw $e;
}
