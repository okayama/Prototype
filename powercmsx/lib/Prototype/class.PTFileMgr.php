<?php

class PTFileMgr {

    public function put ( $path, $data, $size = false ) {
        if (! is_dir( dirname( $path ) ) ) {
            $this->mkpath( dirname( $path ) );
        }
        $size = file_put_contents( "{$path}.new", $data );
        if ( $size !== false ) {
            if (! rename( "{$path}.new", $path ) ) {
                unlink( "{$path}.new" );
                $size = false;
            }
        }
        if ( $size === false ) {
            trigger_error( "Cannot write file'{$path}'!" );
        }
        return $size;
    }

    public function get ( $path ) {
        return file_get_contents( $path );
    }

    public function delete ( $path ) {
        $this->unlink( $path );
    }

    public function unlink ( $path ) {
        if ( is_dir( $path ) ) {
            return $this->rmdir( $path );
        }
        return unlink( $path );
    }

    public function filesize ( $path ) {
        return filesize( $path );
    }

    public function copy ( $from, $to ) {
        if (! is_dir( dirname( $to ) ) ) {
            $this->mkpath( dirname( $to ) );
        }
        if ( is_dir( $from ) ) {
            return $this->copy_recursive( $from, $to );
        }
        return copy( $from, $to );
    }

    public function copy_recursive ( $from, $to ) {
        if ( is_dir( $from ) ) {
            if (! is_dir( $to ) ) {
                $this->mkpath( dirname( $to ) );
            }
            $dir = dir( $from );
            while ( false !== ( $item = $dir->read() ) ) {
                if ( $item != '.' && $item != '..' ) {
                    return $this->copy_recursive( $from . DS . $item, $to . DS . $item );
                }
            }
            $dir->close();
        } else if ( file_exists( $from ) ) {
            return $this->copy( $from, $to );
        }
    }

    public function rename ( $from, $to ) {
        if (! is_dir( dirname( $to ) ) ) {
            $this->mkpath( dirname( $to ) );
        }
        return rename( $from, $to );
    }

    public function exists ( $path ) {
        return file_exists( $path );
    }

    public function mkpath ( $path, $mode = 0777 ) {
        if ( is_dir( $path ) ) return true;
        return mkdir( $path, $mode, true );
    }

    public static function rmdir ( $dir, $children_only = false ) {
        require_once( 'class.PTUtil.php' );
        return PTUtil::remove_dir( $dir, $children_only );
    }

    public function remove_empty_dirs ( $dirs ) {
        require_once( 'class.PTUtil.php' );
        return PTUtil::remove_empty_dirs( $dirs );
    }

    public function file_find ( $dir, &$files = [], &$dirs = [] ) {
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