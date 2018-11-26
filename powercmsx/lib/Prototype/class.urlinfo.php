<?php
class urlinfo extends PADOBaseModel {

    public $fmgr = null;

    function remove ( $force = false ) {
        if ( $this->file_path ) {
            $file_path = $this->file_path;
            $file_path = str_replace( '/', DS, $file_path );
            if (! $fmgr = $this->fmgr ) {
                $app = Prototype::get_instance();
                $fmgr = $app->fmgr;
                $this->fmgr = $fmgr;
            }
            if ( $fmgr->exists( $file_path ) ) {
                $fmgr->unlink( $file_path );
            }
        }
        if (! $force ) {
            $this->delete_flag(1);
            $this->is_published(0);
            $this->publish_file(0);
            return parent::save();
        }
        return parent::remove();
    }

    function save () {
        $this->filemtime( time() );
        $this->dirname( dirname( $this->url ) . '/' );
        if (! $this->meta_id ) {
            $this->meta_id(0);
        }
        if (! $this->publish_file ) {
            $this->publish_file(0);
        }
        $this->delete_flag(0);
        return parent::save();
    }

    function pre_load ( &$terms = [], &$args = [], &$cols = '*',
        &$extra = '', $include_deleted = false ) {
        if ( ( is_array( $terms ) && isset( $terms['delete_flag'] ) ) 
            || strpos( $extra, 'delete_flag' ) !== false ) {
            return;
        }
        if (! $include_deleted ) {
            $extra = ' AND urlinfo_delete_flag=0 ' . $extra;
        } else {
            $extra = ' AND urlinfo_delete_flag IN (0,1) ' . $extra;
        }
    }

    function count ( $terms = [], $args = [], $cols = '*', $extra = '',
        $include_deleted = false ) {
        $this->pre_load( $terms, $args, $cols, $extra, $include_deleted );
        return parent::count( $terms, $args, $cols, $extra );
    }

    function load ( $terms = [], $args = [], $cols = '*', $extra = '',
        $include_deleted = false ) {
        $this->pre_load( $terms, $args, $cols, $extra, $include_deleted );
        return parent::load( $terms, $args, $cols, $extra );
    }
}