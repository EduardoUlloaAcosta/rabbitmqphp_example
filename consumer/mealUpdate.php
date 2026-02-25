<?php
// Brian Patoilo, 2/23/26, file made to update meals table with new info from API.
//This will be a weekly cronjob

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$letters = range('a', 'z');
$totalInserted = 0;
$totalSkipped = 0;
$totalFailed = 0;
$startTime = time();

echo "cronjob running" . date('Y-m-d H:i:s');

foreach ($letters as $letter) {
    echo "Searching letter: $letter";

    $request = [
        'type' => 'search_meal',
        'query' => $letter
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

        $replyQueueName = 'cron_reply_' . uniqid();
        $channel->queue_declare($replyQueueName, false, false, false, true);

        $response = null;
        $correlationId = uniqid('cron_', true);

        $channel->basic_consume($replyQueueName, '', false, true, false, false,
            function ($msg) use (&$response, $correlationId) {
                if ($msg->get('correlation_id') == $correlationId) {
                    $response = json_decode($msg->body, true);
                }
            }
        );

        $msg = new AMQPMessage(json_encode($request), [
            'correlation_id' => $correlationId,
            'reply_to' => $replyQueueName
        ]);

        $channel->basic_publish($msg, '', QUEUE_NAME);

        //longer timeout each letter
        $waitUntil = time() + 300;
        while ($response === null && time() < $waitUntil) {
            $channel->wait(null, false, 300);
        }

        $channel->close();
        $connection->close();

        if ($response === null) {
            echo "Timeout for letter $letter\n";
            $totalFailed++;
        } elseif ($response['success']) {
            $meals = $response['meals'] ?? [];
            $newCount = 0;
            $skipCount = 0;
            foreach ($meals as $m) {
                if ($m['already_existed']) {
                    $skipCount++;
                } else {
                    $newCount++;
                }
            }
            $totalInserted += $newCount;
            $totalSkipped += $skipCount;
            echo "Letter $letter: $newCount new, $skipCount already existed\n";
        } else {
            echo "Letter $letter: " . ($response['message'] ?? 'unknown error') . "\n";
        }

    } catch (Exception $e) {
        echo "Error letter $letter: " . $e->getMessage() . "\n";
        $totalFailed++;
    }

    // small delay
    sleep(2);
}

$elapsed = time() - $startTime;
$minutes = round($elapsed / 60, 1);

echo "\ndatabase cronjob complete" . date('Y-m-d H:i:s');
echo "new meals entered: $totalInserted\n";
echo "meals already in DB: $totalSkipped\n";
echo "errors: $totalFailed\n";
echo "Time elapsed: $minutes minutes\n";
?>
