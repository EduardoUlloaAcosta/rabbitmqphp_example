<?php
session_start();
// need to get the stuff that is being used by the user

if(!isset($_SESSION['user_id'])){
    header('Location: index.html?error=' . urlencode('....what you have come to find is not here'));
    exit;
}
$user_id = $_SESSION['user_id'];
require_once __DIR__ . '/rabbitmq_helper.php';

//Messages for success or failure
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = sendRequest([
        'type' => 'update_user_profile',
        //check session using user_id
        'user_id' => $_SESSION['user_id'],
        //need to make insert statement for DB
        'height' => $_POST['height'],
        'current_weight' => $_POST['current_weight'],
        'goal_weight' => $_POST['goal_weight']
    ]);

    if ($response['success']) {
        $successMsg = 'Your profile has been saved!!!!!';

        //FIXED - PAGE NOT POPPING UP WHEN RUNNING - Check Routing for pages
    } else {
        $errorMsg = $response['message'];
    }

}

?>


<html>

<head>
<link rel="stylesheet" href="searchstyle.css">
<link rel="stylesheet" href="global.css">

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


<style>
    .box {
        width: 800px;
        height: 700px;
        background-color: rgb(255, 255, 255);
        border: 2px solid rgb(0, 0, 0);
        padding: 10px;
        margin: 10px;
    }
    .username-box{
        width: 40px;
        height: 80px;
        background-color: rgb(115, 255, 0);
        border: 2px solid rgb(0, 0, 0);
        padding: 10px;
        margin: 10px;

    }

    .small-box{
        width: 400px;
        height: 60px;
        background-color: rgb(116, 198, 0);
        border: 2px solid rgb(0, 0, 0);
        padding: 10px;
        margin: 10px;
        margin-top: 50px;


    }
</style>

<body>
<!-- Users will be able to enter their height, current weight, goal weight, and diet Preference -->
<form method="POST">
    <!-- Height Box -->
    <div class="small-box">
        <input type="text" name="height" placeholder="Input Height (in cm): ">
    </div>
    <!-- Weight Box -->
    <div class="small-box">
        <input type="text" name="current_weight" placeholder="Input Weight (lbs): ">
    </div>
    <!-- Goal Weight -->
    <div class="small-box">
        <input type="text" name="goal_weight" placeholder="Input Goal Weight (lbs): ">
    </div>
    <!-- Diet Preference (leaving as text for now) -->
    <div class="small-box">
        <input type="text" name="diet" placeholder="Diet Preference: ">
    </div>
    <div class="small-box">
        <button type="submit">Save Profile</button>
    </div>
</form>
    
 
    
</body>






</head>
</html>
