<?php
// Brian Patoilo, 2/5/26 Handles meal searches.
// I needed help trying to understand the logic so I looked up a lot for these functions so if somethings wrong just ignore it.
// It calls the DMZ for API data, parses it, and inserts into Meals table
// Brian patoilo on 2/26/26. This has become our controller file that all the functions live in for the meal information. Maybe to be split up later but for now it all lives here.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/dmz_client.php';

// function to be called by handler
function handleSearchMeal($data) {
    $query = $data['query'] ?? '';
    if (empty($query)) {
        return ['success' => false, 'message' => 'No search query provided'];
    }
    echo " [*] Searching database for: $query\n";

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'Database connection failed: ' . $db->connect_error];
    }

    $searchTerm = '%' . $query . '%';
    $stmt = $db->prepare("SELECT id, api_id, name, category, area, image_url, calories FROM meals WHERE name LIKE ?");
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    $meals = [];
    while ($row = $result->fetch_assoc()) {
        $meals[] = $row;
    }
    $stmt->close();
    $db->close();

    if (empty($meals)) {
        return ['success' => false, 'message' => 'No meals found for: ' . $query];
    }

    echo " [*] Found " . count($meals) . " meals in database\n";
    return ['success' => true, 'meals' => $meals];
}


// function I looked up to make ingredients into one string in our database
function buildIngredientString($meal) {
    $parts = [];
    for ($i = 1; $i <= 20; $i++) {
        $ingredient = trim($meal["strIngredient$i"] ?? '');
        $measure = trim($meal["strMeasure$i"] ?? '');
        if ($ingredient === '') {
            continue;
        }

        if ($measure !== '') {
            $parts[] = "$measure $ingredient";
        } else {
            $parts[] = $ingredient;
        }
    }
    return implode(", ", $parts);
}

//2/23/2026
//function added by ainesh, connects to DB and grabs name and image url of first 6 meals
function handleGetMeals($data){
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error){
        return ["success" => false, "message" => "Database connection failed: " . $db->connect_error];
    }

   $stmt = $db->prepare(
       "SELECT id, name, image_url FROM meals ORDER BY name ASC LIMIT 6" //alphabetical order
);
    $stmt->execute();
    $result = $stmt->get_result();

    $meals = [];
    while ($row = $result->fetch_assoc()){
        $meals[] = $row;
    }

    $stmt->close();
    $db->close();

    return ['success' => true, 'meals' => $meals];
}

// 2/25/26 function to search meals by first letter for cronjob
function handleSearchMealByLetter($data) {
    $letter = $data['letter'] ?? '';
    if (empty($letter)) {
        return ['success' => false, 'message' => 'No letter provided'];
    }
    echo " [*] Searching meals by letter: $letter\n";

    $mealResponse = sendDmzRequest([
        'type' => 'search_meal_by_letter',
        'letter' => $letter
    ]);

    if ($mealResponse['status'] !== 'success') {
        return ['success' => false, 'message' => 'Failed to get meals from DMZ: ' . ($mealResponse['message'] ?? 'unknown error')];
    }

    $meals = $mealResponse['results']['meals'] ?? null;

    if ($meals === null || empty($meals)) {
        return ['success' => false, 'message' => 'No meals found for letter: ' . $letter];
    }

    echo "Got " . count($meals) . " meals for letter $letter\n";
    return processAndInsertMeals($meals);
}

// process logic to add meals to database for the CRONJOB
function processAndInsertMeals($meals) {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'Database connection failed: ' . $db->connect_error];
    }

    $insertedMeals = [];

    foreach ($meals as $meal) {
        $apiId = $meal['idMeal'] ?? null;
        $name = $meal['strMeal'] ?? 'Unknown';
        $category = $meal['strCategory'] ?? null;
        $area = $meal['strArea'] ?? null;
        $instructions = $meal['strInstructions'] ?? null;
        $imageUrl = $meal['strMealThumb'] ?? null;

        // check if this meal already exists in our DB
        $checkStmt = $db->prepare("SELECT id, calories FROM meals WHERE api_id = ?");
        $checkStmt->bind_param("s", $apiId);
        $checkStmt->execute();
        $existingResult = $checkStmt->get_result();

        if ($existingResult->num_rows > 0) {
            $existingMeal = $existingResult->fetch_assoc();
            $insertedMeals[] = [
                'id' => $existingMeal['id'],
                'api_id' => $apiId,
                'name' => $name,
                'category' => $category,
                'area' => $area,
                'image_url' => $imageUrl,
                'calories' => $existingMeal['calories'],
                'already_existed' => true
            ];
            $checkStmt->close();
            echo "Meal '$name' already exists in DB, skipping\n";
            continue;
        }
        $checkStmt->close();

        $ingredients = buildIngredientString($meal);

        $calories = null;
        $fdcId = null;
        $fdcResponse = sendDmzRequest([
            'type' => 'fdc_search',
            'query' => $name
        ]);

        if ($fdcResponse['status'] === 'success') {
            $calories = $fdcResponse['kcal'] ?? null;
            $fdcId = $fdcResponse['fdc_id'] ?? null;
            echo " Got calories for '$name': " . ($calories ?? "not found") . " kcal\n";
        } else {
            echo " FDC search failed for '$name', storing NULL calories\n";
        }

        $stmt = $db->prepare(
            "INSERT INTO meals (api_id, is_api, name, category, area, instructions, ingredients, image_url, fdc_id, calories)
             VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "ssssssssd",
            $apiId,
            $name,
            $category,
            $area,
            $instructions,
            $ingredients,
            $imageUrl,
            $fdcId,
            $calories
        );

        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $insertedMeals[] = [
                'id' => $newId,
                'api_id' => $apiId,
                'name' => $name,
                'category' => $category,
                'area' => $area,
                'image_url' => $imageUrl,
                'calories' => $calories,
                'already_existed' => false
            ];
            echo "Inserted meal: $name (id: $newId)\n";
        } else {
            echo "Failed to insert meal '$name': " . $stmt->error . "\n";
        }
        $stmt->close();
    }

    $db->close();

    return [
        'success' => true,
        'message' => 'Found ' . count($insertedMeals) . ' meals',
        'meals' => $insertedMeals
    ];
}

//added on 2/25/26 by ainesh, get single meal by ID for meals details page
function handleGetMealById($data){
    $id = $data['id'] ?? null;
    if(!$id){
        return ['success' => false, 'message' => 'no meal id given'];
    }

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if($db->connect_error){
        return ['success' => false, 'message' => 'db connection failed: ' . $db->connect_error];
    }

    $stmt = $db->prepare("SELECT id, name, category, area, instructions, ingredients, image_url, calories FROM meals WHERE id = ?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $result = $stmt->get_result();
    $meal = $result->fetch_assoc();

    $stmt->close();
    $db->close();

    if (!$meal){
        return ['success' => false, 'message' => "meal not found"];
    }

    return ['success' => true, 'meal' => $meal];
}

//added on 2/25/26 by ainesh, gets reviews for a meal
function handleGetReviews($data){
    $meal_id = $data['meal_id'] ?? null;
    if (!$meal_id){
        return ['success' => false, 'message' => 'no meal id given' ];
    }

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if($db->connect_error){
        return ['success' => false, 'message' => 'db connection failed: ' . $db->connect_error];
    }

    $stmt = $db->prepare("SELECT r.id, r.rating, r.review_text, r.created_at, u.username FROM reviews r JOIN users u ON r.user_id = u.user_id WHERE r.meal_id = ? ORDER BY r.created_at DESC");
    $stmt->bind_param("i", $meal_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $reviews = [];
    while ($row = $result->fetch_assoc()){
        $reviews[] = $row;
    }

    $stmt->close();
    $db->close();

    return ['success' => true, 'reviews' => $reviews];
}

//added on 2/25/26 by ainesh, handles posting a review
function handlePostReview($data){
    $meal_id = $data['meal_id'] ?? null;
    $user_id = $data['user_id'] ?? null;
    $rating = $data['rating'] ?? null;
    $review_text = $data['review_text'] ?? '';

    if (!$meal_id || !$user_id || !$rating){
        return ['success' => false, 'message' => 'missing required fields'];
    }

    if ($rating < 1 || $rating > 5){
        return ['success' => false, 'message' => 'rating has to be between 1 and 5'];
    }

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if($db->connect_error){
        return ['success' => false, 'message' => 'db connection failed: ' . $db->connect_error];
        }

        $stmt = $db->prepare("INSERT INTO reviews (user_id, meal_id, rating, review_text)
        VALUES (?, ?, ?, ?)
<<<<<<< HEAD
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_text = VALUES(review_text)");
=======
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_text = VALUES(review_text)"); //fix reviews duplicate error that crashes rabbitmq 3/4/26 brian
>>>>>>> master
        $stmt->bind_param("iiis", $user_id, $meal_id, $rating, $review_text);

        if($stmt->execute()){
            $stmt->close();
            $db->close();
            return ['success' => true, 'message' => 'review posted! :)'];
        } else {
            $stmt->close();
            $db->close();
            return  ['success' => false, 'message' => 'failed to post review :c'];
        }

}

function handleAddToDashboard($data) { //function to add meals to use dashboard Brian Patoilo
    $user_id = $data['user_id'] ?? null;
    $meal_id = $data['meal_id'] ?? null;
    $meal_type = $data['meal_type'] ?? null;
    $plan_date = $data['plan_date'] ?? null;

    if (!$user_id || !$meal_id || !$meal_type || !$plan_date) {
        return ['success' => false, 'message' => 'missing required fields'];
    }

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'db connection failed: ' . $db->connect_error];
    }

    //creates daily plan for user
    $stmt = $db->prepare("SELECT id FROM daily_plans WHERE user_id = ? AND plan_date = ?");
    $stmt->bind_param("is", $user_id, $plan_date);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $daily_plan_id = $row['id'];
    } else {
        $stmt->close();
        $stmt = $db->prepare("INSERT INTO daily_plans (user_id, plan_date) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $plan_date);
        $stmt->execute();
        $daily_plan_id = $stmt->insert_id;
    }
    $stmt->close();

    // add the meal to the daily plan
    $stmt = $db->prepare("INSERT INTO daily_plan_meals (daily_plan_id, meal_id, meal_type) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $daily_plan_id, $meal_id, $meal_type);

    if ($stmt->execute()) {
        $stmt->close();
        $db->close();
        return ['success' => true, 'message' => 'Meal added to dashboard'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        $db->close();
        return ['success' => false, 'message' => 'Failed to add meal: ' . $error];
    }
}

// get users meals for a date. getting history
function handleGetDashboard($data) {
    $user_id = $data['user_id'] ?? null;
    $plan_date = $data['plan_date'] ?? null;

    if (!$user_id || !$plan_date) {
        return ['success' => false, 'message' => 'missing user_id or plan_date'];
    }

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'db connection failed: ' . $db->connect_error];
    }

    $stmt = $db->prepare(
        "SELECT dpm.id AS daily_plan_meal_id, dpm.meal_type, m.id AS meal_id, m.name, m.image_url, m.calories
         FROM daily_plans dp
         JOIN daily_plan_meals dpm ON dp.id = dpm.daily_plan_id
         JOIN meals m ON dpm.meal_id = m.id
         WHERE dp.user_id = ? AND dp.plan_date = ?
         ORDER BY dpm.meal_type, dpm.added_at"
    );
    $stmt->bind_param("is", $user_id, $plan_date);
    $stmt->execute();
    $result = $stmt->get_result();

    $meals = [];
    while ($row = $result->fetch_assoc()) {
        $meals[] = $row;
    }

    $stmt->close();
    $db->close();

    return ['success' => true, 'meals' => $meals];
}

// Brian P - remove the meal from dash
function handleRemoveFromDashboard($data) {
    $user_id = $data['user_id'] ?? null;
    $daily_plan_meal_id = $data['daily_plan_meal_id'] ?? null;

    if (!$user_id || !$daily_plan_meal_id) {
        return ['success' => false, 'message' => 'missing required fields'];
    }

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'db connection failed: ' . $db->connect_error];
    }

    // verify daily plan id and user id
    $stmt = $db->prepare(
        "DELETE dpm FROM daily_plan_meals dpm
         JOIN daily_plans dp ON dpm.daily_plan_id = dp.id
         WHERE dpm.id = ? AND dp.user_id = ?"
    );
    $stmt->bind_param("ii", $daily_plan_meal_id, $user_id);

    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        $stmt->close();
        $db->close();
        if ($affected > 0) {
            return ['success' => true, 'message' => 'Meal removed from dashboard'];
        } else {
            return ['success' => false, 'message' => 'Meal not found or not yours'];
        }
    } else {
        $stmt->close();
        $db->close();
        return ['success' => false, 'message' => 'Failed to remove meal'];
    }
}

<<<<<<< HEAD
// Stefan - 3/3 - all this stuff is for userProfile, the frontend of it is in userPage.php
function handleUpdateUserProfile($data){
    $user_id = $data['user_id'] ?? null;
    $height = $data['height'] ?? null;
    $current_weight = $data['current_weight'] ?? null;
    $goal_weight = $data['goal_weight'] ?? null;

=======
//test function added 3/5 for recommendations on dashboard Brian Patoilo
function handleGetRecommendations($data) {
    $user_id = $data['user_id'];

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'db connection failed: ' . $db->connect_error];
    }

    //adding diet retrieval for meal recommendations
    $dietStuff = $db->prepare("SELECT diet_name FROM user_diets WHERE user_id = ?");
    $dietStuff->bind_param("i", $user_id);
    $dietStuff->execute();
    $dietResult = $dietStuff->get_result();

    $categories = [];
    while ($row = $dietResult->fetch_assoc()) {
        $mapped = getDietCategories($row['diet_name']);
        $categories = array_merge($categories, $mapped);
    }

    $categories = array_unique($categories);
    $dietStuff->close();

     if (!empty($categories)) {
        $placeholders = implode(',', array_fill(0, count($categories), '?'));
        $sql = "SELECT id, name FROM meals WHERE category IN ($placeholders) ORDER BY RAND() LIMIT 3";
        $types = str_repeat('s', count($categories));
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$categories);
    } else {
        $stmt = $db->prepare("SELECT id, name FROM meals ORDER BY RAND() LIMIT 3"); //reuse random logic for people who have not selected a diet
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $meals = [];
    while ($row = $result->fetch_assoc()) {
        $meals[] = $row;
    }

    $stmt->close();
    $db->close();

    return ['success' => true, 'meals' => $meals];
}

// adding funtion 3/9 for diet preferences. I looked up how to do this and I found mapping the categories of the meals to a specific diary
function getDietCategories($diet_name) {
    $map = [
        'Vegan'        => ['Vegan'],
        'Vegetarian'   => ['Vegetarian', 'Vegan'],
        'High Protein' => ['Chicken', 'Beef', 'Lamb', 'Seafood', 'Goat', 'Pork'],
        'No Red Meat'  => ['Chicken', 'Seafood', 'Vegetarian', 'Vegan'],
        'Chud'         => ['Dessert', 'Miscellaneous', 'Pasta', 'Side', 'Starter', 'Breakfast'],
    ];

    return $map[$diet_name] ?? [];
}
// Stefan - 3/3 - all this stuff is for userProfile, the frontend of it is in userPage.php
function handleUpdateUserProfile($data){
    $user_id = $data['user_id'] ?? null;
    $height = $data['height'] ?? null;
    $current_weight = $data['current_weight'] ?? null;
    $goal_weight = $data['goal_weight'] ?? null;

>>>>>>> master
    if (!$user_id) {
        return ['success' => false, 'message' => 'missing user_id'];
    }

      $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'db connection failed: ' . $db->connect_error];
    }
       $stmt = $db->prepare("
       UPDATE users SET height = ?,
       current_weight = ?,
       goal_weight = ?
       WHERE user_id = ?");
    $stmt->bind_param("dddi", $height, $current_weight, $goal_weight, $user_id);

    if ($stmt->execute()) {
        $stmt->close();
        $db->close();
        //Profilr update was successful
        return ['success' => true, 'message' => 'Profile updated!'];
    } else {
        $stmt->close();
        $db->close();
        //update was unsuccessful
        return ['success' => false, 'message' => 'failed to update profile: ' . $stmt->error];
    }
}

// Get existing user metrics
function handleGetUserProfile($data){
    $user_id = $data['user_id'] ?? null;

    if (!$user_id){
        return ['success' => false, 'message' => 'missing user_id'];
    }

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'db connection failed: ' . $db->connect_error];
    }

     $stmt = $db->prepare("
     SELECT height,
     current_weight,
     goal_weight FROM users
     WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();

    $stmt->close();
    $db->close();

    return ['success' => true, 'profile' => $profile];
}
function handleUpdateUserDiet($data) {
    $user_id = $data['user_id'] ?? null;
    $diet_name = $data['diet_name'] ?? null;

    if (!$user_id || !$diet_name) {
        return ['success' => false, 'message' => 'missing fields'];
    }

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'db connection failed'];
    }

    $del = $db->prepare("DELETE FROM user_diets WHERE user_id = ?");
    $del->bind_param("i", $user_id);
    $del->execute();
    $del->close();

    $stmt = $db->prepare("INSERT INTO user_diets (user_id, diet_name) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $diet_name);
    $stmt->execute();

    $stmt->close();
    $db->close();

    return ['success' => true, 'message' => 'Diet saved'];
}

function handleDeleteUserDiet($data) {
    $user_id = $data['user_id'] ?? null;

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'db connection failed'];
    }

    $stmt = $db->prepare("DELETE FROM user_diets WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $stmt->close();
    $db->close();

    return ['success' => true, 'message' => 'Diet preference removed'];
}

function handleGetUserDiet($data) {
    $user_id = $data['user_id'] ?? null;

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'db connection failed'];
    }

    $stmt = $db->prepare("SELECT diet_name FROM user_diets WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();
    $db->close();

    return ['success' => true, 'diet_name' => $row['diet_name'] ?? ''];
}

?>
