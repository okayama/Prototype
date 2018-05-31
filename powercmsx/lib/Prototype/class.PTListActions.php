<?php

class PTListActions {

    function list_action ( $app ) {
        $app->validate_magic();
        $db = $app->db;
        $model = $app->param( '_model' );
        $list_actions = $this->get_list_actions( $model );
        $action_name = $app->param( 'action_name' );
        $action = $list_actions[ $action_name ];
        if ( is_array( $action ) && isset( $action['component'] ) ) {
            if ( isset( $action['columns'] ) && $action['columns'] ) {
                $objects = $app->get_object( $model, $action['columns'] );
            } else {
                $objects = $app->get_object( $model );
            }
            foreach ( $objects as $obj ) {
                if (! $app->can_do( $model, 'edit', $obj ) ) {
                    return $app->error( 'Permission denied.' );
                }
            }
            $component = $action['component'];
            $meth = $action['method'];
            if ( method_exists( $component, $meth ) ) {
                $db->caching = false;
                return $component->$meth( $app, $objects, $action );
            }
        }
        return $app->error( 'Invalid request.' );
    }

    function get_list_actions ( $model, &$list_actions = [] ) {
        $app = Prototype::get_instance();
        $table = $app->get_table( $model );
        if ( $table->has_status ) {
            $list_actions[] = ['name' => 'set_status', 'input' => 0,
                               'label' => $app->translate( 'Set Status' ),
                               'component' => $this,
                               'method' => 'set_status'];
        }
        if ( $table->taggable ) {
            $list_actions[] = ['name' => 'add_tags', 'input' => 1,
                               'label' => $app->translate( 'Add Tags' ),
                               'component' => $this,
                               'method' => 'add_tags'];
            $list_actions[] = ['name' => 'remove_tags', 'input' => 1,
                               'label' => $app->translate( 'Remove Tags' ),
                               'component' => $this,
                               'method' => 'remove_tags'];
        }
        if ( $table->name === 'asset' ) {
            $list_actions[] = ['name' => 'publish_assets', 'input' => 0,
                               'label' => $app->translate( 'Publish Files' ),
                               'component' => $this,
                               'method' => 'publish_assets'];
        }
        if ( $table->name === 'template' ) {
            $list_actions[] = ['name' => 'recompile_cache', 'input' => 0,
                               'label' => $app->translate( 'Re-Compile Cache' ),
                               'component' => $this,
                               'columns' => ['id', 'text', 'compiled', 'cache_key'],
                               'method' => 'recompile_cache'];
        }
        if ( $table->name === 'fieldtype' ) {
            $list_actions[] = ['name' => 'export_fieldtypes', 'input' => 0,
                               'label' => $app->translate( 'Export' ),
                               'component' => $this,
                               'method' => 'export_fieldtypes'];
        }
        if ( $table->im_export ) {
            $list_actions[] = ['name' => 'export_objects', 'input' => 0,
                               'label' => $app->translate( 'Export CSV' ),
                               'component' => $this,
                               'columns' => '*',
                               'method' => 'export_objects'];
        }
        return $app->get_registries( $model, 'list_actions', $list_actions );
    }

    function export_objects ( $app, $objects, $action ) {
        $model = $app->param( '_model' );
        $export_type = (int) $app->param( 'itemset_export_select' );
        if ( $export_type > 0 && $export_type < 5 ) {
            $encoding = ( $export_type < 3 ) ? 'UTF-8' : 'Shift_JIS';
            $without_id = false;
            if ( $export_type == 2 || $export_type == 4 ) {
                $without_id = true;
            }
        } else {
            return $app->error( 'Invalid request.' );
        }
        $table = $app->get_table( $model );
        $scheme = $app->get_scheme_from_db( $model );
        $column_defs = $scheme['column_defs'];
        $column_keys = array_keys( $column_defs );
        $plural = strtolower( $table->plural );
        $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];
        $ts = date( 'Y-m-d_H-i-s' );
        /*
        header( 'Content-Type: application/octet-stream' );
        header( "Content-Disposition: attachment; filename={$plural}-{$ts}.csv" );
        $fp = fopen( 'php://output','w' );
        */
        $upload_dir = $app->upload_dir();
        $csv_out = $upload_dir . DS . "{$plural}-{$ts}.csv";
        $fp = fopen( $csv_out,'w' );
        if ( $encoding == 'Shift_JIS' ) {
            stream_filter_append( $fp, 'convert.iconv.UTF-8/CP932', STREAM_FILTER_WRITE );
        }
        $excludes = ['uuid', 'rev_type', 'rev_object_id', 'rev_changed', 'rev_diff',
                     'created_on', 'modified_on', 'created_by', 'modified_by'];
        $i = 0;
        foreach ( $objects as $obj ) {
            $values = $obj->get_values();
            if ( $without_id ) {
                unset( $values["{$model}_id"] );
            }
            foreach ( $excludes as $exclude ) {
                if ( isset( $values["{$model}_{$exclude}"] ) ) {
                    unset( $values["{$model}_{$exclude}"] );
                }
            }
            $__values = [];
            foreach ( $column_keys as $column_key ) {
                if ( array_key_exists( "{$model}_{$column_key}", $values ) ) {
                    $__values["{$model}_{$column_key}"] = $values["{$model}_{$column_key}"];
                }
            }
            $values = $__values;
            if (! $i ) {
                $names = array_keys( $values );
                if (! empty( $relations ) ) {
                    $rel_names = array_keys( $relations );
                    foreach ( $rel_names as $rel_name ) {
                        $names[] = "{$model}_{$rel_name}";
                    }
                }
                fputcsv( $fp, $names, ',', '"' );
            }
            $column_values = [];
            foreach ( $values as $key => $value ) {
                $key = preg_replace( "/^{$model}_/", '', $key );
                if ( $column_defs[ $key ]['type'] == 'blob' ) {
                    // $value = ''; // base64_encode( $value );
                    $urlinfo = $app->db->model( 'urlinfo' )->get_by_key(
                        ['model' => $obj->_model, 'object_id' => $obj->id,
                         'key' => $key, 'class' => 'file' ] );
                    $relative_path = $urlinfo->relative_path;
                    $outpath = preg_replace( "/^%r/", $upload_dir, $relative_path );
                    $app->fmgr->mkpath( dirname( $outpath ) );
                    file_put_contents( $outpath, $value );
                    $value = $relative_path;
                } else if ( $column_defs[ $key ]['type'] == 'datetime' ) {
                    $value = $obj->db2ts( $value );
                    if ( $value ) {
                        $value = date( DATE_ATOM, strtotime( $value ) );
                        list( $value, $tz ) = explode( '+', $value );
                    }
                }
                $column_values[] = $value;
            }
            if (! empty( $relations ) ) {
                foreach ( $relations as $name => $to_obj ) {
                    $terms = ['from_id'  => $obj->id, 
                              'name'     => $name,
                              'from_obj' => $obj->_model ];
                    if ( $to_obj !== '__any__' ) $terms['to_obj'] = $to_obj;
                    $rel_objs = $app->db->model( 'relation' )->load(
                                                $terms, ['sort' => 'order'] );
                    $ids = [];
                    $labels = [];
                    $rel_model = '';
                    foreach( $rel_objs as $relation ) {
                        $rel_model = $relation->to_obj;
                        if (! $rel_model ) continue;
                        $to_id = (int) $relation->to_id;
                        $rel_obj = $app->db->model( $rel_model )->load( $to_id );
                        if ( $rel_obj ) {
                            $rel_table = $app->get_table( $rel_model );
                            $primary = $rel_table->primary;
                            if ( $rel_obj->_model == 'asset' ) {
                                if ( $rel_obj->file ) {
                                    $relative_path = $app->get_assetproperty(
                                            $rel_obj, 'file', 'relative_path' );
                                    if ( $relative_path ) {
                                        $outpath = preg_replace(
                                            "/^%r/", $upload_dir, $relative_path );
                                        $app->fmgr->mkpath( dirname( $outpath ) );
                                        file_put_contents( $outpath, $rel_obj->file );
                                        $relative_path = $rel_obj->workspace_id ?
                                            "%w/{$relative_path}" : "%s/{$relative_path}";
                                        $labels[] = $relative_path;
                                    }
                                }
                            } else {
                                $labels[] = $rel_obj->$primary;
                            }
                        }
                    }
                    if ( $to_obj == '__any__' ) {
                        array_unshift( $labels, $rel_model );
                    }
                    $column_values[] = implode( ',', $labels );
                }
            }
            fputcsv( $fp, $column_values, ',', '"' );
            $i++;
        }
        fclose( $fp );
        $zip = $upload_dir . DS . "{$plural}-{$ts}.zip";
        PTUtil::make_zip_archive( $upload_dir, $zip, "{$plural}-{$ts}" );
        PTUtil::export_data( $zip );
        PTUtil::remove_dir( $upload_dir );
    }

    function set_status ( $app, $objects, $action ) {
        $model = $app->param( '_model' );
        $status_published = $app->status_published( $model );
        $table = $app->get_table( $model );
        if (! $table || ! $table->has_status ) {
            return $app->error( 'Invalid request.' );
        }
        $status = (int) $app->param( 'itemset_action_input' );
        $max_status = $app->max_status( $app->user(), $model );
        if (! $status || $status > $max_status ) {
            return $app->error( 'Invalid request.' );
        }
        $counter = 0;
        $rebuild_ids = [];
        $error = false;
        $db = $app->db;
        $db->begin_work();
        foreach ( $objects as $obj ) {
            if (! $obj->has_column( 'status' ) ) {
                return $app->error( 'Invalid request.' );
            }
            $original = clone $obj;
            if ( $obj->status != $status ) {
                if ( $obj->status == $status_published || $status == $status_published ) {
                    if ( $app->get_permalink( $obj, true ) ) {
                        $rebuild_ids[] = $obj->id;
                    }
                }
                $obj->status( $status );
                if (! $obj->save() ) $error = true;
                $original = clone $obj;
                $callback = ['name' => 'post_save', 'error' => '', 'is_new' => false ];
                $app->run_callbacks( $callback, $model, $obj, $original );
                $counter++;
            }
        }
        if ( $error || !empty( $db->errors ) ) {
            $errstr = $app->translate( 'An error occurred while saving %s.',
                      $app->translate( $table->label ) );
            $app->rollback( $errstr );
        } else {
            $db->commit();
        }
        if ( $counter ) {
            $column = $app->db->model( 'column' )->get_by_key(
                      ['table_id' => $table->id, 'name' => 'status'] );
            $options = $column->options;
            $status_text = $status;
            if ( $options ) {
                $options = explode( ',', $options );
                $status_text = $app->translate( $options[ $status - 1 ] );
            }
            $action = $action['label'] . " ({$status_text})";
            $this->log( $action, $model, $counter );
        }
        $return_args = "does_act=1&__mode=view&_type=list&_model={$model}&"
                     . "apply_actions={$counter}" . $app->workspace_param;
        if (! empty( $rebuild_ids ) ) {
            $ids = join( ',', $rebuild_ids );
            $app->mode = 'rebuild_phase';
            $app->param( '__mode', 'rebuild_phase' );
            $app->param( 'ids', $ids );
            $app->param( 'apply_actions', $counter );
            $app->param( '_return_args', $return_args );
            return $app->rebuild_phase( $app, true, 0, true );
        }
        $app->redirect( $app->admin_url . '?' . $return_args );
    }

    function add_tags ( $app, $objects, $action, $add = true ) {
        $model = $app->param( '_model' );
        $table = $app->get_table( $model );
        if (! $table || ! $table->taggable ) {
            return $app->error( 'Invalid request.' );
        }
        $counter = 0;
        $add_tags = $app->param( 'itemset_action_input' );
        if (! $add_tags ) {
            $app->redirect( $app->admin_url .
                "?__mode=view&_type=list&_model={$model}&apply_actions={$counter}"
                                                    . $app->workspace_param );
        }
        $column = $app->db->model( 'column' )->load(
            ['table_id' => $table->id, 'type' => 'relation', 'options' => 'tag'],
            ['limit' => 1] );
        $name = 'tags';
        if ( is_array( $column ) && count( $column ) ) {
            $column = $column[0];
            $name = $column->name;
        }
        $add_tags = preg_split( '/\s*,\s*/', $add_tags );
        $status_published = $app->status_published( $model );
        $tag_ids = [];
        $tag_objs = [];
        if (! $add ) {
            foreach ( $add_tags as $tag ) {
                $normalize = preg_replace( '/\s+/', '', trim( strtolower( $tag ) ) );
                if (! $tag ) continue;
                $terms = ['normalize' => $normalize ];
                $tags = $app->db->model( 'tag' )->load( $terms );
                if (! empty( $tags ) ) {
                    $tag_objs = array_merge( $tag_objs, $tags );
                }
            }
            if ( empty( $tag_objs ) ) {
                $app->redirect( $app->admin_url .
                    "?__mode=view&_type=list&_model={$model}&apply_actions={$counter}"
                                                        . $app->workspace_param );
            }
            foreach ( $tag_objs as $tag ) {
                $tag_ids[] = $tag->id;
            }
        }
        $rebuild_ids = [];
        $rebuild_tag_ids = [];
        $db = $app->db;
        $db->begin_work();
        foreach ( $objects as $obj ) {
            $res = false;
            if ( $add ) {
                $res = $this->add_tags_to_obj( $obj, $add_tags, $name, $tag_ids );
                $rebuild_tag_ids = $tag_ids;
            } else {
                $relations = $app->get_relations( $obj, 'tag', $name );
                foreach ( $relations as $relation ) {
                    if ( in_array( $relation->to_id, $tag_ids ) ) {
                        $res = $relation->remove();
                        if (! in_array( $relation->to_id, $rebuild_tag_ids ) ) {
                            if ( $obj->status == $status_published ) {
                                $rebuild_tag_ids[] = $relation->to_id;
                            }
                        }
                    }
                }
            }
            if ( $res ) {
                $counter++;
                if ( $table->has_status && $obj->has_column( 'status' ) ) {
                    if ( $obj->status == $status_published ) {
                        if ( $app->get_permalink( $obj, true ) ) {
                            $rebuild_ids[] = $obj->id;
                        }
                    }
                }
            }
        }
        if ( !empty( $db->errors ) ) {
            $errstr = $app->translate( 'An error occurred while saving %s.',
                      $app->translate( $table->label ) );
            return $app->rollback( $errstr );
        } else {
            $db->commit();
        }
        if ( $counter ) {
            $add_tags = join( ', ', $add_tags );
            $action = $action['label'] . " ({$add_tags})";
            $this->log( $action, $model, $counter );
        }
        $return_args = "does_act=1&__mode=view&_type=list&_model={$model}&"
                     ."apply_actions={$counter}" . $app->workspace_param;
        if (! empty( $rebuild_ids ) ) {
            $ids = join( ',', $rebuild_ids );
            if (! empty( $rebuild_tag_ids ) ) {
                $tag_counter = count( $rebuild_tag_ids );
                $return_args .= '&publish_dependencies=' . $tag_counter;
                $tag_ids = join( ',', $rebuild_tag_ids );
                $return_args = '__mode=rebuild_phase&ids=' . $tag_ids
                            . '&_model=tag&apply_actions=' . $tag_counter 
                            . '&_return_args=' . rawurlencode( $return_args );
            }
            $app->mode = 'rebuild_phase';
            $app->param( '__mode', 'rebuild_phase' );
            $app->param( 'ids', $ids );
            $app->param( 'apply_actions', $counter );
            $app->param( '_return_args', $return_args );
            return $app->rebuild_phase( $app, true );
        }
        $app->redirect( $app->admin_url . '?' . $return_args );
    }

    function remove_tags ( $app, $objects, $action ) {
        return $this->add_tags( $app, $objects, $action, false );
    }

    function publish_assets ( $app, $objects, $action ) {
        $counter = 0;
        $callback = ['name' => 'post_save', 'is_new' => false ];
        foreach ( $objects as $obj ) {
            if ( $obj->status == 4 ) {
                $obj = $app->db->model( 'asset' )->load( $obj->id );
                $app->publish_obj( $obj, null, true );
                $res = $app->post_save_asset( $callback, $app, $obj );
                if ( $res ) $counter++;
            }
        }
        $return_args = "does_act=1&__mode=view&_type=list&_model=asset&"
                     ."apply_actions={$counter}" . $app->workspace_param;
        $app->redirect( $app->admin_url . '?' . $return_args );
    }

    function recompile_cache ( $app, $objects, $action ) {
        $counter = 0;
        $db = $app->db;
        $db->begin_work();
        foreach ( $objects as $obj ) {
            $counter++;
            $obj->compiled('');
            $obj->cache_key('');
            $obj->save();
        }
        if ( !empty( $db->errors ) ) {
            $errstr = $app->translate( 'An error occurred while saving %s.',
                      $app->translate( 'Model' ) );
            return $app->rollback( $errstr );
        } else {
            $db->commit();
        }
        $return_args = "does_act=1&__mode=view&_type=list&_model=template&"
                     ."apply_actions={$counter}" . $app->workspace_param;
        $app->redirect( $app->admin_url . '?' . $return_args );
    }

    function export_fieldtypes ( $app, $objects, $action ) {
        $counter = 0;
        $model = $app->param( '_model' );
        $counter = 0;
        $temp_dir = $app->upload_dir();
        $temp_dir .= DS . 'field_types';
        $tmpl_dir = $temp_dir . DS . 'tmpl' . DS;
        mkdir( $tmpl_dir, 0777, TRUE );
        $config = [];
        $files = [];
        $dirs = [ $tmpl_dir, $temp_dir, dirname( $tmpl_dir ), dirname( $tmpl_dir ) ];
        foreach ( $objects as $obj ) {
            $counter++;
            $obj = $app->db->model( 'fieldtype' )->load( $obj->id );
            $basename = $obj->basename;
            $label = "{$tmpl_dir}{$basename}_label.tmpl";
            file_put_contents( $label, $obj->label );
            $files[] = $label;
            $content = "{$tmpl_dir}{$basename}_content.tmpl";
            file_put_contents( $content, $obj->content );
            $files[] = $content;
            $config[ $basename ] = [
                'name' => $obj->name,
                'order' => (int) $obj->order ];
        }
        $config = json_encode( $config, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT );
        file_put_contents( $temp_dir . DS ."fields.json", $config );
        $files[] = "{$temp_dir}field.json";
        require_once( 'class.PTUtil.php' );
        $zip_path = rtrim( $temp_dir, '/' ) . '.zip';
        $res = PTUtil::make_zip_archive( $temp_dir, $zip_path );
        $files[] = $zip_path;
        PTUtil::export_data( $zip_path, 'application/zip' );
        foreach ( $files as $file ) {
            unlink( $file );
        }
        foreach ( $dirs as $dir ) {
            rmdir( $dir );
        }
        exit();
    }

    function add_tags_to_obj ( $obj, $add_tags, $name = 'tags', &$tag_ids ) {
        $app = Prototype::get_instance();
        if (! empty( $add_tags ) ) {
            $db = $app->db;
            $workspace_id = 0;
            if ( $obj->has_column( 'workspace_id' ) ) {
                $workspace_id = (int) $obj->workspace_id;
            }
            $to_ids = [];
            $error = false;
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
                $to_ids[] = $tag_obj->id;
                if (! in_array( $tag_obj->id, $tag_ids ) ) {
                    $tag_ids[] = $tag_obj->id;
                }
            }
            $args = ['from_id' => $obj->id, 
                     'name' => $name,
                     'from_obj' => $obj->_model,
                     'to_obj' => 'tag' ];
            $res = $app->set_relations( $args, $to_ids, true );
            return $res;
        }
        return null;
    }

    function log ( $action, $model, $count ) {
        $app = Prototype::get_instance();
        $table = $app->get_table( $model );
        $obj_label = $count == 1 ? $table->label : $table->plural;
        $obj_label = $app->translate( $obj_label );
        $message = $app->translate(
                        'The action \'%1$s\' was executed for %2$s %3$s by %4$s.',
                            [ $action, $count, $obj_label, $app->user()->nickname ] );
        $app->log( ['message'  => $message,
                    'category' => 'list_action',
                    'model'    => $model,
                    'level'    => 'info'] );
    }
}