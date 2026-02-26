<?php
// Brian Patoilo, 2/22/26. Handles meal searches.
// I needed help trying to understand the logic so I looked up a lot for these functions so if somethings wrong just ignore it.
// It calls the DMZ for API data, parses it, and inserts into Meals table

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

        $stmt = $db->prepare("INSERT INTO reviews (user_id, meal_id, rating, review_text) VALUES(?, ?, ?, ?)");
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

?>
