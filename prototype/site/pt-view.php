<?php
$pt_path = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'prototype' . DIRECTORY_SEPARATOR;
$workspace_id = 1;
require_once( $pt_path . 'class.Prototype.php' );
$app = new Prototype();
$app->in_dynamic = true;
$app->init();
list( $request, $param ) = explode( '?', $app->request_uri );
$url = $app->db->model( 'urlinfo' )->get_by_key( ['relative_url' => $request ] );
if (! $url->id ) {
    if ( $workspace_id ) {
        $workspace_id = (int) $workspace_id;
        $workspace = $app->db->model( 'workspace' )->load( $workspace_id );
    } else {
        $request_uri = $app->base . $request;
        $workspace = pt_get_workspace_from_url( $app, $request_uri );
    }
    pt_page_not_found( $app, $workspace );
    exit();
}
$ctx = $app->ctx;
$workspace_id = (int) $url->workspace_id;
$workspace = $url->workspace;
$object_id = (int) $url->object_id;
$model = $url->model;
$table = $app->db->model( 'table' )->load(['name' => $model ] );
if ( empty( $table ) ) {
    exit();
}
$table = $table[0];
$publish_status = null;
$can_view = false;
if ( $table->has_status ) {
    $publish_status = $table->has_deadline ? 4 : 2;
    $obj_id = (int) $url->object_id;
    $url_obj = $app->db->model( $model )->load( $obj_id );
    if ( $url_obj->status != $publish_status ) {
        if (! $app->user() ) {
            if (! $app->dynamic_view ) {
                pt_page_not_found( $app, $workspace );
            }
        }
    } else {
        $can_view = true;
    }
} else {
    if (! $app->user() ) {
        if (! $app->dynamic_view ) {
            pt_page_not_found( $app, $workspace );
        }
    }
}
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
        if (! $can_view ) {
            if (! $app->dynamic_view ) {
                if (! $app->can_do( $model, 'edit', $obj, $workspace ) ) {
                    pt_page_not_found( $app, $workspace, $app->translate( 'Permission denied.' ) );
                }
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
    if ( $url->publish_file == 3 ) {
        file_put_contents( $url->file_path, $data );
    }
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
        pt_view_print_html( $data, $mime_type, 404 );
    } else {
        echo htmlspecialchars( $error );
    }
    exit();
}

function pt_view_print_html ( $data, $mime_type = 'text/html', $status = 200 ) {
    if ( $status == 200 ) {
        header( 'HTTP/1.1 200 OK' );
    }
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