<?php

class PTPublisher {

    function publish_queue () {
        $app = Prototype::get_instance();
        $db = $app->db;
        $app->get_scheme_from_db( 'urlinfo' );
        $sth = $db->model( 'urlinfo' )->load_iter( ['publish_file' => 4, 'is_published' => 0] );
        while( $result = $sth->fetch( PDO::FETCH_ASSOC ) ) {
            $obj = $db->model( 'urlinfo' )->new( $result );
            $data = $this->publish( $obj );
            $hash = md5( $data );
            $publish = false;
            $file_path = $obj->file_path;
            $md5 = $obj->md5;
            if ( !$md5 && file_exists( $file_path ) ) {
                $md5 = md5( file_get_contents( $file_path ) );
            }
            if ( !$md5 || $md5 !== $hash ) {
                $publish = true;
            }
            if ( $publish ) {
                file_put_contents( $file_path, $data );
            }
            $mime_type = mime_content_type( $file_path );
            $obj->mime_type( $mime_type );
            $obj->is_published( 1 );
            $obj->save();
            usleep( 300000 );
            $app->db->reconnect();
        }
    }

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
                    $container = $mapping->container;
                    if ( $container ) {
                        $obj = $app->db->model( $container )->new();
                    }
                    $ctx->stash( 'archive_date_based', $obj->_model );
                    list( $title, $start, $end ) =
                        $app->title_start_end( $at, $ts, $mapping );
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
                $ctx->vars['current_archive_url'] = $url->url;
                $data = $ctx->build( $tmpl );
            }
            return $data;
        }
    }
}
