<?php
//written by Brian Patoilo 2/11/26
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/login.php';
require_once __DIR__ . '/register.php';
require_once __DIR__ . '/meals.php';

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
	echo "Recieved:" . $msg->body . "\n";
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
			case 'get_meals': //added 2/23 by ainesh for handleGetMeals function
                $response = handleGetMeals($data);
                break;
            case 'search_meal_by_letter': //add case to allow cronjob to run for all letters
                $response = handleSearchMealByLetter($data);
                break;
            case 'get_meal_by_id': //added 2/25 by ainesh for grabbing meal by id
                $response = handleGetMealById($data);
                break;
            case 'get_reviews': //added 2/25 by ainesh for grabbing reviews
                $response = handleGetReviews($data);
                break;
            case 'post_review': //added 2/25 by ainesh for posting reviews
                $response = handlePostReview($data);
                break;
            case 'add_to_dashboard': //added by Brian for dashboard logic
                $response = handleAddToDashboard($data);
                break;
            case 'get_dashboard':
                $response = handleGetDashboard($data);
                break;
            case 'remove_from_dashboard':
                $response = handleRemoveFromDashboard($data);
                break;
            case 'get_recommendations': // recommendations caller 3/5 brian
                $response = handleGetRecommendations($data);
                break;



            // add cases here when make more features
			//example cases
            // case 'get_profile':
            //     $response = handleGetProfile($data);
            //     break;

            //stefan - 3/3/26 - need to make functionality for userProfile
            case 'update_user_profile':
                $response = handleUpdateUserProfile($data);
                break;
            //case for getting user Profile
            case 'get_user_profile':
                $response = handleGetUserProfile($data);
                break;
			case 'get_user_diet': //added to get diet
			    $response = handleGetUserDiet($data);
			    break;
			case 'update_user_diet':
			    $response = handleUpdateUserDiet($data);
			    break;
			case 'delete_user_diet':
			    $response = handleDeleteUserDiet($data);
			    break;

                //added on 4/10/2026 by ainesh, 'db_replicate' case
            case 'db_replicate': //cronjob on hot standby will call this for db dump
                $dump = shell_exec('mysqldump -u testUser -p123 meal_planner');
                if ($dump){
                    $response = ['success' => true, 'dump' => $dump];
                } else {
                    $response = ['success' => false, 'message' => 'dump failed :c'];
                }
                break;

            case 'add_custom_meal':
                $response = CustomMealMaker($data);
                break;

            default:
                $response = ['success' => false, 'message' => "Unknown request type: $type"];
                echo " Unknown type: $type\n";
                break;
        }
    }

    if($type != 'db_replicate'){
        echo "Response: " . json_encode($response) . "\n\n";
    }

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

        echo "Reply sent to: " . $msg->get('reply_to') . "\n";
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
