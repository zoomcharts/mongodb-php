<?php
// included for debugging purposes - should be removed in any production code
ini_set('display_errors', 1);
date_default_timezone_set("UTC");

// include the mongodb library
require 'vendor/autoload.php';

// connect to the database. replace "mongo-example.friends_nodes" and "mongo-example.fiends_links" with where you imported the sample datasets

$dsn = "mongodb://user:pass@127.0.0.1/db";
if (file_exists("config-local.php")){
    require "config-local.php";
}
$manager = new MongoDB\Driver\Manager($dsn);

$collection_nodes = new MongoDB\Collection($manager, "mongo-example.friends_nodes");
$collection_links = new MongoDB\Collection($manager, "mongo-example.friends_links");

$GLOBALS["collection_nodes"] = $collection_nodes;
$GLOBALS["collection_links"] = $collection_links;

init();
function init() {
    if(isset($_GET["mtd"])) {
        $f = $_GET["mtd"];
        if(function_exists($f)) {
            $params = $_GET;
            //execute method:
            $f($params);
        } else {
            echo "No such method - " . $f1;
            exit;
        }
    }
}

function getNodesByIds($params) {
    if(!isset($params["nodes"]) || !$params["nodes"]) {
        echo "'nodes' must be passed";
        exit;
    }
    $node_ids = explode(",", $params["nodes"]);
    //add this node:
    $nodes = $links = $arr = [];
    foreach($node_ids as $k => $node_id) {
        $arr[] = ["id" => $node_id];
    }
    $collection = $GLOBALS["collection_nodes"];
    //We need to retrieve all records that contains passed node_ids, that is 
    //why we use '$or' expression:
    $q = ['$or' => $arr];
    $options = [];
    $cursor = $collection->find($q, $options);

    //just convert stdClass to array:
    $iterator = iterator_to_array($cursor);
    $array = json_decode(json_encode($iterator), true);
    foreach ($array as $doc) {
        $doc["_id"] = convertId($doc["_id"]);
        $params["node_id"] = $doc["id"];

        //add record to node array
        $nodes = addNodeRecord($nodes, $doc, true);

        //This node probably is linked to some other nodes, so we need to find 
        //those nodes, links to them and return them as well.
        list($nodes, $links) = getMoreNodes($params, $nodes, $links);
    }

    sort($nodes);
    sort($links);
    $data = [
        "nodes" => $nodes,
        "links" => $links
        ];
    
    $json = json_encode($data);
    print_r($json);
}

function addNodeRecord($nodes, $record, $loaded = false) {
    $name = $record["name"];

    $id = (string)$record["id"];
    $node = ["id" => $id, "mongo_id" => convertId($record["_id"]), "loaded" => $loaded, "style" => ["label" => $name . " - [" . $record["id"] . "]"], "extra" => $record];           
    $nodes[$id] = $node;
       
    return $nodes;
}

function getMoreNodes($params, $nodes = [], $links = []) {
    if(!isset($params["node_id"]) || !$params["node_id"]) {
        echo "node_id must be passed";
        exit;   
    }
    $node_id = $params["node_id"];

    //First we need to find links that are related to our already known node.
    //This node can be in 'from' field or in 'to' field, so we need to check 
    //both of them:
    //from:
    $rel_links_1 = getRelatedLinks(array_merge($params, ["target" => "from", "node_id" => $node_id]));
    //to:
    $rel_links_2 = getRelatedLinks(array_merge($params, ["target" => "to", "node_id" => $node_id]));

    //Both returned links must be formatted for NetChart and added to 'links' array
    $links = processLinks($links, $rel_links_1, $node_id);
    $links = processLinks($links, $rel_links_2, $node_id);

    return [$nodes, $links];
}


function getRelatedLinks($params) {
    //Let's set collection we will be using:
    $collection = $GLOBALS["collection_links"];
    
    //check if we have all the necessary params:
    if(!$params["node_id"]) {
        echo "node_id is required";
        exit;
    }
    $target = isset($params["target"]) && $params["target"] ? $params["target"] : "from";
    if($target != "from" && $target != "to") {
        echo "no such target allowed. Options: 'from' or 'to'";
        exit;
    }

    $q = [$target => $params["node_id"]];

    //In options you can set projection, limit, skip (offset) and other 
    //options:
    //$options =  array("projection" => ["dts" => 1], "limit"=>0, "sort" => ["dts" => 1]);
    $options =  [
        "limit" => isset($params["limit"]) && $params["limit"] ? (int)$params["limit"] : null,
        "skip" => isset($params["offset"]) && $params["offset"] ? (int)$params["offset"] : null
        ];

    //In case you want to execute count command:
    //$count = $cursor = $collection->count($q, []);

    $cursor = $collection->find($q, $options);
    //just convert stdClass to array:
    $iterator = iterator_to_array($cursor);
    $array = json_decode(json_encode($iterator), true);
    $docs = [];
    foreach ($array as $doc) {
        $docs[] = $doc;
    }
    return $docs;
}

function processLinks($links, $rels, $pre_node_id) {
    if($rels) {
        foreach($rels as $r) {
            $id = $r["from"] . "-" . $r["to"] . "-" . $r["type"];
            if(!isset($links[$id])) {
                $link = [
                    "id" => $id,
                    "from" => $r["from"], 
                    "to" => $r["to"], 
                    "extra" => [
                        "type" => $r["type"]
                    ]
                ];
                $links[$id] = $link;

                //Here we have links that are related to our node. Each link has 
                //2 points(nodes), Now we know both IDs and our firstly requested node data,
                //so we have options whether to load the other node and return it to NetChart
                //or just pass links as they are and NetChart will ask for those missing 
                //nodes automatically.

                //$records = FunctionToGetMissingNodes();
            }
        }
    }
    return $links;
}

function convertId($idObj) {
    if ($idObj instanceof \MongoDB\BSON\ObjectID){
        return (string)$idObj;
    }
    return $idObj;
}

