<?php

class upgrader_urlmapping {

    public $upgrade_functions = [
        'set_compile_cache' => [
            'method' => 'set_compile_cache',
            'version_limit' => 1.9
        ]
    ];

    function set_compile_cache ( $app, $upgrader, $old_version ) {
        $objects = $app->db->model( 'urlmapping' )->load( [], [],
                                    'id,mapping' );
        foreach ( $objects as $obj ) {
            $obj->save();
        }
    }

}