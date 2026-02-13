<?php
// test search page/home page to bring after login

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html?error=' . urlencode('Howd you get here??'));
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Search</title>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    <p>Your user ID is: <?php echo htmlspecialchars($_SESSION['user_id']); ?></p>
    <p>You are logged in. This page is protected.</p>

    <a href="logout.php">Logout</a>
</body>
</html>
