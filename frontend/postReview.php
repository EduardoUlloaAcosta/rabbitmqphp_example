<?php
//handles review forms, created by ainesh 2/25
session_start();
if(!isset($_SESSION['user_id'])){
    header('Location: index.html?error=' . urlencode('how did u get here.'));
    exit;
}

require_once __DIR__ . '/rabbitmq_helper.php';

$meal_id = $_POST['meal_id'] ?? null;
$rating = $_POST['rating'] ?? null;
$review_text = $_POST['review_text'] ?? '';
$user_id = $_SESSION['user_id'];

if (!$meal_id || !$rating){
    header('Location: mealDetails.php?id=' . $meal_id . '&error=missing fields');
    exit;
}

$response = sendRequest([
    'type' => 'post_review',
    'meal_id' => (int)$meal_id,
    'user_id' => (int)$user_id,
    'rating' => (int)$rating,
    'review_text'=> $review_text
]);

if ($response && $response['success']){
    header('Location: mealDetails.php?id=' . $meal_id . '&success=review posted!');
}else{
    header('Location: mealDetails.php?id=' . $meal_id . '&error=failed to post review');
}
exit;

?>
