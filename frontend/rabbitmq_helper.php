<?php
// Brian Patoilo. Looked up this file because I had no clue how to get the frontend to send to the database. started 2/12/26

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/FEconfig.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// this is the function for sendin stuff to the RabbitMQ to send to DB
function sendRequest($data, $timeout = 10) {
    try {
        $connection = new AMQPStreamConnection(
            RABBITMQ_HOST,
            RABBITMQ_PORT,
            RABBITMQ_USER,
            RABBITMQ_PASS,
            RABBITMQ_VHOST
        );
        $channel = $connection->channel();
        list($replyQueue, ,) = $channel->queue_declare("", false, false, true, false); //creates a queue to listen for a reply
        $response = null;
        $correlationId = uniqid('', true);

        $channel->basic_consume($replyQueue, '', false, true, false, false,
            function ($msg) use (&$response, $correlationId) {
                if ($msg->get('correlation_id') == $correlationId) {
                    $response = json_decode($msg->body, true);
                }
            }
        );

        $msg = new AMQPMessage(json_encode($data), [
            'correlation_id' => $correlationId,
            'reply_to' => $replyQueue
        ]);

        $channel->basic_publish($msg, '', QUEUE_NAME);

        $waitUntil = time() + $timeout;
        while ($response === null && time() < $waitUntil) {
            $channel->wait(null, false, $timeout);
        }

        $channel->close();
        $connection->close();
        if ($response === null) {
            return ['success' => false, 'message' => 'No guuud database, need to fix database'];
        }

        return $response;

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Connection error: ' . $e->getMessage()];
    }
}

?>
