<?php

require_once( __DIR__ . DIRECTORY_SEPARATOR .'class.Prototype.php' );
$app = new Prototype();
// $app->debug = true;
$app->logging = true;
// $app->list_async = true;
$app->init();
$app->run();
