<?php

function ptutil_auto_loader($class) {
    $path = $class;
    $path = str_replace( '\\', DS, $path ) . '.php';
    if ( file_exists( LIB_DIR . $path ) ) {
        include_once( LIB_DIR . $path );
    }
}
spl_autoload_register( '\ptutil_auto_loader' );

class PTUtil {

    public static function mkpath ( $path, $mode = 0777 ) {
        if ( is_dir( $path ) ) return true;
        return mkdir( $path, $mode, true );
    }

    public static function start_end_month ( $ts ) {
        $y = substr( $ts, 0, 4 );
        $m = substr( $ts, 4, 2 );
        $start = sprintf( "%04d%02d01000000", $y, $m );
        $end   = sprintf( "%04d%02d%02d235959", $y, $m,
                 date( 't', mktime( 0, 0, 0, $m, 1, $y ) ) );
        return [ $start, $end ];
    }

    public static function setup_terms ( $obj, &$terms = [] ) {
        $app = Prototype::get_instance();
        if ( $obj->has_column( 'workspace_id' ) ) {
            $terms['workspace_id'] = $obj->workspace_id;
        }
        if ( $obj->has_column( 'status' ) ) {
            $status_published = $app->status_published( $obj->_model );
            $terms['status'] = $status_published;
        }
        if ( $obj->has_column( 'rev_type' ) ) {
            $terms['rev_type'] = 0;
        }
        return $terms;
    }

    public static function current_ts ( $ts = null ) {
        $ts = $ts ? $ts : time();
        return date( 'YmdHis', $ts );
    }

    public static function sec2hms ( $sec ) {
        $hours = floor( $sec / 3600 );
        $minutes = floor( ( $sec / 60 ) % 60 );
        $seconds = $sec % 60;
        return [ $hours, $minutes, $seconds ];
    }

    public static function clone_object ( $app, $obj, $strict = true ) {
        $clone = clone $obj;
        $clone->id( null );
        $blob_cols = $app->db->get_blob_cols( $obj->_model, true );
        foreach ( $blob_cols as $col ) {
            $clone->$col( $obj->$col );
        }
        $table = $app->get_table( $obj->_model );
        $columns = $app->db->model( 'column' )->load(
          ['table_id' => $table->id, 'unique' => 1] );
        foreach ( $columns as $column ) {
            $col_name = $column->name;
            if ( $column->type == 'string' || $column->type == 'text' ) {
                $orig_value = $clone->$col_name;
                if ( $orig_value ) {
                    $new_value = $app->translate( 'Clone of %s', $orig_value );
                    $clone->$col_name( $new_value );
                }
            } else if ( $column->type == 'int' ) {
                $maxObj = $app->db->model( $obj->_model )->load(
                  [], ['sort' => $col_name, 'direction' => 'descend', 'limit' => 1],
                  "id,$col_name" );
                if ( count( $maxObj ) ) {
                    $maxObj = $maxObj[0];
                    $clone->$col_name( $maxObj->$col_name + 1 );
                }
            }
        }
        if ( $obj->has_column( 'order' ) ) {
            $maxObj = $app->db->model( $obj->_model )->load(
              [], ['sort' => 'order', 'direction' => 'descend', 'limit' => 1],
              "id,order" );
            if ( count( $maxObj ) ) {
                $maxObj = $maxObj[0];
                $clone->order( $maxObj->order + 1 );
            }
        }
        if ( $obj->has_column( 'uuid' ) ) {
            $clone->uuid( $app->generate_uuid() );
        }
        if (! $strict ) {
            if ( $obj->has_column( 'status' ) ) {
                $workspace = $obj->workspace;
                if ( $app->user() ) {
                    $max_status = $app->max_status( $app->user(), $obj->_model, $workspace );
                    if ( $obj->status > $max_status ) {
                        $clone->status( $max_status );
                    }
                }
                $status_published = $app->status_published( $obj->_model );
                if ( $obj->status == $status_published ) {
                    if ( $status_published == 4 ) {
                        $clone->status = '0';
                    } else {
                        $clone->status( 1 );
                    }
                }
            }
            if ( $obj->has_column( 'created_by' ) ) {
                $clone->created_by('');
            }
            if ( $obj->has_column( 'modified_by' ) ) {
                $clone->modified_by('');
            }
            if ( $obj->has_column( 'created_on' ) ) {
                $clone->created_on('');
            }
            if ( $obj->has_column( 'modified_on' ) ) {
                $clone->modified_on('');
            }
            $app->set_default( $clone );
        }
        $clone->save();
        $orig_relations = $app->get_relations( $obj );
        $orig_metadata  = $app->get_meta( $obj );
        foreach ( $orig_relations as $relation ) {
            if ( $relation->to_obj != 'attachmentfile' ) {
                $rel = clone $relation;
                $rel->id( null );
                $rel->from_id = $clone->id;
                $rel->save();
            }
        }
        foreach ( $orig_metadata as $metadata ) {
            $meta = clone $metadata;
            $meta->id( null );
            $meta->object_id = $clone->id;
            $meta->save();
        }
        self::attachments_to_clone( $app, $obj, $clone );
        return $clone;
    }

    public static function mb_urlencode( $url ) {
        return preg_replace_callback( '/[^\x21-\x7e]+/',
            function( $matches ) {
                return urlencode( $matches[0] );
            }, $url );
    }

    public static function object_diff ( $app, $obj1, $obj2, $excludes = [] ) {
        $renderer = null;
        $scheme = $app->get_scheme_from_db( $obj1->_model );
        $properties = $scheme['edit_properties'];
        $column_defs = $scheme['column_defs'];
        $values = $obj1->get_values();
        $prefix = $obj1->_colprefix;
        $excludes = array_merge( $excludes, 
                    ['id', 'uuid', 'rev_type', 'rev_object_id', 'rev_changed', 'rev_diff',
                     'created_on', 'modified_on', 'created_by', 'modified_by'] );
        $excludes = array_unique( $excludes );
        $changed_cols = [];
        foreach ( $values as $key => $value1 ) {
            $key = preg_replace( "/^$prefix/", '' , $key );
            if ( in_array( $key, $excludes ) ) continue;
            $prop = isset( $properties[ $key ] ) ? $properties[ $key ] : '' ;
            if ( $prop && $prop === 'password' ) continue;
            $type = isset( $column_defs[ $key ] ) ? $column_defs[ $key ] : '';
            if ( is_array( $type ) ) {
                $type = $type['type'];
                $value2 = $obj2->$key;
                if ( $type == 'blob' ) {
                    $value1 = base64_encode( $value1 );
                    $value2 = base64_encode( $value2 );
                }
                $diff = self::diff( $value1, $value2, $renderer );
                if ( $diff ) {
                    if ( $type == 'blob' ) {
                        $changed_cols[ $key ] = true;
                    } else {
                        $changed_cols[ $key ] = $diff;
                    }
                }
            }
        }
        self::get_relation_diff( $app, $obj1, $obj2, $changed_cols );
        self::get_relation_diff( $app, $obj2, $obj1, $changed_cols );
        self::get_meta_diff( $app, $obj1, $obj2, $changed_cols );
        self::get_meta_diff( $app, $obj2, $obj1, $changed_cols );
        return $changed_cols;
    }

    public static function get_meta_diff ( $app, $obj1, $obj2,
        &$changed_cols = [] ) {
        $blobs = ['blob', 'data', 'metadata'];
        $metadata1  = $app->get_meta( $obj1 );
        foreach ( $metadata1 as $rel ) {
            if ( isset( $changed_cols[ $rel->key ] ) ) continue;
            $terms = ['model' => $rel->model, 'kind' => $rel->kind,
                      'key' => $rel->key,'object_id' => $obj2->id ];
            if ( $rel->type ) $terms['type'] = $rel->type;
            if ( $rel->field_id ) $terms['field_id'] = $rel->field_id;
            if ( $rel->number ) $terms['number'] = $rel->number;
            $comp = $app->db->model( 'meta' )->get_by_key( $terms );
            if (! $comp->id ) {
                if ( $rel->kind != 'thumbnail' ) {
                    $changed_cols[ $rel->key ] = true;
                }
            } else {
                if ( $rel->text != $comp->text ) {
                    $changed_cols[ $rel->key ] = true;
                } else {
                    foreach ( $blobs as $blob ) {
                        if ( $rel->$blob || $comp->$blob ) {
                            $value1 = base64_encode( $rel->$blob );
                            $value2 = base64_encode( $comp->$blob );
                            if ( $value1 != $value2 ) {
                                $changed_cols[ $rel->key ] = true;
                                break;
                            }
                        }
                    }
                }
            }
        }
        return $changed_cols;
    }

    public static function get_relation_diff ( $app, $obj1, $obj2,
        &$changed_cols = [] ) {
        $relations1 = $app->get_relations( $obj1 );
        foreach ( $relations1 as $rel ) {
            if ( isset( $changed_cols[ $rel->name ] ) ) continue;
            $comp = $app->db->model( 'relation' )->get_by_key(
                ['name' => $rel->name, 'from_obj' => $rel->from_obj,
                 'to_obj' => $rel->to_obj, 'to_id' => $rel->to_id,
                 'from_id' => $obj2->id, 'order' => $rel->order ] );
            if (! $comp->id ) {
                $changed_cols[ $rel->name ] = true;
            }
        }
        return $changed_cols;
    }

    public static function attachments_to_clone ( $app, $obj, $clone ) {
        $scheme = $app->get_scheme_from_db( $obj->_model );
        $attachment_cols = self::attachment_cols( $obj->_model, $scheme );
        $updated = false;
        foreach ( $attachment_cols as $col ) {
            if ( $obj->$col ) {
                $attachmentfile =
                    $app->db->model( 'attachmentfile' )->load( (int) $obj->$col );
                if ( $attachmentfile ) {
                    $orig_id = $attachmentfile->id;
                    $attach_clone = self::clone_object( $app, $attachmentfile );
                    if ( $clone->has_column( 'status' ) ) {
                        $attach_clone->status( $clone->status );
                    } else {
                        $attach_clone->status(0);
                    }
                    $attach_clone->save();
                    $clone->$col( $attach_clone->id );
                    $app->publish_obj( $attach_clone, null, false, true );
                    $updated = true;
                }
            }
        }
        if ( $updated ) {
            $clone->save();
        }
        $to_ids = [];
        $relations = $app->get_relations( $obj, 'attachmentfile' );
        if ( empty( $relations ) ) {
            return;
        }
        $rel_to_ids = [];
        foreach ( $relations as $meta ) {
            $file_id = (int) $meta->to_id;
            $attachment = $app->db->model( 'attachmentfile' )->load( $file_id );
            if (! $attachment ) continue;
            $attach_clone = self::clone_object( $app, $attachment );
            if ( $clone->has_column( 'status' ) ) {
                $attach_clone->status( $clone->status );
            } else {
                $attach_clone->status(0);
            }
            $attach_clone->save();
            $app->publish_obj( $attach_clone, null, false, true );
            $name = $meta->name;
            if ( isset( $rel_objs[ $name ] ) ) {
                $rel_to_ids[ $name ][] = (int) $attach_clone->id;
            } else {
                $rel_to_ids[ $name ] = [ (int) $attach_clone->id ];
            }
        }
        if (! empty( $rel_to_ids ) ) {
            foreach ( $rel_to_ids as $name => $to_ids ) {
                $args = ['from_id' => $clone->id, 
                         'name' => $name,
                         'from_obj' => $clone->_model,
                         'to_obj' => 'attachmentfile'];
                $app->set_relations( $args, $to_ids );
            }
        }
    }

    public static function attachment_cols ( $model, $scheme = null, $type = 'int' ) {
        $app = Prototype::get_instance();
        $scheme = $scheme ? $scheme : $app->get_scheme_from_db( $model );
        $properties = $scheme['edit_properties'];
        $column_defs = $scheme['column_defs'];
        $attachment_cols = [];
        foreach ( $properties as $key => $prop ) {
            $col_type = $column_defs[ $key ]['type'];
            if ( $col_type == $type ) {
                if ( strpos( $prop, 'relation:attachmentfile:' ) === 0 ) {
                    $attachment_cols[] = $key;
                }
            }
        }
        return $attachment_cols;
    }

    public static function session_to_attachmentfile ( $sess, $obj, $i = 1 ) {
        $app = Prototype::get_instance();
        $tmp_obj = $app->db->model( 'attachmentfile' )->new();
        $tmp_obj->file( $sess->data );
        $meta = json_decode( $sess->text, true );
        $tmp_obj->name( $meta['file_name'] );
        $tmp_obj->mime_type( $meta['mime_type'] );
        $tmp_obj->class( $meta['class'] );
        $tmp_obj->size( $meta['file_size'] );
        $app->set_default( $tmp_obj );
        if ( $obj->has_column( 'status' ) ) {
            $tmp_obj->status( $obj->status );
        }
        $tmp_id = $obj->id * 20 + $i;
        $tmp_id *= -1;
        $tmp_obj->id( $tmp_id );
        $tmp_obj->_meta = $meta;
        $tmp_obj->__session = $sess;
        return $tmp_obj;
    }

    public static function pack_revision ( $obj, &$original, &$changed_cols = [] ) {
        $app = Prototype::get_instance();
        $scheme = $app->get_scheme_from_db( $obj->_model );
        $columns = $scheme['column_defs'];
        $properties = $scheme['edit_properties'];
        $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];
        $values = $obj->get_values();
        $renderer = null;
        $excludes = ['id', 'uuid', 'rev_type', 'rev_object_id', 'rev_changed', 'rev_diff',
                     'created_on', 'modified_on', 'created_by', 'modified_by', 'password',
                     'rev_note', 'user_id', 'status', 'previous_owner', 'published_on',
                     'compiled', 'cache_key'];
        $obj->_relations = $obj->_relations
                          ? $obj->_relations : $app->get_relations( $obj );
        if ( $obj->_relations && $original->_relations ) {
            $orig_rels = $original->_relations;
            $obj_rels = $obj->_relations;
            $orig_rel_keys = [];
            $obj_rel_keys = [];
            foreach ( $orig_rels as $orig_rel ) {
                $rel_key = $orig_rel->name . ':'
                     . $orig_rel->to_obj . ':' . $orig_rel->to_id;
                $orig_rel_keys[] = $rel_key;
            }
            foreach ( $obj_rels as $obj_rel ) {
                $rel_key = $obj_rel->name . ':'
                     . $obj_rel->to_obj . ':' . $obj_rel->to_id;
                $obj_rel_keys[] = $rel_key;
            }
            foreach ( $orig_rel_keys as $rel_key ) {
                if (! in_array( $rel_key, $obj_rel_keys ) ) {
                    $changed_cols[ explode( ':', $rel_key )[0] ] = true;
                }
            }
            foreach ( $obj_rel_keys as $rel_key ) {
                if (! in_array( $rel_key, $orig_rel_keys ) ) {
                    $changed_cols[ explode( ':', $rel_key )[0] ] = true;
                }
            }
        }
        $obj->_meta = $obj->_meta
                     ? $obj->_meta : $app->get_meta( $obj );
        if ( $obj->_meta && $original->_meta ) {
            $orig_metadata = $original->_meta;
            $obj_metadata = $obj->_meta;
            $orig_fields = [];
            $obj_fields = [];
            $orig_meta_files = [];
            $orig_meta_labels = [];
            $obj_meta_files = [];
            $obj_meta_labels = [];
            foreach ( $orig_metadata as $orig_meta ) {
                if ( $orig_meta->kind == 'customfield' ) {
                    $field_basename = $orig_meta->key . '__c';
                    $orig_fields[ $field_basename ][ $orig_meta->number ]
                        = $orig_meta->text;
                } else if ( $orig_meta->kind == 'metadata'
                    && strpos( $orig_meta->text, '{"file_size":' ) === 0 ) {
                    $key = $orig_meta->key;
                    $json = json_decode( $orig_meta->text, true );
                    $orig_meta_files[ $key ] = isset( $json['file_name'] )
                        ? $json['file_name'] : '';
                    $orig_meta_labels[ $key ] = isset( $json['label'] )
                        ? $json['label'] : '';
                }
            }
            foreach ( $obj_metadata as $obj_meta ) {
                if ( $obj_meta->kind == 'customfield' ) {
                    $field_basename = $obj_meta->key . '__c';
                    $obj_fields[ $field_basename ][ $obj_meta->number ]
                        = $obj_meta->text;
                } else if ( $obj_meta->kind == 'metadata'
                    && strpos( $obj_meta->text, '{"file_size":' ) === 0 ) {
                    $key = $obj_meta->key;
                    $json = json_decode( $obj_meta->text, true );
                    $obj_meta_files[ $key ] = isset( $json['file_name'] )
                        ? $json['file_name'] : '';
                    $obj_meta_labels[ $key ] = isset( $json['label'] )
                        ? $json['label'] : '';
                }
            }
            foreach ( $orig_fields as $basename => $field_vars ) {
                foreach ( $field_vars as $number => $text ) {
                    $comp = '';
                    if (! isset( $obj_fields[ $basename ] )
                        || ! isset( $obj_fields[ $basename ][ $number ] ) ) {
                    } else {
                        $comp = $obj_fields[ $basename ][ $number ];
                    }
                    if ( $comp != $text ) {
                        $changed_cols["{$basename}__{$number}"] = 
                            self::diff( $text, $comp, $renderer );
                    }
                }
            }
            foreach ( $obj_fields as $basename => $field_vars ) {
                foreach ( $field_vars as $number => $text ) {
                    $comp = '';
                    if (! isset( $orig_fields[ $basename ] )
                        || ! isset( $orig_fields[ $basename ][ $number ] ) ) {
                    } else {
                        $comp = $orig_fields[ $basename ][ $number ];
                    }
                    if ( $comp != $text && ! isset( $changed_cols["{$basename}__{$number}"] ) ) {
                        $changed_cols["{$basename}__{$number}"] = 
                            self::diff( $comp, $text, $renderer );
                    }
                }
            }
            foreach ( $orig_meta_files as $col => $field_var ) {
                $comp = isset( $obj_meta_files[ $col ] ) ? $obj_meta_files[ $col ] : '';
                if ( $field_var != $comp ) {
                    $changed_cols["{$col}__filename"] = 
                        self::diff( $field_var, $comp, $renderer );
                }
            }
            foreach ( $orig_meta_labels as $col => $field_var ) {
                $comp = isset( $obj_meta_labels[ $col ] ) ? $obj_meta_labels[ $col ] : '';
                if ( $field_var != $comp ) {
                    $changed_cols["{$col}__filelabel"] = 
                        self::diff( $field_var, $comp, $renderer );
                }
            }
            foreach ( $obj_meta_files as $col => $field_var ) {
                $comp = isset( $orig_meta_files[ $col ] ) ? $orig_meta_files[ $col ] : '';
                if ( $field_var != $comp && !isset( $changed_cols["{$col}__filename"] ) ) {
                    $changed_cols["{$col}__filename"] = 
                        self::diff( $comp, $field_var, $renderer );
                }
            }
            foreach ( $obj_meta_labels as $col => $field_var ) {
                $comp = isset( $orig_meta_labels[ $col ] ) ? $orig_meta_labels[ $col ] : '';
                if ( $field_var != $comp && !isset( $changed_cols["{$col}__filelabel"] ) ) {
                    $changed_cols["{$col}__filelabel"] = 
                        self::diff( $comp, $field_var, $renderer );
                }
            }
        }
        $attachment_cols = self::attachment_cols( $obj->_model, $scheme );
        foreach( $columns as $col => $props ) {
            if ( isset( $relations[ $col ] ) ) {
                continue;
            } else if ( in_array( $col, $excludes ) ) {
                continue;
            }
            $type = $props['type'];
            $comp_old = $original->$col;
            $prop = isset( $properties[ $col ] ) ? isset( $properties[ $col ] ) : '' ;
            $comp_new = $obj->$col;
            if ( $type != 'blob' ) {
                $comp_old = preg_replace( "/\r\n|\r/","\n", $comp_old );
                $comp_new = preg_replace( "/\r\n|\r/","\n", $comp_new );
                $comp_old = rtrim( $comp_old );
                $comp_new = rtrim( $comp_new );
            }
            if ( $type === 'datetime' ) {
                $comp_old = preg_replace( '/[^0-9]/', '', $comp_old );
                $comp_old = (int) $comp_old;
                $comp_new = preg_replace( '/[^0-9]/', '', $comp_new );
                $comp_new = (int) $comp_new;
            } else if ( strpos( $type, 'int' ) !== false ) {
                $comp_new = (int) $comp_new;
                $comp_old = (int) $comp_old;
            } else if ( $type === 'blob' ) {
                $comp_new = base64_encode( $comp_new );
                $comp_old = base64_encode( $comp_old );
            }
            if ( $comp_old != $comp_new ) {
                if ( $type == 'blob' ) {
                    $changed_cols[ $col ] = true;
                } else {
                    if (! in_array( $col, $attachment_cols ) ) {
                        $changed_cols[ $col ] =
                            self::diff( $comp_old, $comp_new, $renderer );
                    }
                }
            }
        }
        if (! empty( $changed_cols ) ) {
            $original->rev_type( 1 );
            $original->rev_object_id( $obj->id );
            $changed = array_keys( $changed_cols );
            $original->rev_changed( join( ', ', $changed ) );
            $original->rev_diff( json_encode( $changed_cols,
                                 JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT ) );
            // $original->id( null );
            if ( $original->has_column( 'status' ) ) {
                $original->status( 0 );
            }
            $original->save();
            if ( $orig_relations = $original->_relations ) {
                foreach ( $orig_relations as $relation ) {
                    $rel_rev = clone $relation;
                    $rel_rev->id( null );
                    $rel_rev->from_id = $original->id;
                    if ( $rel_rev->to_obj == 'attachmentfile' ) {
                        $file =
                            $app->db->model( 'attachmentfile' )->load( (int) $rel_rev->to_id );
                        if ( $file ) {
                            $clone_file = self::clone_object( $app, $file );
                            $clone_file->status( 0 );
                            $clone_file->file( $file->file ); // 
                            $clone_file->save();
                            $rel_rev->to_id( $clone_file->id );
                        }
                    }
                    $rel_rev->save();
                }
            }
            if ( $orig_metadata = $original->_meta ) {
                foreach ( $orig_metadata as $meta ) {
                    $meta_rev = clone $meta;
                    $meta_rev->id( null );
                    $meta_rev->object_id = $original->id;
                    $meta_rev->save();
                }
            }
            $app->publish_obj( $original );
            return true;
        }
        return false;
    }

    public static function diff ( $source, $change, &$renderer = null ) {
        $source = str_replace( ['\r\n', '\r', '\n'], '\n', $source );
        $source = explode( "\n", $source );
        $change = str_replace( ['\r\n', '\r', '\n'], '\n', $change );
        $change = explode( "\n", $change );
        if (! $renderer && !class_exists( 'Text_Diff_Renderer_unified' ) ) {
            $text_diff = LIB_DIR . 'Text' . DS . 'Diff';
            require_once ( $text_diff . '.php' );
            require_once ( $text_diff . DS . 'Renderer.php' );
            require_once ( $text_diff . DS . 'Renderer' . DS . 'unified.php' );
            $renderer = new Text_Diff_Renderer_unified();
        }
        if (! $renderer ) {
            $renderer = new Text_Diff_Renderer_unified();
        }
        $diff = new Text_Diff( 'auto', [ $source, $change ] );
        return $renderer->render( $diff );
    }

    public static function remove_dir ( $dir, $children_only = false ) {
        if (! self::is_removable( $dir ) ) {
            return false;
        }
        if (! is_dir( $dir ) ) return;
        if ( $handle = opendir( $dir ) ) {
            while ( false !== ( $item = readdir( $handle ) ) ) {
                if ( $item != "." && $item != ".." ) {
                    if ( is_dir( $dir . DS . $item ) ) {
                        self::remove_dir( $dir . DS . $item, false );
                    } else {
                        unlink( $dir . DS . $item );
                    }
                }
            }
            closedir( $handle );
            if ( $children_only ) return true;
            return rmdir( $dir );
        }
    }

    public static function remove_empty_dirs ( $dirs ) {
        if ( empty( $dirs ) ) {
            return false;
        }
        if ( array_values( $dirs ) !== $dirs ) {
            $dirs = array_keys( $dirs );
        }
        $does_act = false;
        foreach ( $dirs as $dir ) {
            if ( is_dir( $dir ) && count( glob( $dir . "/*" ) ) == 0 ) {
                if ( self::is_dir_empty( $dir ) ) {
                    if (! self::is_removable( $dir ) ) {
                        continue;
                    }
                    rmdir( $dir );
                    $does_act = true;
                }
            }
        }
        return $does_act;
    }

    public static function is_removable ( $dir ) {
        $app = Prototype::get_instance();
        if ( strpos( $dir, realpath( $app->temp_dir ) ) === 0 ) {
            return true;
        }
        if ( strpos( $dir, realpath( $app->support_dir ) ) === 0 ) {
            return true;
        }
        if (! $app->app_protect ) return true;
        $dir = rtrim( $dir, DS );
        $app_path = $app->pt_dir;
        if ( strpos( $dir, $app_path ) === 0 ) {
            return false;
        }
        if ( $dir == $app->site_path ) {
            return false;
        }
        if ( $app->workspace() && $app->workspace()->site_path == $dir ) {
            return false;
        }
        return true;
    }

    public static function is_dir_empty ( $dir ) {
        if (!is_readable( $dir ) ) return NULL; 
        $handle = opendir( $dir );
        while ( false !== ( $entry = readdir( $handle ) ) ) {
            if ( $entry != '.' && $entry != '..' ) {
                return false;
            }
        }
        return true;
    }

    public static function make_basename ( $obj, $basename = '', $unique = false ) {
        $app = Prototype::get_instance();
        $basename_len = $app->basename_len;
        $table = $app->get_table( $obj->_model );
        if (! $basename ) $basename = $obj->_model;
        $basename = strtolower( $basename );
        $basename = preg_replace( "/[^a-z0-9\-]/", ' ', $basename );
        $basename = preg_replace( "/\s{1,}/", ' ', $basename );
        $basename = str_replace( ' ', '_', $basename );
        $basename = trim( $basename, '_' );
        $basename = mb_substr( $basename, 0, $basename_len, $app->db->charset );
        if ( $unique && $table->allow_identical ) {
            $permalink = $app->get_permalink( $obj );
            if ( $permalink ) {
                $url = $app->db->model( 'urlinfo' )->get_by_key(
                  ['url' => $permalink, 'delete_flag' => 0, 'model' => $obj->_model ] );
                if ( $url->id && $url->object_id != $obj->id ) {
                } else {
                    $unique = false;
                }
            } else if ( $basename ) {
                $unique = false;
            }
        }
        if (! $basename ) $basename = $obj->_model;
        if ( $unique ) {
            $terms = [];
            if ( $obj->id ) {
                $terms['id'] = ['!=' => (int)$obj->id ];
            }
            if ( $obj->has_column( 'workspace_id' ) ) {
                $workspace_id = $obj->workspace_id ? $obj->workspace_id : 0;
                if (! $workspace_id && $app->workspace() ) {
                    $workspace_id = $app->workspace()->id;
                }
                $terms['workspace_id'] = $workspace_id;
            }
            if ( $obj->has_column( 'rev_type' ) ) {
                $terms['rev_type'] = 0;
            }
            $terms['basename'] = $basename;
            $i = 1;
            $is_unique = false;
            $new_basename = $basename;
            while ( $is_unique === false ) {
                $exists = $app->db->model( $obj->_model )->load( $terms );
                if (! $exists ) {
                    $is_unique = true;
                    $basename = $new_basename;
                    break;
                } else {
                    $len = mb_strlen( $basename . '_' . $i );
                    if ( $len > $basename_len ) {
                        $diff = $len - $basename_len;
                        $basename = mb_substr(
                            $basename, 0, $basename_len - $diff, $app->db->charset );
                    }
                    $new_basename = $basename . '_' . $i;
                    $terms['basename'] = $new_basename;
                }
                $i++;
            }
        }
        return $basename;
    }

    public static function thumbnail_url ( $obj, $args, $assetproperty = null ) {
        $app = Prototype::get_instance();
        $fmgr = $app->fmgr;
        $orig_args = $args;
        $orig_property = $assetproperty;
        $ctx = $app->ctx;
        $id = $obj->id;
        $width = isset( $args['width'] ) ? (int) $args['width'] : '';
        $height = isset( $args['height'] ) ? (int) $args['height'] : '';
        $square = isset( $args['square'] ) ? $args['square'] : false;
        $scale = isset( $args['scale'] ) ? (int) $args['scale'] : '';
        $model = isset( $args['model'] ) ? $args['model'] : $obj->_model;
        $name = isset( $args['name'] ) ? $args['name'] : 'file';
        $model = strtolower( $model );
        $name = strtolower( $name );
        if (! $obj->$name ) return;
        if ( $scale ) {
            if ( $width ) $width = round( $width * $scale / 100 );
            if ( $height ) $height = round( $height * $scale / 100 );
        }
        $modified_on = $obj->modified_on;
        $modified_on = $obj->db2ts( $modified_on );
        if (! $assetproperty ) $assetproperty = $app->get_assetproperty( $obj, $name );
        if ( empty( $assetproperty ) ) {
            return '';
        }
        $basename = $assetproperty['basename'];
        $extension = $assetproperty['extension'];
        if ( $assetproperty['class'] != 'image' ) {
            return '';
        }
        $thumbnail_basename = 'thumb';
        if ( $model != 'asset' ) {
            $thumbnail_basename .= "-{$model}";
        }
        if ( $width && !$height ) {
            $thumbnail_basename .= "-{$width}xauto";
        } else if (!$width && $height ) {
            $thumbnail_basename .= "-autox{$height}";
        }
        if ( $square ) {
            $thumbnail_basename .= '-square';
        }
        $thumbnail_basename .= "-{$id}";
        $thumbnail_name = "{$thumbnail_basename}-{$name}.{$extension}";
        $site_path = $app->site_path;
        $extra_path = $app->extra_path;
        $site_url = $app->site_url;
        if ( $workspace = $obj->workspace ) {
            $site_path = $workspace->site_path;
            $extra_path = $workspace->extra_path;
            $site_url = $workspace->site_url;
        }
        $relative_path = '%r' . DS;
        $relative_url =
            preg_replace( '!^https{0,1}:\/\/.*?\/!', '/', $site_url );
        $relative_url .= $extra_path;
        $relative_url = rtrim( $relative_url, '/' );
        $relative_url .= '/thumbnails/' . $thumbnail_name;
        $relative_path .= $extra_path;
        $relative_path = rtrim( $relative_path, DS );
        $relative_path .= DS . 'thumbnails' . DS . $thumbnail_name;
        $asset_url = rtrim( $site_url, '/' );
        $asset_url .= '/' . $extra_path;
        $asset_url = rtrim( $asset_url, '/' );
        $asset_url .= '/thumbnails/' . $thumbnail_name;
        $asset_path = rtrim( $site_path, DS );
        $asset_path = $asset_path . DS . $extra_path;
        $asset_path = rtrim( $asset_path, DS );
        $asset_path .= DS . 'thumbnails' . DS . $thumbnail_name;
        $metadata = $app->db->model( 'meta' )->get_by_key( [
            'object_id' => $id, 'model' => $model,
            'kind' => 'thumbnail', 'key' => $name, 'value' => $thumbnail_basename ] );
        $uploaded = 0;
        if ( $metadata->text ) {
            $thumb_props = json_decode( $metadata->text, true );
            $uploaded = $thumb_props['uploaded'];
            $uploaded = $obj->db2ts( $uploaded );
        }
        $wants = false;
        if ( isset( $orig_args['wants'] ) && $orig_args['wants'] == 'data' ) {
            $wants = true;
        }
        $app->logging = false;
        $error_reporting = ini_get( 'error_reporting' ); 
        error_reporting(0);
        $hash = '';
        if (! $metadata->id || $modified_on > $uploaded || $wants ) {
            $ctx->stash( 'current_context', $model );
            $ctx->stash( $model, $obj );
            $args = ['model' => $model, 'name' => $name, 'id' => $id ];
            $args['width'] = $width;
            $args['height'] = $height;
            $args['square'] = $square;
            $args['scale'] = $scale;
            $meta = [];
            $upload_dir = $app->upload_dir();
            $file = $upload_dir . DS . $obj->id;
            file_put_contents( $file, $obj->$name );
            list( $w, $h ) = getimagesize( $file );
            if (! $width || ! $height ) {
                if ( $scale ) {
                    $scale = $scale * 0.01;
                    $height = round( $h * $scale );
                    $width = round( $w * $scale );
                } else {
                    if ( $width ) {
                        $scale = $width / $w;
                        $height = round( $h * $scale );
                    } else if ( $height ) {
                        $scale = $height / $h;
                        $width = round( $w * $scale );
                    }
                }
            }
            if ( $square ) {
                if ( $width > $height ) {
                    $width = $height;
                } else {
                    $height = $width;
                }
            }
            $meta['image_width'] = $width;
            $meta['image_height'] = $height;
            $imagine = new \Imagine\Gd\Imagine();
            $image = $imagine->open( $file );
            if ( $square ) {
                $mode = Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
                $thumbnail = $image->thumbnail( new Imagine\Image\Box( $width, $height ), $mode );
            } else {
                $thumbnail = $image->thumbnail( new Imagine\Image\Box( $width, $height ) );
            }
            $thumbnail->save( $upload_dir . DS . $thumbnail_name );
            $thumb = file_get_contents( $upload_dir . DS . $thumbnail_name );
            if ( isset( $orig_args['wants'] ) && $orig_args['wants'] == 'data' ) {
                // $fmgr->rmdir( $upload_dir );
                return $thumb;
            }
            $t_property = $assetproperty;
            $t_property['file_name'] = $thumbnail_name;
            $t_property['basename'] = $thumbnail_basename;
            $t_property['file_size'] = strlen( bin2hex( $thumb ) ) / 2;
            $t_property['image_width'] = $meta['image_width'];
            $t_property['image_height'] = $meta['image_height'];
            $t_property['uploaded'] = date( 'Y-m-d H:i:s' );
            $t_property['user_id'] = $app->user()->id;
            $metadata->blob( $thumb );
            $orig_args = serialize( $orig_args );
            $orig_property = serialize( $orig_property );
            $metadata->data( $orig_args );
            $metadata->metadata( $orig_property );
            $hash = md5( base64_encode( $thumb ) );
            $t_property = json_encode( $t_property );
            $metadata->text( $t_property );
            $metadata->save();
            // $fmgr->rmdir( $upload_dir );
        }
        $thumb = $metadata->blob;
        $info = $app->db->model( 'urlinfo' )->get_by_key( [
            'object_id' => $id, 'model' => $model, 'class' => 'file',
            'key' => 'thumbnail', 'meta_id' => $metadata->id,
            'workspace_id' => $obj->workspace_id ] );
        if ( $info->relative_path != $relative_path ) {
            if ( $info->file_path &&
                ( $info->file_path != $asset_path ) ) {
                if ( file_exists( $info->file_path ) ) {
                    unlink( $info->file_path );
                }
            }
            $mimetype = null;
            if ( $obj->has_column( 'mime_type' ) ) {
                $mimetype = $obj->mime_type;
            } else {
                $extension = isset( pathinfo( $asset_path )['extension'] )
                           ? pathinfo( $asset_path )['extension'] : '';
                $mimetype = self::get_mime_type( $extension );
            }
            $info->set_values( [
                'relative_path' => $relative_path,
                'relative_url' => $relative_url,
                'file_path' => $asset_path,
                'url' => $asset_url,
                'mime_type' => $mimetype,
            ] );
            if ( $obj->status == 4 ) {
                $info->is_published( 1 );
            }
        }
        $publish = false;
        if ( $obj->status != 4 ) {
            if ( file_exists( $asset_path ) ) {
                unlink( $asset_path );
                $info->is_published( 0 );
            }
        } else {
            if ( file_exists( $asset_path ) ) {
                $comp = md5( base64_encode( file_get_contents( $asset_path ) ) );
                $data = md5( base64_encode( $thumb ) );
                if ( $comp != $data ) {
                    $publish = true;
                }
                $hash = $data;
            } else {
                $publish = true;
            }
            $info->is_published( 1 );
        }
        if ( $publish && ! $app->param( '_preview' ) ) {
            // self::mkpath( dirname( $asset_path ) );
            $fmgr->put( $asset_path, $thumb );
        }
        if ( $hash ) {
            $info->md5( $hash );
        }
        error_reporting( $error_reporting );
        $app->logging = $logging;
        $info->save();
        if ( $app->param( '_preview' ) && $app->txn_active ) {
            if ( $obj->id < 1 ) {
                self::temp_object( $info );
            }
            $app->db->commit();
            $app->db->begin_work();
        }
        return $asset_url;
    }

    public static function temp_object ( $obj, $expires = 60 ) {
        $app = Prototype::get_instance();
        $screen_id = $app->screen_id ? $app->screen_id : $app->request_id;
        $session = $app->db->model( 'session' )->get_by_key(
            ['name'  => $screen_id, 'key' => $obj->_model,
             'value' => (int) $obj->id,
             'kind'  => 'TP'] );
        $session->start( time() );
        $session->expires( time() + $expires );
        $app->set_default( $session );
        $session->save();
    }

    public static function get_meta_property ( $obj, $col, $property ) {
        if (! $obj->id ) return;
        $app = Prototype::get_instance();
        $meta = $app->db->model( 'meta' )->get_by_key(
                               ['model' => $obj->_model, 'object_id' => $obj->id,
                                'kind' => 'metadata', 'key' => $col ] );
        if (! $meta->id ) return;
        $metadata = json_decode( $meta->text );
        if ( property_exists( $metadata, $property ) ) {
            return $metadata->$property;
        }
    }

    public static function set_meta_property ( &$obj, $col, $property, $value ) {
        if (! $obj->id ) {
            $obj->save();
        }
        $app = Prototype::get_instance();
        $meta = $app->db->model( 'meta' )->get_by_key(
                               ['model' => $obj->_model, 'object_id' => $obj->id,
                                'kind' => 'metadata', 'key' => $col ] );
        if (! $meta->id ) return;
        $metadata = json_decode( $meta->text, true );
        $metadata[ $property ] = $value;
        $new = json_encode( $metadata );
        if ( $meta->text != $new ) {
            $meta->text( $new );
            $meta->save();
        }
    }

    public static function file_attach_to_obj ( $app, $obj, $col, $path,
                                                $label = '', &$error = '' ) {
        if (! file_exists( $path ) ) {
            $error = 'File not found.';
            return null;
        }
        $model = $obj->_model;
        $logging = $app->logging;
        $app->logging = false;
        $error_reporting = ini_get( 'error_reporting' ); 
        error_reporting(0);
        // Warning: exif_read_data File not supported in...
        $data = self::get_upload_info( $app, $path, $error );
        $app->logging = $logging;
        error_reporting( $error_reporting );
        if ( $error ) return null;
        $obj->$col( file_get_contents( $path ) );
        if (! $obj->id ) {
            $obj->save();
        }
        $meta = $app->db->model( 'meta' )->get_by_key(
                               ['model' => $obj->_model, 'object_id' => $obj->id,
                                'kind' => 'metadata', 'key' => $col ] );
        $metadata = $data['metadata'];
        if ( $label ) {
            $metadata['label'] = $label;
        }
        $metadata = json_encode( $metadata );
        if ( isset( $data['thumbnail_square'] ) ) {
            $thumbnail_square = $data['thumbnail_square'];
            $thumbnail_small = $data['thumbnail_small'];
            if ( file_exists( $thumbnail_square ) ) {
                $meta->metadata( file_get_contents( $thumbnail_square ) );
            }
            if ( file_exists( $thumbnail_small ) ) {
                $meta->data( file_get_contents( $thumbnail_small ) );
            }
            // self::remove_dir( dirname( $thumbnail_small ) );
        }
        $meta->text( $metadata );
        $obj->save();
        $meta->save();
        return $obj;
    }

    public static function update_blob_label ( $app, $obj, $col, $label = '' ) {
        $meta = $app->db->model( 'meta' )->get_by_key(
                               ['model' => $obj->_model, 'object_id' => $obj->id,
                                'kind' => 'metadata', 'key' => $col ] );
        if (! $meta->id ) {
            return;
        }
        $metadata = json_decode( $meta->text, true );
        if (! isset( $metadata['label'] ) ||
            ( isset( $metadata['label'] ) && $metadata['label'] != $label ) ) {
            $metadata['label'] = $label;
            $metadata = json_encode( $metadata );
            $meta->text( $metadata );
            return $meta->save();
        }
    }

    public static function get_upload_info ( $app, $upload_path, &$error ) {
        if (! file_exists( $upload_path ) ) {
            $error = 'File not found.';
            return [];
        }
        $app->logging = false;
        $error_reporting = ini_get( 'error_reporting' ); 
        error_reporting(0);
        $images = $app->images;
        $videos = $app->videos;
        $audios = $app->audios;
        $upload_dir = $app->mode == 'manage_theme' ? $app->upload_dir() : dirname( $upload_path );
        $data = [];
        $ext = strtolower( pathinfo( $upload_path, PATHINFO_EXTENSION ) );
        $pathdata = pathinfo( $upload_path );
        $mime_type = self::get_mime_type( $ext );
        $metadata = [
            'file_size' => filesize( $upload_path ),
            'mime_type' => $mime_type,
            'extension' => $ext,
            'basename'  => $pathdata['filename'],
            'file_name' => $pathdata['basename'] ];
        if ( $user = $app->user() ) {
            $metadata['user_id'] = $user->id;
        }
        $basename = md5( $pathdata['filename'] );
        if ( in_array( $ext, $videos ) ) {
            $metadata['class'] = 'video';
        } else if ( in_array( $ext, $audios ) ) {
            $metadata['class'] = 'audio';
        } else if ( in_array( $ext, $images ) ) {
            try {
                $info = getimagesize( $upload_path );
                $w = $info[0];
                $h = $info[1];
                $metadata['image_width'] = $info[0];
                $metadata['image_height'] = $info[1];
                $metadata['mime_type'] = $info['mime'];
                $metadata['class'] = 'image';
            } catch ( Exception $e ) {
                $error = $e->getMessage();
                $data['metadata'] = $metadata;
                return $data;
            }
            $imagine = new \Imagine\Gd\Imagine();
            $image = null;
            try {
                $image = $imagine->open( $upload_path );
            } catch ( Exception $e ) {
                $error = $e->getMessage();
                $data['metadata'] = $metadata;
                return $data;
            }
            $width = 128;
            $height = 128;
            $mode = Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
            if (! is_dir( $upload_dir . DS . "thumbnails-{$basename}" ) ) {
                mkdir( $upload_dir . DS . "thumbnails-{$basename}", 0777, true );
            }
            // $app->fmgr->mkpath( $upload_dir . DS . "thumbnails-{$basename}" );
            $thumbnail = $image->thumbnail( new Imagine\Image\Box( $width, $height ), $mode );
            $thumbnail_square = $upload_dir . DS . "thumbnails-{$basename}" . DS . "thumb-square.{$ext}";
            $thumbnail->save( $thumbnail_square );
            $data['thumbnail_square'] = $thumbnail_square;
            if ( $w > $h ) {
                $width = 256;
                $scale = $width / $w;
                $height = round( $h * $scale );
            } else {
                $height = 256;
                $scale = $height / $h;
                $width = round( $w * $scale );
            }
            $image = $imagine->open( $upload_path );
            $thumbnail = $image->thumbnail( new Imagine\Image\Box( $width, $height ) );
            $thumbnail_small = $upload_dir . DS . "thumbnails-{$basename}" . DS . "thumb-small.{$ext}";
            $thumbnail->save( $thumbnail_small );
            $data['thumbnail_small'] = $thumbnail_small;
        } else {
            $metadata['class'] = 'file';
        }
        error_reporting( $error_reporting );
        $app->logging = $logging;
        $date = date( 'Y-m-d H:i:s', time() );
        $metadata['uploaded'] = $date;
        $data['metadata'] = $metadata;
        return $data;
    }

    public static function generate_uuid () {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), 
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }

    public static function get_fields ( $obj, $args = [], $type = 'objects', $all = false ) {
        $app = Prototype::get_instance();
        if ( is_string( $obj ) ) $obj = $app->db->model( $obj )->new();
        if ( is_string( $args ) ) {
            $type = $args;
            $args = [];
        }
        $table = $app->get_table( $obj->_model );
        $terms = [];
        $terms = ['name' => 'models', 'from_obj' => 'field', 'to_obj' => 'table',
                  'to_id' => $table->id ];
        $relations = $app->db->model( 'relation' )->load(
                                       $terms, ['sort' => 'order'] );
        if ( empty( $relations ) ) {
            return [];
        }
        $ids = [];
        foreach ( $relations as $rel ) {
            $ids[] = (int) $rel->from_id;
        }
        $workspace_ids = [0];
        if ( $obj->has_column( 'workspace_id' ) ) {
            if ( $obj->workspace_id ) {
                $workspace_ids[] = (int) $obj->workspace_id;
            }
        }
        if ( isset( $args['workspace_id'] ) && $args['workspace_id'] ) {
            $workspace_ids[] = (int) $args['workspace_id'];
        } else {
            if ( $app->workspace() ) {
                $workspace_ids[] = (int) $app->workspace()->id;
            }
        }
        $workspace_ids = array_unique( $workspace_ids );
        if (! $all ) {
            $fields = $app->db->model( 'field' )->load(
                ['id' => ['IN' => $ids ], 'workspace_id' => ['IN' => $workspace_ids ] ] );
        } else {
            $fields = $app->db->model( 'field' )->load( ['id' => ['IN' => $ids ] ] );
        }
        if ( empty( $fields ) ) {
            return [];
        }
        $_fields = [];
        foreach ( $ids as $id ) {
            $field = $app->db->model( 'field' )->load( $id );
            if ( in_array( $field->workspace_ids, $workspace_ids ) ) {
                $_fields[] = $field;
            }
        }
        if ( $type === 'objects' ) {
            return $_fields;
        } else if ( $type === 'basenames' ) {
            $basenames = [];
            foreach ( $_fields as $field ) {
                $basenames[] = $field->basename;
            }
            return $basenames;
        } else if ( $type === 'types' ) {
            $meta_fields = [];
            foreach ( $_fields as $field ) {
                $fieldtype = $field->fieldtype;
                $type = $fieldtype ? $fieldtype->basename : '';
                $props = ['type' => $type, 'id' => $field->id ];
                $basenames[ $field->basename ] = $props;
            }
            return $basenames;
        } else if ( $type === 'requireds' ) {
            $meta_fields = [];
            foreach ( $_fields as $field ) {
                if ( $field->required )
                    $meta_fields[ $field->basename ] = $field->name;
            }
            return $meta_fields;
        } else if ( $type === 'displays' ) {
            $basenames = [];
            foreach ( $_fields as $field ) {
                if ( $field->display )
                    $basenames[] = $field->basename;
            }
            return $basenames;
        }
    }

    public static function add_id_to_field ( &$content, $uniqueid, $basename ) {
        if (! $content ) return $content;
        $app = Prototype::get_instance();
        $ctx = $app->ctx;
        $dom = $ctx->dom;
        if (!$dom->loadHTML( mb_convert_encoding( $content,
                'HTML-ENTITIES','utf-8' ),
            LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD|LIBXML_COMPACT ) )
            trigger_error( 'loadHTML failed!' );
        $inputs = ['input', 'textarea', 'select', 'button'];
        foreach ( $inputs as $ctrl ) {
            $elements = $dom->getElementsByTagName( $ctrl );
            if ( $elements->length ) {
                $i = $elements->length - 1;
                while ( $i > -1 ) {
                    $ele = $elements->item( $i );
                    $i -= 1;
                    $ctrl_name = $ele->getAttribute( 'name' );
                    $ctrl_value = $ele->getAttribute( 'value' );
                    if ( $ctrl_value === $uniqueid ) continue;
                    if ( $ctrl_name && $ctrl_name != "{$basename}__c"
                        && $ctrl_name != "{$basename}__c[]"
                        && strpos( $ctrl_name, $uniqueid ) === false ) {
                        $new_name = "{$uniqueid}_{$ctrl_name}";
                        $ele->setAttribute( 'name', $new_name );
                    }
                }
            }
        }
        $content = mb_convert_encoding( $dom->saveHTML(),
                                        'utf-8', 'HTML-ENTITIES' );
        return $content;
    }

    public static function make_zip_archive ( $dir, $file, $root = '' ){
        $zip = new ZipArchive();
        $res = $zip->open( $file, ZipArchive::CREATE );
        if( $res ) {
            if( $root ) {
                $zip->addEmptyDir( $root );
                $root .= DS;
            }
            $base_len = mb_strlen( $dir );
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $dir,
                    FilesystemIterator::SKIP_DOTS
                    |FilesystemIterator::KEY_AS_PATHNAME
                    |FilesystemIterator::CURRENT_AS_FILEINFO
                ), RecursiveIteratorIterator::SELF_FIRST
            );
            $list = [];
            foreach( $iterator as $pathname => $info ){
                $localpath = $root . mb_substr( $pathname, $base_len );
                if( $info->isFile() ){
                    // $zip->addFile( $pathname, $localpath );
                    $zip->addFile( $pathname, ltrim( $localpath, '/' ) );
                } else {
                    $res = $zip->addEmptyDir( $localpath );
                }
            }
            $zip->close();
        } else {
            return false;
        }
        return $res;
    }

    public static function object_to_resource ( $obj, $type = 'api', $required = null ) {
        $app = Prototype::get_instance();
        $scheme = $app->get_scheme_from_db( $obj->_model );
        $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];
        $translates = isset( $scheme['translate'] ) ? $scheme['translate'] : [];
        $options = isset( $scheme['options'] ) ? $scheme['options'] : [];
        $column_defs = $scheme['column_defs'];
        $list_properties = $scheme['list_properties'];
        $vars = $obj->get_values( true );
        $object_keys = array_keys( $vars );
        if ( in_array( 'object_id', $object_keys ) && in_array( 'model', $object_keys ) ) {
            $object_id = (int) $vars['object_id'];
            $object_model = $vars['model'];
            if ( $object_model && $object_id ) {
                $obj_table = $app->get_table( $object_model );
                if ( $obj_table ) {
                    $_primary = $obj_table->primary;
                    $cols = "id,{$_primary}";
                    $obj_model = $app->db->model( $object_model )->get_by_key(
                         ['id' => $object_id ], ['limit' => 1], $cols );
                    if ( $obj_model->id ) {
                        $name = $obj_model->$_primary
                              ? $obj_model->$_primary
                              : "null(id:{$object_id})";
                        $vars['_model_primary'] = $name;
                    }
                }
            }
        }
        foreach ( $vars as $key => $var ) {
            if ( $key != 'status' && $required && ! in_array( $key, $required ) ) continue;
            if ( isset( $column_defs[ $key ] ) && isset( $column_defs[ $key ]['type'] ) ) {
                if ( $column_defs[ $key ]['type'] == 'blob' ) {
                    unset( $vars[ $key ] );
                }
            }
            if ( isset( $list_properties[ $key ] ) ) {
                if (! in_array( $key, $relations ) ) {
                    $rel_obj = null;
                    $prop = $list_properties[ $key ];
                    if ( $prop && strpos( $prop, 'reference:' ) === 0 ) {
                        $props = explode( ':', $prop );
                        $rel_model = $props[1];
                        if ( $rel_model == '__any__' ) {
                            if (! isset( $vars['model'] ) ) continue;
                            $rel_model = $vars['model'];
                        }
                        if ( ctype_digit( $vars[ $key ] ) ) {
                            $ref_id = (int) $vars[ $key ];
                            if ( $ref_id ) {
                                $rel_obj = $app->db->model( $rel_model )->load( $ref_id );
                            }
                            if ( $rel_obj ) {
                                if ( $type === 'api' ) {
                                    $vars[ $key ] = self::object_to_resource( $rel_obj );
                                } else {
                                    $rel_col = $props[2];
                                    if ( $rel_col == '__primary__' ) {
                                        $rel_table = $app->get_table( $rel_model );
                                        $rel_col = $rel_table->primary;
                                    }
                                    if ( $rel_obj->has_column( $rel_col ) ) {
                                        if ( $key == 'workspace_id' ) {
                                            $vars['workspace_name'] = $rel_obj->$rel_col;
                                        } else {
                                            $vars[ $key ] = $rel_obj->$rel_col;
                                        }
                                    }
                                }
                            } else {
                                $vars[ $key ] = null;
                            }
                        }
                    }
                    if ( $type === 'list' ) {
                        switch ( true ) {
                            case $prop === 'primary':
                                //$vars[ $key ] = self::trim_to( $vars[ $key ], 60 );
                                if ( in_array( $key, $translates ) ) {
                                    $vars[ $key ] = $app->translate( $vars[ $key ] );
                                }
                                break;
                            case $prop === 'datetime':
                                $format = 'Y-m-d H:i';
                                if ( in_array( $key, $translates ) ) {
                                    $format = $app->translate( $format );
                                }
                                if ( $vars[ $key ] ) $vars[ $key ] = 
                                date( $format, strtotime( $obj->db2ts( $vars[ $key ] ) ) );
                                break;
                            case $prop === 'date':
                                $format = 'Y-m-d';
                                if ( in_array( $key, $translates ) ) {
                                    $format = $app->translate( $format );
                                }
                                if ( $vars[ $key ] ) $vars[ $key ] = 
                                date( $format, strtotime( $obj->db2ts( $vars[ $key ] ) ) );
                                break;
                            case $key === 'status':
                                if (! empty( $options ) ) {
                                    $status_opt = $options['status'];
                                    $args = ['options' => $status_opt,
                                             'status' => $vars[ $key ],
                                             'icon' => 1, 'text' => 1];
                                    $vars['_status_text'] =
                                        $app->core_tags->hdlr_statustext( $args, $app->ctx );
                                }
                                break;
                            case $prop === 'text':
                                if ( in_array( $key, $translates ) ) {
                                    $vars[ $key ] = $app->translate( $vars[ $key ] );
                                }
                                if ( $vars[ $key ] === null ) {
                                    $vars[ $key ] = '';
                                }
                                $vars[ $key ] = self::trim_to( $vars[ $key ], 60 );
                                break;
                            case $prop === 'text_short':
                                if ( in_array( $key, $translates ) ) {
                                    $vars[ $key ] = $app->translate( $vars[ $key ] );
                                }
                                if ( $vars[ $key ] === null ) {
                                    $vars[ $key ] = '';
                                }
                                $vars[ $key ] = self::trim_to( $vars[ $key ], 22 );
                                break;
                            case $prop === 'password':
                                $vars[ $key ] = $vars[ $key ] ? '**********...' : '';
                                break;
                            default:
                                if ( in_array( $key, $translates ) )
                                    $vars[ $key ] = $app->translate( $vars[ $key ] );
                        }
                    }
                }
            }
        }
        if (! isset( $vars['workspace_name'] ) ) $vars['workspace_name'] = '';
        if (! isset( $vars['_status_text'] ) ) $vars['_status_text'] = '';
        $edit_properties = $scheme['edit_properties'];
        $thumbnail = false;
        $vars['_icon'] = '';
        $vars['_icon_class'] = '';
        $vars['_has_file'] = '';
        foreach ( $edit_properties as $col => $prop ) {
            if ( $thumbnail && $required && ! in_array( $col, $required ) ) continue;
            if ( $prop === 'file' ) {
                $vars['_has_file'] = 1;
                $meta_vars = [];
                $meta = $app->db->model( 'meta' )->get_by_key(
                    ['model' => $obj->_model, 'object_id' => $obj->id,
                     'kind' => 'metadata', 'key' => $col ] );
                if ( $meta->id ) {
                    $meta_vars = json_decode( $meta->text, true );
                    $url = $app->get_assetproperty( $obj, $col, 'url' );
                    $meta_vars['url'] = $url;
                    $vars['_icon_class'] = isset( $meta_vars['class'] )
                                         ? $meta_vars['class'] : '';
                    $ext = $meta_vars['extension'];
                    if (! $thumbnail && ( $vars['_icon_class'] == 'image' || $ext == 'svg' ) ) {
                        $icon = $app->admin_url
                              . '?__mode=get_thumbnail&square=1&id=' . $meta->id;
                        $vars['_icon'] = $icon;
                        $icon2 = $app->admin_url
                              . '?__mode=get_thumbnail&id=' . $meta->id;
                        $vars['_icon_large'] = $icon2;
                        $thumbnail = true;
                    }
                }
                if ( in_array( $col, $object_keys ) ) {
                    $vars[ $col ] = isset( $meta_vars['file_name'] )
                                  ? $meta_vars['file_name'] : $meta_vars;
                }
            }
        }
        foreach ( $relations as $name => $to_obj ) {
            if ( $required && ! in_array( $name, $required ) ) continue;
            if ( $to_obj == '__any__' ) $to_obj = null;
            $rel_objs = $app->get_relations( $obj, $to_obj, $name );
            $relation_vars = [];
            if (! empty( $rel_objs ) ) {
                if (! $to_obj ) {
                    $first = $rel_objs[0];
                    $to_obj = $first->to_obj;
                }
                $rel_table = $app->get_table( $to_obj );
                if (! $rel_table ) continue;
                $prop = $list_properties[ $name ];
                $props = explode( ':', $prop );
                $rel_col = $props[2];
                if ( $rel_col === 'primary ' || !$rel_col || $rel_col == 'null' ) {
                    $rel_col = $rel_table->primary;
                }
                $rel_ids = [];
                foreach ( $rel_objs as $rel_obj ) {
                    $rel_ids[] = (int) $rel_obj->to_id;
                }
                $load_relations = $app->db->model( $to_obj )->load(
                    ['id' => ['IN' => $rel_ids ] ] );
                foreach ( $rel_objs as $rel_obj ) {
                    $to_id = (int) $rel_obj->to_id;
                    $rel_obj = $app->db->model( $to_obj )->load( $to_id );
                    if ( $rel_obj ) {
                        if ( $type === 'api' ) {
                            $relation_vars[] = self::object_to_resource( $rel_obj );
                        } else {
                            $rel_value = $rel_obj->$rel_col;
                            if ( $type === 'list' ) {
                                if ( in_array( $name, $translates ) ) {
                                    $rel_value = $app->translate( $rel_value );
                                }
                            }
                            $rel_value = $type === 'list' 
                                       ? self::trim_to( $rel_value, 22 )
                                       : $rel_value;
                            $relation_vars[] = $rel_value;
                        }
                    }
                }
            }
            $vars[ $name ] = $relation_vars;
        }
        $vars['_permalink'] = '';
        if ( $permalink = $app->get_permalink( $obj ) ) {
            $vars['_permalink'] = $permalink;
        }
        if ( $obj->has_column( 'rev_type' ) ) {
            $vars['rev_type'] = $obj->rev_type;
        }
        if ( $user = $app->user() ) {
            if ( isset( $column_defs['status'] ) ) {
                $vars['_max_status']
                    = $app->max_status( $user, $obj->_model, $obj->workspace );
            }
        }
        return $vars;
    }

    public static function trim_to ( $str, $num ) {
        $app = Prototype::get_instance();
        $ctx = $app->ctx;
        return $ctx->modifier_truncate( $str, "{$num}+...", $ctx );
    }

    public static function sort_by_order ( &$registries, $default = 50, $widget = false ) {
        $registries_all = [];
        foreach ( $registries as $key => $registry ) {
            $registry['key'] = $key;
            $order = isset( $registry['order'] ) ? $registry['order'] : $default;
            $item_by_order = isset( $registries_all[ $order ] ) ? $registries_all[ $order ] : [];
            $item_by_order[] = $registry;
            $registries_all[ $order ] = $item_by_order;
        }
        ksort( $registries_all );
        if ( $widget ) {
            $ordered = [];
            foreach ( $registries_all as $appWidget ) {
                $appWidget = $appWidget[0];
                $key = $appWidget['key'];
                unset( $appWidget['key'] );
                $ordered[ $key ] = $appWidget;
            }
            $registries = $ordered;
            return $registries;
        }
        $registries = [];
        foreach ( $registries_all as $registry_by_order ) {
            foreach ( $registry_by_order as $r ) {
                $registries[] = $r;
            }
        }
        return $registries;
    }

    public static function trim_image ( $file, $x, $y, $w, $h ) {
        $extension = strtolower( pathinfo( $file )['extension'] );
        $res = false;
        $meth = 'imagejpeg';
        switch ( $extension ) {
            case 'jpg':
            case 'jpeg':
                $resource = imagecreatefromjpeg( $file );
                self::crop_image( $resource, $x, $y, $w, $h );
                break;
            case 'png':
                $resource = imagecreatefrompng( $file );
                $meth = 'imagepng';
                self::crop_image( $resource, $x, $y, $w, $h );
                break;
            case 'gif':
                $resource = imagecreatefromgif( $file );
                $meth = 'imagegif';
                break;
        }
        self::crop_image( $resource, $x, $y, $w, $h );
        rename( $file, "{$file}.bk" );
        if ( $meth( $resource, $file ) ) {
            unlink( "{$file}.bk" );
            $res = true;
        }
        return $res;
    }

    public static function crop_image ( &$resource, $x, $y, $w, $h ) {
        $resource = imagecrop( $resource, array(
            'x'      => $x,
            'y'      => $y,
            'width'  => $w,
            'height' => $h,
        ) );
    }

    public static function send_mail ( $to, $subject, $body, $headers, &$error = '' ) {
        $app = Prototype::get_instance();
        $from = isset( $headers['From'] )
            ? $headers['From'] : $app->get_config( 'system_email' );
        if ( strpos( $to, ',' ) !== false ) {
            $to = preg_split( '/\s*,\s*/', $to );
        }
        if ( is_array( $to ) ) {
            foreach ( $to as $addr ) {
                if (!$app->is_valid_email( $addr, $error ) ) {
                    return false;
                }
            }
            $to = implode( ',', $to );
        } else {
            if (!$app->is_valid_email( $to, $error ) ) {
                return false;
            }
        }
        if (! $to || ! $from ||! $subject ) {
            $error = $app->translate( 'To, From and subject are required.' );
            return false;
        }
        if (!$app->is_valid_email( $from, $error ) ) {
            return false;
        }
        mb_internal_encoding( $app->encoding );
        if ( $app->mail_language ) {
            mb_language( $app->mail_language );
        }
        unset( $headers['From'] );
        $from = self::encode_mimeheader( $from );
        $options = "From: {$from}\r\n";
        foreach ( $headers as $key => $value ) {
            $value = self::encode_mimeheader( $value );
            $key = ucwords( $key );
            if ( $key == 'Cc' || $key == 'Bcc' ) {
                $addrs = [];
                if ( is_array( $value ) || strpos( $value, ',' ) !== false ) {
                    if (! is_array( $value ) ) {
                        $value = preg_split( '/\s*,\s*/', $value );
                    }
                    foreach ( $value as $addr ) {
                        if ( $app->is_valid_email( $addr, $error ) ) {
                            $addrs[] = $addr;
                        }
                    }
                } else {
                    if ( $app->is_valid_email( $value, $error ) ) {
                        $addrs[] = $value;
                    }
                }
                if (! empty( $addrs ) ) {
                    $value = implode( ',', $addrs );
                    $options .= "{$key}: {$value}\r\n";
                }
            } else {
                $options .= "{$key}: {$value}\r\n";
            }
        }
        $additional = $app->mail_return_path ? '-f' . $app->mail_return_path : null;
        return mb_send_mail( $to, $subject, $body, $options, $additional );
    }

    public static function send_multipart_mail ( $to, $subject, $body, $headers,
                                                 $files = [], &$error = '' ) {
        $app = Prototype::get_instance();
        $content_type = isset( $headers['Content-Type'] ) ? $headers['Content-Type'] : 'text/plain';
        $boundary = '__BOUNDARY__' . md5( rand() );
        $headers['Content-Type'] = "multipart/mixed;boundary=\"{$boundary}\"";
        $charset = $app->mail_encording ? strtoupper( $app->mail_encording ) : 'ISO-2022-JP';
        $text = $body;
        $body = "--{$boundary}\n";
        $body .= "Content-Type: {$content_type}; charset=\"{$charset}\"\n\n";
        $body .= $text . "\n";
        $body .= "--{$boundary}\n";
        $existing_files = [];
        foreach ( $files as $file ) {
            if ( is_object( $file ) ) { // Session
                $upload_dir = $app->upload_dir();
                $file_path = $upload_dir . DS . $file->value;
                file_put_contents( $file_path, $file->data );
                $file = $file_path;
            }
            if ( file_exists( $file ) ) {
                $existing_files[] = $file;
            }
        }
        $counter = 0;
        foreach ( $existing_files as $file ) {
            $file_name = self::encode_mimeheader( basename( $file ) );
            $body .= "Content-Type: application/octet-stream; name=\"{$file_name}\"\n";
            $body .= "Content-Disposition: attachment; filename=\"{$file_name}\"\n";
            $body .= "Content-Transfer-Encoding: base64\n";
            $body .= "\n";
            $body .= chunk_split( base64_encode( file_get_contents( $file ) ) );
            $counter++;
            $body .= $counter == count( $existing_files ) ? "--{$boundary}--\n" : "--{$boundary}\n";
        }
        return self::send_mail( $to, $subject, $body, $headers, $error );
    }

    public static function encode_mimeheader ( &$value ) {
        $app = Prototype::get_instance();
        if ( strpos( $value, '<' ) !== false && strpos( $value, '>' ) !== false
            && preg_match( '/(^.*?)<(.*?)>$/', $value, $matches ) ) {
            list( $addr, $value ) = [ $matches[2], $matches[1] ]; 
            if ( $app->mail_encording ) {
                $value = mb_encode_mimeheader( $value, strtoupper( $app->mail_encording ) );
            } else {
                $value = mb_encode_mimeheader( $value );
            }
            $value = "{$value}<{$addr}>";
        } else {
            if ( $app->mail_encording ) {
                $value = mb_encode_mimeheader( $value, strtoupper( $app->mail_encording ) );
            } else {
                $value = mb_encode_mimeheader( $value );
            }
        }
        return $value;
    }

    public static function convert_breaks ( $str = '' ) {
        if (! $str ) return '';
        $app = Prototype::get_instance();
        $ctx = $app->ctx;
        $dom = $ctx->dom;
        if (! $dom ) $dom = new DomDocument();
        libxml_use_internal_errors( true );
        if (!$dom->loadHTML( mb_convert_encoding( $str, 'HTML-ENTITIES','utf-8' ),
            LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD|LIBXML_COMPACT ) )
            trigger_error( 'loadHTML failed!' );
        $str = mb_convert_encoding( $dom->saveHTML(),
                                        'utf-8', 'HTML-ENTITIES' );
        return $str;
    }

    public static function export_data ( $path, $mimetype = '' ) {
        $file_size = filesize( $path );
        $file_name = basename( $path );
        if (! $mimetype ) {
            $extension = isset( pathinfo( $path )['extension'] )
                       ? pathinfo( $path )['extension'] : '';
            $mimetype = self::get_mime_type( $extension );
        }
        header( "Content-Type: {$mimetype}" );
        header( "Content-Length: {$file_size}" );
        header( "Content-Disposition: attachment;"
            . " filename=\"{$file_name}\"" );
        header( 'Pragma: ' );
        while ( ob_get_level() ) {
            ob_end_flush();
        }
        flush();
        readfile( $path );
        // echo file_get_contents( $path );
    }

    public static function get_asset_class ( $extension ) {
        $app = Prototype::get_instance();
        $extension = strtolower( $extension );
        if (in_array($extension, $app->videos)) {
            return 'video';
        } elseif (in_array($extension, $app->images)) {
            return 'image';
        } elseif (in_array($extension, $app->audios)) {
            return 'audio';
        } elseif ($extension==='pdf') {
            return 'pdf';
        } else {
            return 'file';
        }
    }

    public static function get_field_html ( $app ) {
        $app->validate_magic( true );
        $id = (int) $app->param( 'id' );
        $model = $app->param( 'model' );
        $workspace_id = $app->param( 'workspace_id' );
        if ( $app->param( '_type' ) == 'questiontype' ) {
            $field_model = $app->param( '_type' );
        } else {
            $field_model = $app->param( '_type' ) && $app->param( '_type' ) == 'fieldtype'
                     ? 'fieldtype' : 'field';
        }
        $field = $app->db->model( $field_model )->load( $id );
        header( 'Content-type: application/json' );
        if (!$field ) {
            echo json_encode( ['status' => 404,
                               'message' => $app->translate( 'Field not found.' ) ] );
            exit();
        }
        if ( $field_model == 'questiontype' ) {
            echo json_encode( ['status' => 200,
                    'content' => $field->template, 'class' => $field->class ] );
            exit();
        }
        $field_label = $field->label;
        $field_content = $field->content;
        if (!$field_label || !$field_content ) {
            if ( $field_model == 'field' ) {
                $field_type = $field->fieldtype;
                if ( $field_type ) {
                    if (!$field_label ) $field_label = $field_type->label;
                    if (!$field_content ) $field_content = $field_type->content;
                }
            }
        }
        if (!$field_label && ! $field_content ) {
            echo json_encode( ['status' => 404,
                     'message' => $app->translate( 'Field HTML not specified.' ) ] );
            exit();
        }
        if ( $field_model == 'fieldtype' ) {
            echo json_encode( ['status' => 200,
                    'hide_label' => $field->hide_label,
                    'label' => $field_label, 'content' => $field_content ] );
            exit();
        }
        $ctx = $app->ctx;
        $param = [];
        $field_name = $field->name;
        if ( $field->translate ) {
            $field_name = $app->translate( $field_name );
        }
        $ctx->local_vars['field_name'] = $field_name;
        $ctx->local_vars['field_required'] = $field->required;
        $basename = $field->basename;
        $ctx->local_vars['field_basename'] = $basename;
        $prefix = $field->_colprefix;
        $values = $field->get_values();
        foreach ( $values as $key => $value ) {
            $key = preg_replace( "/^$prefix/", '', $key );
            $ctx->local_vars[ 'field__' . $key ] = $value;
        }
        $options = $field->options;
        if ( $options ) {
            $labels = $field->options_labels;
            $options = preg_split( '/\s*,\s*/', $options );
            $labels = $labels ? preg_split( '/\s*,\s*/', $labels ) : $options;
            $i = 0;
            $field_options = [];
            foreach ( $options as $option ) {
                $label = isset( $labels[ $i ] ) ? $labels[ $i ] : $option;
                $field_options[] = ['field_label' => $label, 'field_option' => $option ];
                $i++;
            }
            $ctx->local_vars['field_options'] = $field_options;
        }
        if ( $app->param( 'field__out' ) ) {
            $ctx->local_vars['field__out'] = 1;
        }
        $uniqueid = $app->magic();
        $ctx->local_vars['field__hide_label'] = $field->hide_label;
        $ctx->local_vars['field_uniqueid'] = $uniqueid;
        if (! $field->hide_label ) {
            $field_label = $app->tmpl_markup === 'mt' ? $ctx->build( $field_label )
                                                      : $app->build( $field_label, $ctx );
            $ctx->local_vars['field_label_html'] = $field_label;
            $field_label = $app->build_page( 'field' . DS . 'label.tmpl', $param, false );
            $ctx->local_vars['field_label_html'] = $field_label;
        }
        $field_content = $app->tmpl_markup === 'mt' ? $ctx->build( $field_content )
                                                  : $app->build( $field_content, $ctx );
        $ctx->local_vars['field_content_html'] = $field_content;
        $field_content = $app->build_page( 'field' . DS . 'content.tmpl', $param, false );
        $ctx->local_vars['field_content_html'] = $field_content;
        $html = $app->build_page( 'field' . DS . 'wrapper.tmpl', $param, false );
        self::add_id_to_field( $html, $uniqueid, $basename );
        if (!$app->param( 'field__out' ) ) {
            $html = "<div id=\"field-{$basename}-wrapper\">{$html}</div>";
            $html .= $app->build_page( 'field' . DS . 'footer.tmpl', $param, false );
        }
        echo json_encode( ['html' => $html, 'status' => 200,
                           'basename' => $basename ] );
        exit();
    }

    public static function unique_filename ( $path, $counter = 1, $connector = '_' ) {
        $pathinfo = pathinfo( $path );
        $ext = isset( $pathinfo['extension'] ) ? $pathinfo['extension'] : '';
        $filename = $pathinfo['filename'];
        $filename = "{$filename}{$connector}{$counter}";
        if ( $ext ) $filename .= ".{$ext}";
        $_path = dirname( $path ) . DS . $filename;
        if ( file_exists( $_path ) ) {
            $counter++;
            return self::unique_filename( $path, $counter, $connector );
        }
        return $_path;
    }

    public static function remove_exif ( $file ) {
        $imginfo = @getimagesize( $file );
        if (! $imginfo ) return false;
        $pixel = $imginfo[0] > $imginfo[1] ? $imginfo[0] : $imginfo[1];
        $newfile = self::make_thumbnail( $file, $pixel, 'auto', 100 );
        if ( $newfile ) {
            copy( $newfile, $file );
            return true;
        }
        return false;
    }

    public static function fix_orientation ( $file ) {
        $app = Prototype::get_instance();
        if (! function_exists( 'exif_read_data' ) ) {
            if ( $app->remove_exif ) {
                self::remove_exif( $file );
            }
            return false;
        }
        $app->logging = false;
        $error_reporting = ini_get( 'error_reporting' ); 
        error_reporting(0);
        $exif = @exif_read_data( $file );
        if ( $exif === false ) {
            return false;
        }
        $imginfo = @getimagesize( $file );
        if (! $imginfo ) return false;
        if (! isset( $exif['Orientation'] ) ) return false;
        $orientation = (int)@$exif['Orientation'];
        if ( $orientation < 2 || $orientation > 8 ) {
            if ( $app->remove_exif ) {
                self::remove_exif( $file );
            }
            error_reporting( $error_reporting );
            $app->logging = $logging;
            return false;
        }
        $upload_dir = $app->upload_dir();
        $tmpfile = $upload_dir . basename( $file );
        copy( $file, $tmpfile );
        try {
            ini_set( 'memory_limit', -1 );
            $imagine = new \Imagine\Gd\Imagine();
            $lib_dir = LIB_DIR . 'Imagine' . DS;
            $reader = $lib_dir . 'Image' . DS . 'Metadata' . DS . 'ExifMetadataReader.php';
            require_once( $reader );
            $imagine->setMetadataReader( new Imagine\Image\Metadata\ExifMetadataReader() );
            $rotator = $lib_dir . 'Filter' . DS . 'Basic' . DS . 'Autorotate.php';
            require_once( $rotator );
            $autorotate = new Imagine\Filter\Basic\Autorotate();
            $autorotate->apply( $imagine->open( $tmpfile ) )->save( $tmpfile );
            copy( $tmpfile, $file );
            // self::remove_dir( $upload_dir );
            error_reporting( $error_reporting );
            $app->logging = $logging;
            return true;
        } catch ( Imagine\Exception\Exception $e ) {
            error_reporting( $error_reporting );
            $app->logging = $logging;
            return false;
        }
    }

    public static function make_thumbnail ( $file, $size = 480, $type = 'auto',
                                            $quality = 70, $square = false, $error = null ) {
        $app = Prototype::get_instance();
        if ( $type == 'auto' ) {
            $type = isset( pathinfo( $file )['extension'] )
                           ? pathinfo( $file )['extension'] : '';
            $type = strtolower( $type );
        }
        if ( $type == 'jpeg' ) $type = 'jpg';
        if (! $type ) $type = 'jpg';
        if ( $type == 'png' && $quality >= 10 ) {
            $quality *= 0.1;
            $quality = (int) $quality;
            if ( $quality > 9 ) {
                $quality = 0;
            }
        } else if ( $type == 'png' && $quality == 0 ) {
            $quality = 1;
        }
        $image_quality = '';
        if ( $type == 'png' ) {
            $image_quality = 'png_compression_level';
        } else if ( $type == 'jpg' ) {
            $image_quality = 'jpeg_quality';
        }
        $logging = $app->logging;
        $app->logging = false;
        $error_reporting = ini_get( 'error_reporting' ); 
        error_reporting(0);
        $upload_dir = $app->upload_dir();
        $imagine = new \Imagine\Gd\Imagine();
        $image = $imagine->open( $file );
        if ( $square ) {
            $mode = Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
            $thumbnail = $image->thumbnail( new Imagine\Image\Box( $size, $size ), $mode );
        } else {
            $thumbnail = $image->thumbnail( new Imagine\Image\Box( $size, $size ) );
        }
        error_reporting( $error_reporting );
        $app->logging = $logging;
        $filename = basename( $file );
        if ( $extension ) {
            $filename = preg_replace( "/\.$extension/i", '', $filename );
        }
        $outfile = $upload_dir . DS . basename( "{$filename}.{$type}" );
        $option = $image_quality ? [ $image_quality => $quality ] : null;
        try {
            $thumbnail->save( $outfile, $option );
        } catch ( Exception $e ) {
            $error = $e->getMessage();
            return;
        }
        if ( file_exists( $outfile ) ) return $outfile;
    }

    public static function get_extension ( $path ) {
        if ( strpos( $path, '.' ) === false ) {
            return '';
        }
        $parts = explode( '.', $path );
        $extIndex = count( $parts ) - 1;
        $extension = strtolower( @$parts[ $extIndex ] );
        return $extension;
    }

    public static function upload_check ( $extra, $name = 'files',
        $json = true, &$error = '' ) {
        $app = Prototype::get_instance();
        if (! $extra ) {
            if ( $app->upload_size_limit || $app->upload_max_pixel ) {
                $extra = implode( ':', [
                    $app->upload_size_limit,
                    $app->upload_max_pixel,
                    $app->upload_image_option ] );
            }
        }
        $settings = explode( ':', $extra );
        $filename = $json ? $_FILES['files']['name'] : $name;
        if ( is_array( $filename ) ) $filename = $filename[0];
        $ext = preg_replace("/^.*\.([^\.].*$)/is", '$1', $filename );
        $denied_exts = $app->denied_exts;
        $denied_exts = preg_split( '/\s*,\s*/', $denied_exts );
        if ( in_array( $ext, $denied_exts ) ) {
            if ( $json ) {
                $app->json_error(
                    'The file (%s) that you uploaded is not allowed.',
                        basename( $filename ) );
            } else {
                $error = $app->translate( 'The file (%s) that you uploaded is not allowed.',
                        basename( $filename ) );
                return null;
            }
        }
        $tmp_name = $json ? $_FILES['files']['tmp_name'] : $name;
        if ( is_array( $tmp_name ) ) $tmp_name = $tmp_name[0];
        $filesize = $json ? $_FILES[ $name ]['size'] : filesize( $tmp_name );
        $is_array = false;
        if ( is_array( $filesize ) ) {
            $filesize = $filesize[0];
            $is_array = true;
        }
        if (! $filesize ) {
            if ( $json ) {
                $app->json_error(
                    'The upload that 0 byte size file is not allowed.' );
            } else {
                $error = $app->translate(
                    'The upload that 0 byte size file is not allowed.' );
                return null;
            }
        }
        if (! $json && in_array( $ext, $app->images ) ) {
            if ( $app->auto_orient ) {
                self::fix_orientation( $tmp_name );
            } else if ( $app->remove_exif ) {
                self::remove_exif( $tmp_name );
            }
        }
        if (! $extra && ! $app->upload_size_limit ) return 1;
        if (! $extra ) {
            $extra = $app->upload_size_limit;
        }
        list( $sizelimit, $pixel, $extra, $type ) = array_pad( explode( ':', $extra ), 4, null );
        if (! $sizelimit && $app->upload_size_limit ) {
            $sizelimit = $app->upload_size_limit;
        }
        $ext = preg_replace("/^.*\.([^\.].*$)/is", '$1', $filename );
        $type_label = ( $type === 'pdf' ) ? 'PDF' : ucfirst( $type );
        if ( $type ) {
            $type .= 's';
            $extensions = ( $type === 'pdfs' ) ? ['pdf'] : $app->$type;
            if (! in_array( $ext, $extensions ) ) {
                if ( $json ) {
                    $app->json_error( 'The file must be an %s.',
                                  $app->translate( $type_label ) );
                } else {
                    $error = $app->translate( 'The file must be an %s.',
                                  $app->translate( $type_label ) );
                    return null;
                }
            }
        }
        $resized = null;
        $pixel = (int) $pixel;
        if ( $pixel && in_array( $ext, $app->images ) ) {
            $size = getimagesize( $tmp_name );
            if ( $size && ( $size[0] > $pixel || $size[1] > $pixel ) ) {
                if ( $extra == 'resize' ) {
                    $resized = self::make_thumbnail( $tmp_name, $pixel, $ext,
                                                     $app->image_quality );
                    if (! $resized ) {
                        if ( $json ) {
                            $app->json_error( 'Failed to resize image.' );
                        } else {
                            $error = $app->translate( 'Failed to resize image.' );
                            return null;
                        }
                    } else {
                        file_put_contents( $tmp_name, file_get_contents( $resized ) );
                        if ( $json ) {
                            if ( $is_array ) {
                                $_FILES['files']['size'][0] = filesize( $resized );
                            } else {
                                $_FILES['files']['size'] = filesize( $resized );
                            }
                        }
                        self::remove_dir( dirname( $resized ) );
                        $resized = 'resized';
                        $filesize = filesize( $tmp_name );
                    }
                } else {
                    if ( $json ) {
                        $app->json_error( 'The file you uploaded is too large.' );
                    } else {
                        $error = $app->translate( 'The file you uploaded is too large.' );
                        return null;
                    }
                }
            }
        }
        if ( $sizelimit && ( $filesize > $sizelimit ) ) {
            $sizelimit = $app->ctx->modifier_format_size( $sizelimit, 1, $app->ctx );
            if ( $json ) {
                $app->json_error( 'The file you uploaded is too large(The file must be %s or less).', $sizelimit );
            } else {
                $error = $app->translate( 'The file you uploaded is too large(The file must be %s or less).', $sizelimit );
                return null;
            }
        }
        return $resized;
    }

    public static function get_mime_type ( $extension, $default = '' ) {
        if ( strpos( $extension, DS ) !== false ) {
            $extension = self::get_extension( $extension );
        }
        $extension = strtolower( $extension );
        $extension = ltrim( $extension, '.' );
        if ( isset( $_SERVER[ 'HTTP_USER_AGENT' ] ) ) {
            if ( strpos( $_SERVER[ 'HTTP_USER_AGENT' ], 'DoCoMo/2.0' ) === 0 ) {
                if ( $extension === 'html' ) {
                    return 'application/xhtml+xml';
                }
            }
        }
        $mime_type = array (
            'css'     => 'text/css',
            'html'    => 'text/html',
            'php'     => 'text/html',
            'mtml'    => 'text/html',
            'xhtml'   => 'application/xhtml+xml',
            'htm'     => 'text/html',
            'txt'     => 'text/plain',
            'rtx'     => 'text/richtext',
            'tsv'     => 'text/tab-separated-values',
            'csv'     => 'text/csv',
            'hdml'    => 'text/x-hdml; charset=Shift_JIS',
            'xml'     => 'application/xml',
            'atom'    => 'application/atom+xml',
            'rss'     => 'application/rss+xml',
            'rdf'     => 'application/rdf+xml',
            'xsl'     => 'text/xsl',
            'mpeg'    => 'video/mpeg',
            'mpg'     => 'video/mpeg',
            'mpe'     => 'video/mpeg',
            'avi'     => 'video/x-msvideo',
            'movie'   => 'video/x-sgi-movie',
            'mov'     => 'video/quicktime',
            'qt'      => 'video/quicktime',
            'ice'     => 'x-conference/x-cooltalk',
            'svr'     => 'x-world/x-svr',
            'vrml'    => 'x-world/x-vrml',
            'wrl'     => 'x-world/x-vrml',
            'vrt'     => 'x-world/x-vrt',
            'spl'     => 'application/futuresplash',
            'hqx'     => 'application/mac-binhex40',
            'doc'     => 'application/msword',
            'pdf'     => 'application/pdf',
            'ai'      => 'application/postscript',
            'eps'     => 'application/postscript',
            'ps'      => 'application/postscript',
            'ppt'     => 'application/vnd.ms-powerpoint',
            'rtf'     => 'application/rtf',
            'dcr'     => 'application/x-director',
            'dir'     => 'application/x-director',
            'dxr'     => 'application/x-director',
            'js'      => 'application/javascript',
            'dvi'     => 'application/x-dvi',
            'gtar'    => 'application/x-gtar',
            'gzip'    => 'application/x-gzip',
            'latex'   => 'application/x-latex',
            'lzh'     => 'application/x-lha',
            'swf'     => 'application/x-shockwave-flash',
            'sit'     => 'application/x-stuffit',
            'tar'     => 'application/x-tar',
            'tcl'     => 'application/x-tcl',
            'tex'     => 'application/x-texinfo',
            'texinfo' => 'application/x-texinfo',
            'texi'    => 'application/x-texi',
            'src'     => 'application/x-wais-source',
            'zip'     => 'application/zip',
            'au'      => 'audio/basic',
            'snd'     => 'audio/basic',
            'midi'    => 'audio/midi',
            'mid'     => 'audio/midi',
            'kar'     => 'audio/midi',
            'mpga'    => 'audio/mpeg',
            'mp2'     => 'audio/mpeg',
            'mp3'     => 'audio/mpeg',
            'ra'      => 'audio/x-pn-realaudio',
            'ram'     => 'audio/x-pn-realaudio',
            'rm'      => 'audio/x-pn-realaudio',
            'rpm'     => 'audio/x-pn-realaudio-plugin',
            'wav'     => 'audio/x-wav',
            'bmp'     => 'image/x-ms-bmp',
            'gif'     => 'image/gif',
            'jpeg'    => 'image/jpeg',
            'jpg'     => 'image/jpeg',
            'jpe'     => 'image/jpeg',
            'png'     => 'image/png',
            'tiff'    => 'image/tiff',
            'tif'     => 'image/tiff',
            'pnm'     => 'image/x-portable-anymap',
            'ras'     => 'image/x-cmu-raster',
            'pnm'     => 'image/x-portable-anymap',
            'pbm'     => 'image/x-portable-bitmap',
            'pgm'     => 'image/x-portable-graymap',
            'ppm'     => 'image/x-portable-pixmap',
            'svg'     => 'image/svg+xml',
            'rgb'     => 'image/x-rgb',
            'xbm'     => 'image/x-xbitmap',
            'xls'     => 'application/vnd.ms-excel',
            'xpm'     => 'image/x-pixmap',
            'xwd'     => 'image/x-xwindowdump',
            'ico'     => 'image/vnd.microsoft.icon',
            'docx'    => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pptx'    => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'xlsx'    => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'json'    => 'application/json',
        );
        if ( isset( $mime_type[ $extension ] ) ) {
            return $mime_type[ $extension ];
        }
        return $default ? $default : 'text/plain';
    }

    public static function file_find ( $dir, &$files = [], &$dirs = [], $hidden = false ) {
        $iterator = new RecursiveDirectoryIterator( $dir );
        $iterator = new RecursiveIteratorIterator( $iterator );
        $list = [];
        foreach ( $iterator as $fileinfo ) {
            $path = $fileinfo->getPathname();
            $list[] = $path;
            $name = $fileinfo->getBasename();
            if (! $hidden && strpos( $name, '..' ) === 0 ) continue;
            if ( $fileinfo->isFile() ) {
                $files[] = $path;
            } else if ( $fileinfo->isDir() ) {
                $dirs[] = $path;
            }
        }
        return $list;
    }

}