<?php

class upgrader_urlinfo {

    public $upgrade_functions = [
        'dirname_and_filemtime' => [
            'method' => 'dirname_and_filemtime',
            'version_limit' => 1.4
        ]
    ];

    function dirname_and_filemtime ( $app, $upgrader, $old_version ) {
        $objects = $app->db->model( 'urlinfo' )->load( [], [],
                                    'id,url,file_path,publish_file,filemtime,dirname' );
        foreach ( $objects as $obj ) {
            $update = false;
            if ( file_exists( $obj->file_path ) && $obj->publish_file != 1 ) {
                $obj->publish_file( 1 );
                $update = true;
            }
            if ( $update || ( ! $obj->filemtime || ! $obj->dirname ) ) {
                $obj->save();
            }
        }
    }

}