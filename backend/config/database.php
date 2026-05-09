 <?php
 define('DB_HOST', '100.84.167.48');
 define('DB_USER', 'testUser1');
 define('DB_PASS', '123');
 define('DB_NAME', 'meal_planner');

 function getDBConnection() {
     $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);


     if ($conn->connect_error) {

         die("Connection failed: " . $conn->connect_error);
     }
     return $conn;
 }
 ?>
