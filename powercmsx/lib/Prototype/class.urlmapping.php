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
        if ( $model != 'template' ) {
            $app = Prototype::get_instance();
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
        return parent::save();
    }

}