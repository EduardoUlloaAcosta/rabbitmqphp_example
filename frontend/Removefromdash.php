<?php
// Brian Patoilo 2/26/26 - handles removing a meal from the dashboard
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

require_once __DIR__ . '/rabbitmq_helper.php';

$daily_plan_meal_id = $_POST['daily_plan_meal_id'] ?? null;
$plan_date = $_POST['plan_date'] ?? date('Y-m-d');

if ($daily_plan_meal_id) {
    sendRequest([
        'type' => 'remove_from_dashboard',
        'user_id' => $_SESSION['user_id'],
        'daily_plan_meal_id' => $daily_plan_meal_id
    ]);
}

header('Location: dashboard.php?date=' . urlencode($plan_date));
exit;
?>
