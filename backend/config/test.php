<?PHP
require_once 'database.php';

$conn = getDBConnection();
echo "Connected" . PHP_EOL;
$conn->close();
?>
