<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html?error=' . urlencode('Howd you get here??'));
    exit;
}


require_once __DIR__ . '/rabbitmq_helper.php';
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="searchstyle.css" />
    <link rel="stylesheet" href="global.css" />
</head>
<body>
<div class="headerMain">
    <header>
        <nav>
            <div class="logo">
                <a href="search.php">Cooking Crew</a>
            </div>
            <ul class="nav-links">
                <li><a href="search.php">Home</a></li>
                <li><a href="userPage.php">Profile</a></li>
                <li><a href="calorietrackerPage.php">Calorie Tracker</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
            </ul>
            <div class="mobile-menu-button">
                <span></span><span></span><span></span>
            </div>
            <div class="logout-btn">
                <a href="logout.php">Logout</a>
            </div>
        </nav>
    </header>
</div>

<!-- Search box to search by username -->
<div class="box">
    <h1>Search For User</h1>
    <form method="GET" action="journey.php">
    <input
        type="text"
        name="query"
        placeholder="Search for User.... ex)Bob, Chud"
        value="<?php echo htmlspecialchars($_GET['query'] ?? ''); ?>"
        />
        <button type="submit">Search</button>
    </form>
</div>
