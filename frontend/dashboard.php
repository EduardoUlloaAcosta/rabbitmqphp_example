<?php
// dashboard.php - converted from html to php for functionality
// Brian Patoilo 2/26/26
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html?error=' . urlencode('Howd you get here??'));
    exit;
}

require_once __DIR__ . '/rabbitmq_helper.php';

$today = date('Y-m-d');
$plan_date = $_GET['date'] ?? $today;
$isToday = ($plan_date === $today);

$dashResponse = sendRequest([
    'type' => 'get_dashboard',
    'user_id' => $_SESSION['user_id'],
    'plan_date' => $plan_date
]);

$breakfast = [];
$lunch = [];
$dinner = [];
$totalCalories = 0;

if ($dashResponse && $dashResponse['success'] && !empty($dashResponse['meals'])) {
    foreach ($dashResponse['meals'] as $m) {
        $cal = isset($m['calories']) && $m['calories'] !== null ? round($m['calories']) : 0;
        $totalCalories += $cal;
        switch ($m['meal_type']) {
            case 'breakfast': $breakfast[] = $m; break;
            case 'lunch': $lunch[] = $m; break;
            case 'dinner': $dinner[] = $m; break;
        }
    }
}


// meal recommendation logic/ Brian Patoilo 3/4/26
$recommendations = [];
$recResponse = sendRequest([
    'type' => 'get_recommendations',
    'user_id' => $_SESSION['user_id'],
    'plan_date' => $plan_date
]);
if ($recResponse && $recResponse['success'] && !empty($recResponse['meals'])) {
    $recommendations = $recResponse['meals'];
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta name ="viewport" content ="width=device-width, initial-scale=1.0">
    <title>My Dashboard</title>
    <link rel="stylesheet" href="dashboard.css" />
    <link rel="stylesheet" href="global.css" />
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet"> <!-- added by Brian for better looking font -->
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

<div class="container">
    <h1>My Dashboard</h1>
    <input type="date" id="dashboard-date" value="<?= htmlspecialchars($plan_date) ?>" max="<?= $today ?>">

    <?php if (!$isToday): ?>
        <p class="history-label">Viewing: <?= date('F j, Y', strtotime($plan_date)) ?></p>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
    <p class="calorie-total">Total Calories: <strong><?= $totalCalories ?> cal</strong></p>

    <?php if (!empty($recommendations)): ?> <!-- displays meals recommendations in the top right -->
            <div class="recommendations">
                <strong>Recommended for You</strong>
                <ul>
                    <?php foreach ($recommendations as $rec): ?>
                        <li><a href="mealDetails.php?id=<?= $rec['id'] ?>"><?= htmlspecialchars($rec['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>


    <div class="grid">
        <div class="box1">
            <h2>Breakfast</h2>
            <?php if (empty($breakfast)): ?>
                <p class="empty-msg">No meals added</p>
            <?php else: ?>
                <?php foreach ($breakfast as $m): ?>
                    <div class="dashboard-meal">
                        <img src="<?= htmlspecialchars($m['image_url']) ?>" alt="<?= htmlspecialchars($m['name']) ?>">
                        <div>
                            <p class="dash-meal-name"><?= htmlspecialchars($m['name']) ?></p>
                            <span class="dash-meal-cal"><?= isset($m['calories']) && $m['calories'] !== null ? round($m['calories']) . ' cal' : '' ?></span>
                        </div>
                        <?php if ($isToday): ?>
                        <form method="POST" action="Removefromdash.php" class="remove-form">
                            <input type="hidden" name="daily_plan_meal_id" value="<?= $m['daily_plan_meal_id'] ?>">
                            <input type="hidden" name="plan_date" value="<?= htmlspecialchars($plan_date) ?>">
                            <button type="submit" class="remove-btn" title="Remove">Remove</button>
                        </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="box2">
            <h2>Lunch</h2>
            <?php if (empty($lunch)): ?>
                <p class="empty-msg">No meals added</p>
            <?php else: ?>
                <?php foreach ($lunch as $m): ?>
                    <div class="dashboard-meal">
                        <img src="<?= htmlspecialchars($m['image_url']) ?>" alt="<?= htmlspecialchars($m['name']) ?>">
                        <div>
                            <p class="dash-meal-name"><?= htmlspecialchars($m['name']) ?></p>
                            <span class="dash-meal-cal"><?= isset($m['calories']) && $m['calories'] !== null ? round($m['calories']) . ' cal' : '' ?></span>
                        </div>
                        <?php if ($isToday): ?>
                        <form method="POST" action="Removefromdash.php" class="remove-form">
                            <input type="hidden" name="daily_plan_meal_id" value="<?= $m['daily_plan_meal_id'] ?>">
                            <input type="hidden" name="plan_date" value="<?= htmlspecialchars($plan_date) ?>">
                            <button type="submit" class="remove-btn" title="Remove">Remove</button>
                        </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="box3">
            <h2>Dinner</h2>
            <?php if (empty($dinner)): ?>
                <p class="empty-msg">No meals added</p>
            <?php else: ?>
                <?php foreach ($dinner as $m): ?>
                    <div class="dashboard-meal">
                        <img src="<?= htmlspecialchars($m['image_url']) ?>" alt="<?= htmlspecialchars($m['name']) ?>">
                        <div>
                            <p class="dash-meal-name"><?= htmlspecialchars($m['name']) ?></p>
                            <span class="dash-meal-cal"><?= isset($m['calories']) && $m['calories'] !== null ? round($m['calories']) . ' cal' : '' ?></span>
                        </div>
                        <?php if ($isToday): ?>
                        <form method="POST" action="Removefromdash.php" class="remove-form">
                            <input type="hidden" name="daily_plan_meal_id" value="<?= $m['daily_plan_meal_id'] ?>">
                            <input type="hidden" name="plan_date" value="<?= htmlspecialchars($plan_date) ?>">
                            <button type="submit" class="remove-btn" title="Remove">Remove</button>
                        </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script> //javascript to look at previous dates (fancy, right? ;) )Brian Patoilo
    const dateInput = document.getElementById('dashboard-date');
    dateInput.addEventListener('change', function() {
        window.location.href = 'dashboard.php?date=' + this.value;
    });
</script>
<script> //stefan - javascript for navlinks for mobile menu (if u see this ill buy the whole group pizza from dominos)
    document.querySelector('.mobile-menu-button').addEventListener('click', () => {
        document.querySelector('.nav-links').classList.toggle('open');
    });
</script>
</body>
</html>
