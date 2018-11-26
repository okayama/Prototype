<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );
class ExternalPreview extends PTPlugin {

    function __construct () {
        parent::__construct();
    }

    function post_init ( $app ) {
        if ( $app->id != 'Prototype' ) return true;
        if ( $app->mode != 'save' ) return true;
        if ( $app->param( '_preview' ) ) return true;
        $workspace_id = $app->workspace() ? (int) $app->workspace()->id : 0;
        $models = $this->get_config_value( 'externalpreview_models', $workspace_id );
        if (! $models ) return true;
        $model = $app->param( '_model' );
        $models = preg_split( '/\s*,\s*/', strtolower( $models ) );
        if (! in_array( $model, $models ) ) {
            return true;
        }
        $app->register_callback( $model, 'post_save', 'post_save_object', 1, $this );
        return true;
    }

    function hdlr_if_modelhasurlmapping ( $args, $content, $ctx, $repeat, $counter ) {
        $workspace_id = isset( $args['workspace_id'] ) ? (int) $args['workspace_id'] : 0;
        $model = isset( $args['model'] ) ? $args['model'] : null;
        if (! $model ) return false;
        $workspace_ids = [];
        if ( $workspace_id ) {
            $workspace_ids = [0, $workspace_id];
        } else {
            $workspace_ids = 0;
        }
        $app = $ctx->app;
        $mappings = $app->db->model( 'urlmapping' )->count(
            ['workspace_id' => ['IN' => $workspace_ids ], 'model' => $model] );
        return $mappings;
    }

    function post_save_object ( $cb, $app, $obj, $original ) {
        $external_preview = $app->param( '__external_preview' );
        $meta = $app->db->model( 'meta' )->get_by_key( ['model' => $obj->_model,
                                                'object_id' => $obj->id,
                                                'kind' => 'external_preview' ] );
        if ( $external_preview ) {
            $date_expires = $app->param( '__external_preview_date' );
            $time_expires = $app->param( '__external_preview_time' );
            if ( $date_expires ) {
                $date_expires = trim( $date_expires );
                $time_expires = trim( $time_expires );
                if (! $time_expires ) $time_expires = '00:00';
                $date_expires = "{$date_expires}{$time_expires}";
                $date_expires = preg_replace( '/[^0-9]/', '', $date_expires );
                $date_expires .= '00';
                if ( preg_match( '/^[0-9]{14}$/', $date_expires ) ) {
                    $y = substr( $date_expires, 0, 4 );
                    $m = substr( $date_expires, 4, 2 );
                    $d = substr( $date_expires, 6, 2 );
                    if ( checkdate( $m, $d, $y ) ) {
                        $meta->text( $date_expires );
                    }
                }
            }
            $meta->value( 1 );
            $meta->save();
        } else if ( $meta->id ) {
            $meta->remove();
        }
        return true;
    }

    function post_load_urlinfo ( $cb, $app, $url ) {
        if ( $app->id != 'Bootstrapper' ) return true;
        if ( $url->is_published ) return true;
        if ( file_exists( $url->file_path ) ) return true;
        $uuid = $app->param( 'uuid' );
        if (! $uuid ) return true;
        if ( $url->model && $url->object_id ) {
            $workspace_id = $url->workspace_id ? (int) $url->workspace_id : 0;
            $models = $this->get_config_value( 'externalpreview_models', $workspace_id );
            if (! $models ) return true;
            $models = preg_split( '/\s*,\s*/', strtolower( $models ) );
            if (! in_array( $url->model, $models ) ) {
                return true;
            }
            $obj = $app->db->model( $url->model )->get_by_key( (int) $url->object_id );
            if (! $obj->has_column( 'uuid' ) || ! $obj->has_column( 'status' ) ) {
                return true;
            }
            $status_published = $app->status_published( $url->model );
            $can_views = [1];
            if ( $status_published == 4 ) {
                $can_views = [1, 2, 3];
            }
            $status = (int) $obj->status;
            if (! in_array( $status, $can_views ) ) {
                return true;
            }
            if ( $uuid && $obj->uuid ) {
                $user_col = $obj->has_column( 'user_id' ) ? 'user_id' : 'created_by';
                if (! $obj->$user_col ) return;
                $user = $app->db->model( 'user' )->load( (int) $obj->$user_col );
                if (! $user || $user->status == 1 || $user->lock_out ) {
                    return true;
                }
                $meta = $app->db->model( 'meta' )->get_by_key( ['model' => $obj->_model,
                                                    'object_id' => $obj->id,
                                                    'kind' => 'external_preview' ] );
                if (! $meta->id ) {
                    return true;
                }
                if ( $date_expires = $meta->text ) {
                    $ts = date('YmdHis');
                    if ( $ts > $date_expires ) {
                        $meta->remove();
                        return true;
                    }
                }
                $app->user = $user;
            }
        }
        return true;
    }

    function insert_externalpreview_link ( $cb, $app, $param, &$tmpl ) {
        $workspace_id = $app->workspace() ? (int) $app->workspace()->id : 0;
        $models = $this->get_config_value( 'externalpreview_models', $workspace_id );
        if (! $models ) return true;
        $models = preg_split( '/\s*,\s*/', strtolower( $models ) );
        $model = $app->param( '_model' );
        if (! in_array( $model, $models ) ) {
            return true;
        }
        $obj = $app->db->model( $model );
        if (! $obj->has_column( 'uuid' ) || ! $obj->has_column( 'status' ) ) {
            return true;
        }
        $id = (int) $app->param( 'id' );
        if (! $id ) return true;
        $date_specified = false;
        $meta = $app->db->model( 'meta' )->get_by_key( ['model' => $obj->_model,
                                            'object_id' => $id,
                                            'kind' => 'external_preview' ] );
        if ( $meta->id ) {
            $app->ctx->vars['_externalpreview_specified'] = true;
            if ( $date_expires = $meta->text ) {
                $y = substr( $date_expires, 0, 4 );
                $m = substr( $date_expires, 4, 2 );
                $d = substr( $date_expires, 6, 2 );
                if ( checkdate( $m, $d, $y ) ) {
                    $h = substr( $date_expires, 8, 2 );
                    $t = substr( $date_expires, 10, 2 );
                    $app->ctx->vars['_externalpreview_date_expires'] = "{$y}-{$m}-{$d}";
                    $app->ctx->vars['_externalpreview_time_expires'] = "{$h}:{$t}";
                    $date_specified = true;
                }
            }
        }
        if (! $date_specified ) {
            $default_expires = $this->get_config_value( 'externalpreview_default_expires', $workspace_id );
            $default_expires .= '';
            if ( ctype_digit( $default_expires ) ) {
                $expires = date('Y-m-d H:i', strtotime("+{$default_expires} day"));
                list( $d, $t ) = explode( ' ', $expires );
                $app->ctx->vars['_externalpreview_date_expires'] = $d;
                $app->ctx->vars['_externalpreview_time_expires'] = $t;
            }
        }
        $status_published = $app->status_published( $model );
        $app->ctx->vars['_externalpreview_status_published'] = $status_published;
        $include = $this->path() . DS . 'tmpl' . DS . 'screen_footer.tmpl';
        $include = file_get_contents( $include );
        $tmpl = preg_replace( '/<\/form>/', "{$include}</form>", $tmpl );
        return true;
    }
}
