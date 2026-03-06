<?php
//mealDetails page created by ainesh 2/25/2026
//updated by Brian Patoilo 2/26 for better display, dashboard, and ingredients
session_start();
if(!isset($_SESSION['user_id'])){
    header('Location: index.html?error=' . urlencode ('how did you even arrive here twin.'));
    exit;
}

require_once __DIR__ . '/rabbitmq_helper.php';

$meal_id = $_GET['id'] ?? null;
if(!$meal_id){
    header('Location: search.php');
    exit;
}

$mealResponse = sendRequest(['type' => 'get_meal_by_id', 'id' => $meal_id]);
$meal = $mealResponse['meal'] ?? null;

$reviewsResponse = sendRequest(['type' => 'get_reviews', 'meal_id' => $meal_id]);
$reviews = $reviewsResponse['reviews'] ?? [];

$dashboardMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_dashboard'])) {
    $dashResponse = sendRequest([
        'type' => 'add_to_dashboard',
        'user_id' => $_SESSION['user_id'],
        'meal_id' => $meal_id,
        'meal_type' => $_POST['meal_type'],
        'plan_date' => date('Y-m-d')
    ]);
    if ($dashResponse['success']) {
        header('Location: dashboard.php');
        exit;
    } else {
        $dashboardMsg = '<p class="error-msg">' . htmlspecialchars($dashResponse['message']) . '</p>';
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $meal ? htmlspecialchars($meal['name']) : 'Meal Details' ?></title>
    <link rel="stylesheet" href="searchstyle.css" />
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
                <li><a href="calorieTrackerPage.php">Calorie Tracker</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
            </ul>
            <div class="logout-btn">
                <a href="logout.php">Logout</a>
            </div>
        </nav>
    </header>
</div>

<?php if ($meal): ?> <!-- added ability to add to dash and ingredients displays-->
<div class="mealDetail">
    <img src="<?= htmlspecialchars($meal['image_url']) ?>" alt="<?= htmlspecialchars($meal['name']) ?>">
    <h1><?= htmlspecialchars($meal['name']) ?></h1>
    <p><strong>Category:</strong> <?= htmlspecialchars($meal['category'] ?? 'N/A') ?></p>
    <p><strong>Area:</strong> <?= htmlspecialchars($meal['area'] ?? 'N/A') ?></p>
    <p><strong>Calories:</strong> <?= isset($meal['calories']) && $meal['calories'] !== null ? round($meal['calories']) . ' kcal' : 'N/A' ?></p>

    <h2>Ingredients</h2>
    <?php if (!empty($meal['ingredients'])): ?>
        <ul class="ingredients-list">
            <?php
            $ingredients = explode(', ', $meal['ingredients']);
            foreach ($ingredients as $ing):
                $ing = trim($ing);
                if ($ing !== ''):
            ?>
                <li><?= htmlspecialchars($ing) ?></li>
            <?php
                endif;
            endforeach;
            ?>
        </ul>
    <?php else: ?>
        <p>No ingredients listed.</p>
    <?php endif; ?>

    <h2>Instructions</h2>
    <p><?= nl2br(htmlspecialchars($meal['instructions'] ?? '')) ?></p>

    <!-- add to dash logic Brian Patoilo -->
    <div class="add-to-dashboard">
        <h2>Add to Today's Dashboard</h2>
        <?= $dashboardMsg ?>
        <form method="POST">
            <input type="hidden" name="add_to_dashboard" value="1">
            <label>Meal Type:
                <select name="meal_type" required>
                    <option value="breakfast">Breakfast</option>
                    <option value="lunch">Lunch</option>
                    <option value="dinner">Dinner</option>
                </select>
            </label>
            <button type="submit">Add to Dashboard</button>
        </form>
    </div>
</div>



<div class="reviewSection">
    <h2>Reviews</h2>
    <form method="POST" action="postReview.php">
        <input type="hidden" name="meal_id" value="<?= $meal_id ?>">
        <label>Rating (1-5):
            <select name="rating">
                <option>1</option>
                <option>2</option>
                <option>3</option>
                <option>4</option>
                <option>5</option>
            </select>
        </label>
        <textarea name="review_text" placeholder="Yay or Nay?"></textarea>
        <button type="submit">Submit Review</button>
    </form>

    <?php if (empty($reviews)): ?>
        <p>No reviews yet. Be the first!</p>
    <?php else: ?>
        <?php foreach ($reviews as $review): ?>
        <div class="reviewCard">
            <strong><?= htmlspecialchars($review['username']) ?></strong>
            <span><?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5 - $review['rating']) ?></span>
            <p><?= htmlspecialchars($review['review_text']) ?></p>
            <small><?= $review['created_at'] ?></small>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php else: ?>
    <p>Meal not found.</p>
<?php endif; ?>
</body>
</html>
