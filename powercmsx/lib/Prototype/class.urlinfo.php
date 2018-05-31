<?php
class urlinfo extends PADOBaseModel {

    function remove () {
        if ( $this->file_path ) {
            $file_path = $this->file_path;
            $file_path = str_replace( '/', DS, $file_path );
            if ( file_exists( $file_path ) ) {
                unlink( $file_path );
            }
        }
        return parent::remove();
    }

}