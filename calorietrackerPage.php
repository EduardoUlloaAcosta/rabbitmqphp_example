<?php



// php stuff will go here



?>

<!DOCTYPE html>
<html>

<head>
<link rel="stylesheet" href="searchstyle.css">
<link rel="stylesheet" href="global.css">

<style>
    body {
        margin: 0;
        height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        background-color: #f4f4f4;
    }

    .box {
        width: 800px;
        height: 700px;
        background-color: rgb(255, 255, 255);
        border: 2px solid rgb(0, 0, 0);
        padding: 10px;
        margin-top: 40px;
    }

    .small-box {
        width: 400px;
        height: 60px;
        background-color: rgb(116, 198, 0);
        border: 2px solid rgb(0, 0, 0);
        padding: 10px;
        margin: 20px auto;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>

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

    <div class="small-box">
        <input type="text" placeholder="Calorie Goal: ">
    </div>

    <div class="small-box">
        <p>Progress (will have data)</p>
    </div>

</div>

</body>
</html>