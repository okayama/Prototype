<?php
class PTPlugin {

    public $dictionary = [];

    function __construct () {
        $app = Prototype::get_instance();
        $locale = dirname( debug_backtrace()[0]['file'] )
            . DS . 'locale' . DS . $app->language . '.json';
        if ( file_exists( $locale ) ) {
            $locale = json_decode( file_get_contents( $locale ), true );
            $this->dictionary[ $app->language ] = $locale;
        }
        $app->components[ get_class( $this ) ] = $this;
        $app->ctx->components[ get_class( $this ) ] = $this;
    }
}