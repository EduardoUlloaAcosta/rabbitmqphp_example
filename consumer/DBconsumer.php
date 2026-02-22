<?php
//written by Brian Patoilo 2/11/26
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/login.php';
require_once __DIR__ . '/register.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

//changed 2/22 to not be hard coded
$connection = new AMQPStreamConnection(
    RABBITMQ_HOST,
    RABBITMQ_PORT,
    RABBITMQ_USER,
    RABBITMQ_PASS,
    RABBITMQ_VHOST
);

$channel = $connection->channel();

$channel->queue_declare('db_queue', false, true, false, false);
echo "Waiting for messages";



$callback = function ($msg) {
	echo " [x] Recieved:" . $msg->body . "\n";
	$data = json_decode($msg->body, true);

	if ($data === null) {
        $response = ['success' => false, 'message' => 'Shit dont work'];
        echo "I cant read that stuff";
    } else {
        $type = $data['type'] ?? '';

        switch ($type) {
            case 'login':
                $response = handleLogin($data);
                break;
            case 'register':
                $response = handleRegister($data);
                break;
            case 'search_meal': //added 2/22 for meals php
                $response = handleSearchMeal($data);
                break;

            // add cases here when make more features
			//example cases
            // case 'get_profile':
            //     $response = handleGetProfile($data);
            //     break;

            default:
                $response = ['success' => false, 'message' => "Unknown request type: $type"];
                echo " [!] Unknown type: $type\n";
                break;
        }
    }

    echo " [x] Response: " . json_encode($response) . "\n\n";

    if ($msg->has('reply_to') && $msg->has('correlation_id')) {
        $replyMsg = new AMQPMessage(
            json_encode($response),
            ['correlation_id' => $msg->get('correlation_id')]
        );

        $msg->getChannel()->basic_publish(
            $replyMsg,
            '',
            $msg->get('reply_to')
        );

        echo " [x] Reply sent to: " . $msg->get('reply_to') . "\n";
    }
    $msg->ack();
};

$channel->basic_consume(QUEUE_NAME, '', false, false, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
