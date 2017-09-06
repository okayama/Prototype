<?php

class PTUpgrader {

    protected $reserved = ['magic_token', 'tags', 'additional_tags', 'created_on',
                           'created_by', 'workspace_id', 'order', 'status', 'modified_on',
                           'modified_by', 'published_on', 'unpublished_on', 'user_id',
                           'basename', 'delete', 'remove', 'save', 'has_deadline',
                           'rev_type', 'rev_object_id', 'rev_note', 'rev_changed',
                           'rev_diff', 'workspace_id', 'allow_comment', 'comment_count'];

    function upgrade () {
        $app = Prototype::get_instance();
        if ( $app->installed ) {
            $app->error( 'Invalid request.' );
        }
        $tmpl = TMPL_DIR . 'upgrade.tmpl';
        $ctx = $app->ctx;
        $ctx->vars['language'] = $app->language;
        if ( $app->param( '_type' ) === 'install' ) {
            $ctx->vars['page_title'] = $app->translate( 'Install' );
        } else {
            $ctx->vars['page_title'] = $app->translate( 'Upgrade' );
        }
        if ( $app->request_method === 'POST' ) {
            if ( $app->param( '_type' ) === 'install' ) {
                $name = $app->param( 'name' );
                $pass = $app->param( 'password' );
                $verify = $app->param( 'password-verify' );
                $email = $app->param( 'email' );
                $appname = $app->param( 'appname' );
                $site_path = $app->param( 'site_path' );
                $site_url = $app->param( 'site_url' );
                $language = $app->param( 'sys_language' );
                $extra_path = $app->param( 'extra_path' );
                $asset_publish = $app->param( 'asset_publish' );
                $copyright = $app->param( 'copyright' );
                $system_email = $app->param( 'system_email' );
                $errors = [];
                if (!$appname || !$site_url || !$system_email || !$site_path ) {
                    $errors[] = $app->translate(
                        'App Name, System Email Site URL and Site Path are required.' );
                }
                if (!$name || !$pass || !$email ) {
                    $errors[] = $app->translate( 'Name, Password and Email are required.' );
                } else {
                    if (!$app->is_valid_email( $system_email, $msg ) ) {
                        $errors[] = $msg;
                    }
                    if (!$app->is_valid_password( $pass, $verify, $msg ) ) {
                        $errors[] = $msg;
                    }
                    if (!$app->is_valid_email( $email, $msg ) ) {
                        $errors[] = $msg;
                    }
                    if ( $site_url && !$app->is_valid_url( $site_url, $msg ) ) {
                        $errors[] = $msg;
                    }
                    if (! preg_match( '/\/$/', $site_url ) ) {
                        $site_url .= '/';
                    }
                    $app->sanitize_dir( $extra_path );
                    if ( $extra_path &&
                        !$app->is_valid_property( str_replace( '/', '', $extra_path ) ) ) {
                        $errors[] = $app->translate(
                            'Upload Path contains an illegal character.' );
                    }
                }
                if (! empty( $errors ) ) {
                    $app->assign_params( $app, $ctx, true );
                    $ctx->vars['error'] = join( "\n", $errors );
                    echo $ctx->build_page( $tmpl );
                    exit();
                }
                $default_widget = TMPL_DIR . 'import' . DS . 'default_widget.tmpl';
                $default_widget = file_get_contents( $default_widget );
                $db = $app->db;
                $cfgs = ['appname'    => $appname,
                         'site_path'  => $site_path,
                         'site_url'   => $site_url,
                         'extra_path' => $extra_path,
                         'language'   => $language,
                         'copyright'  => $copyright,
                         'system_email' => $system_email,
                         'asset_publish' => $asset_publish,
                         'default_widget' => $default_widget ];
                $app->set_config( $cfgs );
                $password = $app->param( 'password' );
                $language = $app->param( 'language' );
                $nickname = $app->param( 'nickname' );
                $password = password_hash( $password, PASSWORD_BCRYPT );
                $db->upgrader = true;
                $this->setup_db( true );
                $plugin_models = $this->plugin_models( true );
                if (! empty( $plugin_models ) ) {
                    $m_items = [];
                    foreach ( $plugin_models as $m_dir => $props ) {
                        foreach ( $props as $prop ) {
                            $arr = [ $prop['component'], $m_dir ];
                            $uniqkey = json_encode( $arr );
                            $models = isset( $m_items[ $uniqkey ] )
                                    ? $m_items[ $uniqkey ] : [];
                            $models[] = $prop['model'];
                            $m_items[ $uniqkey ] = $models;
                        }
                    }
                    if (! empty( $m_items ) ) {
                        foreach ( $m_items as $m_key => $m_item ) {
                            list( $component, $m_dir ) = json_decode( $m_key, true );
                            $this->setup_db( true, $component, $m_item, $m_dir );
                        }
                    }
                }
                $user = $db->model( 'user' )->get_by_key( ['name' => $name ] );
                $user->name( $name );
                $user->password( $password );
                $user->email( $email );
                $user->nickname( $nickname );
                $user->language( $language ); // White List Check
                $user->is_superuser( 1 );
                $user->modified_on( date( 'YmdHis' ) );
                $user->created_on( date( 'YmdHis' ) );
                $user->status( 2 );
                $user->save();
                $app->redirect( $app->admin_url );
            }
        } else {
            $ctx->vars['site_url'] = $app->base . '/prototype/site/';
            $ctx->vars['site_path'] = $app->document_root . DS . 'prototype' . DS . 'site';
            $ctx->vars['extra_path'] = 'assets/';
            $ctx->vars['language'] = $app->language;
            $ctx->vars['sys_language'] = $app->language;
        }
        echo $ctx->build_page( $tmpl );
        exit();
    }

    function upgrade_scheme ( $name ) {
        $app = Prototype::get_instance();
        $db = $app->db;
        $table = $app->get_table( $name );
        if (! $table ) return;
        $table_id =  $table->id;
        $columns = $db->model( 'column' )->load( ['table_id' => $table_id,
                                                  'type' => ['not' => 'relation'] ] );
        list( $column_defs, $indexes ) = [ [], [] ];
        foreach ( $columns as $column ) {
            $col_name = $column->name;
            $props = [];
            $props['type'] = $column->type;
            if ( $column->size ) $props['size'] = $column->size;
            $not_null = $column->not_null;
            if ( $not_null ) $props['not_null'] = 1;
            if ( $column->default !== null ) $props['default'] = $column->default;
            $column_defs[ $column->name ] = $props;
            if ( $column->is_primary ) $indexes['PRIMARY'] = $col_name;
            if ( $column->index ) $indexes[ $col_name ] = $col_name;
        }
        $model  = $db->model( $name );
        $scheme = $model->get_scheme(
            $model->_model, $model->_table, $model->_colprefix, true );
        $comp_defs = $scheme['column_defs'] ? $scheme['column_defs'] : [];
        $comp_idxs = $scheme['indexes'];
        foreach ( $column_defs as $key => $props ) {
            unset( $column_defs[ $key ]['default'] );
            if ( $column_defs[ $key ]['type'] === 'relation' ) {
                unset( $column_defs[ $key ] );
            }
        }
        foreach ( $comp_defs as $key => $props ) {
            unset( $comp_defs[ $key ]['default'] );
        }
        $upgrade = $model->array_compare( $column_defs, $comp_defs );
        $upgrade_idx = $model->array_compare( $indexes, $comp_idxs );
        if ( $upgrade || $upgrade_idx ) {
            $upgrade_cols = $model->get_diff( $column_defs, $comp_defs );
            $upgrade_idxs = $model->get_diff( $indexes, $comp_idxs );
            $upgrade = ['column_defs' => $upgrade_cols, 'indexes' => $upgrade_idxs ];
            $db->drop = false;
            return $model->upgrade( $model->_table, $upgrade, $model->_colprefix );
        }
    }

    function manage_scheme ( $app ) {
        if (! $app->user()->is_superuser ) {
            $app->error( 'Permission denied.' );
        }
        $schemes = $app->db->model( 'option' )->load( ['kind' => 'scheme_version'] );
        $items = [];
        $current_scheme = $app->db->scheme;
        $upgrade_count = 0;
        $model_files = [];
        $model_names = [];
        $components = [];
        $cfg_settings = $app->cfg_settings;
        foreach ( $schemes as $item ) {
            $model = $item->key;
            $model_names[] = $model;
            $component = $item->extra;
            $models_dir = null;
            if ( $component && $component !== 'core' ) {
                $plugin = $app->component( $component );
                if (! $plugin ) {
                    $plugin = $app->autoload_component( $plugin );
                }
                if ( $plugin && is_object( $plugin ) ) {
                    $models_dir = $plugin->path() . DS . 'models';
                }
            } else {
                $models_dir = LIB_DIR . 'PADO' . DS . 'models';
            }
            if ( $models_dir ) {
                $file = $models_dir . DS . $model . '.json';
                if ( is_readable( $file ) ) {
                    $model_files[ $model ] = $file;
                    $scheme = json_decode( file_get_contents( $file ), true );
                    $scheme_version = isset( $scheme['version'] ) ? $scheme['version'] : '';
                    $db_version = $item->value;
                    if ( $db_version < $scheme_version ) {
                        $upgrade_count++;
                    }
                    $component = $component == 'core' ? 'Prototype' : $component;
                    $info = ['model' => $model, 'scheme_version' => $scheme_version,
                             'db_version' => $db_version, 'component' => $component ];
                    if ( isset( $cfg_settings[ $component ] ) &&
                        isset( $cfg_settings[ $component ]['label'] ) ) {
                        $cfg_setting = $cfg_settings[ $component ];
                        $plugin = $app->component( $component );
                        $component = $app->translate( $cfg_settings[ $component ]['label'],
                                        null, $plugin );
                        $info['component'] = $component;
                    }
                    $items[] = $info;
                    $components[ $model ] = $component;
                }
            }
        }
        $json_dirs = array_keys( $this->plugin_models( true ) );
        array_unshift( $json_dirs, LIB_DIR . 'PADO' . DS . 'models' );
        $i = 0;
        foreach ( $json_dirs as $dir ) {
            $files = scandir( $dir, $app->plugin_order );
            foreach ( $files as $json ) {
                if ( strpos( $json, '.' ) === 0 ) continue;
                $file = $dir . DS . $json;
                $extension = pathinfo( $json )['extension'];
                if ( $extension !== 'json' ) continue;
                $model = pathinfo( $json )['filename'];
                if (! in_array( $model, $model_names ) ) {
                    $component = $i
                               ? strtolower( basename( dirname( dirname( $file ) ) ) )
                               : 'core';
                    $data = json_decode( file_get_contents( $file ), true );
                    if ( isset( $data['component'] ) ) $component = $data['component'];
                    $version = isset( $data['version'] ) ? $data['version'] : 0;
                    $info = ['model' => $model, 'scheme_version' => $version,
                             'db_version' => 0, 'component' => $component ];
                    $items[] = $info;
                    $upgrade_count++;
                    $model_files[ $model ] = $file;
                    $components[ $model ] = $component;
                }
            }
            $i++;
        }
        if ( $app->request_method === 'POST' &&
            $app->param( '_type' ) && $app->param( '_type' ) === 'upgrade' ) {
            $app->validate_magic();
            $models = $app->param( 'model' );
            $counter = 0;
            if ( $models && !empty( $models ) ) {
                $schemes = [];
                $errors = [];
                foreach ( $models as $model ) {
                    if (! isset( $components[ $model ] ) ) {
                        $errors[] = $app->translate( 'Unknown Model %s.', $model );
                    }
                }
                if (!empty( $errors ) ) {
                    $app->ctx->vars['error'] = join( "\n", $errors );
                } else {
                    foreach ( $models as $model ) {
                        $file = $model_files[ $model ];
                        $component = $components[ $model ];
                        $app->db->base_model->set_scheme_from_json( $model, $file );
                        $this->setup_db( true, $component, [ $model ], dirname( $file ) );
                        $scheme = $app->get_scheme_from_db( $model );
                        $app->db->clear_cache();
                        $this->setup_db( true, $component, [ $model ], dirname( $file ) );
                        $counter++;
                    }
                }
            }
            $app->redirect( $app->admin_url .
                "?__mode=manage_scheme&saved_changes=" . $counter );
        }
        $app->ctx->vars['schemes'] = $items;
        $app->ctx->vars['upgrade_count'] = $upgrade_count;
        return $app->__mode( 'manage_scheme' );
    }

    function plugin_models ( $dirs = false ) {
        $app = Prototype::get_instance();
        $plugin_dirs = $app->plugin_dirs;
        $plugin_models = [];
        $json_dirs = [];
        foreach ( $plugin_dirs as $dir ) {
            $dir .= DS . 'models';
            if ( is_dir( $dir ) ) {
                $files = scandir( $dir, $app->plugin_order );
                $has_model = false;
                $models = [];
                foreach ( $files as $json ) {
                    if ( strpos( $json, '.' ) === 0 ) continue;
                    $file = $dir . DS . $json;
                    $extension = pathinfo( $json )['extension'];
                    if ( $extension !== 'json' ) continue;
                    $model = pathinfo( $json )['filename'];
                    $component = strtolower( basename( dirname( dirname( $file ) ) ) );
                    $data = json_decode( file_get_contents( $file ), true );
                    if ( isset( $data['component'] ) ) $component = $data['component'];
                    $version = isset( $data['version'] ) ? $data['version'] : 0;
                    $info = ['component' => $component, 'version' => $version,
                             'model' => $model ];
                    $plugin_models[ $model ] = $info;
                    $models[] = $info;
                    $has_model = true;
                }
                if ( $has_model ) $json_dirs[ $dir ] = $models;
            }
        }
        return $dirs ? $json_dirs : $plugin_models;
    }

    function setup_db ( $force = false, $component = 'core', $items = [], $m_dir = '' ) {
        $app = Prototype::get_instance();
        $db = $app->db;
        $db->json_model = true;
        $db->upgrader = true;
        $init = $db->model( 'table' )->new();
        $init = $db->model( 'option' )->new();
        $init = $db->model( 'session' )->new();
        if ( $component !== 'core' ) {
            $plugin = $app->component( $component );
            if (! $plugin ) {
                $plugin = $app->autoload_component( $plugin );
            }
            if ( $plugin && is_object( $plugin ) && !$m_dir ) {
                $m_dir = $plugin->path() . DS . 'models';
            }
        }
        $m_dir = $m_dir ? $m_dir : LIB_DIR . 'PADO' . DS . 'models';
        if ( empty( $items ) ) {
            $items = scandir( $m_dir );
            array_unshift( $items, 'phrase.json' );
        } else {
            array_walk( $items, function( &$item ) { $item .= '.json'; } );
        }
        $ws_children = [];
        $workspace = null;
        $items = array_flip( $items );
        $items = array_keys( $items );
        $default_models = [];
        foreach ( $items as $item ) {
            if ( strpos( $item, '.' ) === 0 ) continue;
            $file = $m_dir . DS . $item;
            if (! is_readable( $file ) ) continue;
            $item = str_replace( '.json', '', $item );
            $default_models[] = $item;
            $init = $db->model( $item )->new();
            $scheme = json_decode( file_get_contents( $file ), true );
            if ( isset( $scheme['version'] ) ) {
                $version_opt = $db->model( 'option' )->get_by_key(
                    ['kind' => 'scheme_version', 'key' => $item ] );
                $version_opt->value( $scheme['version'] );
                $version_opt->extra( $component );
                $version_opt->save();
            }
            if ( $item === 'column' || $item === 'option'
                || $item === 'meta' || $item === 'session' ) continue;
            $table = $db->model( 'table' )->get_by_key( ['name' => $item ] );
            if ( isset( $scheme['locale'] ) ) {
                $locale = $scheme['locale'];
                foreach ( $locale as $lang => $dict ) {
                    if ( $lang == 'default' ) {
                        $app->dictionary['default'] = array_merge(
                        $app->dictionary['default'], $scheme['locale']['default'] );
                    } else {
                        $phrase = key( $dict );
                        $trans = $dict[ $phrase ];
                        $record = $db->model( 'phrase' )->get_by_key(
                            ['lang' => $lang, 'phrase' => $phrase ] );
                        if (! $force && $record->id ) continue;
                        $record->trans( $trans );
                        $app->set_default( $record );
                        $record->save();
                    }
                }
            }
            $column_defs = $scheme['column_defs'];
            $indexes = $scheme['indexes'];
            $child_tables = isset( $scheme['child_tables'] )
                          ? $scheme['child_tables'] : [];
            $primary = $indexes['PRIMARY'];
            $col_primary = isset( $scheme['primary'] ) ? $scheme['primary'] : null;
            $child_of = isset( $scheme['child_of'] ) ? $scheme['child_of'] : null;
            $options = ['label', 'plural', 'auditing', 'order', 'sortable',
                'menu_type', 'template_tags', 'taggable', 'display_space', 'start_end',
                'has_basename', 'has_status', 'assign_user', 'revisable', 'hierarchy',
                'allow_comment', 'default_status'];
            foreach ( $options as $option ) {
                $opt = isset( $scheme[ $option ] ) ? $scheme[ $option ] : '';
                if (! $table->$option && $opt ) $table->$option( $opt );
            }
            if ( isset( $scheme['sort_by'] ) ) {
                $sort_by = $scheme['sort_by'];
                $sort_key = key( $sort_by );
                $sort_order = $sort_by[ $sort_key ];
                $table->sort_by( $sort_key );
                $table->sort_order( $sort_order );
            }
            foreach ( $child_tables as $child ) {
                $table = $this->set_child_tables( $child, $table, true, false );
            }
            if ( $child_of === 'workspace' ) {
                $table->space_child( 1 );
                $ws_children[] = $item;
                $table->display_space( 1 );
            }
            $table->primary( $col_primary );
            if ( isset( $scheme['display_system'] ) && $scheme['display_system'] ) {
                $table->display_system( 1 );
            } else {
                $table->display_system( 0 );
            }
            $app->set_default( $table );
            $table->not_delete( 1 );
            if ( isset( $scheme['version'] ) ) {
                $table->version( $scheme['version'] );
            }
            $table->save();
            if ( $item === 'workspace' ) {
                $workspace = $table;
            }
            $table_id = $table->id;
            $list_props = isset( $scheme['list_properties'] ) ?
                $scheme['list_properties'] : [];
            $edit_props = isset( $scheme['edit_properties'] ) ?
                $scheme['edit_properties'] : [];
            $unique = isset( $scheme['unique'] ) ?
                $scheme['unique'] : [];
            $unchangeable = isset( $scheme['unchangeable'] ) ?
                $scheme['unchangeable'] : [];
            $autoset = isset( $scheme['autoset'] ) ? $scheme['autoset'] : [];
            $col_options = isset( $scheme['options'] ) ? $scheme['options'] : [];
            $translates = isset( $scheme['translate'] ) ? $scheme['translate'] : [];
            $hints = isset( $scheme['hint'] ) ? $scheme['hint'] : [];
            $i = 1;
            $locale = $app->dictionary['default'];
            foreach ( $column_defs as $name => $defs ) {
                $record = $db->model( 'column' )->get_by_key(
                    ['table_id' => $table_id, 'name' => $name ] );
                if (! $force && $record->id ) continue;
                if ( $name === $primary ) $record->is_primary( 1 );
                $record->type( $defs['type'] );
                if ( isset( $defs['size'] ) ) $record->size( $defs['size'] );
                if ( isset( $defs['default'] ) ) $record->default( $defs['default'] );
                if ( isset( $defs['not_null'] ) ) $record->not_null( 1 );
                if ( isset( $indexes[ $name ] ) ) $record->index( 1 );
                if ( in_array( $name, $autoset ) ) $record->autoset( 1 );
                if ( isset( $locale[ $name ] ) ) {
                    $label = $app->translate( $name, '', $app, 'default' );
                } else {
                    $phrases = explode( '_', $name );
                    array_walk( $phrases, function( &$str ) { $str = ucfirst( $str ); } );
                    $label = join( ' ', $phrases );
                }
                if ( $item === 'entry' && $name === 'text' ) {
                    $label = 'Body';
                }
                $record->label( $label );
                if ( isset( $edit_props[ $name ] ) ) 
                    $record->edit( $edit_props[ $name ] );
                if ( isset( $list_props[ $name ] ) ) 
                    $record->list( $list_props[ $name ] );
                if ( in_array( $name, $unique ) ) $record->unique( 1 );
                if ( in_array( $name, $unchangeable ) ) $record->unchangeable( 1 );
                $record->not_delete( 1 );
                $record->order( $i );
                if ( isset( $scheme['relations'] ) ) {
                    if ( isset( $scheme['relations'][ $name ] ) ) {
                        $record->options( $scheme['relations'][ $name ] );
                    }
                }
                if ( isset( $col_options[ $name ] ) ) 
                    $record->options( $col_options[ $name ] );
                if ( isset( $hints[ $name ] ) ) 
                    $record->hint( $hints[ $name ] );
                if ( in_array( $name, $translates ) ) 
                    $record->translate( 1 );
                $app->set_default( $record );
                $record->save();
                if ( $name === 'workspace_id' ) {
                    if (! in_array( $item, $ws_children ) ) {
                        $ws_children[] = $item;
                    }
                }
                ++$i;
            }
            if ( $item === 'phrase' ) {
                $locale_dir = dirname( LIB_DIR ) . DS . 'locale';
                $locales = scandir( $locale_dir );
                foreach ( $locales as $locale ) {
                    if ( strpos( $locale, '.' ) === 0 ) continue;
                    if ( $locale === 'default.json' ) continue;
                    $lang = str_replace( '.json', '', $locale );
                    $locale = $locale_dir . DS . $locale;
                    $dict = json_decode( file_get_contents( $locale ), true );
                    foreach ( $dict as $phrase => $trans ) {
                        $record = $db->model( 'phrase' )->get_by_key(
                            ['lang' => $lang, 'phrase' => $phrase ] );
                        if (! $force && $record->id ) continue;
                        $record->trans( $trans );
                        $app->set_default( $record );
                        $record->save();
                    }
                }
            }
        }
        if (! empty( $ws_children ) && $workspace ) {
            foreach ( $ws_children as $child ) {
                $workspace = $this->set_child_tables( $child, $workspace, true, false );
            }
            $workspace->save();
        }
        $app->set_config( ['default_models' => join( ',', $default_models ) ] );
    }

    function save_filter_table ( &$cb, $app, &$obj ) {
        if ( $app->param( '_preview' ) ) return true;
        $validation = $app->param( '__validation' );
        if ( $validation ) {
            $message = ['status' => 200];
            echo json_encode( $message );
            exit();
        }
        if (! $obj->id ) {
            $name = strtolower( $app->param( 'name' ) );
            if (! $app->is_valid_property( $name, $msg, true ) ) {
                $cb['error'] = $msg;
                return false;
            }
            $default_models = $app->get_config( 'default_models' );
            if ( $default_models ) {
                $default_models = explode( ',', $default_models->value );
                if ( in_array( $name, $default_models ) ) {
                    $cb['error'] = $app->translate( 'The name %s is reserved.', $name );
                    return false;
                }
            }
            $obj->name( $name );
        }
        $primary = $app->param( 'primary' );
        if ( $primary && !$app->is_valid_property( $primary, $msg, true ) ) {
            $cb['error'] = $msg;
            return false;
        }
        // todo check reserved column name e.g. magic_token
        $errors = [];
        if (!$obj->id ) return true;
        $new_ids = $app->param( '_new_column' );
        $add_ids = [];
        foreach ( $new_ids as $col )
            if ( $col ) $add_ids[] = (int) $col;
        $ids = $app->param( '_column_id' );
        $not_specify = $this->reserved;
        $types       = ['boolean'  => ['tinyint', 4],
                        'integer'  => ['int', 11],
                        'text'     => ['text', ''],
                        'blob'     => ['blob', ''],
                        'relation' => ['relation', ''],
                        'datetime' => ['datetime', ''],
                        'string'   => ['string', 255] ];
        $list_types  = ['checkbox', 'number', 'primary', 'text', 'popover',
                        'text_short', 'password', 'datetime', 'date'];
        $edit_types  = ['hidden', 'checkbox', 'number', 'primary', 'text', 'file',
                        'text_short', 'textarea', 'password', 'password(hash)',
                        'datetime', 'languages', 'richtext', 'selection', 'color'];
        $db = $app->db;
        $db->can_drop = true;
        $columns = $db->model( 'column' )->load( ['table_id' => $obj->id ] );
        $col_names = [];
        $primary_cols = [];
        foreach ( $columns as $column ) {
            $col_name = $column->name;
            $col_names[] = $column->name;
            $id = $column->id;
            $order = $app->param( '_order_' . $id );
            if ( $column->is_primary ) {
                $list = $app->param( '_list_' . $id ) ? 'number' : '';
                $column->order( $order );
                $column->list( $list );
                if (! $validation ) $column->save();
                continue;
            }
            if (! in_array( $id, $ids ) ) {
                if ( $col_name !== $obj->primary && !$column->not_delete ) {
                    // TODO Cleanup relation( from and to )
                    if (! $validation ) {
                        $column->remove();
                        unset( $db->scheme['table']['column_defs'][ $col_name ] );
                    }
                    continue;
                }
            }
            $type = $app->param( '_type_' . $id );
            if (! $type || !isset( $types[ $type ] ) ) {
                $errors[] = $app->translate( 'Invalid type (%s).', $type );
                continue;
            }
            list( $type, $size ) = $types[ $type ];
            $size = (int) $size;
            $autoset = $app->param( '_autoset_' . $id );
            $autoset = (int) $autoset;
            if (! $size ) $size = ''; // null?
            $label = $app->param( '_label_' . $id );
            $options = $app->param( '_options_' . $id );
            $disp_edit = $app->param( '_disp_edit_' . $id );
            $not_null = $app->param( '_not_null_' . $id ) ? 1 : 0;
            $index = $app->param( '_index_' . $id ) ? 1 : 0;
            $unique = $app->param( '_unique_' . $id ) ? 1 : 0;
            $unchangeable = $app->param( '_unchangeable_' . $id ) ? 1 : 0;
            $list = $app->param( '_list_' . $id );
            $edit = $app->param( '_edit_' . $id );
            $translate = $app->param( '_trans_' . $id );
            $hint = $app->param( '_hint_' . $id );
            if ( $edit && ! in_array( $edit, $edit_types ) ) {
                if ( strpos( $edit, ':' ) === false ||
                    !$app->is_valid_property( str_replace( ':', '', $edit ) ) ) {
                    $errors[] = $app->translate( 'Invalid edit type (%s).', $edit );
                    $edit = ''; // error
                }
            }
            if ( $list && ! in_array( $list, $list_types ) ) {
                if ( strpos( $list, ':' ) === false ||
                    !$app->is_valid_property( str_replace( ':', '', $list ) ) ) {
                    $errors[] = $app->translate( 'Invalid list type (%s).', $list );
                    $list = ''; // error
                }
            }
            $column->type( $type );
            $column->size( $size );
            $column->label( $label );
            $column->options( $options );
            $column->order( $order );
            $column->not_null( $not_null );
            $column->index( $index );
            $column->disp_edit( $disp_edit );
            $column->unique( $unique );
            $column->unchangeable( $unchangeable );
            $column->list( $list );
            $column->edit( $edit );
            $column->translate( $translate );
            $column->hint( $hint );
            $column->autoset( $autoset );
            if ( empty( $errors ) ) {
                $app->set_default( $column );
                if (! $validation ) $column->save();
            }
            if ( $column->list == 'primary' && $column->edit == 'primary' ) {
                $primary_cols[] = $column->name;
            }
        }
        foreach ( $add_ids as $id ) {
            $name = $app->param( '_new_name_' . $id );
            if ( in_array( $name, $col_names ) ) {
                $errors[] = $app->translate( 'A %1$s with the same %2$s already exists.',
                     [ $name, $app->translate( 'column' ) ] );
                continue;
            }
            if (! $app->is_valid_property( $name, $msg, true ) ) {
                $errors[] = $msg;
                continue;
            }
            if ( in_array( $name, $not_specify ) ) {
                $errors[] = $app->translate( "The name '%s' can not be specified.", $name );
            }
            $label = $app->param( '_new_label_' . $id );
            $type = $app->param( '_new_type_' . $id );
            if (! $type || !isset( $types[ $type ] ) ) {
                $errors[] = $app->translate( 'Invalid type (%s).', $type );
                continue;
            }
            list( $type, $size ) = $types[ $type ];
            if ( $type === 'string' && $app->param( 'new_size_' . $id ) ) {
                $size = $app->param( '_new_size_' . $id );
            }
            $order = $app->param( '_new_order_' . $id );
            $order = (int) $order;
            $autoset = $app->param( '_new_autoset_' . $id );
            $autoset = (int) $autoset;
            $options = $app->param( '_new_options_' . $id );
            $disp_edit = $app->param( '_new_disp_edit_' . $id );
            $not_null = $app->param( '_new_not_null_' . $id ) ? 1 : 0;
            $index = $app->param( '_new_index_' . $id ) ? 1 : 0;
            $unique = $app->param( '_new_unique_' . $id ) ? 1 : 0;
            $unchangeable = $app->param( '_new_unchangeable_' . $id ) ? 1 : 0;
            $list = $app->param( '_new_list_' . $id );
            $translate = $app->param( '_new_trans_' . $id );
            $hint = $app->param( '_new_hint_' . $id );
            if (! $primary && $list === 'primary' ) {
                $obj->primary( $list );
            }
            $edit = $app->param( '_new_edit_' . $id );
            if ( $edit && !in_array( $edit, $edit_types ) ) {
                if ( strpos( $edit, ':' ) === false ||
                    !$app->is_valid_property( str_replace( ':', '', $edit ) ) ) {
                    $errors[] = $app->translate( 'Invalid edit type (%s).', $edit );
                    $edit = '';
                }
            }
            if ( $list && !in_array( $list, $list_types ) ) {
                if ( strpos( $list, ':' ) === false ||
                    !$app->is_valid_property( str_replace( ':', '', $list ) ) ) {
                    $errors[] = $app->translate( 'Invalid list type (%s).', $list );
                    $list = '';
                }
            }
            if ( empty( $errors ) ) {
                $column = $db->model( 'column' )->get_by_key( [
                    'table_id'  => $obj->id,
                    'name'      => $name,
                    'label'     => $label,
                    'type'      => $type,
                    'size'      => $size,
                    'order'     => $order,
                    'not_null'  => $not_null,
                    'index'     => $index,
                    'options'   => $options,
                    'disp_edit' => $disp_edit,
                    'unique'    => $unique,
                    'list'      => $list,
                    'edit'      => $edit,
                    'autoset'   => $autoset,
                    'unchangeable' => $unchangeable,
                    'translate' => $translate,
                    'hint'      => $hint
                ] );
                $app->set_default( $column );
                if (! $validation ) $column->save();
                if ( $column->list == 'primary' && $column->edit == 'primary' ) {
                    $primary_cols[] = $column->name;
                }
            }
        }
        if ( $validation ) {
            $message = ['status' => 200];
            header( 'Content-type: application/json' );
            if (! empty( $errors ) ) {
                $message['status'] = 500;
                $message['error'] = join( "\n", $errors );
            }
            echo json_encode( $message );
            exit();
        }
        if (! empty( $errors ) ) {
            $cb['error'] = join( "\n", $errors );
            return false;
        }
        if (!empty( $primary_cols ) && !in_array( $obj->primary, $primary_cols ) ) {
            $obj->primary( $primary_cols[0] );
            $obj->save();
        }
        return true;
    }

    function post_save_table ( $cb, $app, $obj, $force = false ) {
        $is_child = $obj->space_child;
        $db = $app->db;
        $db->logging = false;
        $ctx = $app->ctx;
        $ctx->logging = false;
        $workspace_col = $db->model( 'column' )->get_by_key
            ( ['table_id' => $obj->id, 'name' => 'workspace_id'] );
        if ( $workspace_col->id ) $is_child = true;
        if( $is_child || $obj->sortable || $obj->auditing || $obj->taggable
            || $obj->has_status || $obj->start_end || $obj->has_basename
            || $obj->assign_user || $obj->revisable || $obj->display_space
            || $obj->hierarchy || $obj->allow_comment ) {
            $last = $db->model( 'column' )->load
                    ( ['table_id' => $obj->id ],
                      ['sort' => 'order', 'direction' => 'descend', 'limit' => 1] );
            $last = (! empty( $last ) ) ? $last[0]->order : 10;
            $last++;
            $upgrade = false;
            if ( $obj->sortable ) {
                $values = ['type' => 'int', 'size' => 11,
                           'label'=> 'Order',
                           'list' => 'number', 'edit' => 'number',
                           'index' => 1, 'order' => $last ];
                if ( $this->make_column( $obj, 'order', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
            }
            if ( $obj->assign_user ) {
                $values = ['type' => 'int', 'size' => 11,
                           'label'=> 'User',
                           'list' => 'reference:user:nickname',
                           'edit' => 'relation:user:nickname:dialog',
                           'index' => 1, 'order' => $last ];
                if ( $this->make_column( $obj, 'user_id', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
            }
            if ( $obj->allow_comment ) {
                $values = ['type' => 'tinyint', 'size' => 4,
                           'label'=> 'Accept Comments',
                           'list' => 'checkbox',
                           'edit' => 'checkbox',
                           'order' => $last ];
                if ( $this->make_column( $obj, 'allow_comment', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
                $values = ['type' => 'int', 'size' => 11,
                           'label'=> 'Comment Count',
                           'list' => 'number',
                           'autoset' => 1,
                           'index' => 1, 'order' => $last ];
                if ( $this->make_column( $obj, 'comment_count', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
            }
            if ( $obj->hierarchy ) {
                $name = $obj->name;
                $primary = $obj->primary;
                $list = "reference:{$name}:{$primary}";
                $edit = "relation:{$name}:{$primary}:select";
                $values = ['type' => 'int', 'size' => 11,
                           'label'=> 'Parent',
                           'default' => 0,
                           'list' => $list,
                           'edit' => $edit,
                           'not_null' => 1,
                           'index' => 1, 'order' => $last ];
                if ( $this->make_column( $obj, 'parent_id', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
            }
            if ( $obj->taggable ) {
                $values = ['type' => 'relation',
                           'label'=> 'Tags',
                           'list' => 'reference:tag:name',
                           'edit' => 'relation:tag:name:dialog',
                           'options' => 'tag', 'order' => $last ];
                if ( $this->make_column( $obj, 'tags', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
            }
            if ( $obj->start_end ) {
                $start_end_cols = ['published_on' => 'Publish Date',
                                   'unpublished_on' => 'Unpublish Date',
                                   'has_deadline' => 'Specify the Deadline'];
                foreach ( $start_end_cols as $name => $label ) {
                    $col = $db->model( 'column' )->get_by_key
                      ( ['table_id' => $obj->id, 'name' => $name ] );
                    if (! $col->id ) {
                        $col->label( $label );
                        if ( $name !== 'has_deadline' ) {
                            $col->set_values( ['type' => 'datetime', 'index' => 1,
                                               'list' => 'date', 'edit' => 'datetime',
                                               'order' => $last ] );
                        } else {
                            $col->set_values( ['type' => 'tinyint', 'size' => 4,
                            'list' => 'checkbox', 'index' => 1, 'order' => $last ] );
                        }
                        $app->set_default( $col );
                        $col->save();
                        $upgrade = true;
                        $last++;
                    }
                }
            }
            if ( $obj->has_status ) {
                if ( $obj->start_end ) {
                    $status_opt = 'Draft,Review,Reserved,Publish,Unpublished (End)';
                } else {
                    $status_opt = 'Disable,Enable';
                }
                $values = ['type' => 'int', 'size' => 11, 'default' => 1,
                           'label'=> 'Status', 'list' => 'number',
                           'edit' => 'selection', 'disp_edit' => 'select',
                           'options' => $status_opt, 'index' => 1, 'order' => $last ];
                if ( $this->make_column( $obj, 'status', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
            }
            if ( $obj->has_basename ) {
                $values = ['type' => 'string', 'size' => 255,
                           'label'=> 'Basename',
                           'edit' => 'text_short', 'not_null' => 1,
                           'index' => 1, 'order' => $last ];
                if ( $this->make_column( $obj, 'basename', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
            }
            if ( $obj->space_child || $obj->display_space ) {
                $values = ['type' => 'int', 'size' => 11,
                           'label'=> 'WorkSpace',
                           'list' => 'reference:workspace:name', 'unchangeable' => 1,
                           'autoset' => 1, 'index' => 1, 'order' => $last ];
                if ( $this->make_column( $obj, 'workspace_id', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
            }
            if ( $obj->revisable ) {
                $values = ['type' => 'int', 'size' => 11, 'autoset' => 1,
                           'label'=> 'Type', 'not_null' => 1,
                           'default' => '0', 'index' => 1, 'order' => $last ];
                if ( $this->make_column( $obj, 'rev_type', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
                $values = ['type' => 'int', 'size' => 11,
                           'label'=> 'Object ID', 'autoset' => 1,
                           'index' => 1, 'order' => $last ];
                if ( $this->make_column( $obj, 'rev_object_id', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
                $values = ['type' => 'string', 'size' => 255, 'autoset' => 1,
                           'label'=> 'Changed', 'order' => $last ];
                if ( $this->make_column( $obj, 'rev_changed', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
                $values = ['type' => 'string', 'size' => 255, 'index' => 1,
                           'label'=> 'Change Note', 'order' => $last ];
                if ( $this->make_column( $obj, 'rev_note', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
                $values = ['type' => 'text', 'label'=> 'Diff',
                           'order' => $last, 'autoset' => 1];
                if ( $this->make_column( $obj, 'rev_diff', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
                if (! $obj->auditing ) $obj->auditing( 2 );
            }
            if ( $is_child ) {
                $ws_table = $db->model( 'table' )->get_by_key( ['name' => 'workspace'] );
                if ( $ws_table->id ) {
                    $this->set_child_tables( $obj->name, $ws_table, true, true );
                }
            }
            if ( $obj->auditing ) {
                $auditing_cols = [
                     'created_on'  => 
                         ['label' => 'Date Created', 'type' => 'datetime'],
                     'modified_on' => 
                         ['label' => 'Date Modified', 'type' => 'datetime'],
                     'created_by'  => 
                         ['label' => 'Created By', 'type' => 'reference:user:name'],
                     'modified_by' => 
                         ['label' => 'Modified By', 'type' => 'reference:user:name']
                     ];
                foreach ( $auditing_cols as $name => $props ) {
                    $col = $db->model( 'column' )->get_by_key
                      ( ['table_id' => $obj->id, 'name' => $name ] );
                    if (! $col->id ) {
                        list( $label, $type ) = [ $props['label'], $props['type'] ];
                        $col->label( $label );
                        if ( $type === 'datetime' ) {
                            $col->type( $type );
                            if ( $name === 'modified_on' ) $col->list( $type );
                        } else {
                            $col->type( 'int' );
                            $col->size( 4 );
                            if ( $name == 'modified_by' ) $col->list( $type );
                        }
                        $col->set_values(
                            ['index' => 1, 'autoset' => 1, 'order' => $last ] );
                        $app->set_default( $col );
                        if ( $obj->auditing == 1 ) $col->save();
                        if ( $obj->auditing == 2 ) {
                            if ( strpos( $name, 'modified' ) === 0 ) {
                                $col->save();
                            }
                        }
                    }
                    $upgrade = true;
                    $last++;
                }
            }
        }
        if (! $cb['is_new'] )
            $this->upgrade_scheme( $obj->name );
        if (! $cb['is_new'] ) return;
        $values = ['type' => 'int', 'size' => 11,
                   'label'=> 'ID', 'is_primary' => 1,
                   'list' => 'number', 'edit' => 'hidden',
                   'index' => 1, 'order' => 1, 'not_null' => 1];
        $this->make_column( $obj, 'id', $values, $force );
        $db->upgrader = false;
        $model = $obj->name;
        $scheme = $app->get_scheme_from_db( $model );
        $colprefix = $db->colprefix;
        if ( $colprefix ) {
            if ( strpos( $colprefix, '<model>' ) !== false )
                $colprefix = str_replace( '<model>', $model, $colprefix );
            else if ( strpos( $colprefix, '<table>' ) !== false )
                $colprefix = str_replace( '<table>', $app->_table, $colprefix );
        }
        $res = $db->base_model->create_table( $model, $db->prefix . $model,
                                                $colprefix, $scheme );
        $db->logging = true;
        $ctx->logging = true;
        return $res;
    }

    function make_column ( $obj, $name, $values, $force = false ) {
        $app = Prototype::get_instance();
        $col = $app->db->model( 'column' )->get_by_key
            ( ['table_id' => $obj->id, 'name' => $name ] );
        if (! $col->id || $force ) {
            if ( $col->order ) unset( $values['order'] );
            $col->set_values( $values );
            $app->set_default( $col );
            return $col->save();
        }
    }

    function set_child_tables ( $child, &$parent, $attach = true, $save = true ) {
        $child_tables = $parent->child_tables;
        $child_tables = $child_tables ? explode( ',', $parent->child_tables ) : [];
        $flipped = array_flip( $child_tables );
        if ( $attach ) {
            $flipped[ $child ] = $attach;
        } else {
            unset( $flipped[ $child ] );
        }
        $parent->child_tables( join( ',', array_keys( $flipped ) ) );
        if ( $save ) $parent->save();
        return $parent;
    }

    function drop ( $table ) {
        $app = Prototype::get_instance();
        $tables = $app->db->model( 'table' )->load();
        foreach ( $tables as $t ) {
            if ( $table->id == $t->id ) continue;
            $rel_table = $app->get_table( $t->name );
            $model = $app->db->model( $t->name )->new();
            if ( $model->has_column( 'table_id' ) ) {
                $rel_objs = $model->load( ['table_id' => $table->id ] );
                foreach ( $rel_objs as $obj ) {
                    $app->remove_object( $obj, $rel_table );
                }
            }
            if ( $model->has_column( 'model' ) ) {
                $rel_objs = $model->load( ['model' => $table->name ] );
                foreach ( $rel_objs as $obj ) {
                    $app->remove_object( $obj, $rel_table );
                }
            }
        }
    }
}
