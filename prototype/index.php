<?php

require_once( __DIR__ . DIRECTORY_SEPARATOR .'class.Prototype.php' );
$app = new Prototype();
// $app->debug = true;
$app->logging  = true;
$app->temp_dir = '/powercms/data/temp';
// $app->admin_url = 'http://prototype.localhost/apps/prototype/index.php';
$app->init();
$app->run();
