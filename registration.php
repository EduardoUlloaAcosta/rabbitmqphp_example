<?php

session_start();

//getting form data
$email = $_POST['email'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['psw'] ?? '';
$password_repeat = $_POST['psw-repeat'] ?? '';
?>
