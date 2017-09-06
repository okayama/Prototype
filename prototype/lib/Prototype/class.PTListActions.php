<?php

class PTListActions {

    function list_action ( $app ) {
        $app->validate_magic();
        $db = $app->db;
        $model = $app->param( '_model' );
        $objects = $app->get_object( $model );
        foreach ( $objects as $obj ) {
            if (! $app->can_do( $model, 'edit', $obj ) ) {
                return $app->error( 'Permission denied.' );
            }
        }
        $list_actions = $this->get_list_actions( $model );
        $action_name = $app->param( 'action_name' );
        $action = $list_actions[ $action_name ];
        if ( is_array( $action ) && isset( $action['component'] ) ) {
            $component = $action['component'];
            $meth = $action['method'];
            if ( method_exists( $component, $meth ) ) {
                $db->caching = false;
                return $component->$meth( $app, $objects, $action );
            }
        }
        return $app->error( 'Invalid request.' );
    }

    function get_list_actions ( $model, &$list_actions = [] ) {
        $app = Prototype::get_instance();
        $registry = $app->registry;
        $table = $app->get_table( $model );
        if ( $table->has_status ) {
            $list_actions[] = ['name' => 'set_status', 'input' => 0,
                               'label' => $app->translate( 'Set Status' ),
                               'component' => $this,
                               'method' => 'set_status'];
        }
        if ( $table->taggable ) {
            $list_actions[] = ['name' => 'add_tags', 'input' => 1,
                               'label' => $app->translate( 'Add Tags' ),
                               'component' => $this,
                               'method' => 'add_tags'];
            $list_actions[] = ['name' => 'remove_tags', 'input' => 1,
                               'label' => $app->translate( 'Remove Tags' ),
                               'component' => $this,
                               'method' => 'remove_tags'];
        }
        return $app->get_registries( $model, 'list_actions', $list_actions );
    }

    function set_status ( $app, $objects, $action ) {
        $model = $app->param( '_model' );
        $status_published = $app->status_published( $model );
        $table = $app->get_table( $model );
        if (! $table || ! $table->has_status ) {
            return $app->error( 'Invalid request.' );
        }
        $status = (int) $app->param( 'itemset_action_input' );
        $max_status = $app->max_status( $app->user(), $model );
        if (! $status || $status > $max_status ) {
            return $app->error( 'Invalid request.' );
        }
        $counter = 0;
        $rebuild_ids = [];
        foreach ( $objects as $obj ) {
            if (! $obj->has_column( 'status' ) ) {
                return $app->error( 'Invalid request.' );
            }
            $original = clone $obj;
            if ( $obj->status != $status ) {
                if ( $obj->status == $status_published || $status == $status_published ) {
                    if ( $app->get_permalink( $obj, true ) ) {
                        $rebuild_ids[] = $obj->id;
                    }
                }
                $obj->status( $status );
                $obj->save();
                $original = clone $obj;
                $callback = ['name' => 'post_save', 'error' => '', 'is_new' => false ];
                $app->run_callbacks( $callback, $model, $obj, $original );
                $counter++;
            }
        }
        if ( $counter ) {
            $column = $app->db->model( 'column' )->get_by_key(
                      ['table_id' => $table->id, 'name' => 'status'] );
            $options = $column->options;
            $status_text = $status;
            if ( $options ) {
                $options = explode( ',', $options );
                $status_text = $app->translate( $options[ $status - 1 ] );
            }
            $action = $action['label'] . " ({$status_text})";
            $this->log( $action, $model, $counter );
        }
        $return_args = "does_act=1&__mode=view&_type=list&_model={$model}&"
                     . "apply_actions={$counter}" . $app->workspace_param;
        if (! empty( $rebuild_ids ) ) {
            $ids = join( ',', $rebuild_ids );
            $app->mode = 'rebuild_phase';
            $app->param( '__mode', 'rebuild_phase' );
            $app->param( 'ids', $ids );
            $app->param( 'apply_actions', $counter );
            $app->param( '_return_args', $return_args );
            return $app->rebuild_phase( $app, true, 0, true );
        }
        $app->redirect( $app->admin_url . '?' . $return_args );
    }

    function add_tags ( $app, $objects, $action, $add = true ) {
        $model = $app->param( '_model' );
        $table = $app->get_table( $model );
        if (! $table || ! $table->taggable ) {
            return $app->error( 'Invalid request.' );
        }
        $counter = 0;
        $add_tags = $app->param( 'itemset_action_input' );
        if (! $add_tags ) {
            $app->redirect( $app->admin_url .
                "?__mode=view&_type=list&_model={$model}&apply_actions={$counter}"
                                                    . $app->workspace_param );
        }
        $column = $app->db->model( 'column' )->load(
            ['table_id' => $table->id, 'type' => 'relation', 'options' => 'tag'],
            ['limit' => 1] );
        $name = 'tags';
        if ( is_array( $column ) && count( $column ) ) {
            $column = $column[0];
            $name = $column->name;
        }
        $add_tags = preg_split( '/\s*,\s*/', $add_tags );
        $status_published = $app->status_published( $model );
        $tag_ids = [];
        $tag_objs = [];
        if (! $add ) {
            foreach ( $add_tags as $tag ) {
                $normalize = preg_replace( '/\s+/', '', trim( strtolower( $tag ) ) );
                if (! $tag ) continue;
                $terms = ['normalize' => $normalize ];
                $tags = $app->db->model( 'tag' )->load( $terms );
                if (! empty( $tags ) ) {
                    $tag_objs = array_merge( $tag_objs, $tags );
                }
            }
            if ( empty( $tag_objs ) ) {
                $app->redirect( $app->admin_url .
                    "?__mode=view&_type=list&_model={$model}&apply_actions={$counter}"
                                                        . $app->workspace_param );
            }
            foreach ( $tag_objs as $tag ) {
                $tag_ids[] = $tag->id;
            }
        }
        $rebuild_ids = [];
        $rebuild_tag_ids = [];
        foreach ( $objects as $obj ) {
            $res = false;
            if ( $add ) {
                $res = $this->add_tags_to_obj( $obj, $add_tags, $name, $tag_ids );
                $rebuild_tag_ids = $tag_ids;
            } else {
                $relations = $app->get_relations( $obj, 'tag', $name );
                foreach ( $relations as $relation ) {
                    if ( in_array( $relation->to_id, $tag_ids ) ) {
                        $res = $relation->remove();
                        if (! in_array( $relation->to_id, $rebuild_tag_ids ) ) {
                            if ( $obj->status == $status_published ) {
                                $rebuild_tag_ids[] = $relation->to_id;
                            }
                        }
                    }
                }
            }
            if ( $res ) {
                $counter++;
                if ( $table->has_status && $obj->has_column( 'status' ) ) {
                    if ( $obj->status == $status_published ) {
                        if ( $app->get_permalink( $obj, true ) ) {
                            $rebuild_ids[] = $obj->id;
                        }
                    }
                }
            }
        }
        if ( $counter ) {
            $add_tags = join( ', ', $add_tags );
            $action = $action['label'] . " ({$add_tags})";
            $this->log( $action, $model, $counter );
        }
        $return_args = "does_act=1&__mode=view&_type=list&_model={$model}&"
                     ."apply_actions={$counter}" . $app->workspace_param;
        if (! empty( $rebuild_ids ) ) {
            $ids = join( ',', $rebuild_ids );
            if (! empty( $rebuild_tag_ids ) ) {
                $tag_counter = count( $rebuild_tag_ids );
                $return_args .= '&publish_dependencies=' . $tag_counter;
                $tag_ids = join( ',', $rebuild_tag_ids );
                $return_args = '__mode=rebuild_phase&ids=' . $tag_ids
                            . '&_model=tag&apply_actions=' . $tag_counter 
                            . '&_return_args=' . rawurlencode( $return_args );
            }
            $app->mode = 'rebuild_phase';
            $app->param( '__mode', 'rebuild_phase' );
            $app->param( 'ids', $ids );
            $app->param( 'apply_actions', $counter );
            $app->param( '_return_args', $return_args );
            return $app->rebuild_phase( $app, true );
        }
        $app->redirect( $app->admin_url . '?' . $return_args );
    }

    function remove_tags ( $app, $objects, $action ) {
        return $this->add_tags( $app, $objects, $action, false );
    }

    function add_tags_to_obj ( $obj, $add_tags, $name = 'tags', &$tag_ids ) {
        $app = Prototype::get_instance();
        if (! empty( $add_tags ) ) {
            $db = $app->db;
            $workspace_id = 0;
            if ( $obj->has_column( 'workspace_id' ) ) {
                $workspace_id = (int) $obj->workspace_id;
            }
            $to_ids = [];
            foreach ( $add_tags as $tag ) {
                $normalize = preg_replace( '/\s+/', '', trim( strtolower( $tag ) ) );
                if (! $tag ) continue;
                $terms = ['normalize' => $normalize ];
                if ( $workspace_id )
                    $terms['workspace_id'] = $workspace_id;
                $tag_obj = $db->model( 'tag' )->get_by_key( $terms );
                if (! $tag_obj->id ) {
                    $tag_obj->name( $tag );
                    $app->set_default( $tag_obj );
                    $order = $app->get_order( $tag_obj );
                    $tag_obj->order( $order );
                    $tag_obj->save();
                }
                $to_ids[] = $tag_obj->id;
                if (! in_array( $tag_obj->id, $tag_ids ) ) {
                    $tag_ids[] = $tag_obj->id;
                }
            }
            $args = ['from_id' => $obj->id, 
                     'name' => $name,
                     'from_obj' => $obj->_model,
                     'to_obj' => 'tag' ];
            return $app->set_relations( $args, $to_ids, true );
        }
        return null;
    }

    function log ( $action, $model, $count ) {
        $app = Prototype::get_instance();
        $table = $app->get_table( $model );
        $obj_label = $count == 1 ? $table->label : $table->plural;
        $obj_label = $app->translate( $obj_label );
        $message = $app->translate(
                        'The action \'%1$s\' was executed for %2$s %3$s by %4$s.',
                            [ $action, $count, $obj_label, $app->user()->nickname ] );
        $app->log( ['message'  => $message,
                    'category' => 'list_action',
                    'model'    => $model,
                    'level'    => 'info'] );
    }
}