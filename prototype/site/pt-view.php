<?php

require_once( dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'class.prototype.php' );

$app = new Prototype();
$app->debug = true;
$app->init();
$fi = $app->db->model( 'fileinfo' )->get_by_key( ['relative_url' => $app->request_uri ] );
if (! $fi->id ) {
    exit();
}
$object_id = (int) $fi->object_id;
$table_id = (int) $fi->table_id;
$table = $app->db->model( 'table' )->load( $table_id );
if (! $table ) {
    exit();
}
$model = $table->name;
$key = $fi->key;
if ( $object_id && $model ) {
    $obj = $app->db->model( $model )->load( $object_id );
}
$mime_type = $fi->mime_type ? $fi->mime_type : 'text/html';
if ( $fi->type === 'file' ) {
    if ( isset( $obj ) && $obj->has_column( $key ) ) {
        header( "Content-Type: {$mime_type}" );
        $data = $obj->$key;
        $file_size = strlen( bin2hex( $data ) ) / 2;
        header( "Content-Length: {$file_size}" );
        echo $data;
        unset( $data );
    }
} else if ( $fi->type === 'archive' ) {
    if ( $urlmapping_id = (int) $fi->urlmapping_id ) {
        $urlmapping = $app->db->model( 'urlmapping' )->load( $urlmapping_id );
        if ( $urlmapping && $urlmapping->template ) {
            $tmpl = $urlmapping->template->text;
            $app->init_tags();
            $ctx = $app->ctx;
            $basename = preg_quote( basename( $app->request_uri ) );
            $relative_url = $app->request_uri;
            $relative_path = preg_replace( "/{$basename}$/", '', $relative_url );
            $ctx->stash( 'current_context', $model );
            $ctx->stash( 'current_template', $urlmapping->template );
            $ctx->stash( $model, $obj );
            echo $ctx->build( $tmpl );
        }
    }
}
