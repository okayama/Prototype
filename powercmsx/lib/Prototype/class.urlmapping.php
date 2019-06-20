<?php
class urlmapping extends PADOBaseModel {

    function remove () {
        $model = $this->model;
        if ( $model != 'template' ) {
            $app = Prototype::get_instance();
            $terms = ['workspace_id' => (int) $this->workspace_id,
                      'model' => $model, 'is_preferred' => 1,
                      'id' => ['!=' => $this->id ] ];
            $count = $app->db->model( 'urlmapping' )->count( $terms );
            if (! $count ) {
                $terms['is_preferred'] = 0;
                $urlmapping = $app->db->model( 'urlmapping' )->load( $terms,
                              ['sort' => 'id', 'direction' => 'ascend', 'limit' => 1] );
                if (! empty( $urlmapping ) ) {
                    $urlmapping = $urlmapping[0];
                    $urlmapping->is_preferred( 1 );
                    $app->db->model( 'urlmapping' )->update_multi( [ $urlmapping ] );
                }
            }
        }
        return parent::remove();
    }

    function save () {
        $model = $this->model;
        $app = Prototype::get_instance();
        if ( $model != 'template' ) {
            $terms = ['workspace_id' => (int) $this->workspace_id,
                      'model' => $model, 'is_preferred' => 1 ];
            if ( $this->id ) {
                $terms['id'] = ['!=' => $this->id ];
            }
            if ( $this->is_preferred ) {
                $urlmappings = $app->db->model( 'urlmapping' )->load( $terms );
                $urlmaps = [];
                foreach ( $urlmappings as $urlmapping ) {
                    $urlmapping->is_preferred( 0 );
                    $urlmaps[] = $urlmapping;
                }
                if ( count( $urlmaps ) ) {
                    $app->db->model( 'urlmapping' )->update_multi( $urlmaps );
                }
            } else {
                $count = $app->db->model( 'urlmapping' )->count( $terms );
                if (! $count ) {
                    $terms['is_preferred'] = 0;
                    $urlmappings = $app->db->model( 'urlmapping' )->load( $terms,
                                  ['sort' => 'id', 'direction' => 'ascend'] );
                    if ( empty( $urlmappings ) ) {
                        $this->is_preferred( 1 );
                    } else {
                        $urlmapping = $urlmappings[0];
                        $urlmapping->is_preferred( 1 );
                        $app->db->model( 'urlmapping' )->update_multi( [ $urlmapping ] );
                    }
                }
            }
        }
        $app->init_tags();
        $ctx = $app->ctx;
        $text = $this->mapping;
        $app->init_tags();
        $__stash = $ctx->__stash;
        $local_vars = $ctx->local_vars;
        $vars = $ctx->vars;
        $compiled = $ctx->build( $text, true );
        $this->compiled( $compiled );
        $this->cache_key( md5( $text ) );
        $ctx->vars = $vars;
        $ctx->local_vars = $local_vars;
        $ctx->__stash = $__stash;
        return parent::save();
    }

}