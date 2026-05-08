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
    $stmt = $db->prepare("select id, api_id, name, category, area, image_url, calories from meals where name LIKE ?");
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

// 2/25/26 function to search meals by first letter for cronjob, Brian Patoilo
function handleSearchMealByLetter($data) {
    $letter = $data['letter'] ?? '';
    if (empty($letter)) {
        return ['success' => false, 'message' => 'No letter provided'];
    }
    echo "Search meal by this letter: $letter\n";

    $mealResponse = sendDmzRequest([
        'type' => 'search_meal_by_letter',
        'letter' => $letter
    ]);

    if ($mealResponse['status'] !== 'success') {
        return ['success' => false, 'message' => 'failure to get info - ' . ($mealResponse['message'] ?? 'unknown error')];
    }

    $meals = $mealResponse['results']['meals'] ?? null;

    if ($meals === null || empty($meals)) {
        return ['success' => false, 'message' => 'No meals found for letter: ' . $letter];
    }

    echo "Got " . count($meals) . " meals for letter $letter\n";
    return processAndInsertMeals($meals);
}

// process logic to add meals to database for the cronjob
function processAndInsertMeals($meals) {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'database not connnected: ' . $db->connect_error];
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
        $checkStmt = $db->prepare("select id, calories from meals where api_id = ?");
        $checkStmt->bind_param("s", $apiId);
        $checkStmt->execute();
        $existingResult = $checkStmt->get_result();

        if ($existingResult->num_rows > 0) { //check to see if its already added
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
            echo "Meal '$name' already exists in DB\n";
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

        $stmt = $db->prepare( //all collected info from api
            "insert into meals (api_id, is_api, name, category, area, instructions, ingredients, image_url, fdc_id, calories)
             values (?, 1, ?, ?, ?, ?, ?, ?, ?, ?)"
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
            echo "inserted new meal: $name (id: $newId)\n";
        } else {
            echo "failure to insert meal '$name': " . $stmt->error . "\n";
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

        ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_text = VALUES(review_text)"); //fix reviews duplicate error that crashes rabbitmq 3/4/26 brian

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
    $stmt = $db->prepare("select id from daily_plans where user_id = ? and plan_date = ?");
    $stmt->bind_param("is", $user_id, $plan_date);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $daily_plan_id = $row['id'];
    } else {
        $stmt->close();
        $stmt = $db->prepare("insert into daily_plans (user_id, plan_date) values (?, ?)");
        $stmt->bind_param("is", $user_id, $plan_date);
        $stmt->execute();
        $daily_plan_id = $stmt->insert_id;
    }
    $stmt->close();

    // add the meal to the daily plan for the user to track multiple meals
    $stmt = $db->prepare("insert into daily_plan_meals (daily_plan_id, meal_id, meal_type) values (?, ?, ?)");
    $stmt->bind_param("iis", $daily_plan_id, $meal_id, $meal_type);

    if ($stmt->execute()) {
        $stmt->close();
        $db->close();
        return ['success' => true, 'message' => 'meal added to user dashboard'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        $db->close();
        return ['success' => false, 'message' => 'Failed to add meal: ' . $error];
    }
}

// get users meals for a date. getting history Brian Patoilo
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

    $stmt = $db->prepare( //This some complex shit that I did look up how to do. It is a join between the daily plans, meals, and daily plan meals tables to display everything. I am putting this here to not forget what it does if asked about it.
        "select dpm.id as daily_plan_meal_id, dpm.meal_type, m.id as meal_id, m.name, m.image_url, m.calories
         from daily_plans dp
         join daily_plan_meals dpm ON dp.id = dpm.daily_plan_id
         join meals m ON dpm.meal_id = m.id
         where dp.user_id = ? AND dp.plan_date = ?
         order by dpm.meal_type, dpm.added_at"
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

    // verify daily plan id and user id to make sure correct delete
    $stmt = $db->prepare(
        "delete dpm from daily_plan_meals dpm
         join daily_plans dp on dpm.daily_plan_id = dp.id
         where dpm.id = ? and dp.user_id = ?"
    );
    $stmt->bind_param("ii", $daily_plan_meal_id, $user_id);

    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        $stmt->close();
        $db->close();
        if ($affected > 0) {
            return ['success' => true, 'message' => 'meal removed from dash'];
        } else {
            return ['success' => false, 'message' => 'meal issue'];
        }
    } else {
        $stmt->close();
        $db->close();
        return ['success' => false, 'message' => 'failed to remove meal, prob connection error'];
    }
}

//test function added 3/5 for recommendations on dashboard Brian Patoilo
function handleGetRecommendations($data) {
    $user_id = $data['user_id'];

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'db connection failed: ' . $db->connect_error];
    }

    //adding diet retrieval for meal recommendations
    $dietStuff = $db->prepare("select diet_name from user_diets where user_id = ?");
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
        $sql = "SELECT id, name FROM meals WHERE category IN ($placeholders) ORDER BY RAND() LIMIT 3"; //searched this line to show how to display random options
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
function handleGetUserProfile($data){ //stefan made
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
function handleUpdateUserDiet($data) { //stefan made
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

function handleDeleteUserDiet($data) { //stefan and brian worked on together
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

    return ['success' => true, 'message' => 'Diet preference deleted'];
}

function handleGetUserDiet($data) { //stefan and brian worked on together
    $user_id = $data['user_id'] ?? null;

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'db connection failed'];
    }

    $stmt = $db->prepare("select diet_name from user_diets where user_id = ? limit 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();
    $db->close();

    return ['success' => true, 'diet_name' => $row['diet_name'] ?? ''];
}

//4/13/26 brian. Adding a function to create custom meals
function CustomMealMaker($data) {
    $user_id = $data['user_id'] ?? null;
    $name = $data['name'] ?? null;
    $category = $data['category'] ?? null;
    $area = $data['area'] ?? null;
    $instructions = $data['instructions'] ?? null;
    $ingredients = $data['ingredients'] ?? null;
    $calories = $data['calories'] ?? null;
    $image_url = $data['image_url'] ?? null;

    if (!$user_id || !$name || !$category || !$area || !$instructions || !$ingredients || !$calories) { //check for empty fields because I don't want errors
        return ['success' => false, 'message' => 'one or more field left empty'];
    }

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'db connection failed'];
    }

    $stmt = $db->prepare("insert into meals (is_api, created_by, name, category, area, instructions, ingredients, image_url, calories) values (0, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssd", $user_id, $name, $category, $area, $instructions, $ingredients, $image_url, $calories);


    if ($stmt->execute()) {
        $stmt->close();
        $db->close();
        return ['succes' => true, 'message' => 'meal added'];
    } else {
        $stmt->close();
        $db->close();
        return ['succes' => false, 'mesage' => 'failed to add'];
    }


}
//function to get stats for journey page - Added by Ben
function handleSearchUser($data) {
    $username = $data['query'] ?? '';
    var_dump($data);
    if (empty($username)) {
        return ['success' => false, 'message' => 'No search query provided'];
    }
    echo "Searching database for: $username\n";

    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'Database connection failed: ' . $db->connect_error];
    }
//was doing where is name = before swapped it -Stefan
    $searchTerm = '%' . $username . '%';
    $stmt = $db->prepare("select username, current_weight, goal_weight, height from users where username LIKE ?");
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile= $result->fetch_assoc();
    $stmt->close();
    $db->close();

    if (!$profile) {
        return ['success' => false, 'message' => 'no user found: '];
    }

    return ['success' => true, 'profile' => $profile];
}

?>
