<?php
// Brian Patoilo register code, 2/12/26

require_once __DIR__ . '/rabbitmq_helper.php';

//looked this up, think it means the method that the register html will use
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: registration.html');
    exit;
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$passwordRepeat = $_POST['psw-repeat'] ?? '';

//password check match
if ($password !== $passwordRepeat) {
    header('Location: registration.html?error=' . urlencode('Password match no gud'));
    exit;
}

if (empty($username) || empty($email) || empty($password)) {
    header('Location: register.html?error=' . urlencode('Enter your information dude'));
    exit;
}

//send to rabbitmq
$response = sendRequest([
    'type' => 'register',
    'username' => $username,
    'password' => $password,
    'email' => $email
]);

//response how to look up syntax
if ($response['success']) {
    // Registration worked - send them to login page
    header('Location: index.html?success=' . urlencode('Registration all gud, log in now pulease'));
    exit;
} else {
    // ad to look up error syntax
    header('Location: registration.html?error=' . urlencode($response['message']));
    exit;
}

?>
