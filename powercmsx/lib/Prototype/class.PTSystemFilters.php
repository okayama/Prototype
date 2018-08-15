<?php

class PTSystemFilters {

    function get_system_filters ( $model, &$system_filters = [] ) {
        $app = Prototype::get_instance();
        $registry = $app->registry;
        $table = $app->get_table( $model );
        $obj = $app->db->model( $model )->new();
        $_colprefix = $obj->_colprefix;
        $ws_terms = [];
        if ( $obj->has_column( 'workspace_id' ) && $app->workspace() ) {
            $ws_terms = [ 'workspace_id' => ['IN' => [0, $app->workspace()->id ] ] ];
        }
        if ( $obj->has_column( 'workspace_id' ) && ! $app->workspace() ) {
            if ( $table->display_system && !$table->space_child ) {
                $system_filters[] = ['name' => 'system_objects',
                                     'label' => $app->translate( 'System %s', 
                                        $app->translate( $table->plural ) ),
                                     'component' => $this,
                                     'option' => 'workspace_id',
                                     'method' => 'system_objects'];
            }
        }
        if ( $table->assign_user ) {
            $system_filters[] = ['name' => 'owned_objects',
                                 'label' => $app->translate( 'My %s', 
                                    $app->translate( $table->plural ) ),
                                 'component' => $this,
                                 'option' => 'user_id',
                                 'method' => 'owned_objects'];
        } else if ( $obj->has_column( 'created_by' ) ) {
            $system_filters[] = ['name' => 'my_objects',
                                 'label' => $app->translate( 'My %s', 
                                    $app->translate( $table->plural ) ),
                                 'component' => $this,
                                 'option' => 'created_by',
                                 'method' => 'owned_objects'];
        }
        if ( $table->has_status ) {
            $status_published = $app->status_published( $model );
            $max_status = $app->max_status( $app->user(), $model );
            if ( $status_published == 4 ) {
                $status_text =
                    'Draft %s,%s Under Review,Reserved %s,Published %s,Unpublished %s';
            } else {
                $status_text = 'Disabled %s,Enabled %s';
            }
            $status_text = explode( ',', $status_text );
            $i = 0;
            $methods = ['filter_draft', 'filter_review', 'filter_reserved',
                        'filter_published', 'filter_unpublished'];
            foreach ( $status_text as $text ) {
                if ( $max_status > $i ) {
                    $name = $methods[ $i ];
                    $i++;
                    $system_filters[] = ['name' => $name,
                                         'label' => $app->translate( $text,
                                            $app->translate( $table->plural ) ),
                                         'component' => $this,
                                         'option' => $i,
                                         'method' => 'filter_status'];
                }
            }
        }
        if ( $obj->has_column( 'class' ) ) {
            $args  = [ 'group' => [ 'class' ] ];
            $group = $obj->count_group_by( $ws_terms, $args );
            foreach ( $group as $item ) {
                $class_type = $item[ $_colprefix . 'class' ];
                if (! $class_type ) $class_type = '(Class not specified)';
                $class_name = $app->translate( $class_type, null, null, 'default' );
                $class_name = $app->translate( $class_name );
                $system_filters[] = ['name' => 'filter_class_' . $class_type,
                                     'label' => $class_name,
                                     'component' => $this,
                                     'option' => $class_type,
                                     'method' => 'filter_class'];
            }
        }
        if ( $model === 'log' ) {
            $system_filters[] = ['name' => 'show_only_errors',
                                 'label' => $app->translate( 'Show only errors' ),
                                 'component' => $this,
                                 'method' => 'show_only_errors'];
            $args  = ['group' => ['category']];
            $group = $obj->count_group_by( $ws_terms, $args );
            foreach ( $group as $item ) {
                $log_category = $item[ $_colprefix . 'category' ];
                $system_filters[] = ['name' => 'filter_log_category_' . $log_category,
                                     'label' => $app->translate(
                                        'Category is \'%s\'', $log_category ),
                                     'component' => $this,
                                     'option' => $log_category,
                                     'method' => 'filter_log_category'];
            }
            $args  = ['group' => ['level'] ];
            $group = $obj->count_group_by( $ws_terms, $args );
            $log_levels = [1 => 'info', 2 => 'warning', 4 => 'error',
                           8 => 'security', 16 => 'debug'];
            foreach ( $group as $item ) {
                $log_level = $item[ $_colprefix . 'level' ];
                if (! isset( $log_levels[ $log_level ] ) ) continue;
                $log_level_label = $log_levels[ $log_level ];
                $system_filters[] = ['name' => 'filter_log_level_' . $log_level,
                                     'label' => $app->translate(
                                        'Level is \'%s\'', $log_level_label ),
                                     'component' => $this,
                                     'option' => $log_level,
                                     'method' => 'filter_log_level'];
            }
        }
        return $app->get_registries( $model, 'system_filters', $system_filters );
    }

    function delete_filter ( $app ) {
        $app->validate_magic();
        header( 'Content-type: application/json' );
        $_filter_id = (int) $app->param( '_filter_id' );
        if (! $_filter_id ) {
            echo json_encode( ['result' => false ] );
            exit();
        }
        $option = $app->db->model( 'option' )->load( $_filter_id );
        if ( $option && $option->id ) {
            if ( $option->user_id == $app->user()->id && $option->kind == 'list_filter'
                && $option->key == $app->param( '_model' ) ) {
                $workspace_id = $app->workspace() ? $app->workspace()->id : 0;
                $filter_primary = ['key' => $option->key, 'user_id' => $app->user()->id,
                                   'workspace_id' => $workspace_id,
                                   'kind'  => 'list_filter_primary',
                                   'object_id' => $option->id ];
                $primary = $app->db->model( 'option' )->get_by_key( $filter_primary );
                if ( $primary->id ) {
                    $primary->remove();
                }
                $res = $option->remove();
                echo json_encode( ['result' => $res ] );
                exit();
            }
        }
        echo json_encode( ['result' => true ] );
        exit();
    }

    function filter_log_category ( $app, &$terms, $model, $category ) {
        $terms['category'] = $category;
    }

    function filter_log_level ( $app, &$terms, $model ) {
        $terms['level'] = 4;
    }

    function owned_objects ( $app, &$terms, $model, $col = 'user_id' ) {
        $terms[ $col ] = (int) $app->user()->id;
    }

    function system_objects ( $app, &$terms, $model, $col = 'workspace_id' ) {
        $terms[ $col ] = 0;
    }

    function show_only_errors ( $app, &$terms, $model ) {
        $terms['level'] = 4;
    }

    function filter_status ( $app, &$terms, $model, $status ) {
        $terms['status'] = $status;
    }

    function filter_draft ( $app, &$terms, $model ) {
        return $this->filter_status( $app, $terms, $model, 1 );
    }

    function filter_review ( $app, &$terms, $model, $status ) {
        return $this->filter_status( $app, $terms, $model, 2 );
    }

    function filter_reserved ( $app, &$terms, $model, $status ) {
        return $this->filter_status( $app, $terms, $model, 3 );
    }

    function filter_published ( $app, &$terms, $model, $status ) {
        return $this->filter_status( $app, $terms, $model, 4 );
    }

    function filter_unpublished ( $app, &$terms, $model, $status ) {
        return $this->filter_status( $app, $terms, $model, 5 );
    }

    function filter_class ( $app, &$terms, $model, $class ) {
        $terms['class'] = $class;
    }

}