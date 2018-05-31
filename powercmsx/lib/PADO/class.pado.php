<?php

/**
 * PADO : PHP Alternative Database Object
 *
 * @version    1.0
 * @package    PADO
 * @author     Alfasado Inc. <webmaster@alfasado.jp>
 * @copyright  2017 Alfasado Inc. All Rights Reserved.
 */
if (! defined( 'DS' ) ) {
    define( 'DS', DIRECTORY_SEPARATOR );
}
if (! defined( 'PADODIR' ) ) {
    define( 'PADODIR', __DIR__ . DS );
}

class PADO {

    private $version     = 1.0;

    public  static $pado = null;
    public  $driver      = 'mysql';
    public  $dbname      = '';
    public  $dbhost      = '';
    public  $dbuser      = '';
    public  $dbpasswd    = '';
    public  $dbport      =  3306;
    public  $dbcharset   = 'utf8mb4';
    public  $db;
    public  $dsn         = '';
    public  $max_packet  = null;
    public  $charset     = 'utf-8';
    public  $default_ts  = 'CURRENT_TIMESTAMP';
/**
 * Table prefix.
 */
    public  $prefix      = '';

/**
 * Column name prefix. You can specify wild card strings <table> or <model>.
 */
    public  $colprefix   = '';
/**
 * Index name prefix. You can specify wild card strings <table> or <model>.
 */
    public  $idxprefix   = '';

/**
 * Column name of Primary key.
 */
    public  $id_column   = 'id';

/**
 * $debug: 1.error_reporting( E_ALL ) / 2.debugPrint error. /3.debugPrint SQL statement.
 */
    public  $debug       = false;

/**
 * If specified migrate db from $pado->scheme[$model].
 */
    public  $upgrader    = false;

    public  $persistent  = false;

    public  $logging     = false;
    public  $caching     = true;
    public  $log_path;
    public  $scheme      = [];
    public  $methods     = [];
    public  $json_model  = true;
    public  $save_json   = false;
    public  $can_drop    = false;
    public  $base_model  = null;
    public static $stash = [];
    public  $cache       = [];
    public  $app         = null;
    public  $queries     = [];
    public  $query_cnt   = 0;
    public  $retry_cnt   = 0;
    public  $max_queries = 1500;
    public  $max_retries = 10;
    public  $retry_sleep = 1;
    public  $errors      = [];
    public  $blob_type   = 'LONGBLOB';
    public  $timeout     = 30;
    public  $models_json = [];
    public  $txn_active  = false;

    public  $callbacks   = [
          'pre_save'     => [], 'post_save'   => [],
          'pre_delete'   => [], 'post_delete' => [],
          'pre_load'     => [], 'save_filter' => [],
          'delete_filter'=> [] ];

    public  $last_cache_key;

/**
 * Initialize a PADO.
 *
 * @param array $config: Array for set class properties.
 *                          or properties to JSON file.
 */
    function __construct ( $config = [] ) {
        set_error_handler( [ $this, 'errorHandler'] );
        if ( ( $cfg_json = PADODIR . 'config.json' ) 
            && file_exists( $cfg_json ) ) $this->configure_from_json( $cfg_json );
        foreach ( $config as $key => $value ) $this->$key = $value;
    }

    function __destruct() {
        if (! $this->persistent ) {
            try {
                $this->db = null;
                unset( $this->db );
            } catch ( PDOException $e ) {
                $message = 'Connection close failed: ' . $e->getMessage();
                $this->errors[] = $message;
                trigger_error( $message );
                exit();
            }
        }
    }

/**
 * Initialize a Database Connection.
 *
 * @param array $config: Array for set class properties.
 */
    function init ( $config = [], $retry = false ) {
        if (! $retry ) {
            foreach ( $config as $key => $value ) $this->$key = $value;
            if ( $this->debug ) error_reporting( E_ALL );
        }
        $dsn = $this->dsn;
        $driver = '';
        if (! $dsn ) {
            $driver    = $this->driver;
            $dbname    = $this->dbname;
            $dbhost    = $this->dbhost;
            $dbport    = $this->dbport;
            $dbcharset = $this->dbcharset;
            $dsn = "{$driver}:host={$dbhost};dbname={$dbname};"
                 . "charset={$dbcharset};port={$dbport}";
            $this->dsn = $dsn;
        } else {
            list( $driver ) = explode( ':', $dsn );
            $this->driver = $driver;
        }
        $dbuser    = $this->dbuser;
        $dbpasswd  = $this->dbpasswd;
        if (! $driver ) return;
        $sql = '';
        try {
            $this->db = null;
            unset( $this->db );
            $options = $this->persistent ? [ PDO::ATTR_PERSISTENT => true ] : [];
            $options[ PDO::ATTR_TIMEOUT ] = $this->timeout;
            $pdo = new PDO( $dsn, $dbuser, $dbpasswd, $options );
            $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
            $this->db = $pdo;
            $this->retry_cnt = 0;
            $this->query_cnt = 0;
            if ( class_exists( 'PADO' . $driver ) ) {
                if ( $driver === 'mysql' && $this->max_packet ) {
                    $max_packet = (int) $this->max_packet;
                    if ( $max_packet ) {
                        $sql = "set global max_allowed_packet = {$max_packet}";
                        $sth = $pdo->prepare( $sql );
                        $sth->execute();
                        $this->queries[] = $sql;
                        $sth = null;
                    }
                }
                if (! $retry ) {
                    $class = 'PADO' . $driver;
                    $base_model = new $class;
                    $class = get_class( $base_model );
                    $this->base_model = $base_model;
                    $reflection = new ReflectionClass( $base_model );
                    $get_methods = $reflection->getMethods();
                    $methods = [];
                    foreach ( $get_methods as $method )
                        if ( $method->class === $class )
                            $methods[ $method->name ] = true;
                    $this->methods = $methods;
                }
            }
        } catch ( PDOException $e ) {
            $message = 'Connection failed: ' . $e->getMessage();
            $this->errors[] = $message;
            trigger_error( $message );
            $this->db = null;
            unset( $this->db );
            $this->retry_sleep = $this->retry_sleep * 2;
            sleep( $this->retry_sleep );
            if ( $this->retry_cnt > $this->max_retries ) {
                return die( 'Connection failed: ' . $e->getMessage() );
            }
            ++$this->retry_cnt;
            return $this->reconnect();
        }
        self::$pado = $this;
        return true;
    }

/**
 * Reconnect to db.
 *
 * @return bool   $success : true or false.
 */
    public function reconnect () {
        if ( $this->init( [], true ) ) {
            $this->query_cnt = 0;
            return $this->ping();
        }
        return false;
    }

    public function keep_connect ( $force = false ) {
        if ( $force ||
            ( $this->query_cnt > $this->max_queries && ! $this->txn_active ) ) {
            return $this->reconnect();
        }
    }

/**
 * Connect to db with minimum query.
 *
 * @param  string $sql     : SQL statement.
 * @return bool   $success : true or false.
 */
    public function ping ( $sql = 'SELECT 1' ) {
        try {
            $this->db->query( $sql );
            $this->queries[] = $sql;
            return true;
        } catch ( PDOException $e ) {
            $message = 'PDOException: ' . $e->getMessage() . ", {$sql}";
            $this->errors[] = $message;
            trigger_error( $message );
            sleep( 1 );
            if ( $this->retry_cnt > $this->max_retries ) {
                return false;
            }
            ++$this->retry_cnt;
            $this->reconnect();
            return;
        }
    }

/**
 * Get instance of class PADO($pado = PADO::get_instance();)
 * 
 * @return object $pado : Object class PADO.
 */
    static function get_instance () {
        return self::$pado;
    }

/**
 * Set properties from JSON.
 *
 * @param string $json: JSON file path.
 */
    function configure_from_json ( $json ) {
        if (!is_readable( $json ) ) return;
        $config = json_decode( file_get_contents( $json ), true );
        foreach ( $config as $k => $v ) $this->$k = $v;
    }

/**
 * Initializing the model.
 * When class exists model use it.
 * or PADO + driver name(e.g.PADOMySQL) use it.
 * Otherwise use PADOBaseModel.
 * 
 * @param  string $model : Name of model.
 * @return object $class : Class model object.
 */ 
    function model ( $model ) {
        if ( is_array( $model ) ) {
            $key = key( $model );
            $model = $model[ $key ];
        }
        if (! class_exists( $model ) ) {
            $lib = PADODIR . 'lib' . DS . 'class.' . $model . '.php';
            if ( is_readable( $lib ) ) include( $lib );
        }
        $class = class_exists( $model ) ? $model
               : ( class_exists( 'PADO' . $this->driver )
               ? 'PADO' . $this->driver : 'PADOBaseModel' );
        return new $class( $model, $this );
    }

/**
 * SHOW TABLES.
 * 
 * @param  string $model  : Name of model.
 * @param  bool   $create : Specify true, SHOW CREATE TABLE.
 * @return object $sth    : PDOStatement.
 */ 
    function show_tables ( $model = null, $create = false ) {
        $sql = 'SHOW TABLES';
        if ( $model ) {
            $illegals = PADOBaseModel::ILLEGALS;
            $model = str_replace( $illegals, '', $model );
            $model = $this->prefix . $model;
        }
        if ( $model && ! $create ) {
            $sql = "SHOW TABLES LIKE :model";
        } else if ( $model && $create ) {
            $sql = "SHOW CREATE TABLE {$model}";
        }
        if ( $this->query_cnt > $this->max_queries && ! $this->txn_active ) {
            $this->reconnect();
        }
        $pdo = $this->db;
        if (! $pdo ) {
            $this->init();
        }
        $sth = $pdo->prepare( $sql );
        if ( $model && ! $create ) {
            $sth->bindValue( ':model', $model, PDO::PARAM_STR );
        }
        try {
            $sth->execute();
            $this->queries[] = $model ? $sql . ' / values = ' . $model : $sql;
            ++$this->query_cnt;
        } catch ( PDOException $e ) {
            $message = 'PDOException: ' . $e->getMessage() . ", {$sql}";
            $this->errors[] = $message;
            trigger_error( $message );
        }
        return $sth;
    }

/**
 * Begin transaction.
 */
    function begin_work () {
        if ( $this->txn_active ) return;
        $this->txn_active = true;
        try {
            $this->db->beginTransaction();
        } catch ( Exception $e ) {
            $message = 'PADOException: ' . $e->getMessage();
            $this->errors[] = $message;
            trigger_error( $message );
            return false;
        }
    }

/**
 * Commit.
 */
    function commit () {
        try {
            $this->db->commit();
            $this->txn_active = false;
            return true;
        } catch ( Exception $e ) {
            $message = 'PADOException: ' . $e->getMessage();
            $this->errors[] = $message;
            trigger_error( $message );
            return false;
        }
    }

/**
 * Rollback.
 */
    function rollback () {
        $this->db->rollBack();
        $this->txn_active = false;
    }

/**
 * Register plugin callback.
 *
 * @param  string $model    : Name of model.
 * @param  string $kind     : Kind of callback (pre_save, post_save, pre_delete,
 *                            post_delete, save_filter, delete_filter or pre_load).
 * @param  string $meth     : Function or method name.
 * @param  int    $priority : Callback priority.
 * @param  object $class    : Plugin class object.
 */
    function register_callback ( $model, $kind, $meth, $priority, $obj = null ) {
        if (! $priority ) $priority = 5;
        $this->callbacks[ $kind ][ $model ][ $priority ][] = [ $meth, $obj ];
    }

/**
 * Run callbacks.
 *
 * @param  array  $cb     : An array of string callback name, string sql and array values.
 * @param  string $model  : Name of model.
 * @param  object $obj    : Model object.
 * @param  bool   $needle : If specified and save_filter or delete_filter callbacks
 *                          returns false, cancel it.
 */
    function run_callbacks ( &$cb, $model, &$obj, $needle = false ) {
        $cb_name = $cb['name'];
        $all_callbacks = [];
        if ( isset( $this->callbacks[ $cb_name ][ $model ] ) ) {
            $all_callbacks = $this->callbacks[ $cb_name ][ $model ];
        }
        if ( isset( $this->callbacks[ $cb_name ]['__any__'] ) ) {
            $all_callbacks =
                array_merge( $all_callbacks, $this->callbacks[ $cb_name ]['__any__'] );
        }
        if (! empty( $all_callbacks ) ) {
            ksort( $all_callbacks );
            foreach ( $all_callbacks as $callbacks ) {
                foreach ( $callbacks as $callback ) {
                    list( $meth, $class ) = $callback;
                    $res = true;
                    if ( function_exists( $meth ) ) {
                        $res = $meth( $cb, $this, $obj );
                    } elseif ( $class && method_exists( $class, $meth ) ) {
                        $res = $class->$meth( $cb, $this, $obj );
                    }
                    if ( $needle && !$res ) return false;
                }
            }
        }
        return true;
    }

/**
 * Load object.
 * 
 * @param  string $model   : Name of model.
 * @param  mixed  $terms   : Numeric ID or an array should have keys matching column
 *                           names and the values are the values for that column.
 * @param  array  $args    : Search options.
 * @param  string $cols    : Get columns from records. Comma-separated text or '*'.
 * @param  string $extra   : String to add to the WHERE statement.
 *                           Insufficient care is required for injection.
 * @return array  $objects : An array of objects or single object(Specified Numeric ID).
 */
    function load ( $model, $terms = [], $args = [], $cols = '', $extra = '' ) {
        return $this->model( $model )->load( $terms, $args, $cols, $extra );
    }

/**
 * Quotes a string for use in a query.
 * 
 * @param  string $str    : String to quote.
 * @return string $quoted : Quoted string.
 */
    function quote ( $str ) {
        return $this->db->quote( $str );
    }

/**
 * stash: Where the variable is stored.
 *
 * @param  string $name : Name of set or get variable to(from) stash.
 * @param  mixed  $value: Variable for set to stash.
 * @return mixed  $var  : Stored data.
 */
    function stash ( $name, $value = false, $var = null ) {
        if ( isset( self::$stash[ $name ] ) ) $var = self::$stash[ $name ];
        if ( $value !== false ) self::$stash[ $name ] = $value;
        return $var;
    }

/**
 * Quotes a string for like statement.
 * 
 * @param  string $str    : String to quote.
 * @param  bool   $start  : Add '%' before $str.
 * @param  bool   $end    : Add '%' after $str.
 * @return string $quoted : Quoted string.
 */
    function escape_like ( $str, $start = false, $end = false ) {
        $str = str_replace( '%', '\\%', $str );
        $str = str_replace( '_', '\\_', $str );
        $start = $start ? '%' : '';
        $end   = $end ? '%' : '';
        return $start . $str . $end;
    }

/**
 * Create a unique cache key.
 * 
 * @param              : See load method.
 * @return string $res : Unique cache key.
 */
    function make_cache_key ( $model, $terms = [], $args = [], $cols = '', $extra = '' ) {
        if ( is_array( $terms ) ) ksort( $terms );
        if ( is_array( $args ) ) ksort( $args );
        ob_start();
            print_r( $model );
            print_r( $terms );
            print_r( $args );
            print_r( $cols );
            print_r( $extra );
        $res = ob_get_clean();
        $res = md5( $res );
        $this->last_cache_key = $res;
        return $res;
    }

/**
 * Clear cached objects or valiable. If model is omitted, all caches are cleared.
 * 
 * @param  string $model : Name of model.
 */
    function clear_cache ( $model = null ) {
        if ( $model ) {
            $this->cache[ $model ] = [];
        } else {
            $this->cache = [];
            self::$stash = [];
        }
    }

/**
 * Drop Table
 * 
 * @param  string $model : Name of model.
 */
    function drop ( $model ) {
        if (! $this->can_drop ) return;
        $table = $this->prefix . $model;
        $_model = $this->model( $model )->new();
        if ( isset( $this->scheme[ $model ] )
            && is_array( $this->scheme[ $model ] ) && count( $this->scheme[ $model ] ) ) {
            $sql = "DROP TABLE {$table}";
            if ( $this->query_cnt > $this->max_queries && ! $this->txn_active ) {
                $this->reconnect();
            }
            $sth = $this->db->prepare( $sql );
            try {
                return $sth->execute();
                $this->queries[] = $sql;
                ++$this->query_cnt;
            } catch ( PDOException $e ) {
                $message = 'PDOException: ' . $e->getMessage() . ", {$sql}";
                $this->errors[] = $message;
                trigger_error( $message );
            }
            $sth = null;
        }
    }

/**
 * Display message.
 * 
 * @param  string $msg : Display text.
*/
    function debugPrint ( $msg ) {
        echo '<hr><pre>', htmlspecialchars( $msg ), '</pre><hr>';
    }

/**
 * Custom error handler.
*/
    function errorHandler ( $errno, $errmsg, $f, $line ) {
        $msg = "{$errmsg} ({$errno}) occured( line {$line} of {$f} ).";
        if ( $this->debug == 2 ) $this->debugPrint( $msg );
        if ( $this->logging && !$this->log_path ) $this->log_path = PADODIR . 'log' . DS;
        if ( $this->logging ) error_log( date( 'Y-m-d H:i:s T', time() ) .
            "\t" . $msg . "\n", 3, $this->log_path . 'error.log' );
    }
}

/**
 * PADOBaseModel : PADO Base Model
 *
 * @version    1.0
 * @package    PADO
 * @author     Alfasado Inc. <webmaster@alfasado.jp>
 * @copyright  2017 Alfasado Inc. All Rights Reserved.
 */
class PADOBaseModel {

    public $_model     = '';
    public $_table     = '';
    public $_pado      = null;
    public $_id_column = '';
    public $_colprefix = '';
    public $_scheme    = [];
    public $_driver    = null;

    public static $_Model     = '';
    public static $_ID_column = '';
    public static $_Colprefix = '';
    public static $_Scheme    = [];
    public static $_Driver    = null;

    const ILLEGALS = ["`" ,'+', '*', '/', '-', "'", ':', ';', '"', '\\', '|', "\0", "\x00"];
    const RESERVED = [
                       '_model'     => true, '_table'     => true,
                       '_pado'      => true, '_id_column' => true,
                       '_colprefix' => true, '_scheme'    => true,
                       '_driver'    => true, '_engine'    => true
                     ];

/**
 * Call from 'load' method or 'new' method.
 */
    function __construct ( $model = null, $pado = null ) {
        if (! $model ) {
            $model = self::$_Model;
            if (! $model ) return;
            $this->_id_column = self::$_ID_column;
            $colprefix = $this->_colprefix = self::$_Colprefix;
            $this->_scheme = self::$_Scheme;
            $this->_driver = self::$_Driver;
        }
        if (! $pado ) $pado = PADO::get_instance();
        $this->_model = $model;
        $this->_pado = $pado;
        $this->_table = $pado->prefix . $model;
        $this->_driver = $pado->base_model;
        $class = $this->_driver ? $this->_driver : $this;
        if (! isset( $colprefix ) ) {
            $colprefix = $pado->colprefix;
            if ( $colprefix ) {
                if ( strpos( $colprefix, '<model>' ) !== false )
                    $colprefix = str_replace( '<model>', $model, $colprefix );
                elseif ( strpos( $colprefix, '<table>' ) !== false )
                    $colprefix = str_replace( '<table>', $this->_table, $colprefix );
            }
            $this->_colprefix = $colprefix;
        }
        if (! isset( $pado->scheme[ $model ] ) ) {
            if ( $pado->json_model )
                $class->set_scheme_from_json( $model );
            if (! isset( $pado->scheme[ $model ] ) ) {
                $class->get_scheme( $model, $this->_table, $colprefix );
            }
            if ( $pado->upgrader ) {
                $upgrade = $class->check_upgrade( $model, $this->_table, $colprefix );
                if ( $upgrade !== false ) {
                    $class->upgrade( $this->_table, $upgrade, $colprefix );
                }
            }
        }
        $scheme = isset( $pado->scheme[ $model ] ) ? $pado->scheme[ $model ] : null;
        $this->_scheme = $scheme;
        if (! $this->_id_column ) {
            $primary = ( $scheme && isset( $scheme['indexes'] )
                && isset( $scheme['indexes']['PRIMARY'] ) )
                    ? $colprefix . $scheme['indexes']['PRIMARY']
                    : $colprefix . $pado->id_column;
            $this->_id_column = $primary;
        }
        if ( $pado->caching ) {
            if ( $id_col = $pado->id_column )
                $pado->cache[ $model ][ $this->$id_col ] = $this;
        }
    }

    public function model ( $_model = null ) {
        static $model;
        if ( $_model ) $model = $_model;
        return $model;
    }

/**
 * Get instance of class PADO
 * 
 * @return object $pado : Object class PADO.
 */ 
    function pado () {
        return $this->_pado ? $this->_pado : PADO::get_instance();
    }

/**
 * Get column value without prefix.
 * if $obj->relatedobj_id && exist model relatedobj, return $relatedobj.
 */
    function __get ( $col ) {
        $original = $col;
        if (! isset( $this->$col ) && $this->_colprefix )
            $col = $this->_colprefix . $col;
        if ( isset( $this->$col ) ) return $this->$col;
        $col = $original . '_id';
        if ( $this->has_column( $col ) ) {
            $id = (int) $this->$col;
            $obj = $this->pado()->model( $original )->load( $id );
            return is_object( $obj ) && $obj->id ? $obj : null;
        }
    }

/**
 * Call new method or set column value useing method.
 */
    public function __call ( $method, $args ) {
        if( $method === 'new' ) // for PHP5.x
            return call_user_func_array( array( $this, '__new' ), $args );
        $reserved_vars = PADOBaseModel::RESERVED;
        if (! isset( $reserved_vars[ $method ] ) ) {
            $colprefix = $this->_colprefix;
            if ( $colprefix && strpos( $method, $colprefix ) !== 0 )
                $method = $colprefix . $method;
            $this->$method = $args[ 0 ];
        }
    }

/**
 * Create a new object.
 * 
 * @param  array  $params : An array for column names and values for assign.
 * @return object $object : New object.
 */
    function __new ( $params = [], &$changed = false ) {
        $class = get_class( $this );
        if ( $class === 'PADOBaseModel' && $this->_driver ) {
            $model = $this->_driver;
        } else {
            $model = $this;
        }
        $colprefix = $this->_colprefix;
        foreach ( $params as $key => $value ) {
            if ( $colprefix && strpos( $key, $colprefix ) !== 0 )
                $key = $colprefix . $key;
            if ( $model->$key !== $value ) {
                $model->$key = $value;
                $changed = true;
            }
        }
        return $model;
    }

/**
 * Load object.
 * 
 * @param  mixed  $terms   : Numeric ID or the hash should have keys matching column names
 *                           and the values are the values for that column.
 * @param  array  $args    : Search options.
 * @param  string $cols    : Get columns from records. Comma-separated text or '*'.
 * @param  string $extra   : String to add to the WHERE statement.
 *                           Insufficient care is required for injection.
 * @return array  $objects : An array of objects or single object(Specified Numeric ID).
 */
    function load ( $terms = [], $args = [], $cols = '', $extra = '' ) {
        if (! $terms ) $terms = [];
        $pado = $this->pado();
        if ( isset( $pado->methods['load'] ) )
            return $this->_driver->load( $terms, $args, $cols );
        $model = $this->_model;
        if ( $pado->caching ) {
            if (! isset( $pado->cache[ $model ] ) ) $pado->cache[ $model ] = [];
            if ( is_numeric( $terms ) ) {
                if ( isset( $pado->cache[ $model ][ $terms ] ) ) {
                    return $pado->cache[ $model ][ $terms ];
                }
            }
            $cache_key = $pado->make_cache_key( $model, $terms, $args, $cols, $extra );
            if ( isset( $pado->cache[ $model ][ $cache_key ] ) ) {
                return $pado->cache[ $model ][ $cache_key ];
            }
        }
        $table = $this->_table;
        $colprefix = $this->_colprefix;
        if (! $pado->upgrader && ! isset( $pado->scheme[ $model ] ) ) {
            $this->get_scheme( $model, $table, $colprefix );
        }
        $scheme = isset( $pado->scheme[ $model ] ) ?
            $pado->scheme[ $model ][ 'column_defs' ] : null;
        $id_column = $this->_id_column;
        if ( is_array( $args ) && isset( $args['and_or'] ) ) $and_or = $args['and_or'];
        $extra_and_or = 'AND';
        if ( isset( $and_or ) ) {
        if ( is_array( $and_or ) ) list( $and_or, $extra_and_or ) = $and_or;
            $and_or = strtoupper( $and_or );
            $extra_and_or = strtoupper( $extra_and_or );
        }
        if (! isset( $and_or ) || ( $and_or !== 'AND' && $and_or !== 'OR' ) )
            $and_or = 'AND';
        if (! isset( $extra_and_or ) || ( $extra_and_or !== 'AND' &&
            $extra_and_or !== 'OR' ) ) $extra_and_or = 'AND';
        $illegals = PADOBaseModel::ILLEGALS;
        $model = str_replace( $illegals, '', $model );
        if (! $model ) return is_numeric( $terms ) ? null : [];
        if ( $cols === '*' ) {
        } elseif (! $cols ) {
            $cols = '*';
        } else {
            $cols = str_replace( $illegals, '', $cols );
        }
        if (! $cols ) return is_numeric( $terms ) ? null : [];
        if ( $cols !== '*' ) {
            $columns = explode( ',', $cols );
            $columns = array_unique( $columns );
            array_walk( $columns, function( &$col, $num, $params ) {
                list( $pfx, $scheme ) = $params;
                $orig_col = $col;
                if ( strpos( $col, $pfx ) !== 0 ) {
                    $col = $pfx . $col;
                } else {
                    $orig_col = preg_replace( "/^$pfx/", '', $orig_col );
                }
                if ( $scheme && !isset( $scheme[ $orig_col ] ) ) $col = null;
            }, [ $colprefix, $scheme ] );
            if ( $scheme ) {
                $valid_cols = [];
                foreach ( $columns as $column ) {
                    if ( $column ) $valid_cols[] = $column;
                }
                $cols = join( ',', $valid_cols );
            } else {
                $cols = join( ',', $columns );
            }
            if (! $cols ) $cols = '*';
        }
        $distinct = '';
        $count = '';
        $count_group_by = '';
        $group_by = '';
        $in_join = false;
        $load_iter = false;
        $method = isset( $args['get_by_key'] ) ? 'get_by_key' :'load';
        if ( is_array( $args ) && !empty( $args ) ) {
            if ( isset( $args['distinct'] ) || isset( $args['unique'] ) )
                $distinct = 'distinct ';
            if ( isset( $args['load_iter'] ) || isset( $args['load_iter'] ) ) {
                $load_iter = true;
                $method = 'load_iter';
            }
            $args = array_change_key_case( $args, CASE_LOWER );
            if ( isset( $args['count'] ) && $args['count'] ||
                isset( $args['count_group_by'] ) && $args['count_group_by'] ) {
                if ( $distinct ) {
                    $count = "COUNT(DISTINCT {$id_column}) ";
                    $distinct = '';
                } else {
                    $count = "COUNT({$id_column}) ";
                }
                $method = isset( $args['count'] ) ? 'count' : 'count_group_by';
            }
            if ( isset( $args['count_group_by'] ) && isset( $args['group'] ) ) {
                $columns = $args['group'];
                array_walk( $columns, function( &$col, $num, $pfx = null ){
                    $col = strpos( $col, $pfx ) !== 0 ? $pfx . $col : $col;
                }, $colprefix );
                $count_group_by = join( ',', $columns );
                $group_by = " GROUP BY {$count_group_by} ";
                $count_group_by .= ',';
            }
            if ( isset( $args['join'] ) ) {
                list( $join, $col ) = $args['join'];
                $col2 = $col;
                if ( is_array( $col ) ) {
                    list( $col, $col2 ) = $col;
                }
                if (!isset( $pado->scheme[ $join ] ) && $pado->json_model ) {
                    $this->set_scheme_from_json( $join );
                }
                if (!isset( $pado->scheme[ $join ] ) ) {
                    $_colprefix = $pado->colprefix;
                    $_table = $pado->prefix . $join;
                    if ( strpos( $_colprefix, '<model>' ) !== false )
                        $_colprefix = str_replace( '<model>', $join, $_colprefix );
                    elseif ( strpos( $_colprefix, '<table>' ) !== false )
                        $_colprefix = str_replace( '<table>', $_table, $_colprefix );
                    $this->get_scheme( $join, $pado->prefix . $join, $_colprefix );
                }
                $join_scheme = isset( $pado->scheme[ $join ] ) ?
                    $pado->scheme[ $join ][ 'column_defs' ] : null;
                if ( $join_scheme && !isset( $join_scheme[ $col2 ] ) ) {
                    $message =
                    "PADOBaseModelException: unknown column '{$col2}' for model '{$join}'";
                    $pado->errors[] = $message;
                    trigger_error( $message );
                    return false;
                }
                if ( strpos( $col, $colprefix ) !== 0 )
                    $col = $colprefix . $col;
                if ( $cols !== '*' ) {
                    if ( isset( $columns ) ) {
                        array_walk( $columns, function( &$v, $num, $table = null ){
                            $v = $table . '.' . $v;
                        }, $table );
                        $cols = join( ',', $columns );
                    }
                } else {
                    $cols = $table . '.*';
                }
                if ( $count ) $cols = '';
                $join_prefix = $pado->colprefix;
                if ( strpos( $join_prefix, '<model>' ) !== false )
                    $join_prefix =
                        str_replace( '<model>', $join, $join_prefix );
                elseif ( strpos( $join_prefix, '<table>' ) !== false )
                    $join_prefix =
                        str_replace( '<table>', $pado->prefix . $join, $join_prefix );
                if ( $pado->prefix && strpos( $join, $pado->prefix ) !== 0 )
                    $join = $pado->prefix . $join;
                if ( $join && $cols ) {
                    $cols .= ',' . $join . '.*';
                }
                if ( $cols ) $cols .= ' ';
                $sql = "SELECT {$count_group_by}{$count}{$distinct}{$cols}FROM {$table}"
                     . " JOIN $join ON {$table}.{$col}={$join}.{$join_prefix}{$col2} ";
                $in_join = true;
            }
        }
        if (! isset( $sql ) ) {
            if ( $count ) $cols = '';
            $sql = "SELECT {$count_group_by}{$count}{$distinct}{$cols}"
                 . " FROM {$table} ";
        }
        $vals = [];
        $stms = [];
        $extra_stms = [];
        $extra_vals = [];
        $has_stmt = true;
        $add_where = false;
        if ( is_array( $terms ) && empty( $terms ) ) {
            $sql .= "WHERE 1=?";
            $vals[] = 1;
            $add_where = true;
        } elseif ( is_array( $terms ) && ! empty( $terms ) ) {
            $has_stmt = false;
            foreach ( $terms as $key => $cond ) {
                $orig_key = $key;
                if ( $colprefix && strpos( $key, $colprefix ) !== 0 ) {
                    $key = $colprefix . $key;
                } else {
                    $orig_key = preg_replace( "/^$colprefix/", '', $orig_key );
                }
                if ( $scheme && !isset( $scheme[ $orig_key ] ) )
                    continue;
                $regex = '/(=|>|<|<=|>=|>|BETWEEN|NOT\sBETWEEN|LIKE|IN|NOT\sLIKE|'
                       . 'AND|OR|IS\sNULL|IS\sNOT\sNULL|\!=)/i';
                list( $op, $v ) = ['=', $cond ];
                if ( is_array( $cond ) ) {
                    $op = key( $cond );
                    $v  = $cond[ $op ];
                }
                if ( $cond === null ) $cond = [];
                if ( $pado->debug === 3 ) {
                    var_dump( $cond );
                }
                if ( ( ( is_string( $cond ) || is_numeric( $cond ) )
                    || ( is_array( $cond ) && count( $cond ) === 1 ) )
                    || ( stripos( $op, 'BETWEEN' ) !== false || $op === 'IN' ) ) {
                    if ( preg_match( $regex, $op, $matchs ) ) {
                        $op = strtoupper( $matchs[1] );
                        if ( stripos( $op, 'NULL' ) !== false ) {
                            $stms[] = " {$key} {$op} ";
                        } elseif ( is_array( $v ) &&
                            stripos( $op, 'BETWEEN' ) !== false ) {
                            list( $start, $end ) = $v;
                            $stms[] = " {$key} {$op} ? AND ? ";
                            $vals[] = $start;
                            $vals[] = $end;
                        } else {
                            $col_type = $scheme[ $orig_key ]['type'];
                            if ( $op === 'IN' && is_array( $v ) && ! empty( $v ) ) {
                                $stms[] = $this->in_stmt( $key, $v, $col_type );
                            } else {
                                if ( $op == 'AND' || $op == 'OR' && $and_or != $op ) {
                                    // var_dump( $v );
                                    // if (array_values($arr) === $arr) {
                                    // if ( is_array( $v ) ) {
                                    if ( array_values( $v ) !== $v ) {
                                        $op = key( $v );
                                        $v = $v[ $op ];
                                        if ( preg_match( $regex, $op, $matchs ) ) {
                                            $op = strtoupper( $matchs[1] );
                                            if ( $op == 'AND' || $op == 'OR' ) continue;
                                        } else {
                                            continue;
                                        }
                                    } else if (! is_array( $v ) ) {
                                        $op = '=';
                                    } else {
                                        // var_dump( $extra_stms );
                                        // $stms[] = "1=1";
                                    }
                                    if ( stripos( $op, 'NULL' ) !== false ) {
                                        $extra_stms[] = " {$key} {$op} ";
                                    } elseif ( is_array( $v ) &&
                                        stripos( $op, 'BETWEEN' ) !== false ) {
                                        list( $start, $end ) = $v;
                                        $extra_stms[] = " {$key} {$op} ? AND ? ";
                                        $extra_vals[] = $start;
                                        $extra_vals[] = $end;
                                    } else {
                                        if ( $op === 'IN' && is_array( $v )
                                                                    && ! empty( $v ) ) {
                                            $extra_stms[] =
                                                    $this->in_stmt( $key, $v, $col_type );
                                        } else {
                                            if ( is_array( $v ) )
                                          {
                                            if ( array_values( $v ) === $v ) {
                                                $_stmts = [];
                                                foreach ( $v as $cnd ) {
                                                    $_op = key( $cnd );
                                                    $_val = $cnd[ $_op ];
                                                    if ( preg_match
                                                        ( $regex, $_op, $matchs ) ) {
                                                        $_op = strtoupper( $matchs[1] );
                                                        $_stmts[] = " {$key} {$_op} ? ";
                                                        $extra_vals[] = $_val;
                                                    }
                                                }
                                                $extra_stms[] =
                                                    '(' . join( " {$op} ", $_stmts ) . ')';
                                            }
                                          } else {
                                                $extra_stms[] = " {$key} {$op} ? ";
                                                $extra_vals[] = $v;
                                          }
                                        }
                                    }
                                } else {
                                    $stms[] = " {$key} {$op} ? ";
                                    $vals[] = $v;
                                }
                            }
                        }
                    }
                } else {
                    if ( is_array( $cond ) ) {
                        $conds = array_values( $cond );
                        $stm = '';
                        foreach ( $conds as $k => $v ) {
                            $op = is_array( $v ) ? key( $v ) : '=';
                            $var = is_array( $v ) ? $v[ $op ] : $v;
                            if ( preg_match( $regex, $op, $matchs ) ) {
                                $op = strtoupper( $matchs[ 1 ] );
                                if ( is_array( $var ) ) {
                                    $var = array_change_key_case( $var, CASE_UPPER );
                                    $_and_or = strtoupper( key( $var ) );
                                    $_and_or = ltrim( $and_or, '-' );
                                    if ( $_and_or !== 'AND' && $_and_or !== 'OR' ) {
                                        continue;
                                    }
                                    $var = $var[ $_and_or ];
                                } else {
                                    $_and_or = 'AND';
                                }
                                if ( $stm ) $stm .= $_and_or;
                                if ( stripos( $op, 'NULL' ) !== false ) {
                                    $stm .= " {$key} {$op}";
                                } elseif ( is_array( $var ) &&
                                    stripos( $op, 'BETWEEN' ) !== false ) {
                                    list( $start, $end ) = $var;
                                    $stm .= " {$key} {$op} ? AND ? ";
                                    $vals[] = $start;
                                    $vals[] = $end;
                                } else {
                                    $stm .= " {$key} {$op} ? ";
                                    $vals[] = $var;
                                }
                            }
                        }
                        if ( $stm ) {
                            $stm = " ({$stm}) ";
                            $stms[] = $stm;
                        }
                    }
                }
            }
            if ( count( $stms ) ) {
                // if ( $in_join ) $sql .= ' AND ';
                $sql .= 'WHERE (' . join( " {$and_or} ", $stms ) . ')';
                $add_where = true;
            }
            if (! empty( $extra_stms ) ) {
                if ( $add_where ) {
                    $sql .= " {$extra_and_or} ";
                } else {
                    $sql .= 'WHERE';
                }
                $sql .= ' (' . join( " {$extra_and_or} ", $extra_stms ) . ')';
                $vals = array_merge( $vals, $extra_vals );
            }
        } elseif ( is_numeric( $terms ) ) {
            $sql .= "WHERE {$id_column}=?";
            $vals[] = $terms;
        } elseif ( is_string( $terms ) ) {
            $sql = $terms;
        }
        $sql .= $group_by;
        if ( $extra ) $sql .= " {$extra} ";
        if (!$count || ( isset( $args['count_group_by'] ) && $args['count_group_by'] ) ) {
            $opt = '';
            if ( is_array( $args ) && !empty( $args ) ) {
                foreach ( $args as $key => $arg ) {
                    if ( $key === 'sort' || $key === 'order_by' ) $opt = $arg;
                    elseif ( $key === 'limit' ) $limit = (int) $arg;
                    elseif ( $key === 'offset' ) $offset = (int) $arg;
                    elseif ( $key === 'direction' ) $direction = strtoupper( $arg );
                }
                if ( $opt ) {
                    $opt = str_replace( $illegals, '', $opt );
                    if ( $colprefix && strpos( $opt, $colprefix ) !== 0 )
                        if ( ( $colprefix && !$in_join ) 
                            || ( $in_join && strpos( $opt, '.' ) === false ) )
                                $opt = $colprefix . $opt;
                    $opt = " ORDER BY {$opt} ";
                    if ( isset( $direction ) ) {
                        $direction = strpos( $direction, 'ASC' ) === 0 ? 'ASC' : 'DESC';
                    } else {
                        $direction = 'ASC';
                    }
                    $opt .= $direction . ' ';
                }
                if ( isset( $limit ) ) {
                    if (! isset( $offset ) ) $offset = 0;
                    $opt .= " LIMIT {$limit} OFFSET {$offset} ";
                }
            }
            $sql .= $opt;
        }
        if ( $pado->debug === 3 ) $pado->debugPrint( $sql );
        if ( $pado->debug === 3 ) var_dump( $vals );
        if ( $pado->query_cnt > $pado->max_queries && ! $pado->txn_active ) {
            $pado->reconnect();
        }
        $pdo = $pado->db;
        $callback = ['name' => 'pre_load', 'sql' => $sql,
                     'values' => $vals, 'method' => $method ];
        $pado->run_callbacks( $callback, $model, $this );
        $sql = $callback['sql'];
        $sth = $pdo->prepare( $sql );
        if (! $count_group_by ) {
            $class = class_exists( $model ) ? $model
            : ( $this->_driver
            ? get_class( $this->_driver ) : 'PADOBaseModel' );
            $sth->setFetchMode( PDO::FETCH_CLASS, $class );
            self::$_Model = $this->_model;
            self::$_ID_column = $this->_id_column;
            self::$_Colprefix = $this->_colprefix;
            self::$_Scheme = $this->_scheme;
            self::$_Driver = $this->_driver;
        }
        try {
            $sth->execute( $vals );
            $pado->queries[] = !empty( $vals )
                             ? $sql . ' / values = ' . join( ', ', $vals )
                             : $sql;
            ++$pado->query_cnt;
            if ( $count && !$count_group_by ) {
                $count = (int) $sth->fetchColumn();
                if ( $pado->caching ) {
                    $pado->cache[ $model ][ $cache_key ] = $count;
                }
                return $count;
            }
            if ( $load_iter ) {
                if ( $pado->caching ) {
                    $pado->cache[ $model ][ $cache_key ] = $sth;
                }
                return $sth;
            }
            $objects = $sth->fetchAll();
            if ( is_numeric( $terms ) ) {
                $obj = isset( $objects[0] ) ? $objects[0] : null;
                if ( $pado->caching ) {
                    if ( $obj ) $pado->cache[ $model ][ $obj->id ] = $obj;
                }
                return $obj;
            }
            if ( $pado->caching ) {
                $pado->cache[ $model ][ $cache_key ] = $objects;
            }
            $sth = null;
            return $objects;
        } catch ( PDOException $e ) {
            $message =  'PDOException: ' . $e->getMessage() . ", {$sql}";
            $pado->errors[] = $message;
            trigger_error( $message );
            return $this->retry( $pado, $sth, $message, 'load', $terms, $args, $cols, $extra );
        }
    }

    function retry ( $pado, &$sth, $message, $meth,
            $params1 = null, $params2 = null, $params3 = null, $params4 = null ) {
        if ( $pado->driver === 'mysql' && strpos( $message, 'has gone away' ) !== false ) {
            $sth = null;
            if ( $pado->reconnect() ) {
                return $this->$meth( $terms, $args, $cols, $extra );
            }
        }
        return false;
    }

/**
 * Assemble the IN statement.
 * 
 * @param  string $key      : Key name for search.
 * @param  array  $v        : An array of values.
 * @param  string $col_type : Type of column.
 * @return string $stmt     : SQL IN statement.
 */
    function in_stmt ( $key, $v, $col_type ) {
        if ( strpos( $col_type, 'int' ) !== false ) {
            array_walk( $v, function( &$val ) {
                $val = (int) $val;
            });
        } else {
            array_walk( $v, function( &$val ) {
                $val = $this->pado()->quote( $val );
            });
        }
        $v = '('. join( ',', $v ) . ')';
        return " {$key} IN {$v} ";
    }

/**
 * Load object matches the params.
 * If no matching object is found, return new object assigned params.
 * 
 * @param  array  $params: An array for search or assign.
 * @return object $obj   : Single object matches the params or new object assigned params.
 */
    function get_by_key ( $params ) {
        $args = ['limit' => 1, 'get_by_key' => true ];
        $obj = $this->load( $params, $args );
        if ( $obj && is_array( $obj ) ) $obj = $obj[ 0 ];
        if (! $obj ) {
            $pado = $this->pado();
            $obj = $this->__new( $params );
            if ( isset( $pado->cache[ $obj->_model ] ) ) {
                if ( isset( $pado->cache[ $obj->_model ][ $pado->last_cache_key ] ) ) {
                    unset( $pado->cache[ $obj->_model ][ $pado->last_cache_key ] );
                }
            }
        }
        return $obj;
    }

/**
 * Getting the count of a number of objects.
 * 
 * @param             : See load method.
 * @return int $count : Number of objects.
 */
    function count ( $terms = [], $args = [], $cols = '', $extra = '' ) {
        $args['count'] = true;
        return $this->load( $terms, $args, $cols, $extra );
    }

/**
 * The model has column or not.
 * 
 * @param  string $name : Column name.
 * @return bool   $has_column : Model has column or not.
 */
    function has_column ( $name ) {
        $model = $this->_model;
        $scheme = $this->pado()->scheme;
        $scheme = isset( $scheme[ $model ] ) ? $scheme[ $model ] : $this->_scheme;
        return isset( $scheme['column_defs'][ $name ] ) ? true : false;
    }

/**
 * Counting groups of objects.
 * 
 * @param mixed  $terms  : The hash should have keys matching column names and the values
 *                         are the values for that column.
 * @param array  $args   : Columns for grouping. (e.g.['group' => ['column1', ... ] ])
 * @return array $result : An array of conditions and 'COUNT(*)'.
 */
    function count_group_by ( $terms = [], $args = [] ) {
        $args['count_group_by'] = true;
        return $this->load( $terms, $args );
    }

/**
 * Load object and get PDOStatement.
 * 
 * @param              : See load method.
 * @return object $sth : PDOStatement.
 */
    function load_iter ( $terms = [], $args = [], $cols = '', $extra = '' ) {
        $args['load_iter'] = true;
        return $this->load( $terms, $args, $cols, $extra );
    }

/**
 * INSERT or UPDATE the object.
 * 
 * @return bool $success : Returns true if it succeeds.
 */
    function save () {
        $pado = $this->pado();
        if ( isset( $pado->methods['save'] ) )
            return $this->_driver->save();
        $table = $this->_table;
        $model = $this->_model;
        $id_column = $this->_id_column;
        $colprefix = $this->_colprefix;
        $table = $this->_table;
        $callback = ['name' => 'save_filter'];
        $save_filter = $pado->run_callbacks( $callback, $model, $this, true );
        if (! $save_filter ) return false;
        $arr = get_object_vars( $this );
        $reserved_vars = array_keys( PADOBaseModel::RESERVED );
        foreach ( $reserved_vars as $var ) {
            unset( $arr[ $var ] );
        }
        $original = $arr;
        $statement = 'UPDATE';
        $update = true;
        if (! isset( $arr[ $id_column ] ) || ! $arr[ $id_column ] ) {
            $statement = 'INSERT';
            unset( $arr[ $id_column ] );
            $update = false;
        }
        $arr = $this->validation( $arr, $update );
        $cols = [];
        $vals = [];
        $placeholders = [];
        if ( $statement === 'INSERT' ) {
        } else {
            $object_id = $arr[ $id_column ];
            unset( $arr[ $id_column ] );
        }
        foreach ( $arr as $key => $val ) {
            $cols[] = $key;
            $vals[] = $val;
            $placeholders[] = '?';
        }
        if ( empty( $cols ) ) return false;
        if ( $statement === 'INSERT' ) {
            $sql = "INSERT INTO {$table} (" . join( ',', $cols ) . ')VALUES('
                 . join( ',', $placeholders ) . ')';
        } else {
            $set = join( '=?,', $cols );
            $set .= '=?';
            $vals[] = $object_id;
            $sql = "UPDATE {$table} SET {$set} WHERE {$id_column}=?" ;
        }
        $callback = ['name' => 'pre_save', 'sql' => $sql, 'values' => $vals ];
        $sql = $callback['sql'];
        $vals = $callback['values'];
        $pado->run_callbacks( $callback, $model, $this, true );
        if ( $pado->debug === 3 ) $pado->debugPrint( $sql );
        if ( $pado->query_cnt > $pado->max_queries && ! $pado->txn_active ) {
            $pado->reconnect();
        }
        $pdo = $pado->db;
        $sth = $pdo->prepare( $sql );
        try {
            $res = $sth->execute( $vals );
            $pado->queries[] = $sql;
            ++$pado->query_cnt;
            $this->$id_column = isset( $object_id )
                              ? $object_id : (int) $pdo->lastInsertId( $id_column );
            if ( $pado->caching ) {
                unset ( $pado->cache[ $model ][ $this->$id_column ] );
            }
            $callback['name'] = 'post_save';
            $pado->run_callbacks( $callback, $model, $this );
            return $res;
        } catch ( PDOException $e ) {
            $message = 'PDOException: ' . $e->getMessage() . ", {$sql}";
            $pado->errors[] = $message;
            trigger_error( $message );
            return $this->retry( $pado, $sth, $message, 'save' );
        }
        $sth = null;
    }

/**
 * BULK INSERT or UPDATE the objects.
 *
 * @param array $objects : An array of objects.
 * @param bool  $update  : If specify false, BULK INSERT.
 * @return bool $success : Returns true if it succeeds.
 */
    function update_multi ( $objects, $update = true ) {
        if ( empty( $objects ) ) return;
        $pado = $this->pado();
        if ( isset( $pado->methods['update_multi'] ) )
            return $this->_driver->update_multi( $objects );
    }

/**
 * BULK Remove the objects.
 *
 * @param array $objects : An array of objects.
 * @return bool $success : Returns true if it succeeds.
 */
    function remove_multi ( $objects ) {
        if ( empty( $objects ) ) return;
        $pado = $this->pado();
        if ( isset( $pado->methods['remove_multi'] ) )
            return $this->_driver->remove_multi( $objects );
    }

/**
 * Alias for save.
 */
    function update ( $values = [] ) {
        // TODO
        return $this->save();
    }

/**
 * DELETE the object.
 *
 * @return bool $success : Returns true if it succeeds.
 */
    function remove () {
        $pado = $this->pado();
        if ( isset( $pado->methods['remove'] ) )
            return $this->_driver->remove();
        $id_column = $this->_id_column;
        $model = $this->_model;
        $id = $this->$id_column;
        if (! $id ) return;
        $table = $this->_table;
        $callback = ['name' => 'delete_filter' ];
        $delete_filter = $pado->run_callbacks( $callback, $model, $this, true );
        if (! $delete_filter ) return false;
        if ( $pado->query_cnt > $pado->max_queries && ! $pado->txn_active ) {
            $pado->reconnect();
        }
        $pdo = $pado->db;
        $sql = "DELETE FROM {$table} WHERE {$id_column}=:object_id";
        if ( $pado->debug === 3 ) $pado->debugPrint( $sql );
        $callback = ['name' => 'pre_delete', 'sql' => $sql, 'values' => [ $id ] ];
        $pado->run_callbacks( $callback, $model, $this );
        $sth = $pdo->prepare( $sql );
        $sth->bindValue( ':object_id', $id, PDO::PARAM_INT );
        try {
            $res = $sth->execute();
            $pado->queries[] = $sql;
            ++$pado->query_cnt;
            if ( $pado->caching ) {
                unset ( $pado->cache[ $model ][ $id ] );
            }
            $callback['name'] = 'post_delete';
            $pado->run_callbacks( $callback, $model, $this );
            return $res;
        } catch ( PDOException $e ) {
            $message = 'PDOException: ' . $e->getMessage() . ", {$sql}";
            $pado->errors[] = $message;
            trigger_error( $message );
            return $this->retry( $pado, $sth, $message, 'remove' );
        }
        $sth = null;
    }

/**
 * Alias for remove.
 */
    function delete () {
        return $this->remove();
    }

/**
 * Get table scheme from JSON file and set to $pado->scheme[ $model ].
 * 
 * @param string $model : Name of model.
 * @param string $json  : File path of json.
 */
    function set_scheme_from_json ( $model, $json = '' ) {
        $json = $json ? $json : PADODIR . 'models' . DS . $model . '.json';
        $pado = $this->pado();
        if (! file_exists( $json ) ) {
            if ( isset( $pado->models_json[ $model ] ) ) {
                $json = $pado->models_json[ $model ];
            }
        }
        if ( file_exists( $json ) ) {
            $scheme = json_decode( file_get_contents( $json ), true );
            if ( isset( $scheme['indexes'] ) && isset( $scheme['indexes']['PRIMARY'] ) ) {
                $id = $scheme['indexes']['PRIMARY'];
                if ( is_array( $id ) ) $id = join( ',', $id );
                $this->_id_column = $this->_colprefix . $id;
            }
            $this->_scheme = $scheme;
            $this->pado()->scheme[ $model ] = $scheme;
       }
    }

/**
 * Get table scheme from database and set to $pado->scheme[ $model ].
 * 
 * @param  string $model     : Name of model.
 * @param  string $table     : Name of table.
 * @param  string $colprefix : Column prefix.
 * @param  bool   $needle    : If specified receive results(array).
 * @return array  $scheme    : If $needle specified.
 */
    function get_scheme ( $model, $table, $colprefix, $needle = false ) {
        $pado = $this->pado();
        if ( isset( $pado->methods['get_scheme'] ) )
            return $this->_driver->get_scheme( $model, $table, $colprefix, $needle );
        return;
    }

/**
 * Create new table from scheme.
 * 
 * @param string $model  : Name of model.
 * @param string $table  : Name of table.
 * @param array  $scheme : An array of column definition and index definition.
 */
    function create_table ( $model, $table, $colprefix, $scheme ) {
        $pado = $this->pado();
        if ( isset( $pado->methods['create_table'] ) )
            return $this->_driver->create_table( $model, $table, $colprefix, $scheme );
        return;
    }

/**
 * Get an array of column names and values.
 * 
 * @return array $key-values : Column names and values.
 */
    function column_values () {
        $object_vars = get_object_vars( $this );
        $colprefix = $this->_colprefix;
        foreach ( $object_vars as $name => $value ) {
            if ( strpos( $name, '_' ) !== 0 ) {
                $col_name = preg_replace( "/^$colprefix/", '', $name );
                $object_vars[ $col_name ] = $value;
            }
            unset( $object_vars[ $name ] );
        }
        return $object_vars;
    }

/**
 * Returns a list of the names of columns.
 * 
 * @return array $names : The names of columns.
 */
    function column_names () {
        $pado = $this->pado();
        if ( isset( $pado->scheme[ $this->_model ] ) ) {
            $scheme = $pado->scheme[ $this->_model ]['column_defs'];
            return array_keys( $scheme );
        }
        $id_column = $pado->id_column;
        if ( $this->$id_column ) {
            return array_keys( $this->column_values() );
        }
    }

/**
 * Set column names and values from an array.
 * 
 * @param array $params : The hash for assign.
 */
    function set_values ( $params = [], &$changed = false ) {
        $this->__new( $params, $changed );
    }

/**
 * Get column names and values except model properties.
 */
    function get_values ( $no_prefix = false ) {
        $arr = get_object_vars( $this );
        $reserved_vars = array_keys( PADOBaseModel::RESERVED );
        foreach ( $reserved_vars as $var ) {
            unset( $arr[ $var ] );
        }
        if ( $no_prefix ) {
            $_colprefix = $this->_colprefix;
            $values = [];
            foreach ( $arr as $k => $v ) {
                $values[ preg_replace( "/^$_colprefix/", '', $k ) ] = $v;
            }
            unset( $arr );
            return $values;
        }
        return $arr;
    }

/**
 * Next or Previous Object.
 * 
 * @param  string $direction : 'next' or 'previous'.
 * @param  string $column    : Column for compare.
 * @param  array  $terms     : $terms for load.
 * @param  string $cols      : $cols for load.
 * @param  string $extra     : $extra for load. 
 * @return object $next_prev : Returns one object or null.
 */
    function next_prev ( $direction = 'next', $column = 'id', $terms = [], $cols = '*', $extra = '' ) {
        $pado = $this->pado();
        $model = $this->_model;
        $colprefix = $this->_colprefix;
        $id_column = $pado->id_column;
        $id = $this->$id_column;
        $op = $direction === 'next' ? '>=' : '<=';
        $terms[ $column ] = [ $op => $this->$column ]; 
        $extra .= " AND {$colprefix}{$column} !={$id} ";
        $args = ['limit' => 1];
        $next_prev = $this->load( $terms, $args, $cols, $extra );
        if (! empty( $next_prev ) ) {
            return $next_prev[0];
        }
    }

/**
 * Upgrade database scheme.
 * 
 * @param  string $table     : Name of table.
 * @param  array  $upgrade   : Scheme information of update columns.
 * @param  string $colprefix : Column name prefix.
 * @return bool   $success   : Returns true if it succeeds.
 */
    function upgrade ( $table, $upgrade, $colprefix ) {
        $pado = $this->pado();
        if ( isset( $pado->methods['upgrade'] ) )
            return $this->_driver->upgrade( $table, $upgrade, $colprefix );
    }

/**
 * Whether there is a difference in array.
 * 
 * @param  array $from  : The array to compare from.
 * @param  array $to    : An array to compare against.
 * @return bool  $bool  : A difference in array.
 */
    function array_compare ( $from, $to ) {
        if ( $to === null ) return true;
        if (! empty( array_diff( array_keys( $from ),
                     array_keys( $to ) ) )
            || ! empty( array_diff( array_keys( $to ),
                        array_keys( $from ) ) ) ) {
            return true;
        } else {
            foreach ( $from as $name => $props ) {
                $_props = isset( $to[ $name ] ) ? $to[ $name ] : null;
                if (! $_props ) {
                    return true;
                }
                if ( is_array( $_props ) && is_array( $props ) ) {
                    if (! empty( array_diff_assoc( $_props, $props ) )
                        || ! empty( array_diff_assoc( $props, $_props ) ) ) {
                        return true;
                    }
                } else {
                    if ( $_props !== $props ) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

/**
 * Compare the schema definition with the actual schema.
 * 
 * @param  string $model : Name of model.
 * @param  string $table : Name of table.
 * @param  string $colprefix : Column prefix.
 * @return array  $diff  : Difference in array
 *                       (['column_defs' => $upgrade_cols, 'indexes' => $upgrade_idxs ]).
 */
    function check_upgrade ( $model, $table, $colprefix ) {
        $pado = $this->pado();
        $upgrade = false;
        $upgrade_idx = false;
        if (! empty( $this->_scheme ) ) {
            $pado->scheme[ $model ] = $this->_scheme;
        } else {
            if ( $pado->json_model )
                $this->set_scheme_from_json( $model );
        }
        if ( isset( $pado->scheme[ $model ] ) ) {
            $column_defs = $pado->scheme[ $model ]['column_defs'];
            if ( $scheme = $this->get_scheme(
                    $model, $table, $colprefix, true ) ) {
                $compare = $scheme['column_defs'];
                $upgrade = $this->array_compare( $column_defs, $compare );
                $indexes = $pado->scheme[ $model ]['indexes'];
                $compare_idx = $scheme['indexes'];
                $upgrade_idx = $this->array_compare( $indexes, $compare_idx );
                if ( $upgrade || $upgrade_idx ) {
                    $upgrade_cols = $this->get_diff( $column_defs, $compare );
                    $upgrade_idxs = $this->get_diff( $indexes, $compare_idx );
                    $upgrade = ['column_defs' => $upgrade_cols, 'indexes' => $upgrade_idxs ];
                }
            }
        }
        return $upgrade;
    }

    function get_diff ( $from, $to ) {
        $diff = ['new' => [], 'changed' => [], 'delete' => []];
        foreach ( $from as $name => $props ) {
            $_props = isset( $to[ $name ] ) ? $to[ $name ] : null;
            if (! $_props ) {
                $diff['new'][ $name ] = $props;
                continue;
            }
            if ( is_array( $_props ) && is_array( $props ) ) {
                if (! empty( array_diff_assoc( $_props, $props ) )
                    || ! empty( array_diff_assoc( $props, $_props ) ) ) {
                    $diff['changed'][ $name ] = $props;
                }
            } else {
                if ( $_props !== $props ) $diff['changed'][ $name ] = $props;
            }
        }
        foreach ( $to as $name => $_props ) {
            $props = isset( $from[ $name ] ) ? $from[ $name ] : null;
            if (! $props ) {
                $diff['delete'][ $name ] = null;
                continue;
            }
            if ( is_array( $_props ) && is_array( $props ) ) {
                if (! empty( array_diff_assoc( $_props, $props ) )
                    || ! empty( array_diff_assoc( $props, $_props ) ) ) {
                    $diff['changed'][ $name ] = $props;
                }
            } else {
                if ( $_props !== $props ) $diff['changed'][ $name ] = $props;
            }
        }
        return $diff;
    }

/**
 * Validate keys and values.
 * 
 * @param  array $values : An array for sanitize.
 * @return array $values : Sanitized an array.
 */
    function validation ( $values, $update = false, &$error = null ) {
        $pado = $this->pado();
        if ( isset( $pado->methods['validation'] ) )
            return $this->_driver->validation( $values );
        $model = $this->_model;
        $scheme = isset( $pado->scheme[ $model ] ) ? $pado->scheme[ $model ] : null;
        $scheme = $scheme ? $scheme['column_defs'] : null;
        $colprefix = $this->_colprefix;
        $arr = [];
        if (! $scheme ) {
            $illegals = PADOBaseModel::ILLEGALS;
            foreach ( $values as $key => $val ) {
                $key = str_replace( $illegals, '', $key );
                if ( $colprefix && strpos( $key, $colprefix ) !== 0 )
                    $key = $colprefix . $key;
                $arr[ $key ] = $val;
            }
            return $arr;
        }
        foreach ( $scheme as $col => $props ) {
            if ( $col === $pado->id_column ) continue;
            if ( $update && !isset( $values[ $col ] ) ) continue;
            if ( isset( $props['not_null'] ) && $props['not_null'] ) {
                if (!isset( $values[ $col ] ) &&!isset( $values[ $colprefix . $col ] ) ) {
                    if ( isset( $props['default'] ) && $props['default'] ) {
                        $values[ $col ] = $props['default'];
                    } else {
                        $type = $props['type'];
                        if ( strpos( $type, 'int' ) !== false ) {
                            $values[ $col ] = 0;
                        } else if ( strpos( $type, 'time' ) !== false ) {
                            $default = $pado->default_ts;
                            if ( $default === 'CURRENT_TIMESTAMP') {
                                $values[ $col ] = date( 'YmdHis' );
                            } else {
                                $values[ $col ] = $default;
                            }
                        } else {
                            $values[ $col ] = '';
                        }
                    }
                }
            }
        }
        foreach ( $values as $key => $val ) {
            $orig_key = $key;
            if ( $colprefix && strpos( $key, $colprefix ) === 0 )
                $key = preg_replace( "/^$colprefix/", '', $key );
            if (! isset( $scheme[ $key ] ) ) {
                continue;
            } else {
                $type = $scheme[ $key ]['type'];
                $size = isset( $scheme[ $key ]['size'] ) ? $scheme[ $key ]['size'] : null;
                switch ( true ) {
                case ( strpos( $type, 'int' ) !== false ):
                    $val = intval( $val );
                    break;
                case ( strpos( $type, 'float' ) !== false ):
                    $val = intval( $val );
                    break;
                case ( $type === 'datetime' ):
                    if ( $this->db2ts( $val ) ) {
                        $val = $this->ts2db( $val );
                    } else {
                        $val = null;
                    }
                    break;
                case ( $type === 'date' && $val ):
                    $val = $this->date2db( $val );
                    break;
                case ( $type === 'time' && $val ):
                    $val = $this->time2db( $val );
                    break;
                case ( $type === 'year' ):
                    $val = (string) $val;
                    $val = preg_replace( '/[^0-9]/', '', $val );
                    if ( strlen( $val ) === 2 ) {
                        $val = ( $val > 69 ) ? '19' . $val : '20' . $val;
                    } else {
                        $val = substr( $this->ts2db( $val ), 0, 4 );
                    }
                    $val = (int) $val;
                    break;
                case ( $type === 'blob' ):
                    $val = $this->serialize( $val );
                    break;
                case ( $type === 'string' && $size ):
                    $val = (string) $val;
                    if ( mb_strlen( $val ) > $size )
                        $val = mb_substr( $val, 0, $size, $pado->charset );
                    break;
                default:
                    $val = (string) $val;
                }
            }
            if ( $val !== null ) $arr[ $colprefix . $key ] = $val;
        }
        return $arr;
    }

/**
 * Serialize for type blob.
 */
    function serialize ( $val ) {
        $pado = $this->pado();
        if ( isset( $pado->methods['serialize'] ) )
            return $this->_driver->serialize( $val );
        return $val;
    }

/**
 * Ymd to Y-m-d
 */
    function date2db ( $ts ) {
        $pado = $this->pado();
        if ( isset( $pado->methods['date2db'] ) )
            return $this->_driver->date2db( $ts );
        $ts = (string) $ts;
        if ( strlen( $ts ) > 6 ) $ts = substr( $ts, 0, 6 );
        preg_match( '/^(\d\d\d\d)?(\d\d)?(\d\d)?$/', $ts, $mts );
        list( $all, $Y, $M, $D ) = $mts;
        return sprintf( "%04d-%02d-%02d", $Y, $M, $D );
    }

/**
 * His to H:i:s
 */
    function time2db ( $ts ) {
        if ( isset( $pado->methods['time2db'] ) )
            return $this->_driver->time2db( $ts );
        $ts = (string) $ts;
        if ( strlen( $ts ) > 6 ) $ts = substr( $ts, 0, 6 );
        preg_match( '/^(\d\d)?(\d\d)?(\d\d)?$/', $ts, $mts );
        list( $all, $h, $m, $s ) = $mts;
        return sprintf( "%02d:%02d:%02d", $h, $m, $s );
    }

/**
 * YmdHis to Y-m-d H:i:s
 */
    function ts2db ( $ts ) {
        $pado = $this->pado();
        if ( isset( $pado->methods['ts2db'] ) )
            return $this->_driver->ts2db( $ts );
        $ts = (string) $ts;
        $ts = preg_replace( '/[^0-9]/', '', $ts );
        if ( strlen( $ts ) < 14 ) {
            $pad = 14 - strlen( $ts );
            for ( $count = 0; $count < $pad; $count++ ) $ts .= '0';
        } else if ( strlen( $ts ) > 14 ) {
            $ts = substr( $ts, 0, 14 );
        }
        preg_match( '/^(\d\d\d\d)?(\d\d)?(\d\d)?(\d\d)?(\d\d)?(\d\d)?$/', $ts, $mts );
        list( $all, $Y, $M, $D, $h, $m, $s ) = $mts;
        return sprintf( "%04d-%02d-%02d %02d:%02d:%02d", $Y, $M, $D, $h, $m, $s );
    }

/**
 * Y-m-d H:i:s to YmdHis
 */
    function db2ts( $ts ) {
        if ( isset( $pado->methods['db2ts'] ) )
            return $this->_driver->db2ts( $ts );
        $ts = preg_replace( '/[^0-9]/', '', $ts );
        $ts = (int) $ts;
        return $ts;
    }
}

/**
 * PADOBaseModel : PADO Model for MySQL
 *
 * @version    1.0
 * @package    PADO
 * @author     Alfasado Inc. <webmaster@alfasado.jp>
 * @copyright  2017 Alfasado Inc. All Rights Reserved.
 */
class PADOMySQL extends PADOBaseModel {

    public static $_engine  = 'InnoDB';

/**
 * Get table scheme from db.
 */
    function get_scheme ( $model, $table, $colprefix, $needle = false ) {
        $table = $table ? $table : $this->_table;
        $model = $model ? $model : $this->_model;
        $colprefix = $colprefix ? $colprefix : $this->_colprefix;
        $pado = $this->pado();
        if ( $pado->stash( $model ) ) {
            $scheme = $pado->stash( $model );
            if ( $needle ) return $scheme;
            $pado->scheme[ $model ] = $scheme;
            $this->_scheme = $scheme;
            return;
        }
        if ( $pado->query_cnt > $pado->max_queries && ! $pado->txn_active ) {
            $pado->reconnect();
        }
        $sql = "DESCRIBE {$table}";
        $sth = $pado->db->prepare( $sql );
        try {
            $sth->execute();
            $pado->queries[] = $sql;
            ++$pado->query_cnt;
            $fields = $sth->fetchAll();
            $scheme = [];
            foreach ( $fields as $field ) {
                $name = $field['Field'];
                if ( $colprefix && strpos( $name, $colprefix ) ===0 )
                    $name = preg_replace( "/^$colprefix/", '', $name );
                $type = strtolower( $field['Type'] );
                $not_null = ( isset( $field['Null'] )
                    && $field['Null'] === 'NO' ) ? true : false;
                $size = null;
                $default = ( isset( $field['Default'] ) ) ? $field['Default'] : null;
                switch ( true ) {
                case ( strpos( $type, 'int' ) !== false ||
                       strpos( $type, 'double' ) !== false ):
                    if( strpos( $type, '(' ) !== false ) {
                        list ( $type, $size ) = explode( '(', $type );
                        $size = rtrim( $size, ')' );
                    }
                    break;
                case ( strpos( $type, 'var' ) !== false
                    || strpos( $type, 'text' ) !== false ):
                    if( strpos( $type, '(' ) !== false ) {
                        list ( $type, $size ) = explode( '(', $type );
                        $size = (int) rtrim( $size, ')' );
                        $type = 'string';
                    } else {
                        $type = 'text';
                    }
                    break;
                case ( $type === 'timestamp' || $type === 'datetime' ):
                    $type = 'datetime';
                    break;
                case ( $type === 'date' || $type === 'time' || $type === 'year' ):
                    break;
                case ( strpos( $type, 'blob' ) !== false ):
                    $type = 'blob';
                    break;
                default:
                    $type = 'string';
                }
                $scheme[ $name ]['type'] = $type;
                if ( $not_null ) $scheme[ $name ]['not_null'] = 1;
                if ( $size ) $scheme[ $name ]['size'] = $size;
                if ( $default !== null ) $scheme[ $name ]['default'] = $default;
            }
            $scheme = ['column_defs' => $scheme ];
            $sql = "SHOW INDEX FROM {$table}";
            if ( $pado->query_cnt > $pado->max_queries && ! $pado->txn_active ) {
                $pado->reconnect();
            }
            $sth = $pado->db->prepare( $sql );
            try {
                $sth->execute();
                $pado->queries[] = $sql;
                ++$pado->query_cnt;
                $fields = $sth->fetchAll();
                $indexes = [];
                $idxprefix = $pado->idxprefix;
                if ( strpos( $idxprefix, '<table>' ) !== false )
                    $idxprefix = str_replace( '<table>', $table, $idxprefix );
                elseif ( strpos( $idxprefix, '<model>' ) !== false )
                    $idxprefix = str_replace( '<model>', $model, $idxprefix );
                foreach ( $fields as $field ) {
                    $key = $field['Key_name'];
                    if ( $idxprefix && strpos( $key, $idxprefix ) === 0 )
                        $key = preg_replace( "/^$idxprefix/", '', $key );
                    $name = $field['Column_name'];
                    if ( $colprefix &&  strpos( $name, $colprefix ) ===0 )
                        $name = preg_replace( "/^$colprefix/", '', $name );
                    if ( isset( $indexes[ $key ] ) ) {
                        if ( is_string( $indexes[ $key ] ) ) {
                            $value = $indexes[ $key ];
                            $indexes[ $key ] = [];
                            $indexes[ $key ][] = $value;
                            $indexes[ $key ][] = $name;
                        } else {
                            $indexes[ $key ][] = $name;
                        }
                    } else {
                        $indexes[ $key ] = $name;
                    }
                }
                $scheme['indexes'] = $indexes;
                if ( isset( $indexes['PRIMARY'] ) ) {
                    $id = $indexes['PRIMARY'];
                    if ( is_array( $id ) ) $id = join( ',', $id );
                    $this->_id_column = $this->_colprefix . $id;
                }
            } catch ( PDOException $e ) {
                $message = 'PDOException: ' . $e->getMessage() . ", {$sql}";
                $pado->errors[] = $message;
                trigger_error( $message );
            }
            if ( $pado->json_model && $pado->save_json ) {
                $json = PADODIR . 'models' . DS . $model . '.json';
                if (! file_exists( $json ) )
                    file_put_contents( $json, json_encode( $scheme, JSON_PRETTY_PRINT ) );
            }
            if ( $needle ) return $scheme;
            $pado->scheme[ $model ] = $scheme;
            $this->_scheme = $scheme;
            $pado->stash( $model, $scheme );
        } catch ( PDOException $e ) {
            $msg = $e->getMessage();
            if ( strpos( $msg, 'not found' ) !== false ) {
                if ( $pado->upgrader ) {
                    if ( isset( $pado->scheme[ $model ] ) ) {
                        $this->create_table(
                            $model, $table, $colprefix, $pado->scheme[ $model ] );
                    }
                }
                return [];
            }
            $message = 'PDOException: ' . $msg . ", {$sql}";
            $pado->errors[] = $message;
            trigger_error( $message );
            return $this->retry( $pado, $sth, $message, 'get_scheme',
                $model, $table, $colprefix, $needle );
        }
        $sth = null;
    }

/**
 * ALTER TABLE.
 */
    function upgrade ( $table, $upgrade, $colprefix ) {
        $pado = $this->pado();
        if ( $pado->query_cnt > $pado->max_queries && ! $pado->txn_active ) {
            $pado->reconnect();
        }
        $pdo = $pado->db;
        $column_defs = $upgrade['column_defs'];
        $prefix = $pado->prefix ? $pado->prefix : '';
        $model = $prefix ? preg_replace( "/^$prefix/", '', $table ) : $table;
        $scheme = $pado->scheme[ $model ] ? $pado->scheme[ $model ] : [];
        $indexes = ( $scheme && isset( $scheme['indexes'] ) ) ? $scheme['indexes'] : [];
        $res = false;
        if ( is_array( $column_defs ) ) {
            $update = array_merge( $column_defs['new'], $column_defs['changed'] );
            if (! empty( $update ) ) {
                foreach ( $update as $name => $props ) {
                    $col_name = $name;
                    if ( $name === $pado->id_column ) continue;
                    if ( $colprefix && strpos( $name, $colprefix ) === false )
                        $col_name = $colprefix . $name;
                    $vals = [];
                    $type = $props['type'];
                    $size = 0;
                    if ( isset( $props['size'] ) ) {
                        $size = $props['size'];
                    }
                    switch ( true ) {
                    case ( strpos( $type, 'int' ) !== false ):
                        $type = strtoupper( $type );
                        break;
                    case ( strpos( $type, 'bool' ) === 0 ):
                        list( $type, $size ) = ['tinyint', 4];
                        break;
                    case ( $type === 'double' ):
                        $type = strtoupper( $type );
                        break;
                    case ( $type === 'string' ):
                        $type = 'VARCHAR';
                        break;
                    case ( $type === 'text' ):
                        $type = 'MEDIUMTEXT';
                        break;
                    case ( $type === 'datetime' ):
                        $type = 'DATETIME';
                        break;
                    case ( $type === 'date' ):
                        $type = 'DATE';
                        break;
                    case ( $type === 'time' ):
                        $type = 'TIME';
                        break;
                    case ( $type === 'blob' ):
                        $type = $pado->blob_type;
                        break;
                    default:
                        $type = '';
                    }
                    if (! $type ) {
                        if ( $pado->debug ) {
                            $message = 'PADOBaseModelException: unknown type('
                                     . $props['type'] . ')';
                            $pado->errors[] = $message;
                            trigger_error( $message );
                        }
                        continue;
                    }
                    if ( isset( $props['size'] ) ) {
                        $type .= '(' . $props['size'] . ')';
                    }
                    if ( isset( $props['not_null'] ) ) {
                        $type .= ' NOT NULL';
                    }
                    if ( isset( $props['default'] ) ) {
                        $vals[] = $props['default'];
                        $type .= ' DEFAULT ?';
                    }
                    if ( isset( $upgrade['indexes'] ) &&
                        isset( $upgrade['indexes']['delete'] ) ) {
                        if ( in_array( $name, array_keys($upgrade['indexes']['delete'] ) ) ) {
                            if (! empty( $indexes ) && isset( $indexes[ $name ] ) ) {
                                $sql = "ALTER TABLE {$table} DROP INDEX {$prefix}{$col_name}";
                                if ( $pado->debug === 3 ) $pado->debugPrint( $sql );
                                $sth = $pdo->prepare( $sql );
                                try {
                                    $res = $sth->execute();
                                    $pado->queries[] = $sql;
                                    ++$pado->query_cnt;
                                    $pado->stash( $sql, 1 );
                                } catch ( PDOException $e ) {
                                    $message = 'PDOException: ' . $e->getMessage() . ", {$sql}";
                                    $pado->errors[] = $message;
                                    trigger_error( $message );
                                    return false;
                                }
                                $sth = null;
                            } 
                        }
                    }
                    $statement = isset( $column_defs['new'][ $name ] ) ? 'ADD' : 'CHANGE';
                    $col_name = $statement === 'CHANGE' ? $col_name . ' ' . $col_name : $col_name;
                    $sql = "ALTER TABLE {$table} {$statement} {$col_name} {$type}";
                    if ( $pado->stash( $sql ) ) continue;
                    if ( $pado->debug === 3 ) $pado->debugPrint( $sql );
                    $sth = $pdo->prepare( $sql );
                    try {
                        $res = $sth->execute( $vals );
                        $pado->queries[] = !empty( $vals )
                                         ? $sql . ' / values = ' . join( ', ', $vals )
                                         : $sql;
                        ++$pado->query_cnt;
                        $pado->stash( $sql, 1 );
                    } catch ( PDOException $e ) {
                        $message = 'PDOException: ' . $e->getMessage() . ", {$sql}";
                        $pado->errors[] = $message;
                        trigger_error( $message );
                        return false;
                    }
                    $sth = null;
                }
            }
            if ( $pado->can_drop ) {
                $delete = $column_defs['delete'];
                if (! empty( $delete ) ) {
                    foreach ( $delete as $name => $props ) {
                        $sql = "ALTER TABLE {$table} DROP {$colprefix}{$name}";
                        if ( $pado->stash( $sql ) ) continue;
                        if ( $pado->debug === 3 ) $pado->debugPrint( $sql );
                        $sth = $pdo->prepare( $sql );
                        try {
                            $res = $sth->execute();
                            $pado->queries[] = $sql;
                            ++$pado->query_cnt;
                            $pado->stash( $sql, 1 );
                        } catch ( PDOException $e ) {
                            $message = 'PDOException: ' . $e->getMessage() . ", {$sql}";
                            $pado->errors[] = $message;
                            trigger_error( $message );
                        }
                        $sth = null;
                    }
                }
            }
        }
        $indexes = $upgrade['indexes'];
        if ( is_array( $indexes ) ) {
            $update = array_merge( $indexes['delete'], $indexes['changed'] );
            if (! empty( $update ) ) {
                foreach ( $update as $name => $props ) {
                    if ( $name === 'PRIMARY' ) {
                        $message = 'PADOBaseModelException: PRIMARY KEY could not be changed.';
                        $pado->errors[] = $message;
                        trigger_error( $message );
                        continue;
                    }
                    if ( isset( $indexes['changed'][ $name ] ) || $this->pado()->can_drop ) {
                        if ( isset( $sql ) ) {
                            if ( $pado->stash( $sql ) ) continue;
                            if ( $pado->debug === 3 ) $pado->debugPrint( $sql );
                            $sth = $pdo->prepare( $sql );
                            try {
                                $res = $sth->execute();
                                $pado->queries[] = $sql;
                                ++$pado->query_cnt;
                                $pado->stash( $sql, 1 );
                            } catch ( PDOException $e ) {
                                $message = 'PDOException: ' . $e->getMessage() . ", {$sql}";
                                $pado->errors[] = $message;
                                trigger_error( $message );
                            }
                            $sth = null;
                        }
                    }
                }
            }
            $update = array_merge( $indexes['new'], $indexes['changed'] );
            if (! empty( $update ) ) {
                foreach ( $update as $name => $props ) {
                    if (! is_array( $props ) ) $props = explode( ',', $props );
                    if ( $colprefix ) {
                        array_walk( $props, function( &$col, $num, $pfx = null ){
                            $col = strpos( $col, $pfx ) !== 0 ? $pfx . $col : $col;
                        }, $colprefix );
                    }
                    $props = join( ',', $props );
                    if ( $name === 'PRIMARY' ) {
                        trigger_error(
                        'PADOBaseModelException: PRIMARY KEY could not be changed.' );
                        continue;
                    } else {
                        $name = $table . '_' . $name;
                        $sql = "CREATE INDEX {$name} ON {$table}({$props})";
                        if ( $pado->stash( $sql ) ) continue;
                        if ( $pado->debug === 3 ) $pado->debugPrint( $sql );
                    }
                    $sth = $pdo->prepare( $sql );
                    try {
                        $res = $sth->execute();
                        $pado->queries[] = $sql;
                        ++$pado->query_cnt;
                        $pado->stash( $sql, 1 );
                    } catch ( PDOException $e ) {
                        trigger_error( 'PDOException: ' . $e->getMessage() . ", {$sql}" );
                        return false;
                    }
                    $sth = null;
                }
            }
        }
        return $res;
    }

/**
 * BULK INSERT or UPDATE the objects.
 */
    function update_multi ( $objects, $update = true, $unique_key = '', $cols = '*' ) {
        // todo bulk_update_per = 1000?
        if ( empty( $objects ) ) return;
        $pado = $this->pado();
        $model = $this->_model;
        $i = 0;
        $object_keys = '';
        $vals = [];
        $stmts = [];
        $unique_keys = [];
        $update_stmt = '';
        $model = '';
        list( $id_column, $colprefix, $table ) = ['', '', ''];
        foreach ( $objects as $obj ) {
            if (! $i ) {
                $id_column = $obj->_id_column;
                $colprefix = $obj->_colprefix;
                $table = $obj->_table;
                $model = $obj->_model;
            }
            if ( $model && $model != $obj->_model ) {
                $message = 'PADOBaseModelException: Wrong model in objects.';
                $pado->errors[] = $message;
                trigger_error( $message );
                return;
            }
            $i++;
            $callback = ['name' => 'save_filter'];
            $save_filter = $pado->run_callbacks( $callback, $obj->_model, $obj, true );
            if (! $save_filter ) continue;
            $arr = $obj->get_values();
            $id = $obj->$id_column;
            $arr = $obj->validation( $arr, $update );
            ksort( $arr );
            unset( $arr[ $id_column ] );
            $keys = array_keys( $arr );
            array_unshift( $keys, $id_column );
            if (! $object_keys ) {
                $object_keys = join( ',', $keys );
                $updates = [];
                foreach ( $keys as $key ) {
                    $updates[] = "{$key}=VALUES({$key})";
                }
                $update_stmt = join( ',', $updates );
            } else {
                if ( $object_keys !== join( ',', $keys ) ) {
                    $message = 'PADOBaseModelException: Unequal column designation.';
                    $pado->errors[] = $message;
                    trigger_error( $message );
                    return;
                }
            }
            $values = array_values( $arr );
            if (! $id ) $id = "''";
            $stmt = "({$id},";
            foreach ( $values as $v ) {
                $stmt .= '?,';
            }
            $stmt = rtrim( $stmt, ',' );
            $stmt .= ')';
            $stmts[] = $stmt;
            $vals = array_merge( $vals, $values );
            $callback = ['name' => 'pre_save'];
            $pado->run_callbacks( $callback, $obj->_model, $obj );
            if ( $unique_key && $obj->has_column( $unique_key ) ) {
                $unique_keys[] = $obj->$unique_key;
            }
        }
        $sql = "INSERT INTO {$table} ($object_keys) VALUES ";
        $sql .= join( ',', $stmts );
        $sql .= "ON DUPLICATE KEY UPDATE {$update_stmt}";
        if ( $pado->query_cnt > $pado->max_queries && ! $pado->txn_active ) {
            $pado->reconnect();
        }
        $pdo = $pado->db;
        $sth = $pdo->prepare( $sql );
        try {
            $res = $sth->execute( $vals );
            if ( $pado->debug === 3 ) $pado->debugPrint( $sql );
            $pado->queries[] = !empty( $vals )
                             ? $sql . ' / values = ' . join( ', ', $vals )
                             : $sql;
            ++$pado->query_cnt;
        } catch ( PDOException $e ) {
            $message = 'PDOException: ' . $e->getMessage() . ", {$sql}";
            $pado->errors[] = $message;
            trigger_error( $message );
            return $this->retry( $pado, $sth, $message, 'update_multi', $objects, $update );
        }
        $sth = null;
        if ( $unique_key ) {
            if (! empty( $unique_keys ) ) {
                $terms = [ $unique_key => ['IN' => $unique_keys ] ];
                return $this->load( $terms, null, $cols );
            }
            return [];
        }
        return true;
    }

/**
 * BULK Remove the objects.
 */
    function remove_multi ( $objects ) {
        if ( empty( $objects ) ) return;
        $pado = $this->pado();
        $i = 0;
        $ids = [];
        list( $id_column, $colprefix, $table ) = ['', '', ''];
        $model = '';
        foreach ( $objects as $obj ) {
            if (! $i ) {
                $id_column = $obj->_id_column;
                $table = $obj->_table;
                $colprefix = $obj->_colprefix;
                $model = $obj->_model;
            }
            if ( $model && $model != $obj->_model ) {
                $message = 'PADOBaseModelException: Wrong model in objects.';
                $pado->errors[] = $message;
                trigger_error( $message );
                return;
            }
            $i++;
            $callback = ['name' => 'delete_filter'];
            $delete_filter = $pado->run_callbacks( $callback, $obj->_model, $obj, true );
            if (! $delete_filter ) continue;
            $id = (int) $obj->$id_column;
            if (! $id ) continue;
            $ids[] = $id;
        }
        if ( empty( $ids ) ) return;
        $ids = join( ',', $ids );
        $sql = "DELETE FROM {$table} WHERE {$colprefix}id IN ({$ids})";
        if ( $pado->query_cnt > $pado->max_queries && ! $pado->txn_active ) {
            $pado->reconnect();
        }
        $pdo = $pado->db;
        $sth = $pdo->prepare( $sql );
        try {
            $res = $sth->execute();
            if ( $pado->debug === 3 ) $pado->debugPrint( $sql );
            $pado->queries[] = $sql;
            ++$pado->query_cnt;
            $sth = null;
            return true;
        } catch ( PDOException $e ) {
            $message = 'PDOException: ' . $e->getMessage() . ", {$sql}";
            $pado->errors[] = $message;
            trigger_error( $message );
            return $this->retry( $pado, $sth, $message, 'remove_multi', $objects );
        }
    }

/**
 * Create a new table.
 */
    function create_table ( $model, $table, $colprefix, $scheme, $set_scheme = false ) {
        $column_defs = $scheme['column_defs'];
        $indexes = $scheme['indexes'];
        $primary = $indexes['PRIMARY'];
        $primary = is_array( $primary ) ? join( ',', $primary ) : $primary;
        $props = $column_defs[ $primary ];
        $size = isset( $props['size'] ) ? $props['size'] : 0;
        $vals = [];
        $default = ' ';
        if ( isset( $props['default'] ) ) {
            $default = ' DEFAULT ? ';
            $vals[] = $props['default'];
        }
        $type = strtoupper( $props['type'] );
        $not_null = isset( $props['not_null'] ) ? ' NOT NULL ' : ' ';
        $type = $size ? $type . '(' . $size . ')' : $type;
        $sql  = "CREATE TABLE {$table} (\n";
        $sql .= "{$colprefix}{$primary} {$type}{$not_null}AUTO_INCREMENT,"
             .  "PRIMARY KEY ({$colprefix}{$primary}) )";
        if ( self::$_engine ) $sql .= ' ENGINE=' . self::$_engine;
        $pado = $this->pado();
        $charset = $pado->dbcharset;
        if ( $charset ) $sql .= ' DEFAULT CHARSET=' . $charset;
        if ( $pado->debug === 3 ) $pado->debugPrint( $sql );
        if ( $pado->query_cnt > $pado->max_queries && ! $pado->txn_active ) {
            $pado->reconnect();
        }
        $sth = $pado->db->prepare( $sql );
        try {
            $res = $sth->execute( $vals );
            $pado->queries[] = $sql;
            ++$pado->query_cnt;
            if ( $pado->upgrader ) {
                if ( $set_scheme ) $this->_scheme = $scheme;
                $upgrade = $this->check_upgrade( $model, $table, $colprefix );
                if ( $upgrade !== false )
                    return $this->upgrade( $table, $upgrade, $colprefix );
            }
            $sth = null;
            return $res;
        } catch ( PDOException $e ) {
            $message = 'PDOException: ' . $e->getMessage() . ", {$sql}";
            $pado->errors[] = $message;
            trigger_error( $message );
            return $this->retry( $pado, $sth, $message, 'create_table', 
                $model, $table, $colprefix, $scheme );
        }
    }
}