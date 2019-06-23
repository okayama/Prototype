<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );

class Minifier extends PTPlugin {

    function __construct () {
        parent::__construct();
    }

    function hdlr_jsminifier ( $args, $content, $ctx, &$repeat ) {
        if ( isset( $content ) ) {
            $p = array(
                  "/[\r\n]+/",
                  "/^[\s\t]*\/\/.+$/m",
                  "/\/\*.+?\*\//s",
                  "/([\{\(\[,;=])\n+/",
                  "/[\s\t]*([\{\(\[,;=\+\*-<>\|\&\?\:!])[\s\t]*/",
                  "/\n\}/",
                  "/^[\s\t]+/m",
                  "/[\s\t]+$/m",
                  "/[\s\t]{2,}/",
                );
            $r = array ( "\n", "", "", "$1", "$1", "}", "", "", "", );
            do { $content = preg_replace( $p, $r, $content ); }
                while( $content != preg_replace( $p, $r, $content ) );
        }
        return $content;
    }

    function hdlr_cssminifier ( $args, $content, $ctx, &$repeat ) {
        if ( isset( $content ) ) {
            $content = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content );
            $content = str_replace( array( "\r\n", "\r", "\n", "\t", '  ', '    ', '    ' ),
                                    '', $content );
        }
        return $content;
    }

    function hdlr_htmlminifier ( $args, $content, $ctx, &$repeat ) {
        if ( isset( $content ) ) {
            require_once( LIB_DIR . 'Smarty' . DS . 'outputfilter.trimwhitespace.php' );
            $content = smarty_outputfilter_trimwhitespace( $content );
        }
        return $content;
    }
}