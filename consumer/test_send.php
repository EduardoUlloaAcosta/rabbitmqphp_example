<?php
//Brian Patoilo 2/23/26


require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection(
    RABBITMQ_HOST, RABBITMQ_PORT,
    RABBITMQ_USER, RABBITMQ_PASS, RABBITMQ_VHOST
);
$channel = $connection->channel();

// Create a temporary reply queue
list($replyQueue, ,) = $channel->queue_declare("", false, false, true, false);

$correlationId = uniqid();
$response = null;

// Listen for the reply
$channel->basic_consume($replyQueue, '', false, true, false, false,
    function ($msg) use (&$response, $correlationId) {
        if ($msg->get('correlation_id') == $correlationId) {
            $response = json_decode($msg->body, true);
        }
    }
);

// Pick what you want to test:
// Test login
$testMessage = json_encode([
    'type' => 'login',
    'username' => 'testuser2',
    'password' => 'testpass123',
]);

// To test login instead, comment out the above and uncomment this:
// $testMessage = json_encode([
//     'type' => 'login',
//     'username' => 'testuser',
//     'password' => 'testpass123'
// ]);

$msg = new AMQPMessage($testMessage, [
    'correlation_id' => $correlationId,
    'reply_to' => $replyQueue
]);

$channel->basic_publish($msg, '', QUEUE_NAME);
echo " [x] Sent: $testMessage\n";
echo " [x] Waiting for response...\n";

// Wait up to 5 seconds for a reply
$timeout = time() + 5;
while ($response === null && time() < $timeout) {
    $channel->wait(null, false, 5);
}

if ($response) {
    echo " [x] Got response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
} else {
    echo " [!] No response received (timeout)\n";
}

$channel->close();
$connection->close();

