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
if (! defined( 'ALT_TMPL' ) ) {
    define( 'ALT_TMPL', __DIR__ . DS . 'alt-tmpl' . DS );
}
if (! defined( 'DB_PREFIX' ) ) {
    define( 'DB_PREFIX', 'mt_' );
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
    public    $app_version   = '1.022';
    public    $id            = 'Prototype';
    public    $name          = 'Prototype';
    public    $db            = null;
    public    $ctx           = null;
    public    $dictionary    = [];
    public    $language      = null;
    public    $sys_language  = null;
    public    $copyright     = null;
    public    $app_path      = null;
    protected $dbprefix      = 'mt_';
    public    $cookie_name   = 'pt-user';
    public    $encoding      = 'UTF-8';
    public    $mode          = 'dashboard';
    public    $timezone      = 'Asia/Tokyo';
    public    $set_names     = false;
    public    $list_limit    = 25;
    public    $per_rebuild   = 120;
    public    $rebuilt_ids   = [];
    public    $rebuild_interval = 0;
    public    $two_factor_auth = false;
    public    $basename_len  = 40;
    public    $password_min  = 8;
    public    $retry_auth    = 3;
    public    $sess_timeout  = 86400;
    public    $sess_expires  = 7200;
    public    $token_expires = 7200;
    public    $auth_expires  = 600;
    public    $perm_expires  = 86400;
    public    $cache_expires = 86400;
    public    $search_type   = 1;
    public    $cookie_path   = '/';
    public    $languages     = ['ja', 'en'];
    public    $debug         = false;
    public    $logging       = false;
    public    $stash         = [];
    public    $installed     = false;
    public    $do_conditional= true;
    public    $unify_breaks  = true;
    public    $theme_static  = null;
    public    $csv_delimiter = ',';
    public    $init_tags;
    public    $protocol;
    public    $log_dir;
    public    $screen_id;
    public    $plugin_order  = 0; // 0=asc, 1=desc
    public    $template_paths= [ ALT_TMPL, TMPL_DIR ];
    public    $plugin_paths  = [];
    public    $tmpl_paths    = [];
    public    $theme_paths   = [];
    public    $model_paths   = [];
    public    $class_paths   = [];
    public    $components    = [];
    public    $plugin_dirs   = [];
    public    $cfg_settings  = [];
    public    $plugin_switch = [];
    public    $modules       = [];
    // public    $cache_driver  = 'Memcached';
    public    $cache_driver  = null;
    public    $file_mgr      = 'PTFileMgr';
    protected $upload_dirs   = [];
    public    $auto_orient   = true;
    public    $remove_exif   = true;
    public    $fmgr;
    public    $no_cache      = false;
    public    $txn_active    = false;
    public    $worker_period = 600;
    public    $caching       = false;
    public    $max_revisions = -1;
    public    $unique_url    = false;
    public    $published_files = [];
    public    $remote_ip;
    public    $user;
    public    $pt_path       = __FILE__;
    public    $pt_dir;
    public    $app_protect   = true;
    public    $develop       = false;
    public    $export_without_bin = false;
    public    $cache_permalink = true;
    public    $appname;
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
    public    $start_time;
    public    $request_id;
    public    $admin_url;
    public    $request_method;
    public    $current_magic;
    public    $preview_redirect = true;
    public    $publish_callbacks= false;
    public    $mail_return_path = '';
    public    $mail_encording = '';
    public    $mail_language = 'ja';
    public    $check_int_null = false;
    public    $upload_size_limit = 5242880;
    public    $upload_max_pixel = 0;
    public    $upload_image_option = 'resize';
    public    $ignore_max_input = false;
    public    $image_quality = 60;
    public    $max_exec_time = 3600;
    public    $temp_dir      = '/tmp';
    protected $errors        = [];
    public    $tmpl_markup   = 'mt';
    public    $admin_protect = false;
    public    $build_published_only = true;
    public    $ip_protect    = false;
    public    $tags_compat   = false;
    public    $delayed       = [];
    public    $versions      = [];
    public    $hooks         = [];
    public    $registry      = [];
    public    $panel_width   = 103;

    public    $videos        = ['mov', 'avi', 'qt', 'mp4', 'wmv',
                                '3gp', 'asx', 'mpg', 'flv', 'mkv', 'ogm'];

    public    $images        = ['jpeg', 'jpg', 'png', 'gif', 'jpe'];

    public    $audios        = ['mp3', 'mid', 'midi', 'wav', 'aif', 'aac', 'flac',
                                'aiff', 'aifc', 'au', 'snd', 'ogg', 'wma', 'm4a'];

    public    $denied_exts   = 'ascx,asis,asp,aspx,bat,cfc,cfm,cgi,cmd,com,cpl,dll,exe,'
                             . 'htaccess,inc,jhtml,jsb,jsp,mht,msi,php,php2,php3,php4,php5,'
                             . 'phps,phtm,phtml,pif,pl,pwml,py,reg,scr,sh,shtm,shtml,'
                             . 'vbs,vxd,pm,so,rb,htc';

    protected $methods       = ['view', 'save', 'delete', 'upload', 'save_order',
                                'list_action', 'display_options', 'get_columns_json',
                                'export_scheme', 'recover_password', 'save_hierarchy',
                                'delete_filter', 'edit_image', 'insert_asset',
                                'upload_multi', 'rebuild_phase', 'get_thumbnail',
                                'get_field_html', 'manage_scheme', 'manage_plugins',
                                'import_objects', 'upload_objects', 'preview', 'debug',
                                'can_edit_object', 'flush_session', 'update_dashboard',
                                'get_temporary_file', 'clone_object', 'change_activity',
                                'cleanup_tmp', 'manage_theme'];

    public    $callbacks     = ['pre_save'     => [], 'post_save'   => [],
                                'pre_delete'   => [], 'post_delete' => [],
                                'save_filter'  => [], 'delete_filter'=> [],
                                'pre_listing'  => [], 'start_listing'=> [],
                                'template_source' => [], 'template_output' => [],
                                'pre_view' => [], 'post_view' => [],
                                'post_load_objects' => [], 'pre_archive_list' => [],
                                'pre_archive_count' => [], 'publish_date_based' => [],
                                'post_load_object' => [], 'post_import' => [],
                                'pre_import' => [], 'scheduled_published' => [],
                                'scheduled_replacement' => [] ];

    public    $permissions   = ['can_rebuild', 'manage_plugins',
                                'import_objects', 'can_livepreview'];
    public    $disp_option;
    public    $workspace_param;
    public    $workspace_id;
    public    $output_compression = true;
    public    $force_filter  = false;
    public    $return_args   = [];
    public    $core_tags;
    private   $encrypt_key   = 'prototype-default-encrypt-key';
    public    $ws_menu_type  = 1;
    public    $dynamic_view  = true;
    public    $force_dynamic = false;
    public    $allow_static  = false;
    public    $static_conditional = true;
    public    $in_dynamic    = false;
    public    $form_interval = 180;
    public    $form_upper_limit = 5;
    public    $resetdb_per_rebuild = false;
    public    $max_packet    = null; //16777216;
    public    $publish_queue = 4;
    public    $status_publish= 4;
    public    $status_ended  = 5;
    public    $build_one_by_one = false;
    public    $plugin_configs= [];
    public    $registered_callbacks = [];
    public    $password_symbol = false;
    public    $password_letternum = false;
    public    $password_upperlower = false;
    public    $eval_in_preview = false;
    public    $error_document404 = null;
    public    $always_update_login = false;
    public    $add_port_to_url = true;
    public    $system_info_url = 'https://www.powercms.jp/x/information/index.php';
    public    $news_box_url    = 'https://www.powercms.jp/x/information/news.php';
    private   $powercmsx_auth  = 'powercmsx:xlpXLP';

    static function get_instance() {
        return self::$app;
    }

    function version () {
        return $this->version;
    }

    function __construct ( $cfgs = [] ) {
        if (! empty( $cfgs ) ) {
            foreach ( $cfgs as $k => $v ) {
                $this->$k = $v;
            }
        }
        $this->pt_dir = dirname( __FILE__ );
        $this->configure_from_json( __DIR__ . DS . 'config.json' );
        $this->start_time = microtime( true );
        ini_set( 'memory_limit', -1 );
        $this->request_method = isset( $_SERVER['REQUEST_METHOD'] )
            ? $_SERVER['REQUEST_METHOD'] : '';
        $this->protocol  = isset( $_SERVER['SERVER_PROTOCOL'] )
            ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
        if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] )
            && $_SERVER['HTTP_X_FORWARDED_FOR'] ) {
            $this->remote_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $this->remote_ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $this->remote_ip = 'localhost';
        }
        $secure = !empty( $_SERVER['HTTPS'] ) &&
            strtolower( $_SERVER['HTTPS'] ) !== 'off' ? 's' : '';
        if (! $secure && isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] )
            && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ) {
            $secure = 's';
        }
        $this->is_secure = $secure ? true : false;
        $base = isset( $_SERVER['SERVER_NAME'] ) 
            ? "http{$secure}://{$_SERVER['SERVER_NAME']}" : null;
        $port = isset( $_SERVER['SERVER_PORT'] ) ? (int) $_SERVER['SERVER_PORT'] : null;
        if ( $this->add_port_to_url && $port && ( ( $secure && $port != 443 ) || ( !$secure && $port != 80 ) ) ) {
            $base .= ":{$port}";
        }
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
            $r_query = urldecode( $_SERVER['REDIRECT_QUERY_STRING'] );
            $encoding = mb_detect_encoding( $r_query, "UTF-8, Shift_JIS" );
            if ( $encoding && $encoding != 'UTF-8' ) {
                $r_query = mb_convert_encoding( $r_query, 'UTF-8', $encoding );
            }
            parse_str( $r_query, $params );
            foreach ( $params as $key => $value ) {
                $_REQUEST[ $key ] = $value;
            }
        }
        if ( isset( $_SERVER['REDIRECT_STATUS'] ) ) {
            $status = $_SERVER['REDIRECT_STATUS'];
            if ( ( $status == 403 ) || ( $status == 404 ) ) {
                if ( empty( $_POST ) ) {
                    if ( $params = file_get_contents( "php://input" ) ) {
                        parse_str( $params, $_POST );
                    }
                }
                if ( isset( $_SERVER['REDIRECT_REQUEST_METHOD'] ) ) {
                    $this->request_method = $_SERVER['REDIRECT_REQUEST_METHOD'];
                }
            }
        }
        $this->base = $base;
        $this->request_uri = $request_uri;
        $request = $request_uri;
        if ( strpos( $request_uri, '?' ) ) {
            list( $request, $this->query_string ) = explode( '?', $request_uri );
        }
        $this->document_root = $this->document_root ? $this->document_root
                                                    : $_SERVER['DOCUMENT_ROOT'];
        if (! $this->document_root ) {
            $this->document_root = dirname( __DIR__ );
        } else {
            $this->document_root = rtrim( $this->document_root, DS );
        }
        $path_part = '';
        if ( $this->id != 'Bootstrapper' && $this->id != 'Worker' ) {
            if ( preg_match( "!(^.*?)([^/]*$)!", $request, $mts ) ) {
                list ( $d, $path_part, $this->script ) = $mts;
                if (! $this->path ) $this->path = $path_part;
            }
        } else {
            $this->script = 'index.php';
            if (! $this->path ) {
                $root_quote = preg_quote( $this->document_root, '/' );
                $this->path = dirname( preg_replace( "/^$root_quote/", '', __FILE__ ) ) . '/';
            }
        }
        if ( $mode = $this->param( '__mode' ) ) {
            $this->mode = $mode;
        }
        $path = $this->path;
        if (! $path ) {
            if ( stripos( $this->document_root, __DIR__ ) === 0 ) {
                $search = preg_quote( $this->document_root, '/' );
                $path = preg_replace( "/^$search/", '', __DIR__ ) . DS;
            } else if ( isset( $_SERVER['REQUEST_URI'] ) ) {
                $path = $_SERVER['REQUEST_URI'] . DS;
            }
        }
        $path = str_replace( DS, '/', $path );
        $this->path = $path;
        $basename = $this->id != 'Bootstrapper'
                  && isset( $_SERVER['SCRIPT_FILENAME'] ) ? basename( $_SERVER['SCRIPT_FILENAME'] ) : 'index.php';
        $this->admin_url = $this->admin_url ? $this->admin_url : $this->base . $this->path . $basename;
        if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) )
            $this->language = substr( $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2 );
    }

    function __destruct() {
        if (! empty ( $this->upload_dirs ) ) {
            $fmgr = $this->fmgr;
            $upload_dirs = $this->upload_dirs;
            $keys = array_map( 'strlen', array_keys( $upload_dirs ) );
            array_multisort( $keys, SORT_DESC, $upload_dirs );
            foreach ( $upload_dirs as $dir => $bool ) {
                if ( $bool ) PTUtil::remove_dir( $dir );
            }
        }
        if (! empty( $this->hooks ) ) {
            $this->run_hooks( 'post_run' );
        }
        if ( $this->db ) {
            $this->db->db = null;
            unset( $this->db->db );
            $this->db = null;
            unset( $this->db );
        }
    }

    function init ( $dsn = null, $dbuser = null, $dbpasswd = null ) {
        if (! $this->log_dir )
            $this->log_dir = isset( $this->log_path ) ? $this->log_path : __DIR__ . DS . 'log';
        if ( $this->timezone ) date_default_timezone_set( $this->timezone );
        require_once( LIB_DIR . 'PADO' . DS . 'class.pado.php' );
        require_once( LIB_DIR . 'PAML' . DS .'class.paml.php' );
        // $this->configure_from_json( __DIR__ . DS . 'config.json' );
        $core_menus = ['manage_plugins' => [
                       'display_system' => 1, 'display_space' => 1, 'component' => 'Core',
                       'permission' => 'manage_plugins', 'mode' => 'manage_plugins',
                       'label' => 'Manage Plugins', 'order' => 20],
                       'manage_scheme' => [
                       'display_system' => 1, 'component' => 'Core', 'permission' => 'manage_plugins',
                       'mode' => 'manage_scheme', 'label' => 'Manage Scheme', 'order' => 30],
                       'manage_theme' => [
                       'display_system' => 1, 'display_space' => 1, 'component' => 'Core',
                       'permission' => 'import_objects', 'mode' => 'manage_theme',
                       'label' => 'Manage Theme', 'order' => 40],
                       'import_objects' => [
                       'display_system' => 1, 'display_space' => 1, 'component' => 'Core',
                       'permission' => 'import_objects', 'mode' => 'import_objects',
                       'label' => 'Import Objects', 'order' => 50]];
        $this->registry['menus'] = $core_menus;
        if ( $this->mode == 'dashboard' )
            $this->registry['widgets'] = $this->core_widgets();
        $cfg = __DIR__ . DS . 'db-config.php';
        $db = new PADO();
        $ctx = new PAML();
        if ( $this->mode !== 'upgrade' ) {
            $db->logging = true;
            $ctx->logging = true;
        } else {
            $db->upgrader = true;
            $this->logging = false;
        }
        if ( $this->debug ) {
            error_reporting( E_ALL );
        }
        $db->max_packet = $this->max_packet;
        set_error_handler( [ $this, 'errorHandler'] );
        if (! $dsn ) {
            if ( file_exists( $cfg ) ) {
                require_once( $cfg );
            } else {
                // Backward Compatibility
                $cfg = __DIR__ . DS . 'db-config.json.cgi';
                if ( file_exists( $cfg ) ) {
                    $db->configure_from_json( $cfg );
                }
            }
        } else {
            $db->dsn = $dsn;
            if ( $dbuser && $dbpasswd ) {
                $db->dbuser = $dbuser;
                $db->dbpasswd = $dbpasswd;
            }
        }
        $db->prefix = DB_PREFIX;
        $db->idxprefix = '<table>_';
        $db->colprefix = '<model>_';
        $db->id_column = 'id';
        $db->set_names = $this->set_names;
        $db->init();
        $db->register_callback( '__any__', 'save_filter', 'save_filter_obj', 100, $this );
        $db->register_callback( '__any__', 'post_save', 'flush_cache', 100, $this );
        $db->register_callback( '__any__', 'post_delete', 'flush_cache', 100, $this );
        $this->db = $db;
        $db->app = $this;
        $ctx->prefix = 'mt';
        $ctx->app = $this;
        $ctx->default_component = $this;
        $ctx->csv_delimiter = $this->csv_delimiter;
        $ctx->unify_breaks  = $this->unify_breaks;
        $ctx->force_compile = true;
        if ( $this->cache_driver && $this->cache_driver == 'File' ) {
            $this->cache_driver = null;
            $this->caching = true;
        } else {
            $ctx->cache_driver = $this->cache_driver;
        }
        $ctx->init();
        if ( $driver = $ctx->cachedriver ) {
            $driver->_prefix = $db->dbname . '__';
        }
        // $ctx->esc_trans = true;
        require_once( 'class.PTUtil.php' );
        require_once( 'class.PTTags.php' );
        $core_tags = new PTTags;
        $tags = [
            'function'   => ['objectvar', 'include', 'includeparts', 'getobjectname',
                             'assetthumbnail', 'assetproperty', 'getoption', 'statustext',
                             'archivetitle', 'archivedate', 'archivelink', 'property',
                             'assetthumbnailurl', 'setrolecolumns', 'getobjectlabel',
                             'gettableid', 'customfieldvalue', 'currenturlmappingvalue',
                             'columnproperty', 'pluginsetting', 'geturlprimary', 'getactivity',
                             'getchildrenids', 'websitename', 'websiteurl', 'websitelanguage',
                             'websiteid', 'websitepath', 'websitecopyright', 'websitedescription',
                             'customfieldcount','hex2rgba', 'phpstart', 'phpend', 'getregistry'],
            'block'      => ['objectcols', 'objectloop', 'tables', 'nestableobjects',
                             'countgroupby', 'fieldloop', 'archivelist', 'grouploop',
                             'workspacecontext', 'referencecontext', 'workflowusers',
                             'customfieldvalues', 'cacheblock', 'calendar', 'setcontext',
                             'menuitems', 'breadcrumbs'],
            'conditional'=> ['objectcontext', 'tablehascolumn', 'isadmin', 'ifcomponent',
                             'ifusercan', 'ifworkspacemodel', 'ifhasthumbnail',
                             'ifobjectexists', 'ifuserrole', 'ifarchivetype'],
            'modifier'   => ['epoch2str', 'sec2hms', 'trans', 'convert_breaks', '_eval',
                             'language'] ];
        foreach ( $tags as $kind => $arr ) {
            $tag_prefix = $kind === 'modifier' ? 'filter_' : 'hdlr_';
            foreach ( $arr as $tag ) {
                $ctx->register_tag( $tag, $kind, $tag_prefix . $tag, $core_tags );
            }
        }
        require_once( 'class.' . $this->file_mgr . '.php' );
        $this->fmgr = new $this->file_mgr;
        $sth = null;
        $sth = $db->show_tables( 'table' );
        $table = $sth->fetchColumn();
        if (!$table && $this->mode !== 'upgrade' && $this->request_method !== 'POST' ) {
            $this->redirect( $this->admin_url . '?__mode=upgrade&_type=install' );
        }
        $request_id = $this->param( 'request_id' )
                    ? $this->param( 'request_id' ) : $this->request_id;
        if (!$request_id ) {
            $request_id = $this->magic();
            $this->param( 'request_id', $request_id );
        }
        $this->request_id = $request_id;
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
        $ctx->vars['prototype_path'] = $this->app_path ? $this->app_path : $this->path;
        $ctx->vars['develop'] = $this->develop;
        $ctx->vars['app_version'] = $this->app_version;
        $lang = $this->language;
        $ctx->language = $lang;
        $this->ctx = $ctx;
        self::$app = $this;
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
        if ( $this->mode !== 'upgrade' ) $this->is_login();
        $app_version = 0;
        $upgrade_count = null;
        $this->model_paths[] = LIB_DIR . 'PADO' . DS . 'models';
        $this->db->models_dirs = $this->model_paths;
        if ( $table ) {
            $cfgs = $db->model( 'option' )->load( ['kind' => 'config',
                                                   'workspace_id' => 0] , null, 'key,value,data' );
            list( $site_url, $site_path ) = ['', ''];
            $this->stash( 'configs', [] );
            $configs = [];
            foreach ( $cfgs as $cfg ) {
                $colprefix = $cfg->_colprefix;
                $key = $cfg->key;
                $configs[ $key ] = $cfg;
                if ( $colprefix ) $key = preg_replace( "/^$colprefix/", '', $key );
                $ctx->vars[ $key ] = ( $cfg->value !== '' && !$cfg->data )
                                   ? $cfg->value : $cfg->data;
                if ( $key !== 'app_version' ) $this->$key = $cfg->value;
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
                } else if ( $key === 'administrator_ip' && $cfg->value ) {
                    $this->admin_protect = true;
                } else if ( $key === 'allowed_ip_only' && $cfg->value ) {
                    $this->ip_protect = true;
                } else if ( $key === 'appname' ) {
                    $this->appname = $cfg->value;
                } else if ( $key === 'app_version' ) {
                    $app_version = $cfg->value;
                } else if ( $key === 'language' ) {
                    $this->sys_language = $cfg->value;
                } else if ( $key === 'copyright' ) {
                    $this->copyright = $cfg->value;
                } else if ( $key === 'two_factor_auth' && $cfg->value ) {
                    $this->two_factor_auth = true;
                }
            }
            $this->stash( 'configs', $configs );
        }
        if ( $this->installed && $app_version < $this->app_version ) {
            $upgrader = new PTUpgrader;
            $upgrader->version_up( $this, $app_version, $this->app_version );
            $app_version = $this->app_version;
        }
        if ( $this->installed && ! $app_version ) {
            $this->set_config( ['app_version' => $this->app_version ] );
        }
        $sys_language = $this->language;
        if ( $this->image_quality > 100 ) $this->image_quality = 100;
        $ctx->vars['site_url'] = $this->site_url;
        $ctx->vars['site_path'] = $this->site_path;
        $this->components['core'] = $this;
        if ( $table && $this->use_plugin ) {
            if ( ( $plugin_d = __DIR__ . DS . 'plugins' ) && is_dir( $plugin_d ) )
                $this->plugin_paths[] = $plugin_d;
            $this->init_plugins();
        }
        if (! empty( $this->hooks ) ) {
            $this->run_hooks( 'start_app' );
        }
        if ( $this->installed && $this->user() ) {
            $this->language = $this->user()->language;
            $ctx->vars['user_language'] = $this->language;
            $ctx->vars['user_control_border'] = $this->user()->control_border;
            $ctx->vars['user_fix_spacebar'] = $this->user()->fix_spacebar;
            if ( $this->admin_protect ) {
                if ( $this->user()->is_superuser ) {
                    $ip = $db->model( 'remote_ip' )->get_by_key(
                        ['ip_address' => $this->remote_ip, 'class' => 'administrator' ] );
                    if (! $ip->id ) {
                        if ( $this->mode == 'logout' ) return $this->logout();
                        return $this->redirect(
                            $this->admin_url . '?__mode=logout&_type=admin_ip' );
                    }
                }
            }
            if ( $this->ip_protect ) {
                $ip = $db->model( 'remote_ip' )->get_by_key(
                    ['ip_address' => $this->remote_ip,
                        'class' => ['IN' => ['administrator', 'allow'] ] ] );
                if (! $ip->id ) {
                    if ( $this->mode == 'logout' ) return $this->logout();
                    return $this->redirect(
                        $this->admin_url . '?__mode=logout&_type=not_allowed_ip' );
                }
            }
            $system = $this->db->model( 'workspace' )->new( ['id' => 0 ] );
            if ( $this->can_do( 'manage_plugins', null, null, $system ) ) {
                if ( $upgrade_count === null && $this->mode != 'rebuild_phase' &&
                    !$this->param( 'dialog_view' ) && $this->request_method != 'POST' ) {
                    $upgrader = new PTUpgrader();
                    $upgrade_count = $upgrader->upgrade_scheme_check( $this );
                }
                if ( $upgrade_count ) {
                    $ctx->vars['scheme_upgrade_count'] = $upgrade_count;
                }
            }
        }
        if ( $this->mode != 'rebuild_phase' && $this->mode != 'preview' ) {
            $ctx->vars['panel_width'] = (int) $this->panel_width;
        }
        if ( $lang = $this->language ) {
            $ctx->language = $lang;
            $this->ctx = $ctx;
            $this->set_language( $locale_dir, $lang );
            if ( $lang != $sys_language && $this->id == 'Bootstrapper' ) {
                $this->set_language( $locale_dir, $sys_language );
            }
        }
        if ( count( $this->tmpl_paths ) ) {
            $this->template_paths = array_merge( $this->tmpl_paths, $this->template_paths );
        }
        foreach ( $this->template_paths as $tmpl_dir ) {
            $ctx->include_paths[ $tmpl_dir ] = true;
        }
        if (! empty( $this->hooks ) ) {
            $this->run_hooks( 'post_init' );
        }
    }

    function set_language ( $locale_dir = null, $lang = null ) {
        $locale__c = 'phrase' . DS . "locale_{$lang}__c";
        $dict = $this->get_cache( $locale__c );
        if (!$dict ) {
            $locale_dir = $locale_dir ? $locale_dir : __DIR__ . DS . 'locale';
            $locale = $locale_dir . DS . $lang . '.json';
            if ( file_exists( $locale ) ) {
                $dict = json_decode( file_get_contents( $locale ), true );
                $this->dictionary[ $lang ] = $dict;
            }
            if ( $this->mode !== 'upgrade' ) {
                $dictionary =& $this->dictionary;
                $phrases = $this->db->model( 'phrase' )->load( ['lang' => $lang ],
                                    null, 'phrase,trans' );
                foreach ( $phrases as $phrase ) {
                    $dict[ $phrase->phrase ] = $phrase->trans;
                }
                $this->set_cache( $locale__c, $dict );
            }
        }
        $this->dictionary[ $lang ] = $dict ? $dict : [];
    }

    function init_plugins () {
        $settings = $this->db->model( 'option' )->load( ['kind' => 'plugin'] );
        $plugin_objs = [];
        foreach ( $settings as $setting ) {
            $plugin_objs[ $setting->key ] = $setting;
        }
        $plugin_paths = $this->plugin_paths;
        $plugin_dirs = [];
        foreach ( $plugin_paths as $dir ) {
            $php_classes = [];
            $items = scandir( $dir, $this->plugin_order );
            foreach ( $items as $plugin ) {
                if ( strpos( $plugin, '.' ) === 0 ) continue;
                if ( isset( $plugin_dirs[ strtolower( $plugin ) ] ) ) continue;
                $plugin_dirs[ strtolower( $plugin ) ] = true;
                $plugin = $dir . DS . $plugin;
                if (! is_dir( $plugin ) ) continue;
                $plugins = scandir( $plugin, $this->plugin_order );
                $register = false;
                $component = null;
                $i = 0;
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
                        if (! is_array( $r ) || !isset( $r['component'] ) ) continue;
                        $r['plugin_path'] = dirname( $_plugin );
                        $component = strtolower( $r['component'] );
                        $this->plugin_configs[ $component ] = $r;
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
                        $register = true;
                    } else if ( $extension === 'php' ) {
                        $php_classes[] = $_plugin;
                    }
                    if (! $i && is_dir( $plugin . DS . 'models' ) ) {
                        $this->db->models_dirs[] = $plugin . DS . 'models';
                    }
                    $i++;
                }
                foreach ( $php_classes as $_plugin ) {
                    if(!$component ) 
                        $component = strtolower( pathinfo( $_plugin )['filename'] );
                    $this->class_paths[ $component ] = $_plugin;
                }
                if ( $register ) {
                    $this->plugin_dirs[] = $plugin;
                    if ( file_exists( $plugin . DS . 'tmpl' ) ) {
                        $this->template_paths[] = $plugin . DS . 'tmpl';
                    }
                    if ( file_exists( $plugin . DS . 'alt-tmpl' ) ) {
                        $this->template_paths[] = $plugin . DS . 'alt-tmpl';
                    }
                }
            }
        }
        $template_paths = $this->template_paths;
        usort( $template_paths, function( $a, $b ) {
            return strlen( $b ) - strlen( $a );
        });
        $this->template_paths = $template_paths;
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
            // unset( $plugin_hooks );
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
        if ( isset( $r['component'] ) && isset( $r['version'] ) ) {
            $this->versions[ strtolower( $r['component'] ) ] = $r['version'];
        }
        foreach ( $r as $key => $methods ) {
            if ( $key === 'settings' ) continue;
            if ( $key === 'config_settings' ) {
                foreach ( $methods as $cfg => $setting ) {
                    $this->$cfg = $setting;
                }
            } else if ( $key === 'version' ) {
                if ( isset( $r['component'] ) ) {
                    $this->versions[ $r['component'] ] = $methods;
                }
            } else if ( $key === 'widgets' && $this->mode == 'dashboard' ) {
                foreach ( $methods as $widget => $prop ) {
                    if (! in_array( $widget, $this->registry['widgets'] ) ) {
                        $this->registry['widgets'][ $widget ] = $prop;
                    }
                }
            } else if ( is_array( $methods ) ) {
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
        if (! is_string( $model ) ||! is_string( $action ) ) {
            return;
        }
        if ( isset( $app->registered_callbacks[ $model ][ $action ] ) ) return;
        $app->registered_callbacks[ $model ][ $action ] = true;
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
        try {
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
            $ctx->include_paths[ $app->site_path ] = true;
            if ( $workspace ) {
                $workspace_id = $workspace->id;
                $app->workspace_id = (int) $workspace_id;
                $ctx->stash( 'workspace', $workspace );
                $ctx->vars['workspace_scope'] = 1;
                $ctx->vars['workspace_url'] = $workspace->site_url;
                $ws_values = $workspace->get_values();
                foreach ( $ws_values as $ws_key => $ws_val ) {
                    $ctx->vars[ $ws_key ] = $ws_val;
                }
                $ctx->include_paths[ $workspace->site_path ] = true;
            }
            $user = $app->user();
            if ( $app->mode != 'rebuild_phase' ) {
                if ( isset( $app->registry['menus'] ) ) {
                    $menus = $app->registry['menus'];
                    PTUtil::sort_by_order( $menus );
                    $system_menus = [];
                    $workspace_menus = [];
                    $_system = $app->db->model( 'workspace' )->new( ['id' => 0 ] );
                    foreach ( $menus as $menu ) {
                        $component = $app->component( $menu['component'] );
                        $permission = isset( $menu['permission'] ) ? $menu['permission'] : null;
                        $label = $app->translate( $menu['label'], null, $component );
                        $item = ['menu_label' => $label, 'menu_mode' => $menu['mode'] ];
                        if ( isset( $menu['args'] ) ) {
                            $item['menu_args'] = $menu['args'];
                        }
                        if ( isset( $menu['display_system'] ) ) {
                            if ( $app->can_do( $permission, null, null, $_system ) ) {
                                $system_menus[] = $item;
                            }
                        }
                        if ( isset( $menu['display_space'] ) ) {
                            if ( $app->can_do( $permission, null, null, $workspace ) ) {
                                $workspace_menus[] = $item;
                            }
                        }
                    }
                    $ctx->vars['system_menus'] = $system_menus;
                    $ctx->vars['workspace_menus'] = $workspace_menus;
                }
                if ( $user ) {
                    $ctx->vars['user_name'] = $user->name;
                    $ctx->vars['user_nickname'] = $user->nickname;
                    $ctx->vars['user_id'] = $user->id;
                    $ctx->vars['user_space_order'] = $user->space_order;
                    $ctx->vars['user_text_format'] = $user->text_format;
                }
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
            }
            $screen_id = $app->param( '_screen_id' );
            $app->screen_id = $screen_id;
            if ( $app->mode !== 'start_recover' 
                 && $app->mode !== 'recover_password' && ! $app->is_login() )
                return $app->__mode( 'login' );
            if ( $model = $app->param( '_model' ) ) {
                $table = $app->get_table( $model );
            }
            if ( $workspace ) {
                if (!$user->is_superuser ) {
                    $permissions = array_keys( $app->permissions() );
                    if (! in_array( $workspace_id, $permissions ) ) {
                        $app->return_to_dashboard( ['permission' => 1], true );
                    }
                }
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
            $ctx->vars['query_string'] = http_build_query( $params );
            $ctx->vars['raw_query_string'] = $app->query_string( true );
            if ( $return_args = $app->param( 'return_args' ) ) {
                parse_str( $return_args, $app->return_args );
            }
            if ( in_array( $mode, $app->methods ) ) {
                return $app->$mode( $app );
            }
            return $app->__mode( $mode );
        } catch ( Exception $e ) {
            $errstr = $e->getMessage();
            if ( $app->txn_active ) {
                return $app->rollback( $errstr );
            } else {
                return $app->error( $errstr );
            }
        }
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
                include_once( $_component );
            if ( class_exists( $class_name ) ) {
                $component = new $class_name();
                $app->components[ strtolower( $class_name ) ] = $component;
                return $component;
            }
        }
    }

    function component ( $component ) {
        if ( is_object( $component ) ) return $component;
        $components = $this->components;
        if ( isset( $components[ strtolower( $component ) ] ) )
            return $components[ strtolower( $component ) ];
        $component_paths = $this->class_paths;
        if ( isset( $component_paths[ strtolower( $component ) ] ) ) {
            $_plugin = $component_paths[ strtolower( $component ) ];
            if (! class_exists( $component ) ) include_once( $_plugin );
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
        if ( class_exists( $component ) ) return new $component();
        return null;
    }

    function register_callback ( $model, $kind, $meth, $priority, $obj = null ) {
        // unset( $this->registered_callbacks[ $model ][ $kind ] );
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
                    $modal = isset( $prop['modal'] ) ? $prop['modal'] : null;
                    $hint = isset( $prop['hint'] ) ? $prop['hint'] : null;
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
                        $reg = array_merge( $prop, $reg );
                        if ( $columns !== null ) $reg['columns'] = $columns;
                        $registries[] = $reg;
                    }
                }
            }
        }
        $all_registries = [];
        if ( is_array( $registries ) ) {
            foreach ( $registries as $reg ) {
                $all_registries[ $reg['name'] ] = $reg;
            }
        }
        return $all_registries;
    }

    function __mode ( $mode, $tmpl = null ) {
        $app = $this;
        if ( $mode === 'logout' ) $app->logout();
        if ( strpos( $mode, '.' ) !== false || strpos( $mode, DS ) !== false ) {
            return $app->error( 'Invalid request.' );
        }
        $ctx = $app->ctx;
        $ctx->vars['this_mode'] = $mode;
        if ( $mode === 'login' ) {
            $app->login();
            $ctx->vars['query_string'] = $app->query_string;
            if ( $app->request_method === 'POST' && ! $app->user() ) {
                $message = $app->translate( 'Login failed: Username or Password was wrong.' );
                $ctx->vars['error'] = $message;
                $name = $app->param( 'name' );
                $faild_user = $app->db->model( 'user' )->get_by_key( ['name' => $name ] );
                $metadata = ['username' => $name,
                             'password' => '*************' ];
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
                $no_lockout_allowed = isset( $cfgs['no_lockout_allowed_ip'] )
                                    ? $cfgs['no_lockout_allowed_ip']->value : false;
                if ( $no_lockout_allowed ) {
                    $allowed_ip = $app->db->model( 'remote_ip' )->get_by_key(
                            ['ip_address' => $app->remote_ip,
                             'class' => ['IN' => ['administrator', 'allow'] ] ] );
                    if (! $allowed_ip->id ) {
                        $no_lockout_allowed = false;
                    }
                }
                if (! $no_lockout_allowed && $limit && $faild_user->id ) {
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
                if (! $no_lockout_allowed && $limit ) {
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
        if ( $mode == 'dashboard' ) {
            $workspace_id = $app->workspace() ? $app->workspace()->id : 0;
            $app_widgets = $app->registry['widgets'];
            $option = $app->db->model( 'option' )->get_by_key(
                ['workspace_id' => $workspace_id,
                 'user_id' => $app->user()->id,
                 'key' => 'dashboard_widget']
                );
            if (! $option->value ) {
                PTUtil::sort_by_order( $app_widgets, 25, true );
            }
            $disp_widgets = [];
            $widget_scope = $app->workspace() ? 'workspace' : 'system';
            foreach ( $app_widgets as $widget => $props ) {
                $scope = isset( $props['scope'] ) ? $props['scope'] : null;
                if ( ! $scope || in_array( $widget_scope, $scope ) ) {
                    $disp_widgets[] = $widget;
                }
            }
            $disabled_widgets = [];
            $disp_widgets = array_unique( $disp_widgets );
            if ( $option->id ) {
                if ( $disabled = $option->data ) {
                    $user_widget = [];
                    $disabled = explode( ',', $disabled );
                    foreach ( $disp_widgets as $widget ) {
                        if (! in_array( $widget, $disabled ) ) {
                            $user_widget[] = $widget;
                        } else {
                            $prop = $app_widgets[ $widget ];
                            $label = $app->component(
                                $prop['component'] )->translate( $prop['label'] );
                            $disabled_widgets[ $widget ] = $label;
                        }
                    }
                    $disp_widgets = $user_widget;
                }
                if ( $widgets = $option->value ) {
                    $widgets = explode( ',', $widgets );
                    $disp_widgets = array_merge( $widgets, $disp_widgets );
                    $user_widget = [];
                    foreach ( $disp_widgets as $widget ) {
                        if (! isset( $disabled_widgets[ $widget ] ) ) {
                            if ( isset( $app_widgets[ $widget ] ) ) {
                                $user_widget[] = $widget;
                            }
                        }
                    }
                    $disp_widgets = array_unique( $user_widget );
                }
            }
            $ctx->vars['dashboard_widgets'] = $disp_widgets;
            $ctx->vars['disabled_widgets'] = $disabled_widgets;
            $option_activity = $app->db->model( 'option' )->get_by_key(
                ['workspace_id' => $workspace_id,
                 'user_id' => $app->user()->id,
                 'key' => 'activity_model']
            );
            if ( $option_activity->id ) {
                $ctx->vars['activity_model'] = $option_activity->value;
                $ctx->vars['activity_column'] = $option_activity->option;
                $ctx->vars['activity_label'] = $option_activity->data;
            } else {
                $ctx->vars['activity_model'] = 'log';
                $ctx->vars['activity_column'] = 'created_on';
                $ctx->vars['activity_label'] = 'Logs';
            }
            if (! $workspace_id && $app->system_info_url ) {
                $ctx->vars['system_info_content'] =
                    $app->get_information();
            }
            if (! $workspace_id && $app->news_box_url ) {
                $ctx->vars['news_box_content'] =
                    $app->get_information( $app->news_box_url, 'news_box' );
            }
        }
        $app->assign_params( $app, $ctx );
        if ( $mode == 'login' && !isset( $app->language[ $app->language ] ) ) {
            $app->set_language( null, $app->language );
            $ctx->vars['page_title'] = $app->translate( $ctx->vars['page_title'] );
        }
        $ctx->params['this_mode'] = $mode;
        $tmpl = $tmpl ? $tmpl : $mode . '.tmpl';
        return $app->build_page( $tmpl );
    }

    function get_information ( $url = '', $key = 'system_info' ) {
        $app = $this;
        $app->logging = false;
        if (! $url ) $url = $app->system_info_url;
        $lang = $app->user()->language;
        $sess = $app->db->model( 'session' )->get_by_key(
            ['name' => $key, 'kind' => 'CH', 'value' => $lang ] );
        $content = null;
        if ( $sess->id ) {
            if ( $sess->expires >= time() ) {
                $content = $sess->text;
            }
        }
        if ( $content === null ) {
            $options = ['http' => ['timeout' => 2] ];
            if ( $app->powercmsx_auth ) {
                $basic = ['Authorization: Basic ' . base64_encode( $app->powercmsx_auth )];
                $options['http']['header'] = $basic;
            }
            $context = stream_context_create( $options );
            $system_info_url = $url . '?app_version=' . $app->app_version;
            $system_info_url .= '&lang=' . $lang;
            $content = file_get_contents( $system_info_url, false, $context );
            $sess->text( $content );
            $sess->expires( time() + $this->sess_expires );
            $sess->start( time() );
            $sess->save();
        }
        return $app->build( $content );
    }

    function user () {
        if ( $this->user ) return $this->user;
        if ( $this->mode === 'upgrade' ) return;
        if (! $this->installed ) return;
        if ( $this->is_login() ) {
            return $this->user;
        }
        $name = $this->param( 'name' );
        $password = $this->param( 'password' );
        if ( $name && $password ) {
            if ( $this->two_factor_auth ) {
                return;
            }
            return $this->login();
        }
    }

    function login ( $model = 'user', $return_url = null ) {
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
            $two_factor_auth = $app->two_factor_auth;
            $token = $app->param( 'token' );
            if ( $two_factor_auth && $token ) {
                $key = $app->param( 'confirmation_code' );
                $user_id = (int) $app->param( 'user_id' );
                $user = $app->db->model( $model )->load( ['id' => $user_id, 'status' => 2] );
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
                        $counter = intval( $sess->value );
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
                }
            } else {
                $name = $app->param( 'name' );
                $password = $app->param( 'password' );
                $user = $app->db->model( $model )->load( ['name' => $name, 'status' => 2] );
                if ( empty( $user ) ) return;
                if (! empty( $user ) ) {
                    $user = $user[0];
                    if ( $user->lockout ) {
                        return $app->redirect( $app->admin_url . '?_lockout=1&_type=user' );
                    }
                    if ( password_verify( $password, $user->password ) ) {
                        if ( $two_factor_auth ) {
                            $app->language = $user->language;
                            $app->ctx->language = $user->language;
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
                            $ctx = $app->ctx;
                            $ctx->vars['confirmation_code'] = $key;
                            $app->set_mail_param( $ctx );
                            $subject = null;
                            $body = null;
                            $template = null;
                            $body = $app->get_mail_tmpl( 'confirmation_code', $template );
                            if ( $template ) {
                                $subject = $template->subject;
                            }
                            $subject = $subject ? $app->translate( $subject, $ctx->vars['appname'] )
                                     : $app->translate( "[%s] Your Confirmation Code", $ctx->vars['appname'] );
                            $body = $app->build( $body );
                            $subject = $app->build( $subject );
                            if (! PTUtil::send_mail(
                                $user->email, $subject, $body, $headers, $error ) ) {
                                $ctx->vars['error'] = $error;
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
                ->get_by_key( ['user_id' => $user->id, 'kind' => 'US', 'key' => $model ] );
            if (! $sess->name ) {
                $token = $app->magic(); # TODO more secure?
                $sess->name( $token );
            } else {
                $token = $sess->name;
            }
            $sess->key( $model );
            $sess->expires( time() + $expires );
            $sess->start = ( time() );
            $sess->save();
            if ( $user ) {
                $app->user = $user;
                $user->last_login_on( date( 'YmdHis' ) );
                $user->last_login_ip( $app->remote_ip );
                $user->save();
                $app->set_language( __DIR__ . DS . 'locale', $user->language );
                $message = $app->translate( "%s '%s' (ID:%s) logged in successfully.",
                                [ $app->translate( ucwords( $user->_model ) ), $user->name, $user->id ] );
                $app->log( ['message'  => $message,
                            'category' => 'login',
                            'model'    => $user->_model,
                            'object_id'=> $user->id,
                            'level'    => 'info'] );
            }
            $path = $app->cookie_path ? $app->cookie_path : $app->path;
            $name = $app->cookie_name;
            $app->bake_cookie( $name, $token, $expires, $path, $remember );
            $return_args = $return_args ? '?' . $return_args : '';
            $return_url = $return_url ? $return_url : $app->param( 'return_url' );
            $return_url = $return_url && strpos( $return_url, '/' ) === 0
                        ? $return_url : $app->admin_url;
            $app->redirect( $return_url . $return_args. '#__login=1' );
        }
    }

    function set_mail_param ( &$ctx, $workspace = null ) {
        $app = $this;
        $workspace = $workspace ? $workspace : $app->workspace();
        $app_name = $workspace
                  ? $workspace->name : $app->appname;
        $portal_url = $workspace
                  ? $workspace->site_url : $app->site_url;
        $ctx->vars['app_name'] = $app_name;
        $ctx->vars['portal_url'] = $portal_url;
        $script_uri = $ctx->vars['script_uri'];
        if ( strpos( $script_uri, 'http' ) === false ) {
            $ctx->vars['script_uri'] = $app->base . $script_uri;
        }
    }

    function logout ( $user = null ) {
        $app = $this;
        $user = $user ? $user : $app->user();
        if ( $user ) {
            $sess = $app->db->model( 'session' )
                ->get_by_key( ['user_id' => $user->id, 'kind' => 'US', 'key' => $user->_model ] );
            if ( $sess->id ) $sess->remove();
            $name = $app->cookie_name;
            $path = $app->cookie_path ? $app->cookie_path : $app->path;
            $app->bake_cookie( $name, '', time() - 1800, $path );
        }
        return $app->__mode( 'login' );
    }

    private function view ( $app, $model = null, $type = null ) {
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
                    || ( $model === 'workspace' && !$app->param( 'id' ) ) ) {
                    if ( $app->param( 'from_dialog' ) ) {
                        parse_str( $app->query_string(), $query_params );
                        unset( $query_params['workspace_id'] );
                        unset( $query_params['from_dialog'] );
                        return $app->redirect(
                          $app->admin_url . '?' . http_build_query( $query_params ) );
                    }
                    $app->return_to_dashboard();
                }
            }
        }
        $dialog_view = $table->dialog_view && $app->param( 'dialog_view' ) ? true : false;
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
        $ctx->vars['has_revision'] = $table->revisable;
        $scheme = $app->get_scheme_from_db( $model );
        $user = $app->user();
        $screen_id = $app->param( '_screen_id' );
        $ctx->vars['has_status'] = $table->has_status;
        $max_status = $app->max_status( $user, $model, $workspace );
        $status_published = $app->status_published( $model );
        $ctx->vars['max_status'] = $max_status;
        $ctx->vars['status_published'] = $status_published;
        $ctx->vars['_default_status'] = $table->default_status;
        $ctx->vars['model_out_path'] = $table->out_path;
        if ( $model !== 'template' || $type == 'list' ) {
            if ( $type == 'edit' ) {
                $maps = $db->model( 'urlmapping' )->count(
                    ['model' => $model, 'workspace_id' => $workspace_id ] );
            } else {
                $maps = $db->model( 'urlmapping' )->count( ['model' => $model ] );
            }
            if ( $maps ) {
                $ctx->vars['_has_mapping'] = 1;
            }
        }
        $workflow = $db->model( 'workflow' )->get_by_key(
            ['model' => $model,
             'workspace_id' => $workspace_id ] );
        $can_hierarchy = false;
        if ( $table->hierarchy ) {
            $can_hierarchy = $app->can_do(
                        $model, 'hierarchy', null, $workspace );
            $ctx->vars['can_hierarchy'] = $can_hierarchy;
        }
        $can_duplicate = false;
        if ( $table->can_duplicate ) {
            $can_duplicate = $app->can_do( $model, 'duplicate', null, $workspace );
        }
        $ctx->vars['can_duplicate'] = $can_duplicate;
        $ctx->vars['menu_type'] = $table->menu_type;
        if ( $type === 'list' ) {
            $can_any = false;
            if (! $dialog_view ) {
                $perms = $app->permissions();
                if (!$app->can_do( $model, 'list', null, $workspace ) ) {
                    if (! $workspace ) {
                        foreach ( $perms as $wsId => $perm ) {
                            if ( in_array( 'workspace_admin', $perm )
                                || in_array( 'can_list_' . $model, $perm )
                                || in_array( 'can_all_list_' . $model, $perm ) ) {
                                $can_any = true;
                                break;
                            }
                        }
                    }
                    if (!$can_any ) {
                        $app->return_to_dashboard( ['permission' => 1], true );
                    }
                }
                if ( $table->revisable ) {
                    $can_revision = $app->can_do( $model, 'revision', null, $workspace );
                    if (! $can_revision && ! $workspace && $table->display_system ) {
                        foreach ( $perms as $wsId => $perm ) {
                            if ( in_array( 'can_revision_' . $model, $perm ) ) {
                                $can_revision = true;
                            }
                        }
                    }
                    if (! $can_revision && $app->param( 'revision_select' ) ) {
                        $dialog = $app->param( 'dialog_view' ) ? true : false;
                        $app->error( 'Permission denied.', $dialog );
                    }
                    $ctx->vars['can_revision'] = $can_revision;
                }
            }
            $app->init_callbacks( $model, 'start_listing' );
            $callback = ['name' => 'start_listing', 'model' => $model,
                         'scheme' => $scheme, 'table' => $table ];
            $app->run_callbacks( $callback, $model );
            $scheme = $callback['scheme'];
            $ctx->vars['page_title'] = $app->translate( 'List of %s', $plural );
            $list_option = $app->get_user_opt( $model, 'list_option', $workspace_id );
            $list_props = $scheme['list_properties'];
            $column_defs = $scheme['column_defs'];
            $labels = $scheme['labels'];
            $search_props = [];
            $sort_props   = [];
            $filter_props = [];
            $indexes = $scheme['indexes'];
            $ws_status_map = [];
            $ws_user_map = [];
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
            $obj = $db->model( $model );
            if ( $app->param( 'revision_select' ) || $app->param( 'manage_revision' ) ) {
                $ctx->vars['page_title'] =
                    $app->translate( 'List Revisions of %s', $label );
                $cols = 'rev_note,rev_diff,rev_changed,modified_by,modified_on';
                if ( $obj->has_column( 'has_deadline' ) && $obj->has_column( 'status' ) ) {
                    $cols = 'status,' . $cols;
                }
                if (! $dialog_view ) {
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
            if ( $list_option->value ) {
                $search_type = (int) $list_option->value;
                $ctx->vars['_search_type'] = $search_type;
                $app->search_type = $search_type;
            }
            $user_options = explode( ',', $list_option->option );
            if (!$list_option->id ) {
                if ( isset( $scheme['default_list_items'] ) &&
                !$app->param( 'revision_select' ) && !$app->param( 'manage_revision' ) ) {
                    $user_options = $scheme['default_list_items'];
                } else {
                    $option_ws = in_array( 'workspace_id', $user_options ) ? true : false;
                    $max_cols = $dialog_view ? 3 : 7;
                    $user_options = array_slice( $user_options, 0, $max_cols );
                    if ( $option_ws && ! in_array( 'workspace_id', $user_options ) ) {
                        $user_options[] = 'workspace_id';
                    }
                    if ( $table->primary && ! in_array( $table->primary, $user_options ) ) {
                        array_unshift( $user_options, $table->primary );
                    }
                }
            }
            $_colprefix = $obj->_colprefix;
            if ( $app->workspace() && in_array( 'workspace_id', $user_options ) ) {
                $search = array_search( 'workspace_id',  $user_options );
                $split = array_splice( $user_options, $search, 1 );
            }
            if ( $app->param( 'revision_select' ) || $app->param( 'manage_revision' ) ) {
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
            $sorted_props = [];
            $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];
            foreach ( $list_props as $col => $prop ) {
                $user_opt = in_array( $col, $user_options ) ? 1 : 0;
                $sorted_props[ $col ] = [ $app->translate( $labels[ $col ] ), $user_opt, $prop ];
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
            $ctx->vars['_can_hide_list_col'] = true;
            $hide_list_options = isset( $scheme['hide_list_options'] )
                               ? $scheme['hide_list_options'] : [];
            if ( $table->revisable ) {
                if ( $app->param( 'manage_revision' ) ) {
                    if ( $workflow->id && ! in_array( 'user_id', $user_options ) ) {
                        $user_options[] = 'user_id';
                    }
                } else {
                    $hide_list_options[] = 'rev_type';
                    if ( in_array( 'rev_type', $user_options ) ) {
                        $idx = array_search( 'rev_type', $user_options );
                        unset( $user_options[ $idx ] );
                    }
                }
            }
            $ctx->vars['hide_list_options'] = $hide_list_options;
            $app->disp_option = $user_options;
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
            $list_max_status = $max_status;
            $count_extra = $extra;
            if ( $user->is_superuser ) {
                $ctx->vars['can_create'] = 1;
            } else {
                $permissions = $app->permissions();
                if ( $workspace ) {
                    $perms = ( $workspace && isset( $permissions[ $workspace->id ] ) )
                              ? $permissions[ $workspace->id ] : [];
                    $permissions = [ $workspace->id => $perms ];
                    if ( in_array( 'workspace_admin', $perms )
                        || in_array( 'can_create_' . $model, $perms ) ) {
                        $ctx->vars['can_create'] = 1;
                    }
                } else if ( isset( $permissions[0] ) ) {
                    if ( in_array( 'can_create_' . $model, $permissions[0] ) ) {
                        $ctx->vars['can_create'] = 1;
                    }
                }
                $user_id = $user->id;
                if ( $table->has_status ) {
                    $has_deadline = $obj->has_column( 'has_deadline' ) ? true : false;
                    $status_published = $app->status_published( $obj->_model ) - 1;
                    if ( $has_deadline ) {
                        $status_published = $status_published - 2;
                    }
                }
                if ( empty( $permissions ) ) {
                    $app->error( 'Permission denied.' );
                }
                $extra_permission = [];
                $count_permission = [];
                $ws_ids = [];
                $min_status = $table->start_end ? 1 : 2;
                foreach ( $permissions as $ws_id => $perms ) {
                    if (! in_array( 'workspace_admin', $perms ) &&
                        ! in_array( 'can_list_' . $model, $perms )
                        && ! in_array( 'can_all_list_' . $model, $perms ) ) {
                        continue;
                    }
                    if (! $app->workspace() ) {
                        $max = $app->max_status( $user, $model, $ws_id );
                        $list_max_status = ( $list_max_status < $max )
                                         ? $max : $list_max_status;
                    }
                    $ws_permission = '';
                    if ( $obj->has_column( 'workspace_id' ) ) {
                        if ( in_array( 'workspace_admin', $perms )
                            || in_array( 'can_list_' . $model, $perms )
                            || in_array( 'can_all_list_' . $model, $perms ) ) {
                            $ws_ids[] = (int) $ws_id;
                        }
                    }
                    if ( $table->has_status ) {
                        $ws_status_map[ $ws_id ] = " {$_colprefix}status >= 0 ";
                    }
                    if ( $obj->has_column( 'user_id' ) ) {
                        $ws_user_map[ $ws_id ] = " {$_colprefix}user_id >= 0 ";
                    }
                    if (! $dialog_view ) {
                        if (! in_array( 'workspace_admin', $perms ) ) {
                            if ( $table->has_status ) {
                                if (! in_array( 'can_activate_' . $model, $perms )
                                    && ! in_array( 'can_all_list_' . $model, $perms ) ) {
                                    if ( $workspace ) {
                                        if ( $ws_permission ) $ws_permission .= ' AND ';
                                        if (! in_array( 'can_review_' . $model, $perms ) ) {
                                            $ws_permission .= " {$_colprefix}status < {$min_status}";
                                        } else {
                                            $ws_permission .=
                                                " {$_colprefix}status <= {$status_published}";
                                        }
                                    } else {
                                        if (! in_array( 'can_review_' . $model, $perms ) ) {
                                            $ws_status_map[ $ws_id ] = " {$_colprefix}status < {$min_status}";
                                        } else {
                                            $ws_status_map[ $ws_id ] =
                                                " {$_colprefix}status <= {$status_published}";
                                        }
                                    }
                                }
                            }
                            if ( $obj->has_column( 'user_id' ) ) {
                                if (! in_array( 'can_update_all_' . $model, $perms )
                                    && ! in_array( 'can_all_list_' . $model, $perms ) ) {
                                    if ( in_array( 'can_update_own_' . $model, $perms ) ) {
                                        if ( $workspace ) {
                                            if ( $ws_permission ) $ws_permission .= ' AND ';
                                            $ws_permission .= " {$_colprefix}user_id={$user_id}";
                                        } else {
                                            $ws_user_map[ $ws_id ] = " {$_colprefix}user_id={$user_id}";
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if ( $ws_permission ) {
                        $ws_permission = "($ws_permission)";
                        if (! in_array( 'workspace_admin', $perms ) &&
                            ! in_array( 'can_all_list_' . $model, $perms ) ) {
                            $extra_permission[] = $ws_permission;
                        } else {
                            $count_permission[] = $ws_permission;
                        }
                    }
                }
                if (! empty( $extra_permission ) ) {
                    $extra_permission = join( ' OR ', $extra_permission );
                    $extra .= ' AND ';
                    $extra .= $extra_permission;
                }
                if (! empty( $count_permission ) ) {
                    $count_permission = join( ' OR ', $count_permission );
                    $count_extra .= ' AND ';
                    $count_extra .= $count_permission;
                }
                if (! empty( $ws_ids ) ) {
                    $extra .= ' AND ';
                    $count_extra .= ' AND ';
                    $ws_ids = join( ',', $ws_ids );
                    $extra .= " {$_colprefix}workspace_id IN ({$ws_ids})";
                    $count_extra .= " {$_colprefix}workspace_id IN ({$ws_ids})";
                }
            }
            if (! empty( $ws_status_map ) ) {
                unset( $terms['status'] );
                $extra .= ' AND (';
                $count_extra .= ' AND (';
                $_loop_cnt = 0;
                foreach ( $ws_status_map as $_ws_id => $condition ) {
                    if ( $_loop_cnt ) {
                        $extra .= ' OR ';
                        $count_extra .= ' OR ';
                    }
                    $extra .= 
                        $obj->has_column( 'workspace_id' )
                        ? "({$_colprefix}workspace_id={$_ws_id} AND {$condition})"
                        : "({$condition})";
                    $count_extra .= $obj->has_column( 'workspace_id' )
                        ? "({$_colprefix}workspace_id={$_ws_id} AND {$condition})"
                        : "({$condition})";
                    $_loop_cnt++;
                }
                $extra .= ')';
                $count_extra .= ')';
            }
            if (! empty( $ws_user_map ) ) {
                unset( $terms['user_id'] );
                $extra .= ' AND (';
                $count_extra .= ' AND (';
                $_loop_cnt = 0;
                foreach ( $ws_user_map as $_ws_id => $condition ) {
                    if ( $_loop_cnt ) {
                        $extra .= ' OR ';
                        $count_extra .= ' OR ';
                    }
                    $extra .= $obj->has_column( 'workspace_id' )
                            ? "({$_colprefix}workspace_id={$_ws_id} AND {$condition})"
                            : "({$condition})";
                    $count_extra .= $obj->has_column( 'workspace_id' )
                            ? "({$_colprefix}workspace_id={$_ws_id} AND {$condition})"
                            : "({$condition})";
                    $_loop_cnt++;
                }
                $extra .= ')';
                $count_extra .= ')';
            }
            $ctx->vars['list_max_status'] = $list_max_status;
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
            $app->register_callback( $model, 'pre_listing', 'pre_listing', 1, $app );
            $app->init_callbacks( $model, 'pre_listing' );
            $callback = ['name' => 'pre_listing', 'model' => $model,
                         'scheme' => $scheme, 'table' => $table ];
            $app->run_callbacks( $callback, $model, $terms, $args, $extra );
            if ( $table->revisable ) {
                if (!$app->param( 'rev_object_id' ) && !$app->param( 'manage_revision' ) ) {
                    if ( $extra ) {
                        $extra = " AND ( {$_colprefix}id != 0 {$extra} )";
                    }
                    if (! isset( $terms['rev_type'] ) ) {
                        $extra .= " AND {$_colprefix}rev_type=0 ";
                    }
                    if ( $count_extra ) {
                        $count_extra = " AND ( {$_colprefix}id != 0 {$count_extra} )";
                    }
                    $count_extra .= " AND {$_colprefix}rev_type=0 ";
                } else if ( $app->param( 'manage_revision' ) ) {
                    if ( $extra ) {
                        $extra = " AND ( {$_colprefix}id != 0 {$extra} )";
                    }
                    $extra .= " AND {$_colprefix}rev_type !=0 ";
                    if ( $count_extra ) {
                        $count_extra = " AND ( {$_colprefix}id != 0 {$count_extra} )";
                    }
                    $count_extra .= " AND {$_colprefix}rev_type !=0 ";
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
            if ( $obj->has_column( 'workspace_id' ) ) {
                if (! in_array( 'workspace_id', $select_cols ) ) {
                    $select_cols[] = 'workspace_id';
                }
            }
            if ( $obj->has_column( 'rev_type' ) ) {
                $select_cols[] = 'rev_type';
            }
            if ( $model == 'urlinfo' ) {
                $select_cols[] = 'model';
                $select_cols[] = 'object_id';
                $select_cols = array_unique( $select_cols );
            }
            $select_cols = join( ',', $select_cols );
            if ( $app->param( 'revision_select' ) && $obj->has_column( 'modified_on' ) ) {
                if ( $obj->has_column( 'modified_on' ) ) {
                    $args['sort'] = 'modified_on';
                } else {
                    $args['sort'] = 'id';
                }
                $args['direction'] = 'descend';
            }
            if ( $q = $app->param( 'query' ) ) {
                $pre_load_objects = $obj->load( $terms, [], 'id', $extra );
                if (! empty( $pre_load_objects ) ) {
                    $match_ids = [];
                    foreach ( $pre_load_objects as $match_obj ) {
                        $match_ids[] = (int) $match_obj->id;
                    }
                    $add_extra = " AND {$model}_id IN (" . implode( ',', $match_ids ) . ') ';
                    $extra .= $add_extra;
                    $count_extra .= $add_extra;
                }
                $terms = [];
                $args['and_or'] = 'or';
                $ctx->vars['query'] = $q;
                $cols = array_keys( $search_props );
                $q = trim( $q );
                $q = mb_convert_kana( $q, 's', $app->encoding );
                if ( $app->search_type != 1 ) {
                    $cond = $app->search_type == 2 ? 'or' : 'and';
                    $qs = preg_split( "/\s+/", $q );
                    $conditions = [];
                    $counter = 0;
                    if ( count( $qs ) > 1 ) {
                        foreach ( $qs as $s ) {
                            $s = $db->escape_like( $s, 1, 1 );
                            if (!$counter ) {
                                $conditions[] = ['LIKE' => $s ];
                            } else {
                                $conditions[] = ['LIKE' => [ $cond => $s ] ];
                            }
                            $counter++;
                        }
                    } else {
                        $conditions = ['LIKE' => $db->escape_like( $q, 1, 1 ) ];
                    }
                } else {
                    $conditions = ['LIKE' => $db->escape_like( $q, 1, 1 ) ];
                }
                foreach ( $cols as $col ) {
                    if ( $obj->has_column( $col ) )
                        $terms[ $col ] = $conditions;
                }
            }
            if ( $table->sortable && $table->hierarchy && !$app->workspace()
                && $obj->has_column( 'workspace_id' ) ) {
                $terms['workspace_id'] = 0;
                if ( $colPos = array_search( 'workspace_id', $user_options ) ) {
                    unset( $user_options[ $colPos ] );
                }
            }
            if ( $model == 'urlinfo' && $app->param( '_menu_item' ) ) {
                $terms['delete_flag'] = 0;
            }
            $objects = $obj->load( $terms, $args, $select_cols, $extra );
            unset( $args['limit'], $args['offset'] );
            $count = $app->param( 'totalResult' )
                   ? $app->param( 'totalResult' )
                   : $obj->count( $terms, $args, $select_cols, $extra );
            $permitted_count = $count;
            if ( $count_extra != $extra ) {
                $permitted_count =
                    $obj->count( $terms, $args, $select_cols, $count_extra, true );
                    // 5th argument for urlinfo
            }
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
                if ( !$app->param( 'revision_select' )
                     && !isset( $list_props[ $option ] ) ) continue;
                if ( isset( $list_props[ $option ] ) &&
                     $list_props[ $option ] == 'primary' ) $has_primary = true;
                $col_props = ['_name'  => $option,
                              '_label' => $app->translate( $labels[ $option ] ),
                              '_list'  => isset( $list_props[ $option ] )
                                       ? $list_props[ $option ] : '',
                              '_type'  => $column_defs[ $option ]['type'] ];
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
            $ctx->vars['permitted_count']  = $permitted_count;
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
                $ctx->vars['_field_sort_order'] = implode( ',', $field_sort_order );
            } else {
                $display_options = explode( ',', $user_option->option );
                $ctx->vars['_field_sort_order'] = $user_option->data;
            }
            $ctx->vars['__show_loader'] = true;
            $ctx->vars['__format_default'] = $table->text_format;
            if ( $workflow->id ) {
                $ctx->vars['_has_workflow'] = 1;
                $ctx->vars['_workflow_approval_type'] = $workflow->approval_type;
                $ctx->vars['_workflow_remand_type'] = $workflow->remand_type;
                $group_name = $app->permission_group( $user, $model, $workspace_id );
                $ctx->vars['_workflow_user_type'] = $group_name;
                $ctx->stash( 'workflow', $workflow );
                $app->stash( 'workflow', $workflow );
            }
            $ctx->stash( 'disable_edit_options', ['status', 'user_id'] );
            $ctx->vars['can_revision']
                = $app->can_do( $model, 'revision', null, $workspace );
            $ctx->vars['hide_edit_options'] = 
                isset( $scheme['hide_edit_options'] )
                ? $scheme['hide_edit_options'] : [];
            $ctx->vars['display_options'] = $display_options;
            $ctx->vars['_auditing'] = $table->auditing;
            $ctx->vars['_revisable'] = $table->revisable;
            $ctx->vars['_assign_user'] = $table->assign_user;
            $ctx->vars['_can_hide_edit_col'] = true;
            $ctx->vars['_can_sort_edit_col'] = true;
            if ( $model == 'question' ) {
                if ( isset( $app->registry['form_validations'] ) ) {
                    $ctx->vars['form_validations'] = $app->registry['form_validations'];
                }
            }
            if ( $key = $app->param( 'view' ) ) {
                $session = [];
                if ( $screen_id ) {
                    if ( $attachmentfile = $app->param( 'attachmentfile' ) ) {
                        $screen_id .= '-' . $attachmentfile;
                    } else {
                        $screen_id .= '-' . $key;
                    }
                    $terms = ['name' => $screen_id, 'user_id' => $user->id ];
                    $session_id = $app->param( 'session_id' );
                    if ( $session_id ) {
                        $terms['id'] = (int) $session_id;
                    }
                    $session = $db->model( 'session' )->load( $terms );
                } else {
                    $session_id = $app->param( 'id' );
                    if ( strpos( $session_id, 'session' ) === 0 ) {
                        $session_id = str_replace( 'session-', '', $session_id );
                        $terms = ['id' => (int) $session_id, 'user_id' => $user->id ];
                        $session = $db->model( 'session' )->load( $terms );
                    }
                }
                if ( !empty( $session ) ) {
                    $session = $session[0];
                    $assetproperty = json_decode( $session->text, true );
                    $app->asset_out( $assetproperty, $session->data );
                }
            }
            $ctx->vars['object_user_id'] = $user->id;
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
                if ( $model == 'urlinfo' ) {
                    $obj = $db->model( $model )->get_by_key(
                        ['id' => $id, 'delete_flag' => ['IN' => [ 0,1 ] ] ] );
                } else {
                    $obj = $db->model( $model )->load( $id );
                }
                if ( is_object( $obj ) ) {
                    if (! $app->can_do( $model, 'edit', $obj ) ) {
                        $app->error( 'Permission denied.' );
                    }
                    if ( $obj->has_column( 'user_id' ) ) {
                        $ctx->vars['object_user_id'] = $obj->user_id;
                    }
                    if ( $workflow->id ) {
                        if ( $owner = $obj->user ) {
                            $group_name =
                                $app->permission_group( $owner, $model, $workspace_id );
                            $ctx->vars['_workflow_owner_type'] = $group_name;
                        }
                    }
                    if ( $table->revisable ) {
                        if ( $obj->rev_type && !$app->param( 'edit_revision' ) ) {
                            return $app->redirect( $app->admin_url
                                . '?' . $app->query_string . '&edit_revision=1' );
                        }
                        $revisions = $db->model( $obj->_model )->count(
                            ['rev_object_id' => $obj->id ] );
                        $ctx->vars['_revision_count'] = $revisions;
                    }
                    if ( $primary = $table->primary ) {
                        $primary = strip_tags( $obj->$primary );
                        if ( isset( $scheme['translate'] ) ) {
                            if ( in_array( $table->primary, $scheme['translate'] ) ) {
                                $primary = $app->translate( $primary );
                            }
                        }
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
                    if ( $app->param( 'edit_revision' ) && $obj->has_column( 'status' ) ) {
                        if ( $rev_object_id = (int) $obj->rev_object_id ) {
                            $master = $db->model( $obj->_model )->load( $rev_object_id );
                            $ctx->vars['_master_status'] = $master->status;
                        }
                    }
                    $ctx->vars['can_delete'] = $app->can_do( $model, 'delete', $obj );
                    if ( $model === 'template' ) {
                        if ( $obj->id ) {
                            ob_start();
                            $tag_parser = new PTTagParser( $app );
                            $text = $obj->text;
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
                            ob_end_clean();
                            $ctx->vars['parser_errors'] = $app->stash( 'parser_errors' );
                        }
                        $ctx->vars['_has_mapping'] = 1;
                    }
                } else {
                    $app->return_to_dashboard();
                }
            } else {
                if (! $app->can_do( $model, 'create' ) ) {
                    $app->error( 'Permission denied.' );
                }
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
            $ctx->vars['can_create'] = $app->can_do( $model, 'create' );
            $ctx->stash( 'current_context', $model );
            if ( $app->get_permalink( $obj, true ) ) {
                $ctx->vars['has_mapping'] = 1;
            }
            $ctx->vars['screen_id'] = $screen_id ? $screen_id : $app->magic();
        } else if ( $type === 'hierarchy' ) {
            if (! $can_hierarchy ) {
                $app->error( 'Permission denied.' );
            }
            $ctx->vars['page_title'] = $app->translate( 'Manage %s Hierarchy', $plural );
            if ( $app->param( 'saved_hierarchy' ) ) {
                $ctx->vars['header_alert_message'] =
                    $app->translate( '%s hierarchy saved successfully.', $plural );
            }
        }
        if ( $model == 'table' && $obj->id && !$app->ignore_max_input ) {
            $count = $db->model( 'column' )->count( ['table_id' => $obj->id ] );
            $max_input_vars = ini_get( 'max_input_vars' );
            $count = $count * 18 + 50;
            if ( $count > $max_input_vars ) {
                $msg = $app->translate(
                    'The recommended value of max_input_vars is %s or more'
                    . ' (current value is %s).', [ $count, $max_input_vars ] );
                $ctx->vars['header_alert_message'] =
                    isset( $ctx->vars['header_alert_message'] )
                    ? $ctx->vars['header_alert_message'] . $msg : $msg;
                $ctx->vars['header_alert_class'] = 'danger';
                $ctx->vars['header_alert_force'] = true;
            }
        }
        if ( $type == 'edit' && ( $model == 'entry' || $model == 'page' ) ) {
            if ( $workspace ) {
                $ctx->vars['show_path_entry'] = $workspace->show_path_entry;
                $ctx->vars['show_path_page'] = $workspace->show_path_page;
            } else {
                $show_path_entry = $app->get_config( 'show_path_entry' );
                $ctx->vars['show_path_entry'] = $show_path_entry && $show_path_entry->value;
                $show_path_page = $app->get_config( 'show_path_page' );
                $ctx->vars['show_path_page'] = $show_path_page && $show_path_page->value;
            }
        }
        $ctx->vars['return_args'] = http_build_query( $app->return_args );
        $ctx->local_vars = [];
        return $app->build_page( $tmpl );
    }

    function build ( $text, $ctx = null ) {
        $app = $this;
        $ctx = $ctx ? $ctx : $app->ctx;
        $ctx->vars['theme_static'] = $app->theme_static;
        $ctx->vars['application_dir'] = __DIR__;
        $ctx->vars['application_path'] = $app->path;
        $tmpl_markup = $app->tmpl_markup;
        if ( $tmpl_markup === 'mt' ) {
            return $ctx->build( $text );
        } else if ( $tmpl_markup === 'smarty' ) {
            $text = preg_replace( '/<mt:{0,1}([^>]*?)>/is', '{$1}', $text );
            $text = preg_replace( '/<\/mt:{0,1}([^>]*)?>/is', '{/$1}', $text );
            list ( $pfx, $ldelim, $rdelim ) = [ $ctx->prefix, $ctx->ldelim, $ctx->rdelim ];
            $ctx->quoted_vars = [];
            $ctx->prefix = '';
            $ctx->ldelim = '{';
            $ctx->rdelim = '}';
            $ctx->inited = false;
            $ctx->tag_block = ['{', '}'];
            $build = $ctx->build( $text );
            list( $ctx->prefix, $ctx->ldelim, $ctx->rdelim ) = [ $pfx, $ldelim, $rdelim ];
            $ctx->tag_block = [ $ctx->ldelim, $ctx->rdelim ];
            $ctx->quoted_vars = [];
            return $build;
        }
    }

    function build_page ( $tmpl, $param = [], $output = true ) {
        if ( $this->output_compression && !headers_sent() ) {
            ini_set( 'zlib.output_compression', 'On' );
        }
        $app = $this;
        if (!$app->debug && $output ) {
            header( 'Cache-Control: no-store, no-cache, must-revalidate' );
            header( 'Cache-Control: post-check=0, pre-check=0', false );
            header( 'Content-type: text/html' );
        }
        if (! isset( $app->ctx->vars['appname'] ) ) {
            $appname = $app->appname;
            if (! $appname ) {
                if ( $cfg = $app->get_config( 'appname' ) ) {
                    $appname = $cfg->value;
                } else {
                    $appname = 'System';
                }
            }
            $app->ctx->vars['appname'] = $appname;
        }
        $app->ctx->vars['debug_mode'] = $app->debug ? 1 : 0;
        $alternative = null;
        $model = $app->param( '_model' );
        if ( $app->mode == 'view' && $app->user() ) {
            $type = $app->param( '_type' );
            $alternative = "{$type}_{$model}.tmpl";
            $alternative = $app->ctx->get_template_path( $alternative );
        }
        if ( $tmpl && is_object( $tmpl ) ) {
            $out = $app->build( $tmpl->text );
        } else {
            $tmpl = $alternative ? $alternative : $app->ctx->get_template_path( $tmpl );
            if (!$tmpl ) return;
            $src = file_get_contents( $tmpl );
            $cache_id = null;
            $callback = ['name' => 'template_source', 'template' => $tmpl, 'model' => $model ];
            $basename = pathinfo( $tmpl, PATHINFO_FILENAME );
            $app->init_callbacks( $basename, 'template_source' );
            $app->run_callbacks( $callback, $basename, $param, $src );
            $out = $app->ctx->build_page( $tmpl, $param, $cache_id, false, $src );
            $app->init_callbacks( $basename, 'template_output' );
            $callback = ['name' => 'template_output', 'template' => $tmpl ];
            $app->run_callbacks( $callback, $basename, $param, $src, $out );
        }
        if (!$output ) return $out;
        if ( $app->debug ) {
            $ctx = new PAML;
            foreach ( $app->template_paths as $tmpl_dir ) {
                $ctx->include_paths[ $tmpl_dir ] = true;
            }
            $ctx->prefix = 'mt';
            $time = microtime( true );
            $processing_time = $time - $this->start_time;
            $debug_tmpl = TMPL_DIR . DS . 'include' . DS . 'footer_debug.tmpl';
            $ctx->vars['processing_time'] = round( $processing_time, 2 );
            $ctx->vars['prototype_path'] = $this->app_path ? $this->app_path : $this->path;
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

    function get_permalink ( $obj, $has_map = false, $rebuild = true, $system = false ) {
        if (! $obj->id && $has_map === false ) return null;
        $app = $this;
        $table = $app->get_table( $obj->_model );
        if ( $obj->_model == 'asset' || $obj->_model == 'attachmentfile' ) {
            $url = $app->db->model( 'urlinfo' )->load( [
                'model' => $obj->_model, 'object_id' => $obj->id, 'class' => 'file'] );
            if ( is_array( $url ) && ! empty( $url ) ) {
                $url = $url[0];
                return $url->url;
            }
            return false;
        }
        $terms = ['model' => $obj->_model ];
        if ( $obj->_model === 'template' ) {
            $terms['template_id'] = $obj->id;
            unset( $terms['model'] );
        }
        $args = ['sort_by' => 'order', 'direction' => 'ascend', 'limit'=> 1];
        if (! $system && $obj->has_column( 'workspace_id' ) ) {
            $terms['workspace_id'] = (int) $obj->workspace_id;
        }
        $terms['is_preferred'] = 1;
        $cache_key = 'urlmapping_cache_' . $this->make_cache_key( $terms, $args );
        $urlmapping = $app->stash( $cache_key ) ? $app->stash( $cache_key )
                    : $app->db->model( 'urlmapping' )->load( $terms, $args );
        if ( empty( $urlmapping ) ) {
            unset( $terms['is_preferred'] );
            $urlmapping = $app->db->model( 'urlmapping' )->load( $terms, $args );
        }
        if (! empty( $urlmapping ) && ! $obj->id ) {
            if ( $has_map ) return $urlmapping[0];
            return $app->build_path_with_map( $obj, $urlmapping[0], $table, null, true );
        } else if (! $obj->id ) {
            return '';
        }
        if ( empty( $urlmapping ) && ! $system && $obj->workspace_id ) {
            $app->get_permalink( $obj, $has_map, $rebuild, true );
        }
        $app->stash( $cache_key, $urlmapping );
        if (! empty( $urlmapping ) ) {
            $urlmapping = $urlmapping[0];
            if ( $has_map ) return $urlmapping;
            if ( $obj->_model === 'template' ) {
                $ui = $app->db->model( 'urlinfo' )->get_by_key( [
                      'urlmapping_id' => $urlmapping->id,
                      'delete_flag' => 0, 'class' => 'archive' ] );
                return $ui->url;
            }
            if ( $obj->has_column( 'rev_type' ) && $obj->rev_type && $obj->rev_object_id ) {
                $rev_object_id = (int)$obj->rev_object_id;
                $obj = $app->db->model( $obj->_model )->load( $rev_object_id );
                if (! $obj ) return;
            }
            $terms = ['urlmapping_id' => $urlmapping->id, 'model' => $table->name,
                      'class' => 'archive', 'object_id' => $obj->id, 'delete_flag' => 0 ];
            $cache_key = 'urlmapping_cache_' . $this->make_cache_key( $terms );
            $ui = $app->stash( $cache_key ) ? $app->stash( $cache_key )
                : $app->db->model( 'urlinfo' )->get_by_key( $terms );
            if (! $ui->id ) {
                $urlinfos = $app->db->model( 'urlinfo' )->load( $terms,
                          ['limit' => 1, 'sort' => 'id', 'direction' => 'descend'] );
                if ( count( $urlinfos ) ) {
                    $ui = $urlinfos[0];
                }
            }
            if ( $app->cache_permalink ) $app->stash( $cache_key, $ui );
            if ( $ui && $ui->id ) {
                return $ui->url;
            } else {
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
        if ( is_numeric( $workspace ) ||
            ( is_string( $workspace ) && ctype_digit( $workspace ) ) )
            $workspace = $app->db->model( 'workspace' )->load( (int) $workspace );
        $workspace = $workspace ? $workspace : $app->workspace();
        $workspace_id = is_object( $workspace ) ? $workspace->id : 0;
        $table = $app->get_table( $model );
        $max_status = 1;
        if ( $user->is_superuser ) {
            $max_status = $table->start_end ? 5 : $app->status_published( $model );
        } else {
            if ( $table->has_status ) {
                $group_name = $app->permission_group( $user, $model, $workspace_id );
                $status_published = $app->status_published( $model );
                if ( $status_published === 4 ) {
                    if ( $group_name == 'creator' ) {
                        return 0;
                    } else if ( $group_name == 'reviewer' ) {
                        return 1;
                    } else if ( $group_name == 'publisher' ) {
                        return 5;
                    }
                } else {
                    if ( $group_name == 'publisher' ) {
                        return 2;
                    } else if (! $group_name ) {
                        return 0;
                    }
                }
            }
        }
        return $max_status;
    }

    function is_workspace_admin ( $workspace_id, $user = null ) {
        $app = $this;
        if ( is_object( $workspace_id ) ) {
            $workspace_id = $workspace_id->id;
        }
        $user = $user ? $user : $app->user();
        if (! $user ) return false;
        if ( $user->is_superuser ) return true;
        $perms = $app->permissions( $user );
        if ( isset( $perms[ $workspace_id ] ) ) {
            $perms = $perms[ $workspace_id ];
            return in_array( 'workspace_admin', $perms );
        }
        return false;
    }

    function can_do ( $model, $action = null, $obj = null,
        $workspace = null, $user = null ) {
        $app = $this;
        $user = !$user ? $app->user() : $user;
        if ( $user && $user->_model == 'member' ) return false;
        $workspace = is_object( $workspace ) ? $workspace : $app->workspace();
        if (!$user ) return false;
        $orig_action = $action ? $action : $model;
        $sys_perms = $app->permissions;
        if (!$action && strpos( $model, '_' ) !== false && !in_array( $model, $sys_perms ) ) {
            list( $action, $model ) = explode( '_', $model );
        }
        if ( in_array( $model, $sys_perms ) && is_object( $action ) ) {
            if ( $action->_model == 'workspace' ) {
                $workspace = $action;
            }
        }
        $table = $model ? $app->get_table( $model ) : null;
        if (! $model ) {
            $model = $action;
        }
        if ( !in_array( $model, $sys_perms ) ) {
            if (! $workspace && $obj && $obj->has_column( 'workspace_id' )
                && ( $app->mode == 'view' && $app->param( '_type' ) != 'list' ) ) {
                if ( $table && $table->space_child ) {
                    return false;
                }
            } else if ( $workspace && $obj && $obj->id &&
                $obj->has_column( 'workspace_id' ) ) {
                if ( $workspace->id != $obj->workspace_id ) {
                    return false;
                }
            }
        }
        if ( $model === 'superuser' ) return $user->is_superuser;
        if ( !in_array( $model, $sys_perms ) ) {
            if ( $app->mode !== 'list_action' && $app->mode !== 'get_thumbnail' ) {
                if (!$workspace && ( $obj && ! $obj->workspace ) ) {
                    if ( $table && $table->space_child && $action === 'edit' ) {
                        return false;
                    } else if ( $table && $action === 'list' && !$table->display_system ) {
                        return false;
                    }
                }
            }
        }
        if ( $user->is_superuser ) return true;
        if ( $model == 'user' ) {
            if ( $obj && $obj->id == $user->id ) return true;
        }
        $permissions = $app->permissions( $user );
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
                if ( $obj && ( $model != 'workspace'
                    && !$obj->has_column( 'workspace_id' ) ) ) {
                    return false;
                }
                return true;
            }
        } else {
            $perms = isset( $permissions[0] ) ? $permissions[0] : [];
        }
        if ( $orig_action && in_array( $orig_action, $perms ) ) {
            if ( $workspace ) {
                return in_array( $orig_action, $perms );
            } else {
                return true;
            }
        } else if ( $action == 'list' ) {
            $name = 'can_list_' . $model;
            $all = 'can_all_list_' . $model;
            $perm = in_array( $name, $perms ) ? true : in_array( $all, $perms );
            return $perm;
        } else if ( $action == 'all_list' ) {
            $name = 'can_all_list_' . $model;
            return in_array( $name, $perms );
        } else if ( $action === 'create' || $action === 'revision'
            || $action === 'hierarchy' || $action === 'duplicate'
            || $action === 'edit' || $action === 'save'
            || $action === 'delete' || $action === 'update_all' ) {
            list( $name, $range ) = ['', ''];
            if ( $action === 'hierarchy' || $action === 'revision'
                || $action === 'duplicate' ) {
                $name = "can_{$action}_{$model}";
                return in_array( $name, $perms );
            }
            // if (!$obj || !$obj->id || !$table->assign_user ) {
            $range = null;
            if (!$obj || !$obj->id ) {
                $name = $action == 'delete' ? 'can_delete_' . $model
                                            : 'can_create_' . $model;
                if ( in_array( $name, $perms ) ) {
                    return true;
                }
                if ( $action == 'create' ) return false;
            } else {
                $range = $action != 'delete' ? 'can_update_all_' . $model
                                             : 'can_delete_' . $model;
                if (! $obj->has_column( 'user_id' ) && $action != 'delete' ) {
                    $range = 'can_create_' . $model;
                }
                if ( $obj->has_column( 'status' ) ) {
                    $max_status = $app->max_status( $user, $model, $workspace );
                    if ( $obj->status > $max_status ) {
                        return false;
                    }
                } else if ( $action == 'edit' && ! $obj->has_column( 'user_id' ) ) {
                    $range = 'can_create_' . $model;
                }
            }
            if ( $name && in_array( $name, $perms ) ) {
                return true;
            }
            if ( $range && !in_array( $range, $perms ) ) {
                if ( $obj->has_column( 'user_id' ) ) {
                    $range = 'can_update_own_' . $model;
                    if ( in_array( $range, $perms ) ) {
                        if ( $obj->user_id == $user->id ) {
                            if ( $action == 'delete' ) {
                                return in_array( 'can_delete_' . $model, $perms );
                            }
                            return true;
                        }
                    }
                }
                return false;
            } else if ( $action == 'delete' ) {
                return in_array( $range, $perms );
            } else {
                return true;
            }
        }
        return false;
    }

    function permissions ( $user = null ) {
        $app = $this;
        $user = $user ? $user : $app->user();
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
        $user_permissions = [];
        $role_ids = [];
        $workspace_map = [];
        $tables = $app->db->model( 'table' )->load();
        $table_map = [];
        foreach( $tables as $table ) {
            $table_map[ $table->id ] = $table->name;
        }
        foreach ( $permissions as $perm ) {
            $relations = $app->get_relations( $perm );
            foreach ( $relations as $relation ) {
                if ( $relation->to_obj === 'role' ) {
                    if ( $role = $app->db->model( 'role' )->load( (int) $relation->to_id ) ) {
                        $ws_permission = isset( $user_permissions[ $perm->workspace_id ] )
                                       ? $user_permissions[ $perm->workspace_id ] : [];
                        $perms = $app->get_relations( $role );
                        foreach ( $perms as $p ) {
                            if ( $p->to_obj === 'table' ) {
                                $model = $table_map[ $p->to_id ];
                                $name = $p->name . '_' . $model;
                                if (! in_array( $name, $ws_permission ) ) {
                                    $ws_permission[] = $name;
                                }
                            }
                        }
                        if ( $role->workspace_admin &&
                            ! in_array( 'workspace_admin', $ws_permission ) ) {
                            $ws_permission[] = 'workspace_admin';
                        }
                        if ( $role->can_rebuild &&
                            ! in_array( 'can_rebuild', $ws_permission ) ) {
                            $ws_permission[] = 'can_rebuild';
                        }
                        if ( $role->manage_plugins &&
                            ! in_array( 'manage_plugins', $ws_permission ) ) {
                            $ws_permission[] = 'manage_plugins';
                        }
                        if ( $role->import_objects &&
                            ! in_array( 'import_objects', $ws_permission ) ) {
                            $ws_permission[] = 'import_objects';
                        }
                        if ( $role->can_livepreview &&
                            ! in_array( 'can_livepreview', $ws_permission ) ) {
                            $ws_permission[] = 'can_livepreview';
                        }
                        $user_permissions[ $perm->workspace_id ] = $ws_permission;
                        $role_ids[] = $relation->to_id;
                        $workspace_map[ $relation->to_id ] = $perm->workspace_id;
                    }
                }
            }
        }
        $json = json_encode( $user_permissions );
        $session = $app->db->model( 'session' )->get_by_key(
                                                ['user_id' => $user->id,
                                                 'name' => 'user_permissions',
                                                 'kind' => 'PM'] );
        $session->text( $json );
        $session->start( time() );
        $session->expires( time() + $app->perm_expires );
        $session->save();
        return $user_permissions;
    }

    function asset_out ( $prop, $data ) {
        if ( $this->output_compression ) {
            ini_set( 'zlib.output_compression', 'On' );
        }
        $app = $this;
        if (! is_array( $prop ) || empty( $prop ) ) {
            if ( $data ) {
                header( 'Content-Type: application/octet-stream' );
                $file_size = strlen( bin2hex( $data ) ) / 2;
                header( "Content-Length: {$file_size}" );
                echo $data;
            }
            exit();
        }
        $file_name = $prop['basename'];
        $extension = $prop['extension'];
        $file_name .= $extension ? '.' . $extension : '';
        $file_name = urlencode( $file_name );
        $mime_type = $prop['mime_type'];
        $file_size = $prop['file_size'];
        $download = $app->param( 'download' ) ? true : false;
        if (! $download ) {
            if (! in_array( $extension, $app->images ) && $extension != 'pdf'
                && $extension != 'svg' && strpos( $mime_type, 'text' ) === false ) {
                $download = true;
            }
        }
        header( "Content-Type: {$mime_type}" );
        header( "Content-Length: {$file_size}" );
        if ( $download ) {
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
        $app->txn_active = true;
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
            $app->txn_active = false;
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
        $app->get_scheme_from_db( $model );
        if (! $table ) return $app->error( 'Invalid request.' );
        $workspace_id = $app->param( 'workspace_id' );
        $_nestable_output = $app->param( '_nestable_output' );
        $children = json_decode( $_nestable_output, true );
        $workspace = $app->workspace();
        if (!$app->can_do( $model, 'hierarchy', null, $workspace ) ) {
            return $app->error( 'Permission denied.' );
        }
        $order = 1;
        $error = false;
        $db = $app->db;
        $db->begin_work();
        $app->txn_active = true;
        $app->set_hierarchy( $model, $children, 0, $order, $error );
        if ( $error ) {
            $errstr = $app->translate( 'An error occurred while saving %s.',
                      $app->translate( $table->label ) );
            $app->rollback( $errstr );
        } else {
            $db->commit();
            $app->txn_active = false;
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

    function manage_theme ( $app ) {
        $theme = new PTTheme;
        return $theme->manage_theme( $app );
    }

    function import_objects ( $app ) {
        $importer = new PTImporter;
        return $importer->import_objects( $app );
    }

    function upload_objects ( $app ) {
        $importer = new PTImporter;
        return $importer->upload_objects( $app );
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
                    if ( $save_label && $label ) {
                        if ( $obj->label != $label ) {
                            $obj->label( $label );
                            $obj->save();
                        }
                        PTUtil::update_blob_label( $app, $obj, 'file', $label );
                    }
                    $class = $obj->class;
                    $permalink = $app->get_permalink( $obj );
                    $can_edit = $app->can_do( 'asset', 'edit', $obj ) ? true : false;
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
                                        'url' => $url, 'label' => $label,
                                        'can_edit' => $can_edit,
                                        'id' => $id, 'permalink' => $permalink ];
                    } else {
                        if ( $obj->file_ext == 'svg' ) {
                            $use_thumb = $app->param( 'use-thumb-' . $id );
                            $width = '';
                            $height = '';
                            if ( $use_thumb ) {
                                $width = (int) $app->param( 'thumb-width-' . $id );
                                $height = (int) $app->param( 'thumb-height-' . $id );
                                $scale = $width / 100;
                                $height = round( $height * $scale );
                                $height = (int) $height;
                            }
                            $align = $app->param( 'insert-align-' . $id );
                            $loop_vars[] = ['align' => $align, 'width' => $width,
                                            'class' => 'image', 'height' => $height,
                                            'url' => $url, 'label' => $label,
                                            'can_edit' => $can_edit,
                                            'id' => $id, 'permalink' => $permalink ];
                        } else {
                            $file_size = $assetproperty['file_size'];
                            $loop_vars[] = ['url' => $url, 'label' => $label,
                                            'file_size' => $file_size,
                                            'class' => $obj->class,
                                            'can_edit' => $can_edit,
                                            'id' => $id, 'permalink' => $permalink ];
                        }
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
        if ( $attachmentfile = $app->param( 'attachmentfile' ) ) {
            $session_name = $screen_id . '-' . $attachmentfile;
        } else {
            $session_name = $screen_id . '-' . $key;
        }
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
        $key = $app->escape( $key );
        $model = $app->escape( $model );
        $screen_id = $app->escape( $screen_id );
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
                $size = $fmgr->filesize( $file );
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
                    ['name' => $session_name, 'user_id' => $user->id, 'kind' => 'UP' ]
                );
                $assetproperty = json_decode( $session->text, true );
                $session->data( $image_data );
                $session->metadata( $image_data_thumb );
                $session->extradata( $image_data_square );
                $thumb_dir = dirname( $thumbnail );
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
                $session->value( $upload_dir . DS . $props['file_name'] );
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
                if ( $attachmentfile = $app->param( 'attachmentfile' ) ) {
                    $_REQUEST['view'] = $attachmentfile;
                    $app->param( 'view', $attachmentfile );
                }
            }
        }
        $param = "?__mode=view&amp;_type=edit&amp;_model={$model}&amp;id={$id}"
               ."&amp;_screen_id={$screen_id}&amp;view=";
        if ( $attachmentfile = $app->param( 'attachmentfile' ) ) {
            $param .= 'file&amp;attachmentfile=' . $app->param( 'attachmentfile' );
        } else {
            $param .= $key;
        }
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
            $changed = false;
            if ( $parent != $obj->parent_id ) {
                $obj->parent_id( $parent );
                $changed = true;
            }
            if ( $obj->has_column( 'order' ) ) {
                if ( $obj->order != $order ) {
                    $obj->order( $order );
                    $changed = true;
                }
            }
            if ( $changed ) {
                if (! $obj->save() ) $error = true;
            }
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
            $value = $app->param( '_user_search_type' );
            $user_option->value( $value );
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
        $options .= $app->param( 'workspace_select' ) ?
            '&workspace_select=1' : '';
        $app->redirect( $app->admin_url .
            "?__mode=view&_type={$type}&_model={$model}&saved_props=1"
            . $app->workspace_param . $options );
    }

    function can_edit_object ( $app ) {
        header( 'Content-type: application/json' );
        $id = (int) $app->param( 'id' );
        $model = $app->param( '_model' );
        if (! $id || ! $model ) {
            echo json_encode( ['can_edit_object' => false ] );
            return;
        }
        $table = $app->get_table( $model );
        if (! $table ) {
            echo json_encode( ['can_edit_object' => false ] );
            return;
        }
        $obj = $app->db->model( $model )->load( $id );
        if (! $obj ) {
            echo json_encode( ['can_edit_object' => false ] );
            return;
        }
        if (!$app->can_do( $model, 'edit', $obj ) ) {
            echo json_encode( ['can_edit_object' => false ] );
            return;
        }
        $edit_link = $app->admin_url . "?__mode=view&_type=edit&_model={$model}&id={$id}";
        if ( $obj->has_column( 'workspace_id' ) && $obj->workspace_id ) {
            $edit_link .= '&workspace_id=' . $obj->workspace_id;
        }
        echo json_encode( ['can_edit_object' => true, 'edit_link' => $edit_link ] );
    }

    function get_alternative_icon ( $class, $extension ) {
        $asset_dir = $this->document_root .
            $this->path . 'assets' . DS . 'img' . DS . 'file-icons';
        $asset_dir = str_replace( '\\', DS, $asset_dir );
        $icon = '';
        if ( $class == 'audio' || $class == 'video' || $class == 'pdf' ) {
            $icon = $asset_dir . DS . "{$class}.png";
        } else {
            $icon = file_exists( $asset_dir . DS . "{$extension}.png" )
                  ? $asset_dir . DS . "{$extension}.png" : "";
            if (! $icon ) {
                $icon = file_exists( $asset_dir . DS . "{$extension}x.png" )
                      ? $asset_dir . DS . "{$extension}x.png" : "";
            }
        }
        if (! $icon ) {
            $icon = $asset_dir . DS . "file.png";
        }
        if ( file_exists( $icon ) ) {
            return file_get_contents( $icon );
        }
    }

    function get_temporary_file ( $app ) {
        $id = $app->param( 'id' );
        if ( strpos( $id, 'session-' ) === 0 ) {
            return $this->get_thumbnail( $app );
        }
    }

    function get_thumbnail ( $app ) {
        $id = $app->param( 'id' );
        if ( strpos( $id, 'session-' ) === 0 ) {
            $session_id = str_replace( 'session-', '', $id );
            $session = $app->db->model( 'session' )->load([
                'user_id' => $app->user()->id, 'id' => (int) $session_id,
            ]);
            if (! empty( $session ) ) {
                $session = $session[0];
                $mime_type = $session->key;
                if (! $mime_type ) {
                    $meta = json_decode( $session->text );
                    $mime_type = $meta->mime_type;
                }
                if ( $mime_type == 'image/svg+xml' ) {
                    $app->print( $session->data, $mime_type );
                }
                if ( $app->param( 'square' ) && $session->extradata ) {
                    $app->print( $session->extradata, $mime_type );
                } else if ( $app->param( 'data' ) && $session->data ) {
                    $app->print( $session->data, $mime_type );
                } else if ( $session->metadata ) {
                    $app->print( $session->metadata, $mime_type );
                } else {
                    $prop = json_decode( $session->text, true );
                    $app->print( $app->get_alternative_icon( $prop['class'],
                        $prop['extension'] ), 'image/png' );
                }
            }
        }
        $id = (int) $app->param( 'id' );
        $has_thumbnail = $app->param( 'has_thumbnail' );
        $_model = $app->param( '_model' );
        if ( $has_thumbnail ) {
            header( 'Content-type: application/json' );
        }
        if ( $_model ) {
            $table = $app->get_table( $_model );
            if (! $table ) {
                if ( $has_thumbnail ) echo json_encode( ['has_thumbnail' => false ] );
                return;
            }
        }
        if ( $_model && !$app->can_do( $_model, 'list' ) && $has_thumbnail ) {
            echo json_encode( ['has_thumbnail' => false ] );
            return;
        }
        if (!$id ) {
            if ( $has_thumbnail && $_model ) {
                if ( $_model == 'asset' ) {
                    echo json_encode( ['has_thumbnail' => true ] );
                    return;
                }
                $scheme = $app->get_scheme_from_db( $_model );
                $props = $scheme['edit_properties'];
                foreach ( $props as $prop => $type ) {
                    if ( $type == 'file' ) {
                        $options = isset( $scheme['options'] ) ? $scheme['options'] : [];
                        if ( !empty( $options ) ) {
                            if ( isset( $options[ $prop ] )
                                && $options[ $prop ] == 'image' ) {
                                echo json_encode( ['has_thumbnail' => true ] );
                                return;
                            } else {
                                echo json_encode( ['has_thumbnail' => true ] );
                                return;
                            }
                        } else {
                            echo json_encode( ['has_thumbnail' => true ] );
                            return;
                        }
                    }
                }
                echo json_encode( ['has_thumbnail' => false ] );
                return;
            }
            $app->redirect( $app->app_path . 'assets/img/model-icons/default.png' );
        }
        $meta = null;
        $md = null;
        $mime_type = '';
        if ( $_model ) {
            $meta_objs = $app->db->model( 'meta' )
                ->load( ['object_id' => $id, 'model' => $_model ] );
            if (! is_array( $meta_objs ) || empty( $meta_objs ) ) {
                if ( $has_thumbnail ) {
                    echo json_encode( ['has_thumbnail' => false ] );
                    return;
                }
            }
            foreach ( $meta_objs as $m ) {
                $md = json_decode( $m->text, true );
                if ( isset( $md['class'] )
                    && ( $md['class'] == 'image' || $md['extension'] == 'svg' ) ) {
                    $meta = $m;
                    break;
                }
            }
        } else {
            $meta = $app->db->model( 'meta' )->load( $id );
        }
        $data = '';
        if (!$meta ) {
            if ( $has_thumbnail ) {
                echo json_encode( ['has_thumbnail' => false ] );
                return;
            } else {
                if ( $md ) {
                    $app->print( $app->get_alternative_icon( $md['class'],
                        $md['extension'] ), 'image/png' );
                }
            }
        }
        if (! is_object( $meta ) ) {
            if ( $has_thumbnail ) {
                echo json_encode( ['has_thumbnail' => false ] );
                return;
            } else {
                $icon_base = $app->app_path . 'assets/img/model-icons/';
                $asset_dir = $app->document_root .
                        $app->path . 'assets' . DS . 'img' . DS . 'model-icons';
                $icon_path = $asset_dir . DS . $_model . '.png';
                $default_path = $asset_dir . DS . 'default.png';
                $icon_path = file_exists( $icon_path ) ? $icon_path : $default_path;
                $app->redirect( $icon_base . basename( $icon_path ) );
                return;
            }
        }
        if (! is_object( $meta ) ) {
            return;
        }
        $model = $meta->model;
        if (!$app->can_do( $model, 'list' ) ) {
            if ( $has_thumbnail ) {
                echo json_encode( ['has_thumbnail' => false ] );
                return;
            }
        }
        $matadata = json_decode( $meta->text, true );
        $column = $app->param( 'square' ) ? 'metadata' : 'data';
        $mime_type = $mime_type ? $mime_type : $matadata['mime_type'];
        $column = $app->param( 'square' ) ? 'metadata' : 'data';
        $data = $data ? $data : $meta->$column;
        if ( $matadata['extension'] == 'svg' ) {
            $obj = $app->db->model( $model )->load( (int) $meta->object_id );
            if ( $obj ) {
                $col = $meta->key;
                $data = $obj->$col;
            }
            if (! $data ) {
                return 
                    $app->redirect( $app->app_path . 'assets/img/model-icons/default.png' );
            }
        }
        if (! $data ) {
            $data = $app->param( 'square' ) ? $meta->data : $meta->metadata;
        }
        if (! $data ) {
            if ( $has_thumbnail ) {
                echo json_encode( ['has_thumbnail' => false ] );
                return;
            }
        }
        if ( $has_thumbnail ) {
            echo json_encode( ['has_thumbnail' => true ] );
            return;
        }
        $file_size = strlen( bin2hex( $data ) ) / 2;
        header( "Content-Type: {$mime_type}" );
        header( "Content-Length: {$file_size}" );
        echo $data;
        unset( $data );
    }

    function get_field_html ( $app ) {
        return PTUtil::get_field_html( $app );
    }

    function rebuild_phase ( $app, $start = false, $counter = 0, $dependencies = false ) {
        $ctx = $app->ctx;
        $per_rebuild = $app->per_rebuild;
        // $app->get_scheme_from_db( 'urlinfo' );
        $db = $app->db;
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
        $status_published = null;
        if ( $model && $app->build_published_only ) {
            if (! $app->param('_return_args')
                && $db->model( $model )->has_column( 'status' ) ) {
                $status_published = $app->status_published( $model );
            }
        }
        $app->init_tags();
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
                if ( $app->build_published_only ) {
                    if ( $obj->has_column( 'status' ) ) {
                        $terms['status'] = $app->status_published( $model );
                    }
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
                $objects = $app->db->model( $model )->load( $terms, [], 'id', $extra );
                // $apply_actions = $objects->rowCount();
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
                        $object_ids[] = $obj->id;
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
        $ids = explode( ',', $app->param( 'ids' ) );
        $apply_actions = (int) $app->param( 'apply_actions' );
        // array_walk( $ids, function( &$id ) { $id = (int) $id; } );
        $rebuild_ids = array_slice( $ids , 0, $per_rebuild );
        $next_ids = array_slice( $ids , $per_rebuild );
        $rebuilt = $apply_actions - ( count( $ids ) - count( $rebuild_ids ) );
        $ctx->vars['current_model'] = $model;
        $file_cols = $db->model( 'column' )->count( ['table_id' => $table->id, 'edit' => 'file'] );
        $archives_only = $file_cols ? false : true;
        $db->begin_work();
        $app->txn_active = true;
        if ( $app->build_one_by_one ) { 
            foreach ( $rebuild_ids as $id ) {
                $terms = ['id' => (int) $id ];
                $obj = $db->model( $model )->get_by_key( $terms );
                if (! $obj->id ) continue;
                $cached_vars = $app->ctx->vars;
                $cached_local_vars = $app->ctx->local_vars;
                $app->publish_obj( $obj, null, false, false, $archives_only );
                $app->ctx->vars = $cached_vars;
                $app->ctx->local_vars = $cached_local_vars;
            }
        } else {
            $terms = ['id' => ['IN' => $rebuild_ids ] ];
            $objects = $db->model( $model )->load( $terms );
            foreach ( $objects as $obj ) {
                $cached_vars = $app->ctx->vars;
                $cached_local_vars = $app->ctx->local_vars;
                $app->publish_obj( $obj, null, false, false, $archives_only );
                $app->ctx->vars = $cached_vars;
                $app->ctx->local_vars = $cached_local_vars;
            }
        }
        $db->commit();
        $app->txn_active = false;
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
                if ( isset( $params['__mode'] ) && $params['__mode'] === 'rebuild_phase' ) {
                    $return_args = http_build_query( $params );
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
                if ( isset( $params['_return_args'] ) ) {
                    parse_str( $params['_return_args'], $_return_args );
                    unset( $params['_return_args'] );
                    $params = array_merge( $_return_args, $params );
                }
                unset( $params['magic_token'] );
                $return_args = http_build_query( $params );
                $app->redirect( $app->admin_url . '?' . $return_args );
            }
            if ( $rebuild_last ) {
                $ctx->vars['rebuild_end'] = 1;
                $ctx->vars['publish_time'] = time() - $start_time;
                $ctx->vars['page_title'] = $app->translate( 'Done.' );
                $app->remove_session( $sess_terms );
            } else {
                $title = '';
                if ( $app->param( 'next_models' ) ) {
                    $next_models = explode( ',', $app->param( 'next_models' ) );
                    if ( isset( $next_models[0] ) ) {
                        $next_model = $next_models[0];
                        $table = $app->get_table( $next_model );
                        $plural = $app->translate( $table->plural );
                        $title = $app->translate( 'Rebuilding %s...', $plural );
                    }
                }
                if (! $title ) {
                    $title = $app->translate( 'Rebuilding...' );
                }
                $ctx->vars['icon_url'] = 'assets/img/loading.gif';
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

    function upload ( $app ) {
        if ( empty( $_FILES ) ) {
            $app->json_error( 'Please check the file size and data.' );
        }
        $app->validate_magic( true );
        $upload_dir = $app->upload_dir();
        $screen_id = $app->param( '_screen_id' );
        $name = $app->param( 'name' );
        $model = $app->param( '_model' );
        $table = $app->get_table( $model );
        if (!$table ) return $app->error( 'Invalid request.' );
        $permission = $app->param( 'permission' ) ? $app->param( 'permission' ) : $model;
        $can_do = $app->can_do( $permission, 'create' );
        if (! $can_do ) {
            if ( $object_id = $app->param( 'id' ) ) {
                $obj = $app->db->model( $model )->load( (int) $object_id );
                if ( $obj ) {
                     $can_do = $app->can_do( $model, 'edit', $obj );
                }
            }
        }
        if (! $can_do ) {
            $app->json_error( 'Permission denied.' );
        }
        $column = $app->db->model( 'column' )->load(
            ['table_id' => $table->id, 'name' => $name ] );
        $resized;
        if ( is_array( $column ) && !empty( $column ) ) {
            $extra = $column[0]->extra;
            $res = PTUtil::upload_check( $extra );
            if ( $res == 'resized' ) {
                $resized = true;
            }
        } else {
            $app->json_error( 'Unknown column \'%s\'.', $name );
        }
        if ( $attachmentfile = $app->param( 'attachmentfile' ) ) {
            $magic = "{$screen_id}-{$attachmentfile}";
        } else {
            $magic = "{$screen_id}-{$name}";
        }
        $user = $app->user();
        $options = ['upload_dir' => $upload_dir . DS,
                    'magic' => $magic, 'user_id' => $user->id ,
                    'prototype' => $app, 'resized' => $resized ];
        // auto_orient
        if (! $app->auto_orient ) {
            $image_versions = [
                    '' => ['auto_orient' => false ],
                    'thumbnail' => [
                        'max_width' => 256,
                        'max_height' => 256
                    ]];
            $options['image_versions'] = $image_versions;
        }
        $sess = $app->db->model( 'session' )
            ->get_by_key( ['name' => $magic,
                           'user_id' => $user->id, 'kind' => 'UP'] );
        $sess->start( time() );
        $sess->expires( time() + $app->sess_timeout );
        $sess->save();
        $upload_handler = new UploadHandler( $options );
    }

    function upload_multi ( $app ) {
        if ( empty( $_FILES ) ) {
            $app->json_error( 'Please check the file size and data.' );
        }
        $app->validate_magic( true );
        $name = $app->param( 'name' );
        $magic = '';
        if (! $app->param( 'file_attachment' ) ) {
            if (!$app->can_do( 'asset', 'create' ) ) {
                $app->json_error( 'Permission denied.' );
            }
        } else {
            $permission = $app->param( 'permission' )
                        ? $app->param( 'permission' ) : $model;
            $can_do = $app->can_do( $permission, 'create' );
            if (! $can_do ) {
                if ( $object_id = $app->param( 'id' ) ) {
                    $obj = $app->db->model( $model )->load( (int) $object_id );
                    if ( $obj ) {
                         $can_do = $app->can_do( $model, 'edit', $obj );
                    }
                }
            }
            if (! $can_do ) {
                $app->json_error( 'Permission denied.' );
            }
            $screen_id = $app->param( '_screen_id' );
            $magic = "{$screen_id}-{$name}";
        }
        $model = $app->param( 'model' );
        $table = $app->get_table( $model );
        $column = $app->db->model( 'column' )->load(
            ['table_id' => $table->id, 'name' => $name ] );
        $resized;
        if ( is_array( $column ) && !empty( $column ) ) {
            $extra = $column[0]->extra;
            $res = PTUtil::upload_check( $extra );
            if ( $res == 'resized' ) {
                $resized = true;
            }
        }
        $upload_dir = $app->upload_dir();
        $user = $app->user();
        $options = ['upload_dir' => $upload_dir . DS, 'prototype' => $app,
                    'user_id' => $user->id, 'magic' => $magic, 'resized' => $resized ];
        if (! $app->auto_orient ) {
            $image_versions = [
                    '' => ['auto_orient' => false ],
                    'thumbnail' => [
                        'max_width' => 256,
                        'max_height' => 256
                    ]];
            $options['image_versions'] = $image_versions;
        }
        $upload_handler = new UploadHandler( $options );
    }

    function upload_dir ( $remove = true ) {
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
        mkdir( $upload_dir . DS, 0777, true );
        if ( $remove ) $this->upload_dirs[ $upload_dir ] = true;
        return $upload_dir;
    }

    function get_columns_json ( $app ) {
        $app->validate_magic( true );
        header( 'Content-type: application/json' );
        if (!$app->can_do( 'table', 'edit' ) ) {
            $app->json_error( 'Permission denied.' );
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
        $scheme['column_labels'] = $scheme['locale']['default'];
        if ( $table->text_format ) {
            $scheme['text_format'] = $table->text_format;
        }
        $obj = $app->db->model( $model );
        $hide_options = isset( $scheme['hide_edit_options'] ) 
                      ? $scheme['hide_edit_options'] : [];
        if ( $obj->has_column( 'status' )
            && ! in_array( 'status', $hide_options ) ) {
            $hide_options[] = 'status';
        }
        if ( $obj->has_column( 'user_id' )
            && ! in_array( 'user_id', $hide_options ) ) {
            $hide_options[] = 'user_id';
        }
        if ( $obj->has_column( 'published_on' )
            && ! in_array( 'published_on', $hide_options ) ) {
            $hide_options[] = 'published_on';
        }
        if ( $obj->has_column( 'unpublished_on' )
            && ! in_array( 'unpublished_on', $hide_options ) ) {
            $hide_options[] = 'unpublished_on';
        }
        $scheme['hide_edit_options'] = $hide_options;
        unset( $scheme['locale']['default'] );
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
        $description = $app->param( 'description' );
        $extra_path = $app->param( 'extra_path' );
        $asset_publish = $app->param( 'asset_publish' );
        $show_path_entry = $app->param( 'show_path_entry' );
        $show_path_page = $app->param( 'show_path_page' );
        $copyright = $app->param( 'copyright' );
        $system_email = $app->param( 'system_email' );
        $administrator_ip = $app->param( 'administrator_ip' );
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
        $allowed_ip_only = $app->param( 'allowed_ip_only' );
        $no_lockout_allowed_ip = $app->param( 'no_lockout_allowed_ip' );
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
                 'description'=> $description,
                 'site_path'  => $site_path,
                 'site_url'   => $site_url,
                 'extra_path' => $extra_path,
                 'show_path_entry' => $show_path_entry,
                 'show_path_page' => $show_path_page,
                 'language'   => $language,
                 'barcolor'   => $barcolor,
                 'bartextcolor' => $bartextcolor,
                 'asset_publish' => $asset_publish,
                 'system_email' => $system_email,
                 'administrator_ip' => $administrator_ip,
                 'preview_url' => $preview_url,
                 'lockout_limit' => $lockout_limit,
                 'lockout_interval' => $lockout_interval,
                 'ip_lockout_limit' => $ip_lockout_limit,
                 'ip_lockout_interval' => $ip_lockout_interval,
                 'two_factor_auth' => $two_factor_auth,
                 'default_widget' => $default_widget,
                 'no_lockout_allowed_ip' => $no_lockout_allowed_ip,
                 'allowed_ip_only' => $allowed_ip_only,
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
            $workspace = $app->db->model( 'workspace' )->new();
            $workspace->id( 0 ); // dummy
            $workspace->site_url( $site_url );
            $workspace->site_path( $site_path );
            $app->rebuild_urlinfo( $workspace );
        }
        $app->redirect( $app->admin_url . '?__mode=config&saved=1'
                         . $app->workspace_param );
    }

    function flush_session ( $app ) {
        $session_id = $app->param( 'id' );
        if (! $session_id ) return;
        $session = $app->db->model( 'session' )->load( (int) $session_id );
        if ( $session->id ) {
            if ( $session->user_id == $app->user()->id && $session->kind != 'US' ) {
                $session->remove();
            }
        }
    }

    function debug ( $app ) {
    }

    function update_dashboard ( $app ) {
        $app->validate_magic( true );
        $type = $app->param( '_type' );
        $workspace_id = (int) $app->param( 'workspace_id' );
        if ( $workspace_id ) {
            $ws = $app->db->model( 'workspace' )->load( $workspace_id );
            if (! $ws ) {
                $app->json_error( '%s not found.', $app->translate( 'WorkSpace' ), 404 );
            }
        }
        $option = $app->db->model( 'option' )->get_by_key(
            ['workspace_id' => $workspace_id,
             'user_id' => $app->user()->id,
             'key' => 'dashboard_widget']
        );
        $detatch = $option->data ? explode( ',', $option->data ) : [];
        $name = $app->param( 'name' );
        $name = preg_replace( '/^widget\-/', '', $name );
        if ( $type == 'detatch' ) {
            if (! in_array( $name, $detatch ) ) {
                $detatch[] = $name;
            }
            $option->data( implode( ',', $detatch ) );
            $option->save();
            $return_url = $this->admin_url . '?__mode=dashboard&detatch_widget=1';
            if ( $workspace_id ) {
                $return_url .= '&workspace_id=' . $workspace_id;
            }
            header( 'Content-type: application/json' );
            echo json_encode( ['status' => 200,
                               'return_url'=> $return_url ] );
            exit();
        } else if ( $type == 'add' ) {
            if ( in_array( $name, $detatch ) ) {
                $idx = array_search( $name, $detatch );
                unset( $detatch[ $idx ] );
                $option->data( implode( ',', $detatch ) );
                $option->save();
            }
            $return_url = $this->admin_url . '?__mode=dashboard&add_widget=1';
            if ( $workspace_id ) {
                $return_url .= '&workspace_id=' . $workspace_id;
            }
            $app->redirect( $return_url );
        } else {
            $widgets = explode( ',', $app->param( 'widgets' ) );
            $sorted = [];
            foreach ( $widgets as $widget ) {
                $widget = preg_replace( '/^widget\-/', '', $widget );
                $sorted[] = $widget;
            }
            $option->value( implode( ',', $sorted ) );
            $option->save();
            header( 'Content-type: application/json' );
            echo json_encode( ['status' => 200] );
            exit();
        }
    }

    function change_activity ( $app ) {
        $app->validate_magic( true );
        $model = $app->param( '_model' );
        $table = $app->get_table( $model );
        if (! $table ) {
            return $app->error( 'Invalid request.' );
        }
        $obj = $app->db->model( $model );
        $column = $obj->has_column( 'modified_on' ) ? 'modified_on' : 'created_on';
        $workspace_id = (int) $app->param( 'workspace_id' );
        if ( $workspace_id ) {
            $ws = $app->db->model( 'workspace' )->load( $workspace_id );
            if (! $ws ) {
                return $app->error( '%s not found.', $app->translate( 'WorkSpace' ) );
            }
        }
        $option = $app->db->model( 'option' )->get_by_key(
            ['workspace_id' => $workspace_id,
             'user_id' => $app->user()->id,
             'key' => 'activity_model']
        );
        $option->value( $model );
        $option->data( $table->plural );
        $option->option( $column );
        $option->save();
        $app->return_to_dashboard( ['change_activity' => 1 ] );
    }

    function core_widgets () {
        $core_widgets = ['activity' => [
                            'label' => 'Activity',
                            'component' => 'Core',
                            'order' => 10,
                            'scope' => ['system', 'workspace']],
                         'workflow' => [
                            'label' => 'Workflow',
                            'component' => 'Core',
                            'order' => 40,
                            'scope' => ['system', 'workspace']],
                         'newsbox' => [
                            'label' => 'Events and News',
                            'component' => 'Core',
                            'order' => 70,
                            'scope' => ['system']],
                         'models' => [
                            'label' => 'Models',
                            'component' => 'Core',
                            'order' => 50,
                            'scope' => ['workspace']],
                         'workspaces' => [
                            'label' => 'WorkSpaces',
                            'component' => 'Core',
                            'order' => 30,
                            'scope' => ['system']]];
        return $core_widgets;
    }

    function core_save_callbacks () {
        $callbacks = ['save_filter_table', 'save_filter_urlmapping', 'save_filter_form',
                      'save_filter_workspace', 'post_save_workspace', 'post_save_urlmapping',
                      'pre_save_role', 'post_save_role', 'pre_save_question',
                      'post_save_permission', 'post_save_table', 'post_save_field',
                      'pre_save_widget', 'save_filter_tag', 'pre_save_user'];
        foreach ( $callbacks as $meth ) {
            $cb = explode( '_', $meth );
            $this->register_callback( $cb[2], $cb[0] . '_' . $cb[1], $meth, 1, $this );
        }
    }

    function clone_object ( $app ) {
        $model = $app->param( '_model' );
        $id = $app->param( 'id' );
        $table = $app->get_table( $model );
        if (! $model ||! $id ||! $table ) {
            return $app->error( 'Invalid request.' );
        }
        $workspace = $app->workspace();
        $can_duplicate = false;
        if ( $table->can_duplicate ) {
            $can_duplicate = $app->can_do( $model, 'duplicate', null, $workspace );
        }
        if (! $can_duplicate ) {
            $app->error( 'Permission denied.' );
        }
        $obj = $app->db->model( $model )->load((int)$id );
        if (! $obj ) {
            return $app->error( 'Cannot load %s (ID:%s)', [ 
                $app->translate( $table->label ), $id ] );
        }
        $clone_obj = PTUtil::clone_object( $app, $obj, false );
        $app->redirect( $app->admin_url . '?__mode=view&_type=edit&_model=' . $model .
            '&id=' . $clone_obj->id . $app->workspace_param . '&cloned=1' );
    }

    private function save ( $app ) {
        $db = $app->db;
        $callbacks = $app->callbacks;
        $db->begin_work();
        $app->txn_active = true;
        $app->get_scheme_from_db( 'urlinfo' );
        $model = $app->param( '_model' );
        $app->validate_magic();
        if (!$model ) return $app->error( 'Invalid request.' );
        $table = $app->get_table( $model );
        if (!$table ) return $app->error( 'Invalid request.' );
        $app->stash( 'table', $table );
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
                return $app->error( 'Cannot load %s (ID:%s)', [ 
                    $app->translate( $table->label ), $id ] );
        } else {
            if (! $app->can_do( $model, 'create' ) ) {
                $app->error( 'Permission denied.' );
            }
        }
        if (!$app->can_do( $model, $action, $obj ) ) {
            $app->error( 'Permission denied.' );
        }
        $app->core_save_callbacks();
        $workspace_id = $app->workspace() ? $app->workspace()->id : 0;
        if ( $obj->id && $obj->workspace_id ) {
            $workspace_id = $obj->workspace_id;
        }
        $workflow = $db->model( 'workflow' )->get_by_key(
            ['model' => $model,
             'workspace_id' => $workspace_id ] );
        if ( $workflow->id ) {
            $app->register_callback( $model, 'post_save', 'workflow_post_save', 1, $app );
            $app->stash( 'workflow', $workflow );
        }
        $app->init_callbacks( $model, 'save' );
        $db->caching = false;
        $orig_relations = $app->get_relations( $obj );
        $orig_metadata  = $app->get_meta( $obj );
        $original = clone $obj;
        $original->_relations = $orig_relations;
        $original->_meta = $orig_metadata;
        $original->id( null );
        $is_changed = false;
        $changed_cols = [];
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
        $add_attchments = [];
        $remove_attachments = [];
        $replaced_attachments = [];
        $can_revision = $app->can_do( $model, 'revision', null, $app->workspace() );
        if ( $app->param( '_save_as_revision' ) ) {
            if (! $can_revision ) {
                $app->error( 'Permission denied.' );
            }
            $as_revision = true;
            $orig_id = $obj->id;
            $obj = PTUtil::clone_object( $app, $obj );
            $obj->rev_object_id( $orig_id );
            $obj->rev_type( 2 );
            if ( $table->has_status ) {
                $obj->status = $table->start_end ? 0 : 1;
            }
        }
        if ( $model == 'user' ) {
            $app->user = clone $app->user();
        }
        $attachment_cols = PTUtil::attachment_cols( $model, $scheme );
        $rel_attach_cols = PTUtil::attachment_cols( $model, $scheme, 'relation' );
        $has_attachment = false;
        $has_assets = false;
        $object_label = $app->param( $table->primary );
        $require_blobs = [];
        foreach( $columns as $col => $props ) {
            if ( $col === $primary ) continue;
            if ( $obj->id && in_array( $col, $autoset ) ) continue;
            if ( $as_revision && in_array( $col, $attachment_cols ) ) continue;
            $value = $app->param( $col );
            $type = $props['type'];
            if ( isset( $properties[ $col ] ) ) {
                if ( $type === 'text' || $type === 'string' && is_string( $value ) ) {
                    $text .= ' ' . $value;
                }
                if ( $col === 'order' && $table->sortable && ! $value ) {
                    $value = $app->get_order( $obj );
                }
                if ( isset( $relations[ $col ] ) ) {
                    if (!$value ) $value = [];
                    if (! $app->param( '_preview' ) ) {
                        if ( in_array( $col, $rel_attach_cols ) ) {
                            $new_vars = [];
                            $remove_sesses = [];
                            foreach ( $value as $val ) {
                                if (! $val ) continue;
                                if ( strpos( $val, 'session' ) === false ) {
                                    $attachment_id = (int) $val;
                                    $new_vars[] = $attachment_id;
                                    if ( $as_revision ) {
                                        $old_file = $db->model( 'attachmentfile' )->load( $attachment_id );
                                        if ( $old_file ) {
                                            $replaced_attachments[ $attachment_id ] = $old_file;
                                        }
                                    }
                                    $has_attachment = true;
                                } else {
                                    $sess_id = str_replace( 'session-', '', $val );
                                    $filename = $app->param( "_{$col}_label_" . $val );
                                    $sess = $db->model( 'session' )->load( (int) $sess_id );
                                    if (! $sess ) continue;
                                    $attachment = $app->db->model('attachmentfile')->new();
                                    if ( $filename ) {
                                        $attachment->name( $filename );
                                    } else {
                                        $attachment->name( $sess->value );
                                    }
                                    $attachment->mime_type( $sess->key );
                                    $attachment->workspace_id( $obj->workspace_id );
                                    $attachment->file( $sess->data );
                                    $json = json_decode( $sess->text );
                                    $attachment->size( $json->file_size );
                                    $attachment->class( $json->class );
                                    if ( $obj->has_column( 'status' ) ) {
                                        $obj_status = $obj->status;
                                        $publish_status = $app->status_published( $model );
                                        $file_status = $obj_status == $publish_status ? 4 : $obj_status;
                                        $attachment->status( $obj->status );
                                    }
                                    $app->set_default( $attachment );
                                    $attachment->save();
                                    $changed_cols[ $col ] = true;
                                    $has_attachment = true;
                                    $to_ids[] = $attachment->id;
                                    $metadata = $app->db->model( 'meta' )->get_by_key(
                                       ['model' => 'attachmentfile', 'object_id' => $attachment->id,
                                                      'kind' => 'metadata', 'key' => 'file' ] );
                                    $metadata->text( $sess->text );
                                    $metadata->metadata( $sess->extradata );
                                    $metadata->data( $sess->metadata );
                                    $metadata->save();
                                    $remove_sesses[] = $sess;
                                    $new_vars[] = (int) $attachment->id;
                                    $app->publish_obj( $attachment, null, false, true );
                                }
                            }
                            if (! empty( $remove_sesses ) ) {
                                $db->model( 'session' )->remove_multi( $remove_sesses );
                            }
                            $value = $new_vars;
                        }
                    }
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
                } else if ( $prop === 'datetime' || $prop === 'date' ) {
                    $date = $app->param( $col . '_date' );
                    $time = $app->param( $col . '_time' );
                    if ( $prop === 'date' ) $time = '000000';
                    $ts = $obj->db2ts( $date . $time );
                    $value = $obj->ts2db( $ts );
                } else if ( $prop === 'number' ) {
                    $value = intval( $value );
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
                    if (! $value ) {
                        $value = $obj->$col;
                    } else {
                        if ( strpos( $opt, 'hash' ) !== false ) {
                            $value = password_hash( $value, PASSWORD_BCRYPT );
                        }
                    }
                }
                if ( in_array( $col, $unchangeable ) ) {
                    if ( $obj->id && $obj->$col != $value ) {
                        if ( $col != 'uuid' && $obj->_model != 'user' ) {
                            $value = $obj->$col;
                            continue;
                        }
                    }
                }
                if ( in_array( $col, $unique ) && $value ) {
                    $terms = [ $col => $value ];
                    if ( $obj->id ) {
                        $terms['id'] = ['!=' => $obj->id ];
                    }
                    if ( $table->revisable ) {
                        $terms['rev_type'] = 0;
                    }
                    if ( $obj->has_column( 'workspace_id' ) ) {
                        $workspace_id = $app->workspace() ? $app->workspace()->id : 0;
                        $terms['workspace_id'] = $workspace_id;
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
                        $text = $object_label ? strip_tags( $object_label )
                              : strip_tags( $text );
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
                            if ( $app->check_int_null == false || 
                                $col == 'rev_type' || $col == 'workspace_id' ) {
                                // or allow 0 cols.
                                $value = intval( $value );
                            } else if ( $value === '' ) {
                                $errors[] = $app->translate( '%s is required.',
                                            $app->translate( $labels[ $col ] ) );
                            }
                        } else {
                            $value = '';
                            if ( $type != 'blob' ) {
                                $errors[] = $app->translate( '%s is required.',
                                            $app->translate( $labels[ $col ] ) );
                            } else {
                                $require_blobs[] = $col;
                            }
                        }
                    }
                }
                if ( $col == 'parent_id' && $table->hierarchy ) {
                    if (! $app->can_do( $model, 'hierarchy', null, $app->workspace() ) ) {
                        $value = $obj->parent_id;
                    } else {
                        if ( $original->parent_id != $value ) {
                            $obj->$col = $value;
                            $parent_id_err = '';
                            if ( $obj->id && ! $app->check_parent_id( $obj, $original, $parent_id_err ) ) {
                                $errors[] = $parent_id_err;
                                $obj->$col = $original->parent_id;
                            }
                        }
                    }
                }
            }
            if (! isset( $relations[ $col ] ) ) {
                $value = preg_replace( "/\r\n|\r/","\n", $value );
                if ( $col === 'model' || $col === 'count' ) {
                    // Collision $obj->model( $model )->...
                    $obj->$col = $value;
                } else {
                    if ( $type == 'blob' ) {
                        if (! $obj->$col &&
                            isset( $props['not_null'] ) && $props['not_null'] ) {
                            $obj->$col('');
                        }
                    } else {
                        $obj->$col( $value );
                    }
                }
            }
        }
        if (!$app->can_do( $model, $action, $obj ) ) {
            $app->error( 'Permission denied.' );
        }
        $callback = ['name' => 'save_filter', 'error' => '', 'errors' => $errors ];
        if ( $app->param( '_preview' ) ) {
            $save_filter = true;
        } else {
            $save_filter = $app->run_callbacks( $callback, $model, $obj );
        }
        $errors = $callback['errors'];
        if ( $msg = $callback['error'] ) {
            $errors[] = $msg;
        }
        $is_new = $obj->id ? false : true;
        $required_fields = PTUtil::get_fields( $obj, 'requireds' );
        $required_basenames = array_keys( $required_fields );
        foreach ( $required_basenames as $fld ) {
            $fld_value = $app->param( "{$fld}__c" );
            if ( $fld_value !== null ) {
                if ( is_array( $fld_value ) ) {
                    $var_exists = false;
                    foreach ( $fld_value as $fld_val ) {
                        $fld_values = json_decode( $fld_val, true );
                        $fld_values = array_shift( $fld_values );
                        if ( is_array( $fld_values ) ) {
                            foreach ( $fld_values as $v ) {
                                if ( $v ) {
                                    $var_exists = true;
                                    break 2;
                                }
                            }
                        } else if ( $fld_values ) {
                            $var_exists = $fld_values;
                            break;
                        }
                    }
                    if (! $var_exists ) {
                        $errors[] =
                            $app->translate( '%s is required.', $required_fields[ $fld ] );
                    }
                } else {
                    $fld_values = json_decode( $fld_value, true );
                    if ( empty( $fld_values ) ) {
                        $errors[] =
                            $app->translate( '%s is required.', $required_fields[ $fld ] );
                    } else {
                        $fld_values = array_shift( $fld_values );
                        $var_exists = false;
                        if ( is_array( $fld_values ) ) {
                            foreach ( $fld_values as $v ) {
                                if ( $v ) {
                                    $var_exists = true;
                                    break;
                                }
                            }
                        } else {
                            $var_exists = $fld_values;
                        }
                        if (! $var_exists ) {
                            $errors[] =
                                $app->translate( '%s is required.', $required_fields[ $fld ] );
                        }
                    }
                }
            }
        }
        if (!empty( $errors ) || !$save_filter ) {
            $error = join( "\n", $errors );
            if ( $app->param( '_preview' ) ) return $app->error( $error );
            $db->rollback();
            $app->txn_active = false;
            return $app->forward( $model, $error );
        }
        if ( $model === 'workspace' ) {
            $obj->last_update( time() );
        }
        $app->set_default( $obj );
        if ( $app->param( '_preview' ) ) {
            return $app->preview( $obj, $scheme );
        }
        $callback = ['name' => 'pre_save', 'error' => '', 'is_new' => $is_new,
                     'errors' => $errors ]; // 'changed_cols' => $changed_cols
        $pre_save = $app->run_callbacks( $callback, $model, $obj, $original );
        $errors = $callback['errors'];
        if ( $msg = $callback['error'] ) {
            $errors[] = $msg;
        }
        if (!empty( $errors ) || !$pre_save ) {
            $error = join( "\n", $errors );
            if ( $app->param( '_preview' ) ) return $app->error( $error );
            $db->rollback();
            $app->txn_active = false;
            return $app->forward( $model, $error );
        }
        $errstr = $app->translate( 'An error occurred while saving %s.',
                    $app->translate( $table->label ) );
        if (! $obj->save() ) return $app->rollback( $errstr );
        $target_id = $obj->id;
        $_revision_id = null;
        $file_metadata = [];
        if ( $app->param( '_apply_to_master' ) ) {
            if (! $can_revision ) {
                return $app->rollback( 'Permission denied.' );
            }
            $_revision_id = $app->param( '_revision_id' );
        }
        if ( $_revision_id ) {
            $rev_obj = $db->model( $model )->load( (int) $_revision_id );
            if (! $rev_obj ) {
                return $app->error(
                    'Because the master %s has been deleted,'
                    . ' the %s can not be apply.',
                    $app->translate( $table->label ) );
            }
            if ( $rev_obj ) {
                foreach ( $properties as $key => $val ) {
                    if ( $val === 'file' ) {
                        $file_remove = $app->param( "{$key}-remove" );
                        $magic = $app->param( "{$key}-magic" );
                        if (! $file_remove && ! $magic ) {
                            $comp_from = base64_encode( $rev_obj->$key );
                            $comp_to = base64_encode( $obj->$key );
                            if ( $comp_from != $comp_to ) {
                                $obj->$key( $rev_obj->$key );
                                $file_meta = $db->model( 'meta' )->load(
                                    ['model' => $model, 'object_id' => $rev_obj->id,
                                     'key' => $key ] );
                                if (! empty( $file_meta ) ) {
                                    $file_metadata[ $key ] = $file_meta;
                                }
                            }
                        }
                    }
                }
                $err = '';
                $app->remove_object( $rev_obj, $table, $err, false );
            }
        }
        if ( $table->revisable ) {
            if (! $as_revision && $obj->rev_type && $app->param( 'rev_type' ) && 
                $obj->rev_type != $app->param( 'rev_type' ) ) {
                $rev_type = (int) $app->param( 'rev_type' );
                if ( $rev_type == 1 || $rev_type == 2 ) {
                    $obj->rev_type( $rev_type );
                }
            }
        }
        if ( $obj->has_column( 'user_id' ) && $obj->has_column( 'previous_owner' ) ) {
            if ( $obj->previous_owner != $obj->user_id ) {
                $previous_owner = $original->user_id;
                if ( $previous_owner != $app->user->id ) {
                    // or $previous_owner
                    $previous_owner = $app->user->id;
                }
                if ( $original->user_id != $app->user->id ) {
                    $obj->previous_owner( $original->user_id );
                } else {
                    $obj->previous_owner( $original->previous_owner );
                }
            }
            if (! $original->previous_owner && $app->user->id != $obj->user_id ) {
                $original->previous_owner( $app->user->id );
            }
        }
        if ( $app->param( '_apply_to_master' ) && $table->has_status ) {
            $obj->status( $original->status );
        }
        if (! $obj->save() ) return $app->rollback( $errstr );
        if (! empty( $file_metadata ) ) {
            foreach ( $file_metadata as $key => $file_meta ) {
                $old_meta = $db->model( 'meta' )->load(
                                    ['model' => $model, 'object_id' => $obj->id,
                                     'key' => $key ] );
                foreach ( $old_meta as $meta ) {
                    $urls = $app->db->model( 'urlinfo' )->load(
                                            ['meta_id' => $meta->id ] );
                    foreach ( $urls as $url ) {
                        $url->remove();
                    }
                    $meta->remove();
                }
                foreach ( $file_meta as $meta ) {
                    $meta = clone $meta;
                    $meta->id( null );
                    $meta->object_id( $target_id );
                    $meta->save();
                    if ( $meta->kind == 'thumbnail' ) {
                        // re-make thumbnail.
                        $args = $meta->data;
                        $metaProperties = $meta->metadata;
                        $args = $args ? unserialize( $args ) : null;
                        $metaProperties = $metaProperties
                                        ? unserialize( $metaProperties ) : null;
                        if (! empty( $args ) ) {
                            $url = PTUtil::thumbnail_url( $obj, $args, $metaProperties );
                        }
                    }
                }
            }
        }
        if ( $has_attachment ) {
            $attachments = $app->get_related_objs(
                $obj, 'attachmentfile' );
            $update_files = [];
            foreach ( $attachments as $attachment ) {
                $relation_key = $attachment->relation_name;
                $label = $app->param( "_{$relation_key}_label_" . $attachment->id );
                if ( $label && $attachment->name != $label ) {
                    $attachment->name( $label );
                    $update_files[] = $attachment;
                }
            }
            if (! empty( $update_files ) ) {
                if (! $app->db->model( 'attachmentfile' )->update_multi( $update_files ) ) {
                    return $app->rollback( 'An error occurred while updating the related object(s).' );
                }
            }
        }
        if (! empty( $add_tags ) ) {
            $workspace_id = (int) $app->param( 'workspace_id' );
            $props = $placements['tags']['tag'];
            foreach ( $add_tags as $tag ) {
                if ( function_exists( 'normalizer_normalize' ) ) {
                    $tag = normalizer_normalize( $tag, Normalizer::NFKC );
                }
                $normalize = str_replace( ' ', '', trim( mb_strtolower( $tag ) ) );
                if (!$normalize ) continue;
                $terms = ['normalize' => $normalize, 'class' => $table->name ];
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
                $_primary_id = $app->param( "{$name}_primary_id" );
                if ( $_primary_id && !empty( $to_ids ) ) {
                    $sorted_ids = [ $_primary_id ];
                    foreach ( $to_ids as $to_id ) {
                        if ( $to_id && $to_id != $_primary_id ) {
                            $sorted_ids[] = $to_id;
                        }
                    }
                    $to_ids = $sorted_ids;
                }
                if ( $to_obj === '__any__' ) {
                    $to_obj = $app->param( "_{$name}_model" );
                } else if ( $to_obj == 'asset' ) {
                    $has_assets = true;
                }
                $args = ['from_id' => $target_id, 
                         'name' => $name,
                         'from_obj' => $model,
                         'to_obj' => $to_obj ];
                $app->set_relations( $args, $to_ids, false, $errors, $remove_attachments );
                if (!empty( $errors ) ) {
                    return $app->rollback( join( ',', $errors ) );
                }
            }
            if ( $as_revision && !empty( $replaced_attachments ) ) {
                $attachment_rels = $app->get_relations( $obj, 'attachmentfile' );
                $update_rels = [];
                foreach ( $attachment_rels as $attachment_rel ) {
                    $at_to_id = (int) $attachment_rel->to_id;
                    if ( isset( $replaced_attachments[ $at_to_id ] ) ) {
                        $old_file = $replaced_attachments[ $at_to_id ];
                        $new_file = PTUtil::clone_object( $app, $old_file );
                        $file_status = $obj->has_column( 'status' ) ? $obj->status : 0;
                        $new_file->status( $file_status );
                        $new_file->save();
                        $app->publish_obj( $new_file, null, false, true );
                        $attachment_rel->to_id( $new_file->id );
                        $update_rels[] = $attachment_rel;
                    }
                }
                if (! empty( $update_rels ) ) {
                    if (! $db->model( 'relation' )->update_multi( $update_rels ) ) {
                        return $app->rollback( 'An error occurred while updating the related object(s).' );
                    }
                }
            }
        }
        if ( $has_assets ) {
            $assets = $app->get_related_objs( $obj, 'asset' );
            $update_objs = [];
            foreach ( $assets as $asset ) {
                $relation_key = $asset->relation_name;
                $label = $app->param( "_{$relation_key}_label_" . $asset->id );
                if ( $app->can_do( 'asset', 'edit', $asset, $app->workspace() ) ) {
                    if ( $label && $asset->label != $label ) {
                        $asset->label( $label );
                        $update_objs[] = $asset;
                        PTUtil::update_blob_label( $app, $asset, 'file', $label );
                    }
                }
            }
            if (! empty( $update_objs ) ) {
                if (! $app->db->model( 'asset' )->update_multi( $update_objs ) ) {
                    return $app->rollback( 'An error occurred while updating the related object(s).' );
                }
            }
        }
        $has_file = false;
        foreach ( $properties as $key => $val ) {
            if ( isset( $relations[ $key ] ) ) {
                continue;
            }
            if ( $val == 'relation:asset:label:dialog' &&
                $obj->$key && $app->param( "_{$key}_label" ) ) {
                $asset = $db->model( 'asset' )->load( (int) $obj->$key );
                $label = $app->param( "_{$key}_label" );
                if ( $app->can_do( 'asset', 'edit', $asset, $app->workspace() ) ) {
                    if ( $label && $asset->label != $label ) {
                        $asset->label( $label );
                        PTUtil::update_blob_label( $app, $asset, 'file', $label );
                        $asset->save();
                    }
                }
            }
            if ( in_array( $key, $attachment_cols ) ) {
                $magic = $app->param( "{$key}-magic" );
                $file_remove = $app->param( "{$key}-remove" );
                $file_label = $app->param( "{$key}-label" );
                $attachment = $obj->$key
                    ? $db->model( 'attachmentfile' )->load( (int) $obj->$key )
                    : $db->model( 'attachmentfile' )->new();
                if (! $attachment ) $attachment = $db->model( 'attachmentfile' )->new();
                $old_label = PTUtil::get_meta_property( $attachment, 'file', 'label' );
                if ( $old_label != $file_label ) {
                    PTUtil::set_meta_property( $attachment, 'file', 'label', $file_label );
                }
                if ( $file_remove && $attachment->id ) {
                    $remove_attachments[] = $attachment;
                    // $app->remove_object( $attachment );
                    $obj->$key( null );
                    $changed_cols[ $key ] = true;
                    $has_file = true;
                    $is_changed = true;
                }
                if ( $attachment->id && $table->revisable ) {
                    $is_revision = $table->revisable && $obj->rev_type ? true : false;
                    if (! $app->param( '_apply_to_master' ) && ! $is_revision ) {
                        $rev_attachment =
                            PTUtil::clone_object( $app, $attachment );
                        $add_attchments[] = $rev_attachment;
                        $original->$key( $rev_attachment->id );
                    }
                }
                if ( $magic ) {
                    $sess = $db->model( 'session' )
                        ->get_by_key( ['name' => $magic,
                                       'user_id' => $app->user()->id, 'kind' => 'UP'] );
                    if ( $sess->id ) {
                        if (!$file_remove ) {
                            $tmp_path = $app->upload_dir();
                            $upload_name = basename( $sess->value );
                            $tmp_path .= DS . $upload_name;
                            file_put_contents( $tmp_path, $sess->data );
                            if ( $as_revision ) {
                                if ( $attachment->id )
                                    $remove_attachments[] = $attachment;
                                    // $attachment->remove();
                                $attachment = $db->model( 'attachmentfile' )->new();
                            }
                            $attachment->name( $upload_name );
                            $json = json_decode( $sess->text );
                            $attachment->mime_type( $json->mime_type );
                            if ( $obj->has_column( 'workspace_id' ) ) {
                                $attachment->workspace_id( $obj->workspace_id );
                            }
                            $attachment->size( $json->file_size );
                            $attachment->class( $json->class );
                            if ( $obj->has_column( 'status' ) ) {
                                $obj_status = $obj->status;
                                $publish_status = $app->status_published( $model );
                                $file_status = $obj_status == $publish_status ? 4 : $obj_status;
                                $attachment->status( $obj->status );
                            }
                            $app->set_default( $attachment );
                            $attachment->save();
                            $obj->$key( $attachment->id );
                            $app->file_attach_to_obj( $attachment, 'file', $tmp_path, $file_label );
                            $app->publish_obj( $attachment, null, false, true );
                            $changed_cols[ $key ] = true;
                            $has_file = true;
                            $is_changed = true;
                        }
                        $sess->remove();
                    }
                }
            }
            if ( $val === 'file' ) {
                $file_remove = $app->param( "{$key}-remove" );
                $metadata = $db->model( 'meta' )->get_by_key(
                         ['model' => $model, 'object_id' => $obj->id,
                          'kind' => 'metadata', 'key' => $key ] );
                $magic = $app->param( "{$key}-magic" );
                if ( $file_remove ) {
                    $obj->$key( null );
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
                $meta_save = false;
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
                            $meta_save = true;
                            if ( $obj->id ) $is_changed = true;
                            $changed_cols[ $key ] = true;
                        }
                        $sess->remove();
                    }
                }
                $file_label = $app->param( "{$key}-label" );
                if ( $metadata->text ) {
                    $meta_json = json_decode( $metadata->text, true );
                    if ( is_array( $meta_json ) ) {
                        $old_label = '';
                        if ( isset( $meta_json['label'] ) ) $old_label = $meta_json['label'];
                        if ( $old_label != $file_label ) {
                            $meta_json['label'] = $file_label;
                            $metadata->text( json_encode( $meta_json, JSON_UNESCAPED_UNICODE ) );
                            $meta_save = true;
                        }
                    }
                }
                if ( $meta_save ) {
                    if (! $metadata->save() ) return $app->rollback( $errstr );
                }
                if (! $obj->$key && !empty( $require_blobs ) && in_array( $key, $require_blobs ) ) {
                    $errors[] = $app->translate( '%s is required.',
                                $app->translate( $labels[ $key ] ) );
                }
            }
        }
        if (! empty( $errors ) ) {
            $error = join( "\n", $errors );
            $db->rollback();
            $app->txn_active = false;
            return $app->forward( $model, $error );
        }
        if ( $has_file ) {
            if (! $obj->save() ) return $app->rollback( $errstr );
        }
        $id = $obj->id;
        $object_fields = PTUtil::get_fields( $obj, 'types' );
        $custom_fields = $app->get_meta( $obj, 'customfield' );
        $field_ids = [];
        foreach ( $object_fields as $fld => $props ) {
            $fieldtype = $props['type'];
            $field_id = $props['id'];
            $field_basename = "{$fld}__c";
            if (! isset( $_REQUEST[ $field_basename ] ) ) {
                continue;
            }
            $fld_value = $app->param( $field_basename );
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
                    $meta_vars = $meta->get_values();
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
        }
        if (!$is_new && $table->revisable && (!$obj->rev_object_id && !$as_revision ) ) {
            if (! PTUtil::pack_revision( $obj, $original, $changed_cols ) ) {
                if (! empty( $add_attchments ) ) {
                    foreach ( $add_attchments as $add ) {
                        $app->remove_object( $add );
                    }
                }
            }
            if ( $as_revision ) $id = $original->id;
        }
        if (! empty( $remove_attachments ) ) {
            foreach ( $remove_attachments as $remove ) {
                $app->remove_object( $remove );
            }
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
                $add_return_args .= '&need_rebuild=1';
            }
        }
        if ( $is_changed || $is_new ) {
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
        $app->txn_active = false;
        $db->caching = false;
        $callback = ['name' => 'post_save', 'is_new' => $is_new,
                     'changed_cols' => $changed_cols, 'orig_relations' => $orig_relations,
                     'orig_metadata' => $orig_metadata ];
        $app->run_callbacks( $callback, $model, $obj, $original, $clone_org );
        if (!$as_revision ) {
            if ( $model !== 'template' || $app->param( '__save_and_publish' ) ) {
                $app->publish_obj( $obj, $original, true );
            }
        }
        if ( isset( $callback['return_url'] ) && $callback['return_url'] ) {
            $app->redirect( $callback['return_url'] );
        }
        if ( $app->param( '_duplicate' ) ) {
            if (! $app->can_do( $model, 'duplicate', null, $app->workspace() ) ) {
                $app->error( 'Permission denied.' );
            }
            $add_return_args .= '&cloned=1';
            $clone_obj = PTUtil::clone_object( $app, $obj, false );
            $app->redirect( $app->admin_url . '?__mode=view&_type=edit&_model=' . $model .
                '&id=' . $clone_obj->id . $app->workspace_param . $add_return_args );
        } else {
            $add_return_args .= '&saved=1';
        }
        $app->redirect( $app->admin_url . '?__mode=view&_type=edit&_model=' . $model .
            '&id=' . $id . $app->workspace_param . $add_return_args );
    }

    function check_parent_id ( $obj, $original, &$error ) {
        $children = [];
        $children = $this->get_children( $obj, $children );
        if ( count( $children ) ) {
            foreach ( $children as $child ) {
                if ( $obj->parent_id == $child->id ) {
                    $error = $this->translate( 'A child object can not be specified as a parent.' );
                    return false;
                    break;
                }
            }
        }
        if ( $obj->parent_id && $original->parent_id == 0 ) {
            $terms = ['parent_id' => 0 ];
            if ( $obj->has_column( 'workspace_id' ) ) {
                $terms['workspace_id'] = (int) $obj->workspace_id;
            }
            $root = $this->db->model( $obj->_model )->load( $terms, [], 'id,parent_id' );
            if ( count( $root ) < 2 ) {
                $error = $this->translate( 'You can not change the parent of the root object.' );
                return false;
            }
        }
        return true;
    }

    function get_children ( $obj, &$children = [] ) {
        $terms = ['parent_id' => $obj->id ];
        if ( $obj->has_column( 'workspace_id' ) ) {
            $terms['workspace_id'] = (int) $obj->workspace_id;
        }
        $_children = $this->db->model( $obj->_model )->load( $terms, [], 'id,parent_id' );
        if ( empty( $children ) ) {
            $children = $_children;
        }
        if ( count( $_children ) ) {
            foreach ( $_children as $_child ) {
                $this->get_children( $_child, $children );
            }
        }
        return $children;
    }

    function workflow_post_save ( &$cb, $app, &$obj, $original, $clone_org ) {
        $workflow_class = new PTWorkflow();
        return $workflow_class->workflow_post_save( $cb, $app, $obj, $original, $clone_org );
    }

    function get_mail_tmpl ( $basename, &$template = null, $workspace = null ) {
        $app = $this;
        $workspace = $workspace ? $workspace : $app->workspace();
        if ( is_object( $workspace ) && $workspace->_model != 'workspace' ) {
            if ( $workspace->has_column( 'workspace_id' ) ) {
                $workspace = $workspace->workspace;
            }
        }
        $status_published = $app->status_published( 'template' );
        $terms = ['class' => 'Mail', 'status' => $status_published,
                  'basename' => $basename ];
        $args = ['limit' => 1];
        if ( $workspace ) {
            $terms['workspace_id'] = ['IN' => [0, (int) $workspace->id ] ];
            $args['direction'] = 'descend';
            $args['sort'] = 'workspace_id';
        } else {
            $terms['workspace_id'] = 0;
        }
        $tmpl = $app->db->model( 'template' )->load( $terms, $args );
        if (! empty( $tmpl ) ) {
            $template = $tmpl[0];
            return $template->text;
        }
        $path = 'email' . DS . "{$basename}.tmpl";
        $path = $app->ctx->get_template_path( $path );
        if ( file_exists( $path ) ) {
            return file_get_contents( $path );
        }
    }

    function publish_obj ( $obj, $original = null, $dependencies = false,
                           $files_only = false, $archives_only = false ) {
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
        $extra_path = $table->out_path ? '' : $app->extra_path;
        if ( $workspace ) {
            $base_url = $workspace->site_url;
            $base_path = $workspace->site_path;
            $extra_path = $table->out_path ? '' : $workspace->extra_path;
        }
        $attachment_cols = PTUtil::attachment_cols( $model );
        $rel_attach_cols = PTUtil::attachment_cols( $model, $scheme, 'relation' );
        if ( count( $rel_attach_cols ) || count( $attachment_cols ) ) {
            $from_id = $app->param( '_duplicate_from' );
            if ( $from_id ) {
                PTUtil::attachments_to_clone( $app, $obj, $obj );
            } else {
                $attachments = $app->get_related_objs( $obj, 'attachmentfile' );
                $publish_status = $app->status_published( $model );
                if ( count( $attachment_cols ) ) {
                    $attachment_ids = [];
                    foreach ( $attachment_cols as $attachment_col ) {
                        if ( $obj->$attachment_col ) {
                            $attachment_ids[] = (int) $obj->$attachment_col;
                        }
                    }
                    if ( count( $attachment_ids ) ) {
                        $add_attachments = $app->db->model( 'attachmentfile' )->load(
                            ['id' => ['IN' => $attachment_ids ] ] );
                        if ( count( $add_attachments ) ) {
                            $attachments = array_merge( $attachments, $add_attachments );
                        }
                    }
                }
                foreach ( $attachments as $attachment ) {
                    $orig_attachment = clone $attachment;
                    if ( $obj->has_column( 'status' ) ) {
                        $status = $obj->status;
                        $file_status = $status == $publish_status ? 4 : $status;
                        if ( $attachment->status != $file_status ) {
                            $attachment->status( $file_status );
                            $attachment->save();
                        }
                    }
                    $app->publish_obj(
                        $attachment, $orig_attachment, $dependencies, $files_only );
                }
            }
        }
        $out_counter = 0;
        if (! $table->do_not_output && ! $archives_only ) {
            $out_path = $table->out_path ? $table->out_path : $model;
            foreach ( $properties as $key => $val ) {
                if ( $model === 'asset' && $key === 'file' ) {
                    $app->post_save_asset( null, $app, $obj );
                    continue;
                }
                if ( $val === 'file' ) {
                    if (!$obj->$key ) continue;
                    $metadata = $db->model( 'meta' )->get_by_key(
                             ['model' => $model, 'object_id' => $obj->id,
                              'kind' => 'metadata', 'key' => $key ] );
                    if (!$metadata->id ) continue;
                    $file_meta = json_decode( $metadata->text, true );
                    $mime_type = $file_meta['mime_type'];
                    $file_ext = $file_meta['extension'];
                    if (! $out_counter && $obj->has_column( 'basename' ) ) {
                        $file = "{$out_path}" . DS . $obj->basename;
                    } else {
                        $file = "{$out_path}" . DS . "{$model}-{$key}-" . $obj->id;
                    }
                    $out_counter++;
                    if ( $file_ext ) $file .= '.' . $file_ext;
                    $file_path = $base_path . '/'. $extra_path . $file;
                    $file_path = str_replace( '/', DS, $file_path );
                    $url = $base_url . '/'. $extra_path . $file;
                    if (!$table->revisable || !$obj->rev_type ) {
                        if ( file_exists( $file_path ) ) {
                            $comp = base64_encode( $fmgr->get( $file_path ) );
                            $data = base64_encode( $obj->$key );
                            if ( $original && $obj->has_column( 'status' ) ) {
                                $status_published = $app->status_published( $obj->_model );
                                if ( $original && $original->status == $status_published &&
                                    $obj->status == $status_published ) {
                                    if ( $comp === $data ) continue;
                                }
                            } else {
                                if ( $comp === $data ) continue;
                            }
                            unset( $comp, $data );
                        }
                        $app->publish( $file_path, $obj, $key, $mime_type );
                    }
                }
            }
        }
        if ( $files_only ) return;
        $terms = ['model' => $model, 'container' => $model ];
        $extra = '';
        $ws_id = 0;
        if ( $obj->has_column( 'workspace_id' ) ) {
            $map = $db->model( 'urlmapping' )->__new();
            $extra = ' AND ' . $map->_colprefix . 'workspace_id';
            $ws_id = (int) $obj->workspace_id;
            if ( $ws_id && $table->space_child && !$table->display_system ) {
                $extra .= " IN (0,{$ws_id})";
            } else {
                $extra .= '=' . $ws_id;
            }
        }
        if ( $obj->_model === 'template' ) {
            unset( $terms['container'] );
        }
        $app->get_scheme_from_db( 'urlmapping' );
        $map_cols = 'id,mapping,publish_file,template_id,link_status,date_based,model,skip_empty,'
                  . 'container,container_scope,fiscal_start,workspace_id,compiled,cache_key';
        $mappings = $db->model( 'urlmapping' )->load(
            $terms, ['and_or' => 'OR'], $map_cols, $extra );
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
                    $day = '';
                    if ( $at == 'Daily' ) {
                        $day = ", DAY({$date_col})";
                    }
                    $sql = "SELECT DISTINCT YEAR({$date_col}), MONTH({$date_col}){$day} FROM $_table";
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
                        $callback = ['name' => 'publish_date_based',
                                     'model' => $container_obj->_model ];
                        $app->run_callbacks( $callback, $container_obj->_model, $wheres );
                        $sql .= join( ' AND ', $wheres );
                    }
                    $sql .= " ORDER BY YEAR({$date_col})";
                    $year_month_day = $container_obj->load( $sql );
                    if ( $at == 'Daily' ) {
                        foreach ( $year_month_day as $ymd ) {
                            $ymd = $ymd->get_values();
                            $y = $ymd["YEAR({$date_col})"];
                            $m = $ymd["MONTH({$date_col})"];
                            $d = $ymd["DAY({$date_col})"];
                            $ts = "{$y}{$m}{$d}000000";
                            $file_path = $app->build_path_with_map( $obj, $mapping, $table, $ts );
                        }
                    } else {
                        if ( $at === 'Fiscal-Yearly' ) {
                            $fy_start = $mapping->fiscal_start;
                            $fy_end = $fy_start == 1 ? 12 : $fy_start - 1;
                        }
                        $time_stamp = [];
                        foreach ( $year_month_day as $ym ) {
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
                        }
                    }
                    if ( $app->resetdb_per_rebuild ) $app->db->reconnect();
                }
            }
            if ( $dependencies && ( $app->mode == 'save' || $app->mode == 'delete' ) ) {
                $triggers = $db->model( 'relation' )->load(
                    ['name' => 'triggers', 'from_obj' => 'urlmapping',
                     'to_obj' => 'table', 'to_id' => $table->id ]
                );
                $ctx = $app->ctx;
                $pub = new PTPublisher;
                $magic_token = $app->param( 'magic_token' )
                             ? $app->param( 'magic_token' ) : $app->request_id;
                $ctx->vars['magic_token'] = $magic_token;
                foreach ( $triggers as $trigger ) {
                    $map = $db->model( 'urlmapping' )->load( (int) $trigger->from_id );
                    if ( $map && $map->container != $obj->_model ) {
                        if ( $map->trigger_scope ) {
                            $trigger_scope =
                                $obj->has_column( 'workspace_id' ) ? (int) $obj->workspace_id : 0; 
                            if ( $map->workspace_id != $trigger_scope ) continue;
                        }
                        $trigger_urls = $db->model( 'urlinfo' )->load(
                            ['urlmapping_id' => $map->id, 'publish_file' => ['IN' => [1, 3] ],
                             'delete_flag' => 0, 'is_published' => 1 ]
                        );
                        foreach ( $trigger_urls as $triggerUrl ) {
                            if ( $triggerUrl->model == $obj->_model
                                && $triggerUrl->id == $obj->id ) {
                                continue;
                            }
                            if ( $triggerUrl->publish_file == 3 ) {
                                if ( $fmgr->exists( $triggerUrl->file_path ) ) {
                                    $fmgr->delete( $triggerUrl->file_path );
                                }
                                if ( $triggerUrl->is_published ) {
                                    $triggerUrl->is_published( 0 );
                                    $triggerUrl->save();
                                }
                                continue;
                            }
                            $data = $pub->publish( $triggerUrl );
                            $newHash = md5( $data );
                            if ( $triggerUrl->md5 == $newHash ) continue;
                            $fmgr->put( $triggerUrl->file_path, $data );
                            $triggerUrl->md5( $newHash );
                            $triggerUrl->save();
                        }
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

    function preview ( $obj = null, $scheme = [] ) {
        $app = $this;
        $properties = isset( $scheme['edit_properties'] )
                    ? $scheme['edit_properties'] : [];
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
            if (!$map || !$map->template || !$map->model ) {
                return $app->error( 'View or Model not specified.' );
            }
        } else {
            $template = $obj;
        }
        if ( is_object( $map ) && $map->id ) {
            $template = $map->template;
            $model = $map->model;
        } else {
            $model = 'template';
        }
        $app->init_tags();
        $ctx = clone $app->ctx;
        $ctx->include_paths[ $app->site_path ] = true;
        if ( $workspace ) {
            $ctx->include_paths[ $workspace->site_path ] = true;
        }
        $table = $app->get_table( $model );
        $ctx->stash( 'current_urlmapping', $map );
        $ctx->stash( 'current_object', $obj );
        $ctx->vars['current_archive_model'] = $obj->_model;
        $ctx->vars['current_object_id'] = $obj->id;
        $archive_type = '';
        if ( $obj->_model === 'template' ) {
            $ctx->stash( 'preview_template', $template );
        }
        if ( $obj->_model === 'template' && $table->name != 'template' ) {
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
                $title_col = $table->primary;
                $ctx->stash( 'current_archive_title', $obj->$title_col );
                $ctx->stash( 'current_archive_type', $obj->_model );
                $ctx->stash( 'preview_object', $obj );
            } else {
                $ctx->stash( 'current_archive_title', $template->name );
                $ctx->stash( 'current_archive_type', 'index' );
            }
        } else {
            $archive_type = is_object( $map ) ? $map->model : 'index';
            $ctx->stash( 'current_archive_type', $archive_type );
            $tmpl = $template->text;
            if ( $app->mode == 'save' && $app->param( '_model' )
                && $app->param( '_model' ) == 'template' ) {
                $tmpl = $app->param( 'text' );
            }
            $primary = $table->primary;
            if ( is_object( $map ) && $map->model == $obj->_model ) {
                $ctx->stash( 'current_archive_title', $obj->$primary );
            }
        }
        if ( is_object( $map ) && $map->container ) {
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
        $column_defs = $scheme['column_defs'];
        foreach ( $properties as $key => $val ) {
            $magic = $app->param( "{$key}-magic" );
            if ( $magic ) {
                $sess = $db->model( 'session' )
                           ->get_by_key( ['name' => $magic,
                                   'user_id' => $app->user()->id, 'kind' => 'UP'] );
                if ( $sess->id ) {
                    if ( $column_defs[ $key ]['type'] != 'int' ) {
                        $obj->$key( $sess->data );
                    }
                    $ctx->stash( 'current_session_' . $key, $sess );
                }
            }
        }
        $ctx->vars = [];
        $ctx->local_vars = [];
        if (! $theme_static = $app->theme_static ) {
            $theme_static = $app->path . 'theme-static/';
            $app->theme_static = $theme_static;
        }
        $ctx->vars['theme_static'] = $theme_static;
        $ctx->vars['application_dir'] = __DIR__;
        $ctx->vars['application_path'] = $app->path;
        $ctx->vars['current_archive_type'] = $ctx->stash( 'current_archive_type' );
        $ctx->vars['current_archive_title'] = $ctx->stash( 'current_archive_title' );
        $mapping = is_object( $map ) ? $map->mapping : 'preview.tmpl';
        if ( isset( $obj ) && is_object( $map ) && isset( $table ) ) {
            $ts = $ctx->stash( 'current_timestamp' )
                ? $ctx->stash( 'current_timestamp' ) : '';
            $url = $app->build_path_with_map( $obj, $map, $table, $ts, true );
            $ctx->stash( 'current_archive_url', $url );
            $ctx->vars['current_archive_url'] = $url;
        }
        $ctx->vars['current_archive_title'] = $ctx->stash( 'current_archive_title' );
        if ( strpos( $mapping, '.' ) === false ) {
            $mapping = $app->get_permalink( $obj );
        }
        $parts = explode( '.', $mapping );
        $extIndex = count( $parts ) - 1;
        $extension = strtolower( @$parts[ $extIndex ] );
        $mime_type = PTUtil::get_mime_type( $extension );
        $callback = ['name' => 'pre_preview', 'template' => $tmpl, 'model' => $model,
                     'mime_type' => $mime_type, 'workspace' => $workspace ];
        $app->init_callbacks( 'preview', 'pre_preview' );
        $app->run_callbacks( $callback, 'preview', $tmpl );
        $mime_type = $callback['mime_type'];
        $preview = $app->tmpl_markup === 'mt' ? $ctx->build( $tmpl )
                                              : $app->build( $tmpl, $ctx );
        if ( $app->eval_in_preview && strpos( $preview, '<?php' ) !== false ) {
            ob_start();
            eval( '?>' . $preview );
            $preview = ob_get_clean();
            if ( $err = error_get_last() ) {
                return $app->error( $err );
            }
        }
        $callback['name'] = 'post_preview';
        $app->init_callbacks( 'preview', 'post_preview' );
        $app->run_callbacks( $callback, 'preview', $preview );
        if (!$app->preview_redirect ) {
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
        $key = $app->encrypt( $user->id, $magic );
        $session = $db->model( 'session' )->get_by_key( $terms );
        $session->key( $mime_type );
        $session->start( time() );
        $session->expires( time() + $app->token_expires );
        $session->save();
        $db->commit();
        $app->redirect( $preview_url .
            "?__mode=preview&token={$magic}&key={$key}" . $app->workspace_param );
        exit();
    }

    function cleanup_tmp ( $app ) {
        $session_id = $app->param( 'session_id' );
        $app->log( $session_id );
        if (! $session_id ) return;
        $sessions =
            $app->db->model( 'session' )->load(
                ['name' => $session_id, 'kind' => 'TP', 'user_id' => $app->user()->id ] );
        if ( count( $sessions ) ) {
            foreach ( $sessions as $session ) {
                $model = $session->key;
                $obj_id = (int) $session->value;
                if (! $obj_id ||! $model ) {
                    continue;
                }
                $tmp_obj = $app->db->model( $model )->load( $obj_id );
                $app->remove_object( $tmp_obj );
            }
            $app->db->model( 'session' )->remove_multi( $sessions );
        }
    }

    function init_tags ( $force = false ) {
        $app = $this;
        if ( $app->init_tags && !$force ) return;
        $core_tags = $app->core_tags;
        $core_tags->init_tags( $force );
    }

    function build_path_with_map ( $obj, $mapping, $table, $ts = null, $url = false ) {
        if (! $mapping ) return '';
        $app = $this;
        $path = $mapping->mapping;
        if ( strpos( $path, '<' ) !== false ) {
            $map_path = $path;
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
            if ( $app->mode === 'view' ) {
                $obj = $db->model( $obj->_model )->get_by_key( ['id' => $obj->id ] );
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
            $compiled = $mapping->compiled;
            $cache_key = $mapping->cache_key;
            if ( $compiled && $cache_key ) {
                $ctx->compiled[ $cache_key ] = $compiled;
            }
            $ctx->vars['current_archive_title'] = $ctx->stash( 'current_archive_title' );
            $path = $app->tmpl_markup === 'mt' ? $ctx->build( $map_path )
                                               : $app->build( $map_path, $ctx );
        }
        $path = trim( $path );
        $base_url = $app->site_url;
        $base_path = $app->site_path;
        $workspace_id = $mapping->workspace_id;
        $workspace = null;
        if ( $workspace_id ) {
            $workspace = $mapping->workspace
                       ? $mapping->workspace
                       : $app->db->model( 'workspace' )->load( (int) $workspace_id );
        }
        if ( $workspace ) {
            $base_url = $workspace->site_url;
            $base_path = $workspace->site_path;
        }
        if ( mb_substr( $base_url, -1 ) != '/' ) $base_url .= '/';
        $ds = preg_quote( DS, '/' );
        if (! preg_match( "/{$ds}$/", $base_path ) ) $base_path .= DS;
        if (!$url && strpos( $path, DS ) === 0 ) {
            $path = ltrim( $path, DS );
        }
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
        } elseif ( $archive_type == 'daily' ) {
            $y = substr( $ts, 0, 4 );
            $m = substr( $ts, 4, 2 );
            $d = substr( $ts, 6, 2 );
            $title = "{$y}{$m}{$d}";
            $start = "{$y}{$m}{$d}000000";
            $end = "{$y}{$m}{$d}235959";
        }
        return [ $title, $start, $end ];
    }

    function set_relations ( $args, $ids = [], $add_only = false,
            &$errors = [], &$remove_attachments = [] ) {
        $app = $this;
        if (! is_array( $ids ) ) $ids = [ $ids ];
        $ids = array_unique( $ids );
        $is_changed = false;
        if (!$add_only ) {
            $relations = $app->db->model( 'relation' )->load( $args );
            if ( is_array( $relations ) && !empty( $relations ) ) {
                $removes = [];
                foreach ( $relations as $rel ) {
                    if (! in_array( $rel->to_id, $ids ) ) {
                        $removes[] = $rel;
                        $is_changed = true;
                        if ( $rel->to_obj == 'attachmentfile' ) {
                            $attachmentfile =
                                $app->db->model( 'attachmentfile' )->load( (int) $rel->to_id );
                            if ( $attachmentfile ) {
                                $urls = $app->db->model( 'urlinfo' )->load(
                                    ['model' => 'attachmentfile', 'object_id' => $attachmentfile->id ] );
                                foreach ( $urls as $url ) {
                                    $url->remove();
                                }
                                $remove_attachments[] = $attachmentfile;
                                // $app->remove_object( $attachmentfile );
                            }
                        }
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

    function file_attach_to_obj ( $obj, $col, $path,
                                  $label = '', &$error = '' ) {
        return PTUtil::file_attach_to_obj(
                $this, $obj, $col, $path, $label, $error );
    }

    function run_callbacks ( &$cb, $key, &$params = null, &$args = true,
        &$extra = null, &$option = null ) {
        $app = $this;
        $cb_name = $cb['name'];
        if (! is_string( $cb_name ) ||! is_string( $key ) ) {
            return;
        }
        $all_callbacks = isset( $app->callbacks[ $cb_name ][ $key ] ) ?
            $app->callbacks[ $cb_name ][ $key ] : [];
        $result = true;
        if (! empty( $all_callbacks ) ) {
            ksort( $all_callbacks );
            foreach ( $all_callbacks as $callbacks ) {
                foreach ( $callbacks as $callback ) {
                    list( $meth, $class ) = $callback;
                    $res = true;
                    if ( function_exists( $meth ) ) {
                        $res = $meth( $cb, $app, $params, $args, $extra, $option );
                    } else if ( $class && method_exists( $class, $meth ) ) {
                        $res = $class->$meth( $cb, $app, $params, $args, $extra, $option );
                    }
                    if (!$res && $result === true ) {
                        $result = false;
                    }
                }
            }
        }
        return $result;
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

    private function delete ( $app ) {
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
        $i = 0;
        $remove_objects = [];
        $label = $app->translate( $table->label );
        $errstr = $app->translate( 'An error occurred while deleting %s.', $label );
        $app->register_callback( 'meta', 'post_delete', 'post_delete_meta', 1, $app );
        $app->init_callbacks( 'meta', 'post_delete' );
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
            if (! isset( $status_published ) && $obj->has_column( 'status' ) ) {
                $status_published = $app->status_published( $obj->_model );
            }
            $error = false;
            if (!$obj->has_column( 'rev_type' ) || ( $obj->has_column( 'rev_type' ) &&
                $obj->rev_type == 0 ) ) {
                if ( count( $objects ) == 1 ) {
                    $original = clone $obj;
                    if ( isset( $status_published ) && $obj->has_column( 'status' ) ) {
                        $obj->status( 1 );
                        $obj->save();
                        $original->status( $status_published );
                        $app->publish_obj( $obj, $original, true );
                    }
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
            $app->txn_active = true;
            $app->remove_object( $obj, $table, $error );
            if ( $error ) {
                return $app->rollback( $errstr );
            } else {
                $db->commit();
                $app->txn_active = false;
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
            $rebuilt = 0;
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
                if ( $app->workspace() && empty( $mappings ) ) {
                    break;
                }
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
                $rebuilt++;
            }
        }
        $count = count( $objects );
        if ( $return_url = $app->param('_query_string') ) {
            $return_url = urldecode( $return_url );
            if ( strpos( $return_url, 'does_act=1' ) !== false ) {
                $return_url .= "&apply_actions={$count}";
            }
            $app->redirect( $app->admin_url . "?deleted={$count}&" . $return_url
                . $app->workspace_param );
        }
        $app->redirect( $app->admin_url .
            "?__mode=view&_type=list&_model={$model}&deleted={$count}" . $app->workspace_param );
    }

    function post_delete_meta ( &$cb, $app, $obj, $meta_objs ) {
        $attachment_ids = [];
        foreach ( $meta_objs as $meta ) {
            if ( $meta->to_obj == 'attachmentfile' ) {
                $attachment_ids[] = (int) $meta->to_id;
            }
        }
        if (! empty( $attachment_ids ) ) {
            $attachmentfiles = $app->db->model( 'attachmentfile' )->load(
                                ['id' => ['in' => $attachment_ids ] ] );
            if ( $attachmentfiles && ! empty( $attachmentfiles ) ) {
                foreach ( $attachmentfiles as $attachmentfile ) {
                    $urls = $app->db->model( 'urlinfo' )->load(
                        ['model' => 'attachmentfile', 'object_id' => $attachmentfile->id ] );
                    foreach ( $urls as $url ) {
                        $url->remove();
                    }
                    $app->remove_object( $attachmentfile );
                }
            }
        }
    }

    function remove_object ( $obj, $table = null, &$error = false,
            $remove_attachment = true ) {
        $app = $this;
        $db = $app->db;
        if (! $table ) $table = $app->get_table( $obj->_model );
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
        if ( count( $children ) ) {
            $_children = [];
            $child_removed = false;
            foreach ( $children as $child ) {
                $sth = null;
                $sth = $db->show_tables( $child );
                $table_name = $sth->fetchColumn();
                if ( $table_name ) {
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
                    $_children[] = $child;
                } else {
                    $child_removed = true;
                }
            }
            if ( $child_removed ) {
                $table->child_tables( implode( ',', $_children ) );
                $table->save();
            }
        }
        $relations = $db->model( 'relation' )->load(
            ['from_obj' => $obj->_model, 'from_id' => $obj->id ], null,
            'id,to_obj,to_id' );
        if (! empty( $relations ) ) {
            if (!$db->model( 'relation' )->remove_multi( $relations ) ) {
                $error = true;
                return;
            } else {
                $callback = ['name' => 'post_delete', 'error' => '',
                             'table' => $table];
                $app->run_callbacks( $callback, 'meta', $obj, $relations );
                if ( $callback['error'] ) {
                    $error = true;
                    return;
                }
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
        if ( $remove_attachment ) {
            $attachment_cols = PTUtil::attachment_cols( $model );
            if (! empty( $attachment_cols ) ) {
                $attachment_ids = [];
                foreach ( $attachment_cols as $key ) {
                    if ( $obj->$key ) $attachment_ids[] = (int) $obj->$key;
                }
                if (! empty( $attachment_ids ) ) {
                    $attachmentfiles =
                        $db->model( 'attachmentfile' )->load(
                            ['id' => ['IN' => $attachment_ids ] ] );
                    $attachment_tbl = $app->get_table( 'attachmentfile' );
                    foreach ( $attachmentfiles as $attachmentfile ) {
                        $app->remove_object( $attachmentfile, $attachment_tbl, $error );
                    }
                }
            }
        }
        if ( $obj->has_column( 'rev_type' ) && ! $obj->rev_type ) {
            $cols = 'id';
            if (! empty( $attachment_cols ) ) {
                $cols .= ',' . implode( ',', $attachment_cols );
            }
            $revisions = $db->model( $obj->_model )->load(
                ['rev_object_id' => $obj->id ], null, $cols );
            if (! empty( $revisions ) ) {
                foreach ( $revisions as $rev ) {
                    $app->remove_object( $rev, $table, $error );
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
            if (! $obj->has_column( 'parent_id' ) ) {
                $app->get_scheme_from_db( $obj->_model );
            }
            $children = $db->model( $obj->_model )->load( ['parent_id' => $obj->id ] );
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
        if (! empty( $options ) ) $url .= '&' . http_build_query( $options );
        if ( $workspace && ! $system ) {
            $app->redirect( $url . '&workspace_id=' . $workspace->id );
        }
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
                if ( $keys === false ) {
                    return $driver->flush();
                }
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
            if ( $keys === false ) {
                return $driver->flush();
            }
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

    function save_filter_obj ( $cb, $pado, &$obj ) {
        if ( $obj->has_column( 'uuid' ) && ! $obj->uuid ) {
            $key = $obj->_model . '_' . 'uuid';
            $values = $obj->get_values();
            if ( ( isset( $values[ $key ] ) ) ) {
                $obj->uuid( $this->generate_uuid( $obj->_model ) );
            }
        }
        return true;
    }

    function generate_uuid ( $model = null, $counter = 0 ) {
        $uuid = sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), 
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
        if ( $model ) {
            $_uuid = $this->db->quote( $uuid );
            $extra = ( " AND {$model}_uuid={$_uuid} " );
            $count = $this->db->model( $model )->count( [], null, 'id', $extra );
            if ( $count ) {
                $counter++;
                if ( $counter > 4 ) {
                    return $uuid;
                }
                usleep( 2 );
                return $this->generate_uuid( $model, $counter );
            }
        }
        return $uuid;
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
                $required[] = 'delete_flag';
            } else if ( $model === 'table' ) {
                $required[] = 'name';
            } else if ( $model === 'urlmapping' ) {
                $required[] = 'model';
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
            $required[] = $table->primary;
            $attachment_cols = PTUtil::attachment_cols( $model );
            if (! empty( $attachment_cols ) ) {
                $required = array_merge( $required, $attachment_cols );
            }
        }
        if ( $required && is_string( $required ) && $required != '*' ) {
            $required = explode( ',', $required );
        }
        if ( $model === 'asset' ) {
            $required[] = 'file_name';
            $required[] = 'extra_path';
        }
        if ( is_array( $required ) ) {
            // For permission check.
            if ( $obj->has_column( 'user_id' ) ) {
                $required[] = 'user_id';
            }
            if ( $obj->has_column( 'created_by' ) ) {
                $required[] = 'created_by';
            }
            if ( $obj->has_column( 'workspace_id' ) ) {
                $required[] = 'workspace_id';
            }
            $required = array_unique( $required );
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
            if ( $model === 'urlinfo' ) {
                $app->db->model( 'urlinfo' )->pre_load(
                        $terms, $args, $cols, $extra, true );
            }
            $objects = $obj->load( $terms, $args, $required, $extra );
            return $objects;
        } else {
            $id = $app->param( 'id' );
            if ( is_array( $id ) ) {
                array_walk( $id, function( &$id ) {
                    $id = (int) $id;
                });
                $terms = ['id' => ['IN' => $id ] ];
                $args = null;
                $extra = '';
                if ( $model === 'urlinfo' ) {
                    $app->db->model( 'urlinfo' )->pre_load(
                        $terms, $args, $required, $extra, true );
                }
                $objects = $obj->load( $terms, $args, $required, $extra );
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

    function pre_listing_workflow ( &$cb, $app, &$terms, &$args, &$extra ) {
        $model = isset( $cb['model'] )
               ? isset( $cb['model'] ) : $app->param( '_model' );
        $users = $app->db->model( 'user' )->load( ['status' => 2, 'lockout' => 0] );
        if ( empty( $users ) ) {
            $terms = ['id' => 0];
            return;
        }
        $wf_model = $app->param( 'workflow_model' );
        $wf_type = $app->param( 'workflow_type' );
        $workspace_id = $app->workspace() ? $app->workspace()->id : 0;
        $user_ids = [];
        foreach ( $users as $user ) {
            $group_name = $app->permission_group( $user, $wf_model, $workspace_id );
            if ( $wf_type == 'draft' && $group_name == 'creator' ) {
                $user_ids[] = $user->id;
            } else if ( $wf_type == 'review' && $group_name == 'reviewer' ) {
                $user_ids[] = $user->id;
            } else if ( $wf_type == 'publish' && $group_name == 'publisher' ) {
                $user_ids[] = $user->id;
            } else if ( $group_name && $wf_type == 'all' ) {
                $user_ids[] = $user->id;
            }
        }
        if ( empty( $user_ids ) ) {
            $terms = ['id' => 0];
            return;
        } else {
            $terms = ['id' => ['in' => $user_ids ] ];
            return;
        }
    }

    function permission_group ( $user, $model, $workspace = 0 ) {
        if ( $user->is_superuser ) return 'publisher';
        if ( is_object( $workspace ) ) $workspace = $workspace->id;
        $app = $this;
        $perms = $app->permissions( $user );
        if ( isset( $perms[ $workspace ] ) ) {
            $perm = $perms[ $workspace ];
            $ws_admin = in_array( 'workspace_admin', $perm )
                      ? true : false;
            if ( $ws_admin || $user->is_superuser
                || in_array( "can_activate_{$model}", $perm ) ) {
                return 'publisher';
            } else if ( in_array( "can_review_{$model}", $perm ) ) {
                return 'reviewer';
            } else if ( in_array( "can_create_{$model}", $perm )
                || in_array( "can_update_own_{$model}", $perm )
                || in_array( "can_update_all_{$model}", $perm ) ) {
                return 'creator';
            }
        }
        return null;
    }

    function pre_listing ( &$cb, $app, &$terms, &$args, &$extra ) {
        if ( $app->mode == 'rebuild_phase' || $app->mode == 'save' ) return true;
        $model = isset( $cb['model'] )
               ? $cb['model'] : $app->param( '_model' );
        $workspace_id = $app->workspace() ? $app->workspace()->id : 0;
        $op_map = ['gt' => '>', 'lt' => '<', 'eq' => '=', 'ne' => '!=', 'ge' => '>=',
                   'le' => '<=', 'ct' => 'LIKE', 'nc' => 'NOT LIKE', 'bw' => 'LIKE',
                   'ew' => 'LIKE'];
        $user_id = $app->user() ? $app->user()->id : 0;
        $filter_primary = ['key' => $model, 'user_id' => $user_id,
                           'workspace_id' => $workspace_id,
                           'kind'  => 'list_filter_primary'];
        if ( $user_id && $app->id == 'Prototype' && $app->mode == 'view' ) {
            $primary = $app->db->model( 'option' )->get_by_key( $filter_primary );
            if ( $app->param( '_detach_filter' ) ) {
                $app->param( '_filter', 0 );
                if ( $primary->id ) $primary->remove();
            } else {
                if ( $primary->id ) {
                    if (! $app->param( '_filter_id' )
                     && ! $app->param( 'select_system_filters' ) ) {
                        $app->param( '_filter', $model );
                        if ( $primary->object_id ) {
                            $app->param( '_filter_id', $primary->object_id );
                        } else if ( $primary->value == 'system_filter' ) {
                            $app->param( 'select_system_filters', $primary->extra );
                            $app->param( '_system_filters_option', $primary->data );
                        }
                    }
                }
            }
        }
        if ( $model == 'user'
            && $app->param( 'workflow_model' ) && $app->param( 'workflow_type' ) ) {
            $app->pre_listing_workflow( $cb, $app, $terms, $args, $extra );
        } else if ( $model == 'urlinfo' ) {
            $cols = '*';
            $app->db->model( 'urlinfo' )->pre_load( $terms, $args, $cols, $extra, true );
        }
        $_filter = $app->param( '_filter' );
        if ( $_filter && $_filter == $model ) {
            if ( $app->param( 'limit' ) ) {
                $offset = (int) $app->param( 'offset' );
                $limit = (int) $app->param( 'limit' );
                if ( $limit ) {
                    $args['offset'] = $offset;
                    $args['limit'] = $limit;
                }
            }
            $params = $app->param();
            $conditions = [];
            $scheme = $cb['scheme'];
            $table  = $cb['table'];
            $column_defs = $scheme['column_defs'];
            $list_props = $scheme['list_properties'];
            $obj = $app->db->model( $model )->new();
            $system_filter = $app->param( 'select_system_filters' );
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
            if ( $app->param( '_filter' ) ) {
                $dbprefix = DB_PREFIX;
                $filter_params = [];
                $filter_and = false;
                $filter_add_params = [];
                $filtered_ids = [];
                foreach ( $params as $key => $conds ) {
                    if ( strpos( $key, '_filter' ) === 0 ) {
                        $filter_add_params[ $key ] = $conds;
                    }
                    if ( strpos( $key, '_filter_cond_' ) === 0 ) {
                        $filter_params[ $key ] = $conds;
                        $cond = [];
                        $key = preg_replace( '/^_filter_cond_/', '', $key );
                        if ( $key == 'status' || $key == 'rev_type' ) {
                            if ( $app->id == 'Bootstrapper' ) continue;
                        }
                        $values = isset( $params['_filter_value_' . $key ] )
                                ? $params['_filter_value_' . $key ] : [];
                        $filter_params[ '_filter_value_' . $key ] = $values;
                        if (! isset( $column_defs[ $key ]['type'] ) ) continue;
                        $type = $column_defs[ $key ]['type'];
                        $i = 0;
                        $_values = [];
                        foreach ( $conds as $val ) {
                            $value = isset( $values[ $i ] ) ? $values[ $i ] : null;
                            if (! isset( $op_map[ $val ] ) ) continue;
                            $op = $op_map[ $val ];
                            if ( $type == 'datetime' ) {
                                $value = $obj->db2ts( $value );
                                $value = $obj->ts2db( $value );
                            } else if ( strpos( $op, 'LIKE' ) !== false ) {
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
                                $col = $app->db->model( 'column' )->get_by_key(
                                                        ['name' => $key,
                                                         'table_id' => $table->id ] );
                                if (!$col->id ) continue;
                                $rel_model = $col->options;
                                $_table = $app->db->model( 'table' )->get_by_key(
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
                            $count_rels = 0;
                            foreach ( $conds as $val ) {
                                $value = $values[ $i ];
                                if ( count( $values ) > 1 ) {
                                    $_cond[ $op ] = [ 'OR' => $values ];
                                    $count_rels = count( array_unique( $values ) );
                                } else {
                                    $_cond[ $op ] = $value;
                                    $count_rels = 1;
                                }
                                ++$i;
                            }
                            $rel_terms = [ $rel_col => $_cond ];
                            if ( $rel_model == 'tag' ) {
                                $rel_terms['class'] = $model;
                            }
                            $rel_objs = $rel_obj->load( $rel_terms );
                            $and_or = $app->param( "_filter_and_or_{$key}" )
                                    ? $app->param( "_filter_and_or_{$key}" ) : 'AND';
                            $and_or = strtoupper( $and_or );
                            if ( $and_or != 'OR' ) $and_or = 'AND';
                            if ( is_array( $rel_objs ) && !empty( $rel_objs ) ) {
                                $rel_ids = [];
                                foreach ( $rel_objs as $_obj ) {
                                    $rel_ids[] = (int) $_obj->id;
                                }
                                if ( $type === 'relation' ) {
                                    $rel_terms = ['to_id'    => ['IN' => $rel_ids ],
                                                  'to_obj'   => $rel_model,
                                                  'from_obj' => $model ];
                                    $sql = '';
                                    if ( $obj->has_column( 'rev_type' ) || $obj->has_column( 'status' ) ) {
                                        $sql = "SELECT {$dbprefix}relation.relation_from_id FROM {$dbprefix}relation,{$dbprefix}";
                                        $sql .= "{$model} WHERE ( relation_to_id IN (";
                                        $sql .= implode( ',', $rel_ids ) . ')';
                                        $sql .= " AND relation_to_obj='{$rel_model}' AND relation_from_obj='{$model}')";
                                        $sql .= " AND {$dbprefix}relation.relation_from_id={$dbprefix}{$model}.{$model}_id ";
                                    }
                                    if ( $obj->has_column( 'rev_type' ) &&
                                        ( ! $app->param( 'revision_select' ) &&
                                            ! $app->param( 'manage_revision' ) )
                                            || ( isset( $terms['rev_type'] ) &&
                                            ( is_numeric( $terms['rev_type'] ) || 
                                            ( is_string( $terms['rev_type'] ) &&
                                             ctype_digit( $terms['rev_type'] ) ) ) ) ) {
                                        $rev_type = isset( $terms['rev_type'] ) ? (int) $terms['rev_type'] : 0;
                                        $sql .= " AND {$dbprefix}{$model}.{$model}_rev_type={$rev_type}";
                                        if ( $obj->has_column( 'status' ) && isset( $terms['status'] ) ) {
                                            $status = $terms['status'];
                                            if ( is_string( $status ) ) $status +=0;
                                            if ( is_numeric( $status ) ) {
                                                $sql .= " AND {$dbprefix}{$model}.{$model}_status=$status";
                                            }
                                        }
                                        $relations =
                                            $app->db->model( 'relation' )->load( $sql );
                                    } else {
                                        if ( $obj->has_column( 'status' ) && isset( $terms['status'] ) ) {
                                            $status = $terms['status'];
                                            if ( is_string( $status ) ) $status +=0;
                                            if ( is_numeric( $status ) ) {
                                                $sql .= " AND {$dbprefix}{$model}.{$model}_status=$status";
                                            }
                                            $relations =
                                                $app->db->model( 'relation' )->load( $sql );
                                        } else {
                                            $relations =
                                            $app->db->model( 'relation' )->load( $rel_terms, [], 'from_id' );
                                        }
                                    }
                                    if ( is_array( $relations ) && !empty( $relations ) ) {
                                        $from_ids = [];
                                        if ( $and_or == 'AND' ) {
                                            $rel_map = [];
                                            foreach ( $relations as $rel ) {
                                                if (! isset( $rel_map[ $rel->from_id ] ) ) {
                                                    $rel_map[ $rel->from_id ] = 0;
                                                }
                                                $rel_map[ $rel->from_id ]++;
                                            }
                                            $and_cnt = count( $rel_ids );
                                            foreach ( $rel_map as $rel_id => $rel_cnt ) {
                                                if ( $rel_cnt == $and_cnt && ( $count_rels && $count_rels == $rel_cnt ) ) {
                                                    $from_ids[] = $rel_id;
                                                }
                                            }
                                        } else {
                                            foreach ( $relations as $rel ) {
                                                $from_ids[] = (int) $rel->from_id;
                                            }
                                        }
                                        $from_ids = array_unique( $from_ids );
                                        $filtered_ids[] = $from_ids;
                                        // $terms['id'] = ['AND' => ['IN' => $from_ids ] ];
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
                                    if ( count( $cond ) > 1 ) {
                                        $cond[] = [ $op => $value ];
                                        $cnt++;
                                        continue;
                                    }
                                    $orig = [];
                                    $orig[] = $cond;
                                    $orig[] = [ $op => $value ];
                                    $cond = $orig;
                                } else {
                                    $cond[ $op ] = $value;
                                }
                                $cnt++;
                            }
                            $conditions[ $key ] = $cond;
                        }
                    }
                }
                if ( $_filter_and_or = $app->param( '_filter_and_or' ) ) {
                    $_filter_and_or = strtoupper( $_filter_and_or );
                    $_filter_and_or = $_filter_and_or == 'OR' ? 'OR' : 'AND';
                    $args['array_and_or'] = $_filter_and_or;
                } else {
                    $_filter_and_or = 'AND';
                }
                if (! empty( $filter_add_params ) ) {
                    $app->ctx->vars['filter_add_params'] =
                        '&' . http_build_query( $filter_add_params );
                }
                foreach ( $conditions as $col => $cond ) {
                    if ( is_array( $cond ) && $cond[ key( $cond ) ] === null ) continue;
                    $and_or = $app->param( "_filter_and_or_{$col}" )
                            ? $app->param( "_filter_and_or_{$col}" ) : 'AND';
                    $and_or = strtoupper( $and_or );
                    if ( $and_or != 'OR' ) $and_or = 'AND';
                    $terms[ $col ] = [ $and_or => $cond ];
                }
                if (! empty( $filtered_ids ) ) {
                    $all_ids = [];
                    foreach ( $filtered_ids as $filtered ) {
                        $all_ids = array_merge( $all_ids, $filtered );
                    }
                    $all_ids = array_unique( $all_ids );
                    if ( $_filter_and_or == 'AND' ) {
                        if (! isset( $terms['id'] ) ) {
                            $matche_ids = [];
                            foreach ( $all_ids as $filtered_id ) {
                                foreach ( $filtered_ids as $filtered ) {
                                    if (! in_array( $filtered_id, $filtered ) ) {
                                        unset( $matche_ids[ $filtered_id ] );
                                        continue 2;
                                    }
                                    $matche_ids[ $filtered_id ] = true;
                                }
                            }
                            if (! empty( $matche_ids ) ) {
                                $matche_ids = array_keys( $matche_ids );
                                $terms['id'] = ['AND' => ['IN' => $matche_ids ] ];
                            } else {
                                $terms['id'] = 0;
                            }
                        }
                    } else {
                        $terms['id'] = ['OR' => ['IN' => $all_ids ] ];
                    }
                }
                if ( $model == 'asset' && $app->param( 'insert_editor' ) && $app->param( 'dialog_view' ) ) {
                    if ( $app->param( 'select_system_filters' ) == 'filter_class_image' ) {
                        unset( $terms['class'] );
                        $images = $app->images;
                        $images[] = 'svg';
                        $terms['file_ext'] = ['IN' => $images ];
                    }
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
        $app->param( 'normalize', $normalize );
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

    function save_filter_form ( &$cb, $app, &$obj ) {
        if (! $obj->send_email ) {
            $obj->send_thanks(0);
            $obj->send_notify(0);
            return true;
        }
        $errors = $cb['errors'];
        $success = true;
        $msg = '';
        $addrs = [];
        if ( $obj->send_thanks ) {
            if ( $email_from = $obj->email_from ) {
                if (!$app->is_valid_email( $email_from, $msg ) ) {
                    $errors[] = $msg;
                    $success = false;
                }
            }
            $addrs = ['thanks_cc', 'thanks_bcc'];
        }
        if ( $obj->form_send_notify ) {
            $addrs[] = 'notify_to';
            $addrs[] = 'notify_cc';
            $addrs[] = 'notify_bcc';
            $notify_to = $obj->notify_to;
            if (! $notify_to ) {
                $success = false;
                $errors[] =
                  $app->translate( 'The Email address to notification is not specified.' );
            }
        }
        if (! empty( $addrs ) ) {
            foreach ( $addrs as $addr ) {
                if ( $email = $obj->$addr ) {
                    if ( strpos( $email, ',' ) !== false ) {
                        $emails = preg_split( '/\s*,\s*/', $email );
                    } else {
                        $emails = [ $email ];
                    }
                    foreach ( $emails as $email ) {
                        if (!$app->is_valid_email( $email, $msg ) ) {
                            $errors[] = $msg;
                            $success = false;
                        }
                    }
                }
            }
        }
        $cb['errors'] = $errors;
        return $success;
    }

    function post_delete_table ( &$cb, $app, &$obj ) {
        $options = $app->db->model( 'option' )->load(
            ['key' => $obj->name, 'kind' => 'scheme_version'] );
        foreach ( $options as $option ) {
            $option->remove();
        }
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
            $app->rebuild_urlinfo( $obj );
        }
        return true;
    }

    function post_save_urlmapping ( $cb, $app, &$obj, $original ) {
        if ( $original && $original->model != $obj->model ) {
            $urls = $app->db->model( 'urlinfo' )->load( ['urlmapping_id' => $obj->id ] );
            foreach ( $urls as $url ) {
                $url->remove();
            }
        }
        $app->return_args['need_rebuild'] = 1;
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

    function pre_save_widget ( $cb, $app, $obj, $original ) {
        if ( $original && $original->back_color != $obj->back_color ) {
            $ctx = $app->ctx;
            $tags = new PTTags();
            $args = ['hex' => $obj->back_color, 'alpha' => '0.4'];
            $rgba = $tags->hdlr_hex2rgba( $args, $ctx );
            $text = $obj->text;
            $regex = "/style=\"background-color:\s*rgba\(.*?\)/";
            $newColor = "style=\"background-color: rgba({$rgba})";
            $text = preg_replace( $regex, $newColor, $text );
            $obj->text( $text );
        }
        return true;
    }

    function pre_save_question ( $cb, $app, $obj, $original ) {
        if (! $obj->template ) {
            $questiontype_id = $obj->questiontype_id;
            if ( $questiontype_id ) {
                $qt = $app->db->model( 'questiontype' )->load( (int) $questiontype_id );
                if ( $qt ) {
                    $obj->template( $qt->template );
                }
            }
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

    function post_save_permission ( $cb, $app, $obj, $original ) {
        $sessions =
            $app->db->model( 'session' )->load( ['user_id' => $obj->user_id,
                'name' => 'user_permissions', 'kind' => 'PM' ] );
        if (! empty( $sessions ) ) {
            $app->db->model( 'session' )->remove_multi( $sessions );
        }
        if ( $original && ( $original->user_id != $obj->user_id ) ) {
            $sessions =
                $app->db->model( 'session' )->load( ['user_id' => $original->user_id,
                    'name' => 'user_permissions', 'kind' => 'PM' ] );
            if (! empty( $sessions ) ) {
                $app->db->model( 'session' )->remove_multi( $sessions );
            }
            $user_ids = [ $original->user_id ];
            $app->update_workflow( $user_ids );
        }
        $user_ids = [ $obj->user_id ];
        $app->update_workflow( $user_ids );
        return true;
    }

    function pre_save_role ( $cb, $app, $obj ) {
        $params = $app->param();
        $permissions = [];
        $permission_models = $app->param( 'permission_models' );
        foreach ( $permission_models as $model ) {
            if ( $app->param( 'columns-all-' . $model ) ) {
                $permissions[ $model ] = 'all';
            } else {
                $permissions[ $model ] = [];
                foreach ( $params as $param => $val ) {
                    if ( strpos( $param, $model ) === 0 ) {
                        $permissions[ $model ][] = $val;
                    }
                }
            }
        }
        $obj->columns_data( json_encode( $permissions ) );
        return 1;
    }

    function post_save_role ( $cb, $app, $obj ) {
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
                $app->update_workflow( $user_ids );
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

    function update_workflow ( $user_ids = [] ) {
        $app = $this;
        $users = $app->db->model( 'user' )->load( ['id' => ['IN' => $user_ids ] ] );
        foreach ( $users as $user ) {
            $workflows = $app->db->model( 'relation' )->load(
                ['from_obj' => 'workflow', 'to_obj' => 'user', 'to_id' => $user->id ]
            );
            foreach ( $workflows as $wf ) {
                $group = $wf->name;
                $workflow = $app->db->model( 'workflow' )->load( (int) $wf->from_id );
                if (! $workflow ) {
                    continue;
                }
                $ws_id = $workflow->workspace_id;
                $perms = $app->permissions( $user );
                $perm = isset( $perms[ $ws_id ] ) ? $perms[ $ws_id ] : null;
                if (! $perm ) {
                    continue;
                }
                $wf_model = $workflow->model;
                $group_name = $app->permission_group( $user, $wf_model , $ws_id );
                if ( $group_name == 'creator' && $group != 'user_draft' ) {
                    $wf->name( 'user_draft' );
                    $wf->save();
                    continue;
                } else if ( $group_name == 'reviewer' && $group != 'users_review' ) {
                    $wf->name( 'users_review' );
                    $wf->save();
                    continue;
                } else if ( $group_name == 'publisher' && $group != 'users_publish' ) {
                    $wf->name( 'users_publish' );
                    $wf->save();
                    continue;
                }
            }
        }
    }

    function rebuild_urlinfo ( $workspace = null ) {
        $app = $this;
        if ( $max_execution_time = $app->max_exec_time ) {
            $max_execution_time = (int) $max_execution_time;
            ini_set( 'max_execution_time', $max_execution_time );
        }
        $terms = $workspace ? ['workspace_id' => $workspace->id ] : [];
        $terms['delete_flag'] = ['IN' => [0,1] ];
        $urls = $app->db->model( 'urlinfo' )->load( $terms );
        $class = new PTListActions();
        return $class->reset_urlinfo( $app, $urls, null );
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
                       $mime_type = null, $type = 'file' ) {
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
        $urlmapping = null;
        $workspace = $app->workspace();
        if ( $obj->_model === 'workspace' ) {
            $workspace = $obj;
        } else {
            $workspace = $obj->workspace;
        }
        $date_based = false;
        $ctx->stash( 'current_container', '' );
        $ctx->vars['current_archive_model'] = $obj->_model;
        $ctx->vars['current_object_id'] = $obj->id;
        if ( is_object( $key ) ) {
            $urlmapping_id = $key->id;
            $urlmapping = $key;
            if ( $urlmapping->date_based ) $archive_date = $type;
            $type = $urlmapping->template_id ? 'archive' : 'model';
            $publish = $urlmapping->publish_file;
            $terms = ['urlmapping_id' => $urlmapping->id, 'class' => 'archive',
                 'object_id' => $obj->id, 'model' => $obj->_model,
                 'delete_flag' => ['IN' => [0, 1] ] ];
            if ( isset( $archive_date ) ) $terms['archive_date'] = $archive_date;
            $ui = $db->model( 'urlinfo' )->get_by_key( $terms );
            if ( $ui->file_path != $file_path ) {
                if ( $fmgr->exists( $ui->file_path ) ) {
                    $fmgr->unlink( $ui->file_path );
                    $remove_dirs[ dirname( $ui->file_path ) ] = true;
                }
            }
            if ( $app->unique_url || isset( $app->published_files[ $file_path ] ) ) {
                $ol_terms = ['file_path' => $file_path ];
                if ( $ui->id ) {
                    $ol_terms['id'] = ['!=' => $ui->id ];
                }
                $overlaps = $db->model( 'urlinfo' )->count( $ol_terms );
                if ( $overlaps ) {
                    $overlaps = $db->model( 'urlinfo' )->load( $ol_terms );
                    $db->model( 'urlinfo' )->remove_multi( $overlaps );
                }
            }
            $app->published_files[ $file_path ] = true;
            $ui->file_path( $file_path );
            $ui->publish_file( $publish );
            $template = $urlmapping->template;
            if ( $template && $template->status != 2 ) {
                $unlink = true;
            }
            $workspace = $key->workspace ? $key->workspace : $workspace;
            if ( $urlmapping->container ) {
                $container = $app->get_table( $urlmapping->container );
                if ( is_object( $container ) ) {
                    $ctx->stash( 'current_container', $container->name );
                    if ( $urlmapping->skip_empty ) { // Count Children
                        $cnt_tag = strtolower( $container->plural ) . 'count';
                        $count_terms = ['container' => $container->name, 'this_tag' => $cnt_tag ];
                        if ( $urlmapping->container_scope ) {
                            $count_terms['include_workspaces'] = 'all';
                        }
                        $count_children = $app->core_tags->hdlr_container_count( $count_terms, $ctx );
                        if (! $count_children ) $unlink = true;
                    }
                }
            }
            $date_based = $urlmapping->date_based;
            $link_status = $urlmapping->link_status;
            $key = '';
            $ctx->stash( 'current_urlmapping', $urlmapping );
        } else {
            $ui = $db->model( 'urlinfo' )->get_by_key(
              ['file_path' => $file_path, 'delete_flag' => ['IN' => [0, 1] ] ] );
            $old_uis = [];
            if (! $ui->id ) {
                $old_uis = $db->model( 'urlinfo' )->load(
                    ['model' => $obj->_model, 'object_id' => $obj->id, 'key' => $key,
                     'delete_flag' => ['IN' => [0, 1] ],
                     'class' => ['IN' => ['file', 'thumbnail'] ] ] );
            } else if ( $ui->delete_flag ) {
                $old_uis = $db->model( 'urlinfo' )->load(
                    ['model' => $obj->_model, 'object_id' => $obj->id, 'key' => $key,
                     'delete_flag' => 0,
                     'class' => ['IN' => ['file', 'thumbnail'] ] ] );
            }
            if ( count( $old_uis ) ) {
                foreach ( $old_uis as $old_ui ) {
                    $old_ui->remove();
                }
            }
        }
        $ui->delete_flag( 0 );
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
        if ( mb_substr( $base_url, -1 ) != '/' ) {
            $base_url .= '/';
        }
        $search = preg_quote( $base_path, '/' );
        $relative_path = preg_replace( "/^{$search}\//", '', $file_path );
        $url = $base_url . $relative_path;
        $url = str_replace( DS, '/', $url );
        $relative_path = str_replace( '/', DS, $relative_path );
        $relative_url = preg_replace( '!^https{0,1}:\/\/.*?\/!', '/', $url );
        $orig_url = $ui->url;
        if (! $mime_type ) $mime_type =  PTUtil::get_mime_type( $url );
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
                          'workspace_id' =>
                          $urlmapping ? $urlmapping->workspace_id : $obj->workspace_id ] );
        if ( isset( $archive_date ) ) $ui->archive_date( $archive_date );
        if ( $orig_url != $url ) {
            $ui->save();
        }
        if ( $obj->has_column( 'status' ) ) {
            $status_published = $app->status_published( $obj->_model );
            if ( $obj->status != $status_published ) {
                $unlink = true;
            }
        }
        $ctx->stash( 'current_urlinfo', $ui );
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
                            'object_id' => $obj->id, 'model' => $obj->_model,
                            'kind' => 'thumbnail'], $args );
                        foreach ( $thumbnails as $thumb ) {
                            $args = $thumb->data;
                            $properties = $thumb->metadata;
                            $args = $args ? unserialize( $args ) : null;
                            $properties = $properties ? unserialize( $properties ) : null;
                            if (! empty( $args ) ) 
                                $url = PTUtil::thumbnail_url( $obj, $args, $properties );
                            $thumb_path = $thumb->urlinfo_file_path;
                            $md5 = $thumb->urlinfo_md5;
                            $data = $thumb->blob;
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
                        $file_size = strlen( bin2hex( $data ) ) / 2;
                        if ( $md5 && $md5 == $hash && file_exists( $file_path )
                            && filesize( $file_path ) == $file_size ) {
                            if ( $ui->is_published != 1 || !$ui->md5 ) {
                                $ui->md5( $hash );
                                $ui->is_published( 1 );
                                $ui->save();
                            }
                            return $file_path;
                        }
                        if ( $fmgr->put( $file_path, $data ) !== false ) {
                            $ui->md5( $hash );
                            $ui->is_published( 1 );
                            $ui->publish_file( 1 );
                        } else {
                            $ui->is_published( 0 );
                            $ui->publish_file( 0 );
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
                        if (! $template && $urlmapping && $urlmapping->id &&
                            $urlmapping->template_id ) {
                            $template = $app->db->model('template')->load(
                                (int) $urlmapping->template_id );
                            if (! $template ) return;
                        }
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
                            $container_model = $urlmapping->container;
                            $container_obj =
                                $app->db->model( $urlmapping->container )->new();
                            $date_col = $app->get_date_col( $container_obj );
                            $ctx->stash( 'archive_date_based_col', $date_col );
                            $ts = $ui->db2ts( $ui->archive_date );
                            list( $title, $start, $end )
                                = $app->title_start_end( $date_based, $ts, $urlmapping );
                            $ctx->stash( 'current_archive_title', $title );
                            $count_terms = [ $date_col => ['BETWEEN' => [ $start, $end ] ] ];
                            if ( $container_obj->has_column( 'rev_type' ) ) {
                                $count_terms['rev_type'] = 0;
                            }
                            if ( $container_obj->has_column( 'status' ) ) {
                                $status_published = $app->status_published( $container_model );
                                $count_terms['status'] = $status_published;
                            }
                            $callback = ['name' => 'publish_date_based',
                                         'model' => $container_model ];
                            $app->run_callbacks( $callback, $container_model, $count_terms );
                            $container_count = $container_obj->count( $count_terms );
                            if (! $container_count ) {
                                if ( $fmgr->exists( $file_path ) ) {
                                    $fmgr->unlink( $file_path );
                                    $remove_dirs[ dirname( $file_path ) ] = true;
                                    $fmgr->remove_empty_dirs( $remove_dirs );
                                }
                                $ui->remove();
                                return;
                            }
                            $ctx->stash( 'archive_date_based',
                                $ctx->stash( 'current_container' ) );
                            $ctx->stash( 'current_timestamp', $start );
                            $ctx->stash( 'current_timestamp_end', $end );
                        } else {
                            $ctx->stash( 'current_timestamp', null );
                            $ctx->stash( 'current_timestamp_end', null );
                            $ctx->stash( 'archive_date_based', false );
                            if ( $obj->_model == 'template' ) {
                                $ctx->stash( 'current_archive_type', 'index' );
                                $title = $obj->name;
                            } else {
                                $tPrimary = $table->primary;
                                $title = $obj->$tPrimary;
                                $ctx->stash( 'current_archive_type', $obj->_model );
                            }
                            $ctx->stash( 'current_archive_title', $title );
                        }
                        if (! $ui->archive_type ) {
                            $ui->archive_type( $ctx->stash( 'current_archive_type' ) );
                            $ui->save();
                        }
                        $ctx->vars = [];
                        if (! $theme_static = $app->theme_static ) {
                            $theme_static = $app->path . 'theme-static/';
                            $app->theme_static = $theme_static;
                        }
                        $ctx->vars['current_archive_title'] =
                            $ctx->stash( 'current_archive_title' );
                        $ctx->vars['theme_static'] = $theme_static;
                        $ctx->vars['application_dir'] = __DIR__;
                        $ctx->vars['application_path'] = $app->path;
                        $ctx->vars['current_archive_type'] =
                            $ctx->stash( 'current_archive_type' );
                        $ctx->vars['current_archive_url'] = $url;
                        $ctx->stash( 'current_archive_url', $url );
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
                        $ctx->stash( 'current_urlinfo', $ui );
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
                            if (! $continue ) {
                                return $file_path;
                            }
                        } else {
                            $ctx->stash( 'current_object', $obj );
                            // if ( stripos( $tmpl, 'setvartemplate' ) !== false ) {
                            //     $ctx->compile( $tmpl, false );
                            // }
                            $ctx->vars['publish_type'] = $urlmapping ? $urlmapping->publish_file : 1;
                            if ( $app->publish_callbacks ) {
                                $app->init_callbacks( 'template', 'pre_publish' );
                                $callback = ['name' => 'pre_publish', 'model' => 'template',
                                             'urlmapping' => $urlmapping, 'template' => $template,
                                             'urlinfo' => $ui, 'object' => $obj ];
                                $res = $app->run_callbacks( $callback, 'template', $tmpl );
                                if (! $res ) return $file_path;
                            }
                            $data = $app->tmpl_markup === 'mt' ? $ctx->build( $tmpl )
                                                               : $app->build( $tmpl, $ctx );
                            if ( $app->publish_callbacks ) {
                                $app->init_callbacks( 'template', 'post_rebuild' );
                                $callback['name'] = 'post_rebuild';
                                $app->run_callbacks( $callback, 'template', $tmpl, $data );
                            }
                            $old_hash = $ui->md5;
                            $hash = md5( $data );
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
                            if ( $fmgr->put( $file_path, $data )!== false ) {
                                $ui->md5( $hash );
                                $ui->is_published( 1 );
                            } else {
                                $ui->is_published( 0 );
                                $ui->publish_file( 0 );
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
        if ( count( $remove_dirs ) ) $fmgr->remove_empty_dirs( $remove_dirs );
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
            if (!$mapping->date_based && in_array( $id, $published_ids ) ) {
                continue;
            }
            $dependencie = $app->db->model( $to_obj )->load( $id );
            if ( is_object( $dependencie ) ) {
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
                    $y = substr( $ts, 0, 4 );
                    $m = substr( $ts, 4, 2 );
                    if ( $orig_ts != $ts ) {
                        $orig_y = substr( $orig_ts, 0, 4 );
                        $orig_m = substr( $orig_ts, 4, 2 );
                    } else {
                        $orig_ts = null;
                    }
                    if ( $date_based == 'Fiscal-Yearly' ) {
                        $fy_start = $mapping->fiscal_start;
                        if ( $m < $fy_start ) {
                            $y--;
                        }
                        if ( strlen( $fy_start ) == 1 ) {
                            $fy_start = '0' . $fy_start;
                        }
                        $ts = "{$y}{$fy_start}01000000";
                        if ( $orig_ts && $orig_ts != $ts ) {
                            if ( $orig_m < $fy_start ) {
                                $orig_y--;
                            }
                            $orig_ts = "{$orig_y}{$fy_start}01000000";
                        }
                    } else if ( $date_based == 'Yearly' ) {
                        $ts = "{$y}0101000000";
                        if ( $orig_ts && $orig_ts != $ts ) {
                            $orig_ts = "{$orig_y}0101000000";
                        }
                    } else if ( $date_based == 'Monthly' ) {
                        $ts = "{$y}{$m}01000000";
                        if ( $orig_ts && $orig_ts != $ts ) {
                            $orig_ts = "{$orig_y}{$orig_m}01000000";
                        }
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
                    if ( $orig_ts ) {
                        $orig_path = $app->build_path_with_map
                                                ( $dependencie, $mapping, $table, $orig_ts );
                        $app->publish( $orig_path, $dependencie, $mapping, null, $orig_ts );
                    }
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
            if ( mb_substr( $url, -1 ) != '/' ) $url .= '/';
            $obj->site_url( $url );
        }
        $path = $obj->site_path;
        $path = rtrim( $path, DS );
        $obj->site_path( $path );
        return true;
    }

    function get_user_opt ( $key, $kind, $workspace_id = null ) {
        $app = $this;
        if ( $app->stash( 'user_options' ) ) return $app->stash( 'user_options' );
        $user = $app->user();
        $terms = ['user_id' => $user->id, 'key' => $key , 'kind' => $kind ];
        $workspace_id = (int) $workspace_id;
        $terms['workspace_id'] = $workspace_id;
        $list_option = $app->db->model( 'option' )->get_by_key( $terms );
        $app->stash( 'user_options', $list_option );
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
        $lang = $app->user() ? $app->user()->language : $app->language;
        $locale[ $lang ] = [];
        $scheme['version'] = $table->version;
        if ( $table->do_not_output ) {
            $scheme['do_not_output'] = $table->do_not_output;
        } else {
            unset( $scheme['do_not_output'] );
        }
        $scheme['label'] = $table->label;
        $scheme['plural'] = $table->plural;
        $locale[ $lang ][ $table->label ] = $app->translate( $table->label );
        $locale[ $lang ][ $table->plural ] = $app->translate( $table->plural );
        $relations = [];
        $col_options = [];
        $col_extras = [];
        $translates = [];
        $hints = [];
        $disp_edit = [];
        foreach ( $columns as $column ) {
            $col_name = $column->name;
            $props = [];
            $props['type'] = $column->type;
            if ( $column->type == 'relation' ) {
                $options = $column->options;
                if (! $options ) {
                    $options = $column->edit ? $column->edit : $column->list;
                    if (! $options ) continue;
                    $options = explode( ':', $options );
                    $options = isset( $options[1] ) ? $options[1] : '';
                    if (! $options ) continue;
                    $column->options( $options );
                    $column->save();
                }
                $relations[ $col_name ] = $options;
            } else if ( $column->options ) {
                $col_options[ $col_name ] = $column->options;
            }
            if ( $column->extra ) {
                $col_extras[ $col_name ] = $column->extra;
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
                    'max_revisions', 'allow_comment', 'default_status', 'has_form',
                    'show_activity', 'has_uuid', 'has_assets', 'has_attachments'];
        foreach ( $options as $option ) {
            if ( $table->$option ) $scheme[ $option ] = (int) $table->$option;
        }
        if ( $table->display_system )
            $scheme['display_system'] = 1;
        if ( $table->has_attachments )
            $scheme['has_attachments'] = 1;
        if ( $table->can_duplicate )
            $scheme['can_duplicate'] = 1;
        $scheme['column_defs'] = $column_defs;
        if (! empty( $translates ) ) $scheme['translate'] = $translates;
        if (! empty( $hints ) ) $scheme['hint'] = $hints;
        if (! empty( $relations ) ) $scheme['relations'] = $relations;
        if (! empty( $col_options ) ) $scheme['options'] = $col_options;
        if (! empty( $col_extras ) ) $scheme['extras'] = $col_extras;
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
        if (!$app->user() && !$app->current_magic ) $is_valid = false;
        $token = $app->param( 'magic_token' );
        if (!$token || $token !== $app->current_magic ) $is_valid = false;
        if (!$is_valid ) {
            if ( $json ) {
                $app->json_error( 'Invalid request.' );
            }
            return $app->error( 'Invalid request.' );
        }
        return true;
    }

    function recover_password ( $model = 'user' ) {
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
                $user = $app->db->model( $model )->load( $session->user_id );
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
                $user = $app->db->model( $model )->load( ['email' => $email ] );
                if ( count( $user ) ) {
                    $user = $user[0];
                    $session_id = $app->magic();
                    $ctx = $app->ctx;
                    $ctx->vars['token'] = $session_id;
                    $app->set_mail_param( $ctx );
                    $subject = null;
                    $body = null;
                    $template = null;
                    $body = $app->get_mail_tmpl( 'recover_password', $template );
                    if ( $template ) {
                        $subject = $template->subject;
                    }
                    if (! $subject ) {
                        $subject = $app->translate( 'Password Recovery' );
                    }
                    $body = $app->build( $body );
                    $subject = $app->build( $subject );
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

    function set_config ( $cfgs, $workspace_id = 0 ) {
        $app = $this;
        foreach ( $cfgs as $key => $value ) {
            $option = $app->db->model( 'option' )->get_by_key(
                ['key' => $key, 'kind' => 'config', 'workspace_id' => $workspace_id ] );
            $option->value( $value );
            $option->save();
        }
        // $app->clear_cache( 'configs__c' );
    }

    function get_config ( $key = null, $workspace_id = 0 ) {
        $app = $this;
        if (!$key )
            return $app->db->model( 'option' )->load(
              ['kind' => 'config', 'workspace_id' => $workspace_id ] );
        $config_name = $workspace_id ? 'configs_' . $workspace_id : 'configs';
        $configs = $app->stash( $config_name );
        if ( $configs && isset( $configs[ $key ] ) ) return $configs[ $key ];
        $cfg = $app->db->model( 'option' )->get_by_key(
            ['kind' => 'config', 'key' => $key, 'workspace_id' => $workspace_id ] );
        return $cfg->id ? $cfg : null;
    }

    function assign_params ( $app, $ctx, $raw = false ) {
        $params = $app->param();
        $prefix = is_string( $raw ) ? $raw : 'forward_';
        $raw = is_bool( $raw ) ? $raw : false;
        foreach( $params as $key => $value ) {
            if ( $raw ) $ctx->vars[ $key ] = $value;
            $ctx->__stash[ $prefix . $key ] = $value;
            $ctx->vars[ $prefix . $key ] = $value;
            if ( preg_match( "/(^.*)_date$/", $key, $mts ) ) {
                $name = $mts[1];
                if ( $time = $app->param( $name . '_time' ) ) {
                    $ctx->__stash[ $prefix . $name ] = $value . $time;
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
        $obj_id = is_object( $obj ) ? $obj->id : (int) $app->param('id');
        $ctx = $app->ctx;
        $session = $ctx->stash( 'current_session_' . $name );
        if ( $property === 'url' || $property === 'relative_path' ) {
            if (! $session ) {
                if ( isset( $obj->__session ) ) {
                    $session = $obj->__session;
                }
            }
            if ( $session ) {
                $screen_id = $app->param( '_screen_id' );
                $params = '?__mode=view&amp;_type=edit&amp;_model=' . $model;
                $params .= '&amp;id=' . $obj_id . '&amp;view=' . $name 
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
                return $fi->$property;
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
        if (! $data ) {
            $name = strtolower( $name );
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
        $data = is_string( $data ) ? json_decode( $data, true ) : $data;
        if ( $property === 'all' ) {
            return $data;
        }
        if ( isset( $data[ $property ] ) ) {
            return $data[ $property ];
        }
        return null;
    }

    function status_published ( $model ) {
        if ( is_object( $model ) ) $model = $model->_model;
        $app = $this;
        $status = $app->stash( 'status_published:' . $model );
        if ( isset( $status ) && $status ) return $status;
        $scheme = $app->db->scheme;
        if (! isset( $scheme[ $model ] ) ) {
            $app->get_scheme_from_db( $model );
            $scheme = $app->db->scheme;
        }
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

    function get_relations ( $obj, $to_obj = null, $name = null, $args = [] ) {
        if (! $obj->id ) return [];
        $app = $this;
        if (!$obj ) return [];
        $terms = ['from_id'  => $obj->id, 
                  'from_obj' => $obj->_model ];
        if ( $to_obj ) $terms['to_obj'] = $to_obj;
        if ( $name ) $terms['name'] = $name;
        if (! isset( $args['sort'] ) ) {
            $args['sort'] = 'order';
        }
        $relations = $app->db->model( 'relation' )->load( $terms, $args );
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
            } else {
                $scheme = $app->get_scheme_from_db( $obj->_model );
                $column_defs = $scheme['column_defs'];
                $edit_properties = $scheme['edit_properties'];
                foreach ( $column_defs as $col => $prop ) {
                    if ( $col == 'id' || $prop['type'] != 'int'
                      || !isset( $edit_properties[ $col ] ) ) continue;
                    if ( strpos( $edit_properties[$col], ':' ) === false ) continue;
                    $col_props = explode( ':', $edit_properties[ $col ] );
                    if ( $col_props[0] == 'relation' && $col_props[1] == $to_obj ) {
                        if ( $obj->$col ) {
                            $rel_obj = $app->db->model( $to_obj )->load( (int) $obj->$col );
                            if ( is_object( $rel_obj ) ) {
                                $relation = $app->db->model( 'relation' )->get_by_key(
                                    ['from_id'  => $obj->id, 
                                     'from_obj' => $obj->_model,
                                     'to_obj'   => $to_obj,
                                     'to_id'    => $rel_obj->id ] );
                                $relations = [ $relation ];
                            }
                        }
                    }
                }
            }
        }
        return $relations;
    }

    function get_related_objs ( $obj, $to_obj, $name = null,
                                $get_args = [], $terms = [], $select_cols = '*' ) {
        $app = $this;
        if (!$obj ) return [];
        $model = $obj->_model;
        $id = $obj->id;
        $extra = " AND relation_from_obj='{$model}' AND relation_from_id={$id}"
               . " AND relation_to_obj='{$to_obj}' ";
        $args = ['join' => ['relation', ['id', 'to_id'] ], 'distinct' => 1];
        if ( $name ) {
            $name = $app->db->quote( $name );
            $extra .= " AND relation_name={$name} ";
        }
        $rel_model = $app->db->model( $to_obj );
        if ( $rel_model->has_column( 'status' ) ) {
            $published_only = isset( $get_args['published_only'] ) ?
                $get_args['published_only'] : false;
            if ( $published_only ) {
                $status_published = $app->status_published( $to_obj );
                $terms['status'] = $status_published;
            }
            unset( $get_args['published_only'] );
        }
        if (! empty( $get_args ) ) {
            foreach ( $get_args as $arg => $v ) {
                $args[ $arg ] = $v;
            }
        }
        $to_obj = $app->db->model( $to_obj );
        $scheme = $app->get_scheme_from_db( $to_obj->_model );
        if ( $select_cols != '*' ) {
            $column_defs = $scheme['column_defs'];
            $select_cols = explode( ',', strtolower( $select_cols ) );
            $_select_cols = [];
            foreach ( $select_cols as $select_col ) {
                $select_col = trim( $select_col );
                if ( $to_obj->has_column( $select_col ) ) {
                    if ( isset( $column_defs[ $select_col ] ) 
                        && isset( $column_defs[ $select_col ]['type'] ) ) {
                        if ( $column_defs[ $select_col ]['type'] != 'relation' ) {
                            $_select_cols[] = $select_col;
                        }
                    }
                }
                if (!in_array( 'id', $_select_cols ) ) {
                    array_unshift( $_select_cols, 'id' );
                }
                if ( $to_obj->has_column( 'status' ) &&
                    !in_array( 'status', $_select_cols ) ) {
                    $_select_cols[] = 'status';
                }
            }
            $select_cols = !empty( $_select_cols )
                         ? implode( ',', $_select_cols )
                         : '*';
        }
        if ( isset( $args['sort'] ) && $args['sort']
            && $to_obj->has_column( $args['sort'] ) ) {
        } else {
            $extra .= 'ORDER BY relation_order ';
            if ( isset( $args['direction'] ) && $args['direction']
                && ( $args['direction'] == 'ascend' || $args['direction'] == 'descend' ) ) {
                if ( $args['direction'] == 'ascend' ) {
                    $extra .= 'ASC ';
                } else {
                    $extra .= 'DESC ';
                }
            } else {
                $extra .= 'ASC ';
            }
        }
        $model = $to_obj->_model;
        $orig_args = $get_args;
        $table = $app->get_table( $model );
        $app->register_callback( $model, 'pre_listing', 'pre_listing', 1, $app );
        $app->init_callbacks( $model, 'pre_listing' );
        $callback = ['name' => 'pre_listing', 'model' => $model,
                     'scheme' => $scheme, 'table' => $table,
                     'args' => $orig_args ];
        $app->run_callbacks( $callback, $model, $terms, $args, $extra );
        $objects = $to_obj->load( $terms, $args, $select_cols, $extra );
        if (! count( $objects ) ) {
            $id_col = "{$model}_id";
            if ( $obj->has_column( $id_col ) && $obj->$id_col ) {
                $relation = $obj->$model;
                if ( is_object( $relation ) ) {
                    $objects = [ $relation ];
                }
            }
        }
        $app->init_callbacks( $model, 'post_load_objects' );
        $callback = ['name' => 'post_load_objects', 'model' => $model,
                     'table' => $table ];
        $count_obj = count( $objects );
        $app->run_callbacks( $callback, $model, $objects, $count_obj );
        return $objects;
    }

    function get_meta ( &$obj, $kind = null, $key = null, $name = null ) {
        if (! $obj->id ) return [];
        $app = $this;
        if ( $kind == 'customfield' && $obj->_customfields !== null && $key ) {
            return $obj->_customfields;
        }
        $terms = ['object_id'  => $obj->id, 
                  'model' => $obj->_model ];
        if ( $kind ) $terms['kind'] = $kind;
        if ( $key )  $terms['key']  = $key;
        if ( $name ) $terms['name'] = $name;
        $args = ['sort' => 'number', 'direction' => 'ascend'];
        $meta = $app->db->model( 'meta' )->load( $terms );
        if ( $kind == 'customfield' ) {
            $customfields = [];
            foreach ( $meta as $field ) {
                if ( isset( $customfields[ $field->key ] ) ) {
                    $customfields[ $field->key ][] = $field;
                } else {
                    $customfields[ $field->key ] = [ $field ];
                }
            }
            $obj->_customfields = $customfields;
            if ( $key ) return $customfields;
        }
        if (! $app->db->blob2file ) {
            return $meta;
        }
        $metadata = [];
        foreach ( $meta as $m ) {
            $m->blob( $m->blob );
            $m->data( $m->data );
            $m->metadata( $m->metadata );
            $metadata[] = $m;
        }
        return $metadata;
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
            if ( $obj->status === '0' ) {
                $obj->status( 0 );
            } else { 
                $table = $app->get_table( $obj->_model );
                $obj->status( $table->default_status );
            }
        }
        if ( $obj->has_column( 'uuid' ) ) {
            if (! $obj->uuid && ! $obj->id ) {
                $obj->uuid( $this->generate_uuid( $obj->_model ) );
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
        if ( $obj->has_column( 'order' ) && ! $obj->order ) {
            $order_terms = [];
            if ( $obj->has_column( 'workspace_id' ) ) {
                $order_terms['workspace_id'] = $obj->workspace_id ? $obj->workspace_id : 0;
            }
            if ( $obj->has_column( 'rev_type' ) ) {
                $order_terms['rev_type'] = 0;
            }
            $max_order_obj = $app->db->model( $obj->_model )->load(
                $order_terms, ['sort' => 'order', 'direction' => 'descend', 'limit' => 1],
                               'id,order'
            );
            if ( count( $max_order_obj ) ) {
                $max_order_obj = $max_order_obj[0];
                $obj->order( $max_order_obj->order + 1 );
            } else {
                $obj->order( 1 );
            }
        }
        if ( $obj->has_column( 'basename' ) ) {
            if ( $obj->has_column( 'rev_type' ) && $obj->rev_type ) {
                return;
            }
            $basename = PTUtil::make_basename( $obj, $obj->basename, true );
            $obj->basename( $basename );
        }
    }

    function translate ( $phrase, $params = '', $component = null, $lang = null ) {
        $component = $component ? $component : $this;
        if (! $lang ) {
            $lang = $this->user() ? $this->user()->language : $this->language;
        }
        if (!$lang ) $lang = 'default';
        $dict = isset( $component->dictionary ) ? $component->dictionary : null;
        if ( $dict && isset( $dict[ $lang ] ) && isset( $dict[ $lang ][ $phrase ] ) )
             $phrase = $dict[ $lang ][ $phrase ];
        // if ( is_string( $params ) && $params ) $params = htmlspecialchars( $params );
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

    function is_login ( $model = 'user' ) {
        $app = $this;
        if ( $app->stash( 'logged-in' ) ) return true;
        $cookie = $app->cookie_val( $app->cookie_name );
        if (!$cookie ) return false;
        $sess = $app->db->model( 'session' )->load(
            ['name' => $cookie, 'kind' => 'US', 'key' => $model ] );
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
            $user = $app->db->model( $model )->load( $sess->user_id );
            if ( is_object ( $user ) ) {
                if ( $app->always_update_login ) {
                    $expires = time() + $app->sess_timeout;
                    if ( $sess->expires < $expires ) {
                        $sess->expires( $expires );
                        $sess->save();
                    }
                    $user->last_login_on( date( 'YmdHis' ) );
                    $user->last_login_ip( $app->remote_ip );
                    $user->save();
                }
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
        } else if ( is_array( $message['metadata'] ) ) {
            $message['metadata'] = json_encode( $message['metadata'],
                                        JSON_UNESCAPED_UNICODE );
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
        $expires = intval( $expires );
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
        if ( $params && is_bool( $params ) ) {
            $this->ctx->vars['iframe'] = true;
        }
        $this->ctx->vars['error'] = 
            $this->translate( $message, $params, $component, $lang );
        $this->ctx->vars['page_title'] = $this->translate( 'An error occurred' );
        $this->__mode = 'error';
        // TODO Log
        $this->__mode( 'error' );
    }

    function json_error ( $message,
        $params = null, $status = 200, $component = null, $lang = null ) {
        if ( $status == 403 ) {
            header( $this->protocol. ' 403 Forbidden' );
        } else if ( $status == 404 ) {
            header( $this->protocol. ' 404 Not Found' );
        } else {
            header( $this->protocol. " {$status}" );
        }
        $message = 
            $this->translate( $message, $params, $component, $lang );
        header( 'Content-type: application/json' );
        echo json_encode( [
            'status' => $status,
            'message' => $message ] );
        exit();
    }

    function rollback ( $message = null, $params = null, $component = null, $lang = null ) {
        $this->db->rollback();
        $this->txn_active = false;
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
        if (! isset( $_REQUEST ) ) {
            return '';
        }
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
        return '';
    }

    function is_valid_email ( $value, &$msg ) {
        if ( preg_match( '/^.*?<(.*?\@.*)>$/', $value, $m ) ) {
            $value = $m[1];
        }
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
            $max_length = 30 - strlen( DB_PREFIX );
            if ( strlen( $prop ) > $max_length ) {
                $msg = $app->translate(
                'The Model or Column name must be %s characters or less.', $max_length );
                return false;
            }
        }
        return true;
    }

    function is_valid_url ( $url, &$msg = '' ) {
        if (
            preg_match( '/^https?:\/\/([-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)$/',
                $url, $matches ) ) {
            if ( strpos( '//', $matches[1] ) === false ) {
                return true;
            }
        }
        $app = $this;
        $msg = $app->translate(
                    'Please enter a valid URL.' );
        return false;
    }

    function is_valid_password ( $pass, $verify = null, &$msg = '' ) {
        $app = $this;
        if ( isset( $verify ) && $pass !== $verify ) {
            $msg = $app->translate( 'Both passwords must match.' );
            return false;
        }
        $min = $app->password_min;
        $len = mb_strlen( $pass );
        $error = false;
        if ( $min ) {
            if ( $len < $min ) {
                $msg = $app->translate(
                'Password should be longer than %s characters.', $min );
                $error = true;
            }
        }
        if ( $app->password_symbol ) {
            if (! preg_match( '/[!"#$%&\'\(\|\)\*\+,-\.\/\\:;<=>\?@\[\]^_`{}~]/', $pass ) ) {
                $msg .= $msg ? ' ' : '';
                $msg .= $app->translate( 'Password should contain symbols.' );
                $error = true;
            }
        }
        if ( $app->password_letternum ) {
            if ( preg_match( '/[a-zA-Z]/', $pass ) && preg_match( '/[0-9]/', $pass ) ) {
            } else {
                $msg .= $msg ? ' ' : '';
                $msg .= $app->translate( 'Password should include letters and numbers.' );
                $error = true;
            }
        }
        if ( $app->password_upperlower ) {
            if ( preg_match( '/[a-z]/', $pass ) && preg_match( '/[A-Z]/', $pass ) ) {
            } else {
                $msg .= $msg ? ' ' : '';
                $msg .= $app->translate( 'Password should include lowercase and uppercase letters.' );
                $error = true;
            }
        }
        if ( $error ) return false;
        return true;
    }

    function sanitize_dir ( &$path ) {
        if ( $path ) {
            if ( preg_match( '/^\/{1,}/', $path ) ) {
                $path = preg_replace( '/^\/{1,}/', '', $path );
            }
            if (! preg_match( '/\/$/', $path ) ) $path .= '/';
        }
        return $path;
    }

    function escape ( $str, $kind = 'html' ) {
        if ( strtolower( $kind ) == 'html' && is_string( $str ) ) {
            return htmlspecialchars( $str );
        }
        $func = 'htmlspecialchars';
        $func_map = ['url' => 'rawurlencode', 'uri' => 'rawurlencode',
                     'xml' => 'prototype_escape_xml', 'js' => 'prototype_escape_js',
                     'javascript' => 'prototype_escape_js', 'sql' => 'prototype_escape_sql',
                     'shell' => 'escapeshellarg', 'shellarg' => 'escapeshellarg', 
                     'shellcmd' => 'escapeshellcmd', 'php' => 'addslashes',
                     'regex' => 'prototype_escape_regex', 'preg' => 'prototype_escape_regex'];
        if ( isset( $func_map[ strtolower( $kind ) ] ) ) {
            $func = $func_map[ strtolower( $kind ) ];
        }
        if ( is_string( $str ) ) {
            return $func( $str );
        } else if ( is_array( $str ) ) {
            return array_map( $func, $str );
        }
        return $str;
    }

    function print ( $data, $mime_type = 'text/html', $ts = null, $do_conditional = false ) {
        if ( $this->do_conditional && ( $ts && ! $this->debug && !$this->query_string() ) ) {
            $if_modified  = isset( $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] )
                ? strtotime( stripslashes( $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] ) ) : false;
            $if_nonematch = isset( $_SERVER[ 'HTTP_IF_NONE_MATCH' ] )
                ? stripslashes( $_SERVER[ 'HTTP_IF_NONE_MATCH' ] ) : false;
            $conditional = false;
            $last_modified = gmdate( "D, d M Y H:i:s", $ts ) . ' GMT';
            $etag = '"' . md5( $last_modified ) . '"';
            if ( $if_nonematch && ( $if_nonematch == $etag ) ) {
                $conditional = 1;
            }
            if ( $if_modified && ( $if_modified >= $ts ) ) {
                $conditional = 1;
            }
            if ( $this->request_method == 'POST' ) {
                $conditional = 0;
            }
            if ( $conditional ) {
                header( "Last-Modified: $last_modified" );
                header( "ETag: $etag" );
                header( $this->protocol . ' 304 Not Modified' );
                exit();
            }
            if ( $data === null ) {
                return;
            }
        }
        if ( $this->output_compression ) {
            ini_set( 'zlib.output_compression', 'On' );
        }
        if ( $do_conditional ) return;
        header( "Content-Type: {$mime_type}" );
        $file_size = strlen( bin2hex( $data ) ) / 2;
        header( "Content-Length: {$file_size}" );
        if ( $ts ) {
            $last_modified = gmdate( "D, d M Y H:i:s", $ts ) . ' GMT';
            $etag = '"' . md5( $data ) . '"';
            header( "Last-Modified: $last_modified" );
            header( "ETag: $etag" );
        }
        echo $data;
        unset( $data );
        exit();
    }

    function debugPrint ( $msg ) {
        echo '<hr><pre>', $this->escape( $msg ), '</pre><hr>';
    }

    function errorHandler ( $errno, $errmsg, $f, $line ) {
        if (!ini_get( 'error_reporting' ) ) return;
        $q = $this->query_string( true );
        $q = preg_replace( "/(^.*?)\n.*$/si", "$1", $q );
        if ( $tmpl = $this->ctx->template_file ) $errmsg = " $errmsg( in {$tmpl} )";
        $msg = "{$errmsg} ({$errno}) occured( line {$line} of {$f} ). {$q}";
        $this->errors[] = $msg;
        if ( $this->debug === 2 ) $this->debugPrint( $msg );
        if ( $this->logging ) error_log( date( 'Y-m-d H:i:s T', time() ) .
            "\t" . $msg . "\n", 3, $this->log_dir . DS . 'error.log' );
    }

}

function prototype_escape_xml ( $str ) {
    return htmlentities( $str, ENT_XML1 );
}

function prototype_escape_js ( $str ) {
    $str = json_encode( $str, JSON_UNESCAPED_UNICODE );
    if ( preg_match( '/^"(.*)"$/', $str, $matches ) ) {
        return $matches[1];
    }
    return $str;
}

function prototype_escape_sql ( $str ) {
    $app = Prototype::get_instance();
    return $app->db->quote( $str );
}

function prototype_escape_regex ( $str, $delimiter = '/' ) {
    return preg_quote( $str, $delimiter );
}
