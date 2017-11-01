<?php

$pt_path = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'prototype' . DIRECTORY_SEPARATOR;
$workspace_id = 1;
require_once( $pt_path . 'class.Prototype.php' );
$app = new Prototype();
// $app->debug = true;
$app->init();
$url = $app->db->model( 'urlinfo' )->get_by_key( ['relative_url' => $app->request_uri ] );
if (! $url->id ) {
    if ( $workspace_id ) {
        $workspace_id = (int) $workspace_id;
        $workspace = $app->db->model( 'workspace' )->load( $workspace_id );
    } else {
        $request_uri = $app->base . $app->request_uri;
        $workspace = pt_get_workspace_from_url( $app, $request_uri );
    }
    pt_page_not_found( $app, $workspace );
    exit();
}
$ctx = $app->ctx;
$workspace_id = (int) $url->workspace_id;
$workspace = $url->workspace;
if (! $app->user() ) {
    if (! $app->dynamic_view ) {
        pt_page_not_found( $app, $workspace );
    }
}
$object_id = (int) $url->object_id;
$model = $url->model;
$table = $app->db->model( 'table' )->load(['name' => $model ] );
if ( empty( $table ) ) {
    exit();
}
$table = $table[0];
$model = $table->name;
$workspace = null;
$key = $url->key;
if ( $object_id && $model ) {
    if ( $key == 'thumbnail' && $url->meta_id ) {
        $model = 'meta';
        $object_id = (int) $url->meta_id;
        $key = 'data';
    }
    $obj = $app->db->model( $model )->load( $object_id );
    if ( $obj ) {
        if ( $obj->has_column( 'workspace_id' ) && $obj->workspace ) {
            $workspace = $obj->workspace;
        }
        if (! $app->dynamic_view ) {
            if (! $app->can_do( $model, 'edit', $obj, $workspace ) ) {
                pt_page_not_found( $app, $workspace, $app->translate( 'Permission denied.' ) );
            }
        }
    }
}
$ts = $url->archive_date;
$mime_type = $url->mime_type ? $url->mime_type : 'text/html';
if ( $url->class === 'file' ) {
    if ( isset( $obj ) && $obj->has_column( $key ) ) {
        $data = $obj->$key;
        pt_view_print_html( $data, $mime_type );
    }
} else if ( $url->class === 'archive' ) {
    require_once( $pt_path . 'lib' . DIRECTORY_SEPARATOR . 'Prototype' . DIRECTORY_SEPARATOR . 'class.PTPublisher.php' );
    $pub = new PTPublisher;
    $data = $pub->publish( $url );
    pt_view_print_html( $data, $mime_type );
}

function pt_page_not_found ( $app, $workspace, $error = null, $mime_type = 'text/html' ) {
    $tmpl = null;
    if (! $error ) $error = $app->translate( 'Page not found.' );
    $app->init_tags();
    $app->ctx->vars['error_message'] = $error;
    $app->ctx->stash( 'workspace', $workspace );
    if ( $workspace ) {
        $tmpl = $app->db->model( 'template' )->get_by_key( [
        'basename' => '404-error', 'workspace_id' => $workspace->id ] );
    }
    if ( $tmpl && ! $tmpl->id ) {
        $tmpl = $app->db->model( 'template' )->get_by_key( [
            'basename' => '404-error', 'workspace_id' => 0 ] );
    }
    if ( $tmpl ) {
        $app->ctx->stash( 'current_template', $tmpl );
        $data = $app->ctx->build( $tmpl->text );
        pt_view_print_html( $data, $mime_type );
    } else {
        echo htmlspecialchars( $error );
    }
    exit();
}

function pt_view_print_html ( $data, $mime_type = 'text/html' ) {
    header( "Content-Type: {$mime_type}" );
    $file_size = strlen( bin2hex( $data ) ) / 2;
    header( "Content-Length: {$file_size}" );
    echo $data;
    unset( $data );
    exit();
}

function pt_get_workspace_from_url ( $app, $url ) {
    $url = preg_replace( '!/[^\/]*$!', '/', $url );
    $workspace = $app->db->model( 'workspace' )->load( ['site_url' => $url ] );
    if ( is_array( $workspace ) && ! empty( $workspace ) ) {
        return $workspace[0];
    }
    $url = rtrim( $url, '/' );
    $url = preg_replace( '!/[^\/]*$!', '/dummy', $url );
    if ( preg_match( '!https{0,1}:\/\/.*?/!', $url ) ) {
        return pt_get_workspace_from_url( $app, $url );
    }
    return null;
}