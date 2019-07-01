<?php

class upgrader_table {

    public $upgrade_functions = [
        'column_order' => [
            'method' => 'column_order',
            'version_limit' => 2.5
        ]
    ];

    function column_order ( $app, $upgrader, $old_version ) {
        $objects = $app->db->model( 'column' )->load( [], [],
                                    'id,order' );
        foreach ( $objects as $obj ) {
            $obj->order( $obj->order * 10 );
            $obj->save();
        }
    }

}