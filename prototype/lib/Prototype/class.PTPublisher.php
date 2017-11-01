<?php

class PTPublisher {

    function publish ( $url ) {
        $app = Prototype::get_instance();
        $ctx = $app->ctx;
        $object_id = (int) $url->object_id;
        $model = $url->model;
        $table = $app->get_table( $model );
        if ( !$table ) {
            return;
        }
        $model = $table->name;
        $workspace = null;
        $key = $url->key;
        $obj = null;
        if ( $object_id && $model ) {
            $obj = $app->db->model( $model )->load( $object_id );
            if ( $obj ) {
                if ( $obj->has_column( 'workspace_id' ) && $obj->workspace ) {
                    $workspace = $obj->workspace;
                }
            }
        }
        $ts = $url->archive_date;
        if ( $urlmapping_id = (int) $url->urlmapping_id ) {
            $mapping = $app->db->model( 'urlmapping' )->load( $urlmapping_id );
            if ( $mapping && $mapping->template ) {
                $tmpl = $mapping->template->text;
                $app->init_tags();
                if ( $workspace ) {
                    $ctx->stash( 'workspace', $workspace );
                }
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
                $data = $ctx->build( $tmpl );
            }
            return $data;
        }
    }
}
