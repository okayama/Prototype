<?php
require_once( 'class.Prototype.php' );
$app = new Prototype();
$app->logging = true;
$app->init();
$db = $app->db;
$counter = 0;
$config = $app->get_config( 'dbcompress' );
if (! $db->dbcompress ) {
    $db->dbcompress = 'none';
}
$config = $config ? $config : $db->model( 'option' )->get_by_key(
        ['kind' => 'config', 'key' => 'dbcompress' ] );
$config->value( $db->dbcompress );
$config->save();
$class = class_exists( 'PADO' . $db->driver ) 
   ? 'PADO' . $db->driver : 'PADOBaseModel';
$driver = new $class();
if ( method_exists( $driver, 'compress_all' ) ) {
    $counter = $driver->compress_all( $db->dbcompress );
}
if ( $counter ) {
    echo "Compressed {$counter} table(s).";
} else {
    echo "No table found to compress.";
}
