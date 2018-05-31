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

    private $allowed = ['csv', 'zip'];

    function import_objects ( $app ) {
        // TODO Use FileMgr
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
            $app->validate_magic();
            if (! $session_id ) {
                return $app->error( 'Invalid request.' );
            }
            $import_file = $session->value;
            if (! file_exists( $import_file ) ) {
                return $app->error( 'Invalid request.' );
            }
            $model = $app->param( 'model' );
            if (! $app->can_do( $model, 'create' ) ) {
                return $app->error( 'Permission denied.' );
            }
            $table = $app->get_table( $model );
            $plural = $table->plural;
            $primary = $table->primary;
            $scheme = $app->get_scheme_from_db( $model );
            $column_defs = $scheme['column_defs'];
            $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];
            $meta = json_decode( $session->text, true );
            $extension = $meta['extension'];
            $dirname = dirname( $import_file );
            require_once( 'class.PTUtil.php' );
            echo '<html><body style="font-family: sans-serif">';
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
            $images = $app->images;
            $videos = $app->videos;
            $audios = $app->audios;
            list( $insert, $update, $skip ) = [0, 0, 0];
            $log_info = [];
            foreach ( $import_file as $file ) {
                $extension = pathinfo( $file, PATHINFO_EXTENSION );
                if ( strtolower( $extension ) == 'csv' && $dirname == dirname( $file ) ) {
                    $csv = $file;
                    $content = file_get_contents( $csv );
                    $encoding = mb_detect_encoding( $content );
                    if ( $encoding != 'UTF-8' ) {
                        $content = mb_convert_encoding( $content, 'UTF-8', 'Shift_JIS' );
                    }
                    $content = preg_replace( "/\r\n|\r|\n/", PHP_EOL, $content );
                    file_put_contents( $csv, $content );
                    if ( strtoupper( substr( PHP_OS, 0, 3 ) ) !== 'WIN' ) {
                        $mime = shell_exec( 'file -bi ' . escapeshellcmd( $csv ) );
                        $mime = trim( $mime );
                        $mime = preg_replace( "/(.*?)\/.*/s", "$1", $mime );
                        if ( $mime != 'text' ) {
                            continue;
                        }
                    }
                    $fh = fopen( $csv, 'r' );
                    $i = 0;
                    $columns = [];
                    while( $data = fgetcsv( $fh ) ) {
                        if (! $i ) {
                            $columns = $data;
                        } else {
                            $values = [];
                            $id = null;
                            $cnt = 0;
                            foreach ( $columns as $column ) {
                                if ( $column == "{$model}_id" ) {
                                    $id = (int) $data[ $cnt ];
                                } else {
                                    $values[ $column ] = $data[ $cnt ];
                                }
                                $cnt++;
                            }
                            $obj = null;
                            $update_rels = [];
                            $exists = false;
                            $orig_relations = null;
                            $orig_metadata = null;
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
                                }
                            }
                            if (! $obj ) {
                                $obj = $app->db->model( $model )->new();
                            }
                            $original = clone $obj;
                            $original->__relations = $orig_relations;
                            $original->__meta = $orig_metadata;
                            // Permission
                            $meta = [];
                            foreach ( $values as $col => $value ) {
                                $col = preg_replace( "/^{$model}_/", '', $col );
                                if ( $obj->has_column( $col ) &&
                                    ! isset( $relations[ $col ] ) ) {
                                    $type = $column_defs[ $col ]['type'];
                                    if ( $type == 'blob' ) {
                                        if ( strpos( $value, '%r' ) === 0 ) {
                                            $value = str_replace( '%r', $dirname, $value );
                                            $value = preg_replace( "/\//", DS, $value );
                                            $ext = strtolower( pathinfo( $value, PATHINFO_EXTENSION ) );
                                            // TODO extension check && type check
                                            if ( file_exists( $value ) ) {
                                                $data = [];
                                                $pathdata = pathinfo( $value );
                                                $mime_type = PTUtil::get_mime_type( $ext );
                                                $metadata = [
                                                    'file_size' => filesize( $value ),
                                                    'mime_type' => $mime_type,
                                                    'extension' => $ext,
                                                    'basename'  => $pathdata['filename'],
                                                    'file_name' => $pathdata['basename'] ];
                                                if ( in_array( $ext, $images ) ) {
                                                    $info = getimagesize( $value );
                                                    $w = $info[0];
                                                    $h = $info[1];
                                                    $metadata['image_width'] = $info[0];
                                                    $metadata['image_height'] = $info[1];
                                                    $metadata['mime_type'] = $info['mime'];
                                                    $metadata['class'] = 'image';
                                                    $upload_dir = $app->upload_dir();
                                                    $imagine = new \Imagine\Gd\Imagine();
                                                    $image = $imagine->open( $value );
                                                    $width = 128;
                                                    $height = 128;
                                                    $mode = Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
                                                    $thumbnail = $image->thumbnail( new Imagine\Image\Box( $width, $height ), $mode );
                                                    $thumbnail->save( $upload_dir . DS . "thumb-square.{$ext}" );
                                                    $data['thumbnail_square'] = $upload_dir . DS . "thumb-square.{$ext}";
                                                    if ( $w > $h ) {
                                                        $width = 256;
                                                        $scale = $width / $w;
                                                        $height = round( $h * $scale );
                                                    } else {
                                                        $height = 256;
                                                        $scale = $height / $h;
                                                        $width = round( $w * $scale );
                                                    }
                                                    $image = $imagine->open( $value );
                                                    $thumbnail = $image->thumbnail( new Imagine\Image\Box( $width, $height ) );
                                                    $thumbnail->save( $upload_dir . DS . "thumb-small.{$ext}" );
                                                    $data['thumbnail_small'] = $upload_dir . DS . "thumb-small.{$ext}";
                                                    // var_dump( $data );
                                                } else if ( in_array( $ext, $videos ) ) {
                                                    $metadata['class'] = 'video';
                                                } else {
                                                    $metadata['class'] = 'file';
                                                }
                                                $date = date( 'Y-m-d H:i:s', time() );
                                                $metadata['uploaded'] = $date;
                                                $data['json'] = json_encode( $metadata );
                                                $meta[ $col ] = $data;
                                                $obj->$col( file_get_contents( $value ) );
                                            }
                                        }
                                    } else if ( $type == 'datetime' ) {
                                        $value = $obj->db2ts( $value );
                                        $value = $obj->ts2db( $value );
                                        $obj->$col( $value );
                                    } else if ( $type == 'int' ) {
                                        $value = (int) $value;
                                        $obj->$col( $value );
                                    } else {
                                        $obj->$col( $value );
                                    }
                                }
                            }
                            if (! $obj->id ) $obj->id( null );
                            $app->set_default( $obj );
                            // TODO Callback
                            $is_new = $obj->id ? true : false;
                            $obj->save();
                            if (! $is_new ) {
                                $insert++;
                            } else {
                                $update++;
                            }
                            $line = $obj->$primary;
                            echo $app->translate( "Saving %s ( %s )...",
                                        [ $app->translate( $table->label ),
                                          htmlspecialchars( $line ) ] );
                            $log_info[] = $is_new
                                        ? $app->translate( 'Create %s (%s / ID : %s).',
                                            [ $app->translate( $table->label ),
                                              $app->translate( $line ),
                                              $obj->id ] )
                                        : $app->translate( 'Update %s (%s / ID : %s).',
                                            [ $app->translate( $table->label ),
                                              $app->translate( $line ),
                                              $obj->id ] );
                            echo "<br>\n";
                            if (! empty( $meta ) ) {
                                foreach ( $meta as $key => $data ) {
                                    // var_dump( $data );
                                    $metadata = $app->db->model( 'meta' )->get_by_key(
                                             ['model' => $model, 'object_id' => $obj->id,
                                              'kind' => 'metadata', 'key' => $key ] );
                                    $metadata->text( $data['json'] );
                                    if ( isset( $data['thumbnail_small'] ) ) {
                                        $thumbnail = $data['thumbnail_small'];
                                        if ( file_exists( $thumbnail ) ) {
                                            $metadata->data( file_get_contents( $thumbnail ) );
                                        }
                                    }
                                    if ( isset( $data['thumbnail_square'] ) ) {
                                        $thumbnail = $data['thumbnail_square'];
                                        if ( file_exists( $thumbnail ) ) {
                                            $metadata->metadata( file_get_contents( $thumbnail ) );
                                        }
                                        PTUtil::remove_dir( dirname( $thumbnail ) );
                                    }
                                    $metadata->save();
                                }
                            }
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
                                        if (! $to_table ) continue;
                                        $to_primary = $to_table->primary;
                                        $to_ids = [];
                                        if ( $to_obj != 'asset' ) {
                                            foreach ( $csv as $name ) {
                                                $rel = $app->db->model( $to_obj )->get_by_key(
                                                    [ $to_primary => $name ] );
                                                if ( $rel->id ) {
                                                    $to_ids[] = (int) $rel->id;
                                                } else {
                                                    if ( $to_obj == 'tag' ) {
                                                        $workspace = $obj->workspace ? $obj->workspace : null;
                                                        if ( $app->can_do( 'tag', 'create', null, $workspace ) ) {
                                                            $normalize = str_replace( ' ', '', trim( mb_strtolower( $name ) ) );
                                                            $terms = ['normalize' => $normalize ];
                                                            if ( $workspace )
                                                                $terms['workspace_id'] = $workspace->id;
                                                            $tag_obj = $app->db->model( 'tag' )->get_by_key( $terms );
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
                                            foreach ( $csv as $path ) {
                                                $split_path = explode( '/', $path );
                                                $scope = array_shift( $split_path );
                                                $path = implode( '/', $split_path );
                                                $orig_path = $path;
                                                $ws_id = $scope == '%s' ? 0 : $obj->workspace_id;
                                                if (! $ws_id ) $ws_id = 0;
                                                $url_terms = ['workspace_id' => $ws_id,
                                                              'relative_path' => $path,
                                                              'model' => 'asset' ];
                                                $url_obj =
                                                    $app->db->model( 'urlinfo' )->get_by_key( $url_terms );
                                                $asset_obj = null;
                                                if ( $url_obj->id ) {
                                                    $asset_id = (int) $url_obj->object_id;
                                                    $asset_obj = $app->db->model( 'asset' )->load( $asset_id );
                                                }
                                                $path = str_replace( '%r', $dirname, $path );
                                                $_update = false;
                                                $orig_asset_obj = null;
                                                $is_new = false;
                                                if ( $asset_obj ) {
                                                    if ( $app->can_do( 'asset', 'edit', $asset_obj ) ) {
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
                                                        $workspace = $obj->workspace ? $obj->workspace : null;
                                                        if ( $app->can_do( 'asset', 'create', null, $workspace ) ) {
                                                            $asset_obj = $app->db->model( 'asset' )->new(
                                                               ['workspace_id' => $ws_id ] );
                                                            $asset_data = file_get_contents( $path );
                                                            $asset_obj->file( $asset_data );
                                                            $asset_obj->label( basename( $path ) );
                                                            $asset_obj->save();
                                                            $to_ids[] = (int) $asset_obj->id;
                                                            $_update = true;
                                                            $is_new = true;
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
                                                    $callback = ['name' => 'post_save',
                                                                 'table' => $to_table ];
                                                    $app->publish_obj( $asset_obj );
                                                    $app->post_save_asset( $callback, $app, $asset_obj );
                                                    $log_info[] =
                                                        $is_new
                                                        ? $app->translate( 'Create %s (%s / ID : %s).',
                                                        [ $app->translate( 'Asset' ),
                                                          $asset_obj->label,
                                                          $asset_obj->id ] )
                                                        : $app->translate( 'Update %s (%s / ID : %s).',
                                                        [ $app->translate( 'Asset' ),
                                                          $asset_obj->label,
                                                          $asset_obj->id ] );
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
                            $app->publish_obj( $obj, $original );
                            if ( $table->revisable && $exists ) {
                                PTUtil::pack_revision( $obj, $original, $update_rels );
                            }
                        }
                        $i++;
                    }
                    fclose( $fh );
                }
            }
            $msg = '';
            $plural = $app->translate( $plural );
            if ( $insert || $update ) {
                $msg = $app->translate(
                    'Import %s successfully( Insert : %s, Update : %s ).',
                        [ $plural, $insert, $update ] );
            } else {
                if (! $skip ) {
                    $msg = $app->translate(
                        'Could not import %s. Please check upload file format.', $plural );
                }
            }
            if ( $skip ) {
                $msg .= $app->translate(
                    '%s object(s) were skipped because you have not permission.', $skip );
            }
            echo '<hr>';
            echo $msg;
            $app->log( ['message'  => $msg,
                        'category' => 'import',
                        'model'    => $model,
                        'metadata' => json_encode( $log_info, JSON_UNESCAPED_UNICODE ),
                        'level'    => 'info'] );
            $session->remove();
            PTUtil::remove_dir( dirname( $session->value ) );
            exit();
        }
        echo $app->build_page( 'import_objects.tmpl' );
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
        $meta = $app->db->model( 'meta' )->new( ['model' => 'asset',
             'object_id' => $obj->id, 'kind' => 'metadata', 'key' => 'file'] );
        $metadata = ['file_size' => $size, 'image_width' => $image_width,
                     'image_height' => $image_height, 'class' => $class,
                     'extension' => $file_ext, 'basename' => $basename,
                     'mime_type' => $mime_type, 'file_name' => $file_name ];
        $metadata['uploaded'] = date( 'Y-m-d H:i:s' );
        $metadata['user_id'] = $app->user()->id;
        $meta->text( json_encode( $metadata ) );
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
        $meta->save();
        PTUtil::remove_dir( $upload_dir );
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
        $upload_dir = $app->upload_dir();
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