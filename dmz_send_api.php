<?php
require_once("rabbitMQLib.inc");

$ini = "dmzRabbitMQ.ini";
$server = "dmz";

$client = new rabbitMQClient($ini, $server);

$api = $argv[1] ?? "themealdb";

if ($api === "themealdb") {
    $url = "https://www.themealdb.com/api/json/v1/1/random.php";
}
elseif ($api === "fdc"){
    $fdcKey = "hUtLARhIj6b1fNtmw8SDYt0bwVU4L0y9vru9dqeM";
    $url = "https://api.nal.usda.gov/fdc/v1/foods/search?api_key=hUtLARhIj6b1fNtmw8SDYt0bwVU4L0y9vru9dqeM&query=apple&pageSize=1";
}
else {
    echo "invalid api choice lil bro\n";
    exit(1);
}

$json = @file_get_contents($url);

if ($json === false) {
    echo "api fetch ded\n";
    exit(1);
}

$data = json_decode($json, true);

if ($data === null){
    echo "api returned bad json\n";
    exit(1);
}

$request = [
    "type" => "DMZ_API_DATA",
    "source" => "dmz",
    "api" => $api,
    "timestamp" => time(),
    "payload" => $data
];

$ok = $client->publish($request);

echo "published $api\n";

?>
