<?php

class PTUpgrader {

    protected $reserved = ['magic_token', 'tags', 'additional_tags', 'created_on',
                           'created_by', 'workspace_id', 'order', 'status', 'modified_on',
                           'modified_by', 'published_on', 'unpublished_on', 'user_id',
                           'load', 'retry', 'in_stmt', 'get_by_key', 'count', 'has_column',
                           'count_group_by', 'load_iter', 'save', 'update_multi',
                           'remove_multi', 'update', 'remove', 'delete', 'set_scheme_from_json',
                           'get_scheme', 'create_table', 'column_values', 'column_names',
                           'set_values', 'get_values', 'next_prev', 'upgrade', 'array_compare',
                           'check_upgrade', 'get_diff', 'validation', 'serialize', 'date2db',
                           'time2db', 'ts2db', 'db2ts', 'basename', 'has_deadline',
                           'rev_type', 'rev_object_id', 'rev_note', 'rev_changed', 'uuid',
                           'rev_diff', 'workspace_id', 'allow_comment', 'comment_count', 'form_id',
                           'attachmentfiles', 'previous_owner', 'show_activity', 'has_assets'];

    private $reserved_models = ['queue'];
    private $print_state = false;

    protected function core_upgrade_functions ( $version ) {
        if (! $version ) return [];
        $functions = [
            'upgrade_status' => ['component' => 'PTUpgrader',
                                 'method'    => 'upgrade_status',
                                 'version_limit' => '0.1' ],
            'set_preferred'  => ['component' => 'PTUpgrader',
                                 'method'    => 'set_preferred',
                                 'version_limit' => '1.001' ],
            'set_workspace'  => ['component' => 'PTUpgrader',
                                 'method'    => 'set_workspace',
                                 'version_limit' => '1.003' ],
            'update1006'     => ['component' => 'PTUpgrader',
                                 'method'    => 'update1006',
                                 'version_limit' => '1.006' ],
            'update_perm'    => ['component' => 'PTUpgrader',
                                 'method'    => 'update_perm',
                                 'version_limit' => '1.015' ],
        ];
        $upgrade_functions = [];
        foreach ( $functions as $func ) {
            $version_limit = $func['version_limit'];
            if ( $version_limit > $version ) {
                $upgrade_functions[] = $func;
            }
        }
        return $upgrade_functions;
    }

    function start_upgrade ( $app ) {
        if ( $max_execution_time = $app->max_exec_time ) {
            $max_execution_time = (int) $max_execution_time;
            ini_set( 'max_execution_time', $max_execution_time );
        }
        $app->clear_all_cache();
        $app->db->clear_cache();
    }

    function upgrade () {
        $app = Prototype::get_instance();
        if ( $app->installed ) {
            $app->error( 'Invalid request.' );
        }
        if ( $max_execution_time = $app->max_exec_time ) {
            $max_execution_time = (int) $max_execution_time;
            ini_set( 'max_execution_time', $max_execution_time );
        }
        $tmpl = TMPL_DIR . 'upgrade.tmpl';
        $ctx = $app->ctx;
        $ctx->vars['language'] = $app->language;
        if ( $app->param( '_type' ) === 'install' ) {
            $ctx->vars['page_title'] = $app->translate( 'Install' );
        } else {
            $ctx->vars['page_title'] = $app->translate( 'Upgrade' );
        }
        $app->clear_all_cache();
        if ( $app->request_method === 'POST' ) {
            if ( $app->param( '_type' ) === 'install' ) {
                $this->start_upgrade( $app );
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
                $two_factor_auth = $app->param( 'two_factor_auth' );
                $lockout_limit = $app->param( 'lockout_limit' );
                $lockout_interval = $app->param( 'lockout_interval' );
                $ip_lockout_limit = $app->param( 'ip_lockout_limit' );
                $ip_lockout_interval = $app->param( 'ip_lockout_interval' );
                $tmpl_markup = $app->param( 'tmpl_markup' );
                $barcolor = $app->param( 'barcolor' );
                $bartextcolor = $app->param( 'bartextcolor' );
                $errors = [];
                if (!$appname || !$site_url || !$system_email || !$site_path ) {
                    $errors[] = $app->translate(
                        'App Name, System Email Site URL and Site Path are required.' );
                }
                if (!$name || !$pass || !$email ) {
                    $errors[] = $app->translate( 'Username, Password and Email are required.' );
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
                $tmpl = TMPL_DIR . 'install.tmpl';
                echo $ctx->build_page( $tmpl );
                $msg = $app->translate( 'Start Install...', $item );
                echo str_pad( ' ', 4096 ) . "<br />\n";
                echo "<script>$('#print').html( $('#print').html() + '{$msg}' + '<hr>' );</script>";
                ob_end_flush();
                ob_start( 'mb_output_handler' );
                $this->print_state = true;
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
                         'two_factor_auth' => $two_factor_auth,
                         'lockout_limit' => $lockout_limit,
                         'lockout_interval' => $lockout_interval,
                         'ip_lockout_interval' => $ip_lockout_interval,
                         'ip_lockout_limit' => $ip_lockout_limit,
                         'default_widget' => $default_widget,
                         'tmpl_markup' => $tmpl_markup,
                         'barcolor' => $barcolor,
                         'bartextcolor' => $bartextcolor ];
                $password = $app->param( 'password' );
                $language = $app->param( 'language' );
                $nickname = $app->param( 'nickname' );
                $password = password_hash( $password, PASSWORD_BCRYPT );
                $db->upgrader = true;
                $tbl_count = $this->setup_db( true );
                $app->set_config( $cfgs );
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
                $this->install_field_types( $app );
                $this->install_question_types( $app );
                $message = $app->translate( "PowerCMS X has been installed and create first user '%s'.",
                                            $user->name );
                $app->log( ['message'  => $message,
                            'category' => 'install',
                            'model'    => 'user',
                            'object_id'=> $user->id,
                            'level'    => 'info'] );
                $msg = $app->translate( "Create %s tables.", $tbl_count );
                echo "<script>$('#print').html( $('#print').html() + '<hr>' + '{$msg}' + '<br>' );</script>";
                echo "<script>$('#print').html( $('#print').html() + '<hr>' + '{$message}' + '<br>' );</script>";
                echo '<script>var $target = $(\'#print\');';
                echo '$target.scrollTop($target[0].scrollHeight);</script>';
                echo "<script>$('#move_login').show();</script>";
                ob_flush();
                flush();
                $app->redirect( $app->admin_url );
            }
        } else {
            $path = $app->path;
            if (! $path ) {
                $path = $app->path();
                $search = preg_quote( $app->document_root, '/' );
                $path = preg_replace( "/^$search/", '', $path );
            }
            $path = rtrim( $path, '/' );
            $path = str_replace( '/', DS, $path );
            $_path = str_replace( DS, '/', $path );
            $ctx->vars['site_url'] = $app->base . $_path . '/site/';
            $ctx->vars['site_path'] = $app->document_root . $path . DS . 'site';
            $ctx->vars['extra_path'] = 'assets/';
            $ctx->vars['language'] = $app->language;
            $ctx->vars['sys_language'] = $app->language;
        }
        echo $ctx->build_page( $tmpl );
        exit();
    }

    function upgrade_scheme ( $name ) {
        $app = Prototype::get_instance();
        $this->start_upgrade( $app );
        $db = $app->db;
        $app->clear_all_cache();
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
        $workspace = $app->workspace()
                   ? $app->workspace() : $app->db->model( 'workspace' )->new( ['id' => 0 ] );
        if (! $app->can_do( 'manage_plugins', null, null, $workspace ) ) {
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
                $models_dir = $this->get_models_dir( $app, $model );
                $component = 'core';
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
                    }
                    $items[] = $info;
                    $components[ $model ] = $component;
                }
            }
        }
        $json_dirs = array_keys( $this->plugin_models( true ) );
        $json_dirs = array_merge( $app->model_paths, $json_dirs );
        foreach ( $json_dirs as $dir ) {
            $files = scandir( $dir, $app->plugin_order );
            foreach ( $files as $json ) {
                if ( strpos( $json, '.' ) === 0 ) continue;
                $file = $dir . DS . $json;
                $extension = pathinfo( $json )['extension'];
                if ( $extension !== 'json' ) continue;
                $model = pathinfo( $json )['filename'];
                if (! in_array( $model, $model_names ) ) {
                    $component = in_array( $dir, $app->model_paths )
                               ? 'Prototype'
                               : strtolower( basename( dirname( dirname( $file ) ) ) );
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
        }
        if ( $app->request_method === 'POST' &&
            $app->param( '_type' ) && $app->param( '_type' ) === 'upgrade' ) {
            $app->validate_magic();
            $this->start_upgrade( $app );
            $models = $app->param( 'model' );
            $counter = 0;
            $app->db->clear_cache();
            $app->db->caching = false;
            $upgrader_dir = __DIR__ . DS . 'Upgrader' . DS;
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
                        if ( $components[ $model ] != 'Prototype' ) {
                            $dir = dirname( dirname( $file ) );
                            $locale = $dir . DS . 'locale' . DS . 'default.json';
                            if ( file_exists( $locale ) ) {
                                $locale = json_decode( file_get_contents( $locale ), true );
                                if ( is_array( $locale ) ) {
                                    $app->dictionary['default'] = array_merge(
                                        $app->dictionary['default'], $locale );
                                }
                            }
                        }
                        $old_version = $app->db->model( 'option' )->get_by_key( [
                            'key' => $model,
                            'kind' => 'scheme_version'] );
                        $old_version = $old_version->value;
                        $component = $components[ $model ];
                        $app->db->base_model->set_scheme_from_json( $model, $file );
                        $sth = $app->db->show_tables( $model );
                        $table = $sth->fetchColumn();
                        if (! $table ) {
                            $colprefix = $app->db->colprefix;
                            if ( $colprefix ) {
                                if ( strpos( $colprefix, '<model>' ) !== false )
                                    $colprefix = str_replace( '<model>', $model, $colprefix );
                            }
                            $table = $app->db->prefix . $model;
                            $scheme = $app->db->scheme[ $model ];
                            unset( $app->db->scheme[ $model ] );
                            $app->db->json_model = true;
                            $app->db->upgrader = true;
                            $app->db->base_model->create_table
                                ( $model, $table, $colprefix, $scheme );
                        }
                        unset( $app->db->scheme[ $model ] );
                        $this->setup_db( true, $component, [ $model ], dirname( $file ) );
                        $scheme = $app->get_scheme_from_db( $model );
                        if ( $model == 'fieldtype' ) {
                            $this->install_field_types( $app );
                        } else if ( $model == 'questiontype' ) {
                            $this->install_question_types( $app );
                        }
                        $model_upgrader = "${upgrader_dir}upgrader.{$model}.php";
                        if ( file_exists( $model_upgrader ) ) {
                            require_once( $model_upgrader );
                            $upgrader_class = "upgrader_{$model}";
                            $model_upgrader = new $upgrader_class();
                            $model_upgrade_funcs = $model_upgrader->upgrade_functions;
                            foreach ( $model_upgrade_funcs as $upgrade_key => $upgrader_props ) {
                                // $old_version
                                $upgrade_limit = $upgrader_props['version_limit'];
                                if ( $upgrade_limit >= $old_version ) {
                                    $meth = $upgrader_props['method'];
                                    if ( method_exists( $model_upgrader, $meth ) ) {
                                        $model_upgrader->$meth( $app, $this, $old_version );
                                    }
                                }
                            }
                        }
                        $counter++;
                    }
                }
            }
            if ( isset( $app->registry['upgrade_functions'] ) ) {
                $upgrade_functions = $app->registry['upgrade_functions'];
                foreach ( $upgrade_functions as $func ) {
                    list( $component, $method, $version_limit )
                        = [ strtolower( $func['component'] ), $func['method'], $func['version_limit'] ];
                    $plugin = $app->component( $component );
                    if ( $plugin ) {
                        $plugin_switch = $app->plugin_switch;
                        if ( isset( $plugin_switch[ $component ] ) ) {
                            $option = $plugin_switch[ $component ];
                            $old_version = $option->value;
                            $new_version = $plugin->version();
                            if ( $old_version <= $version_limit ) {
                                if ( method_exists( $plugin, $method ) ) {
                                    $plugin->$method( $app );
                                }
                            }
                            if ( $new_version && $old_version != $new_version ) {
                                $option->value( $new_version );
                                $option->save();
                            }
                        }
                    }
                }
            }
            $this->upgrade_scheme_check( $app );
            $app->redirect( $app->admin_url .
                "?__mode=manage_scheme&saved_changes=" . $counter );
        }
        $app->ctx->vars['schemes'] = $items;
        $app->ctx->vars['upgrade_count'] = $upgrade_count;
        return $app->__mode( 'manage_scheme' );
    }

    function get_models_dir ( $app, $model ) {
        foreach ( $app->model_paths as $model_path ) {
            $json_path = $model_path . DS . $model . '.json';
            if ( file_exists( $json_path ) ) {
                return dirname( $json_path );
            }
        }
    }

    function install_field_types ( $app ) {
        $fields_dir = $app->path() . DS . 'tmpl' . DS . 'field' . DS . 'field_types';
        $json = $fields_dir . DS . 'fields.json';
        $tmpl_dir = $fields_dir . DS . 'tmpl' . DS;
        if ( is_readable( $json ) ) {
            $fields = json_decode( file_get_contents( $json ), true );
            foreach ( $fields as $basename => $prop ) {
                $field = $app->db->model( 'fieldtype' )->get_by_key(
                        ['basename' => $basename ] );
                if ( $field->id ) {
                    $original = clone $field;
                    PTUtil::pack_revision( $field, $original );
                }
                $name = $prop['name'];
                $field->name( $prop['name'] );
                $field->order( $prop['order'] );
                $hide_label = ( isset( $prop['hide_label'] ) && $prop['hide_label'] ) ? 1 : 0;
                $field->hide_label( $hide_label );
                $field->label( file_get_contents( "{$tmpl_dir}{$basename}_label.tmpl" ) );
                $field->content( file_get_contents( "{$tmpl_dir}{$basename}_content.tmpl" ) );
                $app->set_default( $field );
                $field->save();
            }
        }
    }

    function install_question_types ( $app ) {
        $questions_dir = $app->path() . DS . 'tmpl' . DS . 'question' . DS . 'question_types';
        $json = $questions_dir . DS . 'questions.json';
        $tmpl_dir = $questions_dir . DS . 'tmpl' . DS;
        if ( is_readable( $json ) ) {
            $questions = json_decode( file_get_contents( $json ), true );
            foreach ( $questions as $basename => $prop ) {
                $question = $app->db->model( 'questiontype' )->get_by_key(
                        ['basename' => $basename ] );
                if ( $question->id ) {
                    $original = clone $question;
                    PTUtil::pack_revision( $question, $original );
                }
                $name = $prop['name'];
                $question->name( $prop['name'] );
                $question->order( $prop['order'] );
                $question->template( file_get_contents( "{$tmpl_dir}{$basename}.tmpl" ) );
                $app->set_default( $question );
                $question->save();
            }
        }
    }

    function plugin_models ( $dirs = false ) {
        $app = Prototype::get_instance();
        $plugin_dirs = $app->plugin_dirs;
        $plugin_models = [];
        $json_dirs = [];
        foreach ( $plugin_dirs as $dir ) {
            $id = strtolower( basename( $dir ) );
            $switch = $app->db->model( 'option' )->get_by_key( ['key' => $id ] );
            if (! $switch->number ) continue;
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
        $this->start_upgrade( $app );
        $db = $app->db;
        $db->json_model = true;
        $app->db->upgrader = true;
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
        $tbl_count = 0;
        foreach ( $items as $item ) {
            if ( strpos( $item, '.' ) === 0 ) continue;
            $file = $m_dir . DS . $item;
            if (! is_readable( $file ) ) continue;
            $item = str_replace( '.json', '', $item );
            $default_models[] = $item;
            $db->models_json[ $item ] = $file;
            $init = $db->model( $item )->new();
            $scheme = json_decode( file_get_contents( $file ), true );
            if ( isset( $scheme['version'] ) ) {
                $version_opt = $db->model( 'option' )->get_by_key(
                    ['kind' => 'scheme_version', 'key' => $item ] );
                $extra = $component == 'Prototype' ? 'core' : $component;
                $version_opt->value( $scheme['version'] );
                $version_opt->extra( $extra );
                $version_opt->save();
            }
            if ( $item === 'column' || $item === 'option' || $item === 'relation'
                || $item === 'meta' || $item === 'session' ) continue;
            $table = $db->model( 'table' )->get_by_key( ['name' => $item ] );
            $tbl_count++;
            if ( $this->print_state ) {
                $msg = $app->translate( "Creating TABLE \'%s\'...", $item );
                echo "<script>$('#print').html( $('#print').html() + '{$msg}' + '<br>' );</script>";
                echo '<script>var $target = $(\'#print\');';
                echo '$target.scrollTop($target[0].scrollHeight);</script>';
                ob_flush();
                flush();
            }
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
            $column_labels = [];
            if ( isset( $scheme['column_labels'] ) ) {
                $column_labels = $scheme['column_labels'];
            }
            $column_defs = $scheme['column_defs'];
            $indexes = $scheme['indexes'];
            $child_tables = isset( $scheme['child_tables'] )
                          ? $scheme['child_tables'] : [];
            $do_not_output = isset( $scheme['do_not_output'] )
                          ? $scheme['do_not_output'] : false;
            $primary = $indexes['PRIMARY'];
            $col_primary = isset( $scheme['primary'] ) ? $scheme['primary'] : null;
            $child_of = isset( $scheme['child_of'] ) ? $scheme['child_of'] : null;
            $options = ['label', 'plural', 'auditing', 'order', 'sortable',
                'menu_type', 'template_tags', 'taggable', 'display_space', 'start_end',
                'has_basename', 'has_status', 'assign_user', 'revisable', 'max_revisions',
                'hierarchy', 'allow_comment', 'default_status', 'has_uuid', 'dialog_view',
                'can_duplicate', 'has_assets', 'has_attachments', 'show_activity',
                'text_format', 'has_form'];
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
            } else if ( $table->display_space ) {
                $ws_children[] = $item;
            }
            $table->primary( $col_primary );
            if ( isset( $scheme['display_system'] ) && $scheme['display_system'] ) {
                $table->display_system( 1 );
            } else {
                $table->display_system( 0 );
            }
            if ( $do_not_output ) {
                $table->do_not_output( 1 );
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
            $col_extras = isset( $scheme['extras'] ) ? $scheme['extras'] : [];
            $translates = isset( $scheme['translate'] ) ? $scheme['translate'] : [];
            $hints = isset( $scheme['hint'] ) ? $scheme['hint'] : [];
            $disp_edit = isset( $scheme['disp_edit'] ) ? $scheme['disp_edit'] : [];
            $col_unique = isset( $scheme['unique'] ) ? $scheme['unique'] : [];
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
                $record->not_null( 0 );
                $record->index( 0 );
                $record->autoset( 0 );
                $record->unique( 0 );
                $record->unchangeable( 0 );
                $record->not_delete( 0 );
                if ( isset( $defs['not_null'] ) ) $record->not_null( 1 );
                if ( isset( $indexes[ $name ] ) ) $record->index( 1 );
                if ( in_array( $name, $autoset ) ) $record->autoset( 1 );
                if ( isset( $column_labels[ $name ] ) ) {
                    $label = $column_labels[ $name ];
                } else if ( isset( $locale[ $name ] ) ) {
                    $label = $locale[ $name ];
                } else {
                    $phrases = explode( '_', $name );
                    array_walk( $phrases, function( &$str ) { $str = ucfirst( $str ); } );
                    $label = join( ' ', $phrases );
                }
                if ( $item === 'entry' && $name === 'text' ) {
                    $label = 'Body';
                }
                $record->label( $label );
                if ( isset( $edit_props[ $name ] ) ) {
                    $record->edit( $edit_props[ $name ] );
                } else {
                    $record->edit();
                }
                if ( isset( $list_props[ $name ] ) ) {
                    $record->list( $list_props[ $name ] );
                } else {
                    $record->list();
                }
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
                if ( isset( $col_extras[ $name ] ) ) 
                    $record->extra( $col_extras[ $name ] );
                if ( isset( $hints[ $name ] ) ) 
                    $record->hint( $hints[ $name ] );
                if ( isset( $disp_edit[ $name ] ) ) 
                    $record->disp_edit( $disp_edit[ $name ] );
                if ( in_array( $name, $translates ) ) 
                    $record->translate( 1 );
                if ( $record->unique ) {
                    if ( empty( $col_unique ) || ! isset( $col_unique[ $name ] ) ) {
                        $record->unique( 0 );
                    }
                }
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
        return $tbl_count;
    }

    function save_filter_table ( &$cb, $app, &$obj ) {
        if ( $app->param( '_preview' ) ) return true;
        $validation = $app->param( '__validation' );
        if (! $obj->id ) {
            $name = strtolower( $app->param( 'name' ) );
            if (! $app->is_valid_property( $name, $msg, true ) ) {
                $cb['error'] = $msg;
                return false;
            }
            $default_models = $app->get_config( 'default_models' );
            $reserved_models = $this->reserved_models;
            if ( $default_models ) {
                $default_models = explode( ',', $default_models->value );
            }
            $default_models = is_array( $default_models )
                            ? array_merge( $default_models, $reserved_models )
                            : $reserved_models;
            if ( in_array( $name, $default_models ) ) {
                $cb['error'] = $app->translate( 'The name %s is reserved.', $name );
                return false;
            }
            $obj->name( $name );
        }
        $primary = $app->param( 'primary' );
        if ( $primary && !$app->is_valid_property( $primary, $msg, true ) ) {
            $cb['error'] = $msg;
            return false;
        }
        // TODO check reserved column name e.g. magic_token
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
        $can_index   = ['tinyint', 'int', 'string', 'datetime'];
        $db = $app->db;
        $db->can_drop = true;
        $columns = $db->model( 'column' )->load( ['table_id' => $obj->id ] );
        $col_names = [];
        $primary_cols = [];
        foreach ( $columns as $column ) {
            $col_name = $column->name;
            $col_names[] = $col_name;
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
                if ( $col_name !== $obj->primary && ( !$column->not_delete ||
                    $app->develop ) ) {
                    if (! $validation ) {
                        // Cleanup relation
                        if ( $column->type == 'relation' ) {
                            $rel_model = $column->options;
                            $rel_name = $column->name;
                            $placements = $db->model( 'relation' )->load(
                                ['name' => $rel_name, 'from_obj' => $obj->name,
                                 'to_obj' => $rel_model ], null, 'id' );
                            if ( count( $placements ) ) {
                                $db->model( 'relation' )->remove_multi( $placements );
                            }
                        }
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
            $extra = $app->param( '_extra_' . $id );
            $disp_edit = $app->param( '_disp_edit_' . $id );
            $default = $app->param( '_default_' . $id );
            $not_null = $app->param( '_not_null_' . $id ) ? 1 : 0;
            $index = $app->param( '_index_' . $id ) ? 1 : 0;
            if ( $index && ! in_array( $type, $can_index ) ) {
                $errors[] = $app->translate( 'Can not specify an index for \'%s\'.', $type );
                $index = 0;
            }
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
            $column->extra( $extra );
            $column->order( $order );
            $column->not_null( $not_null );
            $column->index( $index );
            $column->disp_edit( $disp_edit );
            if ( $column->name == 'status' ) {
                $column->default( $obj->default_status );
            } else if ( $type == 'boolean' ) {
                $default = $default ? 1 : 0;
                $column->default( $default );
            } else if ( $type == 'integer' ) {
                $default += 0;
                $column->default( $default );
            } else {
                $column->default( $default );
            }
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
        $prefix = $obj->name;
        foreach ( $add_ids as $id ) {
            $name = strtolower( trim( $app->param( '_new_name_' . $id ) ) );
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
            if ( strpos( $name, $prefix ) === 0 ) {
                $errors[] =
                    $app->translate( "The name starting with '%s' can not be specified(%s).",
                        [ $prefix, $name ] );
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
            $default = $app->param( '_new_default_' . $id );
            $not_null = $app->param( '_new_not_null_' . $id ) ? 1 : 0;
            $index = $app->param( '_new_index_' . $id ) ? 1 : 0;
            if ( $index && ! in_array( $type, $can_index ) ) {
                $errors[] = $app->translate( 'Can not specify an index for \'%s\'.', $type );
                $index = 0;
            }
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
            if ( $type == 'boolean' ) {
                $default = $default ? 1 : 0;
            } else if ( $type == 'integer' ) {
                $default += 0;
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
                    'default'   => $default,
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
                array_unshift( $errors, '' );
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

    function post_save_table ( $cb, $app, $obj, $original = null ) {
        $app->caching = false;
        $force = false;
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
            || $obj->hierarchy || $obj->allow_comment || $obj->has_uuid 
            || $obj->has_assets || $obj->has_attachments || $obj->has_form ) {
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
                $values = ['type' => 'int', 'size' => 11,
                           'label'=> 'Previous Owner',
                           'list' => 'reference:user:nickname',
                           'index' => 1, 'order' => $last ];
                if ( $this->make_column( $obj, 'previous_owner', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
            }
            if ( $obj->has_form ) {
                $values = ['type' => 'int', 'size' => 11,
                           'label'=> 'Form',
                           'edit' => 'relation:form:name:dialog',
                           'list' => 'reference:form:name',
                           'index' => 1,
                           'order' => $last ];
                if ( $this->make_column( $obj, 'form_id', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
            }
            if ( $obj->has_assets ) {
                $edit = "relation:asset:label:dialog";
                $values = ['type' => 'relation',
                           'label'=> 'Assets',
                           'edit' => $edit,
                           'options' => 'asset',
                           'order' => $last ];
                if ( $this->make_column( $obj, 'assets', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
            }
            if ( $obj->has_attachments ) {
                $edit = "relation:attachmentfile:name:dialog";
                $values = ['type' => 'relation',
                           'label'=> 'Attachment Files',
                           'edit' => $edit,
                           'options' => 'attachmentfile',
                           'order' => $last ];
                if ( $this->make_column( $obj, 'attachmentfiles', $values, $force ) ) {
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
                if ( $this->make_column( $obj, 'parent_id', $values, true ) ) {
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
                    $status_opt = 'Draft,Review,Approval Pending,Reserved,Publish,Ended';
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
            if ( $obj->has_uuid ) {
                $values = ['type' => 'string', 'size' => 255,
                           'label'=> 'UUID',
                           'edit' => 'text_short',
                           'unchangeable' => 1,
                           'index' => 1, 'order' => $last ];
                if ( $this->make_column( $obj, 'uuid', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
            }
            if ( $obj->space_child || $obj->display_space ) {
                $values = ['type' => 'int', 'size' => 11,
                           'label'=> 'WorkSpace',
                           'default' => 0,
                           'list' => 'reference:workspace:name', 'unchangeable' => 1,
                           'autoset' => 1, 'index' => 1, 'order' => $last ];
                if ( $this->make_column( $obj, 'workspace_id', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
            }
            if ( $obj->revisable ) {
                $values = ['type' => 'int', 'size' => 11, 'autoset' => 1,
                           'label'=> 'Type', 'not_null' => 1, 'list' => 'text_short',
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
                $values = ['type' => 'string', 'size' => 255, 'index' => 1, 'list' => 'text',
                           'label'=> 'Change Note', 'order' => $last ];
                if ( $this->make_column( $obj, 'rev_note', $values, $force ) ) {
                    $last++;
                    $upgrade = true;
                }
                $values = ['type' => 'text', 'label'=> 'Diff', 'list' => 'popover',
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
        $model = $obj->name;
        $version_opt =
          $app->db->model( 'option' )->get_by_key(
          ['kind' => 'scheme_version', 'key' => $model ] );
        $scheme_v = (int) $version_opt->value;
        $new_ver = (int) $obj->version;
        if ( $obj->version && $new_ver > $scheme_v ) {
            $version_opt->value( $new_ver );
            $version_opt->save();
        }
        if ( $original->id && ! $original->has_uuid && $obj->has_uuid ) {
            $app->get_scheme_from_db( $obj->name, true );
            $_model = $db->model( $obj->name )->new();
            $terms = ['uuid' => ['IS NULL' => 1]];
            $objects = $db->model( $obj->name )->load( $terms, null, '*', ' OR ' .
                $_model->_colprefix . 'uuid=\'\'' );
            if (! empty( $objects ) ) {
                $new_objects = [];
                foreach ( $objects as $uu_obj ) {
                    $uu_obj->uuid( $app->generate_uuid() );
                    $new_objects[] = $uu_obj;
                }
                $_model->update_multi( $new_objects );
            }
        }
        if (! $cb['is_new'] ) return;
        $values = ['type' => 'int', 'size' => 11,
                   'label'=> 'ID', 'is_primary' => 1,
                   'list' => 'number', 'edit' => 'hidden',
                   'index' => 1, 'order' => 1, 'not_null' => 1];
        $this->make_column( $obj, 'id', $values, $force );
        $db->upgrader = false;
        $scheme = $app->get_scheme_from_db( $model );
        $colprefix = $db->colprefix;
        if ( $colprefix ) {
            if ( strpos( $colprefix, '<model>' ) !== false )
                $colprefix = str_replace( '<model>', $model, $colprefix );
            else if ( strpos( $colprefix, '<table>' ) !== false )
                $colprefix = str_replace( '<table>', $app->_table, $colprefix );
        }
        if (! isset( $scheme['indexes']['PRIMARY'] ) ) {
            $scheme['indexes']['PRIMARY'] = $db->id_column;
            $scheme['column_defs'][ $db->id_column ] =
                ['type' => 'int', 'size' => 11, 'not_null' => 1];
        }
        $db->upgrader = true;
        $db->caching = false;
        $res = $db->base_model->create_table( $model, $db->prefix . $model,
                                                $colprefix, $scheme, true );
        $db->logging = true;
        $ctx->logging = true;
        $app->clear_all_cache();
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

    function version_up ( $app, $old, $version ) {
        $this->start_upgrade( $app );
        $upgrade_functions = $this->core_upgrade_functions( $old );
        foreach ( $upgrade_functions as $func ) {
            list( $component, $method ) = [ $func['component'], $func['method'] ];
            $component = $app->component( $component );
            if ( is_object( $component ) && method_exists( $component, $method ) ) {
                $component->$method( $app );
            }
        }
        $app->set_config( ['app_version' => $version ] );
        $app->app_version = $version;
    }

    function upgrade_status ( $app ) {
        $tables = $app->db->model( 'table' )->load( ['start_end' => 1] );
        $options = 'Draft,Review,Approval Pending,Reserved,Publish,Ended';
        foreach ( $tables as $table ) {
            $col = $app->db->model( 'column' )->get_by_key( ['table_id' => $table->id,
                                                       'name' => 'status'] );
            if ( $col->id && $col->options != $options ) {
                $col->options( $options );
                $col->save();
            }
            if ( $table->default_status && $table->default_status < 3 ) {
                $table->default_status( $table->default_status - 1 );
            } else if (! $table->default_status ) {
                $table->default_status( 0 );
            }
            $objects = $app->db->model( $table->name )->load( ['status' => ['<' => 3] ] );
            $update_objects = [];
            foreach ( $objects as $obj ) {
                if ( $obj->status ) {
                    $obj->status( $obj->status - 1 );
                    $update_objects[] = $obj;
                }
            }
            if (! empty( $update_objects ) ) {
                $app->db->model( $table->name )->update_multi( $update_objects );
            }
            $table->save();
        }
    }

    function set_preferred ( $app ) {
        unset( $app->db->scheme['urlmapping'] );
        $app->logging = false;
        $dir = LIB_DIR . 'PADO' . DS . 'models';
        $this->setup_db( true, 'core', [ 'urlmapping' ], $dir );
        $app->get_scheme_from_db( 'urlmapping' );
        $db = $app->db;
        $workspaces = $db->model( 'workspace' )->load( [], null, 'id' );
        $ws_ids = [0];
        foreach ( $workspaces as $workspace ) {
            $ws_ids[] = (int) $workspace->id;
        }
        foreach ( $ws_ids as $ws_id ) {
            $urlmappings = $db->model( 'urlmapping' )->load(
              ['workspace_id' => $ws_id ], ['sort' => 'id', 'direction' => 'ascend'] );
            $models = [];
            foreach ( $urlmappings as $urlmapping ) {
                if ( $urlmapping->model == 'template' ) continue;
                if ( isset( $models[ $urlmapping->model ] ) ) continue;
                $models[ $urlmapping->model ] = true;
                $urlmapping->is_preferred( 1 );
                $urlmapping->save();
            }
        }
    }

    function update_perm ( $app ) {
        $sessions = $app->db->model( 'session' )->load(
                                                ['name' => 'user_permissions',
                                                 'kind' => 'PM'] );
        if ( is_array( $sessions ) && !empty( $sessions ) ) {
            $app->db->model( 'session' )->remove_multi( $sessions );
        }
    }
  
    function update1006 ( $app ) {
        $cf = $app->get_table( 'field' );
        $column = $app->db->model( 'column' )->get_by_key( ['table_id' => $cf->id,
                                                            'name' => 'required'] );
        if ( $column->id && $column->hint ) {
            $column->hint( '' );
            $column->save();
        }
        $column = $app->db->model( 'column' )->get_by_key( ['table_id' => $cf->id,
                                                            'name' => 'translate_labels'] );
        if ( $column->id && $column->label == 'Translate' ) {
            $column->label( 'Translate Labels' );
            $column->save();
        }
        $tables = $app->db->model( 'table' )->load();
        foreach ( $tables as $table ) {
            if ( $table->hierarchy != 1 ) {
                $table->hierarchy( 0 );
                $table->save();
            }
        }
    }

    function set_workspace ( $app ) {
        $tables = $app->db->show_tables();
        $pfx = DB_PREFIX;
        $db = $app->db;
        $db->begin_work();
        $app->txn_active = true;
        foreach ( $tables as $table ) {
            $t = $table[0];
            $t = preg_replace( "/^$pfx/", '', $t );
            $app->get_scheme_from_db( $t );
            if ( $db->model( $t )->has_column( 'workspace_id' ) ) {
                $sql = "SELECT {$t}_id FROM {$pfx}{$t} WHERE {$t}_workspace_id IS NULL";
                $objects = $db->model( $t )->load( $sql );
                $update_objs = [];
                foreach ( $objects as $obj ) {
                    $obj->workspace_id( 0 );
                    $update_objs[] = $obj;
                }
                if ( count( $update_objs ) ) {
                    $db->model( $t )->update_multi( $update_objs );
                }
            }
        }
        $db->commit();
        $app->txn_active = false;
    }

    function upgrade_scheme_check ( $app ) {
        $upgrade_count = 0;
        $schemes = $app->db->model( 'option' )->load( ['kind' => 'scheme_version'] );
        $model_names = [];
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
                $models_dir = $this->get_models_dir( $app, $model );
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
                }
            }
        }
        $json_dirs = array_keys( $this->plugin_models( true ) );
        $json_dirs = array_merge( $app->model_paths, $json_dirs );
        foreach ( $json_dirs as $dir ) {
            $files = scandir( $dir, $app->plugin_order );
            foreach ( $files as $json ) {
                if ( strpos( $json, '.' ) === 0 ) continue;
                $file = $dir . DS . $json;
                $extension = pathinfo( $json )['extension'];
                if ( $extension !== 'json' ) continue;
                $model = pathinfo( $json )['filename'];
                if (! in_array( $model, $model_names ) ) {
                    $component = in_array( $dir, $app->model_paths )
                               ? 'Prototype'
                               : strtolower( basename( dirname( dirname( $file ) ) ) );
                    $data = json_decode( file_get_contents( $file ), true );
                    if ( isset( $data['component'] ) ) $component = $data['component'];
                    $version = isset( $data['version'] ) ? $data['version'] : 0;
                    $upgrade_count++;
                }
            }
        }
        $cfg = $app->db->model( 'option' )->get_by_key(
            ['kind' => 'config', 'key' => 'upgrade_count'] );
        $cfg->value( $upgrade_count );
        $cfg->data( time() );
        $cfg->save();
        return $upgrade_count;
    }

    function drop ( $table ) {
        $app = Prototype::get_instance();
        $this->start_upgrade( $app );
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
