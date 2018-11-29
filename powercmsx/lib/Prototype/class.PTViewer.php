<?php

class PTViewer {

    public $prototype_path = '';
    public $allow_login    = false;

    function view ( $app, $workspace_id = null ) {
        $app->id = 'Bootstrapper';
        $app->bootstrapper = $this;
        $app->init();
        if ( $language = $app->param( '_language' ) ) {
            $app->language = $language;
        }
        $pt_path = dirname( $app->pt_path ) . DS;
        $document_root = $app->document_root;
        list( $request, $param ) = array_pad( explode( '?', $app->request_uri ), 2, null );
        if ( preg_match( '!/$!', $request ) ) {
            $request .= 'index.html';
        }
        $workspace = null;
        $file_path = $document_root . $request;
        $existing_data = null;
        $mtime = null;
        if ( file_exists( $file_path ) && !$app->force_dynamic ) {
            $data = file_get_contents( $file_path );
            $mime_type = PTUtil::get_mime_type( $file_path );
            $mtime = filemtime( $file_path );
            $regex = '<\${0,1}' . 'mt';
            if ( strpos( $mime_type, 'text' ) === false
                || !preg_match( "/$regex/i", $data ) ) {
                $app->print( $data, $mime_type, $mtime );
            } else {
                $existing_data = $data;
            }
        }
        if ( $this->allow_login ) {
            if ( $app->mode =='logout' && $app->dynamic_view ) {
                if ( $app->user() ) {
                    return $this->login_logout( $app );
                }
            } else if ( $app->mode =='login' && $app->dynamic_view ) {
                return $this->login_logout( $app );
            }
        }
        $app->init_tags();
        $url = $app->db->model( 'urlinfo' )->get_by_key( ['relative_url' => $request ] );
        if (! $url->id ) {
            $request = urldecode( $request );
            $url = $app->db->model( 'urlinfo' )->get_by_key( ['relative_url' => $request ] );
        }
        $app->init_callbacks( 'urlinfo', 'post_load_object' );
        $callback = ['name' => 'post_load_object', 'model' => 'urlinfo' ];
        $app->run_callbacks( $callback, 'urlinfo', $url );
        $ctx = $app->ctx;
        if (! $theme_static = $app->theme_static ) {
            $theme_static = $app->path . 'theme-static/';
            $app->theme_static = $theme_static;
        }
        $ctx->vars['theme_static'] = $theme_static;
        $ctx->vars['application_dir'] = __DIR__;
        $ctx->vars['application_path'] = $app->path;
        $ctx->stash( 'current_urlinfo', $url );
        $ctx->vars['current_archive_url'] = $url->url;
        $ctx->stash( 'current_archive_url', $url->url );
        $ctx->vars['current_archive_type'] = $url->archive_type;
        $ctx->vars['app_version'] = $app->app_version;
        unset( $ctx->vars['magic_token'] );
        $ctx->vars['appname'] = $app->appname;
        if (! $url->id ) {
            if ( $workspace_id ) {
                $workspace_id = (int) $workspace_id;
                $workspace = $app->db->model( 'workspace' )->load( $workspace_id );
            } else {
                $request_uri = $app->base . $app->request_uri;
                $workspace = $this->get_workspace_from_url( $app, $request_uri );
            }
            $this->page_not_found( $app, $workspace );
        }
        if ( $app->do_conditional && $url->filemtime && $url->mime_type ) {
            $app->print( null, $url->mime_type, $url->filemtime, true );
        }
        $workspace_id = (int) $url->workspace_id;
        $workspace = $url->workspace;
        if (! $app->user() ) {
            if (! $app->dynamic_view ) {
                $this->page_not_found( $app, $workspace );
            }
        }
        if ( $workspace ) {
            $ctx->vars['appname'] = $workspace->name;
        } else {
            $ctx->vars['appname'] = $app->appname;
        }
        $object_id = (int) $url->object_id;
        $model = $url->model;
        $table = $app->db->model( 'table' )->load(['name' => $model ] );
        if ( empty( $table ) ) {
            $this->page_not_found( $app, $workspace );
        }
        $table = $table[0];
        $model = $table->name;
        $can_view = false;
        if ( $table->has_status ) {
            $publish_status = $table->start_end ? 4 : 2;
            $obj_id = (int) $url->object_id;
            $url_obj = $app->db->model( $model )->load( $obj_id );
            if ( $url_obj->status != $publish_status ) {
                if (! $app->user() ) {
                    $login = $this->login_logout( $app );
                    if ( $login === false ) {
                        $this->page_not_found( $app, $workspace );
                    }
                }
            } else {
                $can_view = true;
            }
        }
        $obj = null;
        $key = $url->key;
        if ( $object_id && $model ) {
            if ( $key == 'thumbnail' && $url->meta_id ) {
                $model = 'meta';
                $object_id = (int) $url->meta_id;
                $key = 'blob';
            }
            $obj = $app->db->model( $model )->load( $object_id );
            if ( $obj ) {
                if ( $obj->has_column( 'workspace_id' ) && $obj->workspace ) {
                    $workspace = $obj->workspace;
                }
                if (! $app->dynamic_view ) {
                    if (! $app->can_do( $model, 'edit', $obj, $workspace ) ) {
                        $this->page_not_found( $app, $workspace, $app->translate( 'Permission denied.' ) );
                    }
                }
                $app->ctx->stash( 'current_object', $obj );
            }
        }
        $mapping = null;
        $mime_type = $url->mime_type ? $url->mime_type : PTUtil::get_mime_type( $request );
        if ( $url->class === 'file' ) {
            if ( isset( $obj ) && $obj->has_column( $key ) ) {
                $callback = ['name' => 'pre_view', 'model' => $obj->_model ];
                $app->run_callbacks( $callback, $obj->_model, $obj, $url );
                $data = $obj->$key;
                $app->print( $data, $mime_type );
            }
        } else if ( $url->class === 'archive' ) {
            $mapping = $url->urlmapping;
            if ( $mapping && $mapping->container ) {
                $ctx->stash( 'current_container', $mapping->container );
            }
            if ( $app->param( '_type' ) == 'form' ) {
                require_once( $pt_path . 'lib' . DS . 'Prototype'
                              . DS . 'class.PTForm.php' );
                $form = new PTForm;
                $mode = $app->mode;
                if ( method_exists( $form, $mode ) ) {
                    $form->$mode( $app, $url );
                }
            }
            require_once( $pt_path . 'lib' . DS . 'Prototype' . DS . 'class.PTPublisher.php' );
            $pub = new PTPublisher;
            $magic_token = $app->param( 'magic_token' )
                         ? $app->param( 'magic_token' ) : $app->request_id;
            $ctx->local_vars['magic_token'] = $magic_token;
            if ( $mtime ) {
                $mtime = ( $mtime > $url->filemtime ) ? $mtime : $url->filemtime;
            } else {
                $mtime = $url->filemtime;
            }
            $data = $pub->publish( $url, $existing_data, $mtime, $obj );
            $update = false;
            if ( $url->publish_file == 3 ) {
                $fmgr = $app->fmgr;
                $fmgr->put( $url->file_path, $data );
                if (! $url->is_published ) {
                    $url->is_published( 1 );
                    $update = true;
                }
            }
            $page = str_replace( $magic_token, '', $data );
            $md5 = md5( $page );
            if ( $md5 != $url->md5 ) {
                $url->md5( $md5 );
                $update = true;
            }
            if ( $update ) $url->save();
            $app->print( $data, $mime_type, $mtime );
        }
    }

    function page_not_found ( $app, $workspace, $error = null, $mime_type = 'text/html' ) {
        header( $app->protocol. ' 404 Not Found' );
        $tmpl = null;
        if (! $error ) $error = $app->translate( 'Page not found.' );
        if ( $workspace ) {
            $tmpl = $app->db->model( 'template' )->get_by_key( [
            'basename' => '404-error', 'workspace_id' => $workspace->id ] );
        }
        if (! $tmpl || ! $tmpl->id ) {
            $tmpl = $app->db->model( 'template' )->get_by_key( [
                'basename' => '404-error', 'workspace_id' => 0 ] );
        }
        if ( $tmpl->id ) {
            // $app->init_tags();
            $app->ctx->vars['error_message'] = $error;
            // $app->ctx->stash( 'workspace', $workspace );
            $app->ctx->stash( 'current_template', $tmpl );
            $data = $app->ctx->build( $tmpl->text );
            $app->print( $data, $mime_type );
        } else {
            if ( $app->error_document404 ) {
                $error_document404 = str_replace( '..', '', $app->error_document404 );
                $error_document404 = $app->document_root . $error_document404;
                if ( file_exists( $error_document404 ) ) {
                    $extension = PTUtil::get_extension( $error_document404 );
                    if ( $extension == 'php' ) {
                        global $error_message;
                        $error_message = $error;
                        header( "Content-Type: {$mime_type}" );
                        require_once( $error_document404 );
                        exit();
                    } else {
                        $data = file_get_contents( $error_document404 );
                        $app->print( $data, $mime_type );
                    }
                }
            }
            $app->print( $error );
        }
        exit();
    }

    function login_logout ( $app ) {
        if (! $app->dynamic_view ) return false;
        if (! $this->allow_login ) return false;
        $request_uri = $app->escape( $app->request_uri );
        list( $request, $param ) = array_pad( explode( '?', $request_uri ), 2, null );
        $ctx = $app->ctx;
        $ctx->vars['prototype_path'] = $this->prototype_path
                                     ? $this->prototype_path : $app->path;
        $ctx->vars['script_uri'] = $request;
        $ctx->vars['return_url'] = $request;
        return $app->mode == 'logout' ? $app->logout() : $app->__mode( 'login' );
        return false;
    }

    function get_workspace_from_url ( $app, $url ) {
        $url = preg_replace( '!/[^\/]*$!', '/', $url );
        $workspace = $app->db->model( 'workspace' )->load( ['site_url' => $url ] );
        if ( is_array( $workspace ) && ! empty( $workspace ) ) {
            return $workspace[0];
        }
        $url = rtrim( $url, '/' );
        $url = preg_replace( '!/[^\/]*$!', '/dummy', $url );
        if ( preg_match( '!https{0,1}:\/\/.*?/!', $url ) ) {
            return $this->get_workspace_from_url( $app, $url );
        }
        return null;
    }
}