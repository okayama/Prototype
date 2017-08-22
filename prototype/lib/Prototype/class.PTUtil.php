<?php

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

    public static function current_ts () {
        return date( 'YmdHis' );
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

    public static function remove_empty_dirs ( $dirs ) {
        $app = Prototype::get_instance();
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
        if (! $basename ) $basename = $obj->_model;
        $basename = strtolower( $basename );
        $basename = preg_replace( "/[^a-z0-9]/", ' ', $basename );
        $basename = preg_replace( "/\s{1,}/", ' ', $basename );
        $basename = str_replace( ' ', '_', $basename );
        $basename = trim( $basename, '_' );
        $basename = mb_substr( $basename, 0, 30, $app->db->charset );
        if ( $basename && strpos( $basename, '_' ) !== false ) {
            $basename = preg_replace( '/_[^_]*$/', '', $basename );
        }
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
                    if ( $len > 255 ) {
                        $diff = $len - 255;
                        $basename = mb_substr(
                            $basename, 0, 255 - $diff, $app->db->charset );
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
        if ( $scale ) {
            if ( $width ) $width = round( $width * $scale / 100 );
            if ( $height ) $height = round( $height * $scale / 100 );
        }
        $modified_on = $obj->modified_on;
        $modified_on = $obj->db2ts( $modified_on );
        if (! $assetproperty ) $assetproperty = $app->get_assetproperty( $obj, 'file' );
        $basename = $assetproperty['basename'];
        $extension = $assetproperty['extension'];
        $thumbnail_basename = "thumb";
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
            'object_id' => $id, 'model' => 'asset',
            'key' => 'thumbnail', 'kind' => 'file', 'value' => $thumbnail_basename ] );
        $uploaded = 0;
        if ( $metadata->text ) {
            $thumb_props = json_decode( $metadata->text, true );
            $uploaded = $thumb_props['uploaded'];
            $uploaded = $obj->db2ts( $uploaded );
        }
        if (! $metadata->id || $modified_on > $uploaded ) {
            $core_tags = $app->core_tags;
            $ctx->stash( 'current_context', 'asset' );
            $ctx->stash( 'asset', $obj );
            $args = ['model' => 'asset', 'name' => 'file', 'id' => $id ];
            $args['width'] = $width;
            $args['height'] = $height;
            $args['square'] = $square;
            $args['scale'] = $scale;
            $meta = [];
            $thumb = $core_tags->hdlr_assetthumbnail( $args, $ctx, $meta );
            $t_property = $assetproperty;
            $t_property['file_name'] = $thumbnail_name;
            $t_property['basename'] = $thumbnail_basename;
            $t_property['file_size'] = strlen( bin2hex( $thumb ) ) / 2;
            $t_property['image_width'] = $meta['image_width'];
            $t_property['image_height'] = $meta['image_height'];
            $t_property['uploaded'] = date( 'Y-m-d H:i:s' );
            $t_property['user_id'] = $app->user()->id;
            $metadata->data( $thumb );
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
        if ( $obj->status != 4 ) {
            if ( file_exists( $asset_path ) ) {
                unlink( $asset_path );
                $info->is_published( 0 );
            }
        } else {
            $publish = false;
            if ( file_exists( $asset_path ) ) {
                $comp = base64_encode( file_get_contents( $asset_path ) );
                $data = base64_encode( $thumb );
                if ( $comp != $data ) {
                    $publish = true;
                }
            } else {
                $publish = true;
            }
            $info->is_published( 1 );
        }
        if ( $publish ) {
            self::mkpath( dirname( $asset_path ) );
            file_put_contents( $asset_path, $thumb );
        }
        $info->save();
        return $asset_url;
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