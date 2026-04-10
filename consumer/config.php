<?php
//Brian Patoilo 2/11/26 Written to define things for project

define('RABBITMQ_HOST', '100.84.167.48');
define('RABBITMQ_PORT', 5672);
define('RABBITMQ_USER', 'admin');
define('RABBITMQ_PASS', '123');
define('RABBITMQ_VHOST', '/');

// database connection
define('DB_HOST', 'localhost');
define('DB_USER', 'testUser');
define('DB_PASS', '123');
define('DB_NAME', 'meal_planner');

define('QUEUE_NAME', 'db_queue');

//DMZ stuff added 2/22 for api connection test
define('DMZ_EXCHANGE', 'dmz_exchange');
define('DMZ_EXCHANGE_TYPE', 'topic');
define('DMZ_QUEUE', 'dmz_queue');
define('DMZ_ROUTING_KEY', '*');

?>
