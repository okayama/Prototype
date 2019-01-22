<?php

class upgrader_template {

    public $upgrade_functions = [
        'set_compile_cache' => [
            'method' => 'set_compile_cache',
            'version_limit' => 1.6
        ]
    ];

    function set_compile_cache ( $app, $upgrader, $old_version ) {
        $objects = $app->db->model( 'template' )->load( [], [],
                                    'id,text' );
        foreach ( $objects as $obj ) {
            $obj->save();
        }
    }

}