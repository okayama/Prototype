<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );

class TinyMCE extends PTPlugin {

    function __construct () {
        parent::__construct();
    }

    function post_init ( $app ) {
        // Register plugin to $ctx->include_paths.
        return true;
    }

    function hdlr_if_editormobile ( $args, $content, $ctx, $repeat, $counter ) {
        $ua = $_SERVER['HTTP_USER_AGENT'];
        if ( stripos( $ua, 'iPhone' ) !== false || stripos( $ua, 'Android' ) !== false ) {
            return true;
        }
        return false;
    }

    function tinymce_insert_boilerplate ( $app ) {
        $id = (int) $app->param( 'id' );
        $boilerplate = $app->db->model( 'boilerplate' )->load( $id );
        if (! $boilerplate ) return '';
        $workspace_id = (int) $app->param( 'workspace_id' );
        if ( $workspace_id != $boilerplate->workspace_id ) {
            return;
        }
        $ctx = $app->ctx;
        $model = $app->param( '_model' );
        $object_id = (int) $app->param( 'object_id' );
        if (! $model ) return;
        $obj = $object_id ? $app->db->model( $model )->load( $object_id )
             : $app->db->model( $model )->new();
        if (! $obj->id && $workspace_id && $obj->has_column( 'workspace_id' ) ) {
            $obj->workspace_id( $workspace_id );
        }
        if (! $app->can_do( $model, 'edit', $obj ) ) {
            return; // Permission denied.
        }
        $app->init_tags();
        $ctx->stash( 'current_context', $model );
        $ctx->stash( $model, $obj );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Frame-Options: DENY' );
        $snippet = $boilerplate->snippet;
        $app->print( $app->build( $snippet ), 'text/plain' );
    }
}