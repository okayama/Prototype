<?php
class template extends PADOBaseModel {

    function save () {
        $app = Prototype::get_instance();
        if (! $this->id ) {
            parent::save();
        }
        $app->init_tags();
        $ctx = $app->ctx;
        $text = $this->text;
        $app->init_tags();
        $ctx->stash( 'current_template', $this );
        $__stash = $ctx->__stash;
        $local_vars = $ctx->local_vars;
        $vars = $ctx->vars;
        $compiled = $ctx->build( $text, true );
        $compiled = rtrim( $compiled );
        $this->compiled( $compiled );
        $this->cache_key( md5( $text ) );
        $ctx->vars = $vars;
        $ctx->local_vars = $local_vars;
        $ctx->__stash = $__stash;
        return parent::save();
    }

}