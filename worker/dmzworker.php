<?php
require_once(__DIR__ . '/vendor/autoload.php'); // need for api key to read from .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once(__DIR__ . '/../rabbitMQLib.inc');

$ini = __DIR__ . '/dmzRabbitMQ.ini';
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

        $query = $request['query'] ?? "chicken"; //chicken is default

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

    elseif ($request['type'] === "fdc_search"){

        $query = $request['query'] ?? "apple"; //apple is default

        $fdcKey = $_ENV['USDA_FOODCHART_API_KEY'];

        if (!$fdcKey){
            return ["status" => "error", "message" => "missing fdc api key"];
        }

        $url = "https://api.nal.usda.gov/fdc/v1/foods/search?api_key=" . urlencode($fdcKey)
            . "&query=" . urlencode($query)
            . "&pageSize=1";

        $context = stream_context_create([
            'http' => [
                'timeout' => 10
            ]
        ]);

        $json = file_get_contents($url, false, $context);
        if ($json === false){
            $err = error_get_last();
            return["status" => "error", "message" => "fdc fetch failed", "error" => $err['message']];
        }

        $fdcData = json_decode($json, true);

        if ($fdcData === null){
            return ["status" => "error", "message" => "fdc gave bad json :c"];
        }

        $kcal = null;
        $food = $fdcData["foods"][0] ?? null;

        if ($food && isset($food["foodNutrients"]) && is_array($food["foodNutrients"])){
            foreach ($food["foodNutrients"] as $n){
                $name = $n["nutrientName"] ?? "";
                $unit = $n["unitName"] ?? "";
                if(strtolower($name) === "energy" && strtoupper($unit) === "KCAL"){
                    $kcal = $n["value"] ?? null;
                    break;
                }
            }
        }

        $name = $food["description"] ?? null;

        return[
            "status" => "success",
            "source" => "dmz",
            "api" => "fdc",
            "query" => $query,
            "kcal" => $kcal,
            "item" => $name,
            "fdc_id" => isset($food["fdcId"]) ? (string)$food["fdcId"] : null
        ];

    }

    return ["status" => "error", "message" => "unknown request type"];
}

$worker-> process_requests("handleRequest");
?>
