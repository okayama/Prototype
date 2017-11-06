<?php
require_once( 'class.Prototype.php' );
$app = new Prototype();
$app->logging  = true;
$app->temp_dir = '/powercms/data/temp';
$app->language = 'ja';
$bulk_remove_per = 20;
$app->init();
$pid = $app->temp_dir . DS . md5( __FILE__ ) . '.pid';
if ( file_exists( $pid ) ) {
    $worker_period = $app->worker_period;
    $mtime = filemtime( $pid );
    if ( ( time() - $worker_period ) < $mtime ) {
        exit();
    }
    unlink( $pid );
}
touch( $pid );
require_once( 'lib' . DS . 'Prototype' . DS . 'class.PTPublisher.php' );
$pub = new PTPublisher;
$pub->publish_queue();
$ts = time();
$objects = [];
$sth = $app->db->model( 'session' )->load_iter( ['expires' => ['<' => $ts ] ] );
while( $result = $sth->fetch( PDO::FETCH_ASSOC ) ) {
    $obj = $app->db->model( 'session' )->new( $result );
    $objects[] = $obj;
    if ( count( $objects ) >= $bulk_remove_per ) {
        $app->db->model( 'session' )->remove_multi( $objects );
        $objects = [];
    }
}
if (! empty( $objects ) ) {
    $app->db->model( 'session' )->remove_multi( $objects );
}
unlink( $pid );
