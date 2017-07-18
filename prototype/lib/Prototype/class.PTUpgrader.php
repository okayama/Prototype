<?php

class PTUpgrader {

    function setup_db ( $force = false ) {
        $app = Prototype::get_instance();
        $db = $app->db;
        $db->json_model = true;
        $db->upgrader = true;
        $init = $db->model( 'option' )->new();
        $init = $db->model( 'session' )->new();
        $init = $db->model( 'table' )->new();
        $models_dir = LIB_DIR . 'PADO' . DS . 'models';
        $items = scandir( $models_dir );
        $ws_children = [];
        $workspace = null;
        array_unshift( $items, 'phrase.json' );
        $items = array_flip( $items );
        $items = array_keys( $items );
        $default_models = [];
        foreach ( $items as $item ) {
            if ( strpos( $item, '.' ) === 0 ) continue;
            $file = $models_dir . DS . $item;
            $item = str_replace( '.json', '', $item );
            $default_models[] = $item;
            $init = $db->model( $item )->new();
            if ( $item === 'column' || $item === 'option'
                || $item === 'meta' || $item === 'session' ) continue;
            $table = $db->model( 'table' )->get_by_key( ['name' => $item ] );
            $scheme = $properties = $db->scheme[ $item ];
            if ( isset( $scheme['locale'] ) ) {
                $locale = $scheme['locale'];
                foreach ( $locale as $lang => $dict ) {
                    if ( $lang == 'default' ) {
                        $app->dictionary['default'] = array_merge(
                        $app->dictionary['default'], $scheme['locale']['default']
                        );
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
            $label = $properties['label'];
            $plural = $properties['plural'];
            $auditing = isset( $properties['auditing'] ) ? $properties['auditing'] : 0;
            $sort_by = isset( $properties['sort_by'] ) ? $properties['sort_by'] : null;
            $col_primary = isset( $properties['primary'] ) ? $properties['primary'] : null;
            $order = isset( $properties['order'] ) ? $properties['order'] : 0;
            // $version = isset( $properties['version'] ) ? $properties['version'] : null;
            $child_of = isset( $properties['child_of'] ) ? $properties['child_of'] : null;
            $display_space = isset( $properties['display_space'] )
                ? $properties['display_space'] : null;
            if (! $table->label ) $table->label( $label );
            if (! $table->plural ) $table->plural( $plural );
            if (! $table->order ) $table->order( $order );
            if (! $table->display_space ) $table->display_space( $display_space );
            $table->auditing( $auditing );
            if ( $sort_by ) {
                $sort_key = key( $sort_by );
                $sort_order = $sort_by[ $sort_key ];
                $table->sort_by( $sort_key );
                $table->sort_order( $sort_order );
            }
            foreach ( $child_tables as $child ) {
                $table = $app->set_child_tables( $child, $table, true, false );
            }
            if ( $child_of === 'workspace' ) {
                $table->space_child( 1 );
                $ws_children[] = $item;
                $table->display_space( 1 );
            }
            $table->primary( $col_primary );
            if ( isset( $scheme['display'] ) && $scheme['display'] ) {
                $table->display( 1 );
            } else {
                $table->display( 0 );
            }
            if ( isset( $scheme['sortable'] ) && $scheme['sortable'] )
                $table->sortable( 1 );
            $app->set_default( $table );
            $table->not_delete( 1 );
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
                    $label = $app->translate( join( ' ', $phrases ) );
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
                $locale_dir = __DIR__ . DS . 'locale';
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
                $workspace = $app->set_child_tables( $child, $workspace, true, false );
            }
            $workspace->save();
        }
        $app->set_config( ['default_models' => join( ',', $default_models ) ] );
    }


    function delete_filter_table ( &$cb, $app, &$obj ) {
        $ids = $app->param( 'id' );
        if (!is_array( $ids ) ) $ids = [ $ids ];
        $tables = $app->db->model( 'table' )->load( [ 'id' => [ 'IN' => $ids ] ] );
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

    function save_filter_table ( &$cb, $app, &$obj ) {
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
                    $cb['error'] = $app->translate( "The name %s is reserved.", $name );
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
        $not_specify = ['save', 'delete', 'remove'];
        $types       = ['boolean'  => [ 'tinyint', 4 ],
                        'integer'  => [ 'int', 11 ],
                        'text'     => [ 'text', '' ],
                        'blob'     => [ 'blob', '' ],
                        'relation' => [ 'relation', '' ],
                        'datetime' => [ 'datetime', '' ],
                        'string'   => [ 'string', 255 ] ];
        $list_types  = ['checkbox', 'number', 'primary', 'text',
                        'text_short', 'password', 'datetime', 'date'];
        $edit_types  = ['hidden', 'checkbox', 'number', 'primary', 'text', 'file',
                        'text_short', 'textarea', 'password', 'password(hash)',
                        'datetime', 'languages', 'richtext', 'selection', 'color'];
        $db = $app->db;
        $db->can_drop = true;
        $columns = $db->model( 'column' )->load( [ 'table_id' => $obj->id ] );
        $col_names = [];
        $validation = $app->param( '__validation' );
        foreach ( $columns as $column ) {
            $col_name = $column->name;
            $col_names[] = $column->name;
            $id = $column->id;
            $order = $app->param( '_order_' . $id );
            $order = (int) $order;
            if ( $column->is_primary ) {
                $list = $app->param( '_list_' . $id ) ? 'number' : '';
                $column->order( $order );
                $column->list( $list );
                if (! $validation ) $column->save();
                continue;
            }
            if (!in_array( $id, $ids ) ) {
                if ( $col_name !== $obj->primary && !$column->not_delete ) {
                    if (! $validation ) $column->remove();
                    continue;
                }
            }
            $type = $app->param( '_type_' . $id );
            if (! $type || !isset( $types[ $type ] ) ) {
                $errors[] = $app->translate( 'Invalid type (%s).', $type );
                continue;
            }
            list( $type, $size ) = $types[ $type ];
            // if ( $type === 'string' && $app->param( '_size_' . $id ) ) {
            //     $size = $app->param( '_size_' . $id );
            // }
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
            $column->autoset( $autoset );
            if ( empty( $errors ) ) {
                $app->set_default( $column );
                if (! $validation ) $column->save();
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
            $column = $db->model( 'column' )->get_by_key( [
                'table_id'     => $obj->id,
                'name'         => $name,
                'label'        => $label,
                'type'         => $type,
                'size'         => $size,
                'order'        => $order,
                'not_null'     => $not_null,
                'index'        => $index,
                'options'      => $options,
                'disp_edit'    => $disp_edit,
                'unique'       => $unique,
                'list'         => $list,
                'edit'         => $edit,
                'autoset'      => $autoset,
                'unchangeable' => $unchangeable,
            ] );
            if ( empty( $errors ) ) {
                $app->set_default( $column );
                if (! $validation ) $column->save();
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
        $this->upgrade_scheme( $obj->name );
        return true;
    }

    function post_save_table ( &$cb, $app, $obj ) {
        $is_child = $obj->space_child;
        $db = $app->db;
        $workspace_col = $db->model( 'column' )->get_by_key
            ( [ 'table_id' => $obj->id, 'name' => 'workspace_id' ] );
        if ( $workspace_col->id ) $is_child = true;
        if( $is_child || $obj->sortable || $obj->auditing ) {
            $last = $db->model( 'column' )->load
                    ( [ 'table_id' => $obj->id ],
                      ['sort' => 'order', 'direction' => 'descend', 'limit' => 1 ] );
            $last = (! empty( $last ) ) ? $last[0]->order : 10;
            $last++;
            $upgrade = false;
            if ( $obj->sortable ) {
                $order_col = $db->model( 'column' )->get_by_key
                    ( [ 'table_id' => $obj->id, 'name' => 'order' ] );
                if (! $order_col->id ) {
                    $order_col->type( 'int' );
                    $order_col->size( 4 );
                    $order_col->label( 'Order' );
                    $order_col->list( 'number' );
                    $order_col->edit( 'number' );
                    $order_col->index( 1 );
                    $order_col->order( $last );
                    $app->set_default( $order_col );
                    $order_col->save();
                    $last++;
                    $upgrade = true;
                }
            }
            if ( $obj->space_child ) {
                $workspace_col = $db->model( 'column' )->get_by_key
                    ( [ 'table_id' => $obj->id, 'name' => 'workspace_id' ] );
                if (! $workspace_col->id ) {
                    $workspace_col->type( 'int' );
                    $workspace_col->size( 4 );
                    $workspace_col->label( 'WorkSpace' );
                    $workspace_col->list( 'reference:workspace:name' );
                    $workspace_col->unchangeable( 1 );
                    $workspace_col->index( 1 );
                    $workspace_col->autoset( 1 );
                    $workspace_col->order( $last );
                    $app->set_default( $workspace_col );
                    $workspace_col->save();
                    $upgrade = true;
                    $last++;
                }
            }
            if ( $is_child ) {
                $ws_table = $db->model( 'table' )->get_by_key( ['name' => 'workspace'] );
                if ( $ws_table->id ) {
                    $app->set_child_tables( $obj->name, $ws_table, true, true );
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
                      ( [ 'table_id' => $obj->id, 'name' => $name ] );
                    if (! $col->id ) {
                        list( $label, $type ) = [ $props['label'], $props['type'] ];
                        $col->label( $label );
                        if ( $type === 'datetime' ) {
                            $col->type( $type );
                            $col->edit( $type );
                            if ( $name == 'modified_on' ) {
                                $col->list( $type );
                            }
                        } else {
                            $col->type( 'int' );
                            $col->size( 4 );
                            $col->edit( $type );
                            if ( $name == 'modified_by' ) {
                                $col->list( $type );
                            }
                        }
                        $col->index( 1 );
                        $col->autoset( 1 );
                        $col->order( $last );
                        $app->set_default( $col );
                        $col->save();
                    }
                    $upgrade = true;
                    $last++;
                }
            }
            if ( $upgrade ) {
                $this->upgrade_scheme( $obj->name );
            }
        }
        if (! $cb['is_new'] ) return;
        $column = $db->model( 'column' )->get_by_key( [
            'table_id'     => $obj->id,
            'name'         => 'id',
            'label'        => 'ID',
            'type'         => 'int',
            'size'         => 11,
            'order'        => 1,
            'not_null'     => 1,
            'is_primary'   => 1,
            'list'         => 'number',
            'edit'         => 'hidden'
        ] );
        $app->set_default( $column );
        $column->save();
        $db->upgrader = false;
        $model = $obj->name;
        $scheme = $app->get_scheme_from_db( $model );
        $colprefix = $db->colprefix;
        if ( $colprefix ) {
            if ( strpos( $colprefix, '<model>' ) !== false )
                $colprefix = str_replace( '<model>', $model, $colprefix );
            elseif ( strpos( $colprefix, '<table>' ) !== false )
                $colprefix = str_replace( '<table>', $app->_table, $colprefix );
        }
        return $db->base_model->create_table( $model, $db->prefix . $model,
                                                $colprefix, $scheme );
    }


    function upgrade () {
        $app = Prototype::get_instance();
        $tmpl = TMPL_DIR . 'upgrade.tmpl';
        if (! is_readable( $tmpl ) )
            return; // Show Error
        $ctx = $app->ctx;
        $ctx->vars[ 'language' ] = $app->language;
        if ( $app->param( '_type' ) === 'install' ) {
            $ctx->vars[ 'page_title' ] = $app->translate( 'Install' );
        } else {
            $ctx->vars[ 'page_title' ] = $app->translate( 'Upgrade' );
        }
        if ( $app->request_method === 'POST' )
      {
        if ( $app->param( '_type' ) === 'install' ) {
            $name = $app->param( 'name' );
            $pass = $app->param( 'password' );
            $verify = $app->param( 'password-verify' );
            $email = $app->param( 'email' );
            $appname = $app->param( 'appname' );
            $site_path = $app->param( 'site_path' );
            $site_url = $app->param( 'site_url' );
            $extra_path = $app->param( 'extra_path' );
            $copyright = $app->param( 'copyright' );
            $system_email = $app->param( 'system_email' );
            $errors = [];
            if (!$appname || !$site_url || !$system_email ) {
                $errors[] = $app->translate( 'App Name, System Email and Site URL are required.' );
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
                // $app->sanitize_dir( $site_path ); // TODO writable check .htaccess
                $app->sanitize_dir( $extra_path );
                if ( $site_path &&
                    !$app->is_valid_property( str_replace( '/', '', $site_path ) ) ) {
                    $errors[] = $app->translate(
                        'Site URL contains an illegal character.' );
                }
                if ( $extra_path &&
                    !$app->is_valid_property( str_replace( '/', '', $extra_path ) ) ) {
                    $errors[] = $app->translate(
                        'Upload Path contains an illegal character.' );
                }
            }
            if (! empty( $errors ) ) {
                $app->assign_params( $app, $ctx );
                $ctx->vars['error'] = join( "\n", $errors );
                echo $ctx->build_page( $tmpl );
                exit();
            }
            $default_widget = TMPL_DIR . 'parts' . DS . 'default_widget.tmpl';
            $default_widget = file_get_contents( $default_widget );
            $db = $app->db;
            $cfgs = ['appname'    => $appname,
                     'site_path'  => $site_path,
                     'site_url'   => $site_url,
                     'extra_path' => $extra_path,
                     'copyright'  => $copyright,
                     'system_email' => $system_email,
                     'default_widget' => $default_widget ];
            $app->set_config( $cfgs );
            $password = $app->param( 'password' );
            $language = $app->param( 'language' );
            $nickname = $app->param( 'nickname' );
            $password = password_hash( $password, PASSWORD_BCRYPT );
            $this->setup_db( true );
            $user = $db->model( 'user' )->get_by_key( [ 'name' => $name ] );
            $user->name( $name );
            $user->password( $password );
            $user->email( $email );
            $user->nickname( $nickname );
            $user->language( $language ); // White List Check
            $user->is_superuser( 1 );
            $user->modified_on( date( 'YmdHis' ) );
            $user->created_on( date( 'YmdHis' ) );
            $user->save();
            $app->redirect( $app->admin_url );
        }
      } else {
        $ctx->vars['site_url'] = $app->base . '/';
        $ctx->vars['site_path'] = ltrim( $app->path, '/' ) . 'site/';
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
        $comp_defs = $scheme['column_defs'];
        $comp_idxs = $scheme['indexes'];
        foreach ( $column_defs as $key => $props ) {
            unset( $column_defs[ $key ]['default'] );
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
            return $model->upgrade( $model->_table, $upgrade, $model->_colprefix );
        }
    }
}
