<?php
//Brian Patoilo 2/11/26 Written to define things for project
define('RABBITMQ_HOST', '100.71.114.73');   // <-- Change to your RabbitMQ VM's IP
define('RABBITMQ_PORT', 5672);
define('RABBITMQ_USER', 'admin');      // <-- Change to your RabbitMQ username
define('RABBITMQ_PASS', '123');      // <-- Change to your RabbitMQ password
define('RABBITMQ_VHOST', '/');

// ─── Database Connection (local on this VM) ───
define('DB_HOST', 'localhost');
define('DB_USER', 'testUser');
define('DB_PASS', '123');
define('DB_NAME', 'meal_planner');
// ─── Queue Names ───
define('QUEUE_NAME', 'db_queue');
?>
