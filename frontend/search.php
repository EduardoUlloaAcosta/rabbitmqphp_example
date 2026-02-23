<?php
// test search page/home page to bring after login

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html?error=' . urlencode('Howd you get here??'));
    exit;
}
?>
<!DOCTYPE html>
<div class="headerMain">
    <header>
        <nav>
            <div class="logo">
                <a href="search.php">Cooking Crew</a>
                <link rel="stylesheet" href="global.css" />
                <link rel="stylesheet" href="searchstyle.css" />
            </div>
            <ul class="nav-links">
                <li><a href="search.php">Home</a></li>
                <li><a href="profile.html">Profile</a></li>
                <li><a href="calorieTracker.html">Calorie Tracker</a></li>
                <li><a href="dashboard.html">Dashboard</a></li>

            </ul>
            <div class="logout-btn">
                <a href="logout.php">Logout</a>
            </div>

        </nav>

    </header>
</div>

<div class="box">
    <h1>Search For Meals</h1>

    <div class="searchBox">
        <input
            type="text"
            placeholder="Search for meals (e.g, chicken, pasta, salad...." />

    </div>
</div>