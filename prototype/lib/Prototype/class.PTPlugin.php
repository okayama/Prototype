<?php
class PTPlugin {

    public $dictionary = [];
    public $path;
    public $name;

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
        $app->components[ $class_name ] = $this;
        $app->ctx->components[ $class_name ] = $this;
        $app->ctx->include_paths[ dirname( $path ) . DS . 'alt-tmpl' ] = true;
        $app->ctx->include_paths[ dirname( $path ) . DS . 'tmpl' ] = true;
        $paths = array_keys( $app->ctx->include_paths );
        usort( $paths, function( $a, $b ) {
            return strlen( $b ) - strlen( $a );
        });
        $app->ctx->include_paths = [];
        foreach( $paths as $path ) {
            $app->ctx->include_paths[ $path ] = true;
        }
    }

    function translate ( $phrase, $params = '', $lang = null ) {
        $app = Prototype::get_instance();
        $lang = $lang ? $lang : $app->language;
        if (! $lang ) $lang = 'default';
        $dict = isset( $this->dictionary ) ? $this->dictionary : null;
        if ( $dict && isset( $dict[ $lang ] ) && isset( $dict[ $lang ][ $phrase ] ) )
             $phrase = $dict[ $lang ][ $phrase ];
        $phrase = is_string( $params )
            ? sprintf( $phrase, $params ) : vsprintf( $phrase, $params );
        return $phrase;
    }

    function path () {
        return dirname( $this->path );
    }

    function manage_plugins ( $app ) {
        $plugin_switch = $app->plugin_switch;
        $cfg_settings = $app->cfg_settings;
        $counter = 0;
        if ( $_type = $app->param( '_type' ) ) {
            $app->validate_magic();
            if ( $_type === 'enable' || $_type === 'disable' ) {
                if ( $app->workspace() ) {
                    return $app->error( 'Invalid request.' );
                }
                $status = $_type === 'enable' ? 1 : 0;
                $plugin_ids = $app->param( 'plugin_id' );
                foreach ( $plugin_ids as $plugin_id ) {
                    if (! isset( $plugin_switch[ $plugin_id ] ) ) {
                        return $app->error( 'Invalid request.' );
                    }
                    $obj = $plugin_switch[ $plugin_id ];
                    if ( $obj->number != $status ) {
                        $obj->number( $status );
                        $obj->save();
                        $counter++;
                    }
                }
            }
            $app->redirect( $app->admin_url .
            "?__mode=manage_plugins&action_type={$_type}&saved=1&count={$counter}" );
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
                foreach ( $keys as $key ) {
                    $var = $app->param( 'setting_' . $key );
                    $terms['key'] = $key;
                    $setting_obj = $app->db->model( 'option' )->get_by_key( $terms );
                    if ( $setting_obj->value != $var ) {
                        $setting_obj->value( $var );
                        $setting_obj->save();
                    }
                }
                $ctx->vars['config_saved'] = 1;
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
        foreach ( $cfg_settings as $key => $cfg ) {
            $switch = $plugin_switch[ $key ];
            $cfg['status'] = $switch->number;
            $plugins_loop[ $key ] = $cfg;
        }
        $ctx->local_vars['plugins_loop'] = $plugins_loop;
        return $app->__mode( 'manage_plugins' );
    }

    function get_config_value ( $name, $ws_id = 0 ) {
        $app = Prototype::get_instance();
        $plugin_id = strtolower( get_class( $this ) );
        $terms = ['extra' => $plugin_id, 'key' => $name, 'workspace_id' => $ws_id ];
        $setting_obj = $app->db->model( 'option' )->get_by_key( $terms );
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
}