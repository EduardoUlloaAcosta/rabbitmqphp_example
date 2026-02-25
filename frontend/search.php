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
    <link rel="stylesheet" href="searchstyle.css" />
</div>


<!-- 2/23 added by ainesh, changed on 2/25 by ainesh -->

<div class = "mealsGrid">
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
      echo '<img src="' . htmlspecialchars($meal['image_url']) . '" alt "' . htmlspecialchars($meal['name']) . '">';
      echo '<p>' . htmlspecialchars($meal['name']) . '</p>';
      echo '</div>';
    }
  } else{
    echo '<p>No meals found</p>';
  }
  ?>

</div>
