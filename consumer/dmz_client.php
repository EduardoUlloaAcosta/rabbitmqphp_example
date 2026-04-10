<?php
//Brian Patoilo 2/22/26 file made to send request for API data to get fetched from dmz worker

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

function sendDmzRequest($data, $timeout = 15) {
    try {
        $connection = new AMQPStreamConnection(
            RABBITMQ_HOST,
            RABBITMQ_PORT,
            RABBITMQ_USER,
            RABBITMQ_PASS,
            RABBITMQ_VHOST
        );
        $channel = $connection->channel();

        $channel->exchange_declare(DMZ_EXCHANGE, DMZ_EXCHANGE_TYPE, false, true, false);

        $replyQueueName = 'dmz_reply_' . uniqid();
        $channel->queue_declare($replyQueueName, false, false, false, true); //unexclusive replay queue
        $replyQueue = $replyQueueName;

        $replyRoutingKey = DMZ_ROUTING_KEY . ".response";
        $channel->queue_bind($replyQueue, DMZ_EXCHANGE, $replyRoutingKey);
        $channel->queue_declare(DMZ_QUEUE, false, true, false, false);
        $channel->queue_bind(DMZ_QUEUE, DMZ_EXCHANGE, DMZ_ROUTING_KEY);

        $response = null;
        // This makes sure the answer from RabbitMQ is for this code
        $correlationId = uniqid('dmz_', true);

        // reply queue
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

        //sends to dmz queue
        $channel->basic_publish($msg, DMZ_EXCHANGE, DMZ_ROUTING_KEY);

        echo "Gone to the DMZ" . json_encode($data) . "\n";
        $waitUntil = time() + $timeout;
        while ($response === null && time() < $waitUntil) {
            $channel->wait(null, false, $timeout);
        }

        $channel->close();
        $connection->close();

        if ($response === null) {
            return ['status' => 'error', 'message' => 'DMZ is in timeout'];
        }

        echo " Success from the DMz\n";
        return $response;

    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'DMZ connection error: ' . $e->getMessage()];
    }
}
?>
