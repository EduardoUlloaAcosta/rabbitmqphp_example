<?php
//Brian Patoilo 2/11/26 Written to define things for project
<<<<<<< Ainesh
define('RABBITMQ_HOST', '100.77.247.65'); //changed by ainesh for local testing
=======
define('RABBITMQ_HOST', '100.71.114.73');
>>>>>>> master
define('RABBITMQ_PORT', 5672);
define('RABBITMQ_USER', 'admin');
define('RABBITMQ_PASS', '123');
define('RABBITMQ_VHOST', '/');

<<<<<<< Ainesh
//  database

=======
// database connection
>>>>>>> master
define('DB_HOST', 'localhost');
define('DB_USER', 'testUser');
define('DB_PASS', '123');
define('DB_NAME', 'meal_planner');
<<<<<<< Ainesh
// rabbitmq stuff
=======

>>>>>>> master
define('QUEUE_NAME', 'db_queue');

//DMZ stuff added 2/22 for api connection test
define('DMZ_EXCHANGE', 'dmz_exchange');
define('DMZ_EXCHANGE_TYPE', 'topic');
define('DMZ_QUEUE', 'dmz_queue');
define('DMZ_ROUTING_KEY', '*');

?>
