<?php
if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
    exit();
}
require_once( 'class.Prototype.php' );
$app = new Prototype();
$app->logging = true;
$app->init();
$db = $app->db;
$blob_path = $db->blob_path;
if (! $blob_path ) {
    echo 'PADO::blob_path not specified.';
    exit();
}
$sth = $db->show_tables();
$tables = $sth->fetchAll();
$pfx = preg_quote( DB_PREFIX, '/' );
$counter = 0;
foreach ( $tables as $key => $table ) {
    $tbl_name = $table[0];
    $tbl_name = preg_replace( "/^$pfx/", '', $tbl_name );
    $app->get_scheme_from_db( $tbl_name );
    $model = $db->model( $tbl_name );
    $db->caching = false;
    $blob_cols = $db->get_blob_cols( $tbl_name );
    $objects = $model->load( null, [], 'id' );
    if (! empty( $blob_cols ) ) {
        array_unshift( $blob_cols, "{$tbl_name}_id" );
        foreach ( $objects as $obj ) {
            $obj = $model->load( (int) $obj->id, [], implode( ',', $blob_cols ) );
            $update = false;
            foreach ( $blob_cols as $blob_col ) {
                if ( $blob_col != "{$tbl_name}_id" && $value = $obj->$blob_col ) {
                    if ( strpos( $value, 'a:1:{s:8:"basename";s:' ) !== 0 ) {
                        $blob = $blob_path . $obj->_model;
                        if (!is_dir( $blob ) ) {
                            mkdir( $blob, 0777, true );
                        }
                        $basename = $db->generate_uuid( $blob );
                        $blob = $blob . DS . $basename;
                        file_put_contents( $blob, $value );
                        $value = ['basename' => $basename ];
                        $value = serialize( $value );
                        $obj->$blob_col( $value );
                        $update = true;
                        $counter++;
                    }
                }
            }
            if ( $update ) {
                $obj->save();
            }
        }
    }
}
if ( $counter ) {
    echo "Saved {$counter} blob(s) to file.";
} else {
    echo "No blob found to save file.";
}
