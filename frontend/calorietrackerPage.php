<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html?error=' . urlencode('Howd you get here??'));
    exit;
}

require_once __DIR__ . '/rabbitmq_helper.php';

$today = date('Y-m-d');
$calorieGoal = 2000; //will change later to be entered by user

$dashResponse = sendRequest([
    'type' => 'get_dashboard',
    'user_id' => $_SESSION['user_id'],
    'plan_date' => $today
]);

$totalCalories = 0;
if ($dashResponse && $dashResponse['success'] && !empty($dashResponse['meals'])) {
    foreach ($dashResponse['meals'] as $m) {
        $totalCalories += isset($m['calories']) && $m['calories'] !== null ? round($m['calories']) : 0;
    }
}

$progress = $calorieGoal > 0 ? min(round(($totalCalories / $calorieGoal) * 100), 100) : 0;
?>
<!DOCTYPE html>
<html>

<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="searchstyle.css">
<link rel="stylesheet" href="global.css">

<style>
    body {
        margin: 0;
        height: auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        background-color: #f4f4f4;
    }

    .box {
        width: 800px;
        max-width:800px
        height: auto;
        background-color: rgb(255, 255, 255);
        border: 2px solid rgb(0, 0, 0);
        padding: 10px;
        margin-top: 40px;
    }

    .small-box {
        width: 400px;
        max-width:440px;
        height: auto;
        background-color: rgb(116, 198, 0);
        border: 2px solid rgb(0, 0, 0);
        padding: 10px;
        margin: 20px auto;
        display: flex;
        align-items: center;
        justify-content: center;
    }

     /* progress is gonna get changes out cuz
    gonna need to make a variable
    that will work out the calulation for it -Stefan */
    .progress-bar {
      --progress: 65;
      width: 200px;
      height: 200px;
      border-radius: 50%;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      font-weight: bold;
      font-size: 28px;
      margin: 100px auto;
      background:
        radial-gradient(closest-side, white 78%, transparent 80% 100%),
        conic-gradient(
          rgb(116, 198, 0) calc(var(--progress) * 1%),
          #e6e6e6 0
        );
    }

    .progress-bar small {
      font-size: 16px;
      font-weight: normal;
      margin-top: 5px;
    }
</style>

</head>

<body>
<!-- Eduardo's Header design remember in case of new page-->
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

<div class="box">

    <div class="small-box">
        <input type="text" placeholder="Calorie Goal: ">
    </div>


    <div class="small-box">
        <p><?= $totalCalories ?> / <?= $calorieGoal ?> kcal consumed today</p>
    </div>

<div class="progress-bar" style="--progress: <?= $progress ?>;">
    <?= $totalCalories ?>
    <small>/ <?= $calorieGoal ?> kcal</small>
</div>
<!-- ty Brian -->
 <a href="journey.php" class="add-meal-btn">Social Progress</a>
    <link rel="stylesheet" href="searchstyle.css" />

</div>

<script>
 document.querySelector('.mobile-menu-button').addEventListener('click', () => {
        document.querySelector('.nav-links').classList.toggle('open');
    });
 </script>


</body>
</html>
