<?php
class urlinfo extends PADOBaseModel {

    public $_fmgr = null;

    function remove ( $force = false ) {
        if ( $this->file_path ) {
            $file_path = $this->file_path;
            $file_path = str_replace( '/', DS, $file_path );
            if (! $fmgr = $this->_fmgr ) {
                $app = Prototype::get_instance();
                $fmgr = $app->fmgr;
                $this->_fmgr = $fmgr;
            }
            if ( $fmgr->exists( $file_path ) && !$fmgr->is_dir( $file_path ) ) {
                $fmgr->unlink( $file_path );
            }
        }
        if (! $force ) {
            if ( $this->was_published ) {
                $this->delete_flag(1);
                $this->is_published(0);
                $this->publish_file(0);
                return parent::save();
            }
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
        unset( $this->_fmgr );
        /*
        unset( $this->_original );
        unset( $this->_relations );
        unset( $this->_meta );
        unset( $this->_insert );
        */
        return parent::save();
    }

    function pre_load ( &$terms = [], &$args = [], &$cols = '*',
        &$extra = '', $ex_vals = [], $include_deleted = false ) {
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

    function count ( $terms = [], $args = [], $cols = '*', $extra = '', $ex_vals = [],
        $include_deleted = false ) {
        if ( is_numeric( $terms ) ) {
            $include_deleted = true;
        }
        $this->pre_load( $terms, $args, $cols, $extra, $ex_vals, $include_deleted );
        return parent::count( $terms, $args, $cols, $extra, $ex_vals );
    }

    function load ( $terms = [], $args = [], $cols = '*', $extra = '', $ex_vals = [],
        $include_deleted = false ) {
        if ( is_numeric( $terms ) ) {
            $include_deleted = true;
        }
        $this->pre_load( $terms, $args, $cols, $extra, $ex_vals, $include_deleted );
        return parent::load( $terms, $args, $cols, $extra, $ex_vals );
    }
}