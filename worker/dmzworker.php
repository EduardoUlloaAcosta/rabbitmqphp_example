<?php
require_once(__DIR__ . '/../rabbitMQLib.inc');

$ini = __DIR__ . '/../dmzRabbitMQ.ini';
$server = "dmz";

$worker = new rabbitMQServer($ini, $server);

echo "dmz worker started. waiting for requests :)\n";

function handleRequest($request)
{
    echo "\nreceived request:\n";
    print_r($request);

    if (!isset($request['type'])){
        return ["status" => "error", "message" => "missing type"];
    }

    //this part is for searching meals
    if ($request['type'] === "search_meal"){

        $query = $request['query'] ?? "chicken";

        $url = "https://www.themealdb.com/api/json/v1/1/search.php?s=" . urlencode($query);
        $json = file_get_contents($url);

        if ($json === false){
            return ["status" => "error", "message" => "themealdb fetch failed bruh"];
        }

        $mealData = json_decode($json, true);

        return [
            "status" => "success",
            "source" => "dmz",
            "api" => "themealdb",
            "results" => $mealData
        ];

    }

    return ["status" => "error", "message" => "unknown request type"];
}

$worker-> process_requests("handleRequest");
?>
