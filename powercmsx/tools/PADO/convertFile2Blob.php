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
$db->blob2file = false;
$db->caching = false;
$sth = $db->show_tables();
$tables = $sth->fetchAll();
$pfx = preg_quote( DB_PREFIX, '/' );
$counter = 0;
foreach ( $tables as $key => $table ) {
    $tbl_name = $table[0];
    $tbl_name = preg_replace( "/^$pfx/", '', $tbl_name );
    $app->get_scheme_from_db( $tbl_name );
    $model = $db->model( $tbl_name );
    $blob_cols = $db->get_blob_cols( $tbl_name );
    $objects = $model->load( null, [], 'id' );
    if (! empty( $blob_cols ) ) {
        array_unshift( $blob_cols, "{$tbl_name}_id" );
        foreach ( $objects as $obj ) {
            $obj = $model->load( (int) $obj->id, [], implode( ',', $blob_cols ) );
            $update = false;
            foreach ( $blob_cols as $blob_col ) {
                if ( $blob_col != "{$tbl_name}_id" && $value = $obj->$blob_col ) {
                    if ( strpos( $value, 'a:1:{s:8:"basename";s:' ) === 0 ) {
                        $blob = $blob_path . $obj->_model;
                        $unserialized = @unserialize( $value );
                        if ( is_array( $unserialized ) 
                            && isset( $unserialized['basename'] ) ) {
                            $basename = $unserialized['basename'];
                            $blob = $blob . DS . $basename;
                            if ( file_exists( $blob ) ) {
                                $value = file_get_contents( $blob );
                                $obj->$blob_col( $value );
                                $update = true;
                                $counter++;
                            }
                        }
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
    echo "Saved {$counter} file(s) to blob.\n";
} else {
    echo "No file found to save blob.\n";
}
