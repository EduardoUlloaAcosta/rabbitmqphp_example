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
    echo " [*] Searching meals for: $query\n";

    // searching TheMealDB
    $mealResponse = sendDmzRequest([
        'type' => 'search_meal',
        'query' => $query
    ]);

    if ($mealResponse['status'] !== 'success') {
        return ['success' => false, 'message' => 'Failed to get meals from DMZ: ' . ($mealResponse['message'] ?? 'unknown error')];
    }

    $meals = $mealResponse['results']['meals'] ?? null;

    if ($meals === null || empty($meals)) {
        return ['success' => false, 'message' => 'No meals found for: ' . $query];
    }

    echo " [*] Got " . count($meals) . " meals from TheMealDB\n";

    // Connect to the database to add meals
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        return ['success' => false, 'message' => 'Database connection failed: ' . $db->connect_error];
    }

    $insertedMeals = [];

    // Loop for each meal and add into database
    foreach ($meals as $meal) {
        $apiId = $meal['idMeal'] ?? null;
        $name = $meal['strMeal'] ?? 'Unknown';
        $category = $meal['strCategory'] ?? null;
        $area = $meal['strArea'] ?? null;
        $instructions = $meal['strInstructions'] ?? null;
        $imageUrl = $meal['strMealThumb'] ?? null;

        // check if this meal already exists in our DB (by api_id)
        $checkStmt = $db->prepare("SELECT id, calories FROM meals WHERE api_id = ?");
        $checkStmt->bind_param("s", $apiId);
        $checkStmt->execute();
        $existingResult = $checkStmt->get_result();

        if ($existingResult->num_rows > 0) {
            // meal already in DB, just grab it and add to results
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
            echo " [*] Meal '$name' already exists in DB, skipping insert\n";
            continue;
        }
        $checkStmt->close();
        // ingredients all one string
        $ingredients = buildIngredientString($meal);

        // to be worked on. Calorie from FDC call
        $calories = null;
        $fdcId = null;
        $fdcResponse = sendDmzRequest([
            'type' => 'fdc_search',
            'query' => $name
        ]);

        echo "fdc response: " . json_encode($fdcResponse) . "\n";

        if ($fdcResponse['status'] === 'success') {
            $calories = $fdcResponse['kcal'] ?? null;
            $fdcId = $fdcResponse['fdc_id'] ?? null;
            echo " [*] Got calories for '$name': " . ($calories ?? "not found") . " kcal\n";
        } else {
            echo " [!] FDC search failed for '$name', storing NULL calories\n";
        }

        // Step 6: Insert into Meals table
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
            echo " [+] Inserted meal: $name (id: $newId)\n";
        } else {
            echo " [!] Failed to insert meal '$name': " . $stmt->error . "\n";
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
       "SELECT name, image_url FROM meals ORDER BY name ASC LIMIT 6" //alphabetical order
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
?>
