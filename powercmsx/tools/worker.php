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
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTWorker.php' );
$worker = new PTWorker;
$worker->pid = $pid;
$worker->work( $app );
if ( file_exists( $pid ) ) {
    unlink( $pid );
}

