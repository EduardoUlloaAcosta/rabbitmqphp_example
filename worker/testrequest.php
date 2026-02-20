<?php
require_once (__DIR__ . '/../rabbitMQLib.inc');

$client = new rabbitMQClient("dmzRabbitMQ.ini", "dmz");

$request = [
    "type" => "search_meal",
    "query" => "beef"
];

$response = $client->send_request($request);
print_r($response);
?>
