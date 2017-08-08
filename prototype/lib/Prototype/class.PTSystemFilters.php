<?php

class PTSystemFilters {

    function get_system_filters ( $model, &$system_filters = [] ) {
        $app = Prototype::get_instance();
        $registry = $app->registry;
        $table = $app->get_table( $model );
        $obj = $app->db->model( $model )->new();
        if ( $table->assign_user ) {
            $system_filters[] = ['name' => 'owned_objects',
                                 'label' => $app->translate( 'My %s', 
                                    $app->translate( $table->plural ) ),
                                 'component' => $this,
                                 'option' => 'user_id',
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
        if ( $model === 'log' ) {
            $system_filters[] = ['name' => 'show_only_errors',
                                 'label' => $app->translate( 'Show only errors' ),
                                 'component' => $this,
                                 'method' => 'show_only_errors'];
        }
        return $app->get_registries( $model, 'system_filters', $system_filters );
    }

    function owned_objects ( $app, &$terms, $model, $col = 'user_id' ) {
        $terms[ $col ] = ['AND' => $app->user()->id ];
    }

    function show_only_errors ( $app, &$terms, $model ) {
        $terms['level'] = ['AND' => 4];
    }

    function filter_status ( $app, &$terms, $model, $status ) {
        $terms['status'] = ['AND' => $status ];
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

}