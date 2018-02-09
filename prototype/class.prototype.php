<?php

if (! defined( 'DS' ) ) {
    define( 'DS', DIRECTORY_SEPARATOR );
}
if (! defined( 'LIB_DIR' ) ) {
    define( 'LIB_DIR', __DIR__ . DS . 'lib' . DS );
}
if (! defined( 'TMPL_DIR' ) ) {
    define( 'TMPL_DIR', __DIR__ . DS . 'tmpl' );
}
if (! defined( 'ALT_TMPL' ) ) {
    define( 'ALT_TMPL', __DIR__ . DS . 'alt-tmpl' );
}
ini_set( 'include_path', ini_get( 'include_path' ) . PATH_SEPARATOR
         . __DIR__ . DS . 'lib' . PATH_SEPARATOR . LIB_DIR . 'Prototype' );

function prototype_auto_loader ( $class ) {
    if ( is_readable( LIB_DIR . 'Prototype' . DS . "class.{$class}.php" ) ) {
        require_once( LIB_DIR . 'Prototype' . DS . "class.{$class}.php" );
        return true;
    }
}
spl_autoload_register( '\prototype_auto_loader' );

class Prototype {

    public static $app = null;

    public    $name          = 'Prototype';
    public    $db            = null;
    public    $ctx           = null;
    public    $dictionary    = [];
    public    $language      = null;
    protected $dbprefix      = 'mt_';
    protected $cookie_name   = 'pt-user';
    public    $encoding      = 'UTF-8';
    public    $mode          = 'dashboard';
    public    $timezone      = 'Asia/Tokyo';
    public    $list_limit    = 25;
    public    $per_rebuild   = 120;
    public    $rebuild_interval = 0;
    public    $basename_len  = 40;
    public    $passwd_min    = 8;
    public    $passwd_rule   = false;
    public    $retry_auth    = 3;
    public    $sess_timeout  = 86400;
    public    $token_expires = 7200;
    public    $auth_expires  = 600;
    public    $perm_expires  = 86400;
    public    $cache_expires = 86400;
    public    $bcache_expires= 600;
    public    $cookie_path   = '/';
    public    $languages     = ['ja', 'en'];
    public    $debug         = false;
    public    $logging       = false;
    public    $stash         = [];
    public    $installed     = false;
    public    $init_tags;
    protected $protocol;
    protected $log_path;
    public    $plugin_paths  = [];
    public    $plugin_order  = 0; // 0=asc, 1=desc
    public    $template_paths= [ ALT_TMPL, TMPL_DIR ];
    public    $class_paths   = [];
    public    $components    = [];
    public    $plugin_dirs   = [];
    public    $cfg_settings  = [];
    public    $plugin_switch = [];
    public    $modules       = [];
    public    $cache_driver  = 'Memcached';
    // public    $cache_driver  = null;
    public    $file_mgr      = 'PTFileMgr';
    public    $fmgr;
    public    $worker_period = 600;
    public    $caching       = true;
    public    $remote_ip;
    public    $user;
    public    $appname;
    public    $developer_mode= true;
    public    $site_url;
    public    $site_path;
    public    $use_plugin    = true;
    public    $fiscal_start  = 4;
    public    $base;
    public    $path;
    public    $is_secure;
    public    $document_root;
    public    $request_uri;
    public    $query_string;
    public    $list_async    = false;
    public    $start_time;
    public    $request_id;
    public    $admin_url;
    public    $request_method;
    public    $current_magic;
    public    $pv_redirect   = true;
    public    $temp_dir      = '/tmp';
    protected $errors        = [];
    public    $tmpl_markup   = 'mt';
    public    $delayed       = [];
    public    $hooks         = [];

    public    $registry      = [];

    public    $videos        = ['mov', 'avi', 'qt', 'mp4', 'wmv',
                                '3gp', 'asx', 'mpg', 'flv', 'mkv', 'ogm'];

    public    $images        = ['jpeg', 'jpg', 'png', 'gif', 'jpe'];

    public    $audios        = ['mp3', 'mid', 'midi', 'wav', 'aif', 'aac', 'flac',
                                'aiff', 'aifc', 'au', 'snd', 'ogg', 'wma', 'm4a'];

    protected $methods       = ['view', 'save', 'delete', 'upload', 'save_order',
                                'list_action', 'display_options', 'get_columns_json',
                                'export_scheme', 'recover_password', 'save_hierarchy',
                                'delete_filter', 'edit_image', 'insert_asset',
                                'upload_multi', 'rebuild_phase', 'get_thumbnail',
                                'get_field_html', 'manage_scheme', 'manage_plugins',
                                'preview', 'debug'];

    public    $callbacks     = ['pre_save'     => [], 'post_save'   => [],
                                'pre_delete'   => [], 'post_delete' => [],
                                'save_filter'  => [], 'delete_filter'=> [],
                                'pre_listing'  => [], 'template_source' => [],
                                'template_output'];

    public    $permissions   = ['can_rebuild', 'manage_plugins'];
    public    $disp_option;
    public    $workspace_param;
    public    $return_args   = [];
    public    $core_tags;
    private   $encrypt_key   = 'prototype-default-encrypt-key';
    public    $dynamic_view  = false;
    public    $in_dynamic    = false;
    public    $resetdb_per_rebuild = false;

    static function get_instance() {
        return self::$app;
    }

    function __construct () {
        $this->start_time = microtime( true );
        ini_set( 'memory_limit', -1 );
        $this->request_method = isset( $_SERVER['REQUEST_METHOD'] )
            ? $_SERVER['REQUEST_METHOD'] : '';
        $this->protocol  = isset( $_SERVER['SERVER_PROTOCOL'] )
            ? $_SERVER['SERVER_PROTOCOL'] : '';
        if ( isset( $_SERVER[ 'HTTP_X_FORWARDED_FOR' ] ) ) {
            $this->remote_ip = $_SERVER[ 'HTTP_X_FORWARDED_FOR' ];
        } else if ( isset( $_SERVER[ 'REMOTE_ADDR' ] ) ) {
            $this->remote_ip = $_SERVER[ 'REMOTE_ADDR' ];
        } else {
            $this->remote_ip = 'localhost';
        }
        $secure = !empty( $_SERVER['HTTPS'] ) &&
            strtolower( $_SERVER['HTTPS'] ) !== 'off' ? 's' : '';
        $this->is_secure = $secure ? true : false;
        $base = isset( $_SERVER['SERVER_NAME'] ) 
            ? "http{$secure}://{$_SERVER['SERVER_NAME']}" : null;
        $port = isset( $_SERVER['SERVER_PORT'] ) ? ( int ) $_SERVER['SERVER_PORT'] : null;
        if (! empty( $port ) &&
            $port !== ( $secure === '' ? 80 : 443 ) ) $base .= ":{$port}";
        $request_uri = NULL;
        if ( isset( $_SERVER['HTTP_X_REWRITE_URL'] ) ) {
            $request_uri = $_SERVER['HTTP_X_REWRITE_URL'];
        } else if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $request_uri = $_SERVER['REQUEST_URI'];
        } else if ( isset( $_SERVER['HTTP_X_ORIGINAL_URL'] ) ) {
            $request_uri = $_SERVER['HTTP_X_ORIGINAL_URL'];
            $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'];
        } else if ( isset( $_SERVER['ORIG_PATH_INFO'] ) ) {
            $request_uri = $_SERVER['ORIG_PATH_INFO'];
            if (! empty( $_SERVER['QUERY_STRING'] ) ) {
                $request_uri .= '?' . $_SERVER['QUERY_STRING'];
            }
        }
        if ( isset( $_SERVER['REDIRECT_QUERY_STRING'] ) ) {
            $redirect_query = $_SERVER['REDIRECT_QUERY_STRING'];
            parse_str( $redirect_query, $params );
            foreach ( $params as $key => $value ) {
                $_REQUEST[ $key ] = $value;
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
        if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) )
            $this->language = substr( $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2 );
    }

    function __destruct() {
        if (! empty( $this->hooks ) ) {
            $this->run_hooks( 'post_run' );
        }
        $this->db->db = null;
        unset( $this->db->db );
        $this->db = null;
        unset( $this->db );
    }

    function init ( $dsn = null, $dbuser = null, $dbpasswd = null ) {
        $this->log_path = __DIR__ . DS . 'log' . DS;
        if ( $this->timezone ) date_default_timezone_set( $this->timezone );
        require_once( LIB_DIR . 'PADO' . DS . 'class.pado.php' );
        require_once( LIB_DIR . 'PAML' . DS .'class.paml.php' );
        $cfg = __DIR__ . DS . 'db-config.json.cgi';
        $db = new PADO();
        $ctx = new PAML();
        if ( $this->mode !== 'upgrade' ) {
            $db->logging = true;
            $ctx->logging = true;
        }
        if ( $this->debug ) {
            error_reporting( E_ALL );
        }
        $db->max_packet = 16777216;
        set_error_handler( [ $this, 'errorHandler'] );
        if ( ! $dsn && file_exists( $cfg ) ) {
            $db->configure_from_json( $cfg );
        } else {
            $db->dsn = $dsn;
            if ( $dbuser && $dbpasswd ) {
                $db->dbuser = $dbuser;
                $db->dbpasswd = $dbpasswd;
            }
        }
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
        $db->register_callback( '__any__', 'save_filter', 'save_filter_obj', 100, $this );
        $db->register_callback( '__any__', 'post_save', 'flush_cache', 100, $this );
        $db->register_callback( '__any__', 'post_delete', 'flush_cache', 100, $this );
        $this->db = $db;
        $ctx->include_paths[ ALT_TMPL ] = true;
        $ctx->include_paths[ TMPL_DIR ] = true;
        $ctx->prefix = 'mt';
        $ctx->app = $this;
        $ctx->default_component = $this;
        $ctx->csv_delimiter = ',';
        $ctx->force_compile = true;
        $ctx->cache_driver = $this->cache_driver;
        $ctx->init();
        if ( $driver = $ctx->cachedriver ) {
            $driver->_prefix = $db->dbname . '__';
        }
        $ctx->esc_trans = true;
        require_once( 'class.PTUtil.php' );
        require_once( 'class.PTTags.php' );
        $core_tags = new PTTags;
        $tags = [
            'function'   => ['objectvar', 'include', 'includeparts', 'getobjectname',
                             'assetthumbnail', 'assetproperty', 'getoption', 'statustext',
                             'archivetitle', 'archivedate', 'archivelink'],
            'block'      => ['objectcols', 'objectloop', 'tables', 'nestableobjects',
                             'countgroupby', 'fieldloop', 'archivelist'],
            'conditional'=> ['objectcontext', 'tablehascolumn', 'isadmin',
                             'ifusercan', 'ifworkspacemodel'],
            'modifier'   => ['epoch2str', 'sec2hms', 'trans', 'convert_breaks', '_eval'] ];
        foreach ( $tags as $kind => $arr ) {
            $tag_prefix = $kind === 'modifier' ? 'filter_' : 'hdlr_';
            foreach ( $arr as $tag ) {
                $ctx->register_tag( $tag, $kind, $tag_prefix . $tag, $core_tags );
            }
        }
        require_once( 'class.' . $this->file_mgr . '.php' );
        $this->fmgr = new $this->file_mgr;
        $sth = $db->show_tables( 'table' );
        $table = $sth->fetchColumn();
        $sth = null;
        if (!$table && $this->mode !== 'upgrade' && $this->request_method !== 'POST' ) {
            $this->redirect( $this->admin_url . '?__mode=upgrade&_type=install' );
        }
        if ( $table ) {
            $this->installed = true;
        } else {
            $db->upgrader = true;
            $db->json_model = true;
        }
        $this->core_tags = $core_tags;
        $ctx->vars['script_uri'] = $this->admin_url;
        $ctx->vars['this_mode'] = $this->mode;
        $ctx->vars['languages'] = $this->languages;
        $ctx->vars['request_method'] = $this->request_method;
        $ctx->vars['prototype_path'] = $this->path;
        $ctx->vars['developer_mode'] = $this->developer_mode;
        $lang = $this->language;
        $ctx->language = $lang;
        $this->ctx = $ctx;
        self::$app = $this;
        if ( $this->installed && $this->user() ) {
            $this->language = $this->user()->language;
            $ctx->vars['user_language'] = $this->language;
            $ctx->vars['user_control_border'] = $this->user()->control_border;
        }
        $locale_dir = __DIR__ . DS . 'locale';
        $locale__c = 'phrase' . DS . 'locale_default__c';
        $dict = $this->get_cache( $locale__c );
        if (!$dict ) {
            $locale = $locale_dir . DS . 'default.json';
            if ( file_exists( $locale ) ) {
                $dict = json_decode( file_get_contents( $locale ), true );
                $this->set_cache( $locale__c, $dict );
            }
        }
        $this->dictionary['default'] = $dict ? $dict : [];
        // if ( $this->param( 'setup_db' ) ) {
            // $upgrader = new PTUpgrader;
            // $upgrader->setup_db( true );
        // }
        if ( $lang ) {
            $locale__c = 'phrase' . DS . "locale_{$lang}__c";
            $dict = $this->get_cache( $locale__c );
            if (!$dict ) {
                $locale = $locale_dir . DS . $lang . '.json';
                if ( file_exists( $locale ) ) {
                    $dict = json_decode( file_get_contents( $locale ), true );
                    $this->dictionary[ $lang ] = $dict;
                }
                if ( $this->mode !== 'upgrade' ) {
                    $dictionary =& $this->dictionary;
                    $phrases = $db->model( 'phrase' )->load( ['lang' => $lang ],
                                        null, 'phrase,trans' );
                    foreach ( $phrases as $phrase ) {
                        $dict[ $phrase->phrase ] = $phrase->trans;
                    }
                    $this->set_cache( $locale__c, $dict );
                }
            }
            $this->dictionary[ $lang ] = $dict ? $dict : [];
        }
        if ( $this->mode !== 'upgrade' ) $this->is_login();
        $cfgs = $db->model( 'option' )->load( ['kind' => 'config', null, 'key,value,data'] );
        list( $site_url, $site_path ) = ['', ''];
        $this->stash( 'configs', [] );
        $configs = [];
        foreach ( $cfgs as $cfg ) {
            $colprefix = $cfg->_colprefix;
            $key = $cfg->key;
            $configs[ $key ] = $cfg;
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
            } else if ( $key === 'tmpl_markup' ) {
                $this->tmpl_markup = $cfg->value;
            }
            $ctx->vars['site_url'] = $this->site_url;
            $ctx->vars['site_path'] = $this->site_path;
        }
        $this->stash( 'configs', $configs );
        $this->components['core'] = $this;
        $this->configure_from_json( __DIR__ . DS . 'config.json' );
        if ( $this->use_plugin ) {
            if ( ( $plugin_d = __DIR__ . DS . 'plugins' ) && is_dir( $plugin_d ) )
                $this->plugin_paths[] = $plugin_d;
            $this->init_plugins();
        }
    }

    function init_plugins () {
        $settings = $this->db->model( 'option' )->load( ['kind' => 'plugin'] );
        $plugin_objs = [];
        foreach ( $settings as $setting ) {
            $plugin_objs[ $setting->key ] = $setting;
        }
        $plugin_paths = $this->plugin_paths;
        foreach ( $plugin_paths as $dir ) {
            $items = scandir( $dir, $this->plugin_order );
            foreach ( $items as $plugin ) {
                if ( strpos( $plugin, '.' ) === 0 ) continue;
                $plugin = $dir . DS . $plugin;
                if (! is_dir( $plugin ) ) continue;
                $plugins = scandir( $plugin, $this->plugin_order );
                $register = false;
                $component = null;
                $php_classes = [];
                foreach ( $plugins as $f ) {
                    if ( strpos( $f, '.' ) === 0 ) continue;
                    $_plugin = $plugin . DS . $f;
                    $extension = isset( pathinfo( $_plugin )['extension'] )
                        ? pathinfo( $_plugin )['extension'] : '';
                    if (! is_file( $_plugin ) 
                        && ( $extension !== 'json' && $extension !== 'php' ) ) {
                        continue;
                    }
                    if ( $extension === 'json' ) {
                        $r = json_decode( file_get_contents( $_plugin ), true );
                        $r['plugin_path'] = dirname( $_plugin );
                        $component = strtolower( $r['component'] );
                        if (! isset( $plugin_objs[ $component ] ) ) {
                            $obj = $this->db->model( 'option' )->get_by_key(
                                ['kind' => 'plugin', 'key' => $component ] );
                            $obj->number( 0 );
                            $obj->save();
                            $plugin_objs[ $component ] = $obj;
                        }
                        $this->plugin_switch = $plugin_objs;
                        $setting = $plugin_objs[ $component ];
                        $this->cfg_settings[ $component ] = $r;
                        if (!$setting->number ) continue;
                        $this->configure_from_json( $_plugin, $r );
                    } else if ( $extension === 'php' ) {
                        $php_classes[] = $_plugin;
                    }
                    $register = true;
                }
                foreach ( $php_classes as $_plugin ) {
                    if(!$component ) 
                        $component = strtolower( pathinfo( $_plugin )['filename'] );
                    $this->class_paths[ $component ] = $_plugin;
                }
                if ( $register ) $this->plugin_dirs[] = $plugin;
            }
        }
        // registry hooks and tags.
        $registry = $this->registry;
        if ( isset( $registry['hooks'] ) ) {
            $hooks = $registry['hooks'];
            $plugin_hooks = [];
            foreach ( $hooks as $key => $hook ) {
                $event = key( $hook );
                $regi = $hook[ $event ];
                $priority = isset( $regi['priority'] ) ? (int) $regi['priority'] : 5;
                $event_hooks = isset( $plugin_hooks[ $event ] )
                    ? $plugin_hooks[ $event ] : [];
                $event_hooks[ $priority ] = isset( $event_hooks[ $priority ] )
                                          ? $event_hooks[ $priority ] : [];
                unset( $regi['priority'] );
                $event_hooks[ $priority ][] = $regi;
                $plugin_hooks[ $event ] = $event_hooks;
            }
            $this->hooks = $plugin_hooks;
            unset( $plugin_hooks );
        }
        if ( isset( $registry['tags'] ) ) {
            $tags = $registry['tags'];
            $ctx = $this->ctx;
            foreach ( $tags as $kind => $props ) {
                foreach ( $props as $tag => $func ) {
                    $component = $this->component( $func['component'] );
                    $method = $func['method'];
                    if ( $component && method_exists( $component, $method ) ) {
                        $ctx->register_tag( $tag, $kind, $method, $component );
                    }
                }
            }
        }
    }

    function configure_from_json ( $file, $r = null ) {
        $r = $r !== null ? $r : json_decode( file_get_contents( $file ), true );
        foreach ( $r as $key => $props ) {
            $methods = $r[ $key ];
            if ( $key === 'settings' ) continue;
            if ( is_array( $methods ) ) {
                if ( $key === 'tags' ) {
                    foreach ( $methods as $kind => $props ) {
                        foreach ( $props as $tag => $prop ) {
                            $this->registry['tags'][ $kind ][ $tag ] = $prop;
                        }
                    }
                } else {
                    foreach ( $methods as $meth => $prop ) {
                        $this->registry[ $key ][ $meth ] = $prop;
                    }
                }
            }
        }
        return $r;
    }

    function init_callbacks ( $model, $action ) {
        $app = $this;
        $registry = $app->registry;
        $callbacks = isset( $registry['callbacks'] ) ? $registry['callbacks'] : [];
        if (! isset( $callbacks ) ) return;
        $components = $app->class_paths;
        foreach ( $callbacks as $callback ) {
            $_model = key( $callback );
            if ( $_model !== $model ) continue;
            $callback = $callback[ $model ];
            if ( strpos( key( $callback ), $action ) !== false ) {
                $kind = key( $callback );
                $callback = $callback[ $kind ];
                $component_name = strtolower( $callback['component'] );
                if ( isset( $components[ $component_name ] ) ) {
                    $_plugin = $components[ $component_name ];
                    $plugin = $callback['component'];
                    if (!class_exists( $plugin ) ) 
                        if (!include_once( $_plugin ) )
                            trigger_error( "Plugin '{$_plugin}' load failed!" );
                    if ( class_exists( $plugin ) ) {
                        $component = new $plugin();
                        $app->components[ strtolower( $plugin ) ] = $component;
                        $app->register_callback( $model, $kind, $callback['method'],
                                                 $callback['priority'], $component );
                    }
                }
            }
        }
    }

    function path () {
        return __DIR__;
    }

    function run () {
        $app = $this;
        $ctx = $app->ctx;
        if ( $app->mode === 'upgrade' ) {
            $upgrader = new PTUpgrader;
            return $upgrader->upgrade();
        }
        $mode = $app->mode;
        $registry = $app->registry;
        if (! empty( $app->hooks ) ) {
            $app->run_hooks( 'pre_run' );
        }
        $workspace_id = null;
        $workspace = $app->workspace();
        if ( $workspace ) {
            $workspace_id = $workspace->id;
            $ctx->vars['workspace_scope'] = 1;
            $ctx->vars['workspace_name'] = $workspace->name;
            $ctx->vars['workspace_url'] = $workspace->site_url;
            $ctx->vars['workspace_barcolor'] = $workspace->barcolor;
            $ctx->vars['workspace_bartextcolor'] = $workspace->bartextcolor;
            $ctx->vars['workspace_extra_path'] = $workspace->extra_path;
            $ctx->vars['workspace_id'] = $workspace_id;
        }
        $user = $app->user();
        if ( isset( $registry['methods'] ) && isset( $registry['methods'][ $mode ] ) ) {
            $meth = $registry['methods'][ $mode ];
            $plugin = $meth['component'];
            $method = $meth['method'];
            $requires_login = isset( $meth['requires_login'] )
                ? $meth['requires_login'] : true;
            if ( $requires_login && ! $app->is_login() ) {
                return $app->__mode( 'login' );
            }
            $component = $app->component( $plugin );
            if (!$component ) $component = $app->autoload_component( $plugin );
            if ( method_exists( $component, $method ) ) {
                if ( isset( $meth['permission'] ) && $meth['permission'] ) {
                    if (!$app->can_do( $meth['permission'],
                                        null, null, $workspace ) ) {
                        $app->error( 'Permission denied.' );
                    }
                }
                return $component->$method( $app );
            }
        }
        if ( $app->mode !== 'start_recover' 
             && $app->mode !== 'recover_password' && ! $app->is_login() )
            return $app->__mode( 'login' );
        if ( $model = $app->param( '_model' ) ) {
            $table = $app->get_table( $model );
        }
        $request_id = $app->param( 'request_id' );
        if (!$request_id ) {
            $request_id = $app->magic();
            $app->param( 'request_id', $request_id );
        }
        $app->request_id = $request_id;
        if ( $workspace ) {
            if (!$user->is_superuser ) {
                $permissions = array_keys( $app->permissions() );
                if (! in_array( $workspace_id, $permissions ) ) {
                    $app->return_to_dashboard( ['permission' => 1], true );
                }
            }
        }
        if ( isset( $app->registry['menus'] ) ) {
            $menus = $app->registry['menus'];
            PTUtil::sort_by_order( $menus );
            $system_menus = [];
            $workspace_menus = [];
            foreach ( $menus as $menu ) {
                $component = $app->component( $menu['component'] );
                $permission = isset( $menu['permission'] ) ? $menu['permission'] : null;
                if ( isset( $permission ) ) {
                    if ( isset( $menu['display_space'] ) ) {
                        if (!$app->can_do( $permission, null, null, $workspace ) ) continue;
                    }
                    if ( isset( $menu['display_system'] ) ) {
                        if (!$app->can_do( $permission ) ) continue;
                    }
                }
                $label = $app->translate( $menu['label'], null, $component );
                $item = ['menu_label' => $label, 'menu_mode' => $menu['mode'] ];
                if ( isset( $menu['args'] ) ) {
                    $item['menu_args'] = $menu['args'];
                }
                if ( isset( $menu['display_system'] ) ) {
                    $system_menus[] = $item;
                }
                if ( isset( $menu['display_space'] ) ) {
                    $workspace_menus[] = $item;
                }
            }
            $ctx->vars['system_menus'] = $system_menus;
            $ctx->vars['workspace_menus'] = $workspace_menus;
        }
        if ( $user ) {
            $ctx->vars['user_name'] = $user->name;
            $ctx->vars['user_nickname'] = $user->nickname;
            $ctx->vars['user_id'] = $user->id;
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
        unset( $params['workspace_id'], $params['permission'], $params['id'],
               $params['saved'], $params['deleted'], $params['saved_props'],
               $params['apply_actions'] );
        $app->ctx->vars['query_string'] = http_build_query( $params );
        $app->ctx->vars['raw_query_string'] = $app->query_string( true );
        if ( $return_args = $app->param( 'return_args' ) ) {
            parse_str( $return_args, $app->return_args );
        }
        if ( in_array( $mode, $app->methods ) ) {
            return $app->$mode( $app );
        }
        return $app->__mode( $mode );
    }

    function autoload_component ( $class_name ) {
        $app = $this;
        if ( $component = $app->component( $class_name ) ) {
            return $component;
        } 
        $components = $app->class_paths;
        if ( isset( $components[ strtolower( $class_name ) ] ) ) {
            $_component = $components[ strtolower( $class_name ) ];
            if (! class_exists( $class_name ) && is_readable( $_component ) ) 
                require_once( $_component );
            if ( class_exists( $class_name ) ) {
                $component = new $class_name();
                $app->components[ strtolower( $class_name ) ] = $component;
                return $component;
            }
        }
    }

    function component ( $component ) {
        $components = $this->components;
        if ( isset( $components[ strtolower( $component ) ] ) )
            return $components[ strtolower( $component ) ];
        $component_paths = $this->class_paths;
        if ( isset( $component_paths[ strtolower( $component ) ] ) ) {
            $_plugin = $component_paths[ strtolower( $component ) ];
            if (! class_exists( $component ) ) require_once( $_plugin );
            if ( class_exists( $component ) ) {
                $class = new $component();
                $this->components[ strtolower( $component ) ] = $class;
                return $class;
            }
        }
        foreach ( $components as $class ) {
            if ( is_object( $class ) ) {
                if ( strtolower( get_class( $class ) ) == strtolower( $component ) ) {
                    return $class;
                }
            } else if ( strtolower( $class ) == strtolower( $component ) ) {
                return isset( $components[ $class ] ) ? $components[ $class ] : null;
            }
        }
        return null;
    }

    function register_callback ( $model, $kind, $meth, $priority, $obj = null ) {
        if (!$priority ) $priority = 5;
        $this->callbacks[ $kind ][ $model ][ $priority ][] = [ $meth, $obj ];
    }

    function get_registries ( $model, $name, &$registries = [] ) {
        $app = $this;
        $registry = $app->registry;
        $table = $app->get_table( $model );
        $current_scope = $app->workspace() ? 'workspace' : 'system';
        $plugin_registries = [];
        if ( isset( $registry[ $name ] ) ) {
            $_registries = $registry[ $name ];
            foreach ( $_registries as $props ) {
                $registry_model = key( $props );
                if ( $model != $registry_model ) continue;
                $method = $props[ $model ];
                $prop = $method[ key( $method ) ];
                $order = isset( $prop['order'] ) ? $prop['order'] : 5;
                $order = (int) $order;
                $scope = isset( $prop['scope'] ) ? $prop['scope'] : null;
                if ( $scope ) {
                    if ( is_array( $scope ) && ! in_array( $current_scope, $scope ) ) {
                        continue;
                    } else if ( is_string( $scope ) && $scope != $current_scope ) {
                        continue;
                    }
                }
                $methods = isset( $plugin_registries[ $order ] )
                         ? $plugin_registries[ $order ] : [];
                $methods[] = $method;
                $plugin_registries[ $order ] = $methods;
            }
        }
        if (! empty( $plugin_registries ) ) {
            ksort( $plugin_registries, SORT_NUMERIC );
            $components = $app->class_paths;
            foreach ( $plugin_registries as $methods ) {
                foreach ( $methods as $method ) {
                    $method_name = key( $method );
                    $prop = $method[ $method_name ];
                    $input = isset( $prop['input'] ) ? (int) $prop['input'] : 0;
                    $input_options = isset( $prop['input_options'] ) ? $prop['input_options'] : [];
                    $label = $prop['label'];
                    $plugin = $prop['component'];
                    $_plugin = $components[ strtolower( $plugin ) ];
                    $meth = $prop['method'];
                    $columns = isset( $prop['columns'] ) ? $prop['columns'] : null;
                    if (!include_once( $_plugin ) )
                        trigger_error( "Plugin '{$_plugin}' load failed!" );
                    if ( class_exists( $plugin ) ) {
                        $component = new $plugin();
                        if ( is_string( $input_options ) ) {
                            if ( method_exists( $component, $input_options ) ) {
                                $input_options = $component->$input_options();
                            } else {
                                $input_options = [];
                            }
                        }
                        $label = $app->translate( $label, null, $component );
                        $reg = ['name'  => $method_name,
                                         'input' => $input,
                                         'input_options' => $input_options,
                                         'label' => $label,
                                         'component' => $component,
                                         'component_name' => $plugin,
                                         'method' => $meth ];
                        if ( $columns !== null ) $reg['columns'] = $columns;
                        $registries[] = $reg;
                    }
                }
            }
        }
        $all_registries = [];
        foreach ( $registries as $reg ) {
            $all_registries[ $reg['name'] ] = $reg;
        }
        return $all_registries;
    }

    function __mode ( $mode ) {
        $app = $this;
        if ( $mode === 'logout' ) $app->logout();
        $tmpl = $mode . '.tmpl';
        $ctx = $app->ctx;
        $ctx->vars['this_mode'] = $mode;
        if ( $mode === 'login' ) {
            $app->login();
            $ctx->vars['query_string'] = $app->query_string;
            if ( $app->request_method === 'POST' && ! $app->user() ) {
                $message = $app->translate( 'Login failed: Username or Password was wrong.' );
                $ctx->vars['error'] = $message;
                $name = $app->param('name');
                $faild_user = $app->db->model( 'user' )->get_by_key( ['name' => $name ] );
                $metadata = ['username' => $name,
                             'password' => $app->param('password') ];
                $app->log( ['message'  => $message,
                            'category' => 'login',
                            'model'    => 'user',
                            'object_id'=> $faild_user->id,
                            'metadata' => json_encode( $metadata, JSON_UNESCAPED_UNICODE ),
                            'level'    => 'security'] );
                $cfgs = $app->stash( 'configs' );
                $limit = isset( $cfgs['lockout_limit'] )
                     ? $cfgs['lockout_limit']->value : 0;
                $user_locked_out = false;
                if ( $limit && $faild_user->id ) {
                    $interval = $cfgs['lockout_interval']->value
                         ? $cfgs['lockout_interval']->value : 600;
                    $ts = PTUtil::current_ts( time() - $interval );
                    $terms = ['object_id' => $faild_user->id, 'created_on' => ['>' => $ts ],
                              'level' => 8, 'category' => 'login', 'model' => 'user' ];
                    $faild_login = $app->db->model( 'log' )->count( $terms );
                    if ( $faild_login && $faild_login >= $limit ) {
                        $faild_user->lock_out( 1 );
                        $faild_user->lock_out_on( PTUtil::current_ts( time() ) );
                        $faild_user->save();
                        $user_locked_out = true;
                    }
                }
                $ip_locked_out = false;
                $limit = isset( $cfgs['ip_lockout_limit'] )
                     ? $cfgs['ip_lockout_limit']->value : 0;
                if ( $limit ) {
                    $interval = $cfgs['ip_lockout_interval']->value
                         ? $cfgs['ip_lockout_interval']->value : 600;
                    $ts = PTUtil::current_ts( time() - $interval );
                    $terms = ['remote_ip' => $app->remote_ip, 'created_on' => ['>' => $ts ],
                              'level' => 8, 'category' => 'login', 'model' => 'user'];
                    $faild_login = $app->db->model( 'log' )->count( $terms );
                    if ( $faild_login && $faild_login >= $limit ) {
                        $banned_ip = $app->db->model( 'remote_ip' )->get_by_key(
                            ['ip_address' => $app->remote_ip ] );
                        $banned_ip->class( 'banned' );
                        $app->set_default( $banned_ip );
                        $banned_ip->save();
                        $ip_locked_out = true;
                    }
                }
                if ( $ip_locked_out ) {
                    return $app->redirect( $app->admin_url . '?_lockedout=1&_type=ip' );
                } else if ( $user_locked_out ) {
                    return $app->redirect( $app->admin_url . '?_lockedout=1&_type=user' );
                }
            }
        }
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
        return $app->build_page( $tmpl );
    }

    function user () {
        if ( $this->user ) return $this->user;
        if ( $this->mode === 'upgrade' ) return;
        if ( $this->is_login() ) {
            return $this->user;
        }
        $name = $this->param( 'name' );
        $password = $this->param( 'password' );
        if ( $name && $password ) {
            $two_factor_auth = $this->get_config( 'two_factor_auth' );
            if ( $two_factor_auth && $two_factor_auth->value ) {
                return;
            }
            return $this->login( true );
        }
    }

    function login ( $get = false ) {
        $app = $this;
        $user = null;
        if ( $app->request_method === 'POST' ) {
            $banned_ip = $app->db->model( 'remote_ip' )->get_by_key(
                ['ip_address' => $app->remote_ip, 'class' => 'banned'] );
            if ( $banned_ip->id ) {
                return $app->redirect( $app->admin_url . '?_lockout=1&_type=ip' );
            }
            $return_args = $app->param( 'return_args' );
            if ( strpos( $return_args, '__mode=logout' ) !== false ) $return_args = '';
            if ( strpos( $return_args, '__mode=login' ) !== false ) $return_args = '';
            $two_factor_auth = $app->get_config( 'two_factor_auth' );
            $two_factor_auth = $two_factor_auth ? $two_factor_auth->value : false;
            $token = $app->param( 'token' );
            if ( $two_factor_auth && $token ) {
                $key = $app->param( 'confirmation_code' );
                $user_id = (int) $app->param( 'user_id' );
                $user = $app->db->model( 'user' )->load( ['id' => $user_id, 'status' => 2] );
                if ( empty( $user ) ) {
                    return $app->error( 'Invalid request.' );
                }
                $user = $user[0];
                $sess = $app->db->model( 'session' )->get_by_key( [
                           'name' => $token, 'kind' => 'AU', 'user_id' => $user_id ] );
                if ( $sess->id && $sess->key === $key ) {
                    // Confirmation Code is valid.
                    $return_args = $sess->text;
                    $sess->remove();
                } else {
                    if ( $sess->id ) {
                        if ( $sess->expires < time() ) {
                            return
                              $app->error( 'The confirmation code has been expired.' );
                        }
                        $counter = $sess->value;
                        $counter += 1;
                        $retry_auth = $app->retry_auth ? $app->retry_auth : 3;
                        if ( $counter <= $retry_auth ) {
                            $sess->value( $counter );
                            $sess->save();
                        } else {
                            $sess->remove();
                            return
                              $app->error( 'The number of retry times limit was exceeded,'
                                          .' the confirmation code was discarded.' );
                        }
                    } else {
                        return $app->error( 'Invalid request.' );
                    }
                    $app->ctx->vars['invalid_code'] = 1;
                    $app->assign_params( $app, $app->ctx );
                    $app->language = $user->language;
                    $app->ctx->vars['page_title']
                        = $app->translate( 'Two-factor Authentication' );
                    $app->ctx->vars['token'] = $token;
                    $app->ctx->vars['user_id'] = $user->id;
                    return $app->build_page( 'two_factor_auth.tmpl' );
                    exit();
                }
            } else {
                $name = $app->param( 'name' );
                $password = $app->param( 'password' );
                $user = $app->db->model( 'user' )->load( ['name' => $name, 'status' => 2] );
                if (! empty( $user ) ) {
                    $user = $user[0];
                    if ( $user->lockout ) {
                        return $app->redirect( $app->admin_url . '?_lockout=1&_type=user' );
                    }
                    if ( password_verify( $password, $user->password ) ) {
                        if ( $two_factor_auth ) {
                            $token = $app->magic();
                            $key = rand( 10000, 99999 );
                            $sess = $app->db->model( 'session' )->get_by_key( [
                                                     'name' => $token, 'kind' => 'AU'] );
                            $sess->key( $key );
                            $sess->text( $return_args );
                            $sess->start( time() );
                            $sess->expires( time() + $app->auth_expires );
                            $sess->user_id( $user->id );
                            $sess->value( '0' );
                            $system_email = $app->get_config( 'system_email' );
                            if (!$system_email ) {
                                return $app->error( 'System Email Address is not set in System.' );
                            }
                            $headers = ['From' => $system_email->value ];
                            $subject = $app->translate( 'Your Confirmation Code' );
                            $tmpl = 'email' . DS . 'recover_password.tmpl';
                            $app->ctx->vars['confirmation_code'] = $token;
                            $body = $app->build_page( $tmpl, [], false );
                            if (! PTUtil::send_mail(
                                $user->email, $subject, $body, $headers, $error ) ) {
                                $app->ctx->vars['error'] = $error;
                                return $app->error( $error );
                            }
                            $sess->save();
                            $app->assign_params( $app, $app->ctx );
                            $app->language = $user->language;
                            $app->ctx->vars['page_title']
                                = $app->translate( 'Two-factor Authentication' );
                            $app->ctx->vars['token'] = $token;
                            $app->ctx->vars['user_id'] = $user->id;
                            return $app->build_page( 'two_factor_auth.tmpl' );
                            exit();
                        }
                    } else {
                        return;
                    }
                }
            }
            $remember = $app->param( 'remember' );
            $expires = $app->sess_timeout;
            if ( $remember ) {
                $expires = 60 * 60 * 24 * 365;
            }
            $sess = $app->db->model( 'session' )
                ->get_by_key( ['user_id' => $user->id, 'kind' => 'US'] );
            if (! $sess->name ) {
                $token = $app->magic(); // TODO more secure?
                $sess->name( $token );
            } else {
                $token = $sess->name;
            }
            $sess->expires( time() + $expires );
            $sess->start = ( time() );
            $sess->save();
            if ( $user ) {
                $user->last_login_on( date( 'YmdHis' ) );
                $user->save();
            }
            $path = $app->cookie_path ? $app->cookie_path : $app->path;
            $name = $app->cookie_name;
            $app->bake_cookie( $name, $token, $expires, $path, $remember );
            $return_args = $return_args ? '?' . $return_args : '';
            $app->redirect( $app->admin_url . $return_args. '#__login=1' );
        }
    }

    function logout () {
        $app = $this;
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
        if (!$model ) return;
        $db = $app->db;
        $table = $app->get_table( $model );
        $workspace = $app->workspace();
        $workspace_id = $workspace ? $workspace->id : 0;
        $registry = $app->registry;
        if (!$app->param( 'dialog_view' ) && $workspace ) {
            if (!$table->display_space ) {
                if ( $model !== 'workspace' || 
                    ( $model === 'workspace' && $type === 'list' )
                    || ( $model === 'workspace' && !$app->param('id') ) ) {
                    $app->return_to_dashboard();
                }
            }
        }
        if (!$table ) return $app->error( 'Invalid request.' );
        $tmpl = $type . '.tmpl';
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
        $ctx->vars['has_status'] = $table->has_status;
        $max_status = $app->max_status( $user, $model, $workspace );
        $status_published = $app->status_published( $model );
        $ctx->vars['max_status'] = $max_status;
        $ctx->vars['status_published'] = $status_published;
        $ctx->vars['_default_status'] = $table->default_status;
        if ( $model !== 'template' || $type == 'list' ) {
            $maps = $db->model( 'urlmapping' )->count( ['model' => $model ] );
            if ( $maps ) {
                $ctx->vars['_has_mapping'] = 1;
            }
        }
        if ( $type === 'list' ) {
            if (!$app->param( 'dialog_view' ) ) {
                if (!$app->can_do( $model, 'list', null, $workspace ) ) {
                    $app->return_to_dashboard( ['permission' => 1], true );
                }
            }
            $ctx->vars['page_title'] = $app->translate( 'List of %s', $plural );
            $ctx->vars['menu_type'] = $table->menu_type;
            $list_option = $app->get_user_opt( $model, 'list_option', $workspace_id );
            $list_props = $scheme['list_properties'];
            $column_defs = $scheme['column_defs'];
            $labels = $scheme['labels'];
            $search_props = [];
            $sort_props   = [];
            $filter_props = [];
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
                if ( strpos( $col, 'rev_' ) !== 0 && $prop['type'] !== 'blob' ) {
                    if ( $app->workspace() && $col === 'workspace_id' ) continue;
                    $list_prop = isset( $list_props[ $col ] ) ? $list_props[ $col ] : null;
                    $type = $prop['type'];
                    if ( $list_prop ) {
                        if ( strpos( $list_prop, 'reference:' ) !== false ) {
                            $type = 'reference';
                        }
                    }
                    $filter_props[] = ['name' => $col, 'type' => $type,
                        'label' => $app->translate( $labels[ $col ] ) ];
                }
            }
            if ( $app->param( 'revision_select' ) ) {
                $ctx->vars['page_title'] =
                    $app->translate( 'List Revisions of %s', $label );
                $list_option->number = 0;
                $cols = 'rev_note,rev_diff,rev_changed,rev_type,modified_by,modified_on';
                if (!$app->param( 'dialog_view') ) {
                    $cols = $table->primary . ',' . $cols;
                }
                $list_option->option( $cols );
            } else if (!$list_option->id ) {
                $list_option->number( $app->list_limit );
                $list_option->option( join ( ',', array_keys( $list_props ) ) );
                $list_option->data( join ( ',', array_keys( $search_props ) ) );
            }
            if ( $list_option->number ) {
                $ctx->vars['_per_page'] = (int) $list_option->number;
            }
            $user_options = explode( ',', $list_option->option );
            if (!$list_option->id ) {
                if ( isset( $scheme['default_list_items'] ) && !$app->param( 'revision_select' ) ) {
                    $user_options = $scheme['default_list_items'];
                } else {
                    $option_ws = in_array( 'workspace_id', $user_options ) ? true : false;
                    $max_cols = $app->param( 'dialog_view' ) ? 4 : 7;
                    $user_options = array_slice( $user_options, 0, $max_cols );
                    if ( $option_ws && ! in_array( 'workspace_id', $user_options ) ) {
                        $user_options[] = 'workspace_id';
                    }
                    if ( $table->primary && ! in_array( $table->primary, $user_options ) ) {
                        array_unshift( $user_options, $table->primary );
                    }
                }
            }
            $obj = $db->model( $model );
            $_colprefix = $obj->_colprefix;
            if ( $app->workspace() && in_array( 'workspace_id', $user_options ) ) {
                $search = array_search( 'workspace_id',  $user_options );
                $split = array_splice( $user_options, $search, 1 );
            }
            if ( $app->param( 'revision_select' ) ) {
                if (! in_array( 'rev_diff', $user_options ) ) $user_options[] = 'rev_diff';
                if (! in_array( 'rev_note', $user_options ) ) $user_options[] = 'rev_note';
                if ( $obj->has_column( 'modified_on' ) ) {
                    if (! in_array( 'modified_on', $user_options ) )
                        $user_options[] = 'modified_on';
                }
            } else {
                unset( $list_props['rev_diff'], $list_props['rev_note'],
                       $list_props['rev_changed'] );
                if ( in_array( 'rev_diff', $user_options ) ) {
                    $search = array_search( 'rev_diff',  $user_options );
                    $split = array_splice( $user_options, $search, 1 );
                }
                if ( in_array( 'rev_note', $user_options ) ) {
                    $search = array_search( 'rev_note',  $user_options );
                    $split = array_splice( $user_options, $search, 1 );
                }
            }
            $app->disp_option = $user_options;
            $sorted_props = [];
            $select_cols  = [];
            $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];
            foreach ( $list_props as $col => $prop ) {
                $user_opt = in_array( $col, $user_options ) ? 1 : 0;
                $sorted_props[ $col ] = [ $app->translate( $labels[ $col ] ), $user_opt, $prop ];
                if (! isset( $relations[ $col ] ) ) {
                    $select_cols[] = $col;
                }
            }
            $search_cols = explode( ',', $list_option->data );
            foreach ( $search_props as $col => $prop ) {
                $user_opt = in_array( $col, $search_cols ) ? 1 : 0;
                $search_props[ $col ] = [ $app->translate( $labels[ $col ] ), $user_opt ];
            }
            $sort_option = explode( ',', $list_option->extra );
            if (!$list_option->extra && isset( $scheme['sort_by'] ) ) {
                $sort_option = [];
                $sort_option[] = key( $scheme['sort_by'] );
                $sort_option[] = $scheme['sort_by'][ key( $scheme['sort_by'] ) ];
            }
            $has_primary = false;
            foreach ( $sort_props as $col => $prop ) {
                $user_opt = ( $sort_option && $sort_option[0] === $col ) ? 1 : 0;
                $sort_props[ $col ] = [ $app->translate( $labels[ $col ] ), $user_opt ];
            }
            $ascend = isset( $sort_option[1] ) && $sort_option[1] == 'ascend' ? 1 : 0;
            $descend = isset( $sort_option[1] ) && $sort_option[1] == 'descend' ? 1 : 0;
            $order_props = ['1' => [ $app->translate( 'Ascend' ), $ascend ],
                            '2' => [ $app->translate( 'Descend' ), $descend ] ];
            $ctx->vars['disp_options']   = $sorted_props;
            $ctx->vars['search_options'] = $search_props;
            $ctx->vars['sort_options']   = $sort_props;
            $ctx->vars['order_options']  = $order_props;
            $ctx->vars['filter_options'] = $filter_props;
            if (!$app->param( 'revision_select' ) ) {
                $actions_class = new PTListActions();
                $list_actions = [];
                $actions_class->get_list_actions( $model, $list_actions );
                if (! empty( $list_actions ) ) $ctx->vars['list_actions'] = $list_actions;
                $filters_class = new PTSystemFilters();
                $system_filters = [];
                $filters_class->get_system_filters( $model, $system_filters );
                if (! empty( $system_filters ) )
                    $ctx->vars['system_filters'] = $system_filters;
            }
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
            $terms = [];
            if ( $q = $app->param( 'query' ) ) {
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
                        if (!$counter ) {
                            $conditions[] = ['LIKE' => $s ];
                        } else {
                            $conditions[] = ['LIKE' => ['or' => $s ] ];
                        }
                        $counter++;
                    }
                } else {
                    $conditions = ['LIKE' => $db->escape_like( $q, 1, 1 ) ];
                }
                foreach ( $cols as $col ) {
                    if ( $obj->has_column( $col ) )
                        $terms[ $col ] = $conditions;
                }
            }
            $extra = null;
            if ( $ws = $app->workspace() ) {
                if ( isset( $column_defs['workspace_id'] ) ) {
                    $ws_id = (int) $ws->id;
                    $extra = " AND {$_colprefix}workspace_id={$ws_id}";
                }
            }
            if ( $table->revisable ) {
                if ( $rev_object_id = $app->param( 'rev_object_id' ) ) {
                    $terms['rev_object_id'] = (int) $rev_object_id;
                    $app->return_args['revision_select'] = 1;
                    $app->return_args['rev_object_id'] = $rev_object_id;
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
            $app->register_callback( $model, 'pre_listing', 'pre_listing', 1, $app );
            $app->init_callbacks( $model, 'pre_listing' );
            $callback = ['name' => 'pre_listing',
                         'scheme' => $scheme, 'table' => $table ];
            $app->run_callbacks( $callback, $model, $terms, $args, $extra );
            if ( $table->revisable ) {
                if (!$app->param( 'rev_object_id' ) ) {
                    $extra .= " AND {$_colprefix}rev_type=0";
                }
            }
            if ( $get_col = $app->param( 'get_col' ) ) {
                if (! in_array( $get_col, $user_options ) ) {
                    array_unshift( $user_options, $get_col );
                }
            }
            $orig_user_options = $user_options;
            if (! in_array( 'id', $user_options ) ) {
                array_unshift( $user_options, 'id' );
            }
            if ( $table->has_status ) {
                if (! in_array( 'status', $user_options ) ) $user_options[] = 'status';
                if ( isset( $scheme['options'] ) && isset( $scheme['options']['status'] ) ) {
                    $ctx->vars['_status_options'] = $scheme['options']['status'];
                }
            }
            $select_cols = [];
            foreach ( $user_options as $_col ) {
                if (! isset( $relations[ $_col ] ) ) {
                    $select_cols[] = $_col;
                }
            }
            $select_cols = join( ',', $select_cols );
            if ( $app->param( 'revision_select' ) && $obj->has_column( 'modified_on' ) ) {
                $args['sort'] = 'modified_on';
            }
            if ( $app->list_async && !$app->param( 'to_json' ) ) {
                $objects = [ $obj ];
            } else {
                $objects = $obj->load( $terms, $args, $select_cols, $extra );
            }
            unset( $args['limit'], $args['offset'] );
            $count = $app->param( 'totalResult' )
                   ? $app->param( 'totalResult' )
                   : $obj->count( $terms, $args, $select_cols, $extra );
            $items = [];
            foreach ( $objects as $_obj ) {
                $items[] = PTUtil::object_to_resource( $_obj, 'list', $user_options );
            }
            if ( $app->param( 'to_json' ) ) {
                header( 'Content-type: application/json' );
                $json = ['totalResult' => (int) $count, 'items' => $items ];
                echo json_encode( $json );
                exit();
            }
            $list_cols = [];
            $has_primary = false;
            foreach ( $user_options as $option ) {
                if (! isset( $list_props[ $option ] ) ) continue;
                if ( $list_props[ $option ] == 'primary' ) $has_primary = true;
                $col_props = ['_name' => $option,
                              '_label' => $app->translate( $labels[ $option ] ),
                              '_list' => $list_props[ $option ],
                              '_type' => $column_defs[ $option ]['type'] ];
                if ( isset( $scheme['options'] ) && isset( $scheme['options'][ $option ] ) ) {
                    $col_props['_options'] = $scheme['options'][ $option ];
                }
                $list_cols[] = $col_props;
            }
            if (!$has_primary && isset( $list_cols[1] ) ) {
                $list_cols[1]['_list'] = 'primary';
            } else if (!$has_primary ) {
                $ctx->vars['_no_primary'] = 1;
            }
            $ctx->vars['show_cols'] = $orig_user_options;
            $ctx->vars['list_cols'] = $list_cols;
            $ctx->vars['list_colspan'] = count( $list_cols ) + 1;
            $filter_params = ['terms' => $terms, 'args' => $args, 'extra' => $extra ];
            $filter_params = json_encode( $filter_params );
            $ctx->vars['filter_params'] = $app->encrypt( $filter_params );
            $ctx->vars['list_items'] = $items;
            $ctx->vars['object_count']  = $count;
            $ctx->vars['totalResult']   = $count;
            $ctx->vars['list_limit']    = $limit;
            $ctx->vars['list_offset']   = $offset;
            $ctx->vars['_has_deadline'] = $obj->has_column( 'has_deadline' );
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
            $obj = $db->model( $model )->new();
            $user_option = $app->get_user_opt( $model, 'edit_option', $workspace_id );
            if (! $user_option->id ) {
                $_properties = $scheme[ $type . '_properties'];
                $display_options = array_keys( $scheme[ $type . '_properties'] );
                foreach ( $_properties as $col => $prop ) {
                    if ( $col === 'id' || $prop !== 'hidden' ) {
                        $display_options[] = $col;
                    }
                }
                $fields = PTUtil::get_fields( $obj, 'displays' );
                $all_fields = PTUtil::get_fields( $obj, 'basenames' );
                array_walk( $fields, function( &$field ) { $field = 'field-' . $field; } );
                array_walk( $all_fields, function( &$field ) { $field = 'field-' . $field; } );
                $field_sort_order = array_merge( $display_options, $all_fields );
                $display_options = array_merge( $display_options, $fields );
                $field_sort_order = array_unique( array_diff( $field_sort_order, ['id'] ) );
                $ctx->vars['_field_sort_order'] = join( ',', $field_sort_order );
            } else {
                $display_options = explode( ',', $user_option->option );
                $ctx->vars['_field_sort_order'] = $user_option->data;
            }
            $ctx->vars['display_options'] = $display_options;
            $ctx->vars['_auditing'] = $table->auditing;
            $ctx->vars['_revisable'] = $table->revisable;
            $ctx->vars['_assign_user'] = $table->assign_user;
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
                if (!$id ) return $app->error( 'Invalid request.' );
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
                    if ( $model === 'template' ) {
                        if ( $obj->id ) {
                            $cache_key = 'template' . DS . $obj->id . DS . 'include_modules__c';
                            $includes = $app->get_cache( $cache_key );
                            if (!$includes ) {
                                $text = $obj->text;
                                if ( stripos( $text, 'include' ) !== false ) {
                                    $app->init_tags();
                                    $ctx->stash( 'current_template', $obj );
                                    $__stash = $ctx->__stash;
                                    $local_vars = $ctx->local_vars;
                                    $vars = $ctx->vars;
                                    $app->tmpl_markup === 'mt' ? $ctx->build( $text )
                                              : $app->build( $text, $ctx );
                                    $ctx->vars = $vars;
                                    $ctx->local_vars = $local_vars;
                                    $ctx->__stash = $__stash;
                                    $includes = array_values( $app->modules );
                                    $ctx->vars['_include_modules'] = $includes;
                                    $app->set_cache( $cache_key, $includes );
                                }
                            } else {
                                $ctx->vars['_include_modules'] = $includes;
                            }
                        }
                        $ctx->vars['_has_mapping'] = 1;
                    }
                } else {
                    $app->return_to_dashboard();
                }
            } else {
                $ctx->vars['page_title'] = $app->translate( 'New %s', $label );
                $obj = $db->model( $model )->new();
                if ( $obj->has_column( 'published_on' ) ) {
                    $obj->published_on( date( 'YmdHis' ) );
                }
                if ( $obj->has_column( 'user_id' ) ) {
                    $obj->user_id( $app->user()->id );
                }
                if ( $obj->has_column( 'workspace_id' ) ) {
                    $obj->workspace_id( $workspace_id );
                }
                $ctx->stash( 'object', $obj );
            }
            if (!$app->can_do( $model, $type, $obj ) ) {
                $app->error( 'Permission denied.' );
            }
            $ctx->vars['can_create'] = 1;
            $ctx->stash( 'current_context', $model );
            if ( $app->get_permalink( $obj, true ) ) {
                $ctx->vars['has_mapping'] = 1;
            }
            $ctx->vars['screen_id'] = $screen_id ? $screen_id : $app->magic();
        } else if ( $type === 'hierarchy' ) {
            if (!$app->can_do( $model, 'edit' ) ) {
                $app->error( 'Permission denied.' );
            }
            $ctx->vars['page_title'] = $app->translate( 'Manage %s Hierarchy', $plural );
            if ( $app->param( 'saved_hierarchy' ) ) {
                $ctx->vars['header_alert_message'] =
                    $app->translate( '%s hierarchy saved successfully.', $plural );
            }
        }
        $ctx->vars['return_args'] = http_build_query( $app->return_args );
        return $app->build_page( $tmpl );
    }

    function build ( $text, $ctx = null ) {
        $app = $this;
        $ctx = $ctx ? $ctx : $app->ctx;
        $tmpl_markup = $app->tmpl_markup;
        if ( $tmpl_markup === 'mt' ) {
            return $ctx->build( $text );
        } else if ( $tmpl_markup === 'smarty' ) {
            list ( $pfx, $ldelim, $rdelim ) = [ $ctx->prefix, $ctx->ldelim, $ctx->rdelim ];
            $ctx->prefix = '';
            $ctx->ldelim = '{';
            $ctx->rdelim = '}';
            $ctx->inited = false;
            $ctx->tag_block = ['{', '}'];
            $build = $ctx->build( $text );
            list( $ctx->prefix, $ctx->ldelim, $ctx->rdelim ) = [ $pfx, $ldelim, $rdelim ];
            $ctx->tag_block = [ $ctx->ldelim, $ctx->rdelim ];
            return $build;
        }
    }

    function build_page ( $tmpl, $param = [], $output = true ) {
        $app = $this;
        if (!$app->debug && $output ) {
            header( 'Cache-Control: no-store, no-cache, must-revalidate' );
            header( 'Cache-Control: post-check=0, pre-check=0', FALSE );
            header( 'Content-type: text/html' );
        }
        if (! isset( $app->ctx->vars['appname'] ) ) {
            $app->ctx->vars['appname'] = $app->get_config( 'appname' )->value;
        }
        $tmpl = $app->ctx->get_template_path( $tmpl );
        if (!$tmpl ) return;
        $src = file_get_contents( $tmpl );
        $cache_id = null;
        $callback = ['name' => 'template_source', 'template' => $tmpl ];
        $basename = pathinfo( $tmpl, PATHINFO_FILENAME );
        $app->run_callbacks( $callback, $basename, $param, $src );
        $out = $app->ctx->build_page( $tmpl, $param, $cache_id, false, $src );
        $callback = ['name' => 'template_output', 'template' => $tmpl ];
        $app->run_callbacks( $callback, $basename, $param, $src, $out );
        if (!$output ) return $out;
        if ( $app->debug ) {
            $ctx = new PAML;
            $ctx->include_paths[ ALT_TMPL ] = true;
            $ctx->include_paths[ TMPL_DIR ] = true;
            $ctx->prefix = 'mt';
            $time = microtime( true );
            $processing_time = $time - $this->start_time;
            $debug_tmpl = TMPL_DIR . 'include' . DS . 'footer_debug.tmpl';
            $ctx->vars['processing_time'] = round( $processing_time, 2 );
            $ctx->vars['debug_mode'] = is_int( $app->debug ) ? $app->debug : 1;
            $ctx->vars['queries'] = $app->db->queries;
            $ctx->vars['query_count'] = count( $app->db->queries );
            $ctx->vars['db_errors'] = $app->db->errors;
            $ctx->vars['errors'] = $app->errors;
            $ctx->vars['query_string'] = $app->query_string( true, true );
            $debug = $ctx->build( file_get_contents( $debug_tmpl ) );
            $out = preg_replace( '!<\/body>!', $debug . '</body>', $out );
        }
        if (!$app->debug && $output ) {
            $file_size = strlen( bin2hex( $out ) ) / 2;
            header( "Content-Length: {$file_size}" );
        }
        echo $out;
        exit();
    }

    function list_action ( $app ) {
        $actions_class = new PTListActions();
        return $actions_class->list_action( $app );
    }

    function encrypt ( $text, $key = '', $meth = 'AES-128-ECB' ) {
        if (!$key ) $key = $this->encrypt_key;
        return bin2hex( openssl_encrypt( $text, $meth, $key ) );
    }

    function decrypt ( $text, $key = '', $meth = 'AES-128-ECB' ) {
        if (!$key ) $key = $this->encrypt_key;
        return openssl_decrypt( hex2bin( $text ), $meth, $key );
    }

    function get_permalink ( $obj, $has_map = false, $rebuild = true ) {
        $app = $this;
        $table = $app->get_table( $obj->_model );
        if ( $obj->_model == 'asset' ) {
            $url = $app->db->model( 'urlinfo' )->load( [
                'model' => 'asset', 'object_id' => $obj->id, 'class' => 'file'] );
            if ( is_array( $url ) ) {
                $url = $url[0];
                return $url->url;
            }
            return false;
        }
        $terms = ['model' => $obj->_model ];
        if ( $has_map && $obj->_model === 'template' ) {
            $terms['template_id'] = $obj->id;
            unset( $terms['model'] );
        }
        $args = ['sort_by' => 'order', 'direction' => 'ascend', 'limit'=> 1];
        $cache_key = 'urlmapping_cache_' . $this->make_cache_key( $terms, $args );
        $urlmapping = $app->stash( $cache_key ) ? $app->stash( $cache_key )
                    : $app->db->model( 'urlmapping' )->load( $terms, $args );
        $app->stash( $cache_key, $urlmapping );
        if (! empty( $urlmapping ) ) {
            $urlmapping = $urlmapping[0];
            if ( $has_map ) return $urlmapping;
            $terms = ['urlmapping' => $urlmapping->id, 'model' => $table->name,
                      'class' => 'archive', 'object_id' => $obj->id ];
            $cache_key = 'urlmapping_cache_' . $this->make_cache_key( $terms );
            $ui = $app->stash( $cache_key ) ? $app->stash( $cache_key )
                : $app->db->model( 'urlinfo' )->get_by_key( $terms );
            $app->stash( $cache_key, $ui );
            if ( $ui ) {
                return $ui->url;
            } else {
                if (!$rebuild )
                return $app->build_path_with_map( $obj, $urlmapping, $table, null, true );
            }
        }
        return false;
    }

    function make_cache_key ( $arr1, $arr2 = null, $prefix = '' ) {
        ob_start();
        print_r( $arr1 );
        print_r( $arr2 );
        $res = ob_get_clean();
        $res = md5( $res );
        return $prefix ? "{$prefix}_$res" : $res;
    }

    function max_status ( $user, $model, $workspace = null ) {
        $app = $this;
        $workspace = $workspace ? $workspace : $app->workspace();
        $workspace_id = $workspace ? $workspace->id : 0;
        $table = $app->get_table( $model );
        $max_status = 1;
        if ( $user->is_superuser ) {
            $max_status = 5;
        } else {
            $permissions = $app->permissions();
            $perms = isset( $permissions[ $workspace_id ] )
                   ? $permissions[ $workspace_id ] : [];
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

    function can_do ( $model, $action = null, $obj = null, $workspace = null  ) {
        $app = $this;
        $user = $app->user();
        $workspace = $app->workspace();
        if (!$user ) return false;
        $orig_action = $action;
        if (!$action && strpos( $model, '_' ) !== false )
            list( $action, $model ) = explode( '_', $model );
        if ( ( $model == 'rebuild' || $model == 'plugins' ) && is_object( $action ) ) {
            if ( $action->_model == 'workspace' ) {
                $workspace = $action;
            }
        }
        if ( $model === 'superuser' ) return $app->user()->is_superuser;
            if ( $model !== 'rebuild' && $model !== 'plugins' ) {
            $table = $app->get_table( $model );
            if ( $app->mode !== 'list_action' && $app->mode !== 'get_thumbnail' ) {
                if (!$app->workspace() && ( $obj && ! $obj->workspace ) ) {
                    if ( $table->space_child && $action === 'edit' ) {
                        return false;
                    } else if ( $action === 'list' && !$table->display_system ) {
                        return false;
                    }
                }
            }
        }
        if ( $user->is_superuser ) return true;
        if ( $model == 'user' ) {
            if ( $obj && $obj->id == $app->user()->id ) return true;
        }
        $permissions = $app->permissions();
        if (!$workspace ) {
            if ( $obj && $obj->has_column( 'workspace_id' ) ) {
                $workspace = $obj->workspace;
            }
        }
        $ws_perms = ( $workspace && isset( $permissions[ $workspace->id ] ) )
                  ? $permissions[ $workspace->id ] : [];
        if ( $workspace ) {
            $perms = $ws_perms;
            if ( in_array( 'workspace_admin', $perms ) ) {
                return true;
            }
        } else {
            $perms = isset( $permissions[0] ) ? $permissions[0] : [];
        }
        $_perms = $app->permissions;
        if ( $orig_action && in_array( $orig_action, $_perms ) ) {
            if ( $workspace ) {
                return in_array( $orig_action, $perms );
            } else {
                return isset( $permissions[0] )
                        && isset( $permissions[0][ $orig_action ] ) ? true : false;
            }
        } else if ( $action == 'list' ) {
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
        } else if ( $action === 'create'
                || $action === 'edit' || $action === 'save' || $action === 'delete' ) {
            list( $name, $range ) = ['', ''];
            if (!$obj || ! $obj->id ) {
                $name = 'can_create_' . $model;
                if ( in_array( $name, $perms ) ) {
                    return true;
                }
            } else {
                $range = 'can_update_all_' . $model;
                if ( $obj->has_column( 'status' ) ) {
                    $max_status = $app->max_status( $app->user(), $model, $workspace );
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
        $app = $this;
        $user = $app->user();
        if (!$user ) return [];
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
            if ( $role->workspace_admin ) {
                $ws_permission[] = 'workspace_admin';
            }
            if ( $role->can_rebuild ) {
                $ws_permission[] = 'can_rebuild';
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
        $app = $this;
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
        if (!$app->can_do( $model, 'edit' ) ) {
            $app->error( 'Permission denied.' );
        }
        if (! $model ) return $app->error( 'Invalid request.' );
        $table = $app->get_table( $model );
        if (! $table ) return $app->error( 'Invalid request.' );
        $objects = $app->get_object( $model );
        if (! is_array( $objects ) && is_object( $objects ) ) {
            $objects = [ $objects ];
        }
        $table = $app->get_table( $model );
        $error = false;
        $db = $app->db;
        $db->begin_work();
        foreach ( $objects as $obj ) {
            $order = $app->param( 'order_' . $obj->id );
            $order = (int) $order;
            $obj->order( $order );
            if (! $obj->save() ) $error = true;
        }
        if ( $error ) {
            $errstr = $app->translate( 'An error occurred while saving %s.',
                      $app->translate( $table->label ) );
            $app->rollback( $errstr );
        } else {
            $db->commit();
        }
        $app->redirect( $app->admin_url .
            "?__mode=view&_type=list&_model={$model}&saved_order=1"
                                                . $app->workspace_param );
    }

    function save_hierarchy ( $app ) {
        $model = $app->param( '_model' );
        $app->validate_magic();
        if (! $model ) return $app->error( 'Invalid request.' );
        $table = $app->get_table( $model );
        if (! $table ) return $app->error( 'Invalid request.' );
        $workspace_id = $app->param( 'workspace_id' );
        $_nestable_output = $app->param( '_nestable_output' );
        $children = json_decode( $_nestable_output, true );
        if (!$app->can_do( $model, 'edit' ) ) {
            return $app->error( 'Permission denied.' );
        }
        $order = 1;
        $error = false;
        $db = $app->db;
        $db->begin_work();
        $app->set_hierarchy( $model, $children, 0, $order, $error );
        if ( $error ) {
            $errstr = $app->translate( 'An error occurred while saving %s.',
                      $app->translate( $table->label ) );
            $app->rollback( $errstr );
        } else {
            $db->commit();
        }
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

    function manage_scheme ( $app ) {
        $upgrader = new PTUpgrader;
        return $upgrader->manage_scheme( $app );
    }

    function manage_plugins ( $app ) {
        $plugin = new PTPlugin;
        return $plugin->manage_plugins( $app );
    }

    function delete_filter ( $app ) {
        $filter = new PTSystemFilters;
        return $filter->delete_filter( $app );
    }

    function insert_asset ( $app ) {
        $ctx = $app->ctx;
        if ( $app->param( 'insert_editor' ) ) {
            $ids = $app->param( 'id' );
            $assets = [];
            $insert_assets = [];
            foreach ( $ids as $id ) {
                $id = (int) $id;
                $asset = $app->db->model( 'asset' )->load( $id );
                if ( $asset ) {
                    $assets[] = $asset;
                    $insert_assets[ $id ] = $asset;
                }
            }
            $ctx->stash( 'loop_objects', $assets );
            if ( $app->param( 'do_insert' ) ) {
                $loop_vars = [];
                foreach ( $ids as $id ) {
                    $id = (int) $id;
                    $obj = $insert_assets[ $id ];
                    $urlinfo = $app->db->model( 'urlinfo' )->get_by_key( [
                                                'object_id' => $id, 'model' => 'asset',
                                                'key' => 'file' ] );
                    $url = $urlinfo->url;
                    $assetproperty = $app->get_assetproperty( $obj, 'file' );
                    $label = $app->param( 'asset-label-' . $id );
                    $save_label = $app->param( 'save-label-' . $id );
                    if ( $save_label ) {
                        $obj->label( $label );
                        $obj->save();
                    }
                    $class = $obj->class;
                    if ( $class == 'image' ) {
                        $width = (int) $app->param( 'thumb-width-' . $id );
                        $height = $assetproperty['image_height'];
                        if ( $assetproperty['image_width'] != $width ) {
                            $orig_width = $assetproperty['image_width'];
                            $scale = $width / $orig_width;
                            $height = round( $height * $scale );
                        }
                        $height = (int) $height;
                        $use_thumb = $app->param( 'use-thumb-' . $id );
                        if ( $use_thumb ) {
                            $args = ['width' => $width ];
                            $url = PTUtil::thumbnail_url( $obj, $args, $assetproperty );
                        }
                        $align = $app->param( 'insert-align-' . $id );
                        $loop_vars[] = ['align' => $align, 'width' => $width,
                                        'class' => $obj->class, 'height' => $height,
                                        'url' => $url, 'label' => $label ];
                    } else {
                        $file_size = $assetproperty['file_size'];
                        $loop_vars[] = ['url' => $url, 'label' => $label,
                                        'file_size' => $file_size,
                                        'class' => $obj->class ];
                    }
                }
                $ctx->vars['insert_loop'] = $loop_vars;
            }
        }
        $class = $app->param( '_system_filters_option' );
        if (!$class ) $class = 'Asset';
        $tmpl = 'insert_asset.tmpl';
        $class = $app->translate( $app->translate( $class, '', $app, 'default' ) );
        $ctx->vars['page_title'] = $app->translate( 'Insert %s', $class );
        $ctx->vars['this_mode'] = $app->mode;
        $app->assign_params( $app, $ctx );
        return $app->build_page( $tmpl );
    }

    function edit_image ( $app ) {
        $db = $app->db;
        $user = $app->user();
        $fmgr = $app->fmgr;
        $model = $app->param( '_model' );
        $id = (int) $app->param( 'id' );
        $obj = $db->model( $model )->new();
        if ( $id ) {
            $obj = $obj->load( $id );
            if (!$obj ) {
                return $app->error( 'Invalid request.' );
            }
            if (!$app->can_do( $model, 'edit', $obj ) ) {
                $app->error( 'Permission denied.' );
            }
        } else {
            if (!$app->can_do( $model, 'edit' ) ) {
                $app->error( 'Permission denied.' );
            }
        }
        $ctx = $app->ctx;
        $screen_id = $app->param( '_screen_id' );
        $tmpl = 'edit_image.tmpl';
        $key = $app->param( 'view' );
        $assetproperty = [];
        $session_name = $screen_id . '-' . $key;
        $session = $db->model( 'session' )->get_by_key(
            ['name' => $session_name, 'user_id' => $user->id ]
        );
        if (!$session->id && $obj->id ) {
            $assetproperty = $app->get_assetproperty( $obj, $key );
        } else {
            $json = $session->text;
            $assetproperty = json_decode( $json, true );
        }
        if ( empty( $assetproperty  ) ) {
            return $app->error( 'Invalid request.' );
        }
        $ctx->vars['image_width'] = $assetproperty['image_width'];
        $ctx->vars['image_height'] = $assetproperty['image_height'];
        $ctx->vars['page_title'] = $app->translate( 'Edit Image' );
        $key = htmlspecialchars( $key );
        $model = htmlspecialchars( $model );
        $screen_id = htmlspecialchars( $screen_id );
        if ( $app->request_method === 'POST' ) {
            $app->validate_magic();
            $image_data = $app->param( 'image_data' );
            if ( preg_match( '/^data:(.*?);base64,(.*$)/', $image_data, $matchs ) ) {
                $mime_type = $matchs[1];
                $image_data = base64_decode( $matchs[2] );
                $max_scale= 256;
                $meta = explode( '/', $mime_type );
                $extension = $meta[1];
                $upload_dir = $app->upload_dir();
                $file = $upload_dir . DS . 'tmpimg.' . $extension;
                $fmgr->put( $file, $image_data );
                list( $width, $height ) = getimagesize( $file );
                $width--;
                $height--;
                $orig_width = $app->param( 'orig_width' );
                $orig_height = $app->param( 'orig_height' );
                $image_data = $fmgr->get( $file );
                $size = filesize( $file ); // TODO $fmgr is not local
                list( $width, $height ) = getimagesize( $file );
                $_FILES = [];
                $_FILES['files'] = ['name' => basename( $file ), 'type' => $mime_type,
                        'tmp_name' => $file, 'error' => 0, 'size' => filesize( $file ) ];
                $image_versions = [
                ''          => ['auto_orient' => true ],
                'thumbnail' => ['max_width' => $max_scale, 'max_height' => $max_scale ] ];
                $options = ['upload_dir' => $upload_dir . DS,
                            'prototype' => $app, 'print_response' => false,
                            'no_upload' => true, 'image_versions' => $image_versions ];
                $upload_handler = new UploadHandler( $options );
                $_SERVER['CONTENT_LENGTH'] = filesize( $file );
                $upload_handler->post( false );
                $thumbnail = $upload_dir . DS . 'thumbnail' . DS . basename( $file );
                $image_data_thumb = $fmgr->get( $thumbnail );
                $max_scale = $max_scale / 2;
                $file = preg_replace( "/\.$extension$/", "-square.{$extension}", $file );
                $fmgr->put( $file, $image_data );
                $_FILES['files'] = ['name' => basename( $file ), 'type' => $mime_type,
                        'tmp_name' => $file, 'error' => 0, 'size' => filesize( $file ) ];
                $image_versions = [
                ''          => ['auto_orient' => true ],
                'thumbnail' => ['max_width' => $max_scale, 'max_height' => $max_scale ] ];
                $image_versions['thumbnail']['crop'] = true;
                $options = ['upload_dir' => $upload_dir . DS,
                            'prototype' => $app, 'print_response' => false,
                            'no_upload' => true, 'image_versions' => $image_versions ];
                $upload_handler = new UploadHandler( $options );
                $_SERVER['CONTENT_LENGTH'] = filesize( $file );
                $upload_handler->post( false );
                $thumbnail_square = $upload_dir . DS . 'thumbnail' . DS . basename( $file );
                $image_data_square = $fmgr->get( $thumbnail_square );
                $screen_id .= '-' . $key;
                $session = $db->model( 'session' )->get_by_key(
                    ['name' => $screen_id, 'user_id' => $user->id, 'kind' => 'UP' ]
                );
                $assetproperty = json_decode( $session->text, 'true' );
                $session->data( $image_data );
                $session->metadata( $image_data_thumb );
                $session->extradata( $image_data_square );
                $thumb_dir = dirname( $thumbnail );
                $fmgr->unlink( $thumbnail );
                $fmgr->unlink( $thumbnail_square );
                $fmgr->delete( $thumb_dir );
                $fmgr->delete( $upload_dir );
                $props = [];
                if ( $session->id ) {
                    $props = $session->text;
                    if ( $props ) $props = json_decode( $props, true );
                } else {
                    if ( $obj->id ) {
                        $meta = $db->model( 'meta' )->get_by_key(
                                ['model' => $model, 'object_id' => $obj->id,
                                 'kind' => 'metadata', 'key' => $key ] );
                        if ( $meta->id ) {
                            $props = $meta->text;
                            $props = json_decode( $props, true );
                        }
                    }
                }
                if ( empty( $props ) ) {
                    return $app->error( 'Invalid request.' );
                }
                $props['file_size'] = $size;
                $props['image_width'] = $width;
                $props['image_height'] = $height;
                $ts = date( 'Y-m-d H:i:s' );
                $props['uploaded'] = $ts;
                $props['mime_type'] = $mime_type;
                $session->text( json_encode( $props ) );
                $session->save();
                $data = "data:{$mime_type};base64," . base64_encode( $image_data_thumb );
                $ctx->vars['thumbnail_image'] = $data;
                $ctx->vars['has_thumbnail_image'] = 1;
                $ctx->vars['file_name'] = $props['file_name'];
                $ctx->vars['image_width'] = $props['image_width'];
                $ctx->vars['image_height'] = $props['image_height'];
                $ctx->vars['mime_type'] = $props['mime_type'];
                $ctx->vars['file_size'] = $props['file_size'];
            }
        }
        $param = "?__mode=view&amp;_type=edit&amp;_model={$model}&amp;id={$id}"
               ."&amp;_screen_id={$screen_id}&amp;view=$key";
        if ( $app->workspace() ) {
            $workspace_id = $app->workspace()->id;
            $param .= "&amp;workspace_id={$workspace_id}";
        }
        $ctx->vars['this_mode'] = $app->mode;
        $ctx->vars['edit_url'] = $this->admin_url . $param;
        $app->assign_params( $app, $ctx );
        return $app->build_page( $tmpl );
    }

    function set_hierarchy ( $model, $children, $parent = 0, &$order = 1, &$error = false ) {
        $app = $this;
        foreach ( $children as $value ) {
            $id = $value['id'];
            $children = isset( $value['children'] ) ? $value['children'] : null;
            $obj = $app->db->model( $model )->load( $id );
            if (!$obj ) continue;
            $obj->parent_id( $parent );
            if ( $obj->has_column( 'order' ) ) {
                $obj->order( $order );
            }
            if (! $obj->save() ) $error = true;
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
        if ( $type == 'edit' ) {
            $obj = $app->db->model( $model )->new();
            if ( $workspace_id ) {
                if ( $obj->has_column( 'workspace_id' ) ) {
                    $obj->workspace_id( $workspace_id );
                }
            }
            $fields = PTUtil::get_fields( $obj, 'basenames' );
            foreach ( $fields as $opt ) {
                if ( $app->param( '_d_field-' . $opt ) ) {
                    $options[] = 'field-' .$opt;
                }
            }
        }
        $user_option = $app->get_user_opt( $model, $type . '_option', $workspace_id );
        $user_option->option( join( ',', $options ) );
        if ( $type === 'list' ) {
            $number = $app->param( '_per_page' );
            $user_option->number( (int) $number );
            $search_cols = [];
            $sort_by = [];
            $column_defs = array_keys( $scheme['column_defs'] );
            foreach ( $column_defs as $col ) {
                if ( $app->param( '_s_' . $col ) ) {
                    $search_cols[] = $col;
                }
                if ( $app->param( '_by_' . $col ) ) {
                    $sort_by[0] = $col;
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
        } else {
            $user_option->data( $app->param( 'field_sort_order' ) );
        }
        if ( $app->param( 'workspace_id' ) ) {
            $user_option->workspace_id( $app->param( 'workspace_id' ) );
        }
        $res = $user_option->save();
        if ( $type === 'edit' ) {
            header( 'Content-type: application/json' );
            echo json_encode( ['result' => $res ] );
            exit();
        }
        $options = $app->param( 'dialog_view' ) ? '&dialog_view=1' : '';
        $options .= $app->param( 'single_select' ) ? '&single_select=1' : '';
        $options .= $app->param( 'insert_editor' ) ? '&insert_editor=1' : '';
        $options .= $app->param( 'insert' ) ?
            '&insert_editor=' . $app->param( 'insert' ) : '';
        $options .= $app->param( 'insert' ) ?
            '&selected_ids=' . $app->param( 'selected_ids' ) : '';
        $options .= $app->param( 'target' ) ?
            '&target=' . $app->param( 'target' ) : '';
        $options .= $app->param( 'get_col' ) ?
            '&get_col=' . $app->param( 'get_col' ) : '';
        $app->redirect( $app->admin_url .
            "?__mode=view&_type={$type}&_model={$model}&saved_props=1"
            . $app->workspace_param . $options );
    }

    function upload ( $app ) {
        $app->validate_magic( true );
        $upload_dir = $app->upload_dir();
        $screen_id = $app->param( '_screen_id' );
        $name = $app->param( 'name' );
        $model = $app->param( '_model' );
        $table = $app->get_table( $model );
        if (!$table ) return $app->error( 'Invalid request.' );
        if (!$app->can_do( $model, 'create' ) ) {
            $error = $app->translate( 'Permission denied.' );
            header( 'Content-type: application/json' );
            echo json_encode( ['message'=> $error ] );
            exit();
        }
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
                    header( 'Content-type: application/json' );
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

    function get_thumbnail ( $app ) {
        $id = (int) $app->param( 'id' );
        if (!$id ) return;
        $meta = $app->db->model( 'meta' )->load( $id );
        if (!$meta ) return;
        $model = $meta->model;
        if (!$app->can_do( $model, 'list' ) ) {
            return;
        }
        $matadata = json_decode( $meta->text, true );
        $mime_type = $matadata['mime_type'];
        header( "Content-Type: {$mime_type}" );
        $column = $app->param( 'square' ) ? 'metadata' : 'data';
        $data = $meta->$column;
        $file_size = strlen( bin2hex( $data ) ) / 2;
        header( "Content-Length: {$file_size}" );
        echo $data;
        unset( $data );
    }

    function get_field_html ( $app ) {
        $app->validate_magic( true );
        $id = (int) $app->param( 'id' );
        $model = $app->param( 'model' );
        $workspace_id = $app->param( 'workspace_id' );
        $field_model = $app->param( '_type' ) && $app->param( '_type' ) == 'fieldtype'
                     ? 'fieldtype' : 'field';
        $field = $app->db->model( $field_model )->load( $id );
        header( 'Content-type: application/json' );
        if (!$field ) {
            echo json_encode( ['status' => 404,
                               'message' => $app->translate( 'Field not found.' ) ] );
            exit();
        }
        $field_label = $field->label;
        $field_content = $field->content;
        if (!$field_label || !$field_content ) {
            if ( $field_model == 'field' ) {
                $field_type = $field->fieldtype;
                if ( $field_type ) {
                    if (!$field_label ) $field_label = $field_type->label;
                    if (!$field_content ) $field_content = $field_type->content;
                }
            }
        }
        if (!$field_label && ! $field_content ) {
            echo json_encode( ['status' => 404,
                     'message' => $app->translate( 'Field HTML not specified.' ) ] );
            exit();
        }
        if ( $field_model == 'fieldtype' ) {
            echo json_encode( ['status' => 200,
                    'label' => $field_label, 'content' => $field_content ] );
            exit();
        }
        $ctx = $app->ctx;
        $param = [];
        $ctx->local_vars['field_name'] = $field->name;
        $ctx->local_vars['field_required'] = $field->required;
        $basename = $field->basename;
        $ctx->local_vars['field_basename'] = $basename;
        $prefix = $field->_colprefix;
        $values = $field->get_values();
        foreach ( $values as $key => $value ) {
            $key = preg_replace( "/^$prefix/", '', $key );
            $ctx->local_vars[ 'field__' . $key ] = $value;
        }
        $options = $field->options;
        if ( $options ) {
            $labels = $field->options_labels;
            $options = preg_split( '/\s*,\s*/', $options );
            $labels = $labels ? preg_split( '/\s*,\s*/', $labels ) : $options;
            $i = 0;
            $field_options = [];
            foreach ( $options as $option ) {
                $label = isset( $labels[ $i ] ) ? $labels[ $i ] : $option;
                $field_options[] = ['field_label' => $label, 'field_option' => $option ];
                $i++;
            }
            $ctx->local_vars['field_options'] = $field_options;
        }
        if ( $app->param( 'field__out' ) ) {
            $ctx->local_vars['field__out'] = 1;
        }
        $uniqueid = $app->magic();
        $ctx->local_vars['field_uniqueid'] = $uniqueid;
        $field_label = $app->tmpl_markup === 'mt' ? $ctx->build( $field_label )
                                                  : $app->build( $field_label, $ctx );
        $ctx->local_vars['field_label_html'] = $field_label;
        $field_label = $app->build_page( 'field' . DS . 'label.tmpl', $param, false );
        $field_content = $app->tmpl_markup === 'mt' ? $ctx->build( $field_content )
                                                  : $app->build( $field_content, $ctx );
        $ctx->local_vars['field_content_html'] = $field_content;
        $field_content = $app->build_page( 'field' . DS . 'content.tmpl', $param, false );
        $ctx->local_vars['field_label_html'] = $field_label;
        $ctx->local_vars['field_content_html'] = $field_content;
        $html = $app->build_page( 'field' . DS . 'wrapper.tmpl', $param, false );
        PTUtil::add_id_to_field( $html, $uniqueid, $basename );
        if (!$app->param( 'field__out' ) ) {
            $html = "<div id=\"field-{$basename}-wrapper\">{$html}</div>";
            $html .= $app->build_page( 'field' . DS . 'footer.tmpl', $param, false );
        }
        echo json_encode( ['html' => $html, 'status' => 200,
                           'basename' => $basename ] );
        exit();
    }

    function rebuild_phase ( $app, $start = false, $counter = 0, $dependencies = false ) {
        $ctx = $app->ctx;
        $per_rebuild = $app->per_rebuild;
        $app->get_scheme_from_db( 'urlinfo' );
        $tmpl = 'rebuild_phase.tmpl';
        $model = $app->param( '_model' );
        if ( $app->param( '_type' ) && $app->param( '_type' ) == 'start_rebuild' ) {
            $scope_name = $app->workspace() ? $app->workspace()->name : $app->appname;
            $title = $app->translate( 'Publish %s', $scope_name );
            $ctx->vars['page_title'] = $title;
            return $app->build_page( $tmpl );
        }
        $app->validate_magic();
        $start_time = $app->param( 'start_time' );
        if (!$start_time ) {
            $start_time = time();
            $app->param( 'start_time', $start_time );
        }
        $ctx->vars['request_id'] = $app->request_id;
        $ctx->vars['rebuild_interval'] = $app->rebuild_interval;
        $sess_terms = ['value' => $app->request_id ];
        $next_model = '';
        $next_models = [];
        $rebuild_last = false;
        $current_model = $app->param( 'current_model' );
        $ctx->vars['current_model'] = $current_model;
        if ( $app->param( '_type' ) && $app->param( '_type' ) == 'rebuild_archives' ) {
            $model = $app->param( 'next_models' );
            $models = explode( ',', $model );
            if ( isset( $models[ $counter ] ) ) {
                $model = $models[ $counter ];
                $obj = $app->db->model( $model )->__new();
                $table = $app->get_table( $model );
                if (!$table ) return $app->error( 'Invalid request.' );
                $_colprefix = $obj->_colprefix;
                $terms = [];
                if ( $obj->has_column( 'workspace_id' ) && $app->workspace() ) {
                    $terms['workspace_id'] = $app->workspace()->id;
                }
                $extra = '';
                if ( $table->revisable ) {
                    $extra .= " AND {$_colprefix}rev_type=0";
                }
                if ( $current_model == $model ) {
                    $ctx->vars['rebuild_end'] = 1;
                    $ctx->vars['page_title'] = $app->translate( 'Done.' );
                    $ctx->vars['publish_time'] = time() - $start_time;
                    $ctx->vars['start_time'] = $start_time;
                    $app->remove_session( $sess_terms );
                    return $app->build_page( $tmpl );
                }
                $objects = $obj = $app->db->model( $model )->load( $terms, [], '*', $extra );
                $apply_actions = count( $objects );
                if (!$apply_actions ) {
                    if ( isset( $models[ $counter + 1] ) ) {
                        return $app->rebuild_phase( $app, $start, $counter + 1 );
                    } else {
                        $ctx->vars['rebuild_end'] = 1;
                        $ctx->vars['publish_time'] = time() - $start_time;
                        $ctx->vars['page_title'] = $app->translate( 'Done.' );
                        $ctx->vars['start_time'] = $start_time;
                        $app->remove_session( $sess_terms );
                        return $app->build_page( $tmpl );
                    }
                } else {
                    $next_models =
                        array_slice( $models , $counter + 1, count( $models ) - 1);
                    if ( empty( $next_models ) ) {
                        $rebuild_last = true;
                    } else {
                        $next_model = $next_models[0];
                    }
                    $object_ids = [];
                    foreach ( $objects as $obj ) {
                        if ( $app->get_permalink( $obj, true ) ) {
                            $object_ids[] = $obj->id;
                        }
                    }
                    $apply_actions = count( $object_ids );
                    if (!$apply_actions ) {
                        if ( isset( $models[ $counter + 1] ) ) {
                            return $app->rebuild_phase( $app, $start, $counter + 1 );
                        } else {
                            $ctx->vars['rebuild_end'] = 1;
                            $ctx->vars['publish_time'] = time() - $start_time;
                            $ctx->vars['page_title'] = $app->translate( 'Done.' );
                            $ctx->vars['start_time'] = $start_time;
                            $app->remove_session( $sess_terms );
                            return $app->build_page( $tmpl );
                        }
                    }
                    $app->param( 'apply_actions', $apply_actions );
                    $app->param( 'ids', join( ',', $object_ids ) );
                }
            } else {
                return $app->error( 'Invalid request.' );
            }
        } else {
            $table = $app->get_table( $model );
        }
        $plural = $app->translate( $table->plural );
        $ids = $app->param( 'ids' );
        $ids = explode( ',', $ids );
        $apply_actions = (int) $app->param( 'apply_actions' );
        array_walk( $ids, function( &$id ) { $id = (int) $id; } );
        $rebuild_ids = array_slice( $ids , 0, $per_rebuild );
        $next_ids = array_slice( $ids , $per_rebuild );
        $rebuilt = $apply_actions - ( count( $ids ) - count( $rebuild_ids ) );
        $objects = $app->db->model( $model )->load( ['id' => ['IN' => $rebuild_ids ] ] );
        $ctx->vars['current_model'] = $model;
        foreach ( $objects as $obj ) {
            $cached_vars = $app->ctx->vars;
            $cached_local_vars = $app->ctx->local_vars;
            $app->publish_obj( $obj, null, false );
            $app->ctx->vars = $cached_vars;
            $app->ctx->local_vars = $cached_local_vars;
        }
        $_return_args = $app->param( '_return_args' );
        if ( $dependencies ) {
            $app->param( 'dependencies', 1 );
        }
        if ( $app->param( 'start_rebuild' ) ) {
            $app->param( 'start_time', time() );
        }
        $ctx->vars['this_mode'] = $app->mode;
        $app->assign_params( $app, $ctx );
        $request_dependencies = [];
        if ( $app->param( 'dependencies' ) ) {
            $rebuild_dependencies = $app->param( 'rebuild_dependencies' );
            $request_dependencies = $app->stash( 'rebuild_dependencies' )
                                  ? $app->stash( 'rebuild_dependencies' ) : [];
            if ( $rebuild_dependencies ) {
                $rebuild_dependencies = json_decode( $rebuild_dependencies, true );
                foreach ( $rebuild_dependencies as $key => $ids ) {
                    $_ids = isset( $request_dependencies[ $key ] )
                          ? $request_dependencies[ $key ] : [];
                    $_ids = array_merge( $_ids, $ids );
                    $_ids = array_unique( $_ids );
                    $request_dependencies[ $key ] = $_ids;
                }
            }
            $ctx->vars['rebuild_dependencies'] = json_encode( $request_dependencies );
        }
        if (! empty( $next_ids ) ) {
            $percent = round( $rebuilt / $apply_actions * 100 );
            if ( $start ) {
                $_return_args = rawurlencode( $_return_args );
            }
            $ctx->vars['rebuilt_percent'] = $percent;
            $ctx->vars['_return_args'] = $_return_args;
            $ctx->vars['_model'] = $model;
            $ctx->vars['rebuild_ids'] = join( ',', $next_ids );
            $ctx->vars['apply_actions'] = $apply_actions;
            $ctx->vars['icon_url'] = 'assets/img/loading.gif';
            $title = $app->translate( 'Rebuilding %s...', $plural );
            $ctx->vars['page_title'] = $title;
            if ( empty( $next_models ) ) {
                $next_models = $app->param( 'next_models' );
            } else {
                $next_models = join( ',', $next_models );
            }
            if ( $next_models ) {
                $ctx->vars['next_models'] = $next_models;
            }
            $ctx->vars['start_time'] = $start_time;
            return $app->build_page( $tmpl );
        } else {
            if ( $app->param( 'next_models' ) ) {
                $ctx->vars['rebuild_next'] = 1;
                if ( empty( $next_models ) &&! $rebuild_last ) {
                    $next_models = $app->param( 'next_models' );
                } else {
                    $next_models = join( ',', $next_models );
                }
                $ctx->vars['next_models'] = $next_models;
            }
            if ( $app->param( '_return_args' ) ) {
                $return_args = $app->param( '_return_args' );
                if (! empty( $request_dependencies ) ) {
                    $i = 0;
                    $keys = count( $request_dependencies );
                    foreach ( $request_dependencies as $key => $ids ) {
                        $_return_args = '';
                        if ( $return_args ) {
                            $_return_args = '&_return_args=' . rawurlencode( $return_args );
                            if ( strpos( $return_args, 'does_act' ) !== 0 ) {
                                $_return_args .= rawurlencode(
                                    '&magic_token=' . $app->current_magic );
                            }
                        }
                        $counter = count( $ids );
                        $return_args = '__mode=rebuild_phase&ids=' . join( ',', $ids )
                        . '&_model=' . $key . '&apply_actions=' . $counter . $_return_args;
                        $progress = round( ( ( $keys - $i + 1 ) / $keys ) * 100 );
                        $i++;
                        $return_args .= '&progress=' . $progress;
                    }
                    $return_args .= '&magic_token=' . $app->current_magic;
                }
                if (! empty( $request_dependencies ) ) {
                    $progress = round( ( ( $keys - $i + 1 ) / $keys ) * 100 );
                    $app->param( 'progress', $progress );
                }
                $return_args = rawurldecode( $return_args );
                parse_str( $return_args, $params );
                if ( $workspace_id = $app->param( 'workspace_id' ) ) {
                    $params['workspace_id'] = $workspace_id;
                }
                if (! isset( $params['magic_token'] ) ) {
                    $params['magic_token'] = $app->current_magic;
                }
                $return_args = http_build_query( $params );
                if ( isset( $params['__mode'] ) && $params['__mode'] === 'rebuild_phase' ) {
                    $next_model = $params['_model'];
                    $table = $app->get_table( $next_model );
                    $plural = $app->translate( $table->plural );
                    $ctx->vars['icon_url'] = 'assets/img/loading.gif';
                    $title = $app->translate( 'Rebuilding %s...', $plural );
                    $ctx->vars['page_title'] = $title;
                    $ctx->vars['next_url'] = $app->admin_url . '?' . $return_args;
                    $ctx->vars['start_time'] = $start_time;
                    return $app->build_page( $tmpl );
                }
                $app->redirect( $app->admin_url . '?' . $return_args );
            }
            if ( $rebuild_last ) {
                $ctx->vars['rebuild_end'] = 1;
                $ctx->vars['publish_time'] = time() - $start_time;
                $ctx->vars['page_title'] = $app->translate( 'Done.' );
                $app->remove_session( $sess_terms );
            } else {
                if (!$next_model && $app->param( 'next_models' ) ) {
                    $next_models = explode( ',', $app->param( 'next_models' ) );
                    $next_model = $next_models[0];
                }
                $table = $app->get_table( $next_model );
                $plural = $app->translate( $table->plural );
                $ctx->vars['icon_url'] = 'assets/img/loading.gif';
                $title = $app->translate( 'Rebuilding %s...', $plural );
                $ctx->vars['page_title'] = $title;
            }
            $ctx->vars['start_time'] = $start_time;
            return $app->build_page( $tmpl );
        }
    }

    function remove_session ( $terms = [] ) {
        $app = $this;
        $sessions = $app->db->model( 'session' )->load( $terms );
        foreach ( $sessions as $session ) {
            $session->remove();
        }
    }

    function upload_multi ( $app ) {
        $app->validate_magic( true );
        if (!$app->can_do( 'asset', 'create' ) ) {
            $error = $app->translate( 'Permission denied.' );
            header( 'Content-type: application/json' );
            echo json_encode( ['message' => $error ] );
            exit();
        }
        $upload_dir = $app->upload_dir();
        $options = ['upload_dir' => $upload_dir . DS, 'prototype' => $app ];
        $upload_handler = new UploadHandler( $options );
    }

    function upload_dir ( $basename = '' ) {
        $app = $this;
        $app->db->logging = false;
        $app->ctx->logging = false;
        $app->logging = false;
        require_once( LIB_DIR . 'jQueryFileUpload' . DS . 'UploadHandler.php' );
        $upload_dir = $app->temp_dir;
        if ( $upload_dir ) {
            $upload_dir = rtrim( $upload_dir, DS );
            $upload_dir .= DS;
        }
        if (!$upload_dir ) $upload_dir =  __DIR__ . DS . 'tmp' . DS;
        $upload_dir = tempnam( $upload_dir, '' );
        unlink( $upload_dir );
        mkdir( $upload_dir . DS, 0777, TRUE );
        if ( $basename ) {
            $upload_dir .= DS . $basename . DS;
            mkdir( $upload_dir, 0777, TRUE );
        }
        return $upload_dir;
    }

    function get_columns_json ( $app ) {
        $app->validate_magic( true );
        header( 'Content-type: application/json' );
        if (!$app->can_do( 'table', 'edit' ) ) {
            echo json_encode( ['status' => 403,
                               'message' => $app->translate( 'Permission denied.' ) ] );
            exit();
        }
        $model = $app->param( '_model' );
        $scheme = $app->get_scheme_from_db( $model );
        echo json_encode( $scheme );
    }

    function export_scheme ( $app ) {
        $app->validate_magic();
        $model = $app->param( 'name' );
        $scheme = $app->get_scheme_from_db( $model, true );
        $table = $app->get_table( $model );
        $scheme['label'] = $table->label;
        unset( $scheme['labels'] );
        header( 'Content-type: application/json' );
        header( "Content-Disposition: attachment;"
            . " filename=\"{$model}.json\"" );
        header( "Pragma: " );
        echo json_encode( $scheme, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT );
    }

    function save_config ( $app ) {
        $app->validate_magic();
        if (!$app->user()->is_superuser ) {
            $app->error( 'Permission denied.' );
        }
        $appname = $app->param( 'appname' );
        $site_path = $app->param( 'site_path' );
        $site_url = $app->param( 'site_url' );
        $extra_path = $app->param( 'extra_path' );
        $asset_publish = $app->param( 'asset_publish' );
        $copyright = $app->param( 'copyright' );
        $system_email = $app->param( 'system_email' );
        $default_widget = $app->param( 'default_widget' );
        $preview_url = $app->param( 'preview_url' );
        $language = $app->param( 'language' );
        $barcolor = $app->param( 'barcolor' );
        $bartextcolor = $app->param( 'bartextcolor' );
        $two_factor_auth = $app->param( 'two_factor_auth' );
        $lockout_limit = $app->param( 'lockout_limit' );
        $lockout_interval = $app->param( 'lockout_interval' );
        $ip_lockout_limit = $app->param( 'ip_lockout_limit' );
        $ip_lockout_interval = $app->param( 'ip_lockout_interval' );
        $tmpl_markup = $app->param( 'tmpl_markup' );
        $errors = [];
        if (!$appname || !$site_url || !$system_email || !$site_path ) {
            $errors[] = $app->translate(
                'App Name, System Email Site URL and Site Path are required.' );
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
        if ( $extra_path &&
            !$app->is_valid_property( str_replace( '/', '', $extra_path ) ) ) {
            $errors[] = $app->translate(
                'Upload Path contains an illegal character.' );
        }
        if (! empty( $errors ) ) {
            $ctx = $app->ctx;
            $ctx->vars['error'] = join( "\n", $errors );
            $app->assign_params( $app, $ctx );
            $tmpl = 'config.tmpl';
            return $app->build_page( $tmpl );
        }
        $cfgs = ['appname'    => $appname,
                 'copyright'  => $copyright,
                 'site_path'  => $site_path,
                 'site_url'   => $site_url,
                 'extra_path' => $extra_path,
                 'language'   => $language,
                 'barcolor'   => $barcolor,
                 'bartextcolor' => $bartextcolor,
                 'asset_publish' => $asset_publish,
                 'system_email' => $system_email,
                 'preview_url' => $preview_url,
                 'lockout_limit' => $lockout_limit,
                 'lockout_interval' => $lockout_interval,
                 'ip_lockout_limit' => $ip_lockout_limit,
                 'ip_lockout_interval' => $ip_lockout_interval,
                 'two_factor_auth' => $two_factor_auth,
                 'default_widget' => $default_widget,
                 'tmpl_markup' => $tmpl_markup ];
        $app->set_config( $cfgs );
        $metadata = json_encode( $cfgs, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT );
        $message = '';
        $nickname = $app->user()->nickname;
        $message = $app->translate( 'User %s updated the system settings.', $nickname );
        $app->log( ['message'   => $message,
                    'category'  => 'save',
                    'model'     => 'option',
                    'metadata'  => $metadata,
                    'level'     => 'info'] );
        $app_path = $app->site_path;
        $app_url  = $app->site_url;
        if ( $site_path !== $app_path || $site_url !== $app_url ) {
            $app->rebuild_urlinfo( $site_url, $site_path );
        }
        $app->redirect( $app->admin_url . '?__mode=config&saved=1'
                         . $app->workspace_param );
    }

    function debug ( $app ) {
    }

    function save ( $app ) {
        $db = $app->db;
        $callbacks = $app->callbacks;
        $app->get_scheme_from_db( 'urlinfo' );
        $model = $app->param( '_model' );
        $app->validate_magic();
        if (!$model ) return $app->error( 'Invalid request.' );
        $table = $app->get_table( $model );
        if (!$table ) return $app->error( 'Invalid request.' );
        $class = $table->name;
        if ( prototype_auto_loader( $class ) ) {
            new $class();
        }
        $action = $app->param( 'id' ) ? 'edit' : 'create';
        $obj = $db->model( $model )->new();
        $scheme = $app->get_scheme_from_db( $model );
        if (!$scheme ) return $app->error( 'Invalid request.' );
        $db->scheme[ $model ] = $scheme;
        $primary = $scheme['indexes']['PRIMARY'];
        $id = $app->param( $primary );
        if ( $id ) {
            $obj = $obj->load( $id );
            if (!$obj )
                return $app->error( 'Cannot load %s (ID:%s)', [ $table->label, $id ] );
        }
        if (!$app->can_do( $model, $action, $obj ) ) {
            $app->error( 'Permission denied.!' );
        }
        $callbacks = ['save_filter_table', 'save_filter_urlmapping',
                      'save_filter_workspace', 'post_save_workspace', 'post_save_role',
                      'post_save_permission', 'post_save_table', 'post_save_field',
                      'post_save_asset', 'save_filter_tag', 'pre_save_user'];
        foreach ( $callbacks as $meth ) {
            $cb = explode( '_', $meth );
            $app->register_callback( $cb[2], $cb[0] . '_' . $cb[1], $meth, 1, $app );
        }
        $app->init_callbacks( $model, 'save' );
        $db->caching = false;
        $orig_relations = $app->get_relations( $obj );
        $orig_metadata  = $app->get_meta( $obj );
        $original = clone $obj;
        $is_changed = false;
        $changed_cols = [];
        $not_for_revision = ['created_on', 'modified_on', 'status', 'user_id',
                             'created_by', 'modified_by', 'rev_type', 'rev_object_id',
                             'rev_changed', 'rev_diff', 'rev_note'];
        $properties = $scheme['edit_properties'];
        $autoset = isset( $scheme['autoset'] ) ? $scheme['autoset'] : [];
        $columns = $scheme['column_defs'];
        $labels = $scheme['labels'];
        $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];
        $unchangeable = isset( $scheme['unchangeable'] ) ? $scheme['unchangeable'] : [];
        $unique = isset( $scheme['unique'] ) ? $scheme['unique'] : [];
        $errors = [];
        $placements = [];
        $add_tags = [];
        $text = '';
        $as_revision = false;
        $renderer = null;
        if ( $app->param( '_save_as_revision' ) ) {
            $as_revision = true;
        }
        if ( $model == 'user' ) {
            $app->user = clone $app->user();
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
                    if (!$value ) $value = [];
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
                    if ( $col === 'id' || $col === 'workspace_id' ) {
                        continue;
                    }
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
                                if (!$app->is_valid_password( $pass, $verify, $msg ) ) {
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
                        $terms['id'] = ['!=' => $obj->id ];
                    }
                    if ( $table->revisable ) {
                        $terms['rev_type'] = 0;
                    }
                    if ( $obj->has_column( 'workspace_id' ) ) {
                        $workspace_ids = [0];
                        if ( $obj->workspace_id ) {
                            $workspace_ids[] = (int) $obj->workspace_id;
                        }
                        $terms['workspace_id'] = ['IN' => $workspace_ids];
                    }
                    $compare = $db->model( $model )->load( $terms );
                    if ( is_array( $compare ) && !empty( $compare ) ) {
                        $errors[] = $app->translate(
                            'A %1$s with the same %2$s already exists.',
                                [ $value, $app->translate( $labels[ $col ] ) ] );
                    }
                }
            }
            if ( is_array( $value ) ) {
                $value = join( ',', $value );
            }
            if ( $col !== 'id' ) {
                $not_null = isset( $props['not_null'] ) ? $props['not_null'] : false;
                if ( $not_null && $col === 'basename' ) {
                    if (!$value ) {
                        $text = strip_tags( $text );
                        $value = PTUtil::make_basename( $obj, $text, true );
                    }
                }
                if ( $not_null && $type === 'datetime' ) {
                    $value = $obj->db2ts( $value );
                    if (!$value ) {
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
                            $errors[] = $app->translate( '%s is required.',
                                        $app->translate( $labels[ $col ] ) );
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
                                PTUtil::diff( $original->$col, $obj->$col, $renderer );
                        }
                    }
                }
            }
        }
        if (!$app->can_do( $model, $action, $obj ) ) {
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
                     'changed_cols' => $changed_cols ];
        $pre_save = $app->run_callbacks( $callback, $model, $obj, $original );
        if ( $msg = $callback['error'] ) {
            $errors[] = $msg;
        }
        $required_fields = PTUtil::get_fields( $obj, 'requireds' );
        $required_basenames = array_keys( $required_fields );
        foreach ( $required_basenames as $fld ) {
            $fld_value = $app->param( "{$fld}__c" );
            if ( $fld_value !== null ) {
                $fld_values = json_decode( $fld_value, true );
                if ( empty( $fld_values ) ) {
                    $errors[] =
                        $app->translate( '%s is required.', $required_fields[ $fld ] );
                }
            }
        }
        if (!empty( $errors ) || !$save_filter || !$pre_save ) {
            $error = join( "\n", $errors );
            if ( $app->param( '_preview' ) ) return $app->error( $error );
            return $app->forward( $model, $error );
        }
        if ( $model === 'workspace' ) {
            $obj->last_update( time() );
        }
        $app->set_default( $obj );
        if ( $app->param( '_preview' ) ) {
            return $app->preview( $obj, $properties );
        }
        $db->begin_work();
        $errstr = $app->translate( 'An error occurred while saving %s.',
                    $app->translate( $table->label ) );
        if (!$as_revision ) {
            if (! $obj->save() ) return $app->rollback( $errstr );
        }
        if (! empty( $add_tags ) ) {
            $workspace_id = (int) $app->param( 'workspace_id' );
            $props = $placements['tags']['tag'];
            foreach ( $add_tags as $tag ) {
                // if ( function_exists( 'normalizer_normalize' ) ) {
                //     $normalize = normalizer_normalize( $normalize, Normalizer::NFKD );
                // }
                $normalize = str_replace( ' ', '', trim( mb_strtolower( $tag ) ) );
                if (!$normalize ) continue;
                $terms = ['normalize' => $normalize ];
                if ( $workspace_id )
                    $terms['workspace_id'] = $workspace_id;
                $tag_obj = $db->model( 'tag' )->get_by_key( $terms );
                if (!$tag_obj->id ) {
                    $tag_obj->name( $tag );
                    $app->set_default( $tag_obj );
                    $order = $app->get_order( $tag_obj );
                    $tag_obj->order( $order );
                    if (! $tag_obj->save() ) return $app->rollback( $errstr );
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
                if ( $res = $app->set_relations( $args, $to_ids, false, $errors ) ) {
                    $changed_cols[ $name ] = $res;
                    if ( $obj->id  && ! $is_changed ) {
                        $is_changed = $res;
                    }
                }
                if (!empty( $errors ) ) {
                    return $app->rollback( join( ',', $errors ) );
                }
            }
        }
        $has_file = false;
        foreach ( $properties as $key => $val ) {
            if ( $val === 'file' ) {
                $file_remove = $app->param( "{$key}-remove" );
                $metadata = $db->model( 'meta' )->get_by_key(
                         ['model' => $model, 'object_id' => $obj->id,
                          'kind' => 'metadata', 'key' => $key ] );
                $magic = $app->param( "{$key}-magic" );
                if ( $file_remove ) {
                    $obj->$key( '' );
                    if ( $metadata->id ) {
                        $metadata->remove();
                        $urls = $db->model( 'urlinfo' )->load(
                            ['class' => 'file','key' => $key,
                             'object_id' => $obj->id,'model' => $obj->_model ] );
                        foreach ( $urls as $url ) {
                            $file_path = $url->file_path;
                            if ( $app->fmgr->exists( $file_path ) ) {
                                $app->fmgr->unlink( $file_path );
                            }
                            if (! $url->remove() ) {
                                return $app->rollback(
                                'An error occurred while updating the related object(s).' );
                            }
                        }
                    }
                    $has_file = true;
                    $changed_cols[ $key ] = true;
                }
                if ( $magic ) {
                    $sess = $db->model( 'session' )
                        ->get_by_key( ['name' => $magic,
                                       'user_id' => $app->user()->id, 'kind' => 'UP'] );
                    if ( $sess->id ) {
                        if (!$file_remove ) {
                            $obj->$key( $sess->data );
                            $has_file = true;
                            $metadata->data( $sess->metadata );
                            $metadata->type( $sess->key );
                            $metadata->text( $sess->text );
                            $metadata->value( $sess->value );
                            $metadata->metadata( $sess->extradata );
                            if (! $metadata->save() ) return $app->rollback( $errstr );
                            if ( $obj->id ) $is_changed = true;
                            $changed_cols[ $key ] = true;
                        }
                        $sess->remove();
                    }
                }
            }
        }
        if ( $has_file && ! $as_revision ) {
            if (! $obj->save() ) return $app->rollback( $errstr );
        }
        $id = $obj->id;
        $object_fields = PTUtil::get_fields( $obj, 'types' );
        $custom_fields = $app->get_meta( $obj, 'customfield' );
        $field_ids = [];
        foreach ( $object_fields as $fld => $props ) {
            $fieldtype = $props['type'];
            $field_id = $props['id'];
            $fld_value = $app->param( "{$fld}__c" );
            $unique_ids = $app->param( "field-unique_id-{$fld}" );
            if ( $fld_value !== null ) {
                if (! is_array( $fld_value ) ) $fld_value = [ $fld_value ];
                if (! is_array( $unique_ids ) ) $unique_ids = [ $unique_ids ];
                $i = 0;
                $meta_objects = [];
                foreach ( $fld_value as $value ) {
                    $unique_id = $unique_ids[ $i ];
                    $i++;
                    $fld_values = json_decode( $value, true );
                    foreach ( $fld_values as $key => $val ) {
                        if ( strpos( $key, $unique_id . '_' ) === 0 ) {
                            $new_key = preg_replace( "/^{$unique_id}_/", '', $key );
                            unset( $fld_values[ $key ] );
                            $fld_values[ $new_key ] = $val;
                        }
                    }
                    $value = json_encode( $fld_values, JSON_UNESCAPED_UNICODE );
                    $meta = $db->model( 'meta' )->get_by_key(
                        ['object_id' => $id, 'model' => $obj->_model,
                         'kind' => 'customfield', 'key' => $fld, 'number' => $i ] );
                    $meta->text( $value );
                    $meta->type( $fieldtype );
                    $meta->field_id( $field_id );
                    if ( count( $fld_values ) == 1 ) {
                        $meta_key = key( $fld_values );
                        $meta->name( $meta_key );
                        $meta_value = $fld_values[ $meta_key ];
                        if ( is_array( $meta_value ) ) 
                            $meta_value = isset( $meta_value[0] ) ? $meta_value[0] : '';
                        $meta->value( $meta_value );
                    } else {
                        $meta->value( '' );
                    }
                    if ( $meta->id ) {
                        $field_ids[] = $meta->id;
                    } else {
                        $meta->id = '';
                    }
                    $meta_objects[] = $meta;
                }
                if (! empty( $meta_objects ) ) {
                    if (! $app->db->model( 'meta' )->update_multi( $meta_objects ) ) {
                        return $app->rollback( 'An error occurred while updating the related object(s).' );
                    }
                }
            }
        }
        if (! empty( $custom_fields ) ) {
            foreach ( $custom_fields as $custom_field ) {
                if (! in_array( $custom_field->id, $field_ids ) ) {
                    $custom_field->remove();
                }
            }
            // todo bulk
        }
        if ( $as_revision ) {
            $is_changed = true;
            $original = $obj;
            $original->rev_object_id( null );
        }
        if (!$is_new && $is_changed && $table->revisable && !$obj->rev_object_id ) {
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
            if (! $original->save() ) return $app->rollback( $errstr );
            foreach ( $orig_relations as $relation ) {
                $rel_rev = clone $relation;
                $rel_rev->id( null );
                $rel_rev->from_id = $original->id;
                if (! $rel_rev->save() ) return $app->rollback( $errstr );
            }
            foreach ( $orig_metadata as $meta ) {
                $meta_rev = clone $meta;
                $meta_rev->id( null );
                $meta_rev->object_id = $original->id;
                if (! $meta_rev->save() ) return $app->rollback( $errstr );
            }
            if ( $as_revision ) $id = $original->id;
        }
        $add_return_args = '';
        if ( $app->param( '_apply_to_master' ) ) {
            $add_return_args = '&apply_to_master=1';
        } else if ( $app->param( '_edit_revision' ) || $as_revision ) {
            $add_return_args = '&edit_revision=1';
            if ( $as_revision && ! $is_changed ) {
                $add_return_args .= '&not_changed=1';
            }
        } else if ( $app->param( '__can_rebuild_this_template' ) ) {
            if (!$app->param( '__save_and_publish' ) ) {
                $add_return_args .= '&rebuild_this_template=1';
            }
        }
        if ( $is_changed ) {
            if ( $ws = $obj->workspace ) {
                $ws->last_update( time() );
                $ws->save();
            }
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
        if (! empty( $errors ) ) {
            return $app->rollback( join( ',', $errors ) );
        }
        $db->commit();
        $db->caching = false;
        $callback = ['name' => 'post_save', 'is_new' => $is_new,
                     'changed_cols' => $changed_cols, 'orig_relations' => $orig_relations,
                     'orig_metadata' => $orig_metadata ];
        $app->run_callbacks( $callback, $model, $obj, $original );
        if (!$as_revision ) {
            if ( $model !== 'template' || $app->param( '__save_and_publish' ) ) {
                $app->publish_obj( $obj, $original );
            }
        }
        $app->redirect( $app->admin_url . '?__mode=view&_type=edit&_model=' . $model .
            '&id=' . $id . '&saved=1' . $app->workspace_param . $add_return_args );
    }

    function publish_obj ( $obj, $original = null, $dependencies = true ) {
        $app = $this;
        $db = $app->db;
        $fmgr = $app->fmgr;
        $model = $obj->_model;
        $table = $app->get_table( $model );
        $scheme = $app->get_scheme_from_db( $model );
        $workspace = $app->workspace();
        if ( $obj->_model === 'workspace' ) {
            $workspace = $obj;
        } else {
            $workspace = $obj->workspace;
        }
        $properties = $scheme['edit_properties'];
        $base_url = $app->site_url;
        $base_path = $app->site_path;
        $extra_path = $app->extra_path;
        if ( $workspace ) {
            $base_url = $workspace->site_url;
            $base_path = $workspace->site_path;
            $extra_path = $workspace->extra_path;
        }
        foreach ( $properties as $key => $val ) {
            if ( $model === 'asset' && $key === 'file' ) continue;
            if ( $val === 'file' ) {
                if (!$obj->$key ) continue;
                $metadata = $db->model( 'meta' )->get_by_key(
                         ['model' => $model, 'object_id' => $obj->id,
                          'kind' => 'metadata', 'key' => $key ] );
                if (!$metadata->id ) continue;
                $file_meta = json_decode( $metadata->text, true );
                $mime_type = $file_meta['mime_type'];
                $file_ext = $file_meta['extension'];
                $file = "{$model}/{$model}-{$key}-" . $obj->id;
                if ( $file_ext ) $file .= '.' . $file_ext;
                $file_path = $base_path . '/'. $extra_path . $file;
                $file_path = str_replace( '/', DS, $file_path );
                $url = $base_url . '/'. $extra_path . $file;
                if (!$table->revisable || !$obj->rev_type ) {
                    if ( file_exists( $file_path ) ) {
                        $comp = base64_encode( $fmgr->get( $file_path ) );
                        $data = base64_encode( $obj->$key );
                        if ( $comp === $data ) continue;
                        unset( $comp, $data );
                    }
                    $app->publish( $file_path, $obj, $key, $mime_type );
                }
            }
        }
        $terms = ['model' => $model, 'container' => $model ];
        $extra = '';
        if ( $obj->has_column( 'workspace_id' ) ) {
            $map = $db->model( 'urlmapping' )->__new();
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
        if (!$table->revisable || (!$obj->rev_type ) ) {
            foreach ( $mappings as $mapping ) {
                if ( $obj->_model === 'template' ) {
                    if ( $obj->id != $mapping->template_id ) {
                        continue;
                    }
                }
                if ( $mapping->link_status && $obj->has_column( 'status' ) ) {
                    $status_published = $app->status_published( $obj->_model );
                    if ( $original && $original->status != $status_published &&
                        $obj->status != $status_published ) {
                        continue;
                    }
                }
                if (!$mapping->date_based && $mapping->model == $model ) {
                    $file_path = $app->build_path_with_map( $obj, $mapping, $table );
                    $app->publish( $file_path, $obj, $mapping );
                    if ( $app->resetdb_per_rebuild ) $app->db->reconnect();
                } else if ( $mapping->model != $model && $mapping->container == $model ) {
                    if ( $dependencies ) {
                        $app->publish_dependencies( $obj, $original, $mapping );
                    } else {
                        if ( $app->param( '_return_args' ) ) {
                            $app->publish_dependencies( $obj, $original, $mapping, false );
                        }
                    }
                } else if ( $mapping->date_based && $mapping->container ) {
                    $at = $mapping->date_based;
                    $container = $mapping->container;
                    $container_table = $app->get_table( $container );
                    $app->get_scheme_from_db( $container );
                    $status_published = $app->status_published( $container );
                    $container_obj = $app->db->model( $container )->__new();
                    $_colprefix = $container_obj->_colprefix;
                    $date_col = $_colprefix
                              . $app->get_date_col( $container_obj );
                    $_table = $db->prefix . $container;
                    $sql = "SELECT DISTINCT YEAR({$date_col}), MONTH({$date_col}) FROM $_table";
                    if ( $status_published || $mapping->workspace_id ) {
                        $sql .= " WHERE ";
                        $wheres = [];
                        if ( $status_published ) {
                            $wheres[] = "{$_colprefix}status=$status_published";
                        }
                        if ( $mapping->workspace_id ) {
                            $ws_id = $mapping->workspace_id;
                            $wheres[] = "{$_colprefix}workspace_id=$ws_id";
                        }
                        if ( $container_obj->has_column( 'rev_type' ) ) {
                            $wheres[] = "{$_colprefix}rev_type=0";
                        }
                        $sql .= join( ' AND ', $wheres );
                    }
                    $sql .= " ORDER BY YEAR({$date_col})";
                    $year_and_month = $container_obj->load( $sql );
                    if ( $at === 'Fiscal-Yearly' ) {
                        $fy_start = $mapping->fiscal_start;
                        $fy_end = $fy_start == 1 ? 12 : $fy_start - 1;
                    }
                    $time_stamp = [];
                    foreach ( $year_and_month as $ym ) {
                        $ym = $ym->get_values();
                        $y = $ym["YEAR({$date_col})"];
                        $m = $ym["MONTH({$date_col})"];
                        $m = sprintf( '%02d', $m );
                        if ( $at === 'Fiscal-Yearly' ) {
                            if ( $m <= $fy_end ) $y--;
                            $time_stamp[ $y ] = true;
                        } else if ( $at === 'Yearly' ) {
                            $time_stamp[ $y ] = true;
                        } else if ( $at === 'Monthly' ) {
                            $time_stamp[ $y . $m ] = true;
                        }
                    }
                    $time_stamp = array_keys( $time_stamp );
                    foreach ( $time_stamp as $time ) {
                        $terms = [];
                        if ( $at === 'Fiscal-Yearly' ) {
                            $fy_start = sprintf( '%02d', $fy_start );
                            $ts = $time . $fy_start . '01000000';
                        } else if ( $at === 'Yearly' ) {
                            $ts = $time . '0101000000';
                        } else if ( $at === 'Monthly' ) {
                            $ts = $time . '01000000';
                        }
                        $file_path = $app->build_path_with_map( $obj, $mapping, $table, $ts );
                        $app->publish( $file_path, $obj, $mapping, null, $ts );
                        if ( $app->resetdb_per_rebuild ) $app->db->reconnect();
                    }
                }
            }
        }
    }

    function get_order ( $obj ) {
        $app = $this;
        $last = $app->db->model( $obj->_model )->load( null, [
                'sort' => 'order', 'limit' => 1, 'direction' => 'descend'] );
        if ( is_array( $last ) && count( $last ) ) {
            $last = $last[0];
            $incl = $obj->_model == 'table' ? 10 : 1;
            $value = $last->order + $incl;
        } else {
            $value = 1;
        }
        return $value;
    }

    function preview ( $obj = null, $properties = [] ) {
        $app = $this;
        $workspace = $app->workspace();
        $db = $app->db;
        if ( $app->param( 'token' ) ) {
            $magic = $app->param( 'token' );
            $user = $app->user();
            if (!$user ) {
                $key = $app->param( 'key' );
                $user_id = $app->decrypt( $key, $magic );
                if ( $user_id ) $user_id = (int) $user_id;
                $user = $db->model( 'user' )->load( $user_id );
            }
            if (!$user ) {
                return $app->error( 'Invalid request.' );
            }
            $terms = ['name' => $magic,
                      'user_id' => $user->id, 'kind' => 'PV'];
            if ( $workspace ) $terms['workspace_id'] = $workspace->id;
            $session = $db->model( 'session' )->get_by_key( $terms );
            if (!$session->id ) {
                return $app->error( 'Invalid request.' );
            }
            $mime_type = $session->key;
            if ( $mime_type ) {
                header( "Content-type: {$mime_type}" );
            }
            echo $session->text;
            $session->remove();
            exit();
        }
        if (!$obj ) {
            return $app->error( 'Invalid request.' );
        }
        $map = $app->get_permalink( $obj, true );
        if ( $obj->_model !== 'template' ) {
            if (!$map || ! $map->template || ! $map->model ) {
                return $app->error( 'View or Model not specified.' );
            }
        } else {
            $template = $obj;
        }
        if ( $map ) {
            $template = $map->template;
            $model = $map->model;
        } else {
            $model = 'template';
        }
        $app->init_tags();
        $ctx = clone $app->ctx;
        $table = $app->get_table( $model );
        $ctx->stash( 'current_urlmapping', $map );
        $archive_type = '';
        if ( $obj->_model === 'template' ) {
            $tmpl = $obj->text;
            $terms = [];
            if ( $map && $map->workspace_id ) {
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
                $title_col = $table->primary;
                $ctx->stash( 'current_archive_title', $obj->$title_col );
                $ctx->stash( 'current_archive_type', $obj->_model );
            } else {
                $ctx->stash( 'current_archive_title', $template->name );
                $ctx->stash( 'current_archive_type', 'index' );
            }
        } else {
            $archive_type = $map ? $map->model : 'index';
            $ctx->stash( 'current_archive_type', $archive_type );
            $tmpl = $template->text;
            $primary = $table->primary;
            if ( $map->model == $obj->_model ) {
                $ctx->stash( 'current_archive_title', $obj->$primary );
            }
        }
        if ( $map && $map->container ) {
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
                        && $workspace ) {
                        $terms['workspace_id'] = $workspace->id;
                        $workspace = $obj->workspace;
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
        $ctx->stash( 'workspace', $workspace );
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
        $ctx->vars = [];
        $ctx->local_vars = [];
        $mapping = $map ? $map->mapping : 'preview.html';
        if ( isset( $obj ) && isset( $map ) && isset( $table ) ) {
            $ts = $ctx->stash( 'current_timestamp' )
                ? $ctx->stash( 'current_timestamp' ) : '';
            $url = $app->build_path_with_map( $obj, $map, $table, $ts, true );
            $ctx->vars['current_archive_url'] = $url;
        }
        $ctx->vars['current_archive_title'] = $ctx->stash( 'current_archive_title' );
        $preview = $app->tmpl_markup === 'mt' ? $ctx->build( $tmpl )
                                              : $app->build( $tmpl, $ctx );
        if (!$app->pv_redirect ) {
            echo $preview;
            exit();
        }
        $magic = $app->magic();
        $user = $app->user();
        $terms = ['name' => $magic, 'text' => $preview,
                  'user_id' => $user->id, 'kind' => 'PV'];
        $preview_url = $app->admin_url;
        if ( $workspace ) {
            $terms['workspace_id'] = $workspace->id;
            if ( $workspace->preview_url ) {
                $preview_url = $workspace->preview_url;
            }
        }
        $parts = explode( '.', $mapping );
        $extIndex = count( $parts ) - 1;
        $extension = strtolower( @$parts[ $extIndex ] );
        $mime_type = PTUtil::get_mime_type( $extension );
        $key = $app->encrypt( $user->id, $magic );
        $session = $db->model( 'session' )->get_by_key( $terms );
        $session->key( $mime_type );
        $session->start( time() );
        $session->expires( time() + $app->token_expires );
        $session->save();
        $app->redirect( $preview_url .
            "?__mode=preview&token={$magic}&key={$key}" . $app->workspace_param );
        exit();
    }

    function init_tags () {
        $app = $this;
        if ( $app->init_tags ) return;
        $core_tags = $app->core_tags;
        $core_tags->init_tags();
    }

    function build_path_with_map ( $obj, $mapping, $table, $ts = null, $url = false ) {
        $app = $this;
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
        $path = $app->tmpl_markup === 'mt' ? $ctx->build( $map_path )
                                           : $app->build( $map_path, $ctx );
        $path = trim( $path );
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
        if (!$url ) {
            $_path = str_replace( '/', DS, $_path );
        } else {
            $_path = str_replace( DS, '/', $_path );
        }
        return $_path;
    }

    function title_start_end ( $archive_type, $ts, $mapping = null ) {
        $app = $this;
        list( $title, $start, $end ) = ['', '', ''];
        $archive_type = strtolower( $archive_type );
        if ( $archive_type == 'yearly' ) {
            $y = substr( $ts, 0, 4 );
            $title = $y;
            $start = "{$y}0101000000";
            $end   = "{$y}1231235959";
        } elseif ( $archive_type == 'fiscal-yearly' ) {
            $y = substr( $ts, 0, 4 );
            $m = substr( $ts, 4, 2 );
            $year = $y;
            $fiscal_start = is_object( $mapping ) ? $mapping->fiscal_start : $mapping;
            $fy_end = $fiscal_start == 1 ? 12 : $fiscal_start - 1;
            $start_y = $y;
            $end_y = $fiscal_start == 1 ? $y : $y + 1;
            $fiscal_start = sprintf( '%02d', $fiscal_start );
            $fy_end = sprintf( '%02d', $fy_end );
            $start_ym = $start_y . $fiscal_start;
            $end_ym = $end_y . $fy_end;
            $ym = $y . $m;
            if ( $ym >= $start_ym && $ym <= $end_ym ) {
                $year = $y;
            } else if ( $end_ym < $ym ) {
                $year = $y + 1;
            }
            list( $start, $end ) = PTUtil::start_end_month( "{$end_ym}01000000" );
            $start = "{$start_ym}01000000";
            $title = $year;
        } elseif ( $archive_type == 'monthly' ) {
            $y = substr( $ts, 0, 4 );
            $m = substr( $ts, 4, 2 );
            list( $start, $end ) = PTUtil::start_end_month( "{$y}{$m}01000000" );
            $title = "{$y}{$m}";
        }
        return [ $title, $start, $end ];
    }

    function set_relations ( $args, $ids, $add_only = false, &$errors = [] ) {
        $app = $this;
        $is_changed = false;
        if (!$add_only ) {
            $relations = $app->db->model( 'relation' )->load( $args );
            if ( is_array( $relations ) && !empty( $relations ) ) {
                $removes = [];
                foreach ( $relations as $rel ) {
                    if (! in_array( $rel->to_id, $ids ) ) {
                        // $rel->remove();
                        $removes[] = $rel;
                        $is_changed = true;
                    }
                }
                if ( $is_changed ) {
                    if (! $app->db->model( 'relation' )->remove_multi( $removes ) ) {
                        $errors[] = $app->translate(
                            'An error occurred while updating the related object(s).' );
                        return false;
                    }
                }
            }
        }
        $i = 0;
        $relations = [];
        foreach ( $ids as $id ) {
            $id = (int) $id;
            if (!$id ) continue;
            $i++;
            $terms = $args;
            $terms['to_id'] = $id;
            $rel = $app->db->model( 'relation' )->get_by_key( $terms );
            if (!$rel->id || $rel->order != $i ) {
                $rel->order( $i );
                if (! $rel->id ) $rel->relation_id = '';
                // $rel->save();
                $relations[] = $rel;
                $is_changed = true;
            }
        }
        if (! empty( $relations ) ) {
            if (! $app->db->model( 'relation' )->update_multi( $relations ) ) {
                $errors[] = $app->translate(
                    'An error occurred while updating the related object(s).' );
                return false;
            }
        }
        return $is_changed;
    }

    function run_callbacks ( &$cb, $key, &$params = null, &$args = true, &$extra = null ) {
        $app = $this;
        $cb_name = $cb['name'];
        $all_callbacks = isset( $app->callbacks[ $cb_name ][ $key ] ) ?
            $app->callbacks[ $cb_name ][ $key ] : [];
        if (! empty( $all_callbacks ) ) {
            ksort( $all_callbacks );
            foreach ( $all_callbacks as $callbacks ) {
                foreach ( $callbacks as $callback ) {
                    list( $meth, $class ) = $callback;
                    $res = true;
                    if ( function_exists( $meth ) ) {
                        $res = $meth( $cb, $app, $params, $args, $extra );
                    } else if ( $class && method_exists( $class, $meth ) ) {
                        $res = $class->$meth( $cb, $app, $params, $args, $extra );
                    }
                    if (!$res ) return false;
                }
            }
        }
        return true;
    }

    function run_hooks ( $name ) {
        $app = $this;
        $_hooks = $app->hooks;
        $_hooks = isset( $_hooks[ $name ] ) ? $_hooks[ $name ] : null;
        if (! $_hooks ) return;
        $components = $app->class_paths;
        foreach ( $_hooks as $hooks ) {
            foreach ( $hooks as $hook ) {
                $plugin = strtolower( $hook['component'] );
                $component = $app->component( $plugin );
                if (!$component ) $component = $app->autoload_component( $plugin );
                if (! $component ) continue;
                $method = $hook['method'];
                if ( method_exists( $component, $method ) ) {
                    $component->$method( $app );
                }
            }
        }
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
        if ( $model === 'template' ) {
            $app->register_callback( 'template', 'delete_filter',
                                 'delete_filter_template', 5, $app );
        } else if ( $model === 'role' ) {
            $app->register_callback( 'role', 'post_delete', 'post_save_role', 5, $app );
        } else if ( $model === 'permission' ) {
            $app->register_callback( 'permission', 'post_delete',
                                 'post_save_permission', 5, $app );
        } else if ( $model === 'table' ) {
            $app->register_callback( 'table', 'post_delete', 'post_delete_table', 5, $app );
            $app->register_callback( 'table', 'delete_filter',
                                 'delete_filter_table', 5, $app );
        } else if ( $model === 'field' ) {
             $app->register_callback( 'field', 'post_delete', 'post_delete_field', 5, $app );
        }
        $app->init_callbacks( $model, 'delete' );
        $db->caching = false;
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
        $remove_objects = [];
        $label = $app->translate( $table->label );
        $errstr = $app->translate( 'An error occurred while deleting %s.', $label );
        foreach( $objects as $obj ) {
            $original = clone $obj;
            $callback = ['name' => 'pre_delete', 'error' => '' ];
            $pre_delete = $app->run_callbacks( $callback, $model, $obj, $original );
            if ( $msg = $callback['error'] ) {
                $errors[] = $msg;
            }
            if ( !empty( $errors ) || !$pre_delete ) {
                return $app->forward( $model, join( "\n", $errors ) );
            }
            if (!$app->can_do( $model, 'delete', $obj ) ) {
                $app->error( 'Permission denied.' );
            }
            // todo begin and finish work for remove child_classes
            if ( ! isset( $status_published ) && $obj->has_column( 'status' ) ) {
                $status_published = $app->status_published( $obj->_model );
            }
            $error = false;
            if (!$obj->has_column( 'rev_type' ) || ( $obj->has_column( 'rev_type' ) &&
                $obj->rev_type == 0 ) ) {
                if ( count( $objects ) == 1 ) {
                    $original = clone $obj;
                    if ( isset( $status_published ) ) {
                        $obj->status( 1 );
                        $obj->save();
                        $original->status( $status_published );
                    }
                    $app->publish_obj( $obj, $original, true );
                } else {
                    if ( isset( $status_published ) ) {
                        if ( $obj->status == $status_published ) {
                            $remove_objects[] = $obj;
                        }
                    } else {
                        $remove_objects[] = $obj;
                    }
                }
            }
            $db->begin_work();
            $app->remove_object( $obj, $table, $error );
            if ( $error ) {
                return $app->rollback( $errstr );
            } else {
                $db->commit();
            }
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
            $callback = ['name' => 'post_delete'];
            $app->run_callbacks( $callback, $model, $obj, $original );
        }
        if ( count( $objects ) > 1 ) {
            $published_on_request = [];
            foreach ( $remove_objects as $obj ) {
                $terms = ['model' => $obj->_model, 'container' => $obj->_model ];
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
                foreach ( $mappings as $mapping ) {
                    if ( $obj->_model === 'template' ) {
                        if ( $obj->id != $mapping->template_id ) {
                            continue;
                        }
                    }
                    $original = clone $obj;
                    if ( isset( $status_published ) ) {
                        $obj->status( 1 );
                        $original->status( $status_published );
                    }
                    $app->publish_dependencies( $obj, $original,
                                                $mapping, true, $published_on_request );
                }
            }
        }
        $app->redirect( $app->admin_url .
            "?__mode=view&_type=list&_model={$model}&deleted=1" . $app->workspace_param );
    }

    function remove_object ( $obj, $table, &$error = false ) {
        $app = $this;
        $db = $app->db;
        $class = $table->name;
        if ( prototype_auto_loader( $class ) ) {
            new $class();
        }
        $model = $obj->_model;
        $meta_objs = $db->model( 'meta' )->load(
            ['model' => $model, 'object_id' => $obj->id ], null, 'id' );
        if (! empty( $meta_objs ) ) {
            if (!$db->model( 'meta' )->remove_multi( $meta_objs ) ) {
                $error = true;
                return;
            }
        }
        if ( $model !== 'urlinfo' ) {
            if ( prototype_auto_loader( 'urlinfo' ) ) {
                new urlinfo;
            }
            $url_objs = $db->model( 'urlinfo' )->load(
                ['model' => $model, 'object_id' => $obj->id ], null, 'id,file_path' );
            if ( $model === 'urlmapping' || $model === 'template' ) {
                $urlinfo = new urlinfo();
                $map_ids = [];
                if ( $model === 'urlmapping' ) {
                    $map_ids[] = $obj->id;
                } else {
                    $maps = $db->model( 'urlmapping' )->load(
                        ['template_id' => $obj->id ], null, 'id' );
                    foreach ( $maps as $map ) {
                        $map_ids[] = $map->id;
                    }
                }
                if (! empty( $map_ids ) ) {
                    $_url_objs = $db->model( 'urlinfo' )->load(
                        ['urlmapping_id' => ['IN' => $map_ids ] ], null, 'id,file_path' );
                    $url_objs = array_merge( $url_objs, $_url_objs );
                }
            }
            foreach ( $url_objs as $ui ) {
                if (!$ui->remove() ) {
                    $error = true;
                    return;
                }
            }
        }
        $children = $table->child_tables ? explode( ',', $table->child_tables ) : [];
        foreach ( $children as $child ) {
            $child_objs = $db->model( $child )->load(
                [ $model . '_id' => $obj->id ] );
            $child_table = $app->get_table( $child );
            $_child_objs = [];
            foreach ( $child_objs as $child_obj ) {
                if ( $child_table ) {
                    $app->remove_object( $child_obj, $child_table, $error );
                } else {
                    $_child_objs[] = $child_obj;
                }
            }
            if (! empty( $_child_objs ) ) {
                if (! $db->model( $child )->remove_multi( $_child_objs ) ) {
                    $error = true;
                    return;
                }
            }
        }
        $relations = $db->model( 'relation' )->load(
            ['from_obj' => $obj->_model, 'from_id' => $obj->id ], null, 'id' );
        if (! empty( $relations ) ) {
            if (!$db->model( 'relation' )->remove_multi( $relations ) ) {
                $error = true;
                return;
            }
        }
        $relations = $db->model( 'relation' )->load(
            ['to_obj' => $obj->_model, 'to_id' => $obj->id ], null, 'id' );
        if (! empty( $relations ) ) {
            if (!$db->model( 'relation' )->remove_multi( $relations ) ) {
                $error = true;
                return;
            }
        }
        if ( $obj->has_column( 'rev_type' ) && ! $obj->rev_type ) {
            $revisions = $db->model( $obj->_model )->load(
                ['rev_object_id' => $obj->id ], null, 'id' );
            if (! empty( $relations ) ) {
                if (!$db->model( $obj->_model )->remove_multi( $revisions ) ) {
                    $error = true;
                    return;
                }
            }
        }
        if ( $table->allow_comment ) {
            $comments = $db->model( 'comment' )->load(
              ['object_id' => $obj->id, 'model' => $obj->_model ], null, 'id' );
            if (! empty( $comments ) ) {
                if (!$db->model( 'comment' )->remove_multi( $comments ) ) {
                    $error = true;
                    return;
                }
            }
        }
        if ( $table->hierarchy ) {
            $children = $db->model( $obj->_model )->load(
                ['parent_id' => $obj->id ] );
            $_children = [];
            foreach ( $children as $child ) {
                $child->parent_id( $obj->parent_id );
                $_children[] = $child;
            }
            if (! empty( $_children ) ) {
                if (!$db->model( $obj->_model )->update_multi( $_children ) ) {
                    $error = true;
                    return;
                }
            }
        }
        $res = $obj->remove();
        if (!$res ) $error = true;
        return $res;
    }

    function return_to_dashboard ( $options = [], $system = false ) {
        $app = $this;
        $workspace = $app->workspace();
        $url = $app->admin_url . "?__mode=dashboard";
        if ( $workspace && ! $system ) {
            $app->redirect( $url . '&workspace_id=' . $workspace->id );
        }
        if (! empty( $options ) ) $url .= '&' . http_build_query( $options );
        $app->redirect( $url );
    }

    function workspace () {
        $app = $this;
        if ( $app->stash( 'workspace' ) ) return $app->stash( 'workspace' );
        if ( $id = $app->param( 'workspace_id' ) ) {
            $cache_key = 'workspace' . DS . $id . DS . 'object__c';
            $id = (int) $id;
            if (!$id ) return null;
            if ( $workspace = $app->get_cache( $cache_key, 'workspace' ) ) {
                $app->stash( 'workspace', $workspace );
                return $workspace;
            }
            $workspace = $app->db->model( 'workspace' )->load( $id );
            $app->stash( 'workspace', $workspace );
            $app->set_cache( $cache_key, $workspace );
            return $workspace ? $workspace : null;
        }
        return null;
    }

    function get_table ( $model ) {
        $app = $this;
        if ( $app->stash( 'table:' . $model ) ) {
            return $app->stash( 'table:' . $model );
        }
        $cache_key = $model . DS . 'properties__c';
        if ( $table = $app->get_cache( $cache_key, 'table' ) ) {
            $app->stash( 'table:' . $model, $table );
            return $table;
        }
        $table = $app->db->model( 'table' )->load( ['name' => $model ], ['limit' => 1] );
        if ( is_array( $table ) && !empty( $table ) ) {
            $table = $table[0];
            $app->set_cache( $cache_key, $table );
            $app->stash( 'table:' . $model, $table );
            return $table;
        }
        return null;
    }

    function set_cache ( $id, $data = [] ) {
        if ( is_object( $data ) ) $data = $data->get_values();
        $app = $this;
        if (! $app->caching ) return;
        $ctx = $app->ctx;
        if ( $driver = $ctx->cachedriver ) {
            return $driver->set( $id, $data, $app->cache_expires );
        }
        if (! is_string( $data ) ) $data = serialize( $data );
        $cache_path = __DIR__ . DS . 'cache' . DS . $id;
        $app->fmgr->put( $cache_path, $data );
    }

    function get_cache ( $id, $model = null, $multiple = false ) {
        $app = $this;
        if (! $app->caching ) return;
        $ctx = $app->ctx;
        if ( $driver = $ctx->cachedriver ) {
            if ( $objects = $driver->get( $id, $app->cache_expires ) ) {
                if ( $multiple && $model ) {
                    $objs = [];
                    foreach ( $objects as $obj ) {
                        $objs[] = $app->db->model( $model )->__new( $obj );
                    }
                    return $objs;
                } else {
                    return $model ? $app->db->model( $model )->__new( $objects ) : $objects;
                }
            }
            return null;
        }
        $cache_path = __DIR__ . DS . 'cache' . DS . $id;
        if ( file_exists( $cache_path ) && filemtime( $cache_path ) >
            ( time() - $app->cache_expires ) ) {
            if ( $multiple && $model ) {
                $objects = unserialize( file_get_contents( $cache_path ) );
                $objs = [];
                foreach ( $objects as $obj ) {
                    $objs[] = $app->db->model( $model )->__new( $obj );
                }
                return $objs;
            } else {
                $data = file_get_contents( $cache_path );
                $data = ( $unserialized = @unserialize( $data ) )
                      !== false ? $unserialized : $data;
                return $model ? $app->db->model( $model )->__new( $data ) : $data;
            }
        }
    }

    function clear_cache ( $id ) {
        if (! $id ) return $this->clear_all_cache();
        $ctx = $this->ctx;
        if ( $driver = $ctx->cachedriver ) {
            $_prefix = $ctx->cachedriver->_prefix;
            $cache_key = $_prefix . $id;
            if ( strpos( $id, DS ) !== false ) {
                $keys = $driver->getAllKeys();
                foreach ( $keys as $key ) {
                    if ( strpos( $key, $cache_key ) === 0 ) {
                        $driver->delete( $key, true );
                    }
                }
                return true;
            } else {
                return $driver->delete( $id );
            }
        }
        $cache_path = $this->path() . DS . 'cache' . DS . $id;
        if ( is_dir( $cache_path ) ) {
            $cache_path = rtrim( $cache_path, DS );
            return PTUtil::remove_dir( $cache_path );
        } else if ( file_exists( $cache_path ) ) {
            return unlink( $cache_path );
        }
    }

    function clear_all_cache () {
        $ctx = $this->ctx;
        if ( $driver = $ctx->cachedriver ) {
            $_prefix = $ctx->cachedriver->_prefix;
            $keys = $driver->getAllKeys();
            foreach ( $keys as $key ) {
                if ( strpos( $key, $_prefix ) === 0 ) {
                    $driver->delete( $key, true );
                }
            }
            return true;
        }
        $cache_path = $this->path() . DS . 'cache';
        return PTUtil::remove_dir( $cache_path, true );
    }

    function save_filter_obj ( $cb, $pado, $obj ) {
        if ( $obj->has_column( 'uuid' ) && ! $obj->uuid ) {
            $obj->uuid( $this->generate_uuid() );
        }
        return true;
    }

    function generate_uuid () {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), 
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }

    function flush_cache ( $cb, $pado, $obj ) {
        $this->clear_cache( $obj->_model . DS . $obj->id . DS );
        return true;
    }

    function get_object ( $model, $required = [] ) {
        $app = $this;
        if (!$model ) $model = $app->param( '_model' );
        $db = $app->db;
        $obj = $db->model( $model )->new();
        $scheme = $app->get_scheme_from_db( $model );
        $table = $app->get_table( $model );
        if (!$scheme ) return null;
        $primary = $scheme['indexes']['PRIMARY'];
        $objects = [];
        if ( is_array( $required ) && empty( $required ) ) {
            $required = [];
            $required[] = 'id';
            if ( $model === 'urlinfo' ) {
                $required[] = 'is_published';
                $required[] = 'file_path';
            }
            if ( $model === 'table' ) {
                $required[] = 'name';
            }
            if ( $table && $table->hierarchy ) {
                $required[] = 'parent_id';
            }
            if ( $table && $table->revisable ) {
                $required[] = 'rev_type';
            }
            if ( $table && $table->has_status ) {
                $required[] = 'status';
            }
            if ( $table && $table->sortable ) {
                $required[] = 'order';
            }
            if ( $obj->has_column( 'workspace_id' ) ) {
                $required[] = 'workspace_id';
            }
        }
        if ( is_array( $required ) ) {
            $required = join( ',', $required );
        }
        if ( $app->param( 'all_selected' ) ) {
            $filter_params = json_decode(
                $app->decrypt( $app->param( 'filter_params' ) ), true );
            $terms = $filter_params['terms'];
            $args = $filter_params['args'];
            $extra = $filter_params['extra'];
            $original = "'${extra}'";
            $quoted = $db->quote( $extra );
            if ( $original != $quoted ) {
                return $app->error( 'Invalid request.' );
            }
            $objects = $obj->load( $terms, $args, $required, $extra );
            return $objects;
        } else {
            $id = $app->param( 'id' );
            if ( is_array( $id ) ) {
                array_walk( $id, function( &$id ) {
                    $id = (int) $id;
                });
                $objects = $obj->load( ['id' => ['IN' => $id ] ], null, $required );
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
        return [];
    }

    function forward ( $model, $error = '' ) {
        $app = $this;
        $ctx = $app->ctx;
        $ctx->vars['error'] = $error;
        if ( $app->param( '_type' ) === 'edit' || $app->mode === 'save' ) {
            $app->assign_params( $app, $ctx );
            $ctx->vars['forward_params'] = 1;
            return $app->view( $app, $model, 'edit' );
        }
        return $app->view( $app, $model, 'list' );
    }

    function pre_listing ( &$cb, $app, &$terms, &$args, &$extra ) {
        $model = $app->param( '_model' );
        $workspace_id = $app->workspace() ? $app->workspace()->id : 0;
        $user_id = $app->user()->id;
        $filter_primary = ['key' => $model, 'user_id' => $user_id,
                           'workspace_id' => $workspace_id,
                           'kind'  => 'list_filter_primary'];
        $op_map = ['gt' => '>', 'lt' => '<', 'eq' => '=', 'ne' => '!=', 'ge' => '>=',
                   'le' => '<=', 'ct' => 'LIKE', 'nc' => 'NOT LIKE', 'bw' => 'LIKE',
                   'ew' => 'LIKE'];
        $primary = $app->db->model( 'option' )->get_by_key( $filter_primary );
        if ( $app->param( '_detach_filter' ) ) {
            $app->param( '_filter', 0 );
            if ( $primary->id ) $primary->remove();
        } else {
            if ( $primary->id ) {
                $app->param( '_filter', 1 );
                if ( $primary->object_id ) {
                    $app->param( '_filter_id', $primary->object_id );
                } else if ( $primary->value == 'system_filter' ) {
                    $app->param( 'select_system_filters', $primary->extra );
                    $app->param( '_system_filters_option', $primary->data );
                }
            }
        }
        if ( $app->param( '_filter' ) ) {
            $params = $app->param();
            $conditions = [];
            $scheme = $cb['scheme'];
            $table  = $cb['table'];
            $column_defs = $scheme['column_defs'];
            $list_props = $scheme['list_properties'];
            $obj = $app->db->model( $model )->new();
            $system_filter = $app->param( 'select_system_filters' );
            $apply_filter = false;
            if ( $system_filter ) {
                $filters_option = $app->param( '_system_filters_option' );
                $filters_class = new PTSystemFilters();
                $filters = $filters_class->get_system_filters( $model, $system_filters );
                if (! isset( $filters[ $system_filter ] ) ) {
                    if ( strpos( $system_filter, 'filter_class_' ) === 0 ) {
                        $filter = [];
                        $class_type = preg_replace(
                                        '/^filter_class_/', '', $system_filter );
                        $_label = $app->translate( $class_type, null, null, 'default' );
                        $_label = $app->translate( $_label );
                        $filter['name'] = $system_filter;
                        $filter['label'] = $_label;
                        $filter['option'] = $class_type;
                        $filter['method'] = 'filter_class';
                        $filter['component'] = $filters_class;
                        $filters[ $system_filter ] = $filter;
                    }
                }
                if ( isset( $filters[ $system_filter ] ) ) {
                    $filter = $filters[ $system_filter ];
                    $app->ctx->vars['current_filter_name'] = $filter['label'];
                    $app->ctx->vars['current_system_filter'] = $filter['name'];
                    $component = $filter['component'];
                    $meth = $filter['method'];
                    if ( method_exists( $component, $system_filter ) ) {
                        $meth = $system_filter;
                    }
                    if ( method_exists( $component, $meth ) ) {
                        $option = isset( $filters_option ) ? $filters_option : '';
                        $component->$meth( $app, $terms, $model, $option );
                        $primary->object_id( 0 );
                        $primary->value( 'system_filter' );
                        $primary->extra( $filter['name'] );
                        $primary->data( $filters_option );
                        if (!$app->param( 'dialog_view' ) ) {
                            $primary->save();
                        }
                        $apply_filter = true;
                    }
                }
            } else {
                $_filter_id = $app->param( '_filter_id' );
                if ( $_filter_id ) $_filter_id = (int) $_filter_id;
                if ( $_filter_id ) {
                    $filter_terms = ['user_id' => $user_id,
                                     'workspace_id' => $workspace_id,
                                     'id'    => $_filter_id,
                                     'key'   => $model,
                                     'kind'  => 'list_filter'];
                    $filter = $app->db->model( 'option' )->get_by_key( $filter_terms );
                    if (!$filter->id ) {
                        $terms['id'] = 0;
                        return;
                    }
                    if ( $primary->object_id != $filter->id ) {
                        $primary->object_id( $filter->id );
                        $primary->data( $filter->data );
                        $primary->save();
                    }
                    $filter_val = $filter->data;
                    $app->ctx->vars['current_filter_id'] = $_filter_id;
                    $app->ctx->vars['current_filter_name'] = $filter->value;
                    if ( $filter_val ) {
                        $filters = json_decode( $filter_val, true );
                        foreach ( $filters as $filter => $values ) {
                            $params[ $filter ] = $values;
                        }
                    }
                }
            }
            if (!$apply_filter ) {
                $filter_params = [];
                $filter_and = false;
                foreach ( $params as $key => $conds ) {
                    if ( strpos( $key, '_filter_cond_' ) === 0 ) {
                        $filter_params[ $key ] = $conds;
                        $cond = [];
                        $key = preg_replace( '/^_filter_cond_/', '', $key );
                        $values = isset( $params['_filter_value_' . $key ] )
                                ? $params['_filter_value_' . $key ] : [];
                        $filter_params[ '_filter_value_' . $key ] = $values;
                        if (! isset( $column_defs[ $key ]['type'] ) ) continue;
                        $type = $column_defs[ $key ]['type'];
                        $i = 0;
                        $_values = [];
                        foreach ( $conds as $val ) {
                            $value = $values[ $i ];
                            if (! isset( $op_map[ $val ] ) ) continue;
                            $op = $op_map[ $val ];
                            if ( $type == 'datetime' ) {
                                $value = $obj->db2ts( $value );
                                $value = $obj->ts2db( $value );
                            } else if ( $op === 'LIKE' ) {
                                if ( $val === 'bw' ) {
                                    $value = $app->db->escape_like( $value, false, 1 );
                                } elseif ( $val === 'ew' ) {
                                    $value = $app->db->escape_like( $value, 1, false );
                                } else {
                                    $value = $app->db->escape_like( $value, 1, 1 );
                                }
                            }
                            $_values[] = $value;
                            $i++;
                        }
                        $values = $_values;
                        $list_type = isset( $list_props[ $key ] ) ? $list_props[ $key ] : '';
                        if ( $type === 'relation' || strpos( $list_type, ':' ) !== false ) {
                            $_cond = [];
                            list( $rel_model, $rel_col ) = ['', ''];
                            if (!$list_type ) {
                                $col = $app->db->column( 'column' )->get_by_key(
                                                        ['name' => $key,
                                                         'table_id' => $table->id ] );
                                if (!$col->id ) continue;
                                $rel_model = $col->options;
                                $_table = $app->db->column( 'table' )->get_by_key(
                                                                ['name' => $rel_model ] );
                                if (!$_table->id ) continue;
                                $rel_col = $_table->primary;
                            } else {
                                $props = explode( ':', $list_type );
                                if ( count( $props ) > 2 ) {
                                    $rel_model = $props[1];
                                    $rel_col = $props[2];
                                }
                            }
                            if ( $rel_model == '__any__' ) {
                                if ( isset( $params['_filter_value_model'] ) ) {
                                    if ( count( $params['_filter_value_model'] ) == 1 ) {
                                        $rel_model = $params['_filter_value_model'][0];
                                        $filter_and = true;
                                        $args['and_or'] = 'AND';
                                    }
                                }
                            }
                            if (!$rel_model || ! $rel_col ) continue;
                            $rel_table = $app->get_table( $rel_model );
                            if (!$rel_table ) {
                                continue;
                            }
                            if ( $rel_col == 'null' ) {
                                $rel_col = $rel_table->primary;
                            }
                            $rel_obj = $app->db->model( $rel_model )->new();
                            $i = 0;
                            foreach ( $conds as $val ) {
                                $value = $values[ $i ];
                                if ( count( $values ) > 2 ) {
                                    $_cond[ $op ] = [ 'AND' => $value ];
                                } else {
                                    $_cond[ $op ] = $value;
                                }
                                ++$i;
                            }
                            $rel_objs = $rel_obj->load( [ $rel_col => $_cond ] );
                            if ( is_array( $rel_objs ) && !empty( $rel_objs ) ) {
                                $rel_ids = [];
                                foreach ( $rel_objs as $_obj ) {
                                    $rel_ids[] = (int) $_obj->id;
                                }
                                if ( $type === 'relation' ) {
                                    $rel_terms = ['to_id'    => ['IN' => $rel_ids ],
                                                  'to_obj'   => $rel_model,
                                                  'from_obj' => $model ];
                                    $relations =
                                        $app->db->model( 'relation' )->load( $rel_terms );
                                    if ( is_array( $relations ) && !empty( $relations ) ) {
                                        $from_ids = [];
                                        foreach ( $relations as $rel ) {
                                            $from_ids[] = (int) $rel->from_id;
                                        }
                                        $from_ids = array_unique( $from_ids );
                                        $terms['id'] = ['AND' => ['IN' => $from_ids ] ];
                                    } else {
                                        $terms['id'] = 0; // No object found.
                                    }
                                } else {
                                    if ( $filter_and ) {
                                        $terms[ $key ] = ['AND' => ['IN' => $rel_ids ] ];
                                    } else {
                                        $terms[ $key ] = ['IN' => $rel_ids ];
                                    }
                                }
                            } else {
                                $terms['id'] = 0;  // No object found.
                            }
                        } else {
                            $cnt = 0;
                            foreach ( $conds as $val ) {
                                $value = $values[ $cnt ];
                                $op = $op_map[ $val ];
                                if ( $cnt ) {
                                    $orig = [];
                                    $orig[] = $cond;
                                    if (! is_array( $orig ) ) {
                                        $orig = [ $orig ];
                                    }
                                    $orig[] = [ $op => $value ];
                                    $cond = $orig;
                                } else {
                                    $cond[ $op ] = $value;
                                }
                                ++$cnt;
                            }
                            $conditions[ $key ] = $cond;
                        }
                    }
                }
                foreach ( $conditions as $col => $cond ) {
                    $terms[ $col ] = ['AND' => $cond ];
                }
                if ( $filter_name = $app->param( '_save_filter_name' ) ) {
                    $filter_terms = ['user_id' => $app->user()->id,
                                     'workspace_id' => $workspace_id,
                                     'key'   => $model,
                                     'value' => $filter_name,
                                     'kind'  => 'list_filter'];
                    $filter_terms['data'] = json_encode( $filter_params );
                    $filter = $app->db->model( 'option' )->get_by_key( $filter_terms );
                    $filter->save();
                    if ( $primary->object_id != $filter->id ) {
                        $primary->object_id( $filter->id );
                        $primary->data( $filter->data );
                        $primary->save();
                    }
                    $app->ctx->vars['current_filter_id'] = $filter->id;
                    $app->ctx->vars['current_filter_name'] = $filter_name;
                } else {
                    if (! isset( $app->ctx->vars['current_filter_name'] ) ) 
                      $app->ctx->vars['current_filter_name'] = $app->translate( 'Custom' );
                }
            }
        }
        return true;
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
        if (!$obj->template_id || ! $obj->model ) {
            $cb['error'] = $app->translate( 'Model and View are required.' );
            return false;
        }
        return true;
    }

    function save_filter_table ( &$cb, $app, &$obj ) {
        $upgrader = new PTUpgrader;
        return $upgrader->save_filter_table( $cb, $app, $obj );
    }

    function post_delete_table ( &$cb, $app, &$obj ) {
        $app->db->can_drop = true;
        $upgrader = new PTUpgrader;
        $upgrader->drop( $obj );
        $app->db->drop( $obj->name );
        $app->clear_cache( $obj->name );
        return true;
    }

    function post_save_workspace ( $cb, $app, $obj, $original ) {
        if ( $original->site_url !== $obj->site_url ||
            $original->site_path !== $obj->site_path ) {
            $app->rebuild_urlinfo( $obj->site_url, $obj->site_path, $obj );
        }
        return true;
    }

    function pre_save_user ( $cb, $app, &$obj, $original ) {
        if ( $app->user()->is_superuser ) {
            return true;
        }
        $not_changes = ['is_superuser', 'status', 'lock_out', 'last_login_on',
            'uuid', 'name', 'lock_out_on', 'created_on'];
        foreach ( $not_changes as $col ) {
            $obj->$col( $original->$col );
        }
        return true;
    }

    function post_save_field ( $cb, $app, $obj, $original ) {
        $relations = $app->get_relations( $obj, 'table', 'models' );
        $table_ids = [];
        foreach ( $relations as $relation ) {
            $table_ids[] = $relation->to_id;
        }
        $basename = $original->basename;
        $orig_relations = $cb['orig_relations'];
        foreach ( $orig_relations as $relation ) {
            if ( $relation->name != 'models' ) continue;
            $table_id = $relation->to_id;
            if (! in_array( $table_id, $table_ids ) ) {
                $table_id = (int) $table_id;
                $table = $app->db->model( 'table' )->load( $table_id );
                if (! $table ) continue;
                $terms =
                    ['kind' => 'customfield', 'key' => $basename, 'model' => $table->name ];
                $meta_fields = $app->db->model( 'meta' )->load( $terms );
                $app->db->model( 'meta' )->remove_multi( $meta_fields );
            }
        }
        return true;
    }

    function post_delete_field ( $cb, $app, $obj, $original ) {
        $terms = ['kind' => 'customfield', 'field_id' => $obj->id ];
        $meta_fields = $app->db->model( 'meta' )->load( $terms );
        $app->db->model( 'meta' )->remove_multi( $meta_fields );
        return true;
    }

    function post_save_permission ( $cb, $app, $obj ) {
        $sessions =
            $app->db->model( 'session' )->load( ['user_id' => $obj->id,
                'name' => 'user_permissions', 'kind' => 'PM' ] );
        if (! empty( $sessions ) ) {
            $app->db->model( 'session' )->remove_multi( $sessions );
        }
        return true;
    }

    function post_save_role ( $cb, $app, $obj ) {
        if ( empty( $cb['changed_cols'] ) ) return;
        $relations = $app->db->model( 'relation' )->load( ['from_obj' => 'permission',
            'to_obj' => 'role', 'name' => 'roles', 'to_id' => $obj->id ] );
        $ids = [];
        foreach ( $relations as $rel ) {
            $ids[] = $rel->from_id;
        }
        if (! empty( $ids ) ) {
            $permissions = 
                $app->db->model( 'permission' )->load( ['id' => ['IN' => $ids ] ] );
            $user_ids = [];
            foreach ( $permissions as $perm ) {
                $user_ids[] = $perm->user_id;
            }
            if (! empty( $user_ids ) ) {
                $user_ids = array_unique( $user_ids );
                $sessions =
                    $app->db->model( 'session' )->load( ['user_id' => ['IN' => $user_ids ],
                        'name' => 'user_permissions', 'kind' => 'PM' ] );
                if (! empty( $sessions ) ) {
                    $app->db->model( 'session' )->remove_multi( $sessions );
                }
            }
        }
        return true;
    }

    function rebuild_urlinfo ( $url, $path, $workspace = null ) {
        $terms = $workspace ? ['workspace_id' => $workspace->id ] : null;
        // TODO load_iter and required columns or bulk update or begin_work
        $app = $this;
        if ( $terms ) {
            $urls = $app->db->model( 'urlinfo' )->load( $terms );
        } else {
            $urls = $app->db->model( 'urlinfo' )->load();
        }
        $dirs = [];
        $update_info = [];
        foreach ( $urls as $url_info ) {
            $map = $url_info->urlmapping;
            if ( $workspace && $map && $workspace->workspace_id != $map->workspace_id ) {
                continue;
            } else if (!$workspace && $map && $map->workspace_id ) {
                continue;
            }
            $relative_path = $url_info->relative_path;
            $url_path = str_replace( '%r', $path, $relative_path );
            $url_path = str_replace( '/', DS, $url_path );
            $url_url = str_replace( '%r/', $url, $relative_path );
            $url_url = str_replace( DS, '/', $url_url );
            if ( $url_info->file_path !== $url_path || $url_info->url !== $url_url ) {
                if ( $url_info->file_path !== $url_path && $url_info->is_published ) {
                    if ( file_exists( $url_info->file_path ) ) {
                        if ( file_exists( $url_path ) ) {
                            $dirs[ dirname( $url_path ) ] = true;
                            $app->fmgr->rename( $url_path, "{$url_path}.bk" );
                        }
                        $app->fmgr->mkpath( dirname( $url_path ) );
                        $app->fmgr->rename( $url_info->file_path, $url_path );
                        if ( $app->fmgr->exists( "{$url_path}.bk" ) ) {
                            $app->fmgr->unlink( "{$url_path}.bk" );
                        }
                    }
                }
                $url_info->file_path( $url_path );
                $url_info->url( $url_url );
                // $url_info->save();
                $update_info[] = $url_info;
            }
        }
        $app->db->model( 'urlinfo' )->update_multi( $update_info );
        $app->fmgr->remove_empty_dirs( $dirs );
    }

    function post_save_table ( $cb, $app, $obj, $original ) {
        $upgrader = new PTUpgrader;
        return $upgrader->post_save_table( $cb, $app, $obj, $original );
    }

    function post_save_asset ( $cb, $app, $obj ) {
        $db = $app->db;
        $metadata = $app->db->model( 'meta' )->get_by_key(
                 ['model' => 'asset', 'object_id' => $obj->id,
                  'kind' => 'metadata', 'key' => 'file'] );
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
        if ( $workspace = $obj->workspace ) {
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
        return true;
    }

    function publish ( $file_path, $obj, $key,
                       $mime_type = 'text/html', $type = 'file' ) {
        $app = $this;
        $fmgr = $app->fmgr;
        $cache_vars = $app->ctx->vars;
        $cache_local_vars = $app->ctx->local_vars;
        $cache_stash = $app->ctx->__stash;
        $remove_dirs = [];
        $table = $app->get_table( $obj->_model );
        if (!$table ) return;
        $db = $app->db;
        $ctx = clone $app->ctx;
        $ui_exists = false;
        $urlmapping_id = 0;
        $old_path = '';
        $publish = false;
        $unlink = false;
        $link_status = false;
        $template;
        $mapping = '';
        $urlmapping = null;
        $workspace = $app->workspace();
        if ( $obj->_model === 'workspace' ) {
            $workspace = $obj;
        } else {
            $workspace = $obj->workspace;
        }
        $date_based = false;
        $ctx->stash( 'current_container', '' );
        if ( is_object( $key ) ) {
            $urlmapping_id = $key->id;
            $urlmapping = $key;
            if ( $urlmapping->date_based ) $archive_date = $type;
            $type = $urlmapping->template_id ? 'archive' : 'model';
            $publish = $urlmapping->publish_file;
            $ui = $db->model( 'urlinfo' )->get_by_key(
                ['urlmapping_id' => $urlmapping->id, 'class' => 'archive',
                 'object_id' => $obj->id, 'model' => $obj->_model ] );
            $ui->file_path( $file_path );
            $ui->publish_file( $publish );
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
            $date_based = $urlmapping->date_based;
            $link_status = $urlmapping->link_status;
            $key = '';
            $ctx->stash( 'current_urlmapping', $urlmapping );
        } else {
            $ui = $db->model( 'urlinfo' )->get_by_key( ['file_path' => $file_path ] );
        }
        if ( $ui->id ) {
            if ( $key == $ui->key && $ui->model == $table->name &&
                $ui->urlmapping_id == $urlmapping_id && $ui->object_id == $obj->id ) {
                $ui_exists = true;
            } else {
                $file_ext = basename( $file_path );
                $file_ext = strpos( $file_ext, '.' ) === false ?
                    '' : preg_replace( '/^.*\.([^\.]*$)/', '$1', $file_ext );
                $basename = preg_replace( "/\.{$file_ext}$/", '', $file_path );
                $i = 1;
                $unique = false;
                while ( $unique === false ) {
                    $rename = $basename . '-' . $i . '.' . $file_ext;
                    $exists = $db->model( 'urlinfo' )->get_by_key(
                        ['file_path' => $rename ] );
                    if (!$exists->id ) {
                        $unique = true;
                        $file_path = $rename;
                        break;
                    }
                    $i++;
                }
                if ( $unique && $obj->has_column( 'basename' ) ) {
                    $obj->basename( pathinfo( $file_path )['filename'] );
                    $obj->save();
                }
                if ( $unique && $obj->has_column( 'file_name' ) ) {
                    $obj->basename( $file_path );
                    $obj->save();
                }
                if ( $unique ) {
                    $ui = clone $ui;
                    $ui->id = null;
                    $ui->save();
                }
            }
        }
        $base_url = $app->site_url;
        $base_path = $app->site_path;
        $asset_publish = $app->asset_publish;
        if ( $workspace ) {
            $base_url = $workspace->site_url;
            $base_path = $workspace->site_path;
            $asset_publish = $workspace->asset_publish;
            $ctx->stash( 'workspace', $workspace );
        }
        $file_path = str_replace( DS, '/', $file_path );
        $base_path = str_replace( DS, '/', $base_path );
        $search = preg_quote( $base_path, '/' );
        $relative_path = preg_replace( "/^{$search}\//", '', $file_path );
        $url = $base_url . $relative_path;
        $url = str_replace( DS, '/', $url );
        $relative_path = str_replace( '/', DS, $relative_path );
        $relative_url = preg_replace( '!^https{0,1}:\/\/.*?\/!', '/', $url );
        $ui->set_values( ['model' => $table->name,
                          'url' => $url,
                          'key' => $key,
                          'object_id' => $obj->id,
                          'relative_url' => $relative_url,
                          'urlmapping_id' => (int) $urlmapping_id,
                          'file_path' => $file_path,
                          'mime_type' => $mime_type,
                          'class' => $type,
                          'relative_path' => '%r' . DS . $relative_path,
                          'workspace_id' => $obj->workspace_id ] );
        if ( isset( $archive_date ) ) $ui->archive_date( $archive_date );
        if ( $obj->has_column( 'status' ) ) {
            $status_published = $app->status_published( $obj->_model );
            if ( $obj->status != $status_published ) {
                $unlink = true;
            }
        }
        $updated = true;
        if ( $type === 'file' || $publish ) {
            if ( $asset_publish || $publish ) {
                if ( $type === 'file' ) {
                    if ( $unlink ) {
                        if ( $fmgr->exists( $file_path ) ) {
                            $fmgr->unlink( $file_path );
                            $remove_dirs[ dirname( $file_path ) ] = true;
                        }
                        $ui->is_published( 0 );
                    } else {
                        $args = ['join' => [ 'urlinfo', 'meta_id' ], 'distinct' => 1 ];
                        $thumbnails = $db->model( 'meta' )->load( [
                            'object_id' => $obj->id, 'model' => $obj->_model ], $args );
                        foreach ( $thumbnails as $thumb ) {
                            $thumb_path = $thumb->urlinfo_file_path;
                            $md5 = $thumb->urlinfo_md5;
                            $data = $thumb->data;
                            $thumb_update = false;
                            $hash = md5( base64_encode( $data ) );
                            if ( file_exists( $thumb_path ) ) {
                                $old = $md5 ? $md5 :
                                md5( base64_encode( $fmgr->get( $thumb_path ) ) );
                                if ( $old !== $hash ) {
                                    $thumb_update = true;
                                }
                            } else {
                                $thumb_update = true;
                            }
                            if ( $thumb_update ) {
                                $fmgr->put( $thumb_path, $data );
                            }
                            if (! $md5 || $thumb_update ) {
                                $uid = (int) $thumb->urlinfo_id;
                                $uinfo = $db->model( 'urlinfo' )->load( $uid );
                                $uinfo->md5( $hash );
                                $uinfo->save();
                            }
                        }
                        $data = $obj->$key;
                        $md5 = $ui->md5;
                        $hash = md5( base64_encode( $data ) );
                        $file_saved = false;
                        if ( !$md5 && file_exists( $file_path ) ) {
                            $md5 = md5( base64_encode( $fmgr->get( $file_path ) ) );
                        }
                        if ( $md5 && $md5 == $hash ) {
                            if ( $ui->is_published != 1 || !$ui->md5 ) {
                                $ui->md5( $hash );
                                $ui->is_published( 1 );
                                $ui->save();
                            }
                            return $file_path;
                        }
                        $ui->md5( $hash );
                        if ( $fmgr->put( $file_path, $data ) !== false ) {
                            $ui->is_published( 1 );
                        } else {
                            $ui->is_published( 0 );
                        }
                    }
                } else {
                    if ( $unlink ) {
                        if ( $fmgr->exists( $file_path ) ) {
                            $fmgr->unlink( $file_path );
                            $remove_dirs[ dirname( $file_path ) ] = true;
                        }
                        $ui->is_published( 0 );
                    } else {
                        $tmpl = $template->text;
                        $compiled = $template->compiled;
                        $cache_key = $template->cache_key;
                        if ( $compiled && $cache_key ) {
                            $ctx->compiled[ $cache_key ] = $compiled;
                        }
                        $ctx->stash( 'current_template', $template );
                        if ( $obj->_model != 'template' ) {
                            $ctx->stash( 'current_context', $obj->_model );
                            $ctx->stash( $obj->_model, $obj );
                        } else {
                            $ctx->stash( 'current_context', '' );
                            $ctx->stash( $obj->_model, '' );
                        }
                        if ( $date_based ) {
                            $date_col = $app->get_date_col( $obj );
                            $ctx->stash( 'archive_date_based_col', $date_col );
                            $ts = $ui->db2ts( $ui->archive_date );
                            list( $title, $start, $end )
                                = $app->title_start_end( $date_based, $ts, $urlmapping );
                            $ctx->stash( 'archive_date_based',
                                $ctx->stash( 'current_container' ) );
                            $ctx->stash( 'current_timestamp', $start );
                            $ctx->stash( 'current_timestamp_end', $end );
                        } else {
                            $ctx->stash( 'current_timestamp', null );
                            $ctx->stash( 'current_timestamp_end', null );
                            $ctx->stash( 'archive_date_based', false );
                        }
                        $ctx->vars = [];
                        $ctx->vars['current_archive_type'] =
                            $ctx->stash( 'current_archive_type' );
                        $ctx->vars['current_archive_url'] = $url;
                        $ctx->local_vars = [];
                        if ( $publish != $ui->publish_file ) {
                            $ui->publish_file( $publish );
                            if ( $publish != 1 ) {
                                $ui->is_published( 0 );
                            }
                            $ui->save();
                        }
                        $continue = false;
                        if ( $publish == 5 && $app->param( '__save_and_publish' ) ) {
                            $continue = true;
                        }
                        if ( !$continue && $publish != 1 ) {
                            if ( $publish == 2 ) {
                                if ( $ui->id ) {
                                    $app->delayed[] = $ui->id;
                                } else {
                                    $continue = true;
                                }
                            } elseif ( $publish == 3 || $publish == 6 ) {
                                // on-demand or dynamic
                                if ( $fmgr->exists( $file_path ) ) {
                                    $fmgr->unlink( $file_path );
                                }
                                if (! $ui->id ) {
                                    $continue = true;
                                }
                            } elseif ( $publish == 4 && $ui->is_published ) {
                                $ui->is_published( 0 );
                                $ui->save();
                            }
                            if (! $continue ) return $file_path;
                        } else {
                            $data = $app->tmpl_markup === 'mt' ? $ctx->build( $tmpl )
                                                               : $app->build( $tmpl, $ctx );
                            $old_hash = $ui->md5;
                            $hash = md5( $data );
                            $ui->md5( $hash );
                            $app->ctx->vars = $cache_vars;
                            $app->ctx->local_vars = $cache_local_vars;
                            $app->ctx->__stash = $cache_stash;
                            if ( $fmgr->exists( $file_path ) ) {
                                $old = $old_hash
                                     ? $old_hash : md5( $fmgr->get( $file_path ) );
                                if ( $old === $hash ) {
                                    if (! $ui->is_published ) {
                                        $ui->is_published( 1 );
                                        $ui->save();
                                    }
                                    return $file_path;
                                }
                            }
                            if ( $updated ) {
                                if ( $fmgr->put( $file_path, $data )!== false ) {
                                    $ui->is_published( 1 );
                                }
                            }
                        }
                    }
                }
            } else {
                $ui->is_published( 0 );
                if ( $fmgr->exists( $file_path ) ) {
                    $fmgr->unlink( $file_path );
                    $remove_dirs[ dirname( $file_path ) ] = true;
                }
            }
        }
        if ( $unlink && $link_status ) {
            if ( $ui->id ) $ui->remove();
        } else {
            $date_based = $ctx->stash( 'archive_date_based' );
            if ( $date_based ) {
                $ui->archive_date( $ctx->stash( 'current_timestamp' ) );
            } else {
                $ui->archive_date = null;
            }
            $ui->archive_type( $ctx->stash( 'current_archive_type' ) );
            $mime_type = PTUtil::get_mime_type( pathinfo( $file_path )['extension'] );
            $ui->mime_type( $mime_type );
            if (!$ui_exists ) {
                $terms = ['model' => $ui->model, 'key' => $ui->key,
                          'object_id' => $ui->object_id,
                          'class' => $ui->class, 'urlmapping_id' => $ui->urlmapping_id,
                          'archive_type' => $ui->archive_type,
                          'archive_date' => $ui->archive_date,
                          'workspace_id' => $ui->workspace_id ];
                $old = $db->model( 'urlinfo' )->get_by_key( $terms );
                if ( $old->id ) {
                    $ui->id( $old->id );
                    $old_path = $old->file_path;
                    $old_path = str_replace( '/', DS, $old_path );
                    if ( $old_path && $old_path
                        !== $file_path && $fmgr->exists( $old_path ) ) {
                        $fmgr->unlink( $old_path );
                        $remove_dirs[ dirname( $old_path ) ] = true;
                    }
                }
            }
            $ui->save();
        }
        if ( $publish == 2 ) {
            $app->delayed[] = $ui->id;
        }
        $fmgr->remove_empty_dirs( $remove_dirs );
        return $file_path;
    }

    function publish_dependencies ( $obj, $original = null, $mapping, $publish = true,
        &$published_on_request = [] ) {
        $app = $this;
        $relations = [];
        $to_obj = $mapping->model;
        if ( $mapping->model !== 'template' ) {
            $to_obj = $mapping->model;
            $relations = $app->get_relations( $obj, $to_obj );
            if ( $original ) {
                $orig_relations = $app->get_relations( $original, $to_obj );
                $relations = array_merge( $relations, $orig_relations );
            }
        } else {
            $template = $mapping->template;
            if ( $template && $template->id ) {
                $relation = $app->db->model( 'relation' )->get_by_key(
                    ['to_obj' => 'template', 'to_id' => $template->id ]
                );
                $relations[] = $relation;
            }
        }
        if (!$original ) $original = clone $obj;
        $to_obj = $mapping->model;
        $table = $app->get_table( $to_obj );
        $published_ids = [];
        foreach ( $relations as $relation ) {
            $id = $relation->to_id;
            $dependencie = $app->db->model( $to_obj )->load( $id );
            if ( is_object( $dependencie ) ) {
                if (!$mapping->date_based && in_array( $id, $published_ids ) ) {
                    continue;
                }
                $ts = '';
                $orig_ts = '';
                if ( $date_based = $mapping->date_based ) {
                    $date_col = $app->get_date_col( $obj );
                    if (!$date_col ) {
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
                        $date = substr( $date, 0, 6 );
                        $orig_date = substr( $orig_date, 0, 6 );
                    }
                    if ( $date == $orig_date && in_array( $id, $published_ids ) ) {
                        continue;
                    }
                }
                if ( $publish ) {
                    $cache_key = $dependencie->id . '-' . $mapping->id . '-' .
                                 $table->name . '-' . $ts;
                    if ( isset( $published_on_request[ $cache_key ] ) ) {
                        continue;
                    }
                    $published_on_request[ $cache_key ] = true;
                    
                    $file_path = $app->build_path_with_map
                                                ( $dependencie, $mapping, $table, $ts );
                    $app->publish( $file_path, $dependencie, $mapping, null, $ts );
                } else {
                    $dependencies = $app->stash( 'rebuild_dependencies' ) ?
                                    $app->stash( 'rebuild_dependencies' ) : [];
                    $publish_ids  = isset( $dependencies[ $dependencie->_model ] ) ?
                                    $dependencies[ $dependencie->_model ] : [];
                    if (! in_array( $dependencie->id, $publish_ids ) ) {
                        $publish_ids[] = (int) $dependencie->id;
                    }
                    $dependencies[ $dependencie->_model ] = $publish_ids;
                    $app->stash( 'rebuild_dependencies', $dependencies );
                }
                $published_ids[] = $id;
            }
        }
    }

    function get_date_col ( $obj ) {
        $date_col = '';
        if ( $obj->has_column( 'published_on' ) ) {
            $date_col = 'published_on';
        } else {
            $app = $this;
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
        $app = $this;
        $user = $app->user();
        $terms = ['user_id' => $user->id, 'key' => $key , 'kind' => $kind ];
        $workspace_id = (int) $workspace_id;
        $terms['workspace_id'] = $workspace_id;
        $list_option = $app->db->model( 'option' )->get_by_key( $terms );
        return $list_option;
    }

    function get_scheme_from_db ( $model, $force = false ) {
        $app = $this;
        if ( $app->stash( 'scheme:' . $model ) && ! $force )
            return $app->stash( 'scheme:' . $model );
        $db = $app->db;
        $language = $app->language;
        $cache_key = $model . DS . "scheme_{$language}__c";
        if (! $force ) {
            if ( $scheme = $app->get_cache( $cache_key ) ) {
                $db->scheme[ $model ] = $scheme;
                $db->stash( $model, $scheme );
                $app->stash( 'scheme:' . $model, $scheme );
                return $scheme;
            }
        }
        $table = $app->get_table( $model );
        if (!$table ) return null;
        $obj_label = $app->translate( $table->label );
        $columns = $db->model( 'column' )->load(
                        ['table_id' => $table->id ], ['sort' => 'order'] );
        if (! is_array( $columns ) || empty( $columns ) ) return null;
        $obj = $db->model( $model )->new();
        $scheme = $obj->_scheme;
        if (!$scheme ) $scheme = [];
        list( $column_defs, $indexes, $list, $edit, $labels, $unique,
            $unchangeable, $autoset, $locale ) = [ [], [], [], [], [], [], [], [], [] ];
        $locale['default'] = [];
        $lang = $app->language;
        $locale[ $lang ] = [];
        $scheme['version'] = $table->version;
        $scheme['label'] = $table->label;
        $scheme['plural'] = $table->plural;
        $locale[ $lang ][ $table->label ] = $app->translate( $table->label );
        $locale[ $lang ][ $table->plural ] = $app->translate( $table->plural );
        $relations = [];
        $col_options = [];
        $translates = [];
        $hints = [];
        $disp_edit = [];
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
            if ( $column->translate ) $translates[] = $col_name;
            if ( $column->hint ) $hints[ $col_name ] = $column->hint;
            if ( $column->disp_edit ) $disp_edit[ $col_name ] = $column->disp_edit;
            $label = $column->label;
            $app->dictionary['default'][ $col_name ] = $label;
            $locale['default'][ $col_name ] = $label;
            $trans_label = $app->translate( $label );
            $locale[ $lang ][ $label ] = $trans_label;
            $labels[ $col_name ] = $label;
        }
        if ( $table->primary ) $scheme['primary'] = $table->primary;
        $options = ['auditing', 'order', 'sortable', 'hierarchy', 'start_end',
                    'menu_type', 'template_tags', 'taggable', 'display_space',
                    'has_basename', 'has_status', 'assign_user', 'revisable',
                    'allow_comment', 'default_status', 'has_uuid'];
        foreach ( $options as $option ) {
            if ( $table->$option ) $scheme[ $option ] = (int) $table->$option;
        }
        if ( $table->display_system )
            $scheme['display_system'] = (int) $table->display_system;
        $scheme['column_defs'] = $column_defs;
        if (! empty( $translates ) ) $scheme['translate'] = $translates;
        if (! empty( $hints ) ) $scheme['hint'] = $hints;
        if (! empty( $relations ) ) $scheme['relations'] = $relations;
        if (! empty( $col_options ) ) $scheme['options'] = $col_options;
        $sort_by = $table->sort_by;
        $sort_order = $table->sort_order ? $table->sort_order : 'ascend';
        if ( $sort_by && $sort_order ) $scheme['sort_by'] = [ $sort_by => $sort_order ];
        if ( $table->space_child ) $scheme['child_of'] = 'workspace';
        if (! empty( $autoset ) ) $scheme['autoset'] = $autoset;
        $db->scheme[ $model ]['column_defs'] = $column_defs;
        $scheme['indexes'] = $indexes;
        if (! empty( $unique ) ) $scheme['unique'] = $unique;
        if (! empty( $unchangeable ) ) $scheme['unchangeable'] = $unchangeable;
        if (! empty( $disp_edit ) ) $scheme['disp_edit'] = $disp_edit;
        $scheme['edit_properties'] = $edit;
        $scheme['list_properties'] = $list;
        $scheme['labels'] = $labels;
        $scheme['label'] = $obj_label;
        $scheme['locale'] = $locale;
        $app->stash( 'scheme:' . $model, $scheme );
        $app->set_cache( $cache_key, $scheme );
        return $scheme;
    }

    function validate_magic ( $json = false ) {
        $app = $this;
        $is_valid = true;
        if (!$app->user() ) $is_valid = false;
        $token = $app->param( 'magic_token' );
        if (!$token || $token !== $app->current_magic ) $is_valid = false;
        if (!$is_valid ) {
            if ( $json ) {
                header( 'Content-type: application/json' );
                echo json_encode( ['status' => 403,
                                   'message'=> $app->translate( 'Invalid request.' ) ] );
                exit();
            }
            return $app->error( 'Invalid request.' );
        }
        return true;
    }

    function recover_password () {
        $app = $this;
        $token = $app->param( 'token' );
        if ( $token ) {
            $session = $app->db->model( 'session' )->get_by_key(
                ['name' => $token, 'kind' => 'RP'] );
            if (!$session->id ) {
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
                if (!$app->is_valid_password( $password, $verify, $msg ) ) {
                    $app->assign_params( $app, $app->ctx, true );
                    $app->ctx->vars['error'] = $msg;
                    $app->param( '_type', 'recover' );
                    return $app->__mode( 'start_recover' );
                }
                $user = $app->db->model( 'user' )->load( $session->user_id );
                if (!$user ) {
                    return $app->error( 'Invalid request.' );
                }
                $password = password_hash( $password, PASSWORD_BCRYPT );
                $user->password( $password );
                $user->lockout( 0 );
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
                    $tmpl = 'email' . DS . 'recover_password.tmpl';
                    $session_id = $app->magic();
                    $app->ctx->vars['token'] = $session_id;
                    $body = $app->build_page( $tmpl, [], false );
                    $session = $app->db->model( 'session' )->get_by_key(
                        ['name' => $session_id, 'kind' => 'RP'] );
                    $session->start( time() );
                    $session->expires( time() + $app->auth_expires );
                    $session->user_id( $user->id );
                    $session->save();
                    $system_email = $app->get_config( 'system_email' );
                    if (!$system_email ) {
                        $app->assign_params( $app, $app->ctx, true );
                        $app->ctx->vars['error'] =
                            $app->translate( 'System Email Address is not set in System.' );
                        return $app->__mode( 'start_recover' );
                    }
                    $headers = ['From' => $system_email->value ];
                    $subject = $app->translate( 'Password Recovery' );
                    if (! PTUtil::send_mail( $email, $subject, $body, $headers, $error ) ) {
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

    function set_config ( $cfgs ) {
        $app = $this;
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
        // $app->clear_cache( 'configs__c' );
    }

    function get_config ( $key = null ) {
        $app = $this;
        if (!$key ) return $app->db->model( 'option' )->load( ['kind' => 'config'] );
        $configs = $app->stash( 'configs' );
        if ( isset( $configs[ $key ] ) ) return $configs[ $key ];
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

    function get_assetproperty ( $obj, $name, $property = 'all' ) {
        $app = $this;
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
                $fi = $app->db->model( 'urlinfo' )->get_by_key(
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
            if (!$session ) {
                $session = $cache ? $cache : $app->db->model( 'session' )->get_by_key(
                ['name' => $screen_id, 'user_id' => $app->user()->id ] );
            }
            $ctx->stash( $model . '_session_' . $screen_id . '_' . $obj_id, $session );
            if ( $session->id ) {
                $data = $session->text;
            }
        }
        if (!$data ) {
            $cache = $ctx->stash( $model . '_meta_' . $name . '_' . $obj_id );
            $metadata = $cache ? $cache : $app->db->model( 'meta' )->get_by_key(
                     ['model' => $model, 'object_id' => $obj_id,
                      'kind' => 'metadata', 'key' => $name ] );
            $ctx->stash( $model . '_meta_' . $name . '_' . $obj_id, $metadata );
            if (!$metadata->id ) {
                return ( $property === 'all' ) ? [] : null;
            }
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
        $app = $this;
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
        $app = $this;
        if (!$obj ) return [];
        $terms = ['from_id'  => $obj->id, 
                  'from_obj' => $obj->_model ];
        if ( $to_obj ) $terms['to_obj'] = $to_obj;
        if ( $name ) $terms['name'] = $name;
        $relations = $app->db->model( 'relation' )->load(
                                       $terms, ['sort' => 'order'] );
        if ( empty( $relations ) && $to_obj ) {
            if ( $obj->has_column( "{$to_obj}_id" ) ) {
                $rel_obj = $obj->$to_obj;
                if ( $rel_obj && is_object( $rel_obj ) ) {
                    $relation = $app->db->model( 'relation' )->get_by_key(
                        ['from_id'  => $obj->id, 
                         'from_obj' => $obj->_model,
                         'to_obj'   => $to_obj,
                         'to_id'    => $rel_obj->id ] );
                    $relations = [ $relation ];
                }
            }
        }
        return $relations;
    }

    function get_related_objs ( $obj, $to_obj, $name = null ) {
        $app = $this;
        if (!$obj ) return [];
        $model = $obj->_model;
        $id = $obj->id;
        $extra = " AND relation_from_obj='{$model}' AND relation_from_id={$id}"
               . " AND relation_to_obj='{$to_obj}' ORDER BY relation_order ASC ";
        $args = ['join' => [ 'relation', ['id', 'to_id'] ], 'distinct' => 1];
        return $app->db->model( $to_obj )->load(
                                      [], $args, '*', $extra );
    }

    function get_meta ( $obj, $kind = null, $key = null, $name = null ) {
        $app = $this;
        $terms = ['object_id'  => $obj->id, 
                  'model' => $obj->_model ];
        if ( $kind ) $terms['kind'] = $kind;
        if ( $key )  $terms['key']  = $key;
        if ( $name ) $terms['name'] = $name;
        $args = ['sort' => 'number', 'direction' => 'ascend'];
        return $app->db->model( 'meta' )->load( $terms );
    }

    function set_default ( &$obj ) {
        $app = $this;
        if ( $obj->has_column( 'modified_on' ) ) {
            $obj->modified_on( date( 'YmdHis' ) );
        }
        if ( $obj->has_column( 'created_on' ) ) {
            $ts = $obj->created_on;
            $ts = preg_replace( '/[^0-9]/', '', $ts );
            $ts = (int) $ts;
            if (!$ts ) {
                $obj->created_on( date( 'YmdHis' ) );
            }
        }
        if ( $obj->has_column( 'published_on' ) ) {
            $ts = $obj->published_on;
            $ts = preg_replace( '/[^0-9]/', '', $ts );
            $ts = (int) $ts;
            if (!$ts ) {
                $obj->published_on( date( 'YmdHis' ) );
            }
        }
        if ( $user = $app->user() ) {
            if ( $obj->has_column( 'modified_by' ) ) {
                $obj->modified_by( $user->id );
            }
            if ( $obj->has_column( 'created_by' ) && !$obj->id ) {
                $obj->created_by( $user->id );
            }
            if ( $obj->has_column( 'user_id' ) && !$obj->user_id ) {
                $obj->user_id( $user->id );
            }
        }
        if ( $obj->has_column( 'extra_path' ) ) {
            $extra_path = $obj->extra_path;
            $obj->extra_path( $app->sanitize_dir( $extra_path ) );
        }
        if ( $obj->has_column( 'status' ) && ! $obj->status ) {
            if ( $table = $app->get_table( $obj->_model ) ) {
                $obj->status( $table->default_status );
            }
        }
        if ( $obj->has_column( 'remote_ip' ) && ! $obj->remote_ip ) {
            $obj->remote_ip( $app->remote_ip );
        }
        if ( $workspace = $app->workspace() ) {
            if ( $obj->has_column( 'workspace_id' ) ) {
                $obj->workspace_id( $workspace->id );
            }
        }
    }

    function translate ( $phrase, $params = '', $component = null, $lang = null ) {
        $component = $component ? $component : $this;
        $lang = $lang ? $lang : $this->user() ? $this->user()->language : $this->language;
        if (!$lang ) $lang = 'default';
        $dict = isset( $component->dictionary ) ? $component->dictionary : null;
        if ( $dict && isset( $dict[ $lang ] ) && isset( $dict[ $lang ][ $phrase ] ) )
             $phrase = $dict[ $lang ][ $phrase ];
        $phrase = is_string( $params )
            ? sprintf( $phrase, $params ) : vsprintf( $phrase, $params );
        return $phrase;
    }

    function redirect ( $url ) {
        $app = $this;
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
        header( $app->protocol . ' 302 Redirect' );
        header( 'Status: 302 Redirect' );
        header( 'Location: ' . $url );
        exit();
    }

    function is_login () {
        $app = $this;
        if ( $app->stash( 'logged-in' ) ) return true;
        $cookie = $app->cookie_val( $app->cookie_name );
        if (!$cookie ) return false;
        $sess = $app->db->model( 'session' )->load(
            ['name' => $cookie, 'kind' => 'US'] );
        if (! empty( $sess ) ) {
            $sess = $sess[0];
            $expires = $sess->expires ? $sess->expires : $sess->start + $app->sess_timeout;
            if ( $expires < time() ) {
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
        $message = ! is_array( $message ) ? ['message' => $message ] : $message;
        $app = $this;
        if (! isset( $message['metadata'] ) ) {
            if ( mb_strlen( $message['message'] ) >= 255 ) {
                $message['metadata'] = $message['message'];
            }
        }
        $log = $app->db->model( 'log' )->__new( $message );
        if (!$log->level ) {
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
        if (!$log->category ) $log->category( 'system' );
        $app->set_default( $log );
        $log->save();
        return $log;
    }

    function moved_permanently ( $url ) {
        header( $this->protocol . ' 301 Moved Permanently' );
        header( 'Status: 301 Moved Permanently' );
        header( 'Location: ' . $url );
        exit();
    }

    function bake_cookie ( $name, $value, $expires = null, $path = null ) {
        if (!$expires ) $expires = 0;
        if ( $expires ) $expires += time();
        return setcookie( $name, $value, $expires, $path );
    }

    function cookie_val ( $name ) {
        if ( isset( $_COOKIE[ $name ] ) ) {
            return $_COOKIE[ $name ];
        }
        return '';
    }

    function magic () {
        $magic = md5( uniqid( mt_rand(), true ) );
        $session = $this->db->model( 'session' )->get_by_key( ['name' => $magic ] );
        if ( $session->id ) return $this->magic();
        return $magic;
    }

    function error ( $message, $params = null, $component = null, $lang = null ) {
        $this->ctx->vars['error'] = 
            $this->translate( $message, $params, $component, $lang );
        $this->ctx->vars['page_title'] = $this->translate( 'An error occurred' );
        $this->__mode = 'error';
        // TODO Log
        $this->__mode( 'error' );
    }

    function rollback ( $message = null, $params = null, $component = null, $lang = null ) {
        $this->db->rollback();
        if ( $message ) return $this->error( $message, $params, $component, $lang );
    }

    function query_string ( $force = false, $debug = false ) {
        if (!$force && ( $query_string = $this->query_string ) ) {
            return $query_string;
        }
        if ( $params = $this->param() ) {
            $params_array = array();
            if ( is_array( $params ) ) {
                foreach ( $params as $key => $value ) {
                    if ( is_array( $value ) ) {
                        foreach( $value as $val ) {
                            array_push( $params_array, "{$key}[]={$val}" );
                        }
                    } else {
                        array_push( $params_array, "{$key}={$value}" );
                    }
                }
                if ( $params_array ) {
                    $separator = $debug ? "\n" : '&';
                    $this->query_string = join( $separator, $params_array );
                    return $this->query_string;
                }
            }
        }
    }

    function param ( $param = null, $value = null ) {
        if ( $param && $value ) {
            if ( !isset( $_GET[ $param ] ) ) $_GET[ $param ] = $value;
            return $value;
        }
        if ( $param ) {
            if ( isset ( $_GET[ $param ] ) ) {
                return $_GET[ $param ];
            } else if ( isset ( $_POST[ $param ] ) ) {
                return $_POST[ $param ];
            } else if ( isset ( $_REQUEST[ $param ] ) ) {
                return $_REQUEST[ $param ];
            }
        } else {
            $vars = $_REQUEST;
            $params = [];
            foreach ( $vars as $key => $value ) {
                if ( isset( $_GET[ $key ] ) || isset( $_POST[ $key ] ) ) {
                    $params[ $key ] = $value;
                }
            }
            return $params;
        }
    }

    function is_valid_email ( $value, &$msg ) {
        $regex = '/^[a-zA-Z0-9\.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-z'
               .'A-Z0-9-]+)*$/';
        if (!$value || ! preg_match( $regex, $value, $mts ) ) {
            $app = $this;
            $msg = $app->translate(
                        'Please specify a valid email address.' );
            return false;
        }
        return true;
    }

    function is_valid_property ( $prop, &$msg = '', $len = false ) {
        $app = $this;
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
            $app = $this;
            $msg = $app->translate(
                        'Please enter a valid URL.' );
            return false;
        }
    }

    function is_valid_password ( $pass, $verify = null, &$msg ) {
        $app = $this;
        if ( isset( $verify ) && $pass !== $verify ) {
            $msg = $app->translate( 'Both passwords must match.' );
            return false;
        }
        $min = $app->passwd_min;
        $rule = $app->passwd_rule;
        $len = mb_strlen( $pass );
        if ( $min && $rule ) {
            // $pass = preg_replace( '/[0-9a-zA-Z]/', '', $pass );
            if (! $len >= $min || !$pass ) {
                $msg = $app->translate(
              'Password should be longer than %s characters and contain symbols.', $min );
                return false;
            }
        } else if ( $min ) {
            if (! $len >= $min ) {
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
        $q = $this->query_string( true );
        $q = preg_replace( "/(^.*?)\n.*$/si", "$1", $q );
        if ( $tmpl = $this->ctx->template_file ) $errmsg = " $errmsg( in {$tmpl} )";
        $msg = "{$errmsg} ({$errno}) occured( line {$line} of {$f} ). {$q}";
        $this->errors[] = $msg;
        if ( $this->debug === 2 ) $this->debugPrint( $msg );
        if ( $this->logging ) error_log( date( 'Y-m-d H:i:s T', time() ) .
            "\t" . $msg . "\n", 3, $this->log_path . 'error.log' );
    }

}