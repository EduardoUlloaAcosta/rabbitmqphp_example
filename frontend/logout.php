<?php
//Brian P, logout, not rocket science
session_start();
session_destroy();
header('Location: index.html?success=' . urlencode('Goodbye friend'));
exit;

?>
