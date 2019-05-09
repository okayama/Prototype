<?php

function ptimporter_auto_loader($class) {
    $path = $class;
    $path = str_replace( '\\', DS, $path ) . '.php';
    if ( file_exists( LIB_DIR . $path ) ) {
        include_once( LIB_DIR . $path );
    }
}
spl_autoload_register( '\ptimporter_auto_loader' );

class PTImporter {

    public  $print_state = true;
    private $allowed = ['csv', 'zip'];
    public  $apply_theme = false;
    public  $skipped_objects = [];
    public  $theme_skipped = 0;
    
    function import_objects ( $app ) {
        if (! $app->can_do( 'import_objects' ) ) {
            return $app->error( 'Permission denied.' );
        }
        $ctx = $app->ctx;
        $ctx->vars['page_title'] = $app->translate( 'Import Objects' );
        // $do_import = $app->param( 'do_import' );
        $do_import = $app->param( '_type' );
        $session_id = $app->param( 'magic' );
        if ( $session_id ) {
            $session = $app->db->model( 'session' )->get_by_key( ['name' => $session_id ] );
            if (! $session->id || $session->user_id != $app->user()->id ) {
                return $app->return_to_dashboard();
            }
        }
        if ( $do_import == 'do_import' ) {
            $app->db->caching = false;
            $app->validate_magic();
            if (! $session_id ) {
                return $app->error( 'Invalid request.' );
            }
            if ( $max_execution_time = $app->max_exec_time ) {
                $max_execution_time = (int) $max_execution_time;
                ini_set( 'max_execution_time', $max_execution_time );
            }
            $denied_exts = $app->denied_exts;
            $denied_exts = preg_split( '/\s*,\s*/', $denied_exts );
            $import_file = $session->value;
            if (! file_exists( $import_file ) ) {
                return $app->error( 'Invalid request.', true );
            }
            $model = $app->param( 'model' );
            if (! $app->can_do( $model, 'create' ) ) {
                return $app->error( 'Permission denied.', true );
            }
            $app->init_callbacks( $model, 'post_import' );
            $meta = json_decode( $session->text, true );
            $extension = $meta['extension'];
            $dirname = dirname( $import_file );
            require_once( 'class.PTUtil.php' );
            if ( strtolower( $extension ) == 'zip' ) {
                $zip = new ZipArchive();
                $res = $zip->open( $import_file );
                if ( $res === true ) {
                    $zip->extractTo( dirname( $import_file ) );
                    $zip->close();
                    // unlink( $import_file );
                    $list = PTUtil::file_find( dirname( $import_file ), $files, $dirs );
                    if ( $files == 0 ) {
                        $error = $app->translate( 'Could not expand ZIP archive.' );
                        return $app->error( $error );
                    }
                    $import_file = $files;
                } else {
                    $error = $app->translate( 'Could not expand ZIP archive.' );
                    return $app->error( $error );
                }
            } else {
                $import_file = [ $import_file ];
            }
            $csv_exists = false;
            foreach ( $import_file as $file ) {
                $extension = pathinfo( $file, PATHINFO_EXTENSION );
                if ( strtolower( $extension ) == 'csv' && $dirname == dirname( $file ) ) {
                    $csv_exists = true;
                }
            }
            $ds = preg_quote( DS, '/' );
            if (! $csv_exists && count( $dirs ) > 1 ) {
                $dirname = $dirs[1];
                $dirname = preg_replace( "/{$ds}\.$/", '', $dirname );
            }
            $this->import_from_files( $app, $model, $dirname, $import_file, $session );
            $session->remove();
            PTUtil::remove_dir( dirname( $session->value ) );
            echo "<script>window.parent.importDone = true;</script>";
            exit();
        }
        return $app->build_page( 'import_objects.tmpl' );
    }

    function import_from_files ( $app, $model, $dirname, $import_file, $session = null ) {
        $table = $app->get_table( $model );
        $plural = $table->plural;
        $primary = $table->primary;
        $primary_col = "{$model}_{$primary}";
        $scheme = $app->get_scheme_from_db( $model );
        $column_defs = $scheme['column_defs'];
        $edit_properties = $scheme['edit_properties'];
        $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];
        $images = $app->images;
        $videos = $app->videos;
        $audios = $app->audios;
        list( $insert, $update, $skip, $errors ) = [0, 0, 0, 0];
        $log_info = [];
        $attachment_cols = PTUtil::attachment_cols( $model, $scheme );
        $status_published = null;
        if ( $app->db->model( $model )->has_column( 'status' ) ) {
            $status_published = $app->status_published( $model );
        }
        $app->db->begin_work();
        $header_out = false;
        $imported_objects = [];
        foreach ( $import_file as $file ) {
            $extension = pathinfo( $file, PATHINFO_EXTENSION );
            if ( strtolower( $extension ) == 'csv' && $dirname == dirname( $file ) ) {
                $csv = $file;
                if (! $this->apply_theme ) {
                    $content = file_get_contents( $csv );
                    $encoding = mb_detect_encoding( $content );
                    if ( $encoding != 'UTF-8' ) {
                        $content = mb_convert_encoding( $content, 'UTF-8', 'Shift_JIS' );
                    }
                    $content = preg_replace( "/\r\n|\r|\n/", PHP_EOL, $content );
                    file_put_contents( $csv, $content );
                }
                // $fh = fopen( $csv, 'r' );
                $i = 0;
                $columns = [];
                $csv_handle = new SplFileObject( $csv ); 
                $csv_handle->setFlags( SplFileObject::READ_CSV ); 
                // while( $data = fgetcsv( $fh ) ) {
                foreach ( $csv_handle as $data ) {
                    if ( $data[0] == null ) {
                        continue;
                    }
                    $attachment_files = [];
                    $has_blob = false;
                    if (! $i ) {
                        $columns = $data;
                        foreach ( $columns as $column ) {
                            if ( strpos( $column, $model ) === false ) {
                                if ( $session ) {
                                    $session->remove();
                                    PTUtil::remove_dir( dirname( $session->value ) );
                                }
                                $error = $app->translate( 'The CSV format are Invalid. Please confirm the file.' );
                                return $app->error( $error, true );
                                exit();
                            }
                        }
                        if (! $header_out && $this->print_state ) {
                            echo '<html><body style="font-family: sans-serif">';
                        }
                        $header_out = true;
                    } else {
                        $values = [];
                        $id = null;
                        $cnt = 0;
                        foreach ( $columns as $column ) {
                            if ( $column == "{$model}_id" ) {
                                $id = (int) $data[ $cnt ];
                            } else if ( $column == "{$model}_workspace_id" ) {
                                $workspace_id = (int) $data[ $cnt ];
                                if ( $app->workspace() && $workspace_id != $app->workspace()->id ) {
                                    $workspace_id = (int) $app->workspace()->id;
                                } else if (! $app->workspace() ) {
                                    $obj_workspace = $app->db->model( 'workspace' )->load( $workspace_id );
                                    if (! $obj_workspace ) {
                                        $workspace_id = 0;
                                    }
                                }
                                $values[ $column ] = $workspace_id;
                            } else {
                                $values[ $column ] = $data[ $cnt ];
                            }
                            $cnt++;
                        }
                        if ( $this->apply_theme ) {
                            if ( isset( $values[ $primary_col ] ) ) {
                                $obj_primary = $values[ $primary_col ];
                                $existing_terms = [ $primary => $obj_primary ];
                                $workspace_id = $app->workspace() ? $app->workspace()->id : 0;
                                if ( $app->db->model( $model )->has_column( 'workspace_id' ) ) {
                                    $existing_terms['workspace_id'] = (int)$workspace_id;
                                }
                                if ( $table->revisable ) {
                                    $existing_terms['rev_type'] = 0;
                                }
                                $existing = $app->db->model( $model )->get_by_key( $existing_terms );
                                if ( $existing->id ) {
                                    $imported_objects[] = $existing;
                                    $this->skipped_objects[ $model ][] = $existing;
                                    $this->theme_skipped++;
                                    $msg = $app->translate(
                                      "The %s '%s' has been skipped because it already existed." ,
                                      [ $app->translate( $table->label ), $obj_primary ] );
                                    $app->log( ['message'  => $msg,
                                                'category' => 'import',
                                                'model'    => $model,
                                                'level'    => 'info'] );
                                    continue;
                                }
                            }
                        }
                        $obj = null;
                        $update_rels = [];
                        $exists = false;
                        $original = null;
                        $orig_relations = null;
                        $orig_metadata = null;
                        $orig_attachments = null;
                        $remove_files = [];
                        $revision_files = [];
                        if ( $id ) {
                            $obj = $app->db->model( $model )->load( $id );
                            if ( $obj ) {
                                if (! $app->can_do( $model, 'edit', $obj ) ) {
                                    $skip++;
                                    continue;
                                }
                                $exists = true;
                                $orig_relations = $app->get_relations( $obj );
                                $orig_metadata  = $app->get_meta( $obj );
                                $original = clone $obj;
                                $original->id( null );
                                $original->_relations = $orig_relations;
                                $original->_meta = $orig_metadata;
                                $orig_attachments = $app->get_related_objs(
                                    $obj, 'attachmentfile' );
                            }
                        }
                        $is_new = $obj ? true : false;
                        if (! $obj ) {
                            $obj = $app->db->model( $model )->new();
                            if ( $id ) {
                                $obj->id = $id;
                                $obj->save();
                            }
                        }
                        foreach ( $values as $col => $value ) {
                            $col = preg_replace( "/^{$model}_/", '', $col );
                            if ( $obj->has_column( $col ) &&
                                ! isset( $relations[ $col ] ) ) {
                                $type = $column_defs[ $col ]['type'];
                                if ( $type == 'blob' ) {
                                    $blob_col = $app->db->model( 'column' )->get_by_key(
                                        ['table_id' => $table->id, 'name' => $col ] );
                                    $extra = $blob_col->extra;
                                    // $type = $blob_col->options;
                                    if ( strpos( $value, '%r' ) === 0 ) {
                                        list( $value, $label ) = preg_split( '/\;/', $value, 2 );
                                        $value = str_replace( '%r', $dirname, $value );
                                        $value = preg_replace( "/\//", DS, $value );
                                        if ( file_exists( $value ) ) {
                                            $error = '';
                                            $res = PTUtil::upload_check( $extra, $value, false, $error );
                                            if ( $this->print_state &&$res == 'resized' ) {
                                                echo $app->translate(
                                                "The image (%s) was larger than the size limit, so it was reduced.",
                                                htmlspecialchars( basename( $value ) ) ), '<br>';
                                            } else if ( $this->print_state && $error ) {
                                                echo $error, '<br>';
                                            }
                                            if (! $error ) {
                                                if (! $obj->id ) {
                                                    $obj->save();
                                                }
                                                $label = $label ? $label : basename( $value );
                                                PTUtil::file_attach_to_obj(
                                                    $app, $obj, $col, $value, $label );
                                                $has_blob = true;
                                            }
                                        }
                                    }
                                } else if ( $type == 'datetime' ) {
                                    $value = $obj->db2ts( $value );
                                    $value = $obj->ts2db( $value );
                                    $obj->$col( $value );
                                } else if ( $type == 'int' ) {
                                    if ( in_array( $col, $attachment_cols ) ) {
                                        $blob_col = $app->db->model( 'column' )->get_by_key(
                                            ['table_id' => $table->id, 'name' => $col ] );
                                        $extra = $blob_col->extra;
                                        // $type = $blob_col->options;
                                        if ( strpos( $value, '%r' ) === 0 ) {
                                            list( $value, $label ) = preg_split( '/\;/', $value, 2 );
                                            $value = str_replace( '%r', $dirname, $value );
                                            $value = preg_replace( "/\//", DS, $value );
                                            if ( file_exists( $value ) ) {
                                                $error = '';
                                                $res = PTUtil::upload_check( $extra, $value, false, $error );
                                                if ( $this->print_state && $res == 'resized' ) {
                                                    echo $app->translate(
                                                    "The image (%s) was larger than the size limit, so it was reduced.",
                                                    htmlspecialchars( basename( $value ) ) ), '<br>';
                                                } else if ( $this->print_state && $error ) {
                                                    echo $error, '<br>';
                                                }
                                                if (! $error ) {
                                                    $upload_info = PTUtil::get_upload_info( $app, $value, $error );
                                                    if (! $error ) {
                                                        if ( isset( $upload_info['thumbnail_small'] ) ) {
                                                            $thumbnail_small = $upload_info['thumbnail_small'];
                                                            if ( file_exists( $thumbnail_small ) ) {
                                                                PTUtil::remove_dir( dirname( $thumbnail_small ) );
                                                            }
                                                        }
                                                        $upload_info = $upload_info['metadata'];
                                                        $_is_new = false;
                                                        if ( $obj->id && $obj->$col ) {
                                                            $attachmentfile = $app->db->model( 'attachmentfile' )->get_by_key( ['id' => $obj->$col ] );
                                                            if ( $original && $table->revisable ) {
                                                                $clone_file = PTUtil::clone_object( $app, $attachmentfile );
                                                                $original->$col( $clone_file->id );
                                                                $revision_files[] = $clone_file;
                                                            }
                                                        } else {
                                                            $_is_new = true;
                                                            $attachmentfile = $app->db->model( 'attachmentfile' )->new();
                                                        }
                                                        $attachmentfile->name( $upload_info['file_name'] );
                                                        $attachmentfile->mime_type( $upload_info['mime_type'] );
                                                        $attachmentfile->class( $upload_info['class'] );
                                                        $attachmentfile->size( $upload_info['file_size'] );
                                                        $app->set_default( $attachmentfile );
                                                        $attachmentfile->save();
                                                        $label = $label ? $label : basename( $value );
                                                        PTUtil::file_attach_to_obj( $app, $attachmentfile, 'file', $value, $label );
                                                        $callback = ['name' => 'post_import'];
                                                        $app->run_callbacks( $callback, 'attachmentfile', $attachmentfile );
                                                        // $app->publish_obj( $attachmentfile );
                                                        $obj->$col( $attachmentfile->id );
                                                        $attachment_files[] = $attachmentfile;
                                                        $log_info[] =
                                                            $_is_new
                                                            ? $app->translate( 'Create %s (%s / ID : %s).',
                                                            [ $app->translate( 'Attachment File' ),
                                                              $attachmentfile->name,
                                                              $attachmentfile->id ] )
                                                            : $app->translate( 'Update %s (%s / ID : %s).',
                                                            [ $app->translate( 'Attachment File' ),
                                                              $attachmentfile->name,
                                                              $attachmentfile->id ] );
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        if ( isset( $edit_properties[ $col ] ) ) {
                                            $edit_prop = $edit_properties[ $col ];
                                            if ( strpos( $edit_prop, 'relation:' ) === 0 ) {
                                                if ( preg_match( "/:hierarchy$/", $edit_prop ) ) {
                                                    $edit_prop = explode( ':', $edit_prop );
                                                    if ( $value && ! ctype_digit( $value ) ) {
                                                        $refModel = $edit_prop[1];
                                                        $ref_model = $app->db->model( $refModel );
                                                        $refCol = $edit_prop[2];
                                                        $terms = [];
                                                        if ( $ref_model->has_column( 'workspace_id' ) ) {
                                                            $workspace_id = $obj->workspace_id;
                                                            if (! $workspace_id && $app->workspace() ) {
                                                                $workspace_id = $app->workspace()->id;
                                                            }
                                                            $workspace_id = (int) $workspace_id;
                                                            $terms = ['workspace_id' => $workspace_id];
                                                        }
                                                        if ( $ref_model->has_column( 'rev_type' ) ) {
                                                            $terms = ['rev_type' => 0];
                                                        }
                                                        $paths = explode( '/', $value );
                                                        $parent_id = 0;
                                                        foreach ( $paths as $path ) {
                                                            $terms['parent_id'] = $parent_id;
                                                            $terms[ $refCol ] = $path;
                                                            $refObj = $ref_model->get_by_key( $terms );
                                                            if (! $refObj->id ) {
                                                                $app->set_default( $refObj );
                                                                if ( $refObj->_model == 'folder' ) {
                                                                    $refObj->basename( PTUtil::make_basename( $refObj, $refObj->label ) );
                                                                }
                                                                $refObj->save();
                                                            }
                                                            $parent_id = $refObj->id;
                                                        }
                                                        $value = $parent_id;
                                                    }
                                                }
                                            }
                                        }
                                        $value = (int) $value;
                                        $obj->$col( $value );
                                    }
                                } else {
                                    $obj->$col( $value );
                                }
                            }
                        }
                        if (! $obj->id ) $obj->id( null );
                        $app->set_default( $obj );
                        $callback = ['name' => 'pre_import', 'values' => $values ];
                        $app->run_callbacks( $callback, $obj->_model, $obj, $original );
                        $obj->save();
                        if (! $is_new ) {
                            $insert++;
                        } else {
                            $update++;
                        }
                        $line = $obj->$primary;
                        if ( $this->print_state ) echo $app->translate( "Saving %s ( %s )...",
                                    [ $app->translate( $table->label ),
                                      htmlspecialchars( $line ) ] ), '<br>';
                        $log_info[] = $is_new
                                    ? $app->translate( 'Create %s (%s / ID : %s).',
                                        [ $app->translate( $table->label ),
                                          $app->translate( $line ),
                                          $obj->id ] )
                                    : $app->translate( 'Update %s (%s / ID : %s).',
                                        [ $app->translate( $table->label ),
                                          $app->translate( $line ),
                                          $obj->id ] );
                        if (! empty( $relations ) ) {
                            foreach ( $relations as $key => $to_obj ) {
                                $col_name = "{$model}_{$key}";
                                if ( isset( $values[ $col_name ] ) ) {
                                    $csv = $values[ $col_name ] ?
                                         str_getcsv( $values[ $col_name ], ',', '"' ) : [];
                                    if ( $to_obj == '__any__' ) {
                                        $to_obj = array_shift( $csv );
                                    }
                                    $to_table = $app->get_table( $to_obj );
                                    $to_model = $app->db->model( $to_obj );
                                    if (! $to_table ) continue;
                                    $to_primary = $to_table->primary;
                                    $to_ids = [];
                                    if ( $to_obj != 'asset' && $to_obj != 'attachmentfile' ) {
                                        foreach ( $csv as $name ) {
                                            $rel_terms = [ $to_primary => $name ];
                                            if ( $obj->workspace_id && $to_model->has_column( 'workspace_id' ) ) {
                                                $rel_terms['workspace_id'] = $obj->workspace_id;
                                            }
                                            $rel = $app->db->model( $to_obj )->get_by_key( $rel_terms );
                                            if ( $rel->id ) {
                                                $to_ids[] = (int) $rel->id;
                                            } else {
                                                if ( $to_obj == 'category' && $obj->_model == 'entry' ) {
                                                    if ( $app->can_do( 'category', 'create', null, $workspace ) ) {
                                                        $rel->basename( PTUtil::make_basename( $rel, $rel->label ) );
                                                        $app->set_default( $rel );
                                                        $rel->save();
                                                        $log_info[] =
                                                            $app->translate( 'Create %s (%s / ID : %s).',
                                                            [ $app->translate( 'Category' ),
                                                              $app->translate( $name ),
                                                              $rel->id ] );
                                                        $to_ids[] = (int) $rel->id;
                                                    }
                                              } else if ( $to_obj == 'tag' )
                                              {
                                                $workspace = $obj->workspace ? $obj->workspace : null;
                                                if ( $app->can_do( 'tag', 'create', null, $workspace ) ) {
                                                    $normalize = str_replace( ' ', '',
                                                        trim( mb_strtolower( $name ) ) );
                                                    $terms = ['normalize' => $normalize ];
                                                    if ( $workspace )
                                                        $terms['workspace_id'] = $workspace->id;
                                                    $tag_obj =
                                                        $app->db->model( 'tag' )->get_by_key( $terms );
                                                    if ( $tag_obj->id ) {
                                                        $to_ids[] = (int) $tag_obj->id;
                                                    } else {
                                                        $tag_obj->name( $name );
                                                        $app->set_default( $tag_obj );
                                                        $order = $app->get_order( $tag_obj );
                                                        $tag_obj->order( $order );
                                                        $tag_obj->save();
                                                        $to_ids[] = (int) $tag_obj->id;
                                                        $log_info[] =
                                                            $app->translate( 'Create %s (%s / ID : %s).',
                                                            [ $app->translate( 'Tag' ),
                                                              $app->translate( $name ),
                                                              $tag_obj->id ] );
                                                    }
                                                } else {
                                                    $skip++;
                                                }
                                              }
                                            }
                                        }
                                    } else {
                                        $ws_id = isset( $values["{$model}_workspace_id"] )
                                                      ? (int)$values["{$model}_workspace_id"] : 0;
                                        if (! $ws_id && $obj->workspace_id ) {
                                            $ws_id = (int) $obj->workspace_id;
                                        }
                                        $extra = '';
                                        if ( $to_obj == 'asset' ) {
                                            $asset_table = $app->get_table( 'asset' );
                                            $rel_column = $app->db->model( 'column' )->get_by_key(
                                                ['table_id' => $asset_table->id, 'name' => 'file' ] );
                                            $extra = $rel_column->extra;
                                        } else {
                                            $rel_column = $app->db->model( 'column' )->get_by_key(
                                                ['table_id' => $table->id, 'name' => $key ] );
                                            $extra = $rel_column->extra;
                                        }
                                        foreach ( $csv as $path ) {
                                            $error = '';
                                            $realpath = '';
                                            $label = '';
                                            if ( strpos( $path, '%r' ) === 0 ) {
                                                list( $path, $label ) = preg_split( '/\;/', $path, 2 );
                                                $realpath = str_replace( '%r', $dirname, $path );
                                                $realpath = preg_replace( "/\//", DS, $realpath );
                                            }
                                            if ( $realpath && file_exists( $realpath ) ) {
                                                $res = PTUtil::upload_check( $extra, $realpath, false, $error );
                                                if ( $this->print_state && $res == 'resized' ) {
                                                    echo $app->translate(
                                                    "The image (%s) was larger than the size limit, so it was reduced.",
                                                    htmlspecialchars( basename( $path ) ) ), '<br>';
                                                } else if ( $this->print_state && $error ) {
                                                    echo $error, '<br>';
                                                    continue;
                                                }
                                            }
                                            $url_terms = ['workspace_id' => $ws_id,
                                                          'relative_path' => $path,
                                                          'model' => $to_obj ];
                                            $asset_obj = null;
                                            if ( $to_obj != 'attachmentfile' ) {
                                                $url_obj =
                                                    $app->db->model( 'urlinfo' )->get_by_key( $url_terms );
                                                if ( $url_obj->id ) {
                                                    $asset_id = (int) $url_obj->object_id;
                                                    $asset_obj = $app->db->model( $to_obj )->load( $asset_id );
                                                }
                                            } else {
                                                $basename = basename( $path );
                                                if ( $orig_attachments ) {
                                                    foreach ( $orig_attachments as $orig_rel ) {
                                                        if ( $orig_rel->name == $basename ) {
                                                            $asset_obj = $orig_rel;
                                                            if ( $original && $table->revisable ) {
                                                                $remove_files[] = $asset_obj;
                                                                $asset_obj = PTUtil::clone_object( $app, $asset_obj );
                                                            }
                                                            break;
                                                        }
                                                    }
                                                }
                                            }
                                            $orig_path = $path;
                                            $path = str_replace( '%r', $dirname, $path );
                                            $_update = false;
                                            $orig_asset_obj = null;
                                            $_is_new = false;
                                            if ( $asset_obj ) {
                                                if ( $to_obj == 'attachmentfile' ||
                                                     $app->can_do( $to_obj, 'edit', $asset_obj ) ) {
                                                    $to_ids[] = (int) $asset_obj->id;
                                                    if ( file_exists( $path ) ) {
                                                        $asset_data = file_get_contents( $path );
                                                        $comp_new = base64_encode( $asset_data );
                                                        $comp_old = base64_encode( $asset_obj->file );
                                                        if ( $comp_new != $comp_old ) {
                                                            $asset_obj->file( $asset_data );
                                                            $_update = true;
                                                        }
                                                    }
                                                } else {
                                                    $skip++;
                                                }
                                            } else {
                                                if ( file_exists( $path ) ) {
                                                    // Create new asset
                                                    $workspace = $ws_id ? $app->db->model( 'workspace' )->load( $ws_id )
                                                               : null;
                                                    if ( $to_obj == 'attachmentfile'
                                                        || $app->can_do( $to_obj, 'create', null, $workspace ) ) {
                                                        $asset_obj = $app->db->model( $to_obj )->new(
                                                           ['workspace_id' => $ws_id ] );
                                                        $app->set_default( $asset_obj );
                                                        $asset_obj->$to_primary( basename( $path ) );
                                                        $label = $label ? $label : basename( $path );
                                                        if ( $asset_obj->has_column( 'name' ) ) {
                                                            $asset_obj->name( $label );
                                                        } else if ( $asset_obj->has_column( 'label' ) ) {
                                                            $asset_obj->label( $label );
                                                        }
                                                        $asset_obj->save();
                                                        PTUtil::file_attach_to_obj(
                                                            $app, $asset_obj, 'file', $path, $label );
                                                        $to_ids[] = (int) $asset_obj->id;
                                                        $_update = true;
                                                        $_is_new = true;
                                                    } else {
                                                        $skip++;
                                                    }
                                                }
                                            }
                                            if ( $_update && $asset_obj ) {
                                                // Update meta objs
                                                $image_info = null;
                                                $ext = PTUtil::get_extension( $orig_path );
                                                if ( in_array( $ext, $images ) ) {
                                                    $image_info = getimagesize( $path );
                                                }
                                                $this->update_asset(
                                                    $app, $asset_obj, $path, $orig_path, $image_info );
                                                $app->set_default( $asset_obj );
                                                $asset_obj->save();
                                                $callback = ['name' => 'post_import'];
                                                $app->run_callbacks( $callback, $to_obj, $asset_obj );
                                                $app->publish_obj( $asset_obj, null, false, true );
                                                // $app->post_save_asset( $callback, $app, $asset_obj );
                                                $log_label = $to_obj == 'asset' ? 'Asset' : 'Attachment File'; 
                                                $log_info[] =
                                                    $_is_new
                                                    ? $app->translate( 'Create %s (%s / ID : %s).',
                                                    [ $app->translate( $log_label ),
                                                      $asset_obj->$to_primary,
                                                      $asset_obj->id ] )
                                                    : $app->translate( 'Update %s (%s / ID : %s).',
                                                    [ $app->translate( $log_label ),
                                                      $asset_obj->$to_primary,
                                                      $asset_obj->id ] );
                                            }
                                            if ( $to_obj == 'attachmentfile' ) {
                                                $attachment_files[] = $asset_obj;
                                            }
                                        }
                                    }
                                    $args = ['from_id' => $obj->id, 
                                             'name' => $key,
                                             'from_obj' => $model,
                                             'to_obj' => $to_obj ];
                                    if ( $res = $app->set_relations( $args, $to_ids, false, $errors ) ) {
                                        $update_rels[ $key ] = true;
                                    }
                                }
                            }
                        }
                        $imported_objects[] = $obj;
                        // $app->publish_obj( $obj, $original );
                        if ( $table->revisable && $exists ) {
                            if ( PTUtil::pack_revision( $obj, $original, $update_rels ) ) {
                                if (! empty( $remove_files ) ) {
                                    foreach ( $remove_files as $remove_file ) {
                                        $remove_file->remove();
                                    }
                                }
                            } else {
                                if (! empty( $revision_files ) ) {
                                    foreach ( $revision_files as $remove_file ) {
                                        $remove_file->remove();
                                    }
                                }
                            }
                        }
                        if (! empty( $attachment_files ) ) {
                            $status = $obj->has_column( 'status' ) ? $obj->status : 0;
                            foreach ( $attachment_files as $attachment ) {
                                if ( $attachment->status != $status ) {
                                    $attachment->status( $status );
                                    $attachment->save();
                                }
                                $app->publish_obj( $attachment );
                            }
                        }
                        if ( $status_published && $obj->status != $status_published ) {
                            $app->publish_obj( $obj, $original );
                        } else if ( $has_blob ) {
                            $app->publish_obj( $obj, $original, false, true );
                        }
                        $callback = ['name' => 'post_import', 'values' => $values ];
                        $app->run_callbacks( $callback, $obj->_model, $obj, $original );
                    }
                    $i++;
                }
                // fclose( $fh );
            }
        }
        $app->db->commit();
        $msg = '';
        $plural = $app->translate( $plural );
        if ( $insert || $update ) {
            $msg = $app->translate(
                'Import %s successfully( Insert : %s, Update : %s ).',
                    [ $plural, $insert, $update ] );
        } else {
            if (! $skip && ! $this->apply_theme ) {
                $msg = $app->translate(
                    'Could not import %s. Please check upload file format.', $plural );
            }
        }
        if ( $skip ) {
            $msg .= $app->translate(
                '%s object(s) were skipped because you have not permission.', $skip );
        }
        if ( $this->print_state ) echo '<hr>', $msg;
        if ( $msg ) {
            $app->log( ['message'  => $msg,
                        'category' => 'import',
                        'model'    => $model,
                        'metadata' => json_encode( $log_info, JSON_UNESCAPED_UNICODE ),
                        'level'    => 'info'] );
        }
        return $imported_objects;
    }

    function update_asset ( $app, $obj, $file, $path, $image_info = null ) {
        $path_relative = preg_replace( "/^%r\//", "", $path );
        $extra_path = dirname( $path_relative ) . '/';
        $file_ext = PTUtil::get_extension( $path_relative );
        $mime_type = PTUtil::get_mime_type( $file_ext );
        $class = PTUtil::get_asset_class( $file_ext );
        $image_width = null;
        $image_height = null;
        if ( $image_info ) {
            if ( isset( $image_info['mime'] ) ) {
                $mime_type = $image_info['mime'];
            }
            $image_width  = $image_info[0];
            $image_height = $image_info[1];
        }
        if (! $obj->label ) {
            $label = basename( $path_relative );
            $obj->label( $label );
        }
        $file_name = basename( $path_relative );
        $basename = pathinfo( $path_relative, PATHINFO_FILENAME );
        $size = strlen( bin2hex( $obj->file ) ) / 2;
        $obj->set_values(
            ['extra_path' => $extra_path,
             'class' => $class, 'size' => $size, 'file_name' => $file_name,
             'file_ext' => $file_ext, 'mime_type' => $mime_type ] );
        if ( $image_width ) {
            $obj->image_width( $image_width );
        }
        if ( $image_height ) {
            $obj->image_height( $image_height );
        }
        $meta = $app->db->model( 'meta' )->get_by_key( ['model' => $obj->_model,
             'object_id' => $obj->id, 'kind' => 'metadata', 'key' => 'file'] );
        $metadata = ['file_size' => $size, 'image_width' => $image_width,
                     'image_height' => $image_height, 'class' => $class,
                     'extension' => $file_ext, 'basename' => $basename,
                     'mime_type' => $mime_type, 'file_name' => $file_name ];
        $metadata['uploaded'] = date( 'Y-m-d H:i:s' );
        $metadata['user_id'] = $app->user()->id;
        if ( $class == 'image' ) {
            $app->logging = false;
            $error_reporting = ini_get( 'error_reporting' ); 
            error_reporting(0);
            $upload_dir = $app->upload_dir();
            $imagine = new \Imagine\Gd\Imagine();
            $image = $imagine->open( $file );
            $width = 128;
            $height = 128;
            $mode = Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
            $thumbnail = $image->thumbnail( new Imagine\Image\Box( $width, $height ), $mode );
            $thumbnail_square = $upload_dir . DS . "thumb-square.{$file_ext}";
            $thumbnail->save( $thumbnail_square );
            if ( $image_width > $image_height ) {
                $width = 256;
                $scale = $width / $image_width;
                $height = round( $image_height * $scale );
            } else {
                $height = 256;
                $scale = $height / $image_height;
                $width = round( $image_width * $scale );
            }
            $image = $imagine->open( $file );
            $thumbnail = $image->thumbnail( new Imagine\Image\Box( $width, $height ) );
            $thumbnail_small = $upload_dir . DS . "thumb-small.{$file_ext}";
            $thumbnail->save( $thumbnail_small );
            $meta->metadata( file_get_contents( $thumbnail_square ) );
            $meta->data( file_get_contents( $thumbnail_small ) );
            error_reporting( $error_reporting );
            $app->logging = $logging;
        }
        if (! $meta->id ) {
            $meta->text( json_encode( $metadata ) );
            $meta->save();
        }
    }

    function pause ( $app = null) {
        if (! $app ) $app = Prototype::get_instance();
        echo "<script>window.parent.importDone = true;</script>";
        if ( $app->txn_active ) {
            $app->db->rollback();
            $app->txn_active = false;
        }
        exit();
    }

    function upload_objects ( $app ) {
        $app->db->caching = false;
        $app->validate_magic( true );
        if (! $app->can_do( 'import_objects' ) ) {
            $error = $app->translate( 'Permission denied.' );
            header( 'Content-type: application/json' );
            echo json_encode( ['message'=> $error ] );
            exit();
        }
        $magic = $app->magic();
        $upload_dir = $app->upload_dir( false );
        $options = ['upload_dir' => $upload_dir . DS, 'prototype' => $app,
                    'magic' => $magic, 'user_id' => $app->user()->id ];
        $name = $_FILES['files']['name'];
        $extension = strtolower( pathinfo( $name )['extension'] );
        if (! in_array( $extension, $this->allowed ) ) {
            $error = $app->translate( 'Invalid File extension\'%s\'.', $extension );
            header( 'Content-type: application/json' );
            echo json_encode( ['message'=> $error ] );
            exit();
        }
        $upload_handler = new UploadHandler( $options );
    }

}