<?php
require_once( __DIR__ . DIRECTORY_SEPARATOR .'class.Prototype.php' );
$app = new Prototype();
// $app->logging = true;
$app->init();
$app->run();
