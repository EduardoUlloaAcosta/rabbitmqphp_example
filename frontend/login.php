<?php
//Brian Patoilo, 2/12/26, login and bring to searchpage

session_start();
require_once __DIR__ . '/rabbitmq_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    header('Location: index.html?error=' . urlencode('ENETER STUFF'));
    exit;
}

// login to database
$response = sendRequest([
    'type' => 'login',
    'username' => $username,
    'password' => $password
]);

if ($response['success']) {
    $_SESSION['user_id'] = $response['user_id'];
    $_SESSION['username'] = $response['username'];

    header('Location: search.php');
    exit;
} else {
    header('Location: index.html?error=' . urlencode($response['message']));
    exit;
}

?>
