<?php

class PTWorker {

    public $bulk_remove_per = 40;
    public $pid = null;

    function __destruct() {
        if ( $this->pid && file_exists( $this->pid ) ) {
            unlink( $this->pid );
        }
    }

    function work ( $app ) {
        if ( $max_execution_time = $app->max_exec_time ) {
            $max_execution_time = (int) $max_execution_time;
            ini_set( 'max_execution_time', $max_execution_time );
        }
        $app->db->caching = false;
        $app->id = 'Worker';
        if (! $theme_static = $app->theme_static ) {
            $theme_static = $app->path . 'theme-static/';
            $app->theme_static = $theme_static;
        }
        $db = $app->db;
        $bulk_remove_per = $this->bulk_remove_per;
        $worker_labels = [];
        $worker_messages = [];
        // Remove old sessions
        $worker_label = $app->translate( 'Remove old sessions' );
        $res_counter = 0;
        $ts = time();
        $objects = [];
        $sessions = $db->model( 'session' )->load( ['expires' => ['<' => $ts ] ], [], 'id' );
        $res_counter = count( $sessions );
        try {
            foreach ( $sessions as $obj ) {
                $objects[] = $obj;
                if ( count( $objects ) >= $bulk_remove_per ) {
                    $db->model( 'session' )->remove_multi( $objects );
                    $objects = [];
                }
            }
            if (! empty( $objects ) ) {
                $db->model( 'session' )->remove_multi( $objects );
            }
            if ( $res_counter ) {
                $worker_labels[] = $worker_label;
                $worker_messages[ $worker_label ] =
                    $app->translate( 'Removed %s %s.',
                        [ $res_counter, $app->translate('Sessions(s)') ] );
            }
        } catch ( Exception $e ) {
            $error = $e->getMessage();
            $error = $app->translate( "An error occurred in task '%s'. (%s)",
                                      [ $worker_label, $error ] );
            $this->log( $error, 'error', $app );
        }
        $uuid_models = $db->model( 'table' )->load( ['has_uuid' => 1] );
        // Set uuid
        foreach ( $uuid_models as $table ) {
            $model = $table->name;
            $revisable = $table->revisable;
            $app->get_scheme_from_db( $model );
            $terms = [];
            $extra = " AND ({$model}_uuid IS NULL OR {$model}_uuid='') ";
            $cols = 'id,uuid';
            if ( $revisable ) {
                $terms['rev_type'] = 0;
                $cols .= ',rev_type,rev_object_id';
            }
            $objects = $db->model( $model )->load( $terms, [], $cols, $extra );
            if ( count( $objects ) ) {
                $worker_label = $app->translate(
                    'Set UUID(%s)', $app->translate( $table->plural ) );
                $res_counter = 0;
                try {
                    foreach ( $objects as $obj ) {
                        if (! $obj->uuid ) {
                            $uuid = $app->generate_uuid( $model );
                            $obj->uuid( $uuid );
                            $obj->save();
                            $res_counter++;
                            if ( $revisable ) {
                                $rev_objs = $db->model( $model )->load( ['rev_object_id' => $obj->id ] );
                                foreach ( $rev_objs as $rev ) {
                                    $rev->uuid( $uuid );
                                    $rev->save();
                                }
                            }
                        }
                    }
                    if ( $res_counter ) {
                        $worker_labels[] = $worker_label;
                        $obj_label = $res_counter == 1 ? $table->label : $table->plural;
                        $worker_messages[ $worker_label ] = $app->translate( 'Set UUID of %s %s.',
                            [ $res_counter, $obj_label ] );
                    }
                } catch ( Exception $e ) {
                    $error = $e->getMessage();
                    $error = $app->translate( "An error occurred in task '%s'. (%s)",
                                              [ $worker_label, $error ] );
                    $this->log( $error, 'error', $app );
                }
            }
        }
        $revisable_models = $db->model( 'table' )->load( ['revisable' => 1] );
        // Remove old revisions
        $worker_label = '';
        $res_counter = 0;
        foreach ( $revisable_models as $table ) {
            $max_revisions = $table->max_revisions
                           ? $table->max_revisions : $app->max_revisions;
            $max_revisions = (int) $max_revisions;
            if ( $max_revisions > 0 ) {
                $worker_label = $app->translate(
                    'Remove old revisions(%s)', $app->translate( $table->plural ) );
                $res_counter = 0;
                $model = $table->name;
                try {
                    $sql = "SELECT {$model}_rev_object_id,COUNT({$model}_rev_object_id) ";
                    $sql.= "FROM mt_{$model} WHERE ( {$model}_rev_type = 1 ) ";
                    $sql.= "GROUP BY {$model}_rev_object_id  HAVING COUNT({$model}_rev_object_id) > ";
                    $sql.= $max_revisions;
                    $groups = $db->model( $model )->load( $sql );
                    $count_key = "COUNT({$model}_rev_object_id)";
                    $id_key = "{$model}_rev_object_id"; 
                    foreach ( $groups as $group ) {
                        $rev_object_id = (int) $group->$id_key;
                        $obj_cnt = (int) $group->$count_key;
                        $rev_limit = $obj_cnt - $max_revisions;
                        $revisions = $db->model( $model )->load(
                            ['rev_object_id' => $rev_object_id ],
                            ['sort' => 'modified_on', 'direction' => 'ascend', 'limit' => $rev_limit ]
                        );
                        foreach ( $revisions as $revision ) {
                            $res_counter++;
                            $app->remove_object( $revision, $table );
                        }
                    }
                    if ( $res_counter ) {
                        $worker_labels[] = $worker_label;
                        $worker_messages[ $worker_label ] = $app->translate( 'Removed %s %s.',
                            [ $res_counter, $app->translate('Revision(s)') ] );
                    }
                } catch ( Exception $e ) {
                    $error = $e->getMessage();
                    $error = $app->translate( "An error occurred in task '%s'. (%s)",
                                              [ $worker_label, $error ] );
                    $this->log( $error, 'error', $app );
                }
            }
        }
        $status_models = $db->model( 'table' )->load( ['start_end' => 1] );
        $workflows = [];
        $wf_class = new PTWorkflow();
        $worker_label = '';
        $res_counter = 0;
        $trigger_mappings = [];
        $model_mappings = [];
        foreach ( $status_models as $table ) {
            // Scheduled publish
            $worker_label = $app->translate( 'Scheduled publish(%s)',
                                        $app->translate( $table->plural ) );
            $res_counter = 0;
            $model = $table->name;
            $terms = ['status' => 3,
                'published_on' => ['<=' => date( 'YmdHis' ) ] ];
            if ( $table->revisable ) {
                $terms['rev_type'] = 0;
            }
            $scheme = $app->get_scheme_from_db( $model );
            $objects = $db->model( $model )->load( $terms );
            // if (! count( $objects ) ) continue;
            $app->init_callbacks( $model, 'scheduled_published' );
            $callback = ['name' => 'scheduled_published', 'model' => $model,
                         'scheme' => $scheme, 'table' => $table ];
            $mappings = $db->model( 'urlmapping' )->load( ['container' => $model] );
            $triggers = $db->model( 'relation' )->load(
                ['name' => 'triggers', 'from_obj' => 'urlmapping',
                 'to_obj' => 'table', 'to_id' => $table->id ]
            );
            foreach ( $triggers as $trigger ) {
                $map = $db->model( 'urlmapping' )->load( (int) $trigger->from_id );
                if ( $map ) {
                    $map->__is_trigger = 1;
                    $mappings[] = $map;
                }
            }
            $model_mappings[ $model ] = $mappings;
            try {
                foreach ( $objects as $obj ) {
                    $res_counter++;
                    $obj->status( 4 );
                    $original = clone $obj;
                    $app->set_default( $obj );
                    $obj->save();
                    $app->publish_obj( $obj, null, true );
                    $app->run_callbacks( $callback, $model, $obj, $original );
                    $workspace_id = $obj->has_column( 'workspace_id' ) ? $obj->workspace_id : 0;
                    $workspace_id = (int) $workspace_id;
                    if ( isset( $workflows["{$model}_{$workspace_id}"] ) ) {
                        $workflow = $workflows["{$model}_{$workspace_id}"];
                    } else {
                        $workflow = $db->model( 'workflow' )->get_by_key(
                            ['model' => $obj->_model,
                             'workspace_id' => $workspace_id ] );
                        $workflows["{$model}_{$workspace_id}"] = $workflow;
                    }
                    if ( $workflow->id ) {
                        $wf_class->publish_object( $app, $obj );
                    }
                    foreach ( $mappings as $map ) {
                        if ( isset( $trigger_mappings[ $map->id ] ) ) continue;
                        if ( isset( $map->__is_trigger ) && $map->__is_trigger ) {
                            if ( $map->trigger_scope ) {
                                if ( $obj->workspace_id == $map->workspace_id ) {
                                    $trigger_mappings[ $map->id ] = $map;
                                }
                            } else {
                                $trigger_mappings[ $map->id ] = $map;
                            }
                        } else {
                            if ( $map->container_scope ) {
                                if ( $obj->workspace_id == $map->workspace_id ) {
                                    $trigger_mappings[ $map->id ] = $map;
                                }
                            } else {
                                $trigger_mappings[ $map->id ] = $map;
                            }
                        }
                    }
                }
                if ( $res_counter ) {
                    $worker_labels[] = $worker_label;
                    $object_label = $res_counter == 1
                                  ? $app->translate( $table->label )
                                  : $app->translate( $table->plural );
                    $worker_messages[ $worker_label ] = $app->translate( 'Published %s %s.',
                        [ $res_counter, $object_label ] );
                }
            } catch ( Exception $e ) {
                $error = $e->getMessage();
                $error = $app->translate( "An error occurred in task '%s'. (%s)",
                                          [ $worker_label, $error ] );
                $this->log( $error, 'error', $app );
            }
            $worker_label = '';
            $res_counter = 0;
            if ( $table->revisable ) {
                // Scheduled replacement from revision
                $worker_label = $app->translate( 'Scheduled replacement from revision(%s)',
                                            $app->translate( $table->plural ) );
                $res_counter = 0;
                $rel_attach_cols = PTUtil::attachment_cols( $table->name, $scheme, 'relation' );
                $terms['rev_type'] = 2;
                try {
                    $objects = $db->model( $model )->load( $terms );
                    $app->init_callbacks( $model, 'scheduled_replacement' );
                    $callback = ['name' => 'scheduled_replacement', 'model' => $model,
                                 'scheme' => $scheme, 'table' => $table ];
                    foreach ( $objects as $obj ) {
                        $rem_id = $obj->id;
                        $obj_relations = $app->get_relations( $obj );
                        $obj_metadata  = $app->get_meta( $obj );
                        $original = null;
                        $original_id = null;
                        $basename = '';
                        $clone = null;
                        $replaced_attaches = [];
                        $orig_attaches = [];
                        if ( $original_id = $obj->rev_object_id ) {
                            $original = $db->model( $model )->load( (int) $original_id );
                            if ( $original ) {
                                $changed_cols = [];
                                if (! empty( $rel_attach_cols ) ) {
                                    foreach ( $rel_attach_cols as $attach_col ) {
                                        $orig_attaches =
                                            $app->get_relations( $original,
                                                'attachmentfile', $attach_col );
                                        $obj_attaches =
                                            $app->get_relations( $obj,
                                                'attachmentfile', $attach_col );
                                        if ( count( $orig_attaches ) || count( $obj_attaches ) ) {
                                            $changed_cols[ $attach_col ] = true;
                                            if ( count( $orig_attaches ) ) {
                                                foreach ( $orig_attaches as $orig_attach ) {
                                                    $attachment_id = (int) $orig_attach->to_id;
                                                    $old_file =
                                                        $db->model( 'attachmentfile' )
                                                            ->load( $attachment_id );
                                                    if ( $old_file ) {
                                                        $replaced_attaches
                                                            [ $attachment_id ] = $old_file;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                $obj->id( (int) $original_id );
                                $clone = clone $original;
                                $clone->id( null );
                                $obj->status( $original->status );
                                if ( $obj->has_column( 'basename' ) ) {
                                    $basename =$original->basename;
                                }
                                $orig_relations = $app->get_relations( $original );
                                $orig_metadata  = $app->get_meta( $original );
                                $clone->_relations = $orig_relations;
                                $clone->_meta = $orig_metadata;
                                $obj->save();
                                PTUtil::pack_revision( $obj, $clone, $changed_cols, false );
                                $res_counter++;
                            } else {
                                continue;
                            }
                        } else {
                            continue;
                        }
                        if ( $clone->id && ! empty( $replaced_attaches ) ) {
                            $remove_rels = [];
                            foreach ( $orig_attaches as $attach ) {
                                $old_id = (int) $attach->to_id;
                                if ( isset( $replaced_attaches[ $old_id ] ) ) {
                                    $old_file = $replaced_attaches[ $old_id ];
                                    if ( $old_file ) $app->remove_object( $old_file );
                                    $remove_rels[] = $attach;
                                }
                            }
                            if (! empty( $remove_rels ) ) {
                                $db->model( 'relation' )->remove_multi( $remove_rels );
                            }
                        }
                        $app->set_default( $obj );
                        if ( $basename ) {
                            $obj->basename( $basename );
                        }
                        $obj->rev_type( 0 );
                        $obj->rev_object_id( 0 );
                        $obj->save();
                        foreach ( $obj_relations as $relation ) {
                            $relation->from_id( (int) $obj->id );
                            $relation->save();
                        }
                        foreach ( $obj_metadata as $meta ) {
                            $meta->object_id( (int) $obj->id );
                            $meta->save();
                        }
                        $app->publish_obj( $obj, $clone, true );
                        foreach ( $mappings as $map ) {
                            if ( isset( $trigger_mappings[ $map->id ] ) ) continue;
                            if ( isset( $map->__is_trigger ) && $map->__is_trigger ) {
                                if ( $map->trigger_scope ) {
                                    if ( $obj->workspace_id == $map->workspace_id ) {
                                        $trigger_mappings[ $map->id ] = $map;
                                    }
                                } else {
                                    $trigger_mappings[ $map->id ] = $map;
                                }
                            } else {
                                if ( $map->container_scope ) {
                                    if ( $obj->workspace_id == $map->workspace_id ) {
                                        $trigger_mappings[ $map->id ] = $map;
                                    }
                                } else {
                                    $trigger_mappings[ $map->id ] = $map;
                                }
                            }
                        }
                        $app->run_callbacks( $callback, $model, $obj, $clone );
                        $rem_obj = $db->model( $model )->load( ['id' => $rem_id ] );
                        if ( !empty( $rem_obj ) ) {
                            $rem_obj = $rem_obj[0];
                            $error = '';
                            $app->remove_object( $rem_obj, $table, $error, false );
                        }
                    }
                    if ( $res_counter ) {
                        $worker_labels[] = $worker_label;
                        $object_label = $res_counter == 1
                                      ? $app->translate( $table->label )
                                      : $app->translate( $table->plural );
                        $worker_messages[ $worker_label ] = $app->translate( 'Replaced %s %s from revision.',
                            [ $res_counter, $object_label ] );
                    }
                } catch ( Exception $e ) {
                    $error = $e->getMessage();
                    $error = $app->translate( "An error occurred in task '%s'. (%s)",
                                              [ $worker_label, $error ] );
                    $this->log( $error, 'error', $app );
                }
            }
            // Scheduled unpublish
            $worker_label = $app->translate( 'Scheduled unpublish(%s)',
                                        $app->translate( $table->plural ) );
            $res_counter = 0;
            $terms = ['status' => 4,
                'has_deadline' => 1,
                'unpublished_on' => ['<=' => date( 'YmdHis' ) ] ];
            if ( $table->revisable ) {
                $terms['rev_type'] = 0;
            }
            try {
                $objects = $db->model( $model )->load( $terms );
                if (! count( $objects ) ) continue;
                foreach ( $objects as $obj ) {
                    $original = clone $obj;
                    $obj->status( 5 );
                    $obj->has_deadline( 0 );
                    $obj->save();
                    $app->publish_obj( $obj, $original, true );
                    foreach ( $mappings as $map ) {
                        if ( isset( $trigger_mappings[ $map->id ] ) ) continue;
                        if ( isset( $map->__is_trigger ) && $map->__is_trigger ) {
                            if ( $map->trigger_scope ) {
                                if ( $obj->workspace_id == $map->workspace_id ) {
                                    $trigger_mappings[ $map->id ] = $map;
                                }
                            } else {
                                $trigger_mappings[ $map->id ] = $map;
                            }
                        } else {
                            if ( $map->container_scope ) {
                                if ( $obj->workspace_id == $map->workspace_id ) {
                                    $trigger_mappings[ $map->id ] = $map;
                                }
                            } else {
                                $trigger_mappings[ $map->id ] = $map;
                            }
                        }
                    }
                    $res_counter++;
                }
                if ( $res_counter ) {
                    $worker_labels[] = $worker_label;
                    $object_label = $res_counter == 1
                                  ? $app->translate( $table->label )
                                  : $app->translate( $table->plural );
                    $worker_messages[ $worker_label ] = $app->translate( 'Unpublished %s %s.',
                        [ $res_counter, $object_label ] );
                }
            } catch ( Exception $e ) {
                $error = $e->getMessage();
                $error = $app->translate( "An error occurred in task '%s'. (%s)",
                                          [ $worker_label, $error ] );
                $this->log( $error, 'error', $app );
            }
        }
        // Publish queue
        require_once( 'lib' . DS . 'Prototype' . DS . 'class.PTPublisher.php' );
        $pub = new PTPublisher;
        $worker_label = $app->translate( 'Publish queue' );
        $res_counter = 0;
        try {
            $res_counter = $pub->publish_queue();
            if ( $res_counter ) {
                $worker_labels[] = $worker_label;
                $object_label = $res_counter == 1
                              ? $app->translate( 'File' )
                              : $app->translate( 'Files' );
                $worker_messages[ $worker_label ] =
                    $app->translate( 'Published %s %s by publish queue.',
                    [ $res_counter, $object_label ] );
            }
        } catch ( Exception $e ) {
            $error = $e->getMessage();
            $error = $app->translate( "An error occurred in task '%s'. (%s)",
                                      [ $worker_label, $error ] );
            $this->log( $error, 'error', $app );
        }
        $res_counter = 0;
        /*
        // Compress the database
        if ( $db->dbcompress ) {
            $config = $app->get_config( 'dbcompress' );
            if (! $config || $config->value != $db->dbcompress ) {
                // if (! $db->dbcompress ) {
                //     $db->dbcompress = 'none';
                // }
                $worker_label = $app->translate( 'Compress the database' );
                $config = $config ? $config : $db->model( 'option' )->get_by_key(
                ['kind' => 'config', 'key' => 'dbcompress' ] );
                $config->value( $db->dbcompress );
                $config->save();
                $class = class_exists( 'PADO' . $db->driver ) 
                   ? 'PADO' . $db->driver : 'PADOBaseModel';
                $driver = new $class();
                if ( method_exists( $driver, 'compress_all' ) ) {
                    $res_counter = $driver->compress_all( $db->dbcompress );
                    if ( $res_counter ) {
                        $worker_labels[] = $worker_label;
                    }
                }
            }
        }
        */
        // Plugin's tasks
        $tasks = isset( $app->registry['tasks'] ) ? $app->registry['tasks'] : [];
        $tasks = array_merge( $tasks, $this->core_tasks() );
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
            foreach ( $event_tasks as $tasks ) {
                foreach ( $tasks as $task ) {
                    $component = $app->component( $task['component'] );
                    $meth = $task['method'];
                    if ( method_exists( $component, $meth ) ) {
                        $frequency = isset( $task['frequency'] ) ? $task['frequency'] : 900;
                        $task_id = $task['id'];
                        $session = $db->model( 'session' )->get_by_key(
                                                ['kind' => 'TK', 'name' => md5( $task_id ) ] );
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
                            $res = $component->$meth( $app );
                            if ( $res ) {
                                $worker_labels[] = $label;
                            }
                            $session->start( $start );
                            $session->expires( $start + $frequency + 3600 );
                            $session->save();
                        } catch ( Exception $e ) {
                            $error = $e->getMessage();
                            $error = $app->translate( "An error occurred in task '%s'. (%s)",
                                                      [ $label, $error ] );
                            $this->log( $error, 'error', $app );
                        }
                    }
                }
            }
        }
        if (! empty( $worker_labels ) ) {
            $message = $app->translate( 'Scheduled tasks update.' );
            $metadata = [ $app->translate( 'Labels' ) => $worker_labels,
                          $app->translate( 'Messages' ) => $worker_messages ];
            $log = ['level' => 'info', 'category' => 'worker', 'message' => $message,
                    'metadata' => $metadata ];
            $app->log( $log );
        }
        if (! empty( $trigger_mappings ) ) {
            $publisher = new PTPublisher();
            foreach ( $trigger_mappings as $map_id => $trigger_mapping ) {
                if ( $trigger_mapping->publish_file == 6 ) continue;
                $urls = $db->model( 'urlinfo' )->load( ['urlmapping_id' => $map_id ] );
                foreach ( $urls as $url ) {
                    $publisher->publish( $url );
                }
            }
        }
    }

    function translate ( $phrase ) {
        $app = Prototype::get_instance();
        return $app->translate( $phrase );
    }

    function core_tasks () {
        $tasks = ['cleanup_blobs' => [
            'label' => 'Cleanup removed files',
            'component' => 'PTWorker',
            'priority' => 100,
            'method' => 'cleanup_blobs',
            'frequency' => 10800,
        ] ];
        return $tasks;
    }

    function cleanup_blobs ( $app ) {
        $db = $app->db;
        $db->caching = false;
        if ( $db->blob2file && $db->blob_path && file_exists( $db->blob_path ) ) {
        } else {
            return false;
        }
        $dir = $db->blob_path;
        $sth = $db->show_tables();
        $tables = $sth->fetchAll();
        $pfx = preg_quote( DB_PREFIX, '/' );
        $targets = [];
        $counter = 0;
        foreach ( $tables as $key => $table ) {
            $tbl_name = $table[0];
            $tbl_name = preg_replace( "/^$pfx/", '', $tbl_name );
            $app->get_scheme_from_db( $tbl_name );
            $blob_cols = $db->get_blob_cols( $tbl_name );
            if (! empty( $blob_cols ) ) {
                $targets[] = $tbl_name;
            }
        }
        foreach ( $targets as $model ) {
            $blobs = [];
            if ( is_dir( $dir . $model ) ) {
                $subDir = dir( $dir . $model );
                $blobs = [];
                while ( false !== ( $blob = $subDir->read() ) ) {
                    if ( $blob != '.' && $blob != '..' ) {
                        $blobs[ $blob ] = true;
                    }
                }
                $subDir->close();
            }
            $objects = $db->model( $model )->load( [], [], 'id' );
            $blob_cols = $db->get_blob_cols( $model, true );
            $cols = implode( ',', $blob_cols );
            if ( $cols ) $cols .= ',id';
            foreach ( $objects as $obj ) {
                $obj = $db->model( $model )->get_by_key(
                    ['id' => $obj->id ], [], $cols );
                $original = $obj->_original;
                foreach ( $blob_cols as $col ) {
                    $col = "{$model}_{$col}";
                    if ( isset( $original[ $col ] ) ) {
                        $value = $original[ $col ];
                        if ( strpos( $value, 'a:1:{s:8:"basename";s:' ) === 0 ) {
                            $unserialized = @unserialize( $value );
                            if ( is_array( $unserialized ) 
                                && isset( $unserialized['basename'] ) ) {
                                $basename = $unserialized['basename'];
                                if ( isset( $blobs[ $basename ] ) ) {
                                    unset( $blobs[ $basename ] );
                                } else {
                                    // no output file.
                                    if ( $value ) {
                                        $obj->$col( $value );
                                        $obj->save();
                                    }
                                }
                            }
                        } else {
                            if ( $value ) {
                                // no output file.
                                $obj->$col( $value );
                                $obj->save();
                            }
                        }
                    }
                }
            }
            foreach ( $blobs as $basename => $bool ) {
                $blob = $dir . $model . DS . $basename;
                if ( file_exists( $blob ) ) {
                    unlink( $blob );
                    $counter++;
                }
            }
        }
        if ( $counter ) {
            $this->log( $app->translate( 'Cleanup %s removed file(s).', $counter ) );
        }
        return $counter;
    }

    function log ( $message, $level = 'info', $app = null ) {
        if (! $app ) $app = Prototype::get_instance();
        $log = ['level' => $level, 'category' => 'worker', 'message' => $message ];
        $app->log( $log );
    }
}
