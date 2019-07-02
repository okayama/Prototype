<?php
if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
    exit();
}
require_once( 'class.Prototype.php' );
$app = new Prototype( ['id' => 'Worker' ] );
$app->logging  = true;
$app->init();
$pid = $app->temp_dir . DS . md5( __FILE__ ) . '.pid';
if ( $app->debug ) {
    if ( file_exists( $pid ) ) {
        unlink( $pid );
    }
} else if ( file_exists( $pid ) ) {
    $worker_period = $app->worker_period;
    $mtime = filemtime( $pid );
    if ( ( time() - $worker_period ) < $mtime ) {
        exit();
    }
    unlink( $pid );
}
touch( $pid );
array_shift( $argv );
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTWorker.php' );
$worker = new PTWorker;
$worker->pid = $pid;
if ( count( $argv ) == 1 ) {
    $argv = preg_split( "/,/", $argv[0] );
}
$argv = array_unique( $argv );
$worker->work( $app, $argv );
if ( file_exists( $pid ) ) {
    unlink( $pid );
}
