<?php
require_once( 'class.Prototype.php' );
$app = new Prototype();
$app->logging  = true;
// $app->temp_dir = '/tsutaeru-webiste/tmp';
$app->language = 'ja';
$bulk_remove_per = 20;
$app->init();
$pid = $app->temp_dir . DS . md5( __FILE__ ) . '.pid';
// unlink( $pid );
if ( file_exists( $pid ) ) {
    $worker_period = $app->worker_period;
    $mtime = filemtime( $pid );
    if ( ( time() - $worker_period ) < $mtime ) {
        exit();
    }
    unlink( $pid );
}
touch( $pid );
$status_models = $app->db->model( 'table' )->load( ['start_end' => 1] );
foreach ( $status_models as $table ) {
    $model = $table->name;
    $terms = ['status' => 3,
        'published_on' => ['<=' => date( 'YmdHis' ) ] ];
    if ( $table->revisable ) {
        $terms['rev_type'] = 0;
    }
    $objects = $app->db->model( $model )->load( $terms );
    foreach ( $objects as $obj ) {
        $obj->status( 4 );
        $app->set_default( $obj );
        $obj->save();
        $app->publish_obj( $obj, null, true );
    }
    $scheme = $app->get_scheme_from_db( $model );
    if ( $table->revisable ) {
        $terms['rev_type'] = 2;
        $objects = $app->db->model( $model )->load( $terms );
        foreach ( $objects as $obj ) {
            $rem_id = $obj->id;
            $obj_relations = $app->get_relations( $obj );
            $obj_metadata  = $app->get_meta( $obj );
            $original = null;
            $original_id = null;
            $basename = '';
            if ( $original_id = $obj->rev_object_id ) {
                $original = $app->db->model( $model )->load( (int) $original_id );
                if ( $original ) {
                    $orig_relations = $app->get_relations( $original );
                    $orig_metadata  = $app->get_meta( $original );
                    $original->rev_type( 1 );
                    $original->rev_object_id( $original->id );
                    if ( $original->has_column( 'basename' ) ) {
                        $basename = $original->basename;
                    }
                    $original->id( null );
                    // TODO diff
                    $app->set_default( $original );
                    if ( $basename ) {
                        $original->basename( $basename );
                    }
                    $original->save();
                    foreach ( $orig_relations as $relation ) {
                        $relation->from_id( $original->id );
                        $relation->save();
                    }
                    foreach ( $orig_metadata as $meta ) {
                        $meta->object_id( $original->id );
                        $meta->save();
                    }
                }
            }
            $rem_id = $obj->id;
            $obj->status( 4 );
            $app->set_default( $obj );
            if ( $basename ) {
                $obj->basename( $basename );
            }
            if ( $original && $original_id ) {
                $obj->id( (int) $original_id );
            }
            $obj->rev_type( 0 );
            $obj->rev_object_id( 0 );
            $obj->save();
            foreach ( $obj_relations as $relation ) {
                $relation->from_id( (int) $original_id );
                $relation->save();
            }
            foreach ( $obj_metadata as $meta ) {
                $meta->object_id( (int) $original_id );
                $meta->save();
            }
            // TODO callback
            $app->publish_obj( $obj, $original, true );
            $_original = $app->db->model( $model )->load( ['id' => $rem_id ] );
            if ( !empty( $_original ) ) {
                $_original = $_original[0];
                $_original->remove();
            }
        }
    }
    $terms = ['status' => 4,
        'has_deadline' => 1,
        'unpublished_on' => ['<=' => date( 'YmdHis' ) ] ];
    if ( $table->revisable ) {
        $terms['rev_type'] = 0;
    }
    $objects = $app->db->model( $model )->load( $terms );
    foreach ( $objects as $obj ) {
        $original = clone $obj;
        $obj->status( 5 );
        $obj->has_deadline( 0 );
        $obj->save();
        $app->publish_obj( $obj, $original, true );
    }
}
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
$tasks = isset( $app->registry['tasks'] ) ? $app->registry['tasks'] : [];
$event_tasks = [];
foreach ( $tasks as $key => $regi ) {
    $priority = isset( $regi['priority'] ) ? (int) $regi['priority'] : 5;
    $event_tasks[ $priority ] = isset( $event_tasks[ $priority ] )
                              ? $event_tasks[ $priority ] : [];
    $regi['id'] = $key;
    unset( $regi['priority'] );
    $event_tasks[ $priority ][] = $regi;
}
if (! empty( $event_tasks ) ) {
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
                                                    ['kind' => 'TK', 'name' => md5( $task_id ) ] );
                if ( $session->id ) {
                    $start = $session->start;
                    $time_limit = $start + $frequency;
                    if ( $time_limit > time() ) {
                        continue;
                    }
                }
                $label = $component->translate( $task['label'] );
                // var_dump( $label );
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
}
unlink( $pid );
