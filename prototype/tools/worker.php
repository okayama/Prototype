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
$tasks = $app->registry['tasks'];
$event_tasks = [];
foreach ( $tasks as $key => $regi ) {
    $priority = isset( $regi['priority'] ) ? (int) $regi['priority'] : 5;
    $event_tasks[ $priority ] = isset( $event_tasks[ $priority ] )
                              ? $event_tasks[ $priority ] : [];
    $regi['id'] = $key;
    unset( $regi['priority'] );
    $event_tasks[ $priority ][] = $regi;
}
ksort( $event_tasks );
$labels = [];
foreach ( $event_tasks as $tasks ) {
    foreach ( $tasks as $task ) {
        $component = $app->component( $task['component'] );
        $meth = $task['method'];
        if ( method_exists( $component, $meth ) ) {
            $frequency = isset( $task['frequency'] ) ? $task['frequency'] : 900;
            $task_id = $task['id'];
            $session = $app->db->model( 'session' )->get_by_key(
                                                ['kind' => 'TK', 'name' => md5( $id ) ] );
            if ( $session->id ) {
                $start = $session->start;
                $time_limit = $start + $frequency;
                if ( $time_limit > time() ) {
                    continue;
                }
            }
            $label = $component->translate( $task['label'] );
            try {
                $start = time();
                $component->$meth( $app );
                $labels[] = $label;
                $session->start( $start );
                $session->expires( $start + $frequency + 3600 );
                $session->save();
            } catch ( Exception $e ) {
                $error = $e->getMessage();
                $error = $app->translate( "An error occurred in task '%s'. (%s)",
                                                                    [ $label, $error ] );
                $log = ['level' => 'error', 'category' => 'task', 'message' => $error ];
                $app->log( $log );
            }
        }
    }
}
unlink( $pid );
