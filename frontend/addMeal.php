<?php
#Brian Patoilo. This is the custom meal adder page. I am basing it off of the meal details page to keep the same look across pages.
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html?error=' . urlencode('how did you even arrive here twin.'));
    exit;
}

require_once __DIR__ . '/rabbitmq_helper.php';

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = sendRequest([
        'type' => 'add_custom_meal',
        'user_id' => $_SESSION['user_id'],
        'name' => $_POST['name'],
        'category' => $_POST['category'],
        'area' => $_POST['area'],
        'instructions' => $_POST['instructions'],
        'ingredients' => $_POST['ingredients'],
        'calories' => $_POST['calories'],
        'image_url' => !empty($_POST['image_url']) ? $_POST['image_url'] : 'no-image.png' #placeholder link didn't work so i am just saving a local file called no-image.png
    ]);

    if ($response['success']) {
        $successMsg = 'meal added!';
    } else {
        $errorMsg = $response['message'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Custom Meal</title>
    <meta name ="viewport" content ="width=device-width, initial-scale=1.0">
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

<div class="mealDetail">
    <h1>Add a Custom Meal</h1>

    <?php if ($successMsg): ?>
        <p style="color: green;"><?= $successMsg ?></p>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <p style="color: red;"><?= $errorMsg ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Meal Name:
            <input type="text" name="name" required placeholder="enter name of meal please">
        </label>

        <label>Which Category?:
            <select name="category" required>
                <option value="">pick one please</option>
                <option value="Chicken">Chicken</option>
                <option value="Beef">Beef</option>
                <option value="Pork">Pork</option>
                <option value="Lamb">Lamb</option>
                <option value="Seafood">Seafood</option>
                <option value="Vegetarian">Vegetarian</option>
                <option value="Vegan">Vegan</option>
                <option value="Pasta">Pasta</option>
                <option value="Dessert">Dessert</option>
                <option value="Breakfast">Breakfast</option>
                <option value="Side">Side</option>
                <option value="Miscellaneous">Miscellaneous</option>
            </select>
        </label>

        <label>Area option:
            <select name="area" required>
                <option value="">pick one</option>
                <option value="American">American</option>
                <option value="British">British</option>
                <option value="Canadian">Canadian</option>
                <option value="Chinese">Chinese</option>
                <option value="Croatian">Croatian</option>
                <option value="Dutch">Dutch</option>
                <option value="Egyptian">Egyptian</option>
                <option value="Filipino">Filipino</option>
                <option value="French">French</option>
                <option value="Greek">Greek</option>
                <option value="Indian">Indian</option>
                <option value="Irish">Irish</option>
                <option value="Italian">Italian</option>
                <option value="Jamaican">Jamaican</option>
                <option value="Japanese">Japanese</option>
                <option value="Kenyan">Kenyan</option>
                <option value="Malaysian">Malaysian</option>
                <option value="Mexican">Mexican</option>
                <option value="Moroccan">Moroccan</option>
                <option value="Norwegian">Norwegian</option>
                <option value="Polish">Polish</option>
                <option value="Portuguese">Portuguese</option>
                <option value="Russian">Russian</option>
                <option value="Spanish">Spanish</option>
                <option value="Thai">Thai</option>
                <option value="Tunisian">Tunisian</option>
                <option value="Turkish">Turkish</option>
                <option value="Ukrainian">Ukrainian</option>
                <option value="Vietnamese">Vietnamese</option>
            </select>
        </label>

        <label>Ingredients (comma separated):
            <textarea name="ingredients" required placeholder="ex: 2 cups rice, 1 lb chicken, 1 tsp salt"></textarea>
        </label>

        <label>Instructions to make meal:
            <textarea name="instructions" required placeholder="write out the steps"></textarea>
        </label>

        <label>Calories in meal:
            <input type="number" name="calories" required placeholder="enter calorie count">
        </label>

        <label>Image URL !!!WARNING THIS HAS TO BE AN ACTUAL IMAGE LINK, not an image file!!! (optional):
            <input type="text" name="image_url" placeholder="paste image link or leave blank">
        </label>

        <button type="submit">Add Meal</button>
    </form>
</div>
<script>
    document.querySelector('.mobile-menu-button').addEventListener('click', () => {
        document.querySelector('.nav-links').classList.toggle('open');
    });
</script>
<!-- repsonsive design script -->
</body>
</html>
