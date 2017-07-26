<?php
require_once( __DIR__ . DIRECTORY_SEPARATOR .'class.prototype.php' );
$app = new Prototype();
$app->debug = true;
$app->init();
$app->run();
