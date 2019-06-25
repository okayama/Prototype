<?php
if (! defined( 'DS' ) ) {
    define( 'DS', DIRECTORY_SEPARATOR );
}
$base_path = '..' . DS . '..' . DS . '..' . DS;
require_once( "{$base_path}class.Prototype.php" );
$member = $base_path . 'plugins' . DS . 'Members' . DS .
          'lib' . DS . 'Prototype' . DS . 'class.PTMembers.php';
require_once( $member );
$app = new PTMembers();
$app->app_path = realpath( $base_path );
$app->init();
$app->run();
