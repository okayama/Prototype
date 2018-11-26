<?php
require_once( __DIR__ . DIRECTORY_SEPARATOR .'class.Prototype.php' );
$app = new Prototype();
$app->init();
if ( $app->user() && $app->user()->is_superuser ) {
    phpinfo();
} else {
    $app->error( 'Permission denied.' );
}