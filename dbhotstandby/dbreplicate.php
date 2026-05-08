<?php
//created by ainesh 4/10/2026.
//this will be run thru cronjob to pull db dump from main db

//this file is completely useless now, lmao
//i was told by kehoe we were supposed to do it through mysql. i thought we couldnt do that so thats why this is how i approached it first
require_once __DIR__ . '/../consumer/vendor/autoload.php';
require_once __DIR__ . '/../consumer/config.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection(
    RABBITMQ_HOST,
    RABBITMQ_PORT,
    RABBITMQ_USER,
    RABBITMQ_PASS,
    RABBITMQ_VHOST,
);

$channel = $connection->channel();
$replyQueue = $channel->queue_declare('', false, false, true, false)[0]; //[0] grabs queue name

$response = null;
$corrId = uniqid();

$msg = new AMQPMessage(
    json_encode(['type' => 'db_replicate']), //calling db_replicate case for dump
    ['correlation_id' => $corrId, 'reply_to' => $replyQueue]
);

$channel->basic_publish($msg,'', QUEUE_NAME);

$channel->basic_consume($replyQueue, '', false, true, false, false, function($msg) use (&$response, $corrId){
    if ($msg->get('correlation_id') === $corrId){
        $response = json_decode($msg->body, true);
    }
});

while ($response === null){
    $channel->wait();
}

$channel->close();
$connection->close();

//receiving the sql dump and writing it to a tmp file to be imported
//this simply stopped working at some point and i was not able to figure it out. also a big reason why i decided to switch using mysql's replication.
if ($response['success'] && !empty($response['dump'])){
    $tmp = '/tmp/dbdump.sql';
    file_put_contents($tmp, $response['dump']);
    shell_exec('mysql -u testUser -p123 meal_planner <' . $tmp);
    echo "db updated";
    } else {
        echo "db update failed: " . ($response['message'] ?? 'unknown error') . "\n";
    }

?>
