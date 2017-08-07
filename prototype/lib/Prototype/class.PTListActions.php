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
        foreach ( $objects as $obj ) {
            if (! $obj->has_column( 'status' ) ) {
                return $app->error( 'Invalid request.' );
            }
            $original = clone $obj;
            if ( $obj->status != $status ) {
                $obj->status( $status );
                $obj->save();
                $app->publish_obj( $obj, $original );
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
        $app->redirect( $app->admin_url .
            "?__mode=view&_type=list&_model={$model}&apply_actions={$counter}"
                                                . $app->workspace_param );
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
        if (! $add ) {
            $tag_objs = [];
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
        foreach ( $objects as $obj ) {
            $res = false;
            if ( $add ) {
                $res = $this->add_tags_to_obj( $obj, $add_tags, $name );
            } else {
                $relations = $app->get_relations( $obj, 'tag', $name );
                foreach ( $relations as $relation ) {
                    if ( in_array( $relation->to_id, $tag_ids ) ) {
                        $res = $relation->remove();
                    }
                }
            }
            if ( $res ) {
                $counter++;
                if ( $table->has_status && $obj->has_column( 'status' ) ) {
                    if ( $obj->status == $status_published ) {
                        $original = clone $obj;
                        $app->publish_obj( $obj, $original );
                    }
                }
            }
        }
        if ( $counter ) {
            $add_tags = join( ', ', $add_tags );
            $action = $action['label'] . " ({$add_tags})";
            $this->log( $action, $model, $counter );
        }
        $app->redirect( $app->admin_url .
            "?__mode=view&_type=list&_model={$model}&apply_actions={$counter}"
                                                . $app->workspace_param );
    }

    function remove_tags ( $app, $objects, $action ) {
        return $this->add_tags( $app, $objects, $action, false );
    }

    function add_tags_to_obj ( $obj, $add_tags, $name = 'tags' ) {
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