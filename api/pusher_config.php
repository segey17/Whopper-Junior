<?php
require __DIR__ . '/../vendor/autoload.php'; // Если используете Composer

// Замените на ваши реальные учетные данные Pusher
define('PUSHER_APP_ID', '1993013');
define('PUSHER_APP_KEY', 'dbe89bd713c5f93e5e19');
define('PUSHER_APP_SECRET', '43c582f0c2c8d158a0e8');
define('PUSHER_APP_CLUSTER', 'eu');

$pusher = new Pusher\Pusher(
    PUSHER_APP_KEY,
    PUSHER_APP_SECRET,
    PUSHER_APP_ID,
    array('cluster' => PUSHER_APP_CLUSTER)
);
?>