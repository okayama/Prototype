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
            $permitted_objs = [];
            foreach ( $objects as $obj ) {
                if ( $app->can_do( $model, 'edit', $obj ) ) {
                    $permitted_objs[] = $obj;
                    // return $app->error( 'Permission denied.' );
                }
            }
            $component = $action['component'];
            $meth = $action['method'];
            $action['model'] = $model;
            if ( method_exists( $component, $meth ) ) {
                $db->caching = false;
                return $component->$meth( $app, $permitted_objs, $action );
            }
        }
        return $app->error( 'Invalid request.' );
    }

    function get_list_actions ( $model, &$list_actions = [] ) {
        $app = Prototype::get_instance();
        $table = $app->get_table( $model );
        if ( $table->has_status && !$app->param( 'manage_revision' ) ) {
            $list_actions[] = ['name' => 'set_status', 'input' => 0,
                               'label' => $app->translate( 'Set Status' ),
                               'component' => $this,
                               'method' => 'set_status'];
        }
        $obj = $app->db->model( $model );
        if ( $obj->has_column( 'state' ) ) {
            $column = $app->db->model( 'column' )->get_by_key(
                ['table_id' => $table->id, 'name' => 'state'] );
            if ( $column->id ) {
                $options = explode( ',', $column->options );
                $input_options = [];
                $i = 1;
                foreach ( $options as $option ) {
                    $input_options[] = ['label' => 
                        $app->translate( $option ), 'value' => $i ];
                    $i++;
                }
                $list_actions[] = ['name' => 'set_state', 'input' => 1,
                                   'label' => $app->translate( 'Set Status' ),
                                   'component' => $this,
                                   'input_options' => $input_options,
                                   'columns' => ['id', 'status'],
                                   'method' => 'set_state'];
            }
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
        $blob_cols = $app->db->get_blob_cols( $model );
        if ( count( $blob_cols ) ) {
            $list_actions[] = ['name' => 'publish_files', 'input' => 0,
                               'label' => $app->translate( 'Publish Files' ),
                               'component' => $this,
                               'method' => 'publish_files'];
        }
        if ( $table->name === 'template' ) {
            $list_actions[] = ['name' => 'export_theme', 'input' => 1,
                               'hint' => $app->translate( 'Input label of the Theme.' ),
                               'label' => $app->translate( 'Export Theme' ),
                               'component' => $this,
                               'columns' => ['id', 'name', 'text', 'subject', 'status',
                                             'class', 'basename', 'form_id', 'uuid'],
                               'method' => 'export_theme'];
            $list_actions[] = ['name' => 'recompile_cache', 'input' => 0,
                               'label' => $app->translate( 'Re-Compile Cache' ),
                               'component' => $this,
                               'columns' => ['id', 'text', 'compiled', 'cache_key'],
                               'method' => 'recompile_cache'];
        } else if ( $table->name === 'urlmapping' ) {
            $list_actions[] = ['name' => 'recompile_cache', 'input' => 0,
                               'label' => $app->translate( 'Re-Compile Cache' ),
                               'component' => $this,
                               'columns' => ['id', 'mapping', 'compiled', 'cache_key'],
                               'method' => 'recompile_cache'];
        } else if ( $table->name === 'fieldtype' ) {
            $list_actions[] = ['name' => 'export_fieldtypes', 'input' => 0,
                               'label' => $app->translate( 'Export' ),
                               'component' => $this,
                               'method' => 'export_fieldtypes'];
        } else if ( $table->name === 'contact' ) {
            $input_options = [];
            $input_options[] = ['label' => 'UTF-8', 'value' => 'UTF-8' ];
            $input_options[] = ['label' => 'Shift_JIS', 'value' => 'Shift_JIS' ];
            $list_actions[] = ['name' => 'export_contacts', 'input' => 1,
                               'label' => $app->translate( 'CSV Export' ),
                               'component' => $this,
                               'columns' => '*',
                               'input_options' => $input_options,
                               'method' => 'export_contacts'];
            $list_actions[] = ['name' => 'aggregate_contact', 'input' => 0,
                               'label' => $app->translate( 'Aggregate' ),
                               'component' => $this,
                               'columns' => '*',
                               // 'hint' => $app->translate( 'Please enter comma-delimited question labels to be summarized(When omitted, it counts everything).' ),
                               'input_options' => [],
                               'method' => 'aggregate_contacts'];
        } else if ( $table->name === 'question' ) {
            $list_actions[] = ['name' => 'set_aggregate_target', 'input' => 0,
                               'label' => $app->translate( 'Target for Aggregation' ),
                               'component' => $this,
                               'columns' => ['id', 'aggregate'],
                               'method' => 'set_aggregate_target'];
            $list_actions[] = ['name' => 'unset_aggregate_target', 'input' => 0,
                               'label' => $app->translate( 'Remove from Aggregation target' ),
                               'component' => $this,
                               'columns' => ['id', 'aggregate'],
                               'method' => 'set_aggregate_target'];
        }
        if ( $table->im_export ) {
            $list_actions[] = ['name' => 'export_objects', 'input' => 0,
                               'label' => $app->translate( 'Export CSV' ),
                               'component' => $this,
                               // 'columns' => '*',
                               'method' => 'export_objects'];
        }
        if ( $model == 'urlinfo' ) {
            // or if ( $obj->has_column( 'delete_flag' ) ) {
            $list_actions[] = ['name' => 'physical_delete', 'input' => 0,
                               'label' => $app->translate( 'Physical Delete' ),
                               'component' => $this,
                               'columns' => 'id,file_path',
                               'method' => 'physical_delete'];
            $list_actions[] = ['name' => 'reset_urlinfo', 'input' => 0,
                               'label' => $app->translate( 'Reset URL' ),
                               'component' => $this,
                               'columns' => 'id,url,dirname,file_path,relative_url,relative_path,workspace_id',
                               'method' => 'reset_urlinfo'];
        }
        $actions = [];
        foreach ( $list_actions as $list_action ) {
            if (! isset( $list_action['hint'] ) ) {
                $list_action['hint'] = '';
            }
            $actions[] = $list_action;
        }
        $list_actions = $actions;
        return $app->get_registries( $model, 'list_actions', $list_actions );
    }

    function set_aggregate_target ( $app, $objects, $action ) {
        $action_name = $action['name'];
        $value = $action_name == 'unset_aggregate_target' ? 0 : 1;
        $counter = 0;
        foreach ( $objects as $obj ) {
            if ( $obj->aggregate != $value ) {
                $obj->aggregate( $value );
                $obj->save();
                $counter++;
            }
        }
        $return_args = "does_act=1&__mode=view&_type=list&_model=question&"
                     . "apply_actions={$counter}" . $app->workspace_param;
        if ( $add_params = $this->add_return_params( $app ) ) {
            $return_args .= "&{$add_params}";
        }
        $app->redirect( $app->admin_url . '?' . $return_args );
    }
    
    function aggregate_contacts ( $app, $objects, $action ) {
        $aggregate_cols = [];
        $multiple_cols = [];
        $aggregates = $this->export_contacts( $app, $objects, $action,
                                              true, $aggregate_cols, $multiple_cols );
        $colors = ['#D9ECFF', '#FAF9DC', '#EFD9D9', '#D7EDCF', '#FFFFFF',
                   '#DDBBFF', '#E9E9FA', '#FFDDAA', '#EEEEEE'];
        $aggregate_vars = [];
        $aggregate_counts = [];
        $aggregate_data = [];
        $aggregate_colors = [];
        $aggregate_raw_colors = [];
        $aggregate_labels = [];
        $aggregate_percents = [];
        foreach ( $aggregate_cols as $col => $dummy ) {
            if ( isset( $aggregates[ $col ] ) ) {
                $sorted = $aggregates[ $col ];
                arsort( $sorted );
                $counter = 0;
                $data = [];
                $dataColors = [];
                $dataRawColors = [];
                $labels = [];
                $percents = [];
                $i = 0;
                $new_data = [];
                foreach ( $sorted as $key => $count ) {
                    $counter += $count;
                    $data[] = $count;
                    if (! $key ) $key = $app->translate( '(No Answer)' );
                    $esc_key = str_replace( ',', '\\,', $key );
                    $esc_key = str_replace( "'", "\\'", $key );
                    $esc_key = $app->ctx->modifier_trim_to( $esc_key, '15+...', $app->ctx );
                    $labels[] = "'{$esc_key}'";
                    if (! isset( $colors[$i] ) ) {
                        $i = 0;
                    }
                    $dataRawColors[] = $colors[$i];
                    $dataColors[] = "'" . $colors[$i] . "'";
                    $new_data[ $key ] = $count;
                    $i++;
                }
                foreach ( $sorted as $key => $count ) {
                    $percent = $count / $counter * 100;
                    $percent = number_format( $percent, 1, '.', ',' );
                    $percents[] = $percent . '%';
                }
                $aggregate_counts[] = $counter;
                $aggregate_vars[ $col ] = $new_data;
                $aggregate_data[] = $data;
                $aggregate_colors[] = $dataColors;
                $aggregate_raw_colors[] = $dataRawColors;
                $aggregate_labels[] = $labels;
                $aggregate_percents[] = $percents;
            }
        }
        // $app->param( '_type', 'edit' );
        $_REQUEST['_type'] = 'edit';
        $app->ctx->vars['can_create'] = 1;
        $app->ctx->vars['model'] = 'contact';
        $app->ctx->vars['aggregate_results'] = $aggregate_vars;
        $app->ctx->vars['aggregate_counts']  = $aggregate_counts;
        $app->ctx->vars['aggregate_data']    = $aggregate_data;
        $app->ctx->vars['aggregate_colors']  = $aggregate_colors;
        $app->ctx->vars['aggregate_labels']  = $aggregate_labels;
        $app->ctx->vars['aggregate_raw_colors'] = $aggregate_raw_colors;
        $app->ctx->vars['aggregate_total'] = count( $objects );
        $app->ctx->vars['aggregate_percents'] = $aggregate_percents;
        $app->ctx->vars['multiple_cols'] = array_keys( $multiple_cols );
        $app->build_page('aggregate_contacts.tmpl');
    }

    function export_contacts ( $app, $objects, $action, $aggregate = null,
            &$aggregate_cols = [], &$multiple_cols = [] ) {
        $encoding = $app->param( 'itemset_action_input' );
        $plural = 'contacts';
        $ts = date( 'Y-m-d_H-i-s' );
        $upload_dir = $app->upload_dir();
        $csv_out = $upload_dir . DS . "{$plural}-{$ts}.csv";
        $add_cols = ['created_on'];
        $col_names = [];
        $has_attachments = false;
        $has_tags = false;
        foreach( $objects as $obj ) {
            $data = json_decode( $obj->data, true );
            $question_map = json_decode( $obj->question_map, true );
            foreach ( $data as $key => $value ) {
                $id = preg_replace( '/^question_/', '', $key );
                $q = $app->db->model( 'question' )->load( (int) $id );
                $label = '';
                if ( $q ) {
                    $label = $q->label;
                } else {
                    $label = $question_map[ $key ];
                }
                $col_names[ $label ] = true;
            }
            if (! $has_attachments ) {
                $attachmentfiles = $app->get_relations( $obj, 'attachmentfile' );
                if ( count( $attachmentfiles ) ) {
                    $has_attachments = true;
                }
            }
            if (! $has_tags ) {
                $tags = $app->get_relations( $obj, 'tag' );
                if ( count( $tags ) ) {
                    $has_tags = true;
                }
            }
        }
        $col_names = array_keys( $col_names );
        $label_names = $col_names;
        // array_unshift( $col_names, $app->translate( 'Email' ) );
        // array_unshift( $col_names, $app->translate( 'Subject' ) );
        array_unshift( $col_names, $app->translate( 'Form' ) );
        array_unshift( $col_names, 'contact_id' );
        if ( $has_tags ) {
            $col_names[] = $app->translate( 'Tags' );
        }
        foreach ( $add_cols as $add_col ) {
            $add_label = $app->translate( $app->translate( $add_col, '', $app, 'default' ) );
            $col_names[] = $add_label;
        }
        $fp = $aggregate ? null : fopen( $csv_out,'w' );
        if (! $aggregate && $encoding == 'Shift_JIS' ) {
            stream_filter_append( $fp, 'convert.iconv.UTF-8/CP932', STREAM_FILTER_WRITE );
        }
        $error_lines = [];
        if (! $aggregate ) fputcsv( $fp, $col_names, ',', '"' );
        $aggregate_values = [];
        foreach( $objects as $obj ) {
            $data = json_decode( $obj->data, true );
            $question_map = json_decode( $obj->question_map, true );
            $values = [];
            $connectors = [];
            foreach ( $data as $key => $value ) {
                $id = preg_replace( '/^question_/', '', $key );
                $q = $app->db->model( 'question' )->load( (int) $id );
                $label = '';
                if ( $q ) {
                    $label = $q->label;
                    if ( $q->connector ) {
                        $connectors[ $label ] = $q->connector;
                    }
                    if ( $q->aggregate ) {
                        $aggregate_cols[ $label ] = true;
                    }
                    if ( $q->multiple ) {
                        $multiple_cols[ $label ] = true;
                    }
                } else {
                    $label = $question_map[ $key ];
                }
                if ( isset( $values[ $label ] ) ) {
                    if ( is_array( $values[ $label ] ) ) {
                        $values[ $label ][] = $value;
                    } else {
                        $values[ $label ] = [ $values[ $label ], $value ];
                    }
                } else {
                    $values[ $label ] = $value;
                }
            }
            $csv_values = [];
            $csv_values[] = $obj->id;
            $form = $obj->form;
            $form_name = $form ? $form->name : $app->translate( '*Deleted*' );
            $csv_values[] = $form_name;
            // $csv_values[] = $obj->subject;
            // $csv_values[] = $obj->email;
            foreach ( $label_names as $label ) {
                if (! isset( $values[ $label ] ) ) {
                    $values[ $label ] = '';
                }
                $value = $values[ $label ];
                if (! isset( $aggregate_values[ $label ] ) ) {
                    $aggregate_values[ $label ] = [];
                }
                if ( is_array( $value ) ) {
                    foreach ( $value as $v ) {
                        if ( isset( $aggregate_values[ $label ][ $v ] ) ) {
                            $aggregate_values[ $label ][ $v ]
                                = $aggregate_values[ $label ][ $v ] + 1;
                        } else {
                            $aggregate_values[ $label ][ $v ] = 1;
                        }
                    }
                    $conn = isset( $connectors[ $label ] ) ? $connectors[ $label ] : ', ';
                    $value = implode( ', ', $value );
                } else {
                    if ( isset( $aggregate_values[ $label ][ $value ] ) ) {
                        $aggregate_values[ $label ][ $value ]
                            = $aggregate_values[ $label ][ $value ] + 1;
                    } else {
                        $aggregate_values[ $label ][ $value ] = 1;
                    }
                }
                $csv_values[] = $value;
            }
            if ( $has_tags ) {
                $tags = $app->get_related_objs( $obj, 'tag', 'tags' );
                $tag_values = [];
                if (! empty( $tags ) ) {
                    foreach ( $tags as $tag ) {
                        $tag_values[] = $tag->name;
                    }
                }
                $csv_values[] = implode( ', ', $tag_values );
            }
            if ( $has_attachments && ! $aggregate ) {
                $files = $app->get_related_objs( $obj, 'attachmentfile', 'attachmentfiles' );
                if (! empty( $files ) ) {
                    $files_dir = $upload_dir . DS . $obj->id;
                    foreach ( $files as $file ) {
                        if ( $file->file ) {
                            $path = $files_dir . DS . $file->name;
                            if ( file_exists( $path ) ) {
                                $path = PTUtil::unique_filename( $path );
                            }
                            file_put_contents( $path, $file->file );
                        }
                    }
                }
            }
            foreach ( $add_cols as $add_col ) {
                $csv_values[] = $obj->$add_col;
            }
            if (! $aggregate ) {
                $res = fputcsv( $fp, $csv_values, ',', '"' );
                if (! $res ) {
                    $has_data = false;
                    foreach ( $csv_values as $v ) {
                        if ( $v != '' ) {
                            $has_data = true;
                            break;
                        }
                    }
                    if ( $has_data ) {
                        $tempLine = '"';
                        $tempLine .= implode( '","', $csv_values );
                        $tempLine = preg_replace("/\r\n|\r|\n/", "", $tempLine );
                        $tempLine .= '"';
                        $error_lines[] = $tempLine;
                    }
                }
            }
        }
        if ( $aggregate ) {
            return $aggregate_values;
        }
        fclose( $fp );
        if (! empty( $error_lines ) ) {
            if ( $encoding == 'Shift_JIS' ) {
                mb_convert_variables( 'sjis-win', 'utf-8', $error_lines );
            }
            $append = implode( "\n", array_values( $error_lines ) );
            file_put_contents( $csv_out, $append, FILE_APPEND );
        }
        $zip = $upload_dir . DS . "{$plural}-{$ts}.zip";
        PTUtil::make_zip_archive( $upload_dir, $zip );
        // PTUtil::make_zip_archive( $upload_dir, $zip, "{$plural}-{$ts}" );
        PTUtil::export_data( $zip );
        // PTUtil::remove_dir( $upload_dir );
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
        $upload_dir = $app->upload_dir();
        $csv_out = $upload_dir . DS . "{$plural}-{$ts}.csv";
        $fp = fopen( $csv_out,'w' );
        if ( $encoding == 'Shift_JIS' ) {
            stream_filter_append( $fp, 'convert.iconv.UTF-8/CP932', STREAM_FILTER_WRITE );
        }
        $excludes = ['rev_type', 'rev_object_id', 'rev_changed', 'rev_diff',
                     'created_on', 'modified_on', 'previous_owner',
                     'created_by', 'modified_by', 'compiled'];
        $column_defs = $scheme['column_defs'];
        $edit_properties = $scheme['edit_properties'];
        if ( $app->export_without_bin ) {
            foreach ( $column_defs as $colName => $column_def ) {
                if ( $column_def['type'] == 'blob' ) {
                    $excludes[] = $colName;
                }
            }
        }
        $load_cols = [];
        foreach ( $column_defs as $colName => $column_def ) {
            if (! in_array( $colName, $excludes ) ) {
                if ( $column_def['type'] != 'relation' ) {
                    $load_cols[] = $colName;
                }
            }
        }
        $load_cols = implode( ',', $load_cols );
        $i = 0;
        $error_lines = [];
        $attachment_cols = PTUtil::attachment_cols( $model, $scheme );
        $app->db->caching = false;
        foreach ( $objects as $obj ) {
            $obj = $obj->load( (int) $obj->id, [], $load_cols );
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
                if ( isset( $edit_properties[ $key ] ) ) {
                    if ( $column_defs[ $key ]['type'] == 'int' ) {
                        $edit_prop = $edit_properties[ $key ];
                        if ( strpos( $edit_prop, 'relation:' ) === 0 ) {
                            if ( preg_match( "/:hierarchy$/", $edit_prop ) ) {
                                $edit_prop = explode( ':', $edit_prop );
                                $refModel = $edit_prop[1];
                                $refCol = $edit_prop[2];
                                if ( $refModel . '_id' == $key ) {
                                    $parents = [];
                                    $refObj = $obj->$refModel;
                                    $parent = $refObj;
                                    if ( $parent ) {
                                        if ( $parent->has_column( 'parent_id' ) ) {
                                            while ( $parent !== null ) {
                                                if ( $parent_id = $parent->parent_id ) {
                                                    $parent_id = (int) $parent_id;
                                                    $parent = $app->db->model( $refModel )->load( $parent_id );
                                                    if ( $parent->id ) {
                                                        array_unshift( $parents, $parent->$refCol );
                                                    } else {
                                                        $parent = null;
                                                    }
                                                } else {
                                                    $parent = null;
                                                }
                                            }
                                        }
                                        $parents[] = $refObj->$refCol;
                                    }
                                    $value = implode( '/', $parents );
                                }
                            }
                        }
                    }
                }
                if ( $column_defs[ $key ]['type'] == 'blob' && $value ) {
                    $value = $obj->$key;
                    // $value = ''; // base64_encode( $value );
                    $urlinfo = $app->db->model( 'urlinfo' )->get_by_key(
                        ['model' => $obj->_model, 'object_id' => $obj->id,
                         'key' => $key, 'class' => 'file' ] );
                    if (! $urlinfo->id ) {
                        $app->publish_obj( $obj );
                        $app->db->clear_cache( $obj->_model );
                        $urlinfo = $app->db->model( 'urlinfo' )->get_by_key(
                            ['model' => $obj->_model, 'object_id' => $obj->id,
                             'key' => $key, 'class' => 'file' ] );
                    }
                    $relative_path = $urlinfo->relative_path;
                    $outpath = preg_replace( "/^%r/", $upload_dir, $relative_path );
                    if (! $app->export_without_bin ) {
                        $app->fmgr->mkpath( dirname( $outpath ) );
                        file_put_contents( $outpath, $value );
                    }
                    $mata = $app->db->model( 'meta' )->get_by_key(
                        ['kind' => 'metadata', 'object_id' => $obj->id, 'model' => $obj->_model ] );
                    $metadata = $mata->text;
                    if ( $metadata && preg_match( '/^{.*}$/s', $metadata ) ) {
                        $metadata = json_decode( $mata->text, true );
                        if ( is_array( $metadata ) && isset( $metadata['label'] ) ) {
                            $label = $metadata['label'];
                            $relative_path .= ';' . $label;
                        }
                    }
                    $value = $relative_path;
                } else if ( $column_defs[ $key ]['type'] == 'datetime' ) {
                    $value = $obj->db2ts( $value );
                    if ( $value ) {
                        $value = date( DATE_ATOM, strtotime( $value ) );
                        list( $value, $tz ) = explode( '+', $value );
                    }
                } else if ( in_array( $key, $attachment_cols ) ) {
                    if (! $value ) {
                        $value = '';
                    } else {
                        $rel_obj = $app->db->model( 'attachmentfile' )->load( (int) $value );
                        $value = '';
                        if ( $rel_obj ) {
                            if ( $rel_obj->file ) {
                                $relative_path = $app->get_assetproperty(
                                        $rel_obj, 'file', 'relative_path' );
                                if ( $relative_path ) {
                                    $outpath = preg_replace(
                                        "/^%r/", $upload_dir, $relative_path );
                                    if (! $app->export_without_bin ) {
                                        $app->fmgr->mkpath( dirname( $outpath ) );
                                        file_put_contents( $outpath, $rel_obj->file );
                                    }
                                    // $relative_path = $rel_obj->workspace_id ?
                                    //     "%w/{$relative_path}" : "%s/{$relative_path}";
                                    $mata = $app->db->model( 'meta' )->get_by_key(
                                        ['kind' => 'metadata', 'object_id' => $rel_obj->id, 'model' => $rel_obj->_model ] );
                                    $metadata = $mata->text;
                                    if ( $metadata && preg_match( '/^{.*}$/s', $metadata ) ) {
                                        $metadata = json_decode( $mata->text, true );
                                        if ( is_array( $metadata ) && isset( $metadata['label'] ) ) {
                                            $label = $metadata['label'];
                                            $relative_path .= ';' . $label;
                                        }
                                    }
                                    $value = $relative_path;
                                }
                            }
                        }
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
                            if ( $rel_obj->_model == 'asset' || $rel_obj->_model == 'attachmentfile' ) {
                                if ( $rel_obj->file ) {
                                    $relative_path = $app->get_assetproperty(
                                            $rel_obj, 'file', 'relative_path' );
                                    if ( $rel_obj->_model == 'asset'
                                        && $relative_path === null ) {
                                        $cb = ['name' => 'post_save', 'is_new' => false ];
                                        $app->post_save_asset( $cb, $app, $rel_obj );
                                        $app->db->clear_cache( $rel_obj->_model );
                                        $relative_path = $app->get_assetproperty(
                                                $rel_obj, 'file', 'relative_path' );
                                    }
                                    if ( $relative_path ) {
                                        $outpath = preg_replace(
                                            "/^%r/", $upload_dir, $relative_path );
                                        if (! $app->export_without_bin ) {
                                            $app->fmgr->mkpath( dirname( $outpath ) );
                                            file_put_contents( $outpath, $rel_obj->file );
                                        }
                                        // $relative_path = $rel_obj->workspace_id ?
                                        //     "%w/{$relative_path}" : "%s/{$relative_path}";
                                        if ( $rel_obj->_model != 'attachmentfile' ) {
                                            $mata = $app->db->model( 'meta' )->get_by_key(
                                                ['kind' => 'metadata', 'object_id' => $rel_obj->id, 'model' => $rel_obj->_model ] );
                                            $metadata = $mata->text;
                                            if ( $metadata && preg_match( '/^{.*}$/s', $metadata ) ) {
                                                $metadata = json_decode( $mata->text, true );
                                                if ( is_array( $metadata ) && isset( $metadata['label'] ) ) {
                                                    $label = $metadata['label'];
                                                    $relative_path .= ';' . $label;
                                                }
                                            }
                                        } else {
                                            $relative_path .= ';' . $rel_obj->name;
                                        }
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
            $res = fputcsv( $fp, $column_values, ',', '"' );
            if (! $res ) {
                $has_data = false;
                foreach ( $column_values as $v ) {
                    if ( $v != '' ) {
                        $has_data = true;
                        break;
                    }
                }
                if ( $has_data ) {
                    $tempLine = '"';
                    $tempLine .= implode( '","', $column_values );
                    $tempLine = preg_replace("/\r\n|\r|\n/", "", $tempLine );
                    $tempLine .= '"';
                    $error_lines[] = $tempLine;
                }
            }
            $i++;
        }
        fclose( $fp );
        if (! empty( $error_lines ) ) {
            if ( $encoding == 'Shift_JIS' ) {
                mb_convert_variables( 'sjis-win', 'utf-8', $error_lines );
            }
            $append = implode( "\n", array_values( $error_lines ) );
            file_put_contents( $csv_out, $append, FILE_APPEND );
        }
        $zip = $upload_dir . DS . "{$plural}-{$ts}.zip";
        PTUtil::make_zip_archive( $upload_dir, $zip );
        // PTUtil::make_zip_archive( $upload_dir, $zip, "{$plural}-{$ts}" );
        PTUtil::export_data( $zip );
        // PTUtil::remove_dir( $upload_dir );
    }

    function set_state ( $app, $objects, $action ) {
        $state = (int) $app->param( 'itemset_action_input' );
        $model = $app->param( '_model' );
        $obj = $app->db->model( $model );
        if (! $obj->has_column( 'state' ) ) {
            return $app->error( 'Invalid request.' );
        }
        $table = $app->get_table( $model );
        $column = $app->db->model( 'column' )->get_by_key(
            ['table_id' => $table->id, 'name' => 'state'] );
        $status_text = '';
        if ( $column->id ) {
            $options = explode( ',', $column->options );
            if (! isset( $options[ $state - 1 ] ) ) {
                return $app->error( 'Invalid request.' );
            }
            $status_text = $app->translate( $options[ $state - 1 ] );
        }
        $db = $app->db;
        $db->begin_work();
        $app->txn_active = true;
        $counter = 0;
        foreach ( $objects as $obj ) {
            if ( $obj->state != $state ) {
                $obj->state( $state );
                $obj->save();
                $counter++;
            }
        }
        if ( $counter ) {
            $action = $action['label'] . " ({$status_text})";
            $this->log( $action, $model, $counter );
            $db->commit();
            $app->txn_active = false;
        }
        $return_args = "does_act=1&__mode=view&_type=list&_model={$model}&"
                     . "apply_actions={$counter}" . $app->workspace_param;
        if ( $add_params = $this->add_return_params( $app ) ) {
            $return_args .= "&{$add_params}";
        }
        $app->redirect( $app->admin_url . '?' . $return_args );
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
        if ( $status > $max_status ) {
            return $app->error( 'Invalid request.' );
        }
        $counter = 0;
        $rebuild_ids = [];
        $publish_objs = [];
        $publish_originals = [];
        $error = false;
        $db = $app->db;
        $db->begin_work();
        $app->txn_active = true;
        $workflow_map = [];
        $ws_user_map = [];
        $user_map = [];
        $app->core_save_callbacks();
        foreach ( $objects as $obj ) {
            if (! $obj->has_column( 'status' ) ) {
                return $app->error( 'Invalid request.' );
            }
            $original = clone $obj;
            if ( $obj->status != $status ) {
                if ( $obj->status == $status_published || $status == $status_published ) {
                    if ( $app->get_permalink( $obj, true ) ) {
                        if ( $obj->has_column( 'workspace_id' )
                            && $obj->workspace_id != $app->param( 'workspace_id' ) ) {
                            $publish_objs[] = $obj;
                            $publish_originals[] = $original;
                        } else {
                            $rebuild_ids[] = $obj->id;
                        }
                    }
                }
                $original = clone $obj;
                $orig_status = $obj->status;
                $obj->status( $status );
                if (! $obj->save() ) $error = true;
                $original->status( $orig_status );
                $workspace_id = $obj->workspace_id ? $obj->workspace_id : 0;
                $workflow = isset( $workflow_map[ $workspace_id ] )
                          ? $workflow_map[ $workspace_id ]
                          : $app->db->model( 'workflow' )->get_by_key(
                                                ['model' => $model,
                                                 'workspace_id' => $workspace_id ] );
                $workflow_map[ $workspace_id ] = $workflow;
                $callback = ['name' => 'post_save', 'error' => '', 'is_new' => false ];
                if ( $workflow->id ) {
                    $class = new PTWorkflow();
                    $class->workflow_post_save( $callback, $app, $obj, $original );
                    if ( isset( $callback['change_case'] ) ) {
                        $old_user = $callback['old_user'];
                        if (! isset( $ws_user_map[ $workspace_id ][ $old_user->id ] ) ) {
                            $ws_user_map[ $workspace_id ][ $old_user->id ] = [];
                        }
                        $user_map[ $old_user->id ] = $old_user;
                        $ws_user_map[ $workspace_id ][ $old_user->id ][] = $obj;
                    }
                }
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
            $app->txn_active = false;
        }
        if (! empty( $ws_user_map ) ) {
            $ctx = $app->ctx;
            $object_label_plural = $app->translate( $table->plural );
            $object_label = $app->translate( $table->label );
            $ctx->vars['object_label_plural'] = $object_label_plural;
            $by_user = $app->user->nickname;
            $ctx->vars['by_user'] = $by_user;
            $tags = new PTTags();
            $tag_args = ['status' => $status, 'model' => $model ];
            $status_text = $tags->hdlr_statustext( $tag_args, $app->ctx );
            $ctx->vars['status_text'] = $status_text;
            foreach ( $ws_user_map as $ws_id => $workflow_objects ) {
                $workflow = $workflow_map[ $ws_id ];
                if (! $workflow || ! $workflow->notify_changes ) {
                    continue;
                }
                $list_url = $app->admin_url . '?__mode=view&_type=list&_model=' . $model;
                if ( $ws_id ) {
                    $list_url .= '&workspace_id=' . $ws_id;
                }
                $workspace = $ws_id
                           ? $app->db->model( 'workspace' )->load( (int) $ws_id )
                           : null;
                $app->set_mail_param( $ctx, $workspace );
                $template = null;
                $tmpl = $app->get_mail_tmpl( 'batch_status_change', $template, $workspace );
                $ctx->stash( 'workspace', $workspace );
                foreach ( $workflow_objects as $user_id => $changed_objs ) {
                    $old_user = $user_map[ $user_id ];
                    $ctx->vars['object_label']
                        = count( $changed_objs ) == 1 ? $object_label
                        : $object_label_plural;
                    $object_label = $ctx->vars['object_label'];
                    $count = count( $changed_objs );
                    $ctx->vars['count_objects'] = $count;
                    $primary = $table->primary;
                    $params = [];
                    foreach ( $changed_objs as $changed_obj ) {
                        $_params = $changed_obj->get_values();
                        $_params['object_name'] = $changed_obj->$primary;
                        $_params['object_id'] = $changed_obj->id;
                        $_params['object_permalink'] = $app->get_permalink( $changed_obj );
                        $params[] = $_params;
                        $edit_link = $app->admin_url . '?__mode=view&_type=edit&_model=';
                        $edit_link .= $model . '&id=' . $changed_obj->id;
                        if ( $changed_obj->workspace_id ) {
                            $edit_link .= '&workspace_id=' . $changed_obj->workspace_id;
                        }
                        $_params['edit_link'] = $edit_link;
                    }
                    $ctx->vars['object_loop'] = $params;
                    $ctx->vars['list_url'] = $list_url;
                    $body = $app->build( $tmpl );
                    $subject = '';
                    if (! $template || ! $template->subject ) {
                        $subject = $app->translate(
                        'The status of the %1$s %2$s you are in charge has been '
                         . 'changed to %3$s by user %4$s and the responsible user has been changed.',
                        [ $count, $object_label, $status_text, $by_user ] );
                    } else {
                        $subject = $app->build( $template->subject );
                    }
                    $headers = ['From' => $app->user()->email ];
                    $error = '';
                    PTUtil::send_mail(
                        $old_user->email, $subject, $body, $headers, $error );
                }
            }
        }
        if ( $counter ) {
            $column = $app->db->model( 'column' )->get_by_key(
                      ['table_id' => $table->id, 'name' => 'status'] );
            $options = $column->options;
            $status_text = $status;
            if ( $options ) {
                $options = explode( ',', $options );
                $status_text = $app->translate( $options[ $status ] );
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
        if (! empty( $publish_objs ) ) {
            $i = 0;
            foreach ( $publish_objs as $publish_obj ) {
                $original = $publish_originals[ $i ];
                $app->publish_obj( $publish_obj, $original, true );
                $i++;
            }
        }
        if ( $add_params = $this->add_return_params( $app ) ) {
            $return_args .= "&{$add_params}";
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
            $return_args =
                "__mode=view&_type=list&_model={$model}&apply_actions={$counter}"
                    . $app->workspace_param;
            if ( $add_params = $this->add_return_params( $app ) ) {
                $return_args .= "&{$add_params}";
            }
            $app->redirect( $app->admin_url . '?' . $return_args );
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
                $terms = ['normalize' => $normalize, 'class' => $model ];
                $tags = $app->db->model( 'tag' )->load( $terms );
                if (! empty( $tags ) ) {
                    $tag_objs = array_merge( $tag_objs, $tags );
                }
            }
            if ( empty( $tag_objs ) ) {
                $return_args =
                    "__mode=view&_type=list&_model={$model}&apply_actions={$counter}"
                    . $app->workspace_param;
                if ( $add_params = $this->add_return_params( $app ) ) {
                    $return_args .= "&{$add_params}";
                }
                $app->redirect( $app->admin_url . '?' . $return_args );
            }
            foreach ( $tag_objs as $tag ) {
                $tag_ids[] = $tag->id;
            }
        }
        $rebuild_ids = [];
        $rebuild_tag_ids = [];
        $db = $app->db;
        $db->begin_work();
        $app->txn_active = true;
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
            $app->txn_active = false;
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
        if ( $add_params = $this->add_return_params( $app ) ) {
            $return_args .= "&{$add_params}";
        }
        $app->redirect( $app->admin_url . '?' . $return_args );
    }

    function remove_tags ( $app, $objects, $action ) {
        return $this->add_tags( $app, $objects, $action, false );
    }

    function publish_files ( $app, $objects, $action ) {
        $counter = 0;
        $callback = ['name' => 'post_save', 'is_new' => false ];
        $model = $action['model'];
        $object = $app->db->model( $model )->new();
        $column_defs = $object->_scheme['column_defs'];
        $status_published = isset( $column_defs['status'] )
                          ? $app->status_published( $model ) : null;
        foreach ( $objects as $obj ) {
            /*
            if ( $status_published && $status_published != $obj->status ) {
                continue;
            }
            */
            $obj = $app->db->model( $model )->load( $obj->id );
            $app->publish_obj( $obj, null, false, true );
            if ( $model == 'asset' ) {
                $res = $app->post_save_asset( $callback, $app, $obj );
                if ( $res ) $counter++;
            } else {
                $counter++;
            }
        }
        $return_args = "does_act=1&__mode=view&_type=list&_model={$model}&"
                     ."apply_actions={$counter}" . $app->workspace_param;
        if ( $add_params = $this->add_return_params( $app ) ) {
            $return_args .= "&{$add_params}";
        }
        $app->redirect( $app->admin_url . '?' . $return_args );
    }

    function physical_delete ( $app, $objects, $action ) {
        $model = $action['model']; // urlinfo
        $db = $app->db;
        $counter = count( $objects );
        if ( $counter ) {
            $db->begin_work();
            $app->txn_active = true;
            $fmgr = $app->fmgr;
            foreach ( $objects as $obj ) {
                if ( $obj->file_path && $fmgr->exists( $obj->file_path ) ) {
                    $fmgr->unlink( $obj->file_path );
                }
            }
            $obj = $db->model( $model );
            $obj->remove_multi( $objects );
            if ( !empty( $db->errors ) ) {
                $table = $app->get_table( $model );
                $object_label = $counter == 1 ? $table->label : $table->plural;
                $errstr = $app->translate( 'An error occurred while deleting %s.',
                          $app->translate( $object_label ) );
                return $app->rollback( $errstr );
            } else {
                $db->commit();
                $app->txn_active = false;
            }
        }
        $return_args = "does_act=1&__mode=view&_type=list&_model={$model}&"
                     ."apply_actions={$counter}" . $app->workspace_param;
        if ( $add_params = $this->add_return_params( $app ) ) {
            $return_args .= "&{$add_params}";
        }
        $app->redirect( $app->admin_url . '?' . $return_args );
    }

    function reset_urlinfo ( $app, $objects, $action = null ) {
        if ( $max_execution_time = $app->max_exec_time ) {
            $max_execution_time = (int) $max_execution_time;
            ini_set( 'max_execution_time', $max_execution_time );
        }
        $site_url = $app->get_config( 'site_url' );
        $site_path = $app->get_config( 'site_path' );
        $workspaces = [];
        $update_objs = [];
        $move_files = [];
        $fmgr = $app->fmgr;
        foreach ( $objects as $obj ) {
            $workspace = null;
            if ( $workspace_id = $obj->workspace_id ) {
                $workspace = isset( $workspaces[ $obj->workspace_id ] )
                           ? $workspaces[ $obj->workspace_id ] : $obj->workspace;
                $workspaces[ $obj->workspace_id ] = $workspace;
            }
            $url = $workspace ? $workspace->site_url : $site_url->value;
            if ( mb_substr( $url, -1 ) == '/' ) {
                $url = rtrim( $url, '/' );
            }
            $path = $workspace ? $workspace->site_path : $site_path->value;
            if ( mb_substr( $path, -1 ) == '/' ) {
                $url = rtrim( $path, '/' );
            }
            $relative_path = $obj->relative_path;
            $newURL = preg_replace( '/^%r/', $url, $relative_path );
            $file_path = $obj->file_path;
            $old_path = $file_path;
            $newPath = preg_replace( '/^%r/', $url, $file_path );
            if ( $obj->url == $newURL && $obj->file_path == $newPath ) {
                continue;
            }
            if ( $obj->file_path != $newPath && $fmgr->exists( $old_path ) ) {
                $move_files[ $old_path ] = $newPath;
            }
            $newDirname = ( dirname( $newURL ) . '/' );
            $newRelativeURL = preg_replace( '!^https{0,1}:\/\/.*?\/!', '/', $newURL );
            $obj->url( $newURL );
            $obj->dirname( $newDirname );
            $obj->relative_url( $newRelativeURL );
            $obj->file_path( $newPath );
            $update_objs[] = $obj;
        }
        if (! empty( $update_objs ) ) {
            if (! $app->db->model( 'urlinfo' )->update_multi( $update_objs ) ) {
                return $app->rollback( 'An error occurred while updating the URLs.' );
            }
            if (! empty( $move_files ) ) {
                foreach ( $move_files as $old => $new ) {
                    $fmgr->rename( $old, "{$old}.bk" );
                    if ( $fmgr->rename( $old, $new ) ) {
                        if ( $fmgr->exists( "{$old}.bk" ) ) {
                            $fmgr->unlink( "{$old}.bk" );
                        }
                    }
                }
            }
        }
        $counter = count( $update_objs );
        if (! $action ) {
            return $counter;
        }
        $return_args = "does_act=1&__mode=view&_type=list&_model=urlinfo&"
                     ."apply_actions={$counter}" . $app->workspace_param;
        if ( $add_params = $this->add_return_params( $app ) ) {
            $return_args .= "&{$add_params}";
        }
        $app->redirect( $app->admin_url . '?' . $return_args );
    }

    function export_theme ( $app, $objects, $action ) {
        $counter = 0;
        $excludes = ['_insert', '_meta', '_relations', '_original', 'id',
                     'created_by', 'workspace_id', 'form_id'];
        $map_cols = ['name', 'model', 'mapping', 'date_based', 'fiscal_start',
                     'publish_file', 'container', 'container_scope', 'trigger_scope'];
        $form_cols= ['name', 'requires_token', 'token_expires', 'redirect_url', 'basename',
                     'send_email', 'email_from', 'send_thanks', 'send_notify',
                     'notify_to'];
        $que_cols = ['label', 'description', 'questiontype_id', 'hint', 'required',
                     'is_primary', 'validation_type', 'normarize', 'format',
                     'maxlength', 'multi_byte', 'hide_in_email', 'aggregate', 'rows',
                     'count_fields', 'multiple', 'connector', 'options', 'unit', 'values',
                     'default_value', 'placeholder', 'basename'];
        $theme = [];
        $templates = [];
        $theme_label = $app->param( 'itemset_action_input' );
        $theme_id = null;
        if ( $theme_label ) {
            $theme_id = strtolower( preg_replace( '/[^A-Za-z0-9]/', '_', $theme_label ) );
            if ( $theme_id ) {
                $theme['label'] = $theme_label;
                $theme['id'] = $theme_id;
            } else {
                $theme['label'] = 'Your Theme';
                $theme['id'] = 'your_theme';
            }
        }
        $theme['version'] = '';
        $theme['author'] = '';
        $theme['author_link'] = '';
        $theme['author_link'] = '';
        $theme['description'] = '';
        $theme['objects'] = [];
        $theme['component'] = '';
        $upload_dir = $app->upload_dir();
        $temp_dir = $theme_id ? $upload_dir . DS . $theme_id
                              : $upload_dir . DS . 'your_theme';
        $template_dir = $temp_dir . DS . 'views';
        $questions_dir = $temp_dir . DS . 'questions';
        mkdir( $template_dir , 0777, true );
        foreach ( $objects as $obj ) {
            if (! $obj->uuid ) continue;
            $values = $obj->get_values( true );
            $text = $values['text'];
            unset( $values['text'] );
            foreach ( $excludes as $prop ) {
                unset( $values[ $prop ] );
            }
            file_put_contents( $template_dir . DS . $obj->uuid . '.tmpl', $text );
            if ( $obj->form_id ) {
                $form = $app->db->model( 'form' )->load( (int) $obj->form_id );
                if ( $form ) {
                    $tmpl_form = [];
                    $form_values = $form->get_values( true );
                    foreach ( $form_cols as $form_col ) {
                        $tmpl_form[ $form_col ] = $form->$form_col;
                    }
                    if ( $form->thanks_template ) {
                        $mail = $app->db->model( 'template' )->load(
                          (int) $form->thanks_template );
                        if ( $mail ) {
                            $tmpl_form['thanks_template'] = $mail->uuid;
                        }
                    }
                    if ( $form->notify_template ) {
                        $mail = $app->db->model( 'template' )->load(
                          (int) $form->notify_template );
                        if ( $mail ) {
                            $tmpl_form['notify_template'] = $mail->uuid;
                        }
                    }
                    $questions = $app->get_related_objs( $form, 'question', 'questions' );
                    $question_ids = [];
                    foreach ( $questions as $question ) {
                        if (! $question->uuid ) continue;
                        if (! is_dir( $questions_dir ) ) {
                            mkdir( $questions_dir, 0777, true );
                        }
                        file_put_contents( $questions_dir . DS . $question->uuid . '.tmpl',
                            $question->template );
                        $question_values = [];
                        foreach ( $que_cols as $que_col ) {
                            $question_values[ $que_col ] = $question->$que_col;
                        }
                        if ( $questiontype_id = $question->questiontype_id ) {
                            $qt = $app->db->model( 'questiontype' )->load( (int) $questiontype_id );
                            if ( $qt ) {
                                $question_values['questiontype_id'] = $qt->basename;
                            }
                        }
                        $question_ids[ $question->uuid ] = $question_values;
                    }
                    if ( count( $question_ids ) ) {
                        $tmpl_form['questions'] = $question_ids;
                    }
                    $values['form'] = $tmpl_form;
                }
            }
            $maps = $app->db->model( 'urlmapping' )->load( ['template_id' => $obj->id ] );
            $mappings = [];
            foreach ( $maps as $map ) {
                $mapping = [];
                $map_values = $map->get_values( true );
                foreach ( $map_cols as $map_col ) {
                    $mapping[ $map_col ] = $map_values[ $map_col ];
                }
                $tables = $app->get_related_objs( $map, 'table', 'triggers' );
                $triggers = [];
                foreach ( $tables as $trigger ) {
                    $triggers[] = $trigger->name;
                }
                if ( count( $triggers ) ) {
                    $mapping['triggers'] = $triggers;
                }
                $mappings[] = $mapping;
            }
            if ( count( $mappings ) ) {
                $values['urlmappings'] = $mappings;
            }
            unset( $values['uuid'] );
            $templates[ $obj->uuid ] = $values;
        }
        $theme['views'] = $templates;
        $theme = json_encode( $theme, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT );
        file_put_contents( $temp_dir . DS . 'theme.json', $theme );
        $zip = $upload_dir . DS . "theme.zip";
        PTUtil::make_zip_archive( $upload_dir, $zip );
        PTUtil::export_data( $zip );
    }

    function recompile_cache ( $app, $objects, $action ) {
        $counter = 0;
        $db = $app->db;
        $db->begin_work();
        $app->txn_active = true;
        foreach ( $objects as $obj ) {
            $counter++;
            $obj->compiled('');
            $obj->cache_key('');
            $obj->save();
        }
        if ( !empty( $db->errors ) ) {
            $errstr = $app->translate( 'An error occurred while saving %s.',
                      $app->translate( 'View' ) );
            return $app->rollback( $errstr );
        } else {
            $db->commit();
            $app->txn_active = false;
        }
        $return_args = "does_act=1&__mode=view&_type=list&_model=" . $action['model']
                     ."&apply_actions={$counter}" . $app->workspace_param;
        if ( $add_params = $this->add_return_params( $app ) ) {
            $return_args .= "&{$add_params}";
        }
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
            $hide_label = $obj->hide_label ? true : false;
            $config[ $basename ] = [
                'name' => $obj->name,
                'order' => (int) $obj->order,
                'hide_label' => $hide_label ];
        }
        $config = json_encode( $config, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT );
        file_put_contents( $temp_dir . DS ."fields.json", $config );
        $files[] = "{$temp_dir}field.json";
        require_once( 'class.PTUtil.php' );
        $zip_path = rtrim( $temp_dir, '/' ) . '.zip';
        $res = PTUtil::make_zip_archive( $temp_dir, $zip_path );
        $files[] = $zip_path;
        PTUtil::export_data( $zip_path, 'application/zip' );
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
                $terms = ['normalize' => $normalize, 'class' => $obj->_model ];
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

    function add_return_params ( $app, $param = '_query_string' ) {
        $query = urldecode( $app->param( $param ) );
        parse_str( $query, $return_params );
        $excludes = ['__mode', '_type', '_model'];
        foreach ( $excludes as $exclude ) {
            if ( isset( $return_params[ $exclude ] ) ) {
                unset( $return_params[ $exclude ] );
            }
        }
        if ( empty( $return_params ) ) {
            return '';
        }
        return http_build_query( $return_params );
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