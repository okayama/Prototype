<?php
class template extends PADOBaseModel {

    function save () {
        $app = Prototype::get_instance();
        if (! $this->id ) {
            parent::save();
        }
        $ctx = $app->ctx;
        $text = $this->text;
        $app->init_tags();
        $ctx->stash( 'current_template', $this );
        $__stash = $ctx->__stash;
        $local_vars = $ctx->local_vars;
        $vars = $ctx->vars;
        $cache_key = md5( $text );
        $ctx->build( $text );
        if ( $ctx->cache_key != $cache_key ) {
            $this->cache_key( $cache_key );
            $compiled = $ctx->compiled[ $cache_key ];
            $this->compiled( $compiled );
        }
        $ctx->vars = $vars;
        $ctx->local_vars = $local_vars;
        $ctx->__stash = $__stash;
        $app->set_cache( 'template' . DS . $this->id . DS . 'include_modules__c',
            array_values( $app->modules ) );
        return parent::save();
    }

    function remove () {
        $app = Prototype::get_instance();
        $cache_key = 'template' . DS . $this->id;
        $app->clear_cache( $cache_key );
        return parent::remove();
    }

}