<?php
class PTTagParser {

    protected $app = null;

    function __construct ( &$app ) {
        $this->app = $app;
        $ctx = $app->ctx;
        $ctx->register_callback(
            'pt_pre_parse_filter', 'pre_parse_filter', 'pre_parse_filter', $this );
    }

    function pre_parse_filter ( $template, $ctx, $insert, $content ) {
        $app = $this->app;
        $ctx = $app->ctx;
        $ctx->unregister_callback( 'pt_pre_parse_filter', 'pre_parse_filter' );
        $tmpl = $template;
        $tmpl = preg_replace( "/\r\n|\r/","\n", $tmpl );
        $prefix = $ctx->prefix;
        $tmpl = preg_replace( '/^<.*?>/', '', $tmpl );
        $tmpl = preg_replace( '/<\/[^>]*?>$/', '', $tmpl );
        $tmpl = str_replace( $insert, '', $tmpl );
        $lib = LIB_DIR . 'simple_html_dom' . DS . 'simple_html_dom.php';
        require_once( $lib );
        $parser = str_get_html( $tmpl );
        if (! $tmpl || $parser === false ) {
            return $template;
        }
        $block_tags = array_merge( $ctx->tags['block'], $ctx->tags['conditional'] );
        $errors = [];
        foreach ( $block_tags as $block ) {
            if ( $block == 'elseif' || $block == 'else' ) continue;
            $block = "{$prefix}{$block}";
            $ret = $parser->find( $block );
            $counter = 0;
            foreach ( $ret as $ele ) {
                $counter++;
                $outertext = $ele->outertext;
                if (! preg_match( "/<{$block}.*?.*<\/{$block}>$/si", $outertext ) ) {
                    $tag_start = $ele->tag_start;
                    $pre_mtml = substr( $tmpl, 0, $tag_start );
                    $line = mb_substr_count( $pre_mtml, "\n" ) + 1;
                    $errors[] = $app->translate(
                        'The %sth %s tag is not closed ( at line %s ).',
                            [ $counter, $block, $line ] );
                }
            }
        }
        $tag_class = new PTTags();
        $includes = $parser->find( "{$prefix}include" );
        foreach ( $includes as $include ) {
            $args = $include->attr;
            if ( isset( $args['module'] ) ) {
                $tag_class->hdlr_include( $args, $ctx, true );
            }
        }
        $parser->clear();
        $regex = '/<(\$?' . $prefix . '.*?)>/';
        $content = preg_replace( "/\r\n|\r/","\n", $content );
        preg_match_all( $regex, $content, $matches );
        $using_tags = [];
        if ( isset( $matches[1] ) ) {
            $matches = $matches[1];
            foreach ( $matches as $match ) {
                $tag = strtolower( str_replace( ':', '', $match ) );
                $tag = str_replace( '$', '', $tag );
                $tag = str_replace( '/', '', $tag );
                $tag = preg_replace( "/\r\n|\r/"," ", $tag );
                $tag = preg_split( "/\s/", $tag );
                $tag = $tag[0];
                $using_tags[] = $tag;
            }
        }
        $using_tags = array_unique( $using_tags );
        $all_tags = $ctx->tags;
        $registered_tags = [];
        foreach ( $all_tags as $kind => $tags ) {
            if ( $kind == 'modifier' ) continue;
            foreach ( $tags as $tag ) {
                $registered_tags[ "{$prefix}{$tag}" ] = true;
            }
        }
        foreach ( $using_tags as $tag ) {
            if (! isset( $registered_tags[ $tag ] ) ) {
                $name = preg_replace( "/^{$prefix}/", '', $tag );
                $emergence =
                    preg_replace( "/(.*?<{$prefix}:{0,1}{$name}.*?>).*$/si", "$1" ,$content );
                $line = mb_substr_count( $emergence, "\n" ) + 1;
                $errors[] = $app->translate(
                        'The tag %s is not found ( at line %s ).', [ $tag, $line ] );
            }
        }
        $app->stash( 'parser_errors', $errors );
        return '<html></html>';
    }

}