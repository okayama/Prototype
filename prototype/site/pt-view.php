<?php
require_once( dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'class.Prototype.php' );
$app = new Prototype();
$app->debug = true;
$app->init();
$url = $app->db->model( 'urlinfo' )->get_by_key( ['relative_url' => $app->request_uri ] );
if (! $url->id ) {
    exit();
}
$object_id = (int) $url->object_id;
$model = $url->model;
$table = $app->db->model( 'table' )->load(['name' => $model ] );
if ( empty( $table ) ) {
    exit();
}
$table = $table[0];
$model = $table->name;
$key = $url->key;
if ( $object_id && $model ) {
    if ( $key == 'thumbnail' && $url->meta_id ) {
        $model = 'meta';
        $object_id = (int) $url->meta_id;
        $key = 'data';
    }
    $obj = $app->db->model( $model )->load( $object_id );
}
$ts = $url->archive_date;
$mime_type = $url->mime_type ? $url->mime_type : 'text/html';
if ( $url->class === 'file' ) {
    if ( isset( $obj ) && $obj->has_column( $key ) ) {
        header( "Content-Type: {$mime_type}" );
        $data = $obj->$key;
        $file_size = strlen( bin2hex( $data ) ) / 2;
        header( "Content-Length: {$file_size}" );
        echo $data;
        unset( $data );
    }
} else if ( $url->class === 'archive' ) {
    if ( $urlmapping_id = (int) $url->urlmapping_id ) {
        $mapping = $app->db->model( 'urlmapping' )->load( $urlmapping_id );
        if ( $mapping && $mapping->template ) {
            $tmpl = $mapping->template->text;
            $app->init_tags();
            $ctx = $app->ctx;
            $basename = preg_quote( basename( $app->request_uri ) );
            $relative_url = $app->request_uri;
            $relative_path = preg_replace( "/{$basename}$/", '', $relative_url );
            $ctx->stash( 'current_context', $model );
            $ctx->stash( 'current_template', $mapping->template );
            $ctx->stash( $model, $obj );
            $ctx->stash( 'current_timestamp', '' );
            $ctx->stash( 'current_timestamp_end', '' );
            $ctx->stash( 'archive_date_based', false );
            $archive_type = '';
            if ( $mapping->model === 'template' ) {
                $ctx->stash( 'current_archive_type', 'index' );
                if ( $mapping->template ) {
                    $ctx->stash( 'current_archive_title', $mapping->template->name );
                }
            } else {
                $archive_type = $mapping->model;
                $ctx->stash( 'current_archive_type', $archive_type );
            }
            if ( $mapping->date_based && $ts ) {
                $ts = $mapping->db2ts( $ts );
                $at = $mapping->date_based;
                $ctx->stash( 'archive_date_based', $obj->_model );
                list( $title, $start, $end ) =
                    $app->title_start_end( $at, $ts, $mapping );
                $y = substr( $title, 0, 4 );
                $map_path = str_replace( '%y', $y, $map_path );
                if ( $title != $y ) {
                    $m = substr( $title, 4, 2 );
                    $map_path = str_replace( '%m', $m, $map_path );
                }
                $ctx->stash( 'current_timestamp', $start );
                $ctx->stash( 'current_timestamp_end', $end );
                $ctx->stash( 'current_archive_title', $title );
                $date_col = $app->get_date_col( $obj );
                $ctx->stash( 'archive_date_based_col', $date_col );
                $archive_type .= $archive_type ? '-' . strtolower( $at )
                               : strtolower( $at );
                $ctx->stash( 'current_archive_type', $archive_type );
            } else {
                if ( $mapping->model === $obj->_model ) {
                    $primary = $table->primary;
                    $ctx->stash( 'current_archive_title', $obj->$primary );
                }
            }
            echo $ctx->build( $tmpl );
        }
    }
}
