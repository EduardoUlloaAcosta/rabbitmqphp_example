<?php
header('Content-Type: application/json');
require_once (__DIR__ . '/../rabbitMQLib.inc');

$client = new rabbitMQClient("dmzRabbitMQ.ini", "dmz");

$query = "beef";

//themealdb request
$mealRequest = [
    "type" => "search_meal",
    "query" => $query
];

$mealResponse = $client->send_request($mealRequest);

//fdc request
$fdcReq = [
    "type" => "fdc_search",
    "query" => $query
];

$fdcResponse = $client->send_request($fdcReq);

echo json_encode([
    "query" => $query,
    //themealdb

    "mealdb_status" => $mealResponse["status"] ?? null,
    "mealdb_count" => isset($mealResponse["results"]["meals"]) ? count($mealResponse["results"]["meals"]) : null,

    //fdc

    "fdc_status" => $fdcResponse["status"] ?? null,
    "kcal" => $fdcResponse["kcal"] ?? null,
    "item" => $fdcResponse["item"] ?? null,
], JSON_PRETTY_PRINT);

echo "\n";
?>
