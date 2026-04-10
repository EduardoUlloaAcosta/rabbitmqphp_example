<?php
//Brian Patoilo 2/11/26 database config caller

require_once __DIR__ . '/config.php';

function getDB() {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception("database not there idiot" . db->connect_error);
    }
    $db->set_charset("utf8mb4");
    return $db;
}
?>
