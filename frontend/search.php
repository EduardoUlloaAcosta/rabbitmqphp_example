<?php
// test search page/home page to bring after login

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html?error=' . urlencode('Howd you get here??'));
    exit;
}
?>
<!DOCTYPE html>


<div class="box">
    <p>Search For Meals</p>



<div class="topnav">
  <input type="text" placeholder="Search for meals (e.g, chicken, pasta, salad....">
  <link rel="stylesheet" href="searchstyle.css">
</div>





</div>
