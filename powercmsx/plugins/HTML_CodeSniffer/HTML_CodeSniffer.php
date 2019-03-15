<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );

class HTML_CodeSniffer extends PTPlugin {

    function __construct () {
        parent::__construct();
    }

    function insert_html_codesniffer ( $cb, $app, &$html ) {
        if (! $app->param( '__html_codesniffer' ) ) return true;
        if ( $cb['mime_type'] != 'text/html' ) return true;
        $workspace = $cb['workspace'];
        $ws_id = $workspace ? $workspace->id : 0;
        $enabled = $this->get_config_value( 'html_codesniffer_enabled', $ws_id );
        if (! $enabled ) return true;
        $level = $this->get_config_value( 'html_codesniffer_wcag_level', $ws_id );
        $js_path = $this->get_config_value( 'html_codesniffer_base_path', $ws_id );
        if (! $level || ( $level != 'A' && $level != 'AAA' ) ) {
            $level = 'AA';
        }
        $filename = 'HTMLCS.js';
        $lang = $app->user()->language;
        $translate = 'en';
        if ( $lang == 'ja' ) {
            $translate = 'ja';
        }
        if (! $js_path ) {
            $uri = $app->request_uri;
            $paths = explode( '/', $uri );
            array_pop( $paths );
            $js_path = implode( '/', $paths ) . '/plugins/HTML_CodeSniffer/assets/HTML_CodeSniffer';
            $js_path = $app->base . $js_path;
            $js_path .= '/';
            if ( $lang == 'ja' ) {
                $filename = 'HTMLCS.ja.js';
            }
        }
        $filename .= '?ts=' . time();
        $tag = "<link rel=\"stylesheet\" href=\"{$js_path}Auditor/HTMLCSAuditor.css\">\n";
        $tag.= <<<EOT
<script>
  (function() {var _p='{$js_path}';
    var _i=function(s,cb) {
    var sc=document.createElement('script');sc.onload = function()
    {sc.onload = null;sc.onreadystatechange = null;cb.call(this);
    };sc.onreadystatechange = function()
    {if(/^(complete|loaded)$/.test(this.readyState) === true)
    {sc.onreadystatechange = null;sc.onload();}};
    sc.src=s;if (document.head)
    {document.head.appendChild(sc);}
    else {document.getElementsByTagName('head')[0].appendChild(sc);}};
    var options={path:_p,lang: '{$translate}'};
    _i(_p+'{$filename}',function()
    {HTMLCSAuditor.run('WCAG2{$level}',null,options);});})
  ();
</script>
EOT;
        if ( preg_match( '/<\/body>/', $html ) ) {
            $html = preg_replace( '/(<\/body>)/', "$tag$1", $html );
        } else {
            $html .= $tag . '</body>';
        }
        return true;
    }

    function insert_codesniffer_checkbox ( $cb, $app, $param, &$tmpl ) {
        $include = $this->path() . DS . 'tmpl' . DS . 'screen_footer.tmpl';
        $include = file_get_contents( $include );
        $tmpl = preg_replace( '/<\/form>/', "{$include}</form>", $tmpl );
        return true;
    }
}