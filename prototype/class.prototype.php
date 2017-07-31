<?php

if (! defined( 'DS' ) ) {
    define( 'DS', DIRECTORY_SEPARATOR );
}
if (! defined( 'LIB_DIR' ) ) {
    define( 'LIB_DIR', __DIR__ . DS . 'lib' . DS );
}
if (! defined( 'TMPL_DIR' ) ) {
    define( 'TMPL_DIR', __DIR__ . DS . 'tmpl' . DS );
}
ini_set( 'include_path', ini_get( 'include_path' )
                         . PATH_SEPARATOR . __DIR__ . DS . 'lib' );

class Prototype {

    public static $app = null;

    public    $db            = null;
    public    $ctx           = null;
    public    $dictionary    = [];
    public    $language      = null;
    protected $dbprefix      = 'mt_';
    protected $cookie_name   = 'pt-user';
    public    $encoding      = 'UTF-8';
    protected $mode          = 'dashboard';
    public    $timezone      = 'Asia/Tokyo';
    public    $list_limit    = 50;
    public    $passwd_min    = 8;
    public    $passwd_rule   = false;
    public    $sess_timeout  = 86400;
    public    $token_expires = 7200;
    public    $perm_expires  = 86400;
    public    $cookie_path   = '';
    public    $languages     = ['ja', 'en'];
    public    $debug         = false;
    public    $logging       = false;
    public    $stash         = [];
    protected $init_tags;
    protected $protocol;
    public    $remote_ip;
    public    $user;
    public    $appname;
    public    $site_url;
    public    $site_path;
    public    $base;
    public    $path;
    public    $is_secure;
    public    $document_root;
    public    $request_uri;
    public    $query_string;
    public    $admin_url;
    public    $request_method;
    public    $current_magic;
    public    $temp_dir = '/tmp';

    public    $videos        = ['mov', 'avi', 'qt', 'mp4', 'wmv',
                                '3gp', 'asx', 'mpg', 'flv', 'mkv', 'ogm'];

    public    $images        = ['jpeg', 'jpg', 'png', 'bmp', 'ico',
                                'tiff', 'jpe', 'xbm', 'psd', 'pict', 'pic'];

    public    $audios        = ['mp3', 'mid', 'midi', 'wav', 'aif', 'aac', 'flac',
                                'aiff', 'aifc', 'au', 'snd', 'ogg', 'wma', 'm4a'];

    protected $methods       = ['view', 'save', 'delete', 'upload', 'save_order',
                                'display_options', 'get_json', 'export_scheme',
                                'recover_password', 'save_hierarchy'];

    public $callbacks        = ['pre_save'     => [], 'post_save'   => [],
                                'pre_delete'   => [], 'post_delete' => [],
                                'save_filter'  => [], 'delete_filter'=> [] ];

    public $disp_option;
    public $workspace_param;
    public $return_args = [];
    protected $core_tags;

    function __construct () {
        //error_reporting( E_ALL );
        set_error_handler( [ $this, 'errorHandler'] );
        $this->request_method = $_SERVER['REQUEST_METHOD'];
        $this->protocol  = $_SERVER['SERVER_PROTOCOL'];
        $this->remote_ip = $_SERVER['REMOTE_ADDR'];
        $secure = !empty( $_SERVER['HTTPS'] ) &&
            strtolower( $_SERVER['HTTPS'] ) !== 'off' ? 's' : '';
        $this->is_secure = $secure ? true : false;
        $base = "http{$secure}://{$_SERVER['SERVER_NAME']}";
        $port = ( int ) $_SERVER['SERVER_PORT'];
        if (! empty( $port ) &&
            $port !== ( $secure === '' ? 80 : 443 ) ) $base .= ":{$port}";
        $request_uri = NULL;
        if ( isset( $_SERVER['HTTP_X_REWRITE_URL'] ) ) {
            $request_uri  = $_SERVER['HTTP_X_REWRITE_URL'];
        } else if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $request_uri  = $_SERVER['REQUEST_URI'];
        } else if ( isset( $_SERVER['HTTP_X_ORIGINAL_URL'] ) ) {
            $request_uri = $_SERVER['HTTP_X_ORIGINAL_URL'];
            $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'];
        } else if ( isset( $_SERVER['ORIG_PATH_INFO'] ) ) {
            $request_uri = $_SERVER['ORIG_PATH_INFO'];
            if (! empty( $_SERVER['QUERY_STRING'] ) ) {
                $request_uri .= '?' . $_SERVER['QUERY_STRING'];
            }
        }
        $this->base = $base;
        $this->request_uri = $request_uri;
        $request = $request_uri;
        if ( strpos( $request_uri, '?' ) ) {
            list( $request, $this->query_string ) = explode( '?', $request_uri );
        }
        if ( preg_match( "!(^.*?)([^/]*$)!", $request, $mts ) ) {
            list ( $d, $this->path, $this->script ) = $mts;
        }
        $this->document_root = $_SERVER['DOCUMENT_ROOT'];
        if ( $mode = $this->param( '__mode' ) ) {
            $this->mode = $mode;
        }
        $search = preg_quote( $this->document_root, '/' );
        $path = preg_replace( "/^$search/", '', __DIR__ ) . DS;
        $path = str_replace( DS, '/', $path );
        $this->path = $path;
        $this->admin_url = $this->base . $this->path . 'index.php';
    }

    static function get_instance() {
        return self::$app;
    }

    function init() {
        if ( $this->timezone ) date_default_timezone_set( $this->timezone );
        //if ( $this->debug ) error_reporting( E_ALL );
        require_once( LIB_DIR . 'PADO' . DS . 'class.pado.php' );
        $cfg = __DIR__ . DS . 'db-config.json.cgi';
        $db = new PADO();
        $db->configure_from_json( $cfg );
        //$db->debug = 1;
        $prefix = $this->dbprefix;
        $db->prefix = $this->dbprefix;
        $db->idxprefix = '<table>_';
        $db->colprefix = '<model>_';
        $db->id_column = 'id';
        if ( $this->param( '__mode' ) === 'upgrade' &&
            $this->param( 'type' ) === 'install' ) {
            $db->upgrader = true;
        }
        $db->init();
        $this->db = $db;
        $callbacks = ['save_filter_table', 'save_filter_urlmapping',
                      'save_filter_workspace', 'post_save_workspace', 'post_save_role',
                      'post_save_permission', 'delete_filter_table', 'post_save_table',
                      'delete_filter_template', 'post_save_asset', 'save_filter_tag'];
        foreach ( $callbacks as $meth ) {
            $cb = explode( '_', $meth );
            $this->register_callback( $cb[2], $cb[0] . '_' . $cb[1], $meth, 1, $this );
        }
        $this->register_callback( 'role', 'post_delete', 'post_save_role', 1, $this );
        $this->register_callback( 'permission', 'post_delete',
                                                    'post_save_permission', 1, $this );
        require_once( LIB_DIR . 'PAML' . DS .'class.paml.php' );
        $ctx = new PAML();
        //$ctx->debug = 1;
        $ctx->prefix = 'mt';
        $ctx->app = $this;
        $ctx->default_component = $this;
        $ctx->csv_delimiter = ',';
        $ctx->force_compile = true;
        $ctx->esc_trans = true;
        require_once( LIB_DIR . 'Prototype' . DS .'class.PTTags.php' );
        $core_tags = new PTTags;
        $tags = [
            'function'   => ['objectvar', 'include', 'includeparts', 'getobjectname',
                             'assetthumbnail', 'assetproperty', 'getoption', 'statustext',
                             'archivetitle'],
            'block'      => ['objectcols', 'objectloop', 'tables', 'nestableobjects'],
            'conditional'=> ['objectcontext', 'tablehascolumn', 'isadmin'],
            'modifier'   => ['epoch2str'] ];
        foreach ( $tags as $kind => $arr ) {
            $tag_prefix = $kind === 'modifier' ? 'filter_' : 'hdlr_';
            foreach ( $arr as $tag ) {
                $ctx->register_tag( $tag, $kind, $tag_prefix . $tag, $core_tags );
            }
        }
        $this->core_tags = $core_tags;
        $ctx->vars['script_uri'] = $this->admin_url;
        $ctx->vars['this_mode'] = $this->mode;
        $ctx->vars['languages'] = $this->languages;
        $ctx->vars['request_method'] = $this->request_method;
        $ctx->vars['prototype_path'] = $this->path;
        $this->ctx = $ctx;
        self::$app = $this;
        if (!$this->language )
            $this->language = substr( $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2 );
        $locale_dir = __DIR__ . DS . 'locale';
        $locale_dir = __DIR__ . DS . 'locale';
        $locale = $locale_dir . DS . 'default.json';
        if ( file_exists( $locale ) )
            $this->dictionary['default']
                = json_decode( file_get_contents( $locale ), true );
        $lang = $this->language;
        $locale = $locale_dir . DS . $lang . '.json';
        if ( file_exists( $locale ) )
            $this->dictionary[ $lang ]
                = json_decode( file_get_contents( $locale ), true );
        if ( $app->mode == 'setup_db' ) {
            require_once( LIB_DIR . 'Prototype' . DS . 'class.PTUpgrader.php' );
            $upgrader = new PTUpgrader;
            $upgrader->setup_db( true );
        }
        $sql = "SHOW TABLES LIKE '{$prefix}table'";
        $sth = $db->db->prepare( $sql );
        $sth->execute();
        $table = $sth->fetchColumn();
        if (! $table && $this->mode !== 'upgrade' ) 
            $this->redirect( $this->admin_url . '?__mode=upgrade&_type=install' );
        if ( $lang && $this->mode !== 'upgrade' ) {
            $this->is_login();
            $dictionary =& $this->dictionary;
            $dict = $dictionary[ $lang ];
            $phrases = $db->model( 'phrase' )->load( ['lang' => $lang ] );
            foreach ( $phrases as $phrase ) {
                $dict[ $phrase->phrase ] = $phrase->trans;
            }
            $this->dictionary[ $lang ] = $dict;
        }
        $cfgs = $db->model( 'option' )->load( ['kind' => 'config'] );
        list( $site_url, $site_path ) = ['', ''];
        foreach ( $cfgs as $cfg ) {
            $colprefix = $cfg->_colprefix;
            $values = $cfg->get_values();
            $key = $cfg->key;
            if ( $colprefix ) $key = preg_replace( "/^$colprefix/", '', $key );
            $ctx->vars[ $key ] = $cfg->value ? $cfg->value : $cfg->data;
            $this->$key = $cfg->value;
            if ( $key === 'site_url' ) {
                $this->site_url = $cfg->value;
            } else if ( $key === 'site_path' ) {
                $this->site_path = $cfg->value;
            } else if ( $key === 'extra_path' ) {
                $this->extra_path = $cfg->value;
            } else if ( $key === 'asset_publish' ) {
                $this->asset_publish = $cfg->value;
            }
            $ctx->vars['site_url'] = $this->site_url;
            $ctx->vars['site_path'] = $this->site_path;
        }
        if ( $this->mode !== 'upgrade' ) {
            $db->logging = 1;
            $ctx->logging = 1;
            $ctx->log_path = __DIR__ . DS . 'log' . DS;
        }
    }

    function run () {
        $app = Prototype::get_instance();
        $ctx = $app->ctx;
        if ( $app->mode === 'upgrade' ) return $app->upgrade();
        if ( $app->mode !== 'start_recover' 
             && $app->mode !== 'recover_password' && ! $app->is_login() )
            return $app->__mode( 'login' );
        // $this->app = $this;
        $mode = $app->mode;
        if ( $model = $app->param( '_model' ) ) {
            $table = $app->get_table( $model );
        }
        $workspace_id = null;
        $workspace = $app->workspace();
        if ( $workspace ) {
            $workspace_id = $workspace->id;
            $ctx->vars['workspace_scope'] = 1;
            $ctx->vars['workspace_name'] = $workspace->name;
            $ctx->vars['workspace_barcolor'] = $workspace->barcolor;
            $ctx->vars['workspace_bartextcolor'] = $workspace->bartextcolor;
            $ctx->vars['workspace_extra_path'] = $workspace->extra_path;
            $ctx->vars['workspace_id'] = $workspace_id;
        }
        if ( isset( $table ) ) {
            if ( $workspace ) {
                $ctx->vars['workspace_scope'] = $table->space_child;
                $app->workspace_param = '&workspace_id=' . $workspace_id;
            }
            if ( $table->space_child ) {
                $ctx->vars['child_model'] = 1;
            }
        }
        $params = $app->param();
        unset( $params['workspace_id'], $params['permission'],
               $params['id'], $params['saved'] );
        $app->ctx->vars['query_string'] = http_build_query( $params );
        if ( $return_args = $app->param( 'return_args' ) ) {
            parse_str( $return_args, $app->return_args );
        }
        if ( in_array( $mode, $app->methods ) ) {
            return $app->$mode( $app );
        }
        /*
            TODO autoload plugins
        */
        return $app->__mode( $mode );
    }

    function register_callback ( $model, $kind, $meth, $priority, $obj = null ) {
        if (! $priority ) $priority = 5;
        $this->callbacks[ $kind ][ $model ][ $priority ][] = [ $meth, $obj ];
    }

    function __mode ( $mode ) {
        // check mode && check path => ../../
        $app = Prototype::get_instance();
        if ( $mode === 'logout' ) $app->logout();
        $tmpl = TMPL_DIR . $mode . '.tmpl';
        // 
        $ctx = $app->ctx;
        $ctx->vars['this_mode'] = $mode;
        if ( $mode === 'login' ) $app->login();
        if (! isset( $ctx->vars['page_title'] ) || !$ctx->vars['page_title'] ) {
            if ( $mode === 'start_recover' ) {
                $ctx->vars['page_title'] = $app->translate( 'Password Recovery' );
                $ctx->vars['this_mode'] = 'login';
            } else {
                $page_title = explode( '_', $mode );
                array_walk( $page_title, function( &$str ) { $str = ucfirst( $str ); } );
                $ctx->vars['page_title'] = $app->translate( join( ' ', $page_title ) );
            }
        }
        if ( $mode === 'config' ) {
            if ( $app->workspace() ) {
                $app->return_to_dashboard();
            }
            if ( $app->request_method === 'POST' &&
                $app->param( '_type' ) && $app->param( '_type' ) === 'save' ) {
                return $app->save_config( $app );
            }
        }
        $app->assign_params( $app, $ctx );
        $ctx->params['this_mode'] = $mode;
        echo $ctx->build_page( $tmpl );
        exit();
    }

    function user () {
        if ( $this->user ) return $this->user;
        if ( $this->is_login() ) {
            return $this->user;
        }
        $name = $this->param( 'name' );
        $password = $this->param( 'password' );
        if ( $name && $password ) {
            return $this->login( true );
        }
    }

    function login ( $get = false ) {
        $app = Prototype::get_instance();
        if ( $app->request_method === 'POST' ) {
            $name = $app->param( 'name' );
            $password = $app->param( 'password' );
            $user = $app->db->model( 'user' )->load( ['name' => $name ] );
            if (! empty( $user ) ) {
                $user = $user[0];
                if ( password_verify( $password, $user->password ) ) {
                    $remember = $app->param( 'remember' );
                    $expires = $app->sess_timeout;
                    if ( $remember ) {
                        $expires = 60 * 60 * 24 * 365;
                    }
                    $token = $app->magic();
                    $sess = $app->db->model( 'session' )
                        ->get_by_key( ['user_id' => $user->id, 'kind' => 'US'] );
                    $sess->name = $token;
                    $sess->expires = time() + $expires;
                    $sess->start = time();
                    $sess->save();
                    $path = $app->cookie_path ? $app->cookie_path : $app->path;
                    $name = $app->cookie_name;
                    $app->bake_cookie( $name, $token, $expires, $path, $remember );
                }
                $user->last_login_on( date( 'YmdHis' ) );
                $user->save();
                if ( $get ) return $user;
                // todo next_url
                $admin_url = $app->admin_url;
                if ( $workspace_param = $app->workspace_param ) {
                    $workspace_param = ltrim( '&', $workspace_param );
                    $admin_url .= '?' . $workspace_param;
                }
                $app->redirect( $app->admin_url );
            }
        }
    }

    function logout () {
        $app = Prototype::get_instance();
        $user = $app->user();
        if ( $user ) {
            $sess = $app->db->model( 'session' )
                    ->get_by_key( ['user_id' => $user->id, 'kind' => 'US'] );
            if ( $sess->id ) $sess->remove();
            $name = $app->cookie_name;
            $path = $app->cookie_path ? $app->cookie_path : $app->path;
            $app->bake_cookie( $name, '', time() - 1800, $path );
        }
        return $app->__mode( 'login' );
    }

    function view ( $app, $model = null, $type = null ) {
        $type  = $type  ? $type : $app->param( '_type' );
        $model = $model ? $model : $app->param( '_model' );
        if (! $model ) return;
        $db = $app->db;
        $table = $app->get_table( $model );
        $workspace = $app->workspace();
        $workspace_id = $workspace ? $workspace->id : 0;
        if (! $app->param( 'dialog_view' ) && $workspace ) {
            if (! $table->display_space ) {
                if ( $model !== 'workspace' || 
                    ( $model === 'workspace' && $type === 'list' )
                    || ( $model === 'workspace' && !$app->param('id') ) ) {
                    $app->return_to_dashboard();
                }
            }
        }
        if (! $table ) return $app->error( 'Invalid request.' );
        $tmpl = TMPL_DIR . $type . '.tmpl';
        if (! is_readable( $tmpl ) ) return $app->error( 'Invalid request.' );
        $label = $app->translate( $table->label );
        $plural = $app->translate( $table->plural );
        $ctx = $app->ctx;
        $ctx->params['context_model'] = $model;
        $ctx->params['context_table'] = $table;
        $ctx->vars['model'] = $model;
        $ctx->vars['label'] = $app->translate( $label );
        $ctx->vars['plural'] = $app->translate( $plural );
        $ctx->vars['has_hierarchy'] = $table->hierarchy;
        $scheme = $app->get_scheme_from_db( $model );
        $user = $app->user();
        $screen_id = $app->param( '_screen_id' );
        if ( $type === 'list' ) {
            if (! $app->param( 'dialog_view' ) ) {
                if (! $app->can_do( $model, 'list' ) ) {
                    $app->error( 'Permission denied.' );
                }
            }
            $ctx->vars['page_title'] = $app->translate( 'List of %s', $plural );
            $ctx->vars['menu_type'] = $table->menu_type;
            $list_option = $app->get_user_opt( $model, 'list_option', $workspace_id );
            $list_props = $scheme['list_properties'];
            $column_defs = $scheme['column_defs'];
            $labels = $scheme['labels'];
            $search_props = [];
            $sort_props = [];
            $indexes = $scheme['indexes'];
            foreach ( $column_defs as $col => $prop ) {
                if ( $prop['type'] === 'string' || $prop['type'] === 'text' ) {
                    $search_props[ $col ] = true;
                    if ( isset( $indexes[ $col ] ) && strpos( $col, 'rev_' ) !== 0 ) {
                        $sort_props[ $col ] = true;
                    }
                } else if ( $prop['type'] === 'int' || $prop['type'] === 'datetime' ) {
                    if ( strpos( $col, 'rev_' ) !== 0 ) {
                        $sort_props[ $col ] = true;
                    }
                }
            }
            if ( $app->param( 'revision_select' ) ) {
                $ctx->vars['page_title'] = $app->translate( 'List Revisions of %s', $label );
                $list_option->number = 0;
                $cols = 'rev_note,rev_changed,rev_diff,rev_type,modified_by,modified_on';
                if (! $app->param( 'dialog_view') ) {
                    $cols = $table->primary . ',' . $cols;
                }
                $list_option->value( $cols );
            } else if (! $list_option->id ) {
                $list_option->number( $app->list_limit );
                $list_option->value( join ( ',', array_keys( $list_props ) ) );
                $list_option->data( join ( ',', array_keys( $search_props ) ) );
            }
            if ( $list_option->number ) {
                $ctx->vars['_per_page'] = (int) $list_option->number;
            }
            $user_options = explode( ',', $list_option->value );
            $app->disp_option = $user_options;
            $sorted_props = [];
            foreach ( $list_props as $col => $prop ) {
                $user_opt = in_array( $col, $user_options ) ? 1 : 0;
                $sorted_props[ $col ] = [ $labels[ $col ], $user_opt, $prop ];
            }
            $search_cols = explode( ',', $list_option->data );
            foreach ( $search_props as $col => $prop ) {
                $user_opt = in_array( $col, $search_cols ) ? 1 : 0;
                $search_props[ $col ] = [ $labels[ $col ], $user_opt ];
            }
            $sort_option = explode( ',', $list_option->extra );
            foreach ( $sort_props as $col => $prop ) {
                $user_opt = ( $sort_option && $sort_option[0] === $col ) ? 1 : 0;
                $sort_props[ $col ] = [ $labels[ $col ], $user_opt ];
            }
            $ascend = isset( $sort_option[1] ) && $sort_option[1] == 'ascend' ? 1 : 0;
            $descend = isset( $sort_option[1] ) && $sort_option[1] == 'descend' ? 1 : 0;
            $order_props = ['1' => [ $app->translate( 'Ascend' ), $ascend ],
                            '2' => [ $app->translate( 'Descend' ), $descend ] ];
            $ctx->vars['disp_options'] = $sorted_props;
            $ctx->vars['search_options'] = $search_props;
            $ctx->vars['sort_options'] = $sort_props;
            $ctx->vars['order_options'] = $order_props;
            $sortable = false;
            if ( in_array( 'order', $user_options ) && $table->sortable ) {
                $ctx->vars['sortable'] = 1;
                $sortable = true;
            }
            $limit = $app->param( 'limit' ) ? $app->param( 'limit' )
                : $list_option->number;
            $limit = (int) $limit;
            $offset = $app->param( 'offset' );
            $offset = (int) $offset;
            $args = [];
            if ( $limit ) $args['limit'] = $limit;
            $args['offset'] = $offset;
            $sort = $app->param( 'sort' );
            $obj = $db->model( $model );
            $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];
            if ( $sort && !$obj->has_column( $sort ) ) $sort = null;
            if ( $sort && isset( $relations[ $sort ] ) ) {
                $sort = null;
            }
            $direction = $app->param( 'direction' );
            if ( $sort && $direction ) {
                $args['sort'] = $sort;
                $args['direction'] = $direction;
            } else {
                $order_opt = $list_option->extra;
                if ( $order_opt ) {
                    $order_opt = explode( ',', $order_opt );
                    $sort_by = $order_opt[0];
                    $direction = $order_opt[1];
                    if ( $obj->has_column( $sort_by ) ) {
                        $args['sort'] = $sort_by;
                        $args['direction'] = $direction;
                    }
                } else {
                    if ( $sortable && $obj->has_column( 'order' ) ) {
                        $args['sort'] = 'order';
                        $args['direction'] = 'ascend';
                    } else if ( $sort_by = $table->sort_by ) {
                        if ( $obj->has_column( $sort_by ) ) {
                            $direction = $table->sort_order ? $table->sort_order : 'ascend';
                            $args['sort'] = $sort_by;
                            $args['direction'] = $direction;
                        }
                    }
                }
            }
            $query = $app->param( 'query' );
            // todo Search or Filter
            $terms = [];
            if ( is_array( $query ) ) {
                $q = $query[0] ? $query[0] : $query[1];
                if ( $q ) {
                    $args['and_or'] = 'or';
                    $ctx->vars['query'] = $q;
                    $cols = array_keys( $search_props );
                    $q = trim( $q );
                    $q = mb_convert_kana( $q, 's', $app->encoding );
                    $qs = preg_split( "/\s+/", $q );
                    $conditions = [];
                    $counter = 0;
                    if ( count( $qs ) > 1 ) {
                        foreach ( $qs as $s ) {
                            $s = $db->escape_like( $s, 1, 1 );
                            if (! $counter ) {
                                $conditions[] = ['like' => $s ];
                            } else {
                                $conditions[] = ['like' => ['or' => $s ] ];
                            }
                            $counter++;
                        }
                    } else {
                        $conditions = ['like' => $db->escape_like( $q, 1, 1 ) ];
                    }
                    foreach ( $cols as $col ) {
                        if ( $obj->has_column( $col ) )
                            $terms[ $col ] = $conditions;
                    }
                }
            }
            $extra = null;
            if ( $ws = $app->workspace() ) {
                if ( isset( $column_defs['workspace_id'] ) ) {
                    $ws_id = (int) $ws->id;
                    $pfx = $obj->_colprefix;
                    $extra = " AND {$pfx}workspace_id={$ws_id}";
                }
            }
            if ( $table->revisable ) {
                if ( $rev_object_id = $app->param( 'rev_object_id' ) ) {
                    $terms['rev_object_id'] = (int) $rev_object_id;
                    $app->return_args['revision_select'] = 1;
                    $app->return_args['rev_object_id'] = $rev_object_id;
                } else {
                    $terms['rev_type'] = 0;
                }
            }
            if ( $user->is_superuser ) {
                $ctx->vars['can_create'] = 1;
            } else {
                $permissions = $app->permissions();
                if ( $workspace ) {
                    $perms = ( $workspace && isset( $permissions[ $workspace->id ] ) )
                              ? $permissions[ $workspace->id ] : [];
                    $permissions = [ $workspace->id => $perms ];
                    if ( in_array( 'can_create_' . $model, $perms ) ) {
                        $ctx->vars['can_create'] = 1;
                    }
                }
                $user_id = $user->id;
                if ( $table->has_status ) {
                    $has_deadline = $obj->has_column( 'has_deadline' ) ? true : false;
                    $status_published = $app->status_published( $obj->_model ) - 1;
                    if ( $has_deadline ) $status_published--;
                }
                $extra_permission = [];
                $_colprefix = $obj->_colprefix;
                if ( empty( $permissions ) ) {
                    $app->error( 'Permission denied.' );
                }
                $ws_ids = [];
                foreach ( $permissions as $ws_id => $perms ) {
                    $ws_permission = '';
                    if ( $obj->has_column( 'workspace_id' ) ) {
                        $ws_permission = "{$_colprefix}workspace_id={$ws_id}";
                        if ( in_array( 'can_list_' . $model, $perms ) ) {
                            $ws_ids[] = (int) $ws_id;
                        }
                    }
                    if ( $table->has_status ) {
                        if (! in_array( 'can_activate_' . $model, $perms ) ) {
                            if ( $ws_permission ) $ws_permission .= ' AND ';
                            if (! in_array( 'can_review_' . $model, $perms ) ) {
                                $ws_permission .= " {$_colprefix}status < 2";
                            } else {
                                $ws_permission .=
                                    " {$_colprefix}status <= {$status_published}";
                            }
                        }
                    }
                    if ( $obj->has_column( 'user_id' ) ) {
                        if (! in_array( 'can_update_all_' . $model, $perms ) ) {
                            if ( in_array( 'can_update_own_' . $model, $perms ) ) {
                                if ( $ws_permission ) $ws_permission .= ' AND ';
                                $ws_permission .= " {$_colprefix}user_id={$user_id}";
                            }
                        }
                    }
                    if ( $ws_permission ) {
                        $ws_permission = "($ws_permission)";
                        $extra_permission[] = $ws_permission;
                    }
                }
                if (! empty( $extra_permission ) ) {
                    $extra_permission = join( ' OR ', $extra_permission );
                    $extra .= ' AND ';
                    $extra .= $extra_permission;
                }
                if (! empty( $ws_ids ) ) {
                    $extra .= ' AND ';
                    $ws_ids = join( ',', $ws_ids );
                    $extra .= " {$_colprefix}workspace_id IN ({$ws_ids})";
                }
            }
            $objects = $obj->load( $terms, $args, '*', $extra );
            unset( $args['limit'], $args['offset'] );
            $count = $obj->count( $terms, $args );
            $ctx->vars['objects'] = $objects;
            $ctx->vars['object_count'] = $count;
            $ctx->vars['list_limit'] = $limit;
            $ctx->vars['list_offset'] = $offset;
            $ctx->vars['_has_deadline'] = $obj->has_column( 'has_deadline' );
            $maps = $db->model( 'urlmapping' )->count( ['model' => $model ] );
            if ( $maps ) {
                $ctx->vars['_has_mapping'] = 1;
            }
            $next_offset = $offset + $limit;
            $prev_offset = $offset - $limit;
            if ( $count > $next_offset )
                $ctx->vars['next_offset'] = $next_offset;
            if ( $prev_offset >= 0 ) $ctx->vars['has_prev'] = 1;
            $ctx->vars['prev_offset'] = $prev_offset;
            if ( $count < $next_offset ) {
                $ctx->vars['list_to'] = $count;
            } else {
                $ctx->vars['list_to'] = $next_offset;
            }
            $ctx->vars['list_from'] = $offset + 1;
        } else if ( $type === 'edit' ) {
            $user_option = $app->get_user_opt( $model, 'edit_option', $workspace_id );
            if (! $user_option->id ) {
                $display_options = array_keys( $scheme[ $type . '_properties'] );
            } else {
                $display_options = explode( ',', $user_option->value );
            }
            $ctx->vars['display_options'] = $display_options;
            $ctx->vars['_auditing'] = $table->auditing;
            $ctx->vars['_revisable'] = $table->revisable;
            $ctx->vars['_default_status'] = $table->default_status;
            if ( $key = $app->param( 'view' ) ) {
                if ( $db->model( $model )->has_column( $key ) ) {
                    if ( $screen_id ) {
                        $screen_id .= '-' . $key;
                        $session = $db->model( 'session' )->get_by_key(
                            ['name' => $screen_id, 'user_id' => $user->id ]
                        );
                    }
                    if ( isset( $session ) && $session->id ) {
                        $assetproperty = json_decode( $session->text, 'true' );
                        $app->asset_out( $assetproperty, $session->data );
                    }
                }
            }
            if ( $id = $app->param( 'id' ) ) {
                if ( $model === 'workspace' ) {
                    $ctx->vars['page_title'] = $app->translate( '%s Settings', $label );
                } else {
                    if ( $app->param( 'edit_revision' ) ) {
                        $ctx->vars['page_title'] = $app->translate( 'Edit Revision of %s',
                                                                                 $label );
                    } else {
                        $ctx->vars['page_title'] = $app->translate( 'Edit %s', $label );
                    }
                }
                $id = (int) $id;
                if (! $id ) return $app->error( 'Invalid request.' );
                $obj = $db->model( $model )->load( $id );
                if ( is_object( $obj ) ) {
                    if ( $primary = $table->primary ) {
                        $primary = strip_tags( $obj->$primary );
                        $ctx->vars['html_title'] = $primary . ' | '
                            . $ctx->vars['page_title'];
                    }
                    if ( $obj->has_column( 'workspace_id' ) ) {
                        if ( $workspace && $workspace->id != $obj->workspace_id ) {
                            $app->return_to_dashboard();
                        }
                    }
                    $permalink = $app->get_permalink( $obj );
                    $ctx->vars['permalink'] = $permalink;
                    $ctx->stash( $model, $obj );
                    $ctx->stash( 'object', $obj );
                    $ctx->stash( 'model', $model );
                    if ( $key = $app->param( 'view' ) ) {
                        if ( $obj->$key ) {
                            $assetproperty = $app->get_assetproperty( $obj, $key );
                            $app->asset_out( $assetproperty, $obj->$key );
                        }
                    }
                    if ( $table->revisable ) {
                        $revisions = $db->model( $obj->_model )->count(
                            ['rev_object_id' => $obj->id ] );
                        $ctx->vars['_revision_count'] = $revisions;
                    }
                    $ctx->vars['can_delete'] = $app->can_do( $model, 'delete', $obj );
                } else {
                    $app->return_to_dashboard();
                }
            } else {
                $ctx->vars['page_title'] = $app->translate( 'New %s', $label );
                $obj = $db->model( $model )->new();
                if ( $obj->has_column( 'published_on' ) ) {
                    $obj->published_on( $app->current_ts() );
                }
                if ( $obj->has_column( 'user_id' ) ) {
                    $obj->user_id( $app->user()->id );
                }
                if ( $obj->has_column( 'workspace_id' ) ) {
                    $obj->workspace_id( $workspace_id );
                }
                $ctx->stash( 'object', $obj );
            }
            if (! $app->can_do( $model, $type, $obj ) ) {
                $app->error( 'Permission denied.' );
            }
            $ctx->vars['can_create'] = 1;
            $ctx->vars['max_status'] = $app->max_status( $user, $model );
            $ctx->stash( 'current_context', $model );
            if ( $app->get_permalink( $obj, true ) ) {
                $ctx->vars['has_mapping'] = 1;
            }
            $ctx->vars['screen_id'] = $screen_id ? $screen_id : $app->magic();
        } else if ( $type === 'hierarchy' ) {
            if (! $app->can_do( $model, 'edit' ) ) {
                $app->error( 'Permission denied.' );
            }
            $ctx->vars['page_title'] = $app->translate( 'Manage %s Hierarchy', $plural );
            if ( $app->param( 'saved_hierarchy' ) ) {
                $ctx->vars['header_alert_message'] =
                    $app->translate( '%s hierarchy saved successfully.', $plural );
            }
        }
        // todo http header
        $ctx->vars['return_args'] = http_build_query( $app->return_args );
        echo $ctx->build_page( $tmpl );
        exit();
    }

    function get_permalink ( $obj, $has_map = false ) {
        $app = Prototype::get_instance();
        $table = $app->get_table( $obj->_model );
        $terms = ['model' => $obj->_model ];
        if ( $has_map && $obj->_model === 'template' ) {
            $terms['template_id'] = $obj->id;
            unset( $terms['model'] );
        }
        $urlmapping = $app->db->model( 'urlmapping' )->load( $terms,
            ['sort_by' => 'order', 'direction' => 'ascend', 'limit'=> 1] );
        if (! empty( $urlmapping ) ) {
            $urlmapping = $urlmapping[0];
            if ( $has_map ) return $urlmapping;
            if ( $fi = $app->db->model( 'fileinfo' )->get_by_key(
                ['urlmapping' => $urlmapping->id, 'model' => $table->name,
                 'type' => 'archive', 'object_id' => $obj->id ] ) ) {
                return $fi->url;
            }
        }
        return false;
    }

    function max_status ( $user, $model ) {
        $app = Prototype::get_instance();
        $workspace_id = $app->workspace() ? $app->workspace()->id : 0;
        $table = $app->get_table( $model );
        if ( $user->is_superuser ) {
            $max_status = 5;
        } else {
            $permissions = $app->permissions();
            $perms = isset( $permissions[ $workspace_id ] )
                   ? $permissions[ $workspace_id ] : [];
            $max_atstus = 1;
            if ( $table->has_status ) {
                $status_published = $app->status_published( $model );
                if ( $status_published === 4 ) {
                    if ( in_array( 'can_activate_' . $model, $perms ) ) {
                        $max_status = 5;
                    } else if ( in_array( 'can_review_' . $model, $perms ) ) {
                        $max_status = 2;
                    }
                }
            } else {
                $max_status = 2;
            }
        }
        return $max_status;
    }

    function can_do ( $model, $action, $obj = null  ) {
        // create, edit, list, delete
        $app = Prototype::get_instance();
        $table = $app->get_table( $model );
        if (! $app->workspace() ) {
            if ( $table->space_child && $action === 'edit' ) {
                return false;
            } else if ( $action === 'list' && !$table->display_system ) {
                return false;
            }
        }
        if ( $app->user()->is_superuser ) return true;
        if ( $model == 'user' ) {
            if ( $obj && $obj->id == $app->user()->id ) return true;
        }
        $permissions = $app->permissions();
        $workspace = $app->workspace();
        if ( $obj && $obj->has_column( 'workspace_id' ) ) {
            $workspace = $obj->workspace;
        }
        $ws_perms = ( $workspace && isset( $permissions[ $workspace->id ] ) )
                  ? $permissions[ $workspace->id ] : [];
        if ( $workspace ) {
            $perms = $ws_perms;
        } else {
            $perms = isset( $permissions[0] ) ? $permissions[0] : [];
        }
        if ( $action == 'list' ) {
            $name = 'can_list_' . $model;
            if ( $workspace ) {
                return in_array( $name, $perms );
            } else {
                foreach ( $permissions as $perm ) {
                    if ( in_array( $name, $perm ) ) {
                        return true;
                    }
                }
            }
        } else if ( $action === 'edit' || $action === 'save' || $action === 'delete' ) {
            list( $name, $range ) = ['', ''];
            if (! $obj->id ) {
                $name = 'can_create_' . $model;
            } else {
                $range = 'can_update_all_' . $model;
                if ( $table->has_status ) {
                    $max_status = $app->max_status( $app->user(), $model );
                    if ( $obj->status > $max_status ) {
                        return false;
                    }
                }
            }
            if ( $name && ! in_array( $name, $perms ) ) {
                return false;
            }
            if ( $range && !in_array( $range, $perms ) ) {
                if ( $obj->has_column( 'user_id' ) ) {
                    $range = 'can_update_own_' . $model;
                    if ( in_array( $range, $perms ) ) {
                        if ( $obj->user_id == $app->user()->id ) {
                            return true;
                        }
                    }
                }
                return false;
            } else {
                return true;
            }
        }
        return false;
    }

    function permissions () {
        $app = Prototype::get_instance();
        $user = $app->user();
        $session = $app->db->model( 'session' )->get_by_key(
                                                ['user_id' => $user->id,
                                                 'name' => 'user_permissions',
                                                 'kind' => 'PM'] );
        if ( $session->id ) {
            if ( $session->expires > time() ) {
                return json_decode( $session->text, true );
            }
        }
        $permissions = $app->db->model( 'permission' )->load( ['user_id' => $user->id ] );
        $role_ids = [];
        $workspace_map = [];
        foreach ( $permissions as $perm ) {
            $relations = $app->get_relations( $perm );
            foreach ( $relations as $relation ) {
                if ( $relation->to_obj === 'role' ) {
                    $role_ids[] = $relation->to_id;
                    $workspace_map[ $relation->to_id ] = $perm->workspace_id;
                }
            }
        }
        $roles = $app->db->model( 'role' )->load( ['id' => ['IN' => $role_ids ] ] );
        $user_permissions = [];
        $tables = $app->db->model( 'table' )->load();
        $table_map = [];
        foreach( $tables as $table ) {
            $table_map[ $table->id ] = $table->name;
        }
        foreach ( $roles as $role ) {
            $workspace_id = $workspace_map[ $role->id ];
            $perms = $app->get_relations( $role );
            $ws_permission = isset( $user_permissions[ $workspace_id ] ) 
                ? $user_permissions[ $workspace_id ] : [];
            foreach ( $perms as $p ) {
                if ( $p->to_obj === 'table' ) {
                    $model = $table_map[ $p->to_id ];
                    $name = $p->name . '_' . $model;
                    if (! in_array( $name, $ws_permission ) ) {
                        $ws_permission[] = $name;
                    }
                }
            }
            $user_permissions[ $workspace_id ] = $ws_permission;
        }
        $json = json_encode( $user_permissions );
        $session = $app->db->model( 'session' )->get_by_key(
                                                ['user_id' => $user->id,
                                                 'name' => 'user_permissions',
                                                 'kind' => 'PM', 'text' => $json ] );
        $session->start( time() );
        $session->expires( time() + $app->perm_expires );
        $session->save();
        return $user_permissions;
    }

    function asset_out ( $prop, $data ) {
        $app = Prototype::get_instance();
        $file_name = $prop['basename'];
        $extension = $prop['extension'];
        $file_name .= $extension ? '.' . $extension : '';
        $mime_type = $prop['mime_type'];
        $file_size = $prop['file_size'];
        header( "Content-Type: {$mime_type}" );
        header( "Content-Length: {$file_size}" );
        if ( $app->param( 'download' ) ) {
            header( "Content-Disposition: attachment;"
                . " filename=\"{$file_name}\"" );
            header( "Pragma: " );
        }
        echo $data;
        exit();
    }

    function save_order ( $app ) {
        $model = $app->param( '_model' );
        $app->validate_magic();
        if (! $app->can_do( $model, 'edit' ) ) {
            $app->error( 'Permission denied.' );
        }
        $objects = $app->get_object( $model );
        if (! is_array( $objects ) && is_object( $objects ) ) {
            $objects = [ $objects ];
        }
        $table = $app->get_table( $model );
        $error = '';
        foreach ( $objects as $obj ) {
            $order = $app->param( 'order_' . $obj->id );
            $order = (int) $order;
            $obj->order( $order );
            $obj->save();
        }
        $app->redirect( $app->admin_url .
            "?__mode=view&_type=list&_model={$model}&saved_order=1"
                                                . $app->workspace_param );
    }

    function save_hierarchy ( $app ) {
        $model = $app->param( '_model' );
        $app->validate_magic();
        $workspace_id = $app->param( 'workspace_id' );
        $_nestable_output = $app->param( '_nestable_output' );
        $children = json_decode( $_nestable_output, true );
        if (! $app->can_do( $model, 'edit' ) ) {
            $app->error( 'Permission denied.' );
        }
        $order = 1;
        $app->set_hierarchy( $model, $children, 0, $order );
        $table = $app->get_table( $model );
        $nickname = $app->user()->nickname;
        $plural = $app->translate( $table->plural );
        $params = [ $plural, $nickname ];
        $message = $app->translate( "%1\$s hierarchy changed by %2\$s.", $params );
        $app->log( ['message'  => $message,
                    'category' => 'hierarchy',
                    'model'    => $table->name,
                    'metadata' => $_nestable_output,
                    'level'    => 'info'] );
        $app->redirect( $app->admin_url .
            "?__mode=view&_type=hierarchy&_model={$model}&saved_hierarchy=1"
            . $app->workspace_param );
    }

    function set_hierarchy ( $model, $children, $parent = 0,
                             &$order = 1, &$error = null ) {
        $app = Prototype::get_instance();
        $workspace = $app->workspace();
        foreach ( $children as $value ) {
            $id = $value['id'];
            $children = isset( $value['children'] ) ? $value['children'] : null;
            $obj = $app->db->model( $model )->load( $id );
            if (! $obj ) continue;
            $obj->parent_id( $parent );
            if ( $obj->has_column( 'order' ) ) {
                $obj->order( $order );
            }
            $obj->save();
            $order++;
            if ( $children ) {
                $app->set_hierarchy( $model, $children, $id, $order, $error );
            }
        }
    }

    function display_options ( $app ) {
        $model = $app->param( '_model' );
        $app->validate_magic();
        $workspace_id = $app->param( 'workspace_id' );
        $scheme = $app->get_scheme_from_db( $model );
        $type = $app->param( '_type' );
        $display_options = array_keys( $scheme[ $type . '_properties'] );
        $options = [];
        foreach ( $display_options as $opt ) {
            if ( $app->param( '_d_' . $opt ) ) {
                $options[] = $opt;
            }
        }
        $user_option = $app->get_user_opt( $model, $type . '_option', $workspace_id );
        $user_option->value( join( ',', $options ) );
        if ( $type === 'list' ) {
            $number = $app->param( '_per_page' );
            $user_option->number( (int) $number );
            $search_cols = [];
            $sort_by = [];
            if ( $type === 'list' ) {
                $column_defs = array_keys( $scheme['column_defs'] );
                foreach ( $column_defs as $col ) {
                    if ( $app->param( '_s_' . $col ) ) {
                        $search_cols[] = $col;
                    }
                    if ( $app->param( '_by_' . $col ) ) {
                        $sort_by[0] = $col;
                    }
                }
            }
            if ( $user_sort_by = $app->param( '_user_sort_by' ) ) {
                $sort_by[0] = $user_sort_by;
            } else {
                $sort_by[0] = 'id';
            }
            if ( $user_sort_order = $app->param( '_user_sort_order' ) ) {
                $sort_by[1] = $user_sort_order == 1 ? 'ascend' : 'descend';
            } else {
                $sort_by[1] = 1;
            }
            $user_option->data( join( ',', $search_cols ) );
            $user_option->extra( join( ',', $sort_by ) );
        }
        if ( $app->param( 'workspace_id' ) ) {
            $user_option->workspace_id( $app->param( 'workspace_id' ) );
        }
        $res = $user_option->save();
        if ( $type === 'edit' ) {
            header( 'Content-type: application/json' );
            echo json_encode( ['result'=> $res ] );
            exit();
        }
        $dialog_view = $app->param( 'dialog_view' ) ? '&dialog_view=1' : '';
        $dialog_view .= $app->param( 'single_select' ) ? '&single_select=1' : '';
        $app->redirect( $app->admin_url .
            "?__mode=view&_type={$type}&_model={$model}&saved_props=1"
            . $app->workspace_param . $dialog_view );
    }

    function upload ( $app ) {
        // todo permission
        $app->validate_magic();
        $upload_dir = $app->upload_dir();
        $screen_id = $app->param( '_screen_id' );
        $name = $app->param( 'name' );
        $model = $app->param( '_model' );
        $table = $app->get_table( $model );
        if (! $table ) return $app->error( 'Invalid request.' );
        $column = $app->db->model( 'column' )->load(
            ['table_id' => $table->id, 'name' => $name, 'edit' => 'file'] );
        if ( is_array( $column ) && !empty( $column ) ) {
            $type = $column[0]->options;
            $filename = $_FILES['files']['name'];
            if ( is_array( $filename ) ) $filename = $filename[0];
            $ext = preg_replace("/^.*\.([^\.].*$)/is", '$1', $filename );
            $type_label = ( $type === 'pdf' ) ? 'PDF' : ucfirst( $type );
            if ( $type ) {
                $type .= 's';
                $extensions = ( $type === 'pdfs' ) ? ['pdf'] : $app->$type;
                if (! in_array( $ext, $extensions ) ) {
                    $error = $app->translate( 'The file must be an %s.',
                        $app->translate( $type_label ) );
                    echo json_encode( ['message'=> $error ] );
                    exit();
                }
            }
        } else {
            header( 'Content-type: application/json' );
            echo json_encode( ['result'=> false ] );
            exit();
        }
        $magic = "{$screen_id}-{$name}";
        $user = $app->user();
        $options = ['upload_dir' => $upload_dir . DS,
                    'magic' => $magic, 'user_id' => $user->id ,'prototype' => $app ];
        $sess = $app->db->model( 'session' )
            ->get_by_key( ['name' => $magic,
                           'user_id' => $user->id, 'kind' => 'UP'] );
        $sess->start( time() );
        $sess->expires( time() + $app->sess_timeout );
        $sess->save();
        $upload_handler = new UploadHandler( $options );
    }

    function upload_dir () {
        $app = Prototype::get_instance();
        $app->db->logging = false;
        $app->ctx->logging = false;
        require_once( LIB_DIR . 'jQueryFileUpload' . DS . 'UploadHandler.php' );
        $upload_dir = $app->temp_dir;
        if ( $upload_dir ) {
            $upload_dir = rtrim( $upload_dir, DS );
            $upload_dir .= DS;
        }
        if (! $upload_dir ) $upload_dir =  __DIR__ . DS . 'tmp' . DS;
        $upload_dir = tempnam( $upload_dir, '' );
        unlink( $upload_dir );
        mkdir( $upload_dir . DS, 0777, TRUE );
        return $upload_dir;
    }

    function get_json ( $app ) {
        // todo permission
        $model = $app->param( '_model' );
        $scheme = $app->get_scheme_from_db( $model );
        header( 'Content-type: application/json' );
        echo json_encode( $scheme );
    }

    function export_scheme ( $app ) {
        // todo permission
        $model = $app->param( 'name' );
        $scheme = $app->get_scheme_from_db( $model );
        $table = $app->get_table( $model );
        $scheme['label'] = $table->label;
        unset( $scheme['labels'] );
        header( "Content-Disposition: attachment;"
            . " filename=\"{$model}.json\"" );
        header( "Pragma: " );
        echo json_encode( $scheme, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT );
    }

    function save_config ( $app ) {
        $app->validate_magic();
        // todo permission
        // appname // site_path
        $appname = $app->param( 'appname' );
        $site_path = $app->param( 'site_path' );
        $site_url = $app->param( 'site_url' );
        $extra_path = $app->param( 'extra_path' );
        $asset_publish = $app->param( 'asset_publish' );
        $copyright = $app->param( 'copyright' );
        $system_email = $app->param( 'system_email' );
        $default_widget = $app->param( 'default_widget' );
        $barcolor = $app->param( 'barcolor' );
        $bartextcolor = $app->param( 'bartextcolor' );
        $errors = [];
        if (!$appname || !$site_url ) {
            $errors[] = $app->translate( 'App Name and Site URL is required.' );
        }
        if ( $site_url && !$app->is_valid_url( $site_url, $msg ) ) {
            $errors[] = $msg;
        }
        if (!$app->is_valid_email( $system_email, $msg ) ) {
            $errors[] = $msg;
        }
        if (! preg_match( '/\/$/', $site_url ) ) {
            $site_url .= '/';
        }
        $site_path = rtrim( $site_path, DS );
        $app->sanitize_dir( $extra_path );
        // TODO Check Path
        if ( $extra_path &&
            !$app->is_valid_property( str_replace( '/', '', $extra_path ) ) ) {
            $errors[] = $app->translate(
                'Upload Path contains an illegal character.' );
        }
        if (! empty( $errors ) ) {
            $ctx = $app->ctx;
            $ctx->vars['error'] = join( "\n", $errors );
            $app->assign_params( $app, $ctx );
            $tmpl = TMPL_DIR . 'config.tmpl';
            echo $ctx->build_page( $tmpl );
            exit();
        }
        $cfgs = ['appname'    => $appname,
                 'copyright'  => $copyright,
                 'site_path'  => $site_path,
                 'site_url'   => $site_url,
                 'extra_path' => $extra_path,
                 'barcolor'   => $barcolor,
                 'bartextcolor' => $bartextcolor,
                 'asset_publish' => $asset_publish,
                 'system_email' => $system_email,
                 'default_widget' => $default_widget ];
        $app->set_config( $cfgs );
        $app_path = $app->site_path;
        $app_url  = $app->site_url;
        if ( $site_path !== $app_path || $site_url !== $app_url ) {
            $app->rebuild_fileinfo( $site_url, $site_path );
        }
        $app->redirect( $app->admin_url . '?__mode=config&saved=1'
                         . $app->workspace_param );
    }

    function save ( $app ) {
        $db = $app->db;
        $callbacks = $app->callbacks;
        $model = $app->param( '_model' );
        $app->validate_magic();
        $table = $app->get_table( $model );
        if (! $table ) return $app->error( 'Invalid request.' );
        $action = $app->param( 'id' ) ? 'edit' : 'create';
        $obj = $db->model( $model )->new();
        $scheme = $app->get_scheme_from_db( $model );
        if (! $scheme ) return $app->error( 'Invalid request.' );
        $db->scheme[ $model ] = $scheme; // todo cache and skip
        $primary = $scheme['indexes']['PRIMARY'];
        $id = $app->param( $primary );
        if ( $id ) {
            $obj = $obj->load( $id );
            if (! $obj )
                return $app->error( 'Cannot load %s (ID:%s)', [ $table->label, $id ] );
        }
        if (! $app->can_do( $model, $action, $obj ) ) {
            $app->error( 'Permission denied.' );
        }
        $orig_relations = $app->get_relations( $obj );
        $orig_metadata  = $app->get_meta( $obj );
        $original = clone $obj;
        $is_changed = false;
        $changed_cols = [];
        $not_for_revision = ['created_on', 'modified_on', 'status', 'user_id',
                             'created_by', 'modified_by', 'rev_type', 'rev_object_id',
                             'rev_changed', 'rev_diff', 'rev_note'];
        $db->schema[ $model ] = $scheme;
        $properties = $scheme['edit_properties'];
        $autoset = isset( $scheme['autoset'] ) ? $scheme['autoset'] : [];
        $columns = $scheme['column_defs'];
        $labels = $scheme['labels'];
        $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];;
        $unchangeable = isset( $scheme['unchangeable'] ) ? $scheme['unchangeable'] : [];
        $unique = isset( $scheme['unique'] ) ? $scheme['unique'] : [];
        $errors = [];
        $placements = [];
        $add_tags = [];
        $text = '';
        $as_revision = false;
        $text_diff = LIB_DIR . 'Text' . DS . 'Diff';
        require_once ( $text_diff . '.php' );
        require_once ( $text_diff . DS . 'Renderer.php' );
        require_once ( $text_diff . DS . 'Renderer' . DS . 'unified.php' );
        $renderer = new Text_Diff_Renderer_unified();
        if ( $app->param( '_save_as_revision' ) ) {
            $as_revision = true;
        }
        foreach( $columns as $col => $props ) {
            if ( $col === $primary ) continue;
            if ( $obj->id && in_array( $col, $autoset ) ) continue;
            $value = $app->param( $col );
            $type = $props['type'];
            if ( isset( $properties[ $col ] ) ) {
                if ( $type === 'text' || $type === 'string' ) {
                    $text .= ' ' . $value;
                }
                if ( $col === 'order' && $table->sortable && ! $value ) {
                    $value = $app->get_order( $obj );
                }
                if ( isset( $relations[ $col ] ) ) {
                    if (! $value ) $value = [];
                    $placements[ $col ] = [ $relations[ $col ] => $value ];
                    if ( $col === 'tags' ) {
                        $add_tags = $app->param( 'additional_tags' );
                        if ( $add_tags ) {
                            $add_tags = preg_split( '/\s*,\s*/', $add_tags );
                        }
                    }
                }
                list( $prop, $opt ) = [ $properties[ $col ], ''];
                if ( strpos( $prop, '(' ) ) {
                    list( $prop, $opt ) = explode( '(', $prop );
                    $opt = rtrim( $opt, ')' );
                }
                if ( $value === null && $prop !== 'datetime' ) continue;
                if ( $prop === 'hidden' ) {
                    continue;
                } else if ( $prop === 'datetime' ) {
                    $date = $app->param( $col . '_date' );
                    $time = $app->param( $col . '_time' );
                    $ts = $obj->db2ts( $date . $time );
                    $value = $obj->ts2db( $ts );
                } else if ( $prop === 'number' ) {
                    $value += 0;
                } else if ( $prop === 'password' ) {
                    $pass = $app->param( $col );
                    $verify = $app->param( $col . '-verify' );
                    if ( $pass || $verify ) {
                        if ( $pass !== $verify ) {
                            $errors[] = $app->translate( 'Both passwords must match.' );
                            continue;
                            if ( $model === 'user' ) {
                                if (! $app->is_valid_password( $pass, $verify, $msg ) ) {
                                    $errors[] = $msg;
                                    continue;
                                }
                            }
                        }
                        $changed_cols[ $col ] = true;
                    }
                    if ( strpos( $opt, 'hash' ) !== false ) {
                        $value = password_hash( $value, PASSWORD_BCRYPT );
                    }
                }
                if ( in_array( $col, $unchangeable ) ) { 
                    if ( $obj->id && $obj->$col != $value ) {
                        $errors[] = $app->translate( 'You can not change the %s.', $col );
                        continue;
                    }
                }
                if ( in_array( $col, $unique ) ) {
                    $terms = [ $col => $value ];
                    if ( $obj->id ) {
                        $terms[ $primary ] = ['!=' => $obj->id ];
                    }
                    if ( $table->revisable ) {
                        $terms['rev_type'] = 0;
                    }
                    $compare = $db->model( $model )->load( $terms );
                    if ( is_array( $compare ) && !empty( $compare ) ) {
                        $errors[] = $app->translate(
                            'A %1$s with the same %2$s already exists.',
                                [ $value, $labels[ $col ] ] );
                    }
                }
            }
            if ( is_array( $value ) ) {
                $value = join( ',', $value );
            }
            if ( $col !== 'id' ) {
                $not_null = isset( $props['not_null'] ) ? $props['not_null'] : false;
                if ( $not_null && $col === 'basename' ) {
                    if ( $value ) {
                        $value = $app->make_basename( $obj, $value );
                    } else {
                        $text = strip_tags( $text );
                        $value = $app->make_basename( $obj, $text, true );
                    }
                }
                if ( $not_null && $type === 'datetime' ) {
                    $value = $obj->db2ts( $value );
                    if (! $value ) {
                        if ( $db->default_ts && $db->default_ts === 'CURRENT_TIMESTAMP') {
                            $value = date( 'YmdHis' );
                        }
                    }
                }
                if ( $not_null && ! $value ) {
                    $default = isset( $props['default'] ) ? $props['default'] : null;
                    if ( $default ) {
                        $value = $default;
                    } else {
                        if ( strpos( $type, 'int' ) !== false ) {
                            $value += 0;
                        } else {
                            $value = '';
                            $errors[] = $app->translate(
                                '%s is required.', $labels[ $col ] );
                        }
                    }
                }
            }
            if (! isset( $relations[ $col ] ) ) {
                if ( $col === 'model' || $col === 'count' ) {
                    // Collision $obj->model( $model )->...
                    $obj->$col = $value;
                } else {
                    $obj->$col( $value );
                }
                if ( $type != 'blob' && $obj->id ) {
                    if (! in_array( $col, $not_for_revision ) ) {
                        $comp_old = $original->$col;
                        $comp_new = $obj->$col;
                        if ( $type === 'datetime' ) {
                            $comp_old = preg_replace( '/[^0-9]/', '', $comp_old );
                            $comp_old = (int) $comp_old;
                            $comp_new = preg_replace( '/[^0-9]/', '', $comp_new );
                            $comp_new = (int) $comp_new;
                        } else if ( strpos( $type, 'int' ) !== false ) {
                            $comp_new = (int) $comp_new;
                            $comp_old = (int) $comp_old;
                        }
                        if ( $prop && $prop != 'password' && $comp_old != $comp_new ) {
                            $is_changed = true;
                            $changed_cols[ $col ] =
                                $app->diff( $original->$col, $obj->$col, $renderer );
                        }
                    }
                }
            }
        }
        if (! $app->can_do( $model, $action, $obj ) ) {
            $app->error( 'Permission denied.' );
        }
        $callback = ['name' => 'save_filter', 'error' => '',
                     'changed_cols' => $changed_cols ];
        $save_filter = $app->run_callbacks( $callback, $model, $obj );
        if ( $msg = $callback['error'] ) {
            $errors[] = $msg;
        }
        $is_new = $obj->id ? false : true;
        $callback = ['name' => 'pre_save', 'error' => '', 'is_new' => $is_new,
                     'original' => $original, 'changed_cols' => $changed_cols ];
        $pre_save = $app->run_callbacks( $callback, $model, $obj );
        if ( $msg = $callback['error'] ) {
            $errors[] = $msg;
        }
        if (!empty( $errors ) || !$save_filter || !$pre_save ) {
            $error = join( "\n", $errors );
            if ( $app->param( '_preview' ) ) return $app->error( $error );
            return $app->forward( $model, $error );
        }
        if ( $model === 'workspace' ) {
            $obj->last_update( time() );
        }
        $app->set_default( $obj, $placements );
        if ( $app->param( '_preview' ) ) {
            return $app->preview( $obj, $properties );
        }
        if (! $as_revision ) $obj->save();
        // for Revision
        if (! empty( $add_tags ) ) {
            $workspace_id = (int) $app->param( 'workspace_id' );
            $props = $placements['tags']['tag'];
            foreach ( $add_tags as $tag ) {
                $normalize = preg_replace( '/\s+/', '', trim( strtolower( $tag ) ) );
                if (! $tag ) continue;
                $terms = ['normalize' => $normalize ];
                if ( $workspace_id )
                    $terms['workspace_id'] = $workspace_id;
                $tag_obj = $db->model( 'tag' )->get_by_key( $terms );
                if (! $tag_obj->id ) {
                    $tag_obj->name( $tag );
                    $app->set_default( $tag_obj );
                    $order = $app->get_order( $tag_obj );
                    $tag_obj->order( $order );
                    $tag_obj->save();
                }
                if (! in_array( $tag_obj->id, $props ) ) {
                    $props[] = $tag_obj->id;
                }
            }
            $placements['tags']['tag'] = $props;
        }
        if (! empty( $placements ) ) {
            foreach ( $placements as $name => $props ) {
                $to_obj = key( $props );
                $to_ids = $props[ $to_obj ];
                if ( $to_obj === '__any__' ) {
                    $to_obj = $app->param( "_{$name}_model" );
                }
                $args = ['from_id' => $obj->id, 
                         'name' => $name,
                         'from_obj' => $model,
                         'to_obj' => $to_obj ];
                if ( $res = $app->set_relations( $args, $to_ids ) ) {
                    $changed_cols[ $name ] = $res;
                    if ( $obj->id  && ! $is_changed ) {
                        $is_changed = $res;
                    }
                }
            }
        }
        $has_file = false;
        foreach ( $properties as $key => $val ) {
            if ( $val === 'file' ) {
                $metadata = $db->model( 'meta' )->get_by_key(
                         ['model' => $model, 'object_id' => $obj->id,
                          'key' => 'metadata', 'kind' => $key ] );
                $magic = $app->param( "{$key}-magic" );
                if ( $magic ) {
                    $sess = $db->model( 'session' )
                        ->get_by_key( ['name' => $magic,
                                       'user_id' => $app->user()->id, 'kind' => 'UP'] );
                    if ( $sess->id ) {
                        $obj->$key( $sess->data );
                        $has_file = true;
                        $metadata->data( $sess->metadata );
                        $metadata->type( $sess->key );
                        $metadata->text( $sess->text );
                        $metadata->value( $sess->value );
                        $metadata->metadata( $sess->extradata );
                        $metadata->save();
                        if ( $obj->id ) $is_changed = true;
                        $sess->remove();
                        $changed_cols[ $key ] = true;
                    }
                }
                if ( $model === 'asset' && $key === 'file' ) continue;
                if (! $metadata->id ) continue;
                $file_meta = json_decode( $metadata->text, true );
                $mime_type = $file_meta['mime_type'];
                $file_ext = $file_meta['extension'];
                $file = "{$model}/{$model}-{$key}-" . $obj->id;
                if ( $file_ext ) $file .= '.' . $file_ext;
                $base_url = $app->site_url;
                $base_path = $app->site_path;
                $extra_path = $app->extra_path;
                if ( $workspace = $app->workspace() ) {
                    $base_url = $workspace->site_url;
                    $base_path = $workspace->site_path;
                    $extra_path = $workspace->extra_path;
                }
                $file_path = $base_path . '/'. $extra_path . $file;
                $file_path = str_replace( '/', DS, $file_path );
                $url = $base_url . '/'. $extra_path . $file;
                if (! $table->revisable || ! $obj->rev_type ) {
                    $app->publish( $file_path, $obj, $key, $mime_type );
                }
                if ( isset( $changed_cols[ $key ] ) ) {
                    $changed_cols[ $key ] = $url;
                }
            }
        }
        if ( $has_file && ! $as_revision ) $obj->save();
        $id = $obj->id;
        $terms = ['model' => $model, 'container' => $model ];
        $extra = '';
        if ( $obj->has_column( 'workspace_id' ) ) {
            $map = $db->model( 'urlmapping' )->new();
            $extra = ' AND ' . $map->_colprefix . 'workspace_id';
            $ws_id = $obj->workspace_id;
            if ( $ws_id ) {
                $extra .= " IN (0,{$ws_id})";
            } else {
                $extra .= '=0';
            }
        }
        if ( $obj->_model === 'template' ) {
            unset( $terms['container'] );
        }
        $mappings = $db->model( 'urlmapping' )->load(
            $terms, ['and_or' => 'OR'], '*', $extra );
        if (! $table->revisable || (! $obj->rev_type && ! $as_revision ) ) {
            foreach ( $mappings as $mapping ) {
                if ( $obj->_model === 'template' ) {
                    if ( $obj->id != $mapping->template_id ) {
                        continue;
                    }
                }
                if ( $mapping->link_status && $obj->has_column( 'status' ) ) {
                    $status_published = $app->status_published( $obj->_model );
                    if ( $original->status != $status_published &&
                        $obj->status != $status_published ) {
                        continue;
                    }
                }
                if (! $mapping->date_based && $mapping->model == $model ) {
                    $file_path = $app->build_path_with_map( $obj, $mapping, $table );
                    $app->publish( $file_path, $obj, $mapping );
                } else if ( $mapping->model != $model && $mapping->container == $model ) {
                    $app->publish_dependencies(
                        $obj, $original, $mapping, $orig_relations );
                }
            }
        }
        $callback = ['name' => 'post_save', 'is_new' => $is_new,
                     'original' => $original, 'changed_cols' => $changed_cols ];
        $app->run_callbacks( $callback, $model, $obj, true );
        if ( $as_revision ) {
            $is_changed = true;
            $original = $obj;
            $original->rev_object_id( null );
        }
        if (! $is_new && $is_changed && $table->revisable && !$obj->rev_object_id ) {
            $changed = array_keys( $changed_cols );
            $original->rev_changed( join( ', ', $changed ) );
            $original->rev_diff( json_encode( $changed_cols,
                                 JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT ) );
            $original->rev_object_id( $obj->id );
            if ( $as_revision ) {
                $original->rev_type( 2 );
            } else {
                $original->rev_type( 1 );
            }
            $original->id( null );
            $original->save();
            foreach ( $orig_relations as $relation ) {
                $rel_rev = clone $relation;
                $rel_rev->id( null );
                $rel_rev->from_id = $original->id;
                $rel_rev->save();
            }
            foreach ( $orig_metadata as $meta ) {
                $meta_rev = clone $meta;
                $meta_rev->id( null );
                $meta_rev->object_id = $original->id;
                $meta_rev->save();
            }
            if ( $as_revision ) $id = $original->id;
            // TODO Clone Children
        }
        // TODO Duplicate ( Check Unique Cols)
        $add_return_args = '';
        if ( $app->param( '_apply_to_master' ) ) {
            $add_return_args = '&apply_to_master=1';
        } else if ( $app->param( '_edit_revision' ) || $as_revision ) {
            $add_return_args = '&edit_revision=1';
            if ( $as_revision && ! $is_changed ) {
                $add_return_args .= '&not_changed=1';
            }
        }
        if ( $is_changed ) {
            // TODO touch
        }
        $nickname = $app->user()->nickname;
        $label = $app->translate( $table->label );
        $primary = $table->primary;
        $name = $primary ? $obj->$primary : '';
        $params = [ $label, $name, $obj->id, $nickname ];
        $message = $is_new
                 ? $app->translate( "%1\$s '%2\$s(ID:%3\$s)' created by %4\$s.", $params )
                 : $app->translate( "%1\$s '%2\$s(ID:%3\$s)' edited by %4\$s.", $params );
        $metadata = (! empty( $changed_cols ) && ! $is_new )
                  ? json_encode( $changed_cols,
                                 JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT )
                  : '';
        $app->log( ['message'   => $message,
                    'category'  => 'save',
                    'model'     => $table->name,
                    'object_id' => $obj->id,
                    'metadata'  => $metadata,
                    'level'     => 'info'] );
        $app->redirect( $app->admin_url . '?__mode=view&_type=edit&_model=' . $model .
            '&id=' . $id . '&saved=1' . $app->workspace_param . $add_return_args );
    }

    function diff ( $source, $change, &$renderer = null ) {
        $source = str_replace( ['\r\n', '\r', '\n'], '\n', $source );
        $source = explode( "\n", $source );
        $change = str_replace( ['\r\n', '\r', '\n'], '\n', $change );
        $change = explode( "\n", $change );
        $diff = new Text_Diff( 'auto', [ $source, $change ] );
        if (! $renderer && !class_exists( 'Text_Diff_Renderer_unified' ) ) {
            require_once ( LIB_DIR . 'Text' . DS . 'Diff' . DS
                            . 'Renderer' . DS . 'unified.php' );
        }
        if (! $renderer ) {
            $renderer = new Text_Diff_Renderer_unified();
        }
        return $renderer->render( $diff );
    }

    function get_order ( $obj ) {
        $app = Prototype::get_instance();
        $last = $app->db->model( $obj->_model )->load( null, [
                'sort' => 'order', 'limit' => 1, 'direction' => 'descend'] );
        if ( is_array( $last ) && count( $last ) ) {
            $last = $last[0];
            $value = $last->order + 1;
        } else {
            $value = 1;
        }
        return $value;
    }

    function preview ( $obj, $properties ) {
        $app = Prototype::get_instance();
        $map = $app->get_permalink( $obj, true );
        if (! $map || ! $map->template || ! $map->model ) {
            return $app->error( 'View or Model not specified.' );
        }
        $app->init_tags();
        $ctx = $app->ctx;
        $db = $app->db;
        $template = $map->template;
        $table = $app->get_table( $map->model );
        $archive_type = '';
        if ( $obj->_model === 'template' ) {
            $tmpl = $obj->text;
            $terms = [];
            if ( $map->workspace_id ) {
                $terms['workspace_id'] = $map->workspace_id;
            }
            if ( $table->revisable ) {
                $terms['rev_type'] = 0;
            }
            $preview_obj = $db->model( $table->name )->load( $terms,
                ['limit' => 1, 'sort' => 'id', 'direction' => 'descend'] );
            if (! empty( $preview_obj ) ) {
                $obj = $preview_obj[0];
                $ctx->stash( 'preview_template', $template );
            }
            $ctx->stash( 'current_archive_title', $template->name );
            $ctx->stash( 'current_archive_type', 'index' );
        } else {
            $archive_type = $map->model;
            $ctx->stash( 'current_archive_type', $archive_type );
            $tmpl = $template->text;
            $primary = $table->primary;
            if ( $map->model == $obj->_model ) {
                $ctx->stash( 'current_archive_title', $obj->$primary );
            }
        }
        if ( $map->container ) {
            $container = $app->get_table( $map->container );
            if ( is_object( $container ) ) {
                $ctx->stash( 'current_container', $container->name );
                if ( $at = $map->date_based ) {
                    $archive_type .= $archive_type ? '-' . strtolower( $at )
                                   : strtolower( $at );
                    $ctx->stash( 'current_archive_type', $archive_type );
                    $container_obj = $app->db->model( $map->container )->new();
                    $date_col = $app->get_date_col( $container_obj );
                    $terms = [];
                    if ( $container_obj->has_column( 'workspace_id' )
                        && $app->workspace() ) {
                        $terms['workspace_id'] = $app->workspace()->id;
                    }
                    $last = $container_obj->load( $terms,
                        ['limit' => 1, 'sort' => $date_col, 'direction' => 'descend'] );
                    if ( is_array( $last ) && !empty( $last ) ) {
                        $last = $last[0];
                        $ts = $container_obj->db2ts( $last->$date_col );
                        list( $title, $start, $end ) =
                            $app->title_start_end( $at, $ts, $map );
                        $ctx->stash( 'archive_date_based', $container_obj->_model );
                        $ctx->stash( 'archive_date_based_col', $date_col );
                        $ctx->stash( 'current_timestamp', $start );
                        $ctx->stash( 'current_timestamp_end', $end );
                        $ctx->stash( 'current_archive_title', $title );
                    }
                }
            }
        }
        $ctx->stash( 'current_template', $template );
        $ctx->stash( 'current_context', $obj->_model );
        $ctx->stash( $obj->_model, $obj );
        foreach ( $properties as $key => $val ) {
            if ( $val === 'file' ) {
                $magic = $app->param( "{$key}-magic" );
                if ( $magic ) {
                    $sess = $db->model( 'session' )
                        ->get_by_key( ['name' => $magic,
                                       'user_id' => $app->user()->id, 'kind' => 'UP'] );
                    if ( $sess->id ) {
                        $obj->$key( $sess->data );
                        $ctx->stash( 'current_session_' . $key, $sess );
                    }
                }
            }
        }
        echo $ctx->build( $tmpl );
        exit();
    }

    function init_tags () {
        $app = Prototype::get_instance();
        if ( $app->init_tags ) return;
        $ctx = $app->ctx;
        $tables = $app->db->model( 'table' )->load( ['template_tags' => 1] );
        $block_relations = [];
        $function_relations = [];
        $function_date = [];
        $workspace_tags = [];
        $alias = [];
        $tags = $this->core_tags;
        foreach ( $tables as $table ) {
            $plural = strtolower( $table->plural );
            $plural = preg_replace( '/[^a-z]/', '' , $plural );
            $ctx->register_tag( $plural, 'block', 'hdlr_objectloop', $tags );
            $ctx->stash( 'blockmodel_' . $plural, $table->name );
            $scheme = $app->get_scheme_from_db( $table->name );
            $columns = $scheme['column_defs'];
            $locale = $scheme['locale']['default'];
            $edit_properties = $scheme['edit_properties'];
            $relations = $scheme['relations'];
            $obj = $app->db->model( $table->name )->new();
            foreach ( $columns as $key => $props ) {
                if (! isset( $relations[ $key ] ) ) {
                    $label = strtolower( $locale[ $key ] );
                    $label = preg_replace( '/[^a-z]/', '' , $label );
                    $tag_name = str_replace( '_', '', $key );
                    if ( $props['type'] === 'datetime' ) {
                        $function_date[] = $table->name . $key;
                    }
                    if ( $key !== $tag_name ) {
                        $alias[ $table->name . $tag_name ] = $table->name . $key;
                    }
                    $ctx->register_tag(
                        $table->name . $tag_name, 'function', 'hdlr_get_objectcol', $tags );
                    if ( $table->name === 'workspace' ) {
                        $workspace_tags[] = $table->name . $tag_name;
                    }
                    if ( $label && $label != $tag_name ) {
                        if (! $obj->has_column( $label ) ) {
                            $ctx->register_tag(
                                $table->name . $label,
                                    'function', 'hdlr_get_objectcol', $tags );
                            $alias[ $table->name . $label ] 
                                = $table->name . $key;
                        }
                    }
                    if ( $key === 'published_on' ) {
                        $ctx->register_tag(
                            $table->name . 'date', 'function',
                                                'hdlr_get_objectcol', $tags );
                            $alias[ $table->name . 'date'] 
                                = $table->name . $key;
                    }
                }
                if ( preg_match( '/(^.*)_id$/', $key, $mts ) ) {
                    if ( isset( $edit_properties[ $key ] ) ) {
                        $prop = $edit_properties[ $key ];
                        if ( strpos( $prop, ':' ) !== false ) {
                            $edit = explode( ':', $prop );
                            if ( $edit[0] === 'relation' || $edit[0] === 'reference' ) {
                                $ctx->register_tag( $table->name . $mts[1],
                                    'function', 'hdlr_get_objectcol', $tags );
                                $function_relations[ $table->name . $mts[1] ]
                                    = [ $key, $table->name, $mts[1], $edit[2] ];
                                $alias[ $table->name . $label ] = $table->name . $mts[1];
                                if ( $key === 'user_id' ) {
                                        $ctx->register_tag( $table->name . 'author',
                                            'function', 'hdlr_get_objectcol', $tags );
                                    $alias[ $table->name . 'author']
                                        = $table->name . $mts[1];
                                }
                            }
                        }
                    }
                }
            }
            foreach ( $relations as $key => $model ) {
                $ctx->register_tag( $table->name . $key, 'block',
                    'hdlr_get_relatedobjs', $tags );
                $block_relations[ $table->name . $key ] = [ $key, $table->name, $model ];
            }
            if ( $table->taggable ) {
                // TODO $table->name . 'iftagged'
            }
        }
        $ctx->stash( 'workspace', $app->workspace() );
        $ctx->stash( 'function_relations', $function_relations );
        $ctx->stash( 'block_relations', $block_relations );
        $ctx->stash( 'function_date', $function_date );
        $ctx->stash( 'workspace_tags', $workspace_tags );
        $ctx->stash( 'alias_functions', $alias );
        $app->init_tags = true;
    }

    function build_path_with_map ( $obj, $mapping, $table, $ts = null, $url = false ) {
        $app = Prototype::get_instance();
        $ctx = $app->ctx;
        $db = $app->db;
        $ctx->prefix = 'mt';
        $table_vars = $table->get_values();
        $colprefix = $table->_colprefix;
        foreach ( $table_vars as $key => $value ) {
            $ctx->local_vars[ $key ] = $value;
            $key = preg_replace( "/^$colprefix/", '', $key );
            $ctx->local_vars[ $key ] = $value;
        }
        $model_vars = $obj->get_values();
        $colprefix = $obj->_colprefix;
        foreach ( $model_vars as $key => $value ) {
            $key = preg_replace( "/^$colprefix/", '', $key );
            $ctx->local_vars[ $key ] = $value;
        }
        $app->init_tags();
        $ctx->stash( 'current_context', $obj->_model );
        $ctx->stash( $obj->_model, $obj );
        $map_path = $mapping->mapping;
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
        $path = trim( $ctx->build( $map_path ) ); // TODO PAML
        $base_url = $app->site_url;
        $base_path = $app->site_path;
        if ( $workspace = $mapping->workspace ) {
            $base_url = $workspace->site_url;
            $base_path = $workspace->site_path;
        }
        if (! preg_match( '!\/$!', $base_url ) ) $base_url .= '/';
        $ds = preg_quote( DS, '/' );
        if (! preg_match( "/{$ds}$/", $base_path ) ) $base_path .= DS;
        $_path = $url ? $base_url . $path : $base_path . $path;
        if (! $url ) {
            $_path = str_replace( '/', DS, $_path );
        } else {
            $_path = str_replace( DS, '/', $_path );
        }
        return $_path;
    }

    function title_start_end ( $archive_type, $ts, $mapping ) {
        $app = Prototype::get_instance();
        list( $title, $start, $end ) = ['', '', ''];
        if ( $archive_type == 'Yearly' ) {
            $y = substr( $ts, 0, 4 );
            $title = $y;
            $start = "{$y}0101000000";
            $end   = "{$y}1231235959";
        } elseif ( $archive_type == 'Fiscal-Yearly' ) {
            $y = substr( $ts, 0, 4 );
            $m = substr( $ts, 4, 2 );
            $fy_start = $mapping->fy_start;
            $fy_end = $fy_start == 1 ? 12 : $fy_start - 1;
            $start_y = $y;
            $end_y = $fy_start == 1 ? $y : $y + 1;
            $fy_start = sprintf( '%02d', $fy_start );
            $fy_end = sprintf( '%02d', $fy_end );
            $start_ym = $start_y . $fy_start;
            $end_ym = $end_y . $fy_end;
            $ym = $y . $m;
            if ( $ym >= $start_ym && $ym <= $end_ym ) {
                $year = $y;
            } else if ( $end_ym < $ym ) {
                $year = $y + 1;
            }
            list( $start, $end ) = $app->start_end_month( "{$end_ym}01000000" );
            $start = "{$start_ym}01000000";
            $title = $year;
        } elseif ( $archive_type == 'Monthly' ) {
            $y = substr( $ts, 0, 4 );
            $m = substr( $ts, 4, 2 );
            list( $start, $end ) = $app->start_end_month( "{$y}{$m}01000000" );
            $title = "{$y}{$m}";
        }
        return [ $title, $start, $end ];
    }

    function start_end_month ( $ts ) {
        $y = substr( $ts, 0, 4 );
        $m = substr( $ts, 4, 2 );
        $start = sprintf( "%04d%02d01000000", $y, $m );
        $end   = sprintf( "%04d%02d%02d235959", $y, $m,
                 date( 't', mktime( 0, 0, 0, $m, 1, $y ) ) );
        return [ $start, $end ];
    }

    function make_basename ( $obj, $basename, $unique = false ) {
        $app = Prototype::get_instance();
        if (! $basename ) $basename = $obj->_model;
        $basename = strtolower( $basename );
        $basename = preg_replace( "/[^a-z0-9]/", ' ', $basename );
        $basename = preg_replace( "/\s{1,}/", ' ', $basename );
        $basename = str_replace( ' ', '_', $basename );
        $basename = trim( $basename, '_' );
        $basename = mb_substr( $basename, 0, 30, $app->db->charset );
        if ( $basename && strpos( $basename, '_' ) !== false ) {
            $basename = preg_replace( '/_[^_]*$/', '', $basename );
        }
        if (! $basename ) $basename = $obj->_model;
        if ( $unique ) {
            $terms = [];
            if ( $obj->id ) {
                $terms['id'] = ['not' => (int)$obj->id ];
            }
            if ( $obj->has_column( 'workspace_id' ) ) {
                $workspace_id = $obj->workspace_id ? $obj->workspace_id : 0;
                if (! $workspace_id && $app->workspace() ) {
                    $workspace_id = $app->workspace()->id;
                }
                $terms['workspace_id'] = $workspace_id;
            }
            if ( $obj->has_column( 'rev_type' ) ) {
                $terms['rev_type'] = 0;
            }
            $terms['basename'] = $basename;
            $i = 1;
            $is_unique = false;
            $new_basename = $basename;
            while ( $is_unique === false ) {
                $exists = $app->db->model( $obj->_model )->load( $terms );
                if (! $exists ) {
                    $is_unique = true;
                    $basename = $new_basename;
                    break;
                } else {
                    $len = mb_strlen( $basename . '_' . $i );
                    if ( $len > 255 ) {
                        $diff = $len - 255;
                        $basename = mb_substr(
                            $basename, 0, 255 - $diff, $app->db->charset );
                    }
                    $new_basename = $basename . '_' . $i;
                    $terms['basename'] = $new_basename;
                }
                $i++;
            }
        }
        return $basename;
    }

    function set_relations ( $args, $ids ) {
        $app = Prototype::get_instance();
        $is_changed = false;
        $relations = $app->db->model( 'relation' )->load( $args );
        foreach ( $relations as $rel ) {
            if (! in_array( $rel->to_id, $ids ) ) {
                $rel->remove();
                $is_changed = true;
            }
        }
        $i = 0;
        foreach ( $ids as $id ) {
            $id = (int) $id;
            if (! $id ) continue;
            $i++;
            $terms = $args;
            $terms['to_id'] = $id;
            $rel = $app->db->model( 'relation' )->get_by_key( $terms );
            if (! $rel->id || $rel->order != $i ) {
                $rel->order( $i );
                $rel->save();
                $is_changed = true;
            }
        }
        return $is_changed;
    }

    function run_callbacks ( &$cb, $model, &$obj = null, $needle = true ) {
        $app = Prototype::get_instance();
        $cb_name = $cb['name'];
        if ( isset( $app->callbacks[ $cb_name ][ $model ] ) ) {
            $all_callbacks = $app->callbacks[ $cb_name ][ $model ];
            ksort( $all_callbacks );
            foreach ( $all_callbacks as $callbacks ) {
                foreach ( $callbacks as $callback ) {
                    list( $meth, $class ) = $callback;
                    $res = true;
                    if ( function_exists( $meth ) ) {
                        $res = $meth( $cb, $app, $obj );
                    } else if ( $class && method_exists( $class, $meth ) ) {
                        $res = $class->$meth( $cb, $app, $obj );
                    }
                    if ( $needle && !$res ) return false;
                }
            }
        }
        return true;
    }

    function delete ( $app ) {
        $model = $app->param( '_model' );
        $app->validate_magic();
        $db = $app->db;
        $objects = $app->get_object( $model );
        if (! is_array( $objects ) && is_object( $objects ) ) {
            $objects = [ $objects ];
        }
        $table = $app->get_table( $model );
        $errors = [];
        $callback = ['name' => 'delete_filter', 'error' => ''];
        $delete_filter = $app->run_callbacks( $callback, $model );
        if ( $msg = $callback['error'] ) {
            $errors[] = $msg;
        }
        if ( !empty( $errors ) || !$delete_filter ) {
            return $app->forward( $model, join( "\n", $errors ) );
        }
        $children = $table->child_tables ? explode( ',', $table->child_tables ) : [];
        $i = 0;
        foreach( $objects as $obj ) {
            $original = clone $obj;
            $callback = ['name' => 'pre_delete', 'error' => '', 'original' => $original ];
            $pre_delete = $app->run_callbacks( $callback, $model );
            if ( $msg = $callback['error'] ) {
                $errors[] = $msg;
            }
            if ( !empty( $errors ) || !$pre_delete ) {
                return $app->forward( $model, join( "\n", $errors ) );
            }
            if (! $app->can_do( $model, 'delete', $obj ) ) {
                $app->error( 'Permission denied.' );
            }
            // todo begin and finish work for remove child_classes
            /*
            foreach ( $children as $child ) {
                $child_objs = $db->model( $child )->load(
                    [ $model . '_id' => $obj->id ] );
                foreach ( $child_objs as $child_obj ) {
                    $child_obj->remove();
                }
            }
            */
            $error = false;
            $app->remove_object( $obj, $table, $error );
            // TODO error
            $i++;
            if ( $model !== 'log' ) {
                $nickname = $app->user()->nickname;
                $label = $app->translate( $table->label );
                $primary = $table->primary;
                $name = $primary ? $obj->$primary : '';
                $params = [ $label, $name, $obj->id, $nickname ];
                $message = $app->translate(
                    "%1\$s '%2\$s(ID:%3\$s)' deleted by %4\$s.", $params );
                $app->log( ['message'   => $message,
                            'category'  => 'delete',
                            'model'     => $table->name,
                            'object_id' => $obj->id,
                            'level'     => 'info'] );
            }
            // $obj->remove();
            // todo touch 
            $callback = ['name' => 'post_delete', 'original' => $original ];
            $app->run_callbacks( $callback, $model, $obj, true );
        }
        $app->redirect( $app->admin_url .
            "?__mode=view&_type=list&_model={$model}&deleted=1" . $app->workspace_param );
    }

    function remove_object ( $obj, $table, &$error = false ) {
        // todo begin work
        $app = Prototype::get_instance();
        $db = $app->db;
        $model = $obj->_model;
        $meta_objs = $db->model( 'meta' )->load(
            ['model' => $model, 'object_id' => $obj->id ] );
        foreach ( $meta_objs as $meta ) {
            if (! $meta->remove() ) $error = true;
        }
        if ( $model === 'fileinfo' ) {
            if ( $obj->is_published && $obj->file_path ) {
                $file_path = $obj->file_path;
                $file_path = str_replace( '/', DS, $file_path );
                if ( file_exists( $file_path ) ) {
                    unlink( $file_path );
                }
            }
        } else {
            $fi_objs = $db->model( 'fileinfo' )->load(
                ['model' => $model, 'object_id' => $obj->id ] );
            if ( $model === 'urlmapping' || $model === 'template' ) {
                $map_ids = [];
                if ( $model === 'urlmapping' ) {
                    $map_ids[] = $obj->id;
                } else {
                    $maps = $db->model( 'urlmapping' )->load(
                        ['template_id' => $obj->id ] );
                    foreach ( $maps as $map ) {
                        $map_ids[] = $map->id;
                    }
                }
                if (! empty( $map_ids ) ) {
                    $_fi_objs = $db->model( 'fileinfo' )->load(
                        ['urlmapping_id' => ['IN' => $map_ids ] ] );
                    $fi_objs = array_merge( $fi_objs, $_fi_objs );
                }
            }
            foreach ( $fi_objs as $fi ) {
                if ( $fi->is_published && $fi->file_path ) {
                    $file_path = $fi->file_path;
                    $file_path = str_replace( '/', DS, $file_path );
                    if ( file_exists( $file_path ) ) {
                        unlink( $file_path );
                    }
                }
                if (! $fi->remove() ) $error = true;
            }
        }
        $children = $table->child_tables ? explode( ',', $table->child_tables ) : [];
        foreach ( $children as $child ) {
            $child_objs = $db->model( $child )->load(
                [ $model . '_id' => $obj->id ] );
            $child_table = $app->get_table( $child );
            foreach ( $child_objs as $child_obj ) {
                if ( $child_table ) {
                    $app->remove_object( $child_obj, $child_table, $error );
                } else {
                    if (! $child_obj->remove() ) $error = true;
                }
            }
        }
        $relations = $db->model( 'relation' )->load(
            ['from_obj' => $obj->_model, 'from_id' => $obj->id ] );
        foreach ( $relations as $rel ) {
            $rel->remove();
        }
        $relations = $db->model( 'relation' )->load(
            ['to_obj' => $obj->_model, 'to_id' => $obj->id ] );
        foreach ( $relations as $rel ) {
            $rel->remove();
        }
        if ( $obj->has_column( 'rev_type' ) && ! $obj->rev_type ) {
            $revisions = $db->model( $obj->_model )->load(
                ['rev_object_id' => $obj->id ] );
            foreach ( $revisions as $rev ) {
                $rev->remove();
            }
        }
        $res = $obj->remove();
        if (! $res ) $error = true;
        return $res;
    }

    function return_to_dashboard ( $options = [] ) {
        $app = Prototype::get_instance();
        $workspace = $app->workspace();
        $url = $app->admin_url . "?__mode=dashboard";
        if ( $workspace ) {
            $app->redirect( $url . '&workspace_id=' . $workspace->id );
        }
        if (! empty( $options ) ) $url .= '&' . http_build_query( $options );
        $app->redirect( $url );
    }

    function workspace () {
        $app = Prototype::get_instance();
        if ( $app->stash( 'workspace' ) ) return $app->stash( 'workspace' );
        if ( $id = $app->param( 'workspace_id' ) ) {
            $id = (int) $id;
            if (! $id ) return null;
            $workspace = $app->db->model( 'workspace' )->load( $id );
            $app->stash( 'workspace', $workspace );
            return $workspace ? $workspace : null;
        }
        return null;
    }

    function get_table ( $model ) {
        $app = Prototype::get_instance();
        if ( $app->stash( 'table_' . $model ) )
                    return $app->stash( 'table:' . $model );
        $table = $app->db->model( 'table' )->load( ['name' => $model ], ['limit' => 1] );
        if ( is_array( $table ) && !empty( $table ) ) {
            $table = $table[0];
            $app->stash( 'table:' . $model, $table );
            return $table;
        }
        return null;
    }

    function get_object ( $model ) {
        $app = Prototype::get_instance();
        if (! $model ) $model = $app->param( '_model' );
        $obj = $app->db->model( $model )->new();
        $scheme = $app->get_scheme_from_db( $model );
        if (! $scheme ) return null;
        $primary = $scheme['indexes']['PRIMARY'];
        $id = $app->param( $primary );
        if ( is_array( $id ) ) {
            array_walk( $id, function( &$id ) {
                $id = (int) $id;
            });
            $objects = $obj->load( ['id' => ['IN' => $id ] ] );
            return $objects;
        } else if ( $id ) {
            if ( $app->stash( $model . ':' . $id ) ) 
                return $app->stash( $model . ':' . $id );
            $id = (int) $id;
            $obj = $obj->load( $id );
            if ( is_object( $obj ) )
                $app->stash( $model . ':' . $id, $obj );
        }
        return isset( $obj ) ? $obj : null;
    }

    function forward ( $model, $error = '' ) {
        $app = Prototype::get_instance();
        $ctx = $app->ctx;
        $ctx->vars['error'] = $error;
        if ( $app->param( '_type' ) === 'edit' || $app->mode === 'save' ) {
            $app->assign_params( $app, $ctx );
            $ctx->vars['forward_params'] = 1;
            return $app->view( $app, $model, 'edit' );
        }
        return $app->view( $app, $model, 'list' );
    }

    function save_filter_tag ( &$cb, $app, &$obj ) {
        $normalize = preg_replace( '/\s+/', '', trim( strtolower( $obj->name ) ) );
        $comp = $app->db->model( 'tag' )->get_by_key(
                ['workspace_id' => $obj->workspace_id,
                 'normalize' => $normalize ] );
        if ( $comp->id && $comp->id != $obj->id ) {
            $cb['error'] = $app->translate( 'A %1$s with the same %2$s already exists.',
                [ $comp->name, $app->translate( 'Tag' ) ] );
            return false;
        }
        $obj->normalize( $normalize );
        return true;
    }

    function delete_filter_table ( &$cb, $app, &$obj ) {
        $ids = $app->param( 'id' );
        if (!is_array( $ids ) ) $ids = [ $ids ];
        $tables = $app->db->model( 'table' )->load( ['id' => ['IN' => $ids ] ] );
        $not_delete = [];
        foreach ( $tables as $table ) {
            if ( $table->not_delete ) {
                $not_delete[] = $table->name;
            }
        }
        if (! empty( $not_delete ) ) {
            $cb['error'] = $app->translate(
                '%s cannot be deleted.', join( ',', $not_delete ) );
            return false;
        }
        return true;
    }

    function delete_filter_template ( &$cb, $app, &$obj ) {
        $ids = $app->param( 'id' );
        if (!is_array( $ids ) ) $ids = [ $ids ];
        $tmpls = $app->db->model( 'template' )->load( ['id' => ['IN' => $ids ] ] );
        foreach ( $tmpls as $tpl ) {
            $cnt = $app->db->model( 'urlmapping' )->count( ['template_id' => $tpl->id ] );
            if ( $cnt ) {
                $cb['error'] = $app->translate( 'This view could not be deleted because '
                                           . 'it is being used for a URL map. To '
                                           . 'delete a view, delete the URL map first.' );
                return false;
            }
        }
        return true;
    }

    function save_filter_urlmapping ( &$cb, $app, $obj ) {
        if (! $obj->template_id || ! $obj->model ) {
            $cb['error'] = $app->translate( 'Model and View are required.' );
            return false;
        }
        return true;
    }

    function save_filter_table ( &$cb, $app, &$obj ) {
        require_once( LIB_DIR . 'Prototype' . DS . 'class.PTUpgrader.php' );
        $upgrader = new PTUpgrader;
        return $upgrader->save_filter_table( $cb, $app, $obj );
    }

    function post_save_workspace ( $cb, $app, $obj ) {
        $original = $cb['original'];
        if ( $original->site_url !== $obj->site_url ||
            $original->site_path !== $obj->site_path ) {
            $app->rebuild_fileinfo( $obj->site_url, $obj->site_path, $obj );
        }
    }

    function post_save_permission ( $cb, $app, $obj ) {
        $sessions =
            $app->db->model( 'session' )->load( ['user_id' => $obj->id,
                'name' => 'user_permissions', 'kind' => 'PM' ] );
        foreach ( $sessions as $sess ) {
            $sess->remove();
        }
    }

    function post_save_role ( $cb, $app, $obj ) {
        if ( empty( $cb['changed_cols'] ) ) return;
        $relations = $app->db->model( 'relation' )->load( ['from_obj' => 'permission',
            'to_obj' => 'role', 'name' => 'roles', 'to_id' => $obj->id ] );
        $ids = [];
        foreach ( $relations as $rel ) {
            $ids[] = $rel->id;
        }
        if (! empty( $ids ) ) {
            $permissions = 
                $app->db->model( 'permission' )->load( ['id' => ['IN', $ids ] ] );
            $user_ids = [];
            foreach ( $permissions as $perm ) {
                $user_ids[] = $perm->user_id;
            }
            if (! empty( $user_ids ) ) {
                $user_ids = array_unique( $user_ids );
                $sessions =
                    $app->db->model( 'session' )->load( ['user_id' => ['IN', $user_ids ],
                        'name' => 'user_permissions', 'kind' => 'PM' ] );
                foreach ( $sessions as $sess ) {
                    $sess->remove();
                }
            }
        }
    }

    function rebuild_fileinfo ( $url, $path, $workspace = null ) {
        $terms = $workspace ? ['workspace_id' => $workspace->id ] : null;
        $app = Prototype::get_instance();
        if ( $terms ) {
            $files = $app->db->model( 'fileinfo' )->load_iter( $terms );
        } else {
            $files = $app->db->model( 'fileinfo' )->load_iter();
        }
        foreach ( $files as $fi ){
            $relative_path = $fi->relative_path;
            $file_path = str_replace( '%r', $path, $relative_path );
            $file_path = str_replace( '/', DS, $file_path );
            $file_url = str_replace( '%r/', $url, $relative_path );
            $file_url = str_replace( DS, '/', $file_url );
            if ( $fi->file_path !== $file_path || $fi->url !== $file_url ) {
                if ( $fi->file_path !== $file_path && $fi->is_published ) {
                    if ( file_exists( $fi->file_path ) ) {
                        if ( file_exists( $file_path ) ) {
                            rename( $file_path, "{$file_path}.bk" );
                        }
                        $app->mkpath( dirname( $file_path ) );
                        rename( $fi->file_path, $file_path );
                        if ( file_exists( "{$file_path}.bk" ) ) {
                            unlink( "{$file_path}.bk" );
                        }
                    }
                }
                $fi->file_path( $file_path );
                $fi->url( $file_url );
                $fi->save();
                // TODO rmdir empty directory
            }
        }
    }

    function post_save_table ( $cb, $app, $obj, $force = false ) {
        require_once( LIB_DIR . 'Prototype' . DS . 'class.PTUpgrader.php' );
        $upgrader = new PTUpgrader;
        return $upgrader->post_save_table( $cb, $app, $obj, $force );
    }

    function post_save_asset ( $cb, $app, $obj ) {
        $db = $app->db;
        $metadata = $app->db->model( 'meta' )->get_by_key(
                 ['model' => 'asset', 'object_id' => $obj->id,
                  'key' => 'metadata', 'kind' => 'file'] );
        if ( $metadata->id && $metadata->text ) {
            $meta = json_decode( $metadata->text, true );
            if ( $meta['file_name'] != $obj->file_name ) {
                $meta['file_name'] = $obj->file_name;
                $metadata->text( json_encode( $meta ) );
                $metadata->save();
            }
        }
        $base_url = $app->site_url;
        $base_path = $app->site_path;
        if ( $workspace = $app->workspace() ) {
            $base_url = $workspace->site_url;
            $base_path = $workspace->site_path;
        }
        $file_path = $base_path . '/'. $obj->extra_path . $obj->file_name;
        $file_path = str_replace( '/', DS, $file_path );
        $rename = $app->publish( $file_path, $obj, 'file', $obj->mime_type );
        if ( $rename !== $file_path ) {
            $obj->file_name( basename( $rename ) );
            $obj->save();
        }
    }

    function publish ( $file_path, $obj, $key,
                        $mime_type = 'text/html', $type = 'file' ) {
        $app = Prototype::get_instance();
        $table = $app->get_table( $obj->_model );
        if (! $table ) return;
        $db = $app->db;
        $ctx = $app->ctx;
        $fi = $db->model( 'fileinfo' )->get_by_key( ['file_path' => $file_path ] );
        if (! $mime_type ) {
            // TODO
        }
        $fi_exists = false;
        $urlmapping_id = 0;
        $old_path = '';
        $publish = false;
        $unlink = false;
        $link_status = false;
        $template;
        $mapping = '';
        $urlmapping = null;
        $workspace = $obj->workspace;
        $ctx->stash( 'current_container', '' );
        if ( is_object( $key ) ) { // urlmapping
            // TODO re-(un)plublish files
            $urlmapping_id = $key->id;
            $urlmapping = $key;
            $type = $urlmapping->template_id ? 'archive' : 'model';
            $publish = $urlmapping->publish_file;
            $template = $urlmapping->template;
            if ( $template && $template->status != 2 ) {
                $unlink = true;
            }
            $workspace = $key->workspace;
            if ( $urlmapping->container ) {
                $container = $app->get_table( $urlmapping->container );
                if ( is_object( $container ) ) {
                    $ctx->stash( 'current_container', $container->name );
                }
            }
            $link_status = $key->link_status;
            $key = '';
        }
        if ( $fi->id ) {
            if ( $key == $fi->key && $fi->model == $table->name &&
                $fi->urlmapping_id == $urlmapping_id && $fi->object_id == $obj->id ) {
                $fi_exists = true;
            } else {
                $file_ext = basename( $file_path );
                $file_ext = strpos( $file_ext, '.' ) === false ?
                    '' : preg_replace( '/^.*\.([^\.]*$)/', '$1', $file_ext );
                $basename = preg_replace( "/\.{$file_ext}$/", '', $file_path );
                $i = 1;
                $unique = false;
                while ( $unique === false ) {
                    $rename = $basename . '-' . $i . '.' . $file_ext;
                    $exists = $db->model( 'fileinfo' )->get_by_key(
                        ['file_path' => $rename ] );
                    if (! $exists->id ) {
                        $unique = true;
                        $file_path = $rename;
                        break;
                    }
                    $i++;
                }
                if ( $mapping && $obj->has_column( 'basename' ) ) {
                    if ( stripos( $mapping, 'basename' ) !== false ) {
                        $obj->basename( basename( $file_path ) );
                        $obj->save();
                    }
                }
            }
        }
        if (! $fi_exists ) {
            $old = $db->model( 'fileinfo' )->get_by_key(
                ['model' => $table->name, 'key' => $key, 'object_id' => $obj->id,
                 'type' => $type, 'urlmapping_id' => $urlmapping_id ] );
            $fi->id( $old->id );
            $old_path = $old->file_path;
            $old_path = str_replace( '/', DS, $old_path );
        }
        $base_url = $app->site_url;
        $base_path = $app->site_path;
        $asset_publish = $app->asset_publish;
        if ( $workspace ) {
            $base_url = $workspace->site_url;
            $base_path = $workspace->site_path;
            $asset_publish = $workspace->asset_publish;
        }
        $file_path = str_replace( DS, '/', $file_path );
        $base_path = str_replace( DS, '/', $base_path );
        $search = preg_quote( $base_path, '/' );
        $relative_path = preg_replace( "/^{$search}\//", '', $file_path );
        $url = $base_url . $relative_path;
        $url = str_replace( DS, '/', $url );
        $relative_path = str_replace( '/', DS, $relative_path );
        $relative_url = preg_replace( '!^https{0,1}:\/\/.*?\/!', '/', $url );
        $fi->set_values( ['model' => $table->name,
                          'url' => $url,
                          'key' => $key,
                          'object_id' => $obj->id,
                          'relative_url' => $relative_url,
                          'urlmapping_id' => (int) $urlmapping_id,
                          'file_path' => $file_path,
                          'mime_type' => $mime_type,
                          'type' => $type,
                          'relative_path' => '%r' . DS . $relative_path,
                          'workspace_id' => $obj->workspace_id ] );
        if ( $obj->has_column( 'status' ) ) {
            $status_published = $app->status_published( $obj->_model );
            if ( $obj->status != $status_published ) {
                $unlink = true;
            }
        }
        if ( $type === 'file' || $publish ) {
            if ( $asset_publish || $publish ) {
                if ( $old_path && $old_path !== $file_path && file_exists( $old_path ) ) {
                    rename( $old_path, "{$old_path}.bk" );
                }
                $app->mkpath( dirname( $file_path ) );
                if ( $type === 'file' ) {
                    // TODO Check Diff or timestamp
                    if ( $unlink ) {
                        if ( file_exists( $file_path ) ) {
                            unlink( $file_path );
                        }
                        $fi->is_published( 0 );
                    } else {
                        if ( file_put_contents( $file_path, $obj->$key ) ) {
                            $fi->is_published( 1 );
                        } else {
                            $fi->is_published( 0 );
                        }
                    }
                } else {
                    if ( $unlink ) {
                        if ( file_exists( $file_path ) ) {
                            unlink( $file_path );
                        }
                        $fi->is_published( 0 );
                    } else {
                        $tmpl = $template->text;
                        $ctx->stash( 'current_template', $template );
                        if ( $obj->_model != 'template' ) {
                            $ctx->stash( 'current_context', $obj->_model );
                            $ctx->stash( $obj->_model, $obj );
                        } else {
                            $ctx->stash( 'current_context', '' );
                            $ctx->stash( $obj->_model, '' );
                        }
                        $data = $ctx->build( $tmpl );
                        $updated = true;
                        if ( file_exists( $file_path ) ) {
                            $old = file_get_contents( $file_path );
                            if ( md5( $old ) === md5( $data ) ) {
                                $fi->is_published( 1 );
                                $updated = false; 
                            }
                        }
                        if ( $updated ) {
                            if ( file_put_contents( $file_path, $data ) ) {
                                $fi->is_published( 1 );
                            }
                        }
                    }
                }
                if ( file_exists( "{$old_path}.bk" ) ) {
                    unlink( "{$old_path}.bk" );
                }
            } else {
                $fi->is_published( 0 );
                if ( $old_path && $old_path !== $file_path && file_exists( $old_path ) ) {
                    unlink( $old_path );
                }
                if ( file_exists( $file_path ) ) {
                    unlink( $file_path );
                }
            }
        }
        if ( $unlink && $link_status ) {
            if ( $fi->id ) $fi->remove();
        } else {
            $date_based = $ctx->stash( 'archive_date_based' );
            if ( $date_based ) {
                $fi->archive_date( $ctx->stash( 'current_timestamp' ) );
            } else {
                $fi->archive_date = null;
            }
            $fi->archive_type( $ctx->stash( 'current_archive_type' ) );
            $fi->save();
        }
        return $file_path;
    }

    function publish_dependencies ( $obj, $original, $mapping ) {
        $app = Prototype::get_instance();
        $relations = [];
        $to_obj = $mapping->model;
        if ( $mapping->model !== 'template' ) {
            $to_obj = $mapping->model;
            $relations = $app->get_relations( $obj, $to_obj );
            $orig_relations = $app->get_relations( $original, $to_obj );
            $relations = array_merge( $relations, $orig_relations );
        } else {
            $template = $mapping->template;
            if ( $template && $template->id ) {
                $relation = $app->db->model( 'relation' )->get_by_key(
                    ['to_obj' => 'template', 'to_id' => $template->id ]
                );
                $relations[] = $relation;
            }
        }
        $to_obj = $mapping->model;
        $table = $app->get_table( $to_obj );
        $published_ids = [];
        foreach ( $relations as $relation ) {
            $id = $relation->to_id;
            $dependencie = $app->db->model( $to_obj )->load( $id );
            if ( is_object( $dependencie ) ) {
                if (! $mapping->date_based && in_array( $id, $published_ids ) ) {
                    continue;
                }
                $ts = '';
                $orig_ts = '';
                if ( $date_based = $mapping->date_based ) {
                    $date_col = $app->get_date_col( $obj );
                    if (! $date_col ) {
                        continue;
                    }
                    $date = $obj->$date_col;
                    $orig_date = $original->$date_col;
                    $date = $obj->db2ts( $date );
                    $orig_date = $obj->db2ts( $orig_date );
                    $ts = $date;
                    $orig_ts = $orig_date;
                    if ( stripos( $date_based, 'Year' ) !== false ) {
                        $date = substr( $date, 0, 4 );
                        $orig_date = substr( $orig_date, 0, 4 );
                    } else {
                        // Monthly
                        $date = substr( $date, 0, 6 );
                        $orig_date = substr( $orig_date, 0, 6 );
                    }
                    if ( $date == $orig_date && in_array( $id, $published_ids ) ) {
                        continue;
                    }
                }
                $file_path = $app->build_path_with_map
                                                ( $dependencie, $mapping, $table, $ts );
                $app->publish( $file_path, $dependencie, $mapping );
                $published_ids[] = $id;
            }
        }
    }

    function get_date_col ( $obj ) {
        $date_col = '';
        if ( $obj->has_column( 'published_on' ) ) {
            $date_col = 'published_on';
        } else {
            $app = Prototype::get_instance();
            $obj_table = $app->get_table( $obj->_model );
            $col = $app->db->model( 'column' )->load(
              ['table_id' => $obj_table->id, 'type' => 'datetime'],
              ['limit' => 1, 'sort' => 'order', 'direction' => 'ascend'] );
            if ( is_array( $col ) && !empty( $col ) ) {
                $col = $col[0];
                $date_col = $col->name;
            }
        }
        return $date_col;
    }

    function mkpath ( $path, $mode = 0777 ) {
        if ( is_dir( $path ) ) return true;
        return mkdir( $path, $mode, true );
    }

    function save_filter_workspace ( &$cb, $app, $obj ) {
        $url = $obj->site_url;
        if ( $url && !$app->is_valid_url( $url, $msg ) ) {
            $cb['error'] = $msg;
        } else {
            $obj->site_url( $url );
            if (! preg_match( '!\/$!', $url ) ) $url .= '/';
        }
        $path = $obj->site_path;
        $path = rtrim( $path, DS );
        $obj->site_path( $path );
        return true;
    }
 
    function get_user_opt ( $key, $kind, $workspace_id = null ) {
        $app = Prototype::get_instance();
        $user = $app->user();
        $terms = ['user_id' => $user->id, 'key' => $key , 'kind' => $kind ];
        $workspace_id = (int) $workspace_id;
        $terms['workspace_id'] = $workspace_id;
        $list_option = $app->db->model( 'option' )->get_by_key( $terms );
        return $list_option;
    }

    function get_scheme_from_db ( $model, $force = false ) {
        $app = Prototype::get_instance();
        if ( $app->stash( 'scheme:' . $model ) && ! $force ) {
            return $app->stash( 'scheme:' . $model );
        }
        $db = $app->db;
        $table = $app->get_table( $model );
        if (! $table ) return null;
        $obj_label = $app->translate( $table->label );
        $columns = $db->model( 'column' )->load( ['table_id' => $table->id ],
            ['sort' => 'order'] );
        if (! is_array( $columns ) || empty( $columns ) ) {
            return null;
        }
        $obj = $db->model( $model )->new();
        $scheme = $obj->_scheme;
        if (! $scheme ) $scheme = [];
        list( $column_defs, $indexes, $list, $edit, $labels, $unique,
            $unchangeable, $autoset, $locale ) = [ [], [], [], [], [], [], [], [], [] ];
        $locale['default'] = [];
        $lang = $app->language;
        $locale[ $lang ] = [];
        $scheme['label'] = $table->label;
        $scheme['plural'] = $table->plural;
        $locale[ $lang ][ $table->label ] = $app->translate( $table->label );
        $locale[ $lang ][ $table->plural ] = $app->translate( $table->plural );
        $relations = [];
        $col_options = [];
        foreach ( $columns as $column ) {
            $col_name = $column->name;
            $props = [];
            $props['type'] = $column->type;
            if ( $column->type == 'relation' ) {
                $relations[ $col_name ] = $column->options;
            } else if ( $column->options ) {
                $col_options[ $col_name ] = $column->options;
            }
            if ( $column->size ) $props['size'] = (int) $column->size;
            $not_null = $column->not_null;
            if ( $not_null ) $props['not_null'] = 1;
            if ( $column->default !== "" ) $props['default'] = $column->default;
            $column_defs[ $column->name ] = $props;
            if ( $column->is_primary ) $indexes['PRIMARY'] = $col_name;
            if ( $column->index ) $indexes[ $col_name ] = $col_name;
            if ( $column->list ) $list[ $col_name ] = $column->list;
            if ( $column->edit ) $edit[ $col_name ] = $column->edit;
            if ( $column->unique ) $unique[] = $col_name;
            if ( $column->unchangeable ) $unchangeable[] = $col_name;
            if ( $column->autoset ) $autoset[] = $col_name;
            $label = $column->label;
            $locale['default'][ $col_name ] = $label;
            $trans_label = $app->translate( $label );
            $locale[ $lang ][ $label ] = $trans_label;
            $labels[ $col_name ] = $trans_label;
        }
        if ( $table->primary ) $scheme['primary'] = $table->primary;
        $options = ['auditing', 'order', 'sortable', 'hierarchy', 'start_end',
                    'menu_type', 'template_tags', 'taggable', 'display_space',
                    'has_basename', 'has_status', 'assign_user', 'revisable'];
        foreach ( $options as $option ) {
            if ( $table->$option ) $scheme[ $option ] = (int) $table->$option;
        }
        // TODO
        if ( $table->display_system ) $scheme['display'] = (int) $table->display_system;
        $scheme['column_defs'] = $column_defs;
        if (! empty( $relations ) ) $scheme['relations'] = $relations;
        if (! empty( $col_options ) ) $scheme['options'] = $col_options;
        $sort_by = $table->sort_by;
        $sort_order = $table->sort_order ? $table->sort_order : 'ascend';
        if ( $sort_by && $sort_order ) {
            $scheme['sort_by'] = [ $sort_by => $sort_order ];
        }
        if ( $table->space_child ) {
            $scheme['child_of'] = 'workspace';
        }
        if (!empty( $autoset ) ) {
            $scheme['autoset'] = $autoset;
        }
        $db->scheme[ $model ]['column_defs'] = $column_defs;
        $scheme['indexes'] = $indexes;
        if (! empty( $unique ) ) $scheme['unique'] = $unique;
        if (! empty( $unchangeable ) ) $scheme['unchangeable'] = $unchangeable;
        $scheme['edit_properties'] = $edit;
        $scheme['list_properties'] = $list;
        $scheme['labels'] = $labels;
        $scheme['label'] = $obj_label;
        $scheme['locale'] = $locale;
        $app->stash( 'scheme:' . $model, $scheme );
        return $scheme;
    }

    function validate_magic () {
        $app = Prototype::get_instance();
        $is_valid = true;
        if (! $app->user() ) $is_valid = false;
        if ( $app->request_method !== 'POST' ) $is_valid = false;
        $token = $app->param( 'magic_token' );
        if (! $token || $token !== $app->current_magic ) $is_valid = false;
        if (! $is_valid ) return $app->error( 'Invalid request.' );
        return true;
    }

    function recover_password () {
        $app = Prototype::get_instance();
        $token = $app->param( 'token' );
        if ( $token ) {
            $session = $app->db->model( 'session' )->get_by_key(
                ['name' => $token, 'kind' => 'RP'] );
            if (! $session->id ) {
                return $app->error( 'Invalid request.' );
            }
            if ( $session->expires < time() ) {
                return $app->error(
                    'Your request to reset your password has expired.' );
            }
        }
        if ( $app->request_method === 'POST' ) {
            $type = $app->param( '_type' );
            if ( $type && $type === 'new_password' ) {
                if (! isset( $session ) ) {
                    return $app->error( 'Invalid request.' );
                }
                $password = $app->param( 'password' );
                $verify = $app->param( 'password-verify' );
                if (! $app->is_valid_password( $password, $verify, $msg ) ) {
                    $app->assign_params( $app, $app->ctx, true );
                    $app->ctx->vars['error'] = $msg;
                    $app->param( '_type', 'recover' );
                    return $app->__mode( 'start_recover' );
                }
                $user = $app->db->model( 'user' )->load( $session->user_id );
                if (! $user ) {
                    return $app->error( 'Invalid request.' );
                }
                $password = password_hash( $password, PASSWORD_BCRYPT );
                $user->password( $password );
                $user->save();
                $session->remove();
                $app->redirect( $app->admin_url . '?__mode=login' );
            } else {
                $email = $app->param( 'email' );
                if (!$app->is_valid_email( $email, $msg ) ) {
                    $app->assign_params( $app, $app->ctx, true );
                    $app->ctx->vars['error'] = $msg;
                    return $app->__mode( 'start_recover' );
                }
                $user = $app->db->model( 'user' )->load( ['email' => $email ] );
                if ( count( $user ) ) {
                    $user = $user[0];
                    $tmpl = TMPL_DIR . 'email' . DS . 'recover_password.tmpl';
                    $session_id = $app->magic();
                    $app->ctx->vars['token'] = $session_id;
                    $body = $app->ctx->build_page( $tmpl );
                    $session = $app->db->model( 'session' )->get_by_key(
                        ['name' => $session_id, 'kind' => 'RP'] );
                    $session->start( time() );
                    $session->expires( time() + $app->token_expires );
                    $session->user_id( $user->id );
                    $session->save();
                    $system_email = $app->get_config( 'system_email' );
                    if (! $system_email ) {
                        $app->assign_params( $app, $app->ctx, true );
                        $app->ctx->vars['error'] =
                            $app->translate( 'System Email Address is not set in System.' );
                        return $app->__mode( 'start_recover' );
                    }
                    $headers = ['From' => $system_email->value ];
                    $subject = $app->translate( 'Password Recovery' );
                    if (! $app->send_mail( $email, $subject, $body, $headers, $error ) ) {
                        $app->ctx->vars['error'] = $error;
                        return $app->__mode( 'start_recover' );
                    }
                }
                $app->ctx->vars['header_alert_message'] = $app->translate(
                        'An email with a link to reset your password has been sent'
                        .' to your email address (%s).', $email );
            }
            $app->ctx->vars['recovered'] = 1;
            return $app->__mode( 'start_recover' );
        } else {
            return $app->__mode( 'start_recover' );
        }
    }

    function send_mail ( $to, $subject, $body, $headers, &$error ) {
        $app = Prototype::get_instance();
        mb_internal_encoding( $app->encoding );
        $from = isset( $headers['From'] )
            ? $headers['From'] : $app->get_config( 'system_email' );
        if (! $to || ! $from ||! $subject ) {
            $error = $app->translate( 'To, From and subject are required.' );
            return false;
        }
        if (!$app->is_valid_email( $from, $error ) ) {
            return false;
        }
        if (!$app->is_valid_email( $to, $error ) ) {
            return false;
        }
        unset( $headers['From'] );
        $options = "From: {$from}\r\n";
        foreach ( $headers as $key => $value ) {
            $options .= "{$key}: {$value}\r\n";
        }
        return mb_send_mail( $to, $subject, $body, $options );
    }

    function upgrade () {
        require_once( LIB_DIR . 'Prototype' . DS . 'class.PTUpgrader.php' );
        $upgrader = new PTUpgrader;
        return $upgrader->upgrade();
    }

    function set_config ( $cfgs ) {
        $app = Prototype::get_instance();
        foreach ( $cfgs as $key => $value ) {
            $option = $app->db->model( 'option' )->get_by_key(
                    ['key' => $key, 'kind' => 'config'] );
            $col = mb_strlen( $value ) >= 255 ? 'data' : 'value';
            if ( $col === 'data' && $option->value ) {
                $option->value( '' );
            } else if ( $col === 'value' && $option->data ) {
                $option->data( '' );
            }
            $option->$col( $value );
            $option->save();
        }
    }

    function get_config ( $key = null ) {
        $app = Prototype::get_instance();
        if (! $key ) return $app->db->model( 'option' )->load( ['kind' => 'config'] );
        $cfg = $app->db->model( 'option' )->get_by_key(
            ['kind' => 'config', 'key' => $key ] );
        return $cfg->id ? $cfg : null;
    }

    function assign_params ( $app, $ctx, $raw = false ) {
        $params = $app->param();
        foreach( $params as $key => $value ) {
            if ( $raw ) $ctx->vars[ $key ] = $value;
            $ctx->__stash['forward_' . $key ] = $value;
            if ( preg_match( "/(^.*)_date$/", $key, $mts ) ) {
                $name = $mts[1];
                if ( $time = $app->param( $name . '_time' ) ) {
                    $ctx->__stash['forward_' . $name ] = $value . $time;
                }
            }
            if ( $key === 'permission' && $value ) {
                $ctx->vars['error'] = $app->translate( 'Permission denied.' );
            }
        }
    }

    function stash ( $name, $value = false, $var = null ) {
        if ( isset( $this->stash[ $name ] ) ) $var = $this->stash[ $name ];
        if ( $value !== false ) $this->stash[ $name ] = $value;
        return $var;
    }

    function filter_epoch2str ( $ts, $arg, $ctx ) {
        if (! $ts ) $this->translate( 'Just Now' );
        $ts = time() - $ts;
        if ( $ts < 3600 ) {
            $str = $ts / 60;
            $str = round( $str );
            if ( $str < 5 ) return $this->translate( 'Just Now' );
            return $this->translate( '%s mins ago', $str );
        } else if ( $ts < 86400 ) {
            $str = $ts / 3600;
            $str = round( $str );
            return $this->translate( '%s hours ago', $str );
        } else if ( $ts < 604800 ) {
            $str = $ts / 86400;
            $str = round( $str );
            return $this->translate( '%s day(s) ago', $str );
        } else if ( $ts < 2678400 ) {
            $str = $ts / 604800;
            $str = round( $str );
            return $this->translate( '%s week(s) ago', $str );
        } else if ( $ts < 31536000 ) {
            return $this->translate( 'More than month ago' );
        } else {
            return $this->translate( 'More than year ago' );
        }
    }

    function get_assetproperty ( $obj, $name, $property = 'all' ) {
        $app = Prototype::get_instance();
        $model = is_object( $obj ) ? $obj->_model : $app->param( '_model' );
        $obj_id = is_object( $obj ) ? $obj->id : 0;
        $ctx = $app->ctx;
        $session = $ctx->stash( 'current_session_' . $name );
        if ( $property === 'url' ) {
            if ( $session ) {
                $screen_id = $app->param( '_screen_id' );
                $params = '?__mode=view&amp;_type=edit&amp;_model=' . $obj->_model;
                $params .= '&amp;id=' . $obj->id . '&amp;view=' . $name 
                        . '&amp;_screen_id=' . $screen_id;
                if ( $workspace = $app->workspace() ) {
                    $params .= '&amp;workspace_id=' . $workspace->id;
                }
                return $app->admin_url . $params;
            }
            if ( is_object( $obj ) ) {
                $fi = $app->db->model( 'fileinfo' )->get_by_key(
                    ['model' => $model, 'object_id' => $obj_id, 'key' => $name ]
                );
                return $fi->url;
            }
        }
        $data = '';
        if ( isset( $ctx->vars['forward_params'] ) || $session ) {
            $screen_id = $ctx->vars['screen_id'];
            $screen_id .= '-' . $name;
            $cache = $ctx->stash( $model . '_session_' . $screen_id . '_' . $obj_id );
            if (! $session ) {
                $session = $cache ? $cache : $app->db->model( 'session' )->get_by_key(
                ['name' => $screen_id, 'user_id' => $this->user()->id ]
                );
            }
            $ctx->stash( $model . '_session_' . $screen_id . '_' . $obj_id, $session );
            if ( $session->id ) {
                $data = $session->text;
            }
        }
        if (! $data ) {
            $cache = $ctx->stash( $model . '_meta_' . $name . '_' . $obj_id );
            $metadata = $cache ? $cache : $this->db->model( 'meta' )->get_by_key(
                     ['model' => $model, 'object_id' => $obj_id,
                      'key' => 'metadata', 'kind' => $name ] );
            if (! $metadata->id ) {
                return ( $property === 'all' ) ? [] : null;
            }
            $ctx->stash( $model . '_meta_' . $name . '_' . $obj_id, $metadata );
            $data = $metadata->text;
        }
        $data = json_decode( $data, true );
        if ( $property === 'all' ) {
            return $data;
        }
        return ( isset( $data[ $property ] ) ) ? $data[ $property ] : null;
        $data = $metadata->value;
        $props = explode( ':', $data );
        $basename = $metadata->text;
        $file_ext = $props[4];
        $file_name = $file_ext ? $basename . '.' . $file_ext : $basename;
        $mime_type = $metadata->type;
        $file_size = $props[0];
        $image_width = $props[1];
        $image_height = $props[2];
        $class = $props[3];
        if ( $property === 'mime_type' ) return $mime_type;
        else if ( $property === 'basename' ) return $basename;
        else if ( $property === 'file_size' ) return $file_size;
        else if ( $property === 'class' ) return $class;
        else if ( $property === 'image_width' ) return $image_width;
        else if ( $property === 'image_height' ) return $image_height;
        else if ( $property === 'file_name' ) return $file_name;
        $all_props = [
            'basename'     => $basename,
            'file_ext'     => $file_ext,
            'file_name'    => $file_name,
            'mime_type'    => $mime_type,
            'file_size'    => $file_size,
            'image_width'  => $image_width,
            'image_height' => $image_height,
            'class'        => $class
        ];
        return $all_props;
    }

    function status_published ( $model ) {
        $app = Prototype::get_instance();
        $status = $app->stash( 'status_published:' . $model );
        if ( isset( $status ) && $status ) return $status;
        $scheme = $app->db->scheme;
        if ( isset( $scheme[ $model ] ) ) {
            $scheme = $scheme[ $model ]['column_defs'];
            if ( isset( $scheme['status'] ) ) {
                if ( isset( $scheme['has_deadline'] ) ) {
                    $app->stash( 'status_published:' . $model, 4 );
                    return 4;
                } else {
                    $app->stash( 'status_published:' . $model, 2 );
                    return 2;
                }
            }
        }
        return null;
    }

    function get_relations ( $obj, $to_obj = null, $name = null ) {
        $app = Prototype::get_instance();
        $terms = ['from_id'  => $obj->id, 
                  'from_obj' => $obj->_model ];
        if ( $to_obj ) $terms['to_obj'] = $to_obj;
        if ( $name ) $terms['name'] = $name;
        $relations = $app->db->model( 'relation' )->load(
                                       $terms, ['sort' => 'order'] );
        return $relations;
    }

    function get_meta ( $obj, $key = null, $type = null, $kind = null ) {
        $app = Prototype::get_instance();
        $terms = ['object_id'  => $obj->id, 
                  'model' => $obj->_model ];
        if ( $key )  $terms['key']  = $key;
        if ( $type ) $terms['type'] = $type;
        if ( $kind ) $terms['kind'] = $kind;
        $meta = $app->db->model( 'meta' )->load( $terms );
        return $meta;
    }

    function set_default ( &$obj ) {
        $app = Prototype::get_instance();
        if ( $obj->has_column( 'modified_on' ) ) {
            $obj->modified_on( date( 'YmdHis' ) );
        }
        if ( $obj->has_column( 'created_on' ) ) {
            $ts = $obj->created_on;
            $ts = preg_replace( '/[^0-9]/', '', $ts );
            $ts = (int) $ts;
            if (! $ts ) {
                $obj->created_on( date( 'YmdHis' ) );
            }
        }
        if ( $user = $this->user() ) {
            if ( $obj->has_column( 'modified_by' ) ) {
                $obj->modified_by( $user->id );
            }
            if ( $obj->has_column( 'created_by' ) && !$obj->id ) {
                $obj->created_by( $user->id );
            }
        }
        if ( $obj->has_column( 'extra_path' ) ) {
            $extra_path = $obj->extra_path;
            $obj->extra_path( $app->sanitize_dir( $extra_path ) );
        }
        if ( $workspace = $app->workspace() ) {
            if ( $obj->has_column( 'workspace_id' ) ) {
                $obj->workspace_id( $workspace->id );
            }
        }
    }

    function translate ( $phrase, $params = '', $component = null, $lang = null ) {
        $component = $component ? $component : $this;
        $lang = $lang ? $lang : $this->language;
        $dict = isset( $component->dictionary ) ? $component->dictionary : null;
        if ( $dict && isset( $dict[ $lang ] ) && isset( $dict[ $lang ][ $phrase ] ) )
             $phrase = $dict[ $lang ][ $phrase ];
        $phrase = is_string( $params )
            ? sprintf( $phrase, $params ) : vsprintf( $phrase, $params );
        return $phrase;
    }

    function redirect ( $url ) {
        $app = Prototype::get_instance();
        if ( $return_args = $app->return_args ) {
            if (! empty( $return_args ) ) {
                $return_args = http_build_query( $return_args );
                if ( strpos( $url, '?' ) === false ) {
                    $url .= '?';
                } else {
                    $url .= '&';
                }
                $url .= $return_args;
            }
        }
        header( $this->protocol . ' 302 Redirect' );
        header( 'Status: 302 Redirect' );
        header( 'Location: ' . $url );
        exit();
    }

    function is_login () {
        $app = Prototype::get_instance();
        if ( $app->stash( 'logged-in' ) ) return true;
        $cookie = $app->cookie_val( $app->cookie_name );
        if (! $cookie ) return false;
        $sess = $app->db->model( 'session' )->load(
            ['name' => $cookie, 'kind' => 'US'] );
        if (! empty( $sess ) ) {
            $sess = $sess[0];
            $expires = $sess->expires ? $sess->expires : $app->sess_timeout;
            if ( $sess->start + $expires < time() ) {
                $sess->remove();
                return false;
            }
            $token = md5( $cookie );
            $app->ctx->vars['magic_token'] = $token;
            $app->current_magic = $token;
            $user = $app->db->model( 'user' )->load( $sess->user_id );
            if ( is_object ( $user ) ) {
                $app->user = $user;
                $app->language = $user->language;
                $app->ctx->language = $user->language;
                $app->ctx->vars['user_id'] = $user->id;
                $app->stash( 'logged-in', true );
                return true;
            }
        }
        return false;
    }

    function log ( $message ) {
        $message = is_string( $message ) ? ['message' => $message ] : $message;
        $app = Prototype::get_instance();
        $ip = '';
        if ( isset( $_SERVER[ 'HTTP_X_FORWARDED_FOR' ] ) ) {
            $ip = $_SERVER[ 'HTTP_X_FORWARDED_FOR' ];
        } else {
            $ip = $_SERVER[ 'REMOTE_ADDR' ];
        }
        $message['remote_ip'] = $ip;
        $log = $app->db->model( 'log' )->new( $message );
        if (! $log->level ) {
            $log->level(1);
        } else {
            if ( strtolower( $log->level ) == 'info' ) {
                $log->level(1);
            } else if ( strtolower( $log->level ) == 'warning' ) {
                $log->level(2);
            } else if ( strtolower( $log->level ) == 'error' ) {
                $log->level(4) ;
            } else if ( strtolower( $log->level ) == 'security' ) {
                $log->level(8) ;
            } else if ( strtolower( $log->level ) == 'debug' ) {
                $log->level(16) ;
            }
        }
        if (! $log->category ) $log->category( 'system' );
        $app->set_default( $log );
        $log->save();
    }

    function moved_permanently ( $url ) {
        header( $this->protocol . ' 301 Moved Permanently' );
        header( 'Status: 301 Moved Permanently' );
        header( 'Location: ' . $url );
        exit();
    }

    function bake_cookie ( $name, $value, $expires = null, $path = null ) {
        if (! $expires ) $expires = 0;
        if ( $expires ) $expires += time();
        return setcookie( $name, $value, $expires, $path );
    }

    function cookie_val ( $name ) {
        if ( isset( $_COOKIE[ $name ] ) ) {
            return $_COOKIE[ $name ];
        }
        return '';
    }

    function current_ts () {
        return date( 'YmdHis' );
    }

    function magic ( $content = '' ) {
        $magic = md5( uniqid( mt_rand(), true ) );
        // todo check uniq session name
        return $magic;
    }

    function error ( $message, $params = null, $component = null, $lang = null ) {
        $this->ctx->vars['error'] = 
            $this->translate( $message, $params, $component, $lang );
        $this->ctx->vars['page_title'] = $this->translate( 'An error occurred' );
        $this->__mode = 'error';
        $this->__mode( 'error' );
    }

    function get_param ( $param ) {
        $qurey = null;
        if ( isset ( $_GET[ $param ] ) ) {
            $qurey = $_GET[ $param ];
        } else if ( isset ( $_POST[ $param ] ) ) {
            $qurey = $_POST[ $param ];
        } else if ( isset ( $_REQUEST[ $param ] ) ) {
            $qurey = $_REQUEST[ $param ];
        }
        return $qurey;
    }

    function param ( $param = null, $value = null ) {
        if ( $param && $value ) {
            $_REQUEST[ $param ] = $value;
            return $value;
        }
        $encoding = $this->encoding;
        if ( $param ) {
            $value = $this->get_param( $param );
            if ( $value !== null ) {
                if (! is_array( $value ) ) {
                    $from = mb_detect_encoding( $value, 'UTF-8,EUC-JP,SJIS,JIS' );
                    $value = mb_convert_encoding( $value, $encoding, $from );
                    return $value;
                } else {
                    $new_array = array();
                    foreach ( $value as $val ) {
                        $from = mb_detect_encoding( $val, 'UTF-8,EUC-JP,SJIS,JIS' );
                        $val = mb_convert_encoding( $val, $encoding, $from );
                        array_push( $new_array, $val );
                    }
                    return $new_array;
                }
            }
        } else {
            $vars = $_REQUEST;
            $params = array();
            foreach ( $vars as $key => $value ) {
                if ( isset( $_GET[ $key ] ) || isset( $_POST[ $key ] ) ) {
                    if ( is_string( $value ) ) {
                        $from = mb_detect_encoding( $value, 'UTF-8,EUC-JP,SJIS,JIS' );
                        $value = mb_convert_encoding( $value, $encoding, $from );
                    }
                    $params[ $key ] = $value;
                }
            }
            return $params;
        }
    }

    function is_valid_email ( $value, &$msg ) {
        $regex = '/^[a-zA-Z0-9\.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-z'
               .'A-Z0-9-]+)*$/';
        if (! $value || ! preg_match( $regex, $value, $mts ) ) {
            $app = Prototype::get_instance();
            $msg = $app->translate(
                        'Please specify a valid email address.' );
            return false;
        }
        return true;
    }

    function is_valid_property ( $prop, &$msg = '', $len = false ) {
        $app = Prototype::get_instance();
        if (! preg_match( "/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $prop ) ||
            ! preg_match( "/^[a-zA-Z]/", $prop ) ) {
            $msg = $app->translate(
            'A valid model or column name starts with a letter, '
            .'followed by any number of letters, numbers, or underscores.' );
            return false;
        }
        if ( $len ) {
            $max_length = 30 - strlen( $this->dbprefix );
            if ( strlen( $prop ) > $max_length ) {
                $msg = $app->translate(
                'The Model or Column name must be %s characters or less.', $max_length );
                return false;
            }
        }
        return true;
    }

    function is_valid_url ( $url, &$msg ) {
        if (
          preg_match( '/^https?(:\/\/[-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)$/',
                $url ) ) {
            return true;
        } else {
            $app = Prototype::get_instance();
            $msg = $app->translate(
                        'Please enter a valid URL.' );
            return false;
        }
    }

    function is_valid_password ( $pass, $verify = null, &$msg ) {
        $app = Prototype::get_instance();
        if ( isset( $verify ) && $pass !== $verify ) {
            $msg = $app->translate( 'Both passwords must match.' );
            return false;
        }
        $min = $app->passwd_min;
        $rule = $app->passwd_rule;
        if ( $min && $rule ) {
            $len = strlen( $pass );
            $pass = preg_replace( '/[0-9a-zA-Z]/', '', $pass );
            if (! $len >= $min || !$pass ) {
                $msg = $app->translate(
              'Password should be longer than %s characters and contain symbols.', $min );
                return false;
            }
        } else {
            if (! strlen( $pass ) >= $min ) {
                $msg = $app->translate(
                'Password should be longer than %s characters.', $min );
                return false;
            }
        }
        return true;
    }

    function sanitize_dir ( &$path ) {
        if ( $path ) {
            if ( preg_match( '/^\/{1,}/', $path ) ) {
                $path = preg_replace( '/^\/{1,}/', '', $path );
            }
            if (! preg_match( '/\/$/', $path ) ) 
                $path .= '/';
        }
        return $path;
    }

    function debugPrint ( $msg ) {
        echo '<hr><pre>', htmlspecialchars( $msg ), '</pre><hr>';
    }

    function errorHandler ( $errno, $errmsg, $f, $line ) {
        $msg = "{$errmsg} ({$errno}) occured( line {$line} of {$f} ).";
        if ( $this->debug == 2 ) $this->debugPrint( $msg );
        if ( $this->logging && !$this->log_path )
            $this->log_path = __DIR__ . DS . 'log' . DS;
        if ( $this->logging ) error_log( date( 'Y-m-d H:i:s T', time() ) .
            "\t" . $msg . "\n", 3, $this->log_path . 'error.log' );
    }
}
