<?php

class PTPublisher {

    function publish_queue ( $interval = 300000 ) {
        $app = Prototype::get_instance();
        require_once( 'class.PTUtil.php' );
        $db = $app->db;
        $fmgr = $app->fmgr;
        $app->get_scheme_from_db( 'urlinfo' );
        $sth = $db->model( 'urlinfo' )->load_iter( ['publish_file' =>
            $app->publish_queue, 'is_published' => 0] );
        $counter = 0;
        while( $result = $sth->fetch( PDO::FETCH_ASSOC ) ) {
            $obj = $db->model( 'urlinfo' )->new( $result );
            $data = $this->publish( $obj );
            if ( $data === false ) {
                continue;
            }
            $hash = md5( $data );
            $publish = false;
            $file_path = $obj->file_path;
            $md5 = $obj->md5;
            if ( !$md5 && file_exists( $file_path ) ) {
                $md5 = md5( $fmgr->get( $file_path ) );
            }
            if (! file_exists( $file_path ) || ( !$md5 || $md5 !== $hash ) ) {
                $publish = true;
            }
            if ( $publish ) {
                $fmgr->put( $file_path, $data );
            }
            $extension = PTUtil::get_extension( $file_path );
            $mime_type = PTUtil::get_mime_type( $extension );
            $obj->mime_type( $mime_type );
            $obj->is_published( 1 );
            $obj->save();
            unset( $obj );
            $counter++;
            if ( $interval ) {
                usleep( $interval );
            }
        }
        return $counter;
    }

    function publish ( &$url, $existing_data = null, &$mtime = null,
                      $obj = null, &$update = false ) {
        $app = Prototype::get_instance();
        if (! $mtime ) $mtime = (int)$url->filemtime;
        $ctx = $app->ctx;
        $object_id = (int) $url->object_id;
        $model = $url->model;
        $table = $app->get_table( $model );
        if ( !$table ) {
            return;
        }
        $model = $table->name;
        $workspace = $ctx->stash( 'workspace' ) ? $ctx->stash( 'workspace' ) : null;
        $key = $url->key;
        if ( $object_id && $model ) {
            $obj = $obj ? $obj : $app->db->model( $model )->load( $object_id );
            if ( $obj ) {
                if ( $obj->has_column( 'workspace_id' ) && $obj->workspace ) {
                    $workspace = $obj->workspace;
                }
                if ( $mtime && $obj->has_column( 'modified_on' ) ) {
                    $comp = strtotime( $obj->modified_on );
                    if ( $comp > $mtime ) {
                        $mtime = $comp;
                    }
                }
            }
        }
        if ( $obj ) {
            $callback = ['name' => 'pre_view', 'model' => $obj->_model ];
            $app->init_callbacks( $obj->_model, 'pre_view' );
            $app->run_callbacks( $callback, $obj->_model, $obj, $url );
            $ctx->vars['current_archive_model'] = $obj->_model;
            $ctx->vars['current_object_id'] = $obj->id;
        }
        $ts = $url->archive_date;
        if ( $urlmapping_id = (int) $url->urlmapping_id ) {
            $mapping = $app->db->model( 'urlmapping' )->load( $urlmapping_id );
            if ( $mapping && $mapping->template ) {
                $template = $mapping->template;
                $tmpl = $existing_data ? $existing_data : $template->text;
                $app->init_tags();
                if ( $workspace ) {
                    $ctx->stash( 'workspace', $workspace );
                }
                $basename = preg_quote( basename( $app->request_uri ) );
                $relative_url = $app->request_uri;
                $relative_path = preg_replace( "/{$basename}$/", '', $relative_url );
                $ctx->stash( 'current_urlmapping', $mapping );
                $ctx->stash( 'current_context', $model );
                $ctx->stash( 'current_template', $template );
                if ( $mtime ) {
                    $comp = strtotime( $template->modified_on );
                    if ( $comp > $mtime ) {
                        $mtime = $comp;
                    }
                }
                $compiled = $template->compiled;
                $cache_key = $template->cache_key;
                if ( $compiled && $cache_key ) {
                    $ctx->compiled[ $cache_key ] = $compiled;
                }
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
                if (! $theme_static = $app->theme_static ) {
                    $theme_static = $app->path . 'theme-static/';
                    $app->theme_static = $theme_static;
                }
                $ctx->vars['publish_type'] = $mapping->publish_file;
                $ctx->vars['theme_static'] = $app->theme_static;
                $ctx->vars['current_archive_type'] = $ctx->stash( 'current_archive_type' );
                $ctx->vars['current_archive_url'] = $url->url;
                // if ( stripos( $tmpl, 'setvartemplate' ) !== false ) {
                //     $ctx->compile( $tmpl, false );
                // }
                if ( $app->publish_callbacks ) {
                    $app->init_callbacks( 'template', 'pre_publish' );
                    $callback = ['name' => 'pre_publish', 'model' => 'template',
                                 'urlmapping' => $mapping, 'template' => $template,
                                 'urlinfo' => $url, 'object' => $obj ];
                    $res = $app->run_callbacks( $callback, 'template', $tmpl );
                    if (! $res ) {
                        return false;
                    }
                }
                $data = $app->tmpl_markup === 'mt' ? $ctx->build( $tmpl )
                                                   : $app->build( $tmpl, $ctx );
                if ( $app->publish_callbacks ) {
                    $app->init_callbacks( 'template', 'post_rebuild' );
                    $callback['name'] = 'post_rebuild';
                    $app->run_callbacks( $callback, 'template', $tmpl, $data );
                }
                if ( $mapping->publish_file == 3 && !$app->user() ) {
                    $fmgr = $app->fmgr;
                    $md5 = $url->md5;
                    $file_path = $url->file_path;
                    if ( !$md5 && file_exists( $file_path ) ) {
                        $md5 = md5( $fmgr->get( $file_path ) );
                    }
                    $hash = md5( $data );
                    if ( $md5 != $hash ) {
                        $fmgr->put( $url->file_path, $data );
                        $url->md5( $hash );
                        $update = true;
                    }
                    if (! $url->is_published ) {
                        $url->is_published( 1 );
                        $update = true;
                    }
                    if ( ( $md5 != $hash ) && $app->publish_callbacks ) {
                        $app->init_callbacks( 'template', 'post_publish' );
                        $callback['name'] = 'post_publish';
                        $callback['urlinfo'] = $url;
                        $app->run_callbacks( $callback, 'template', $tmpl, $data );
                    }
                }
                if ( $update ) {
                    $url->save();
                }
            }
            return $data;
        }
    }
}
