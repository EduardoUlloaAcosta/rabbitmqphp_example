<?php
session_start();
// need to get the stuff that is being used by the user

if(!isset($_SESSION['user_id'])){
    header('Location: index.html?error=' . urlencode('....what you have come to find is not here'));
    exit;
}
$user_id = $_SESSION['user_id'];
require_once __DIR__ . '/rabbitmq_helper.php';

//if the profile alrady has existing stats then they should be brought back up on load
$profile = [];
$profileResponse = sendRequest([
    //get_user_profile can be found in DBconsumer and that links to meals.php
    'type' => 'get_user_profile',
    'user_id' => $user_id
]);
if ($profileResponse['success']) {
    $profile = $profileResponse['profile'];
}

// getting diet preference
$currentDiet = '';
$dietResponse = sendRequest([
    'type' => 'get_user_diet',
    'user_id' => $user_id
]);
if ($dietResponse['success'] && !empty($dietResponse['diet_name'])) {
    $currentDiet = $dietResponse['diet_name'];
}

//Messages for success or failure
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = sendRequest([
        'type' => 'update_user_profile',
        'user_id' => $_SESSION['user_id'],
        'height' => $_POST['height'],
        'current_weight' => $_POST['current_weight'],
        'goal_weight' => $_POST['goal_weight']
    ]);

    if (!empty($_POST['diet'])) {
        sendRequest([
            'type' => 'update_user_diet',
            'user_id' => $_SESSION['user_id'],
            'diet_name' => $_POST['diet']
        ]);
    } else {
        sendRequest([
            'type' => 'delete_user_diet',
            'user_id' => $_SESSION['user_id']
        ]);
    }
    ]);

    if ($response['success']) {
        $successMsg = 'Your profile has been saved!!!!!';

        //FIXED - PAGE NOT POPPING UP WHEN RUNNING - Check Routing for pages
    } else {
        $errorMsg = $response['message'];
    }

}

?>

<!DOCTYPE html>


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
                <li><a href="calorietrackerPage.php">Calorie Tracker</a></li>
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
<!-- also need to make it so that the data shows up when the user loads the age so they know it -->
<form method="POST">
    <!-- Height Box -->
    <div class="small-box">
        <input type="text" name="height" placeholder="Input Height (in cm): "
        value="<?= htmlspecialchars($profile['height'] ?? '') ?>">
    </div>
    <!-- Weight Box -->
    <div class="small-box">
        <input type="text" name="current_weight" placeholder="Input Weight (lbs): "
        value="<?= htmlspecialchars($profile['current_weight'] ?? '') ?>">
    </div>
    <!-- Goal Weight -->
    <div class="small-box">
        <input type="text" name="goal_weight" placeholder="Input Goal Weight (lbs): "
        value="<?= htmlspecialchars($profile['goal_weight'] ?? '') ?>">
    </div>
    <!-- diet stuff -->
    <div class="small-box">
    <label for="diet">Diet Preference: </label>
    <select name="diet" id="diet">
        <option value="">-- None --</option>
        <option value="Vegan" <?= ($currentDiet === 'Vegan') ? 'selected' : '' ?>>Vegan</option>
        <option value="Vegetarian" <?= ($currentDiet === 'Vegetarian') ? 'selected' : '' ?>>Vegetarian</option>
        <option value="High Protein" <?= ($currentDiet === 'High Protein') ? 'selected' : '' ?>>High Protein</option>
        <option value="No Red Meat" <?= ($currentDiet === 'No Red Meat') ? 'selected' : '' ?>>No Red Meat</option>
        <option value="Chud" <?= ($currentDiet === 'Chud') ? 'selected' : '' ?>>Chud</option>
    </select>
</div>
    <!-- BMI Result -->
    <div class="small-box" style="height: auto;">
        <p>BMI: <span id="bmi-value">--</span></p>
    </div>
    <div class="small-box">
        <button type="submit">Save Profile</button>
    </div>

</form>

<?php if ($successMsg): ?>
    <p style="color: green;"><?= $successMsg ?></p>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <p style="color: red;"><?= $errorMsg ?></p>
<?php endif; ?>

<script>
    const heightInput = document.querySelector('input[name="height"]');
    const weightInput = document.querySelector('input[name="current_weight"]');

    function calcBMI() {
        const h = parseFloat(heightInput.value);
        const w = parseFloat(weightInput.value);
        if (!h || !w) return;

        const bmi = (w / ((h / 100) ** 2)).toFixed(1);
        document.getElementById('bmi-value').textContent = bmi;
    }

    heightInput.addEventListener('input', calcBMI);
    weightInput.addEventListener('input', calcBMI);
</script>

</body>
</html>




</body>






</head>
</html>

