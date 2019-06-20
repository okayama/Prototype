<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );

class LivePreview extends PTPlugin {

    protected $preview    = false;
    protected $preview_ts = null;
    protected $status_pending = false;
    protected $status_in_pending = false;
    protected $in_workspace = false;

    function __construct () {
        parent::__construct();
    }

    function post_init ( $app ) {
        if (! $app->user() ) return;
        $app->do_conditional = false;
        $app->static_conditional = false;
        $workspace_id = (int) $app->workspace_id;
        $workspace = $app->workspace_id
                   ? $app->db->model( 'workspace' )->load( $workspace_id ) : null;
        $ts = isset( $_COOKIE['pt-live-preview-ts'] )
                         ? $_COOKIE['pt-live-preview-ts'] : '';
        if ( $app->workspace_id && isset( $_COOKIE['pt-live-preview-ts-' . $app->workspace_id ] ) ) {
            $ts = $_COOKIE['pt-live-preview-ts-' . $app->workspace_id ];
            $this->in_workspace = true;
        }
        if ( $app->id != 'Bootstrapper' ) {
            return;
        }
        if (! $workspace ) {
            if (! $app->can_do( 'can_livepreview' ) ) {
                return;
            }
        } else {
            if (! $app->can_do( 'can_livepreview' )
                && ! $app->can_do( 'can_livepreview', null, null, $workspace ) ) {
                return;
            }
        }
        if ( $app->mode == 'live_preview' ) {
            $bootstrapper = $app->bootstrapper;
            if ( $bootstrapper->allow_login ) {
                $app->ctx->vars['prototype_path'] = $bootstrapper->prototype_path
                                ? $bootstrapper->prototype_path : $app->path;
                $app->ctx->vars['workspace_id'] = $app->workspace_id;
                return $app->build_page( 'live_preview_site.tmpl' );
            }
        }
        if (! $ts ) return;
        if ( $ts ) {
            $ts = preg_replace( '/[^0-9]/', '', $ts );
            $y = substr( $ts, 0, 4 );
            $m = substr( $ts, 4, 2 );
            $d = substr( $ts, 6, 2 );
            if (! checkdate( $m, $d, $y ) ) {
                $this->clear_lp_cookie();
                return;
            }
        }
        if ( date('YmdHis') > $ts ) {
            $this->clear_lp_cookie();
            return;
        }
        $datebased = $this->in_workspace
                   ? $this->get_config_value( 'livepreview_date_based', $workspace_id )
                   : $this->get_config_value( 'livepreview_date_based' );
        if ( $datebased ) {
            $datebased_models = preg_split( '/\s*,\s*/', $datebased );
            foreach ( $datebased_models as $model ) {
                $app->register_callback( $model, 'publish_date_based',
                                         'publish_date_based', 1000, $this );
            }
        }
        $status_pending = $this->in_workspace
                        ? $this->get_config_value( 'livepreview_status_pending', $workspace_id )
                        : $this->get_config_value( 'livepreview_status_pending' );
        if ( $status_pending ) {
            $this->status_pending = true;
        }
        $app->force_filter = true;
        $app->force_dynamic = true;
        $app->no_cache = true;
        $status_models = $app->db->model( 'table' )->load( ['start_end' => 1] );
        foreach ( $status_models as $table ) {
            $model = $table->name;
            $app->register_callback( $model, 'pre_listing', 'pre_listing', 1000, $this );
            $app->register_callback( $model, 'post_load_objects', 'post_load_objects', 1000, $this );
            $app->register_callback( $model, 'post_load_object', 'post_load_object', 1000, $this );
            $app->register_callback( $model, 'pre_view', 'pre_view', 1000, $this );
            $app->register_callback( $model, 'pre_archive_list', 'pre_archive_list', 1000, $this );
            $app->register_callback( $model, 'pre_archive_count', 'pre_listing', 1000, $this );
        }
        $app->register_callback( 'meta', 'pre_view', 'pre_view', 1000, $this );
        $app->register_callback( 'template', 'post_rebuild', 'post_rebuild', 1000, $this );
        $app->publish_callbacks = true;
        $this->preview = true;
        $this->preview_ts = $ts;
        if ( $this->in_workspace ) {
            $status_in_pending = isset( $_COOKIE['pt-live-preview-pending-' . $app->workspace_id ] )
                     ? $_COOKIE['pt-live-preview-pending-' . $app->workspace_id ] : '';
        } else {
            $status_in_pending = isset( $_COOKIE['pt-live-preview-pending'] )
                     ? $_COOKIE['pt-live-preview-pending'] : '';
        }
        if ( $status_in_pending ) {
            $this->status_in_pending = true;
        }
    }

    function hdlr_if_livepreview ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        if ( $app->id != 'Bootstrapper' ) {
            return false;
        }
        if (! $this->preview ) return false;
        if (! $this->preview_ts ) return false;
        $ts = $this->preview_ts;
        if ( date('YmdHis') > $ts ) {
            $this->clear_lp_cookie();
            return false;
        }
        return true;
    }

    function hdlr_if_livepreview_inpending ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        if ( $app->id != 'Bootstrapper' ) {
            return false;
        }
        if (! $this->preview ) return false;
        if (! $this->preview_ts ) return false;
        $ts = $this->preview_ts;
        if ( date('YmdHis') > $ts ) {
            $this->clear_lp_cookie();
            return false;
        }
        if ( $this->status_pending && $this->status_in_pending ) {
            return true;
        }
        return false;
    }

    function hdlr_livepreview_date ( $args, $ctx ) {
        $app = $ctx->app;
        if ( $app->id != 'Bootstrapper' ) {
            return '';
        }
        if (! $this->preview ) return '';
        if (! $this->preview_ts ) return '';
        $ts = $this->preview_ts;
        if ( date('YmdHis') > $ts ) {
            $this->clear_lp_cookie();
            return '';
        }
        return $ts;
    }

    function post_rebuild ( $cb, $app, $tmpl, &$data ) {
        if ( $app->id != 'Bootstrapper' ) {
            return;
        }
        if (! $this->preview ) return '';
        if (! $this->preview_ts ) return '';
        $ts = $this->preview_ts;
        if ( date('YmdHis') > $ts ) {
            $this->clear_lp_cookie();
            return;
        }
        $workspace_id = (int) $app->workspace_id;
        $html = $this->in_workspace
              ? $this->get_config_value( 'livepreview_insert_html', $workspace_id, true )
              : $this->get_config_value( 'livepreview_insert_html' );
        if ( $html ) {
            if ( preg_match ( '/<\/body>/i', $data ) ) {
                $html = $app->build( $html );
                $data = preg_replace( '/(<\/body>)/i', "$html$1", $data );
            }
        }
    }

    function publish_date_based ( &$cb, $app, &$wheres ) {
        if ( array_values( $wheres) === $wheres ) {
            $model = $cb['model'];
            $sqls = [];
            foreach ( $wheres as $sql ) {
                if ( $sql == "{$model}_status=4" ) {
                    if ( $this->status_pending ) {
                        $sql = "( {$model}_status=4 OR {$model}_status=3 OR {$model}_status=2 )";
                    } else {
                        $sql = "( {$model}_status=4 OR {$model}_status=3 )";
                    }
                }
                $sqls[] = $sql;
            }
            $wheres = $sqls;
        } else {
            // $count_terms;
            if ( isset( $wheres['status'] ) && $wheres['status'] == 4 ) {
                if ( $this->status_pending ) {
                    $wheres['status'] = [2, 3, 4];
                } else {
                    $wheres['status'] = [3, 4];
                }
            }
        }
    }

    function pre_archive_list ( &$cb, $app, &$wheres ) {
        if (! $this->preview ) return;
        if (! $this->preview_ts ) return;
        $model = $cb['model'];
        $ts = $this->preview_ts;
        $status_stmt = "{$model}_status=3";
        if ( $this->status_pending && $this->status_in_pending ) {
            $status_stmt = "( {$model}_status=2 OR {$model}_status=3 )";
        }
        $_extra  = " ( ( {$model}_status=4 AND ( ( {$model}_has_deadline != 1 ";
        $_extra .= "OR {$model}_has_deadline IS NULL ) OR ";
        $_extra .= "{$model}_unpublished_on >= $ts ) ) ";
        $_extra .= "OR ( {$status_stmt} AND {$model}_published_on <= {$ts} ";
        $_extra .= "AND ( ( {$model}_has_deadline != 1 OR {$model}_has_deadline ";
        $_extra .= "IS NULL ) OR {$model}_unpublished_on >= {$ts} ) ) )";
        $sqls = [];
        foreach ( $wheres as $sql ) {
            if ( $sql != "{$model}_status=4" ) {
                $sqls[] = $sql;
            }
        }
        $sqls[] = $_extra;
        $wheres = $sqls;
    }

    function pre_view ( &$cb, $app, &$obj, &$url ) {
        if (! $this->preview ) return;
        if (! $this->preview_ts ) return;
        $ts = $this->preview_ts;
        $model = $obj->_model;
        $args = ['sort' => 'published_on', 'direction' => 'descend', 'limit' => 1];
        if ( $model == 'meta' ) {
            $meta_model = $obj->model;
            $meta_object_id = $obj->object_id;
            $meta_obj = $app->db->model( $meta_model )->load( (int)$meta_object_id );
            if (! $meta_obj ) return;
            if (! $meta_obj->has_column( 'status' ) ||
                ! $meta_obj->has_column( 'has_deadline' ) ||
                ! $meta_obj->has_column( 'unpublished_on' ) ||
                ! $meta_obj->has_column( 'published_on' ) ) {
                return;
            }
            $status_stmt = 3;
            if ( $this->status_pending && $this->status_in_pending ) {
                $status_stmt = [2, 3];
            }
            $revision = $app->db->model( $meta_model )->load
            ( ['rev_object_id' => $meta_obj->id, 'rev_type' => 2, 'status' => $status_stmt,
               'published_on' => ['<=' => $ts ] ], $args );
            if ( count( $revision ) ) {
                $meta_obj = $revision[0];
                $col = $obj->key;
                if ( $meta_obj->has_column( $col ) ) {
                    $meta = $app->db->model( 'meta' )->load( [
                        'object_id' => $meta_obj->id, 'model' => $meta_obj->_model,
                        'key' => $col, 'kind' => 'metadata'
                    ] );
                    if ( is_array( $meta ) && !empty( $meta ) ) {
                        $meta = $meta[0];
                        $metadata = $meta->text;
                        $metadata = json_decode( $metadata, true );
                        if ( $obj->kind == 'thumbnail' && $obj->data ) {
                            $args = unserialize( $obj->data );
                            $args['wants'] = 'data';
                            $data = PTUtil::thumbnail_url( $meta_obj, $args );
                        } else {
                            $data = $meta_obj->$col;
                        }
                        $mime_type = $metadata['mime_type'];
                        $app->print( $data, $mime_type );
                        /*
                        header( "Content-Type: {$mime_type}" );
                        $file_size = strlen( bin2hex( $data ) ) / 2;
                        header( "Content-Length: {$file_size}" );
                        echo $data;
                        exit();
                        */
                    }
                }
            }
        } else {
            $status_stmt = 3;
            if ( $this->status_pending && $this->status_in_pending ) {
                $status_stmt = [2, 3];
            }
            $revision = $app->db->model( $obj->_model )->load
            ( ['rev_object_id' => $obj->id, 'rev_type' => 2, 'status' => $status_stmt,
               'published_on' => ['<=' => $ts ] ], $args );
            if ( count( $revision ) ) {
                $obj = $revision[0];
            }
            $url->publish_file( -1 );
        }
    }

    function post_load_object ( &$cb, $app, &$obj ) {
        if (! $this->preview ) return;
        if (! $this->preview_ts ) return;
        if (! $obj->has_column( 'status' ) ||
            ! $obj->has_column( 'has_deadline' ) ||
            ! $obj->has_column( 'unpublished_on' ) ||
            ! $obj->has_column( 'published_on' ) ) {
            return;
        }
        $ts = $this->preview_ts;
        $args = ['sort' => 'published_on', 'direction' => 'descend', 'limit' => 1];
        $status_stmt = 3;
        if ( $this->status_pending && $this->status_in_pending ) {
            $status_stmt = [2, 3];
        }
        $revision = $app->db->model( $obj->_model )->load
        ( ['rev_object_id' => $obj->id, 'rev_type' => 2, 'status' => $status_stmt,
           'published_on' => ['<=' => $ts ] ], $args );
        if ( count( $revision ) ) {
            $obj = $revision[0];
        }
    }

    function post_load_objects ( &$cb, $app, &$objects, &$count_obj ) {
        if ( empty( $objects ) ) return;
        if (! $this->preview ) return;
        if (! $this->preview_ts ) return;
        $_model = $objects[0];
        if (! $_model->has_column( 'status' ) ||
            ! $_model->has_column( 'has_deadline' ) ||
            ! $_model->has_column( 'unpublished_on' ) ||
            ! $_model->has_column( 'published_on' ) ) {
            return;
        }
        $ts = $this->preview_ts;
        $model = $cb['model'];
        $args = ['sort' => 'published_on', 'direction' => 'descend', 'limit' => 1];
        $status_stmt = 3;
        if ( $this->status_pending && $this->status_in_pending ) {
            $status_stmt = [2, 3];
        }
        $loop_objects = [];
        foreach ( $objects as $obj ) {
            $revision = $app->db->model( $model )->load
            ( ['rev_object_id' => $obj->id, 'rev_type' => 2, 'status' => $status_stmt,
               'published_on' => ['<=' => $ts ] ], $args );
            if ( count( $revision ) ) {
                $revision = $revision[0];
                if ( $revision->has_deadline ) {
                    $unpublished_on = $revision->db2ts( $revision->unpublished_on );
                    if ( $ts > $unpublished_on ) {
                        continue;
                    }
                }
                $loop_objects[] = $revision;
            } else {
                $loop_objects[] = $obj;
            }
        }
        $count_obj = count( $loop_objects );
        $objects = $loop_objects;
    }

    function pre_listing ( &$cb, $app, &$terms, &$args, &$extra ) {
        if (! $this->preview ) return;
        if (! $this->preview_ts ) return;
        $model = $cb['model'];
        $ts = $this->preview_ts;
        if ( isset( $terms['status'] ) ) {
            unset( $terms['status'] );
        } else {
            if ( isset( $cb['args'] ) && isset( $cb['args']['include_draft'] ) ) {
                if ( $cb['args']['include_draft'] ) {
                    return;
                }
            }
        }
        $status_stmt = "{$model}_status=3";
        if ( $this->status_pending && $this->status_in_pending ) {
            $status_stmt = "( {$model}_status=2 OR {$model}_status=3 )";
        }
        $_extra  = " AND ( ( {$model}_status=4 AND ( ( {$model}_has_deadline != 1 ";
        $_extra .= "OR {$model}_has_deadline IS NULL ) OR ";
        $_extra .= "{$model}_unpublished_on >= $ts ) ) ";
        $_extra .= "OR ( {$status_stmt} AND {$model}_published_on <= {$ts} ";
        $_extra .= "AND ( ( {$model}_has_deadline != 1 OR {$model}_has_deadline ";
        $_extra .= "IS NULL ) OR {$model}_unpublished_on >= {$ts} ) ) )";
        $extra = $_extra . $extra;
    }

    function clear_lp_cookie () {
        $app = Prototype::get_instance();
        $postfix = $this->in_workspace ? '-' . $app->workspace_id : '';
        setcookie( "pt-live-preview-ts{$postfix}", '', -1, '/' );
        setcookie( "pt-live-preview-date{$postfix}", '', -1, '/' );
        setcookie( "pt-live-preview-time{$postfix}", '', -1, '/' );
        setcookie( "pt-live-preview-pending{$postfix}", '', -1, '/' );
        if ( isset( $_COOKIE["pt-live-preview-ts{$postfix}"] ) ) {
            unset( $_COOKIE["pt-live-preview-ts{$postfix}"] );
        }
        if ( isset( $_COOKIE["pt-live-preview-date{$postfix}"] ) ) {
            unset( $_COOKIE["pt-live-preview-date{$postfix}"] );
        }
        if ( isset( $_COOKIE["pt-live-preview-time{$postfix}"] ) ) {
            unset( $_COOKIE["pt-live-preview-time{$postfix}"] );
        }
        if ( isset( $_COOKIE["pt-live-preview-pending{$postfix}"] ) ) {
            unset( $_COOKIE["pt-live-preview-pending{$postfix}"] );
        }
    }

    function live_preview ( $app ) {
        if (! $app->can_do( 'can_livepreview' ) ) {
            return $app->error( 'Permission denied.' );
        }
        $workspace_id = $app->workspace() ? $app->workspace()->id : 0;
        $postfix = '';
        if ( $workspace_id ) {
            if ( ! $app->can_do( 'can_livepreview', null, null, $app->workspace() ) ) {
                return $app->error( 'Permission denied.' );
            }
            $this->in_workspace = true;
            $postfix = '-' . $workspace_id;
        } else {
            if (! $app->can_do( 'can_livepreview' ) ) {
                return $app->error( 'Permission denied.' );
            }
        }
        $page_url = $this->get_config_value( 'livepreview_page_url', $workspace_id );
        $this_page = $app->base . $_SERVER['REQUEST_URI'];
        $page_url = $page_url ? $app->build( $page_url ) : '';
        if ( $app->request_method === 'GET' ) {
            if ( $page_url && $page_url != $this_page ) {
                if ( $workspace_id && strpos( $page_url, 'workspace_id=' ) === false ) {
                    $page_url = strpos( $page_url, '?' ) === false
                              ? $page_url .= '?workspace_id=' . $workspace_id
                              : $page_url .= '&workspace_id=' . $workspace_id;
                }
                $app->redirect( $page_url );
                exit();
            }
        }
        $tmpl = dirname( $this->path ) . DS . 'tmpl' . DS . 'live_preview.tmpl';
        $app->ctx->vars['live_preview_date'] = date( 'Y-m-d' );
        $app->ctx->vars['live_preview_time'] = date( 'H:i:s' );
        $app->ctx->vars['status_pending']
            = $this->get_config_value( 'livepreview_status_pending', $workspace_id );
        if ( isset( $_COOKIE["pt-live-preview-ts{$postfix}"] ) ) {
            $ts = $_COOKIE["pt-live-preview-ts{$postfix}"];
            if ( $ts && date('YmdHis') > $ts ) {
                $this->clear_lp_cookie();
            }
        }
        if ( $app->request_method === 'POST' ) {
            $app->validate_magic();
            $date = $app->param( 'live_preview_date' );
            $time = $app->param( 'live_preview_time' );
            $pending = $app->param( 'livepreview_status_pending' );
            $_time = $time;
            $action_type = $app->param( 'action_type' );
            if ( $action_type == 'set' ) {
                $time = preg_replace( '/[^0-9]/', '', $time );
                if ( strlen( $time ) < 6 ) {
                    $pad = 6 - strlen( $time );
                    $_time .= ':';
                    for ( $i = 0; $i < $pad; $i++ ) {
                        $time .= '0';
                        $_time.= '0';
                    }
                }
                $ts = "{$date} {$time}";
                $msg_ts = "{$date} {$_time}";
                $ts = preg_replace( '/[^0-9]/', '', $ts );
                $y = substr( $ts, 0, 4 );
                $m = substr( $ts, 4, 2 );
                $d = substr( $ts, 6, 2 );
                if (! checkdate( $m, $d, $y ) ) {
                    $error_msg = $app->translate( '%s is invalid.',
                        $app->translate( 'Date & Time' ) );
                    $app->ctx->vars['error'] = $error_msg;
                    return $app->build_page( $tmpl );
                }
                if ( $ts && date('YmdHis') > $ts ) {
                    $error_msg = $this->translate( 'The past date can not be specified.' );
                    $app->ctx->vars['error'] = $error_msg;
                    return $app->build_page( $tmpl );
                }
                setcookie( "pt-live-preview-ts{$postfix}", $ts, 0, '/' );
                setcookie( "pt-live-preview-date{$postfix}", $date, 0, '/' );
                setcookie( "pt-live-preview-time{$postfix}", $_time, 0, '/' );
                $_COOKIE["pt-live-preview-ts{$postfix}"] = $ts;
                $date = preg_replace( '/[^0-9]/', '', $date );
                $_time = preg_replace( '/[^0-9]/', '', $_time );
                $_COOKIE["pt-live-preview-date{$postfix}"] = $date;
                $_COOKIE["pt-live-preview-time{$postfix}"] = $_time;
                if ( $pending ) {
                    setcookie( "pt-live-preview-pending{$postfix}", 1, 0, '/' );
                    $_COOKIE["pt-live-preview-pending{$postfix}"] = 1;
                } else {
                    setcookie( "pt-live-preview-pending{$postfix}", '', -1, '/' );
                    if ( isset( $_COOKIE["pt-live-preview-pending{$postfix}"] ) ) {
                        unset( $_COOKIE["pt-live-preview-pending{$postfix}"] );
                    }
                }
                $app->ctx->vars['header_alert_message'] = $this->translate(
                'Set the date and time to live preview at %s.', $msg_ts );
            } else {
                $this->clear_lp_cookie();
                $app->ctx->vars['header_alert_message'] = $this->translate(
                'Clear the date and time to live preview.' );
            }
        }
        return $app->build_page( $tmpl );
    }
}