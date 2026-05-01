<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html?error=' . urlencode('Howd you get here??'));
    exit;
}

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
            <div class="logout-btn">
                <a href="logout.php">Logout</a>
            </div>
        </nav>
    </header>
</div>
<div class="box">
    <h1>Search For User</h1>
    <form method="GET" action="userjourneyPage.php">
    <input
        type="text"
        name="query"
        placeholder="Search for User...ex.)Bob, Chud)"
        value="<?php echo htmlspecialchars($_GET['query'] ?? ''); ?>"
        />
        <button type="submit">Search</button>
    </form>
</div>

<?php
require_once __DIR__ . '/rabbitmq_helper.php';
$query = $_GET['query'] ?? '';
$profile =null;

    if(!empty($query)){
        $response = sendRequest(['type' => 'search_user_stats', 'username' => $query]);
        var_dump($response);
        $profile= $response['profile'] ?? null;
    }

    if(!empty($profile)){?>
        <div class="small-box">
        <p><b>Username:</b> <?= htmlspecialchars($profile['username'])?></p>
        </div>
        <div class="small-box">
        <p><b>Username:</b> <?= htmlspecialchars($profile['height'])?>cm</p>
        </div>
        <div class="small-box">
        <p><b>Username:</b> <?= htmlspecialchars($profile['current_weight'])?>lbs</p>
        </div>
        <div class="small-box">
        <p><b>Username:</b> <?= htmlspecialchars($profile['goal_weight'])?>lbs</p>
        </div>
<?php}
?>
<!--spent like 3 hours somethings still broken...well the code it was and it's cuz i was missing a bracket on a line but trying to login and getting this
https://100.100.135.97/index.html?error=Connection+error%3A+stream_socket_client%28%29%3A+Unable+to+connect+to+tcp%3A%2F%2F100.79.180.77%3A5672+%28Connection+timed+out%29

check back later - Stefan

tried seeing if it was an apache issue so reset still doesnt work it didnt work

The issue was in FEconfig needed to make the same changes the others to the RABBITMQ_HOST
-->


</body>
</html>


