<?php
class PTPlugin {

    public $dictionary = [];
    public $path;
    public $name;
    public $configs;
    public $version;

    function __construct () {
        $app = Prototype::get_instance();
        $path = debug_backtrace()[0]['file'];
        $this->path = $path;
        $locale = dirname( $path )
            . DS . 'locale' . DS . $app->language . '.json';
        if ( file_exists( $locale ) ) {
            $locale = json_decode( file_get_contents( $locale ), true );
            $this->dictionary[ $app->language ] = $locale;
        }
        $this->name = get_class( $this );
        $class_name = strtolower( get_class( $this ) );
        if ( isset( $app->plugin_configs[ $class_name ] ) ) {
            $this->configs = $app->plugin_configs[ $class_name ];
        }
        $app->components[ $class_name ] = $this;
        $app->ctx->components[ $class_name ] = $this;
        $include_paths = [ dirname( $path ) . DS . 'alt-tmpl' => true,
                           dirname( $path ) . DS . 'tmpl' => true ];
        $app->ctx->include_paths =
            array_merge( $include_paths, $app->ctx->include_paths );
    }

    function __get ( $name ) {
        if ( $name == 'version' ) return $this->version();
        if ( isset( $this->configs[ $name ] ) ) {
            return $this->configs[ $name ];
        }
    }

    function version () {
        if ( $this->version ) return $this->version;
        $app = Prototype::get_instance();
        $versions = $app->versions;
        $class = strtolower( get_class( $this ) );
        if ( isset( $versions[ $class ] ) ) {
            $this->version = $versions[ $class ];
            return $versions[ $class ];
        }
        $cfg = $this->path() . DS .'config.json';
        if ( is_file( $cfg ) ) {
            $cfgs = json_decode( file_get_contents( $cfg ) );
            return $cfgs->version;
        }
    }

    function translate ( $phrase, $params = '', $lang = null ) {
        $app = Prototype::get_instance();
        if (! $lang ) {
            if ( $app->user() ) {
                $lang = $app->user()->language;
            } else {
                $lang = $app->language;
            }
        }
        if (! $lang ) $lang = 'default';
        $dict = isset( $this->dictionary ) ? $this->dictionary : null;
        if ( $dict && isset( $dict[ $lang ] ) && isset( $dict[ $lang ][ $phrase ] ) )
             $phrase = $dict[ $lang ][ $phrase ];
        // if ( is_string( $params ) && $params ) $params = htmlspecialchars( $params );
        $phrase = is_string( $params )
            ? sprintf( $phrase, $params ) : vsprintf( $phrase, $params );
        return $phrase;
    }

    function path () {
        return dirname( $this->path );
    }

    function manage_plugins ( $app ) {
        $workspace = $app->workspace()
                   ? $app->workspace() : $app->db->model( 'workspace' )->new( ['id' => 0 ] );
        if (! $app->can_do( 'manage_plugins', null, null, $workspace ) ) {
            $app->error( 'Permission denied.' );
        }
        $plugin_switch = $app->plugin_switch;
        $cfg_settings = $app->cfg_settings;
        $counter = 0;
        if ( $_type = $app->param( '_type' ) ) {
            $app->validate_magic();
            if ( $_type === 'enable' || $_type === 'disable' || $_type === 'upgrade' ) {
                if ( $app->workspace() ) {
                    return $app->error( 'Invalid request.' );
                }
                $status = $_type === 'disable' ? 0 : 1;
                $plugin_ids = $app->param( 'plugin_id' );
                $db = $app->db;
                foreach ( $plugin_ids as $plugin_id ) {
                    if (! isset( $plugin_switch[ $plugin_id ] ) ) {
                        return $app->error( 'Invalid request.' );
                    }
                    $component = $app->component( $plugin_id );
                    $version = 0;
                    $version = $component ? $component->version() : 0;
                    $obj = $plugin_switch[ $plugin_id ];
                    if ( $obj->number != $status || $obj->value != $version
                        || $_type === 'upgrade' ) {
                        if ( $status && $component ) {
                            $locale = $component->path() . DS . 'locale';
                            if ( is_dir( $locale ) ) {
                                if ( $handle = opendir( $locale ) ) {
                                    while ( false !== ( $entry = readdir( $handle ) ) ) {
                                        if ( strpos( $entry, '.' ) === 0 ) continue;
                                        $file = $locale . DS . $entry;
                                        $extension = pathinfo( $file )['extension'];
                                        if ( $extension != 'csv' ) continue;
                                        $content = file_get_contents( $file );
                                        $encoding = mb_detect_encoding( $content );
                                        if ( $encoding != 'UTF-8' ) {
                                            $content = mb_convert_encoding( $content, 'UTF-8', 'Shift_JIS' );
                                        }
                                        $content = preg_replace( "/\r\n|\r|\n/", PHP_EOL, $content );
                                        $lines = explode( PHP_EOL, $content );
                                        $lang = basename( $entry, '.csv' );
                                        $name = strtolower( $component->id );
                                        foreach ( $lines as $line ) {
                                            $valus = str_getcsv( $line );
                                            list ( $phrase, $trans ) = $valus;
                                            $phrase = $db->model( 'phrase' )->get_by_key
                                            ( ['phrase' => ['BINARY' => $phrase ],
                                               'component' => $name, 'lang' => $lang ] );
                                            $phrase->trans( $trans );
                                            $app->set_default( $phrase );
                                            $phrase->save();
                                        }
                                    }
                                }
                                if ( property_exists( $component, 'upgrade_functions' ) ) {
                                    $upgrade_functions = $component->upgrade_functions;
                                    foreach ( $upgrade_functions as $upgrade_function ) {
                                        $version_limit = isset( $upgrade_function['version_limit'] )
                                                       ? $upgrade_function['version_limit'] : 0;
                                        if ( $obj->value < $version_limit ) {
                                            $meth = $upgrade_function['method'];
                                            if ( method_exists( $component, $meth ) ) {
                                                $component->$meth( $app, $this, $obj->value );
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        $obj->number( $status );
                        if (! $obj->value || $_type === 'upgrade' ) {
                            $obj->value( $version );
                        }
                        $obj->save();
                        $counter++;
                    }
                }
            }
            $app->redirect( $app->admin_url .
            "?__mode=manage_plugins&action_type={$_type}&saved=1&count={$counter}" );
            exit();
        }
        $ctx = $app->ctx;
        if ( $app->param( 'edit_settings' ) ) {
            $plugin_id = $app->param( 'plugin_id' );
            if (! isset( $cfg_settings[ $plugin_id ] ) ) {
                return $app->error( 'Invalid request.' );
            }
            $cfg_setting = $cfg_settings[ $plugin_id ];
            $component = $app->component( $cfg_setting['component'] );
            if (! $component )
                $component = $app->autoload_component( $cfg_setting['component'] );
            if (! $component ) {
                return $app->error( 'Invalid request.' );
            }
            $has_config = false;
            if ( $app->workspace() && isset( $cfg_setting['cfg_space'] ) &&
                $cfg_setting['cfg_space'] ) {
                $has_config = true;
            } else if (! $app->workspace() && isset( $cfg_setting['cfg_system'] ) &&
                $cfg_setting['cfg_system'] ) {
                $has_config = true;
            }
            $cfg_tmpl = '';
            if ( isset( $cfg_setting['cfg_template'] ) &&
                $cfg_setting['cfg_template'] ) {
                $cfg_tmpl = $cfg_setting['cfg_template'];
                $cfg_tmpl = $component->path() . DS . 'tmpl' . DS . $cfg_tmpl;
                if (! file_exists( $cfg_tmpl ) ) {
                    $has_config = false;
                }
            } else {
                $has_config = false;
            }
            if (! $has_config ) {
                return $app->error( 'Invalid request.' );
            }
            $terms = ['extra' => $plugin_id, 'kind' => 'plugin_setting'];
            if ( $app->workspace() ) {
                $terms['workspace_id'] = $app->workspace()->id;
            } else {
                $terms['workspace_id'] = 0;
            }
            $settings = $cfg_setting['settings'];
            if ( $app->param( 'save_config' ) ) {
                $app->validate_magic();
                $keys = array_keys( $settings );
                $app->db->caching = false;
                $action = $app->param( 'reset_config' ) ? 'reset' : 'saved';
                foreach ( $keys as $key ) {
                    $var = $app->param( 'setting_' . $key );
                    $terms['key'] = $key;
                    $setting_obj = $app->db->model( 'option' )->get_by_key( $terms );
                    if ( $action == 'saved' ) {
                        if ( $setting_obj->value != $var ) {
                            $setting_obj->value( $var );
                            $setting_obj->save();
                        }
                    } else if ( $setting_obj->id ) {
                        $setting_obj->remove();
                    }
                }
                $ctx->vars["config_{$action}"] = 1;
            }
            $label = $app->translate( $cfg_setting['label'], null, $component );
            $ctx->vars['page_title'] = $app->translate( 'Plugin %s\'s Settings', $label );
            $ctx->vars['plugin_label'] = $label;
            $ctx->vars['plugin_id'] = $plugin_id;
            if ( $app->workspace() ) {
                $ctx->vars['page_title']
                    .= ' (' . htmlspecialchars( $app->workspace()->name ) . ')';
            }
            foreach ( $settings as $key => $value ) {
                $ctx->vars['setting_' . $key ] = $value;
            }
            $setting_objs = $app->db->model( 'option' )->load( $terms );
            foreach ( $setting_objs as $setting_obj ) {
                $ctx->vars['setting_' . $setting_obj->key ] = $setting_obj->value;
            }
            $required = '<i class="fa fa-asterisk required" aria-hidden="true"></i>';
            $required .= '<span class="sr-only">' . $app->translate( 'Required' ) . '</span>';
            $ctx->vars['field_required'] = $required;
            $cfg_tmpl = $ctx->build_page( $cfg_tmpl );
            $ctx->vars['cfg_tmpl'] = $cfg_tmpl;
            $ctx->vars['this_mode'] = $app->mode;
            $app->assign_params( $app, $ctx );
            return $app->build_page( 'plugin_config.tmpl' );
        }
        $plugins_loop = [];
        $upgrade_count = 0;
        $scheme_upgrade_count = 0;
        foreach ( $cfg_settings as $key => $cfg ) {
            $switch = $plugin_switch[ $key ];
            $cfg['status'] = $switch->number;
            if (! $app->workspace() ) {
                $old_version = $switch->value;
                if ( $cfg['version'] > $old_version && $cfg['status'] ) {
                    $cfg['upgrade'] = true;
                    $ctx->local_vars['need_upgrade'] = true;
                    $upgrade_count++;
                }
                if ( $old_version ) {
                    $cfg['version'] = $old_version;
                }
            }
            if ( $cfg['status'] && $app->user()->is_superuser ) {
                $component = $app->component( $key );
                $models_dir = $component->path() . DS . 'models';
                if ( is_dir( $models_dir ) ) {
                    if ( $handle = opendir( $models_dir ) ) {
                        while ( false !== ( $entry = readdir( $handle ) ) ) {
                            if ( strpos( $entry, '.' ) === 0 ) continue;
                            $file = $models_dir . DS . $entry;
                            $extension = pathinfo( $file )['extension'];
                            if ( $extension != 'json' ) continue;
                            $scheme = json_decode( file_get_contents( $file ) );
                            $name = basename( $entry, '.json' );
                            $table = $app->get_table( $name );
                            if (! $table || $table->version < $scheme->version ) {
                                $ctx->local_vars['scheme_upgrade'] = true;
                                $scheme_upgrade_count++;
                                $cfg['upgrade_scheme'] = true;
                            }
                        }
                    }
                }
            }
            $plugins_loop[ $key ] = $cfg;
        }
        $ctx->local_vars['upgrade_count'] = $upgrade_count;
        $ctx->local_vars['plugin_scheme_upgrade_count'] = $scheme_upgrade_count;
        if ( $scheme_upgrade_count ) {
            $ctx->local_vars['scheme_upgrade_count'] = $scheme_upgrade_count;
            $cfg = $app->db->model( 'option' )->get_by_key(
                ['kind' => 'config', 'key' => 'upgrade_count'] );
            $cfg->value( $scheme_upgrade_count );
            $cfg->data( time() );
            $cfg->save();
        }
        $ctx->local_vars['plugins_loop'] = $plugins_loop;
        return $app->__mode( 'manage_plugins' );
    }

    function get_config_value ( $name, $ws_id = 0, $inheritance = false ) {
        $app = Prototype::get_instance();
        $plugin_id = strtolower( get_class( $this ) );
        $terms = ['extra' => $plugin_id, 'key' => $name, 'workspace_id' => $ws_id,
                  'kind' => 'plugin_setting'];
        $setting_obj = $app->db->model( 'option' )->get_by_key( $terms );
        if ( $ws_id && $inheritance && !$setting_obj->id ) {
            $terms['workspace_id'] = 0;
            $setting_obj = $app->db->model( 'option' )->get_by_key( $terms );
        }
        if ( $setting_obj->id ) {
            return $setting_obj->value;
        }
        $cfg_settings = $app->cfg_settings;
        if ( isset( $cfg_settings[ $plugin_id ] ) ) {
            $cfg_settings = $cfg_settings[ $plugin_id ];
            if ( isset( $cfg_settings['settings'] ) ) {
                if ( isset( $cfg_settings['settings'][ $name ] ) ) {
                    return $cfg_settings['settings'][ $name ];
                }
            }
        }
    }

    function set_config_value ( $name, $value, $ws_id = 0 ) {
        $app = Prototype::get_instance();
        $plugin_id = strtolower( get_class( $this ) );
        $terms = ['extra' => $plugin_id, 'key' => $name,
                  'workspace_id' => $ws_id, 'kind' => 'plugin_setting'];
        $setting_obj = $app->db->model( 'option' )->get_by_key( $terms );
        $setting_obj->value( $value );
        $setting_obj->save();
    }
}