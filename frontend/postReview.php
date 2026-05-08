<?php
//handles review forms, created by ainesh 2/25
session_start();
if(!isset($_SESSION['user_id'])){
    header('Location: index.html?error=' . urlencode('how did u get here.'));
    exit;
}

require_once __DIR__ . '/rabbitmq_helper.php';
// ainesh comment (5/8/2026) - just grabbing everything from the form submission
$meal_id = $_POST['meal_id'] ?? null;
$rating = $_POST['rating'] ?? null;
$review_text = $_POST['review_text'] ?? '';
$user_id = $_SESSION['user_id'];

//ainesh 5/8 - cant post review w/o meal or rating
if (!$meal_id || !$rating){
    header('Location: mealDetails.php?id=' . $meal_id . '&error=missing fields');
    exit;
}

//ainesh 5/8 - send review to db thru rbmq
$response = sendRequest([
    'type' => 'post_review',
    'meal_id' => (int)$meal_id,
    'user_id' => (int)$user_id,
    'rating' => (int)$rating,
    'review_text'=> $review_text
]);
//ainesh 5/8 - and then redirect back to meal page
if ($response && $response['success']){
    header('Location: mealDetails.php?id=' . $meal_id . '&success=review posted!');
}else{
    header('Location: mealDetails.php?id=' . $meal_id . '&error=failed to post review');
}
exit;

?>
