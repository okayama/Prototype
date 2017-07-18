<?php
require_once( __DIR__ . DIRECTORY_SEPARATOR .'class.prototype.php' );

//$json = file_get_contents( '/Applications/MAMP/htdocs/app/lib/PADO/models/option.json' );

//$json = json_decode( $json );
//var_dump($json);
//exit();
$app = new Prototype();
$app->debug = true;
$app->init();
$app->run();
