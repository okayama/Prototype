<?php
class PTPlugin {

    public $dictionary = [];
    public $path;

    function __construct () {
        $app = Prototype::get_instance();
        $path = debug_backtrace()[0]['file'];
        $this->path = $path;
        $locale = dirname( $path )
            . DS . 'locale' . DS . $app->language . '.json';
        if ( file_exists( $locale ) ) {
            $locale = json_decode( file_get_contents( $locale ), true );
            $this->dictionary[ $app->language ] = $locale;
        }
        $class_name = strtolower( get_class( $this ) );
        $app->components[ $class_name ] = $this;
        $app->ctx->components[ $class_name ] = $this;
        $app->ctx->include_paths[ dirname( $path ) . DS . 'tmpl' ] = true;
    }

    function translate ( $phrase, $params = '', $lang = null ) {
        $app = Prototype::get_instance();
        $lang = $lang ? $lang : $app->language;
        if (! $lang ) $lang = 'default';
        $dict = isset( $this->dictionary ) ? $this->dictionary : null;
        if ( $dict && isset( $dict[ $lang ] ) && isset( $dict[ $lang ][ $phrase ] ) )
             $phrase = $dict[ $lang ][ $phrase ];
        $phrase = is_string( $params )
            ? sprintf( $phrase, $params ) : vsprintf( $phrase, $params );
        return $phrase;
    }
}