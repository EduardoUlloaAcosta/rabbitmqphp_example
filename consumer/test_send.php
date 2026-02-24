<?php
// test file for the meal search

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$query = $argv[1] ?? "chicken"; // pass a search term as argument, defaults to chicken

echo "Searching for: $query\n";
echo "Sending request to DB consumer...\n\n";

$request = [
    'type' => 'search_meal',
    'query' => $query
];

try {
    $connection = new AMQPStreamConnection(
        RABBITMQ_HOST,
        RABBITMQ_PORT,
        RABBITMQ_USER,
        RABBITMQ_PASS,
        RABBITMQ_VHOST
    );
    $channel = $connection->channel();

    // create temp reply queue
    list($replyQueue, ,) = $channel->queue_declare("", false, false, true, false);

    $response = null;
    $correlationId = uniqid('test_', true);

    $channel->basic_consume($replyQueue, '', false, true, false, false,
        function ($msg) use (&$response, $correlationId) {
            if ($msg->get('correlation_id') == $correlationId) {
                $response = json_decode($msg->body, true);
            }
        }
    );

    $msg = new AMQPMessage(json_encode($request), [
        'correlation_id' => $correlationId,
        'reply_to' => $replyQueue
    ]);

    $channel->basic_publish($msg, '', QUEUE_NAME);
    echo "Request sent. Waiting for response...\n";

    $waitUntil = time() + 120;
    while ($response === null && time() < $waitUntil) {
        $channel->wait(null, false, 120);
    }

    $channel->close();
    $connection->close();

    if ($response === null) {
        echo "ERROR: No response received (timed out)\n";
    } else {
        echo "\n=== RESPONSE ===\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
