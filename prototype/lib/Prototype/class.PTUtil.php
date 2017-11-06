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
                rmdir( $dir );
                $does_act = true;
            }
        }
        return $does_act;
    }

    public static function make_basename ( $obj, $basename = '', $unique = false ) {
        $app = Prototype::get_instance();
        $basename_len = $app->basename_len;
        if (! $basename ) $basename = $obj->_model;
        $basename = strtolower( $basename );
        $basename = preg_replace( "/[^a-z0-9]/", ' ', $basename );
        $basename = preg_replace( "/\s{1,}/", ' ', $basename );
        $basename = str_replace( ' ', '_', $basename );
        $basename = trim( $basename, '_' );
        $basename = mb_substr( $basename, 0, $basename_len, $app->db->charset );
        if (! $basename ) $basename = $obj->_model;
        if ( $unique ) {
            $terms = [];
            if ( $obj->id ) {
                $terms['id'] = ['not' => (int)$obj->id ];
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
        $ctx = $app->ctx;
        $id = $obj->id;
        $width = isset( $args['width'] ) ? (int) $args['width'] : '';
        $height = isset( $args['height'] ) ? (int) $args['height'] : '';
        $square = isset( $args['square'] ) ? $args['square'] : false;
        $scale = isset( $args['scale'] ) ? (int) $args['scale'] : '';
        $model = isset( $args['model'] ) ? $args['model'] : $obj->_model;
        $name = isset( $args['name'] ) ? $args['name'] : 'file';
        if (! $obj->$name ) return;
        if ( $scale ) {
            if ( $width ) $width = round( $width * $scale / 100 );
            if ( $height ) $height = round( $height * $scale / 100 );
        }
        $modified_on = $obj->modified_on;
        $modified_on = $obj->db2ts( $modified_on );
        if (! $assetproperty ) $assetproperty = $app->get_assetproperty( $obj, $name );
        $basename = $assetproperty['basename'];
        $extension = $assetproperty['extension'];
        $thumbnail_basename = 'thumb';
        if ( $width && !$height ) {
            $thumbnail_basename .= "-{$width}xauto";
        } else if (!$width && $height ) {
            $thumbnail_basename .= "-autox{$height}";
        }
        if ( $square ) {
            $thumbnail_basename .= "-square";
        }
        $thumbnail_basename .= "-{$id}";
        $thumbnail_name = "{$thumbnail_basename}.{$extension}";
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
        $hash = '';
        if (! $metadata->id || $modified_on > $uploaded ) {
            $ctx->stash( 'current_context', $model );
            $ctx->stash( $model, $obj );
            $args = ['model' => 'asset', 'name' => 'file', 'id' => $id ];
            $args['width'] = $width;
            $args['height'] = $height;
            $args['square'] = $square;
            $args['scale'] = $scale;
            $meta = [];
            $upload_dir = $app->upload_dir();
            $file = $upload_dir . DS . $obj->file_name;
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
            $imagine = new \Imagine\Gd\Imagine();
            $image = $imagine->open( $file );
            $thumbnail = $image->thumbnail( new Imagine\Image\Box( $width, $height ) );
            $thumbnail->save( $upload_dir . DS . $thumbnail_name );
            $thumb = file_get_contents( $upload_dir . DS . $thumbnail_name );
            $t_property = $assetproperty;
            $t_property['file_name'] = $thumbnail_name;
            $t_property['basename'] = $thumbnail_basename;
            $t_property['file_size'] = strlen( bin2hex( $thumb ) ) / 2;
            $t_property['image_width'] = $meta['image_width'];
            $t_property['image_height'] = $meta['image_height'];
            $t_property['uploaded'] = date( 'Y-m-d H:i:s' );
            $t_property['user_id'] = $app->user()->id;
            $metadata->data( $thumb );
            $hash = md5( base64_encode( $thumb ) );
            $t_property = json_encode( $t_property );
            $metadata->text( $t_property );
            $metadata->save();
        }
        $thumb = $metadata->data;
        $info = $app->db->model( 'urlinfo' )->get_by_key( [
            'object_id' => $id, 'model' => 'asset', 'class' => 'file',
            'key' => 'thumbnail', 'meta_id' => $metadata->id,
            'workspace_id' => $obj->workspace_id ] );
        if ( $info->relative_path != $relative_path ) {
            if ( $info->asset_path &&
                ( $info->asset_path != $asset_path ) ) {
                if ( file_exists( $info->asset_path ) ) {
                    unlink( $info->asset_path );
                }
            }
            $info->set_values( [
                'relative_path' => $relative_path,
                'relative_url' => $relative_url,
                'file_path' => $asset_path,
                'url' => $asset_url,
                'mime_type' => $obj->mime_type,
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
        if ( $publish ) {
            self::mkpath( dirname( $asset_path ) );
            file_put_contents( $asset_path, $thumb );
        }
        if ( $hash ) {
            $info->md5( $hash );
        }
        $info->save();
        return $asset_url;
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
            $_fields[] = $field;
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
                    $zip->addFile( $pathname, $localpath );
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
                                $vars[ $key ] = self::trim_to( $vars[ $key ], 40 );
                                break;
                            case $prop === 'text_short':
                                if ( in_array( $key, $translates ) ) {
                                    $vars[ $key ] = $app->translate( $vars[ $key ] );
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
                    $meta_vars = json_decode( $meta->text, 'true' );
                    $url = $app->get_assetproperty( $obj, $col, 'url' );
                    $meta_vars['url'] = $url;
                    $vars['_icon_class'] = isset( $meta_vars['class'] )
                                         ? $meta_vars['class'] : '';
                    if (! $thumbnail && $vars['_icon_class'] == 'image' ) {
                        $icon = $app->admin_url
                              . '?__mode=get_thumbnail&square=1&id=' . $meta->id;
                        $vars['_icon'] = $icon;
                        $icon2 = $app->admin_url
                              . '?__mode=get_thumbnail&id=' . $meta->id;
                        $vars['_icon_large'] = $icon2;
                    }
                }
                if ( in_array( $col, $object_keys ) ) {
                    $vars[ $col ] = $meta_vars;
                }
            }
        }
        foreach ( $relations as $name => $to_obj ) {
            if ( $required && ! in_array( $name, $required ) ) continue;
            $rel_objs = $app->get_relations( $obj, $to_obj, $name );
            $relation_vars = [];
            if (! empty( $rel_objs ) ) {
                $rel_table = $app->get_table( $to_obj );
                if (! $rel_table ) continue;
                $prop = $list_properties[ $name ];
                $props = explode( ':', $prop );
                $rel_col = $props[2];
                if ( $rel_col === 'primary ' ) {
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
        return $vars;
    }

    public static function trim_to ( $str, $num ) {
        $app = Prototype::get_instance();
        $ctx = $app->ctx;
        return $ctx->modifier_truncate( $str, "{$num}+...", $ctx );
    }

    public static function sort_by_order ( &$registries, $default = 50 ) {
        $registries_all = [];
        foreach ( $registries as $key => $registry ) {
            $registry['key'] = $key;
            $order = isset( $registry['order'] ) ? $registry['order'] : $default;
            $item_by_order = isset( $registries_all[ $order ] ) ? $registries_all[ $order ] : [];
            $item_by_order[] = $registry;
            $registries_all[ $order ] = $item_by_order;
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

    public static function send_mail ( $to, $subject, $body, $headers, &$error ) {
        $app = Prototype::get_instance();
        mb_internal_encoding( $app->encoding );
        $from = isset( $headers['From'] )
            ? $headers['From'] : $app->get_config( 'system_email' );
        if (! $to || ! $from ||! $subject ) {
            $error = $app->translate( 'To, From and subject are required.' );
            return false;
        }
        if (!$app->is_valid_email( $from, $error ) ) {
            return false;
        }
        if (!$app->is_valid_email( $to, $error ) ) {
            return false;
        }
        unset( $headers['From'] );
        $options = "From: {$from}\r\n";
        foreach ( $headers as $key => $value ) {
            $options .= "{$key}: {$value}\r\n";
        }
        return mb_send_mail( $to, $subject, $body, $options );
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
        if ( $mimetype ) {
            $extension = isset( pathinfo( $path )['extension'] )
                       ? pathinfo( $path )['extension'] : '';
            $mimetype = self::get_mime_type( $extension );
        }
        header( "Content-Type: {$mimetype}" );
        header( "Content-Length: {$file_size}" );
        header( "Content-Disposition: attachment;"
            . " filename=\"{$file_name}\"" );
        header( 'Pragma: ' );
        // TODO buffer
        echo file_get_contents( $path );
    }

    public static function get_mime_type ( $extension, $default = '' ) {
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

    public static function file_find ( $dir, &$files = [], &$dirs = [] ) {
        $iterator = new RecursiveDirectoryIterator( $dir );
        $iterator = new RecursiveIteratorIterator( $iterator );
        $list = [];
        foreach ( $iterator as $fileinfo ) {
            $path = $fileinfo->getPathname();
            $list[] = $path;
            $name = $fileinfo->getBasename();
            if ( strpos( $name, '.' ) === 0 ) continue;
            if ( $fileinfo->isFile() ) {
                $files[] = $path;
            } else if ( $fileinfo->isDir() ) {
                $dirs[] = $path;
            }
        }
        return $list;
    }

}