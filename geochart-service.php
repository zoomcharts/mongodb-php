<?php
try
{
    // included for debugging purposes - should be removed in any production code
    ini_set('display_errors', 1);
    date_default_timezone_set("UTC");

    // include the mongodb library
    require 'vendor/autoload.php';


    // connect to the database. replace "mongo-example.cities" with where you imported the sample dataset
 
    $dsn = "mongodb://user:pass@127.0.0.1/db";
    if (file_exists("config-local.php")){
        require "config-local.php";
    }

    $manager = new MongoDB\Driver\Manager($dsn);
 
    $collection = new MongoDB\Collection($manager, "mongo-example.cities");


    //Example of coordinates:
    /*
    $coordinates = [
        "north" => "56.97457345228613",
        "south" => "56.9511767654851",
        "west" => "24.115977287292477",
        "east" => "24.20009136199951"
        ];
    */
    
    //Read coordinates from $_GET variable:
    $coordinates = $_GET;
    $match = null;

  
    //creat $match variable for MongoDB
    //coordinates must be converted to (double) as in MongoDB records are stored asn double
    // and in PHP coordinates comes as string
    $match = [
        "lng" => [
            '$gte' => (double)$coordinates["west"],
            '$lte' => (double)$coordinates["east"]
        ],
        "lat" => [
            '$gte' => (double)$coordinates["south"],
            '$lte' => (double)$coordinates["north"]
        ]    
    ];

    //execute query:
    $cursor = $collection->find($match, []);
    
    //just convert stdClass to array:
    $iterator = iterator_to_array($cursor);
    $array = json_decode(json_encode($iterator), true);

    //Now build data array and format each record as needed:
    $nodes = [];
    $count = 0;

    foreach ($array as $doc) {
        $r = [
            "id" => $doc["id"],
            "type" => "point",
            "loaded" => true,
            "name" => $doc["caption"],
            "population" => (int)$doc["population"],
            //longitude as first, latitude as last:
            "coordinates" => [
                (double)$doc["lng"],
                (double)$doc["lat"]
            ],
        ];
        $nodes[] = $r;
        $count++;
    }
    
    $chartResult = [
        'nodes' => $nodes
    ];


    //Use cache control:
    //header("Cache-Control: no-cache");
    header("Cache-Control: private, max-age=300");



    // write the result to the output
    header("Content-Type: application/json");
    print json_encode($chartResult /*, JSON_PRETTY_PRINT */);


} catch(Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    throw $e;
}

