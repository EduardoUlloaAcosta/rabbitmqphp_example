<?php
// test search page/home page to bring after login
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html?error=' . urlencode('Howd you get here??'));
    exit;
}
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
<!-- edited by ainesh on 2/25 -->
<div class="box">
    <h1>Search For Meals</h1>
    <form method="GET" action="search.php">
    <input
        type="text"
        name="query"
        placeholder="Search for meals (e.g. chicken, salad, pasta...)"
        value="<?php echo htmlspecialchars($_GET['query'] ?? ''); ?>"
        />
        <button type="submit">Search</button>
    </form>
<!--adding button to get to add meal page, brian  -->
    <a href="addMeal.php" class="add-meal-btn">Dont see what you need? Add custom meal</a>
    <link rel="stylesheet" href="searchstyle.css" />
</div>
<!-- 2/23 added by ainesh, changed on 2/25 by ainesh -->
<div class="mealsGrid">
  <?php
  require_once __DIR__ . '/rabbitmq_helper.php';
    $query = $_GET['query'] ?? '';
    if(!empty($query)){
        $response = sendRequest(['type' => 'search_meal', 'query' => $query]);
    } else {
        $response = sendRequest(['type' => 'get_meals']);
    }
  if ($response && $response['success'] && !empty($response['meals'])){
    foreach ($response['meals'] as $meal){
      echo '<div class="mealCard">';
      echo '<a href="mealDetails.php?id=' . $meal['id'] . '">';
      echo '<img src="' . htmlspecialchars($meal['image_url']) . '" alt="' . htmlspecialchars($meal['name']) . '">';
      echo '<p>' . htmlspecialchars($meal['name']) . '</p>';
      echo '</a>';
      echo '</div>';
    }
  } else{
    echo '<p>No meals found</p>';
  }
  ?>
</div>
<script>
    document.querySelector('.mobile-menu-button').addEventListener('click', () => {
        document.querySelector('.nav-links').classList.toggle('open');
    });
</script>
</body>
</html>
