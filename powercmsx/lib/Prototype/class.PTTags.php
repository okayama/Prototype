<?php

use Michelf\Markdown;

class PTTags {

    function init_tags ( $force = false ) {
        $app = Prototype::get_instance();
        if ( $app->init_tags &&! $force ) return;
        $ctx = $app->ctx;
        $ctx->stash( 'workspace', $app->workspace() );
        $cache_key = 'template_tags__c';
        $cache = $app->get_cache( $cache_key );
        if ( $cache && ! $force ) {
            $r_tags = $cache['tags'];
            $blockmodels = $cache['blockmodels'];
            $function_relations = $cache['function_relations'];
            $block_relations = $cache['block_relations'];
            $function_date = $cache['function_date'];
            $fileurl_tags = $cache['fileurl_tags'];
            $workspace_tags = $cache['workspace_tags'];
            $alias = $cache['alias_functions'];
            $count_tags = $cache['count_tags'];
            $reference_tags = $cache['reference_tags'];
            $function_tags = $cache['function_tags'];
            $nextprev_tags = $cache['nextprev_tags'];
            $function_relcount = $cache['function_relcount'];
            $permalink_tags = $cache['permalink_tags'];
            foreach ( $r_tags as $name => $prop ) {
                list( $kind, $method, $component ) = $prop;
                if ( $component === 'PTTags' ) {
                    $_component = $this;
                } else {
                    $_component = $app->component( $component );
                    if (! $_component ) $_component = $app->autoload_component( $component );
                }
                $ctx->register_tag( $name, $kind, $method, $_component );
            }
            foreach ( $blockmodels as $key => $value ) {
                $ctx->stash( $key, $value );
            }
            $ctx->stash( 'function_relations', $function_relations );
            $ctx->stash( 'block_relations', $block_relations );
            $ctx->stash( 'function_date', $function_date );
            $ctx->stash( 'workspace_tags', $workspace_tags );
            $ctx->stash( 'fileurl_tags', $fileurl_tags );
            $ctx->stash( 'alias_functions', $alias );
            $ctx->stash( 'count_tags', $count_tags );
            $ctx->stash( 'reference_tags', $reference_tags );
            $ctx->stash( 'function_tags', $function_tags );
            $ctx->stash( 'nextprev_tags', $nextprev_tags );
            $ctx->stash( 'function_relcount', $function_relcount );
            $ctx->stash( 'permalink_tags', $permalink_tags );
            $app->init_tags = true;
            return;
        }
        $tables = $app->db->model( 'table' )->load( ['template_tags' => 1] );
        $block_relations    = [];
        $function_relations = [];
        $function_relcount  = [];
        $function_date      = [];
        $workspace_tags     = [];
        $fileurl_tags       = [];
        $reference_tags     = [];
        $count_tags         = [];
        $function_tags      = [];
        $permalink_tags     = [];
        $alias              = [];
        $tags = $this;
        $r_tags             = [];
        $blockmodels        = [];
        $nextprev_tags      = [];
        $excludes = ['rev_note', 'rev_changed', 'rev_diff', 'rev_type',
                     'rev_object_id', 'password'];
        foreach ( $tables as $table ) {
            $plural = strtolower( $table->plural );
            $plural = preg_replace( '/[^a-z0-9]/', '' , $plural );
            $label = strtolower( $table->label );
            $label = preg_replace( '/[^a-z0-9]/', '' , $label );
            $this->register_tag( $ctx, $plural, 'block', 'hdlr_objectloop', $tags, $r_tags );
            $this->register_tag( $ctx, $label . 'referencecontext',
                'block', 'hdlr_referencecontext', $tags, $r_tags );
            $tbl_name = $table->name;
            if ( $table->start_end ) {
                $this->register_tag( $ctx, $label . 'next',
                    'block', 'hdlr_nextprev', $tags, $r_tags );
                $nextprev_tags[ $label . 'next' ] = [ $tbl_name, 'next'];
                $this->register_tag( $ctx, $label . 'previous',
                    'block', 'hdlr_nextprev', $tags, $r_tags );
                $nextprev_tags[ $label . 'previous' ] = [ $tbl_name, 'previous'];
                if ( $tbl_name != $label ) {
                    $this->register_tag( $ctx, $tbl_name . 'next',
                        'block', 'hdlr_nextprev', $tags, $r_tags );
                    $nextprev_tags[ $tbl_name . 'next' ] = [ $tbl_name, 'next'];
                    $this->register_tag( $ctx, $tbl_name . 'previous',
                        'block', 'hdlr_nextprev', $tags, $r_tags );
                    $nextprev_tags[ $tbl_name . 'previous' ] = [ $tbl_name, 'previous'];
                }
            }
            $ctx->stash( 'blockmodel_' . $plural, $table->name );
            $blockmodels[ 'blockmodel_' . $plural ] = $table->name;
            $scheme = $app->get_scheme_from_db( $table->name );
            $columns = $scheme['column_defs'];
            $locale = $scheme['locale']['default'];
            $edit_properties = $scheme['edit_properties'];
            $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];
            $obj = $app->db->model( $table->name )->new();
            $relation_alias = [];
            foreach ( $columns as $key => $props ) {
                if ( in_array( $key, $excludes ) ) continue;
                if ( strpos( $props['type'], 'password' ) !== false ) {
                    continue;
                }
                $label = strtolower( $locale[ $key ] );
                $label = preg_replace( '/[^a-z0-9]/', '' , $label );
                if (! isset( $relations[ $key ] ) ) {
                    $tag_name = str_replace( '_', '', $key );
                    if ( $props['type'] === 'datetime' ) {
                        $function_date[] = $table->name . $key;
                    } else if ( $props['type'] === 'int' && 
                        isset( $edit_properties[ $key ] )
                        && strpos( $edit_properties[ $key ], 'relation' ) === 0 ) {
                        $prop = $edit_properties[ $key ];
                        if ( strpos( $prop, ':' ) !== false ) {
                            $edit = explode( ':', $prop );
                            $this->register_tag( $ctx, $tbl_name . $label . 'context',
                            'block', 'hdlr_referencecontext', $tags, $r_tags );
                            $reference_tags[ $tbl_name . $label . 'context' ]
                                = [ $tbl_name, $key, $edit[1] ];
                            if ( $key !== $tag_name ) {
                                $this->register_tag( $ctx, $tbl_name . $tag_name . 'context',
                                'block', 'hdlr_referencecontext', $tags, $r_tags );
                                $reference_tags[ $tbl_name . $tag_name . 'context' ]
                                    = [ $tbl_name, $key, $edit[1] ];
                            }
                        }
                    }
                    if ( $key !== $tag_name ) {
                        $alias[ $table->name . $tag_name ] = $tbl_name . $key;
                        $function_tags[ $tbl_name . $key ] = [ $tbl_name, $key ];
                    }
                    $this->register_tag( $ctx, $tbl_name . $tag_name, 'function',
                        'hdlr_get_objectcol', $tags, $r_tags );
                    $function_tags[ $tbl_name . $tag_name ] = [ $tbl_name, $key ];
                    if ( $table->name === 'workspace' ) {
                        $workspace_tags[] = $tbl_name . $tag_name;
                    }
                    if ( $label && $label != $tag_name ) {
                        if (! $obj->has_column( $label ) ) {
                            $this->register_tag( $ctx,
                                $tbl_name . $label,
                                    'function', 'hdlr_get_objectcol', $tags, $r_tags );
                            $alias[ $table->name . $label ] = $tbl_name . $key;
                        }
                    }
                    if ( $key === 'published_on' ) {
                        $this->register_tag( $ctx,
                            $tbl_name . 'date', 'function',
                                                'hdlr_get_objectcol', $tags, $r_tags );
                            $alias[ $tbl_name . 'date'] = $tbl_name . $key;
                    }
                    if ( isset( $edit_properties[ $key ] ) 
                        && $edit_properties[ $key ] === 'file' ) {
                        if (! $obj->has_column( $tag_name . 'url' ) ) {
                            $fileurl_tags[ $tbl_name . $tag_name . 'url'] = $key;
                            if ( $tbl_name === 'workspace' ) {
                                $workspace_tags[] = $tbl_name . $tag_name . 'url';
                            }
                            $this->register_tag( $ctx,
                                $tbl_name . $tag_name . 'url',
                                    'function', 'hdlr_get_objecturl', $tags, $r_tags );
                        }
                    }
                } else {
                    if ( $label && $label != $key ) {
                        $relation_alias[ $key ] = $label;
                    }
                }
                if ( preg_match( '/(^.*)_id$/', $key, $mts ) ) {
                    if ( isset( $edit_properties[ $key ] ) ) {
                        $prop = $edit_properties[ $key ];
                        if ( strpos( $prop, ':' ) !== false ) {
                            $edit = explode( ':', $prop );
                            if ( $edit[0] === 'relation' || $edit[0] === 'reference' ) {
                                $this->register_tag( $ctx, $tbl_name . $mts[1],
                                    'function', 'hdlr_get_objectcol', $tags, $r_tags );
                                $function_relations[ $tbl_name . $mts[1] ]
                                    = [ $key, $tbl_name, $mts[1], $edit[2] ];
                                $alias[ $tbl_name . $label ] = $tbl_name . $mts[1];
                                if ( $key === 'user_id' ) {
                                    $this->register_tag( $ctx, $tbl_name . 'author',
                                            'function', 'hdlr_get_objectcol', $tags, $r_tags );
                                    $alias[ $tbl_name . 'author'] = $tbl_name . $mts[1];
                                }
                            }
                        }
                    }
                }
            }
            $maps = $app->db->model( 'urlmapping' )->count( ['model' => $tbl_name ] );
            if ( $maps ) {
                $this->register_tag( $ctx,
                    $tbl_name . 'permalink',
                        'function', 'hdlr_get_objectcol', $tags, $r_tags );
                $permalink_tags[ $tbl_name . 'permalink' ] = $tbl_name;
                $this->register_tag( $ctx,
                    $tbl_name . 'archivelink',
                        'function', 'hdlr_get_objectcol', $tags, $r_tags );
            }
            $count_tagname = strtolower( $table->plural ) . 'count';
            $this->register_tag( $ctx,
                $count_tagname,
                    'function', 'hdlr_container_count', $tags, $r_tags );
            $count_tags[ $count_tagname ] = $tbl_name;
            foreach ( $relations as $key => $model ) {
                $tagName = preg_replace( '/[^a-z0-9]/', '' , $key );
                $this->register_tag( $ctx, $tbl_name . $tagName, 'block',
                    'hdlr_get_relatedobjs', $tags, $r_tags );
                $block_relations[ $tbl_name . $tagName ] = [ $key, $tbl_name, $model ];
                $this->register_tag( $ctx, $tbl_name . $tagName . 'count', 'function',
                    'hdlr_get_relationscount', $tags, $r_tags );
                $function_relcount[ $tbl_name . $tagName . 'count']
                    = [ $key, $tbl_name, $model ];
                if ( isset( $relation_alias[ $key ] ) ) {
                    $aliasName = $relation_alias[ $key ];
                    $this->register_tag( $ctx, $tbl_name . $aliasName, 'block',
                        'hdlr_get_relatedobjs', $tags, $r_tags );
                    $block_relations[ $tbl_name . $aliasName ] = [ $key, $tbl_name, $model ];
                    $this->register_tag( $ctx, $tbl_name . $aliasName . 'count', 'function',
                        'hdlr_get_relationscount', $tags, $r_tags );
                    $function_relcount[ $tbl_name . $aliasName . 'count']
                        = [ $key, $tbl_name, $model ];
                }
            }
            if ( $table->taggable ) {
                $this->register_tag( $ctx, $tbl_name . 'iftagged',
                    'conditional', 'hdlr_iftagged', $tags, $r_tags );
            }
            if ( $table->hierarchy ) {
                $this->register_tag( $ctx, $tbl_name . 'path',
                    'function', 'hdlr_get_objectpath', $tags, $r_tags );
            }
        }
        $ctx->stash( 'function_relations', $function_relations );
        $ctx->stash( 'block_relations', $block_relations );
        $ctx->stash( 'function_date', $function_date );
        $ctx->stash( 'workspace_tags', $workspace_tags );
        $ctx->stash( 'fileurl_tags', $fileurl_tags );
        $ctx->stash( 'alias_functions', $alias );
        $ctx->stash( 'count_tags', $count_tags );
        $ctx->stash( 'reference_tags', $reference_tags );
        $ctx->stash( 'function_tags', $function_tags );
        $ctx->stash( 'nextprev_tags', $nextprev_tags );
        $ctx->stash( 'permalink_tags', $permalink_tags );
        $cache = ['tags' => $r_tags, 'reference_tags' => $reference_tags,
                  'blockmodels' => $blockmodels, 'function_relations' => $function_relations,
                  'block_relations' => $block_relations, 'function_date' => $function_date,
                  'workspace_tags' => $workspace_tags, 'alias_functions' => $alias,
                  'count_tags' => $count_tags, 'fileurl_tags' => $fileurl_tags,
                  'function_tags' => $function_tags, 'nextprev_tags' => $nextprev_tags,
                  'function_relcount' => $function_relcount, 'permalink_tags' => $permalink_tags ];
        $app->set_cache( $cache_key, $cache );
        $app->init_tags = true;
    }

    function register_tag ( $ctx, $name, $kind, $method, $obj, &$registered_tags = [] ) {
        $ctx->register_tag( $name, $kind, $method, $obj );
        $registered_tags[ $name ] = [ $kind, $method, get_class( $obj ) ];
    }

    function hdlr_tablehascolumn ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        $column = $args['column'];
        $model = isset( $args['model'] ) ? $args['model'] : '';
        $obj = $model ? $app->db->model( $model ) : $ctx->stash( 'object' );
        if ( $model ) $app->get_scheme_from_db( $model );
        if (! $obj ) return;
        if ( $obj->_model !== 'table' || $model ) {
            return $obj->has_column( $column );
        }
        $col = $app->db->model( 'column' )->load( ['table_id' => $obj->id,
                                                   'name' => $column ] );
        return count( $col );
    }

    function hdlr_breadcrumbs ( $args, $content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        if (! $counter ) {
            $container = isset( $args['container'] ) ? $args['container'] : null;
            $include_system =
              isset( $args['include_system'] ) ? $args['include_system'] : null;
            $exclude_workspace =
              isset( $args['exclude_workspace'] ) ? $args['exclude_workspace'] : false;
            $current_context = $ctx->stash( 'current_context' );
            $obj = $ctx->stash( 'current_object' );
            if (! $obj ) {
                $repeat = $ctx->false();
                return;
            }
            if (! $container ) {
                if ( $obj->_model == 'entry' ) {
                    $container = 'category';
                } else if ( $obj->_model == 'page' ) {
                    $exclude_folders = isset( $args['exclude_folders'] )
                                     ? $args['exclude_folders'] : null;
                    if (! $exclude_folders ) {
                        $container = 'folder';
                    }
                }
            }
            $breadcrumbs = [];
            if ( $include_system ) {
                $breadcrumbs[] =
                  ['prymary' => $ctx->app->appname, 'url' => $ctx->app->site_url ];
            }
            if (! $exclude_workspace ) {
                $workspace = $ctx->stash( 'workspace' );
                if ( $workspace ) {
                    $breadcrumbs[] =
                        ['prymary' => $workspace->name, 'url' => $workspace->site_url ];
                }
            }
            if ( $container ) {
                $relation = $app->get_related_objs( $obj, $container );
                if ( count( $relation ) ) {
                    $app->init_callbacks( $container, 'post_load_objects' );
                    $parent = $relation[0];
                    $relation = $parent;
                    $cTable = $app->get_table( $container );
                    $callback = ['name' => 'post_load_objects', 'model' => $container,
                                 'table' => $cTable ];
                    $parents = [];
                    $status_published = null;
                    if ( $parent->has_column( 'status' ) ) {
                        $status_published = $app->status_published( $relation );
                    }
                    if ( $parent->has_column( 'parent_id' ) ) {
                        while ( $parent !== null ) {
                            if ( $parent_id = $parent->parent_id ) {
                                $parent_id = (int) $parent_id;
                                $parent = $app->db->model( $container )->load( ['id' => $parent_id ] );
                                $count_obj = count( $parent );
                                $app->run_callbacks( $callback, $container, $parent, $count_obj );
                                if ( $count_obj ) {
                                    $parent = $parent[0];
                                    if ( $status_published && $parent->status != $status_published ) {
                                        continue;
                                    }
                                    array_unshift( $parents, $parent );
                                } else {
                                    $parent = null;
                                }
                            } else {
                                $parent = null;
                            }
                        }
                    }
                    if ( $status_published && $status_published == $relation->status ) {
                        $parents[] = $relation;
                    } else if (! $status_published ) {
                        $parents[] = $relation;
                    }
                    $primary = $cTable->primary;
                    foreach ( $parents as $parent ) {
                        $permalink = $app->get_permalink( $parent );
                        $breadcrumbs[] = ['prymary' => $parent->$primary, 'url' => $permalink ];
                    }
                }
            }
            $table = $app->get_table( $obj->_model );
            if ( $obj->has_column( 'parent_id' ) ) {
                $parent = $obj;
                $parents = [];
                $status_published = null;
                if ( $parent->has_column( 'status' ) ) {
                    $status_published = $app->status_published( $parent );
                }
                $callback = ['name' => 'post_load_objects', 'model' => $obj->_model,
                             'table' => $table ];
                while ( $parent !== null ) {
                    if ( $parent_id = $parent->parent_id ) {
                        $parent_id = (int) $parent_id;
                        $parent = $app->db->model( $container )->load( ['id' => $parent_id ] );
                        $count_obj = count( $parent );
                        $app->run_callbacks( $callback, $container, $parent, $count_obj );
                        if ( $count_obj ) {
                            $parent = $parent[0];
                            if ( $status_published && $parent->status != $status_published ) {
                                continue;
                            }
                            array_unshift( $parents, $parent );
                        } else {
                            $parent = null;
                        }
                    } else {
                        $parent = null;
                    }
                }
                if ( $status_published && $status_published == $obj->status ) {
                    $parents[] = $obj;
                } else if (! $status_published ) {
                    $parents[] = $obj;
                }
                $primary = $table->primary;
                foreach ( $parents as $parent ) {
                    $permalink = $app->get_permalink( $parent );
                    $breadcrumbs[] = ['prymary' => $parent->$primary, 'url' => $permalink ];
                }
            } else {
                $permalink = $app->get_permalink( $obj );
                $primary = $table->primary;
                $breadcrumbs[] = ['prymary' => $ctx->stash( 'current_archive_title' ),
                                  'url' => $ctx->stash( 'current_archive_url' ) ];
            }
            $ctx->local_params = $breadcrumbs;
        }
        $params = $ctx->local_params;
        $ctx->set_loop_vars( $counter, $params );
        if ( isset( $params[ $counter ] ) ) {
            $param = $params[ $counter ];
            $ctx->local_vars['__key__'] = $param['url'] ? $param['url'] : '';
            $ctx->local_vars['__value__'] = $param['prymary'];
            $ctx->local_vars['__breadcrumbs_url__'] = $ctx->local_vars['__key__'];
            $ctx->local_vars['__breadcrumbs_title__'] = $param['prymary'];
            $repeat = true;
        } else {
            $repeat = false;
        }
        return $content;
    }

    function hdlr_menuitems ( $args, $content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        if (! $counter ) {
            $name = isset( $args['name'] ) ? $args['name'] : null;
            $id = isset( $args['id'] ) ? (int) $args['id'] : null;
            $basename = isset( $args['basename'] ) ? $args['basename'] : null;
            if (! $id && ! $name && ! $basename ) {
                $repeat = $ctx->false();
                return;
            }
            $orig_args = $args;
            $global = isset( $args['global'] ) ? (int) $args['global'] : null;
            $workspace_id = isset( $args['workspace_id'] )
                          ? (int) $args['workspace_id'] : null;
            if ( $workspace_id === null ) {
                $current_template = $ctx->stash( 'current_template' );
                if ( $current_template && $current_template->workspace_id ) {
                    $workspace_id = (int) $current_template->workspace_id;
                }
            }
            $terms = [];
            $args = ['limit' => 1];
            if ( $global ) {
                $terms['workspace_id'] = 0;
            } else {
                if ( $workspace_id === null && $ctx->stash( 'workspace' ) ) {
                    $workspace = $ctx->stash( 'workspace' );
                    $workspace_id = (int) $workspace->id;
                    $terms['workspace_id'] = ['IN' => [0, $workspace_id ]];
                    $args['sort'] = 'workspace_id';
                    $args['direction'] = 'descend';
                } else if ( $workspace_id !== null ) {
                    $terms['workspace_id'] = $workspace_id;
                }
            }
            if ( $name ) {
                $terms['name'] = $name;
            } else if ( $id ) {
                $terms['id'] = $id;
            } else if ( $basename ) {
                $terms['basename'] = $basename;
            }
            $app->get_scheme_from_db( 'menu' );
            $menu = $app->db->model( 'menu' )->get_by_key( $terms, $args );
            if (! $menu->id ) {
                $repeat = $ctx->false();
                return;
            }
            $urls = $app->get_related_objs( $menu, 'urlinfo' );
            if ( empty( $urls ) ) {
                $repeat = $ctx->false();
                return;
            }
            $params = [];
            $include_draft = isset( $args['include_draft'] )
                           ? true : false;
            $column = isset( $args['column'] ) ? $args['column'] : '';
            $collback_models = [];
            foreach ( $urls as $url ) {
                if (! $url->model || ! $url->object_id ) {
                    continue;
                }
                if ( $url->delete_flag ) continue;
                $table = $app->get_table( $url->model );
                if (! $table ) continue;
                $primary = $table->primary;
                $id = (int) $url->object_id;
                $_model = $app->db->model( $url->model );
                $terms = ['id' => $id];
                $args = ['limit' => 1];
                $cols = "id,{$primary}";
                $_caching = $app->db->caching;
                $app->db->caching = false;
                if ( $column ) $cols .= ",{$column}";
                if ( $_model->has_column( 'status' ) && ! $include_draft ) {
                    $status_published = (int) $app->status_published( $url->model );
                    $terms['status'] = $status_published;
                }
                $extra = '';
                if (! isset( $collback_models[ $url->model ] ) ) {
                    $scheme = $app->get_scheme_from_db( $url->model );
                    $app->init_callbacks( $url->model, 'pre_listing' );
                    $app->init_callbacks( $url->model, 'post_load_objects' );
                    $collback_models[ $url->model ] = true;
                }
                $callback = ['name' => 'pre_listing', 'model' => $url->model,
                             'scheme' => $scheme, 'table' => $table,
                             'args' => $orig_args ];
                $extra = '';
                $app->run_callbacks( $callback, $url->model, $terms, $args, $extra );
                $objects = $_model->load( $terms, $args, $cols, $extra );
                $callback = ['name' => 'post_load_objects', 'model' => $url->model,
                             'table' => $table ];
                $count_obj = count( $objects );
                $app->run_callbacks( $callback, $url->model, $objects, $count_obj );
                if (! $objects || ! count( $objects ) ) continue;
                $obj = $objects[0];
                $_params = [];
                $_params['__item_primary__'] = $obj->$primary;
                $_params['__item_label__'] = $obj->$primary;
                if ( $column && $obj->has_column( $column ) ) {
                    $_params["__item_{$column}__"] = $obj->$column;
                }
                $_params['__item_id__'] = $id;
                $_params['__item_model__'] = $url->model;
                $_params['__item_url__'] = $url->url;
                $params[] = $_params;
                $app->db->caching = $_caching;
            }
            $ctx->local_params = $params;
        }
        $params = $ctx->local_params;
        $ctx->set_loop_vars( $counter, $params );
        if ( isset( $params[ $counter ] ) ) {
            $param = $params[ $counter ];
            foreach ( $param as $key => $value ) {
                $ctx->local_vars[ $key ] = $value;
            }
            $ctx->local_vars['__key__'] = $param['__item_url__'];
            $ctx->local_vars['__value__'] = $param['__item_primary__'];
            $repeat = true;
        } else {
            $repeat = false;
        }
        return $content;
    }

    function hdlr_nextprev ( $args, $content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $this_tag = $args['this_tag'];
        $nextprev_tags = $ctx->stash( 'nextprev_tags' );
        if (! isset( $nextprev_tags[ $this_tag ] ) ) {
            $repeat = $ctx->false();
            return;
        }
        list ( $model, $nextprev ) = [ $nextprev_tags[ $this_tag ][0],
                                       $nextprev_tags[ $this_tag ][1]];
        $local_vars = [ $model ];
        if (! $counter ) {
            $obj = $ctx->stash( $model );
            if (! $obj || ! $obj->has_column( 'published_on' ) ) {
                $repeat = $ctx->false();
                return;
            }
            $include_draft = isset( $args['include_draft'] )
                           ? true : false;
            $op = $nextprev == 'next' ? '>=' : '<=';
            $direction = $nextprev == 'next' ? 'ascend' : 'descend';
            $terms = ['id' => ['!=' => $obj->id ] ];
            $terms['published_on'] = [ $op => $obj->published_on ];
            $_params = ['limit' => 1, 'sort' => 'published_on', 'direction' => $direction];
            if ( $obj->has_column( 'rev_type' ) ) {
                $terms['rev_type'] = 0;
            }
            if (! $include_draft && $obj->has_column( 'status' ) ) {
                $terms['status'] = 4;
            }
            $params = $app->db->model( $model )->load( $terms, $_params );
            if ( empty( $params ) ) {
                $repeat = $ctx->false();
                return;
            }
            $_next_prev = $params[0];
            if ( $obj->published_on === $_next_prev->published_on ) {
                if ( $nextprev == 'next' ) {
                    if ( $obj->id > $_next_prev->id ) {
                        $terms['id'] = ['>' => $obj->id ];
                        $params = $app->db->model( $model )->load( $terms, $_params );
                    }
                } else {
                    if ( $obj->id < $_next_prev->id ) {
                        $terms['id'] = ['<' => $obj->id ];
                        $params = $app->db->model( $model )->load( $terms, $_params );
                    }
                }
                if ( empty( $params ) ) {
                    $repeat = $ctx->false();
                    return;
                }
                $_next_prev = $params[0];
            }
            $ctx->localize( $local_vars );
            $ctx->local_params = $params;
            if ( $app->id == 'Prototype'
                && isset( $args['rebuild'] ) && $args['rebuild'] ) {
                if ( $app->mode == 'save' && $obj->id == $app->param( 'id' ) ) {
                    $rebuilt_ids = $app->rebuilt_ids;
                    $rebuilt_ids = isset( $rebuilt_ids[ $obj->_model ] )
                        ? $rebuilt_ids[ $obj->_model ] : [ $obj->id ];
                    if (! in_array( $_next_prev->id, $rebuilt_ids ) ) {
                        $app->publish_obj( $_next_prev );
                        $rebuilt_ids[] = $_next_prev->id;
                        $app->rebuilt_ids[ $obj->_model ] = $rebuilt_ids;
                    }
                }
            }
        }
        $params = $ctx->local_params;
        if ( isset( $params[ $counter ] ) ) {
            $obj = $params[ $counter ];
            $ctx->stash( $model, $obj );
            $repeat = true;
        } else {
            $ctx->restore( $local_vars );
            $repeat = false;
        }
        return $content;
    }

    function hdlr_cacheblock ( $args, $content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $cache_key = isset( $args['cache_key'] ) ? $args['cache_key'] : '';
        $request_id = $app->request_id;
        $db_cache_key = md5( "template-module-{$cache_key}-{$request_id}" );
        if (! $counter ) {
            if ( $cache_key && $app->stash( "template-module-{$cache_key}" ) ) {
                $repeat = false;
                return $app->stash( "template-module-{$cache_key}" );
            } else if ( $cache_key && !$app->no_cache ) {
                $session = $app->db->model( 'session' )->get_by_key( [
                               'name' => $db_cache_key, 
                               'kind' => 'CH',
                               'key'  => $cache_key ] );
                if ( $session->id && ( $session->expires > time() ) ) {
                    $repeat = false;
                    return $session->data;
                }
            }
        }
        if ( isset( $content ) ) {
            if ( $cache_key && !$app->no_cache ) {
                $app->stash( "template-module-{$cache_key}", $content );
                $session = $app->db->model( 'session' )->get_by_key( [
                               'name'  => $db_cache_key, 
                               'kind'  => 'CH',
                               'value' => $request_id,
                               'key'   => $cache_key ] );
                $session->start( time() );
                $session->expires( time() + $app->token_expires );
                $session->data( $content );
                $session->save();
            }
        }
        return $content;
    }

    function hdlr_setcontext ( $args, $content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $localvars = ['workspace', 'current_container', 'current_archive_context',
                      'current_context', 'current_archive_type', 'current_timestamp',
                      'current_timestamp_end'];
        $context = isset( $args['context'] ) ? $args['context'] : null;
        if ( $context ) {
            $localvars[] = $context;
        }
        if (! $counter ) {
            $container = isset( $args['container'] ) ? $args['container'] : null;
            $archive_type = isset( $args['archive_type'] ) ? $args['archive_type'] : null;
            $archive_context = isset( $args['archive_context'] ) ? $args['archive_context'] : null;
            $timestamp = isset( $args['timestamp'] ) ? $args['timestamp'] : null;
            $timestamp_end = isset( $args['timestamp_end'] ) ? $args['timestamp_end'] : null;
            $workspace_id = isset( $args['workspace_id'] ) ? (int) $args['workspace_id'] : null;
            $workspace = $workspace_id
                       ? $app->db->model( 'workspace' )->load( $workspace_id )
                       : $ctx->stash( 'workspace' );
            $obj = null;
            if ( $context ) {
                $table = $app->get_table( $context );
                if (! $table ) {
                    $repeat = $ctx->false();
                    return;
                }
                $primary = $table->primary;
                $value = isset( $args[ $primary ] ) ? $args[ $primary ] : null;
                $path = isset( $args['path'] ) ? $args['path'] : null;
                $id = null;
                if (! $value ) {
                    $id = isset( $args['id'] ) ? (int) $args['id'] : null;
                }
                if (! $id && ! $value && ! $path ) {
                    $repeat = $ctx->false();
                    return;
                }
                $terms = [];
                if ( $path && !$table->hierarchy ) {
                    $repeat = $ctx->false();
                    return;
                }
                if (! $path ) {
                    $terms = $id ? ['id' => $id ] : [ $primary => $value ];
                }
                $model = $app->db->model( $context );
                if ( $model->has_column( 'workspace_id' ) ) {
                    if (! $workspace && ! $table->space_child && $table->display_system ) {
                        $terms['workspace_id'] = 0;
                    } else if ( $workspace ) {
                        $terms['workspace_id'] = $workspace->id;
                    }
                }
                if ( $path ) {
                    $paths = explode( '/', $path );
                    $value = array_pop( $paths );
                    $parent_id = 0;
                    $ws_terms = $terms;
                    if (!empty( $paths ) ) {
                        foreach ( $paths as $path ) {
                            $ws_terms[ $primary ] = $path;
                            $ws_terms['parent_id'] = $parent_id;
                            $parent = $app->db->model( $context )->get_by_key( $ws_terms );
                            if (! $parent->id ) {
                                $repeat = $ctx->false();
                                return;
                            }
                            $parent_id = (int) $parent->id;
                        }
                    }
                    $terms['parent_id'] = $parent_id;
                    $terms[ $primary ] = $value;
                }
                $obj = $app->db->model( $context )->get_by_key( $terms );
                if (! $obj->id ) {
                    $repeat = $ctx->false();
                    return;
                }
            }
            $ctx->localize( $localvars );
            if ( $workspace ) {
                $ctx->stash( 'workspace', $workspace );
            }
            if ( $obj ) {
                $ctx->stash( $context, $obj );
                $ctx->stash( 'current_context', $context );
            }
            if ( $container ) {
                $ctx->stash( 'current_container', $container );
            }
            if ( $archive_type ) {
                $ctx->stash( 'current_archive_type', $archive_type );
            }
            if ( $archive_context ) {
                $ctx->stash( 'current_archive_context', $archive_context );
            }
            if ( $timestamp ) {
                $ctx->stash( 'current_timestamp', $timestamp );
            }
            if ( $timestamp_end ) {
                $ctx->stash( 'current_timestamp_end', $timestamp_end );
            }
        }
        if ( $counter ) {
            $ctx->restore( $localvars );
        }
        return $content;
    }

    function hdlr_calendar ( $args, $content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $localvars = ['current_timestamp', 'current_timestamp_end'];
        if (! $counter ) {
            $m = isset( $args['month'] ) ? strtolower( $args['month'] )
               : $ctx->stash( 'current_timestamp' );
            if ( $m == 'this' || !$m ) {
                $year = date('Y');
                $month = date('n');
            } else if ( $m == 'last' ) {
                $year = date('Y', strtotime( date( 'Y-m-1' ) . '-1 month' ) );
                $month = date('n', strtotime( date( 'Y-m-1' ) . '-1 month' ) );
            } else if ( preg_match( '/^[0-9]*$/', $m ) ) {
                $year = substr( $m, 0, 4 );
                $month = substr( $m, 4, 2 );
            } else {
                $year = date('Y');
                $month = date('n');
            }
            $last_day = date( 'j', mktime( 0, 0, 0, $month + 1, 0, $year ) );
            $params = [];
            $j = 0;
            $s = 7;
            for ( $i = 1; $i < $last_day + 1; $i++ ) {
                $week = date( 'w', mktime( 0, 0, 0, $month, $i, $year ) );
                if ( $i == 1 ) {
                    for ( $s = 1; $s <= $week; $s++ ) {
                        $params[ $j ]['day'] = '';
                        $params[ $j ]['week'] = $s - 1;
                        $j++;
                    }
                    $s--;
                }
                $params[ $j ]['day'] = $i;
                if ( $s == 7 ) {
                    $s = 0;
                }
                $params[ $j ]['week'] = $s;
                $s++;
                $j++;
                if ( $i == $last_day ) {
                    for ( $k = 1; $k <= 6 - $week; $k++ ) {
                        $params[ $j ]['day'] = '';
                        $params[ $j ]['week'] = $s;
                        $s++;
                        $j++;
                    }
                }
            }
            $ctx->localize( $localvars );
            $ctx->local_params = $params;
            $week = [
              'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $ctx->local_vars['__week__'] = $week;
            $ctx->local_vars['__year__'] = $year;
            $month = str_pad( $month, 2, 0, STR_PAD_LEFT );
            $ctx->local_vars['__month__'] = $month;
        }
        $params = $ctx->local_params;
        $ctx->set_loop_vars( $counter, $params );
        if ( isset( $params[ $counter ] ) ) {
            $week = $ctx->local_vars['__week__'];
            $year = $ctx->local_vars['__year__'];
            $month = $ctx->local_vars['__month__'];
            $day = $params[ $counter ];
            $dayOfWeek = $day['week'];
            $day = $day['day'];
            $ctx->local_vars['__date__'] = $day;
            $ctx->local_vars['__value__'] = $day;
            $ctx->local_vars['__week_number__'] = $dayOfWeek;
            $ctx->local_vars['__key__'] = $dayOfWeek;
            $ctx->local_vars['__day_of_week__'] = $week[ $dayOfWeek ];
            $ctx->local_vars['__week_header__'] = $dayOfWeek == 0 ? true : false;
            $ctx->local_vars['__week_footer__'] = $dayOfWeek == 6 ? true : false;
            $ctx->stash( 'current_timestamp', null );
            $ctx->stash( 'current_timestamp_end', null );
            $ctx->local_vars['__timestamp__'] = '';
            if ( $day ) {
                $day = str_pad( $day, 2, 0, STR_PAD_LEFT );
                $ts_start = "{$year}{$month}{$day}000000";
                $ctx->local_vars['__timestamp__'] = $ts_start;
                $ctx->stash( 'current_timestamp', $ts_start );
                $ctx->stash( 'current_timestamp_end', "{$year}{$month}{$day}235959" );
            }
            $repeat = true;
        } else {
            $ctx->restore( $localvars );
            $repeat = false;
        }
        return $content;
    }

    function hdlr_countgroupby ( $args, $content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        if (! $counter ) {
            $model = $args['model'];
            $app->get_scheme_from_db( $model );
            $group = $args['group'];
            if ( is_string( $group ) ) $group = [ $group ];
            $count = isset( $args['count'] ) ? $args['count']
                   : $group[ count( $group ) - 1 ];
            unset( $args['model'], $args['group'] );
            $obj = $app->db->model( $model )->new();
            $terms = [];
            foreach ( $args as $key => $value ) {
                if ( $obj->has_column( $key ) ) {
                    $terms[ $key ] = $value;
                }
            }
            $params = ['group' => $group ];
            if ( isset( $args['sort_by'] ) ) {
                $sort_by = $args['sort_by'];
            }
            if ( isset( $sort_by ) && $obj->has_column( $sort_by ) ) {
                $params['sort'] = $sort_by;
            }
            if ( isset( $args['sort_order'] ) ) {
                $params['direction'] = ( stripos( $args['sort_order'], 'desc' )
                    !== false ) ? 'descend' : 'ascend';
            }
            if ( isset( $args['offset'] ) ) {
                $params['offset'] = (int) $args['offset'];
            }
            if ( isset( $args['limit'] ) ) {
                $params['limit'] = (int) $args['limit'];
            }
            $extra = '';
            if ( $obj->has_column( 'workspace_id' ) ) {
                $ws_attr = $this->include_exclude_workspaces( $app, $args );
                if ( $ws_attr ) {
                    $ws_attr = ' AND ' . $obj->_model . "_workspace_id ${ws_attr}";
                    $extra .= $ws_attr;
                }
            }
            $params['count'] = $count;
            $group = $app->db->model( $model )->count_group_by( $terms, $params, $extra );
            if ( empty( $group ) ) {
                $repeat = $ctx->false();
                return;
            }
            $prefix = $obj->_colprefix;
            $params = [];
            foreach ( $group as $items ) {
                $_item = [];
                foreach ( $items as $k => $v ) {
                    if ( strpos( $k, 'COUNT' ) === 0 ) {
                        $k = '_count_object';
                    } else {
                        $k = preg_replace( "/^$prefix/", '', $k );
                    }
                    $_item[ $k ] = $v;
                }
                $params[] = $_item;
            }
            $ctx->local_params = $params;
        }
        $params = $ctx->local_params;
        $ctx->set_loop_vars( $counter, $params );
        if ( isset( $params[ $counter ] ) ) {
            $results = $params[ $counter ];
            foreach ( $results as $key => $value ) {
                $ctx->local_vars['count_group_by_' . $key ] = $value;
            }
            $repeat = true;
        }
        return ( $counter > 1 && isset( $args['glue'] ) )
            ? $args['glue'] . $content : $content;
    }

    function hdlr_workspacecontext ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        if (! $counter ) {
            $ws = $ctx->stash( 'workspace' );
            $ctx->stash( 'orig_workspace', $ws );
            $app = $ctx->app;
            $id = isset( $args['id'] ) ? $args['id'] : null;
            if (! $id ) {
                $id = isset( $args['workspace_id'] ) ? $args['workspace_id'] : null;
            }
            if ( $id ) {
                $ws = $app->db->model( 'workspace' )->load( (int) $id );
                if ( $ws )
                    $ctx->stash( 'workspace', $ws );
            }
        } else {
            $ws = $ctx->stash( 'orig_workspace' );
            if ( $ws )
                $ctx->stash( 'workspace', $ws );
        }
        return $content;
    }

    function hdlr_workflowusers ( $args, $content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $workflow = $ctx->stash( 'workflow' );
        $workflow_id = isset( $args['workflow_id'] ) ? $args['workflow_id'] : 0;
        if (! $workflow ) {
            $workflow = $app->db->model( 'workflow' )->load( (int) $workflow_id );
        }
        if (! $workflow ) {
            $repeat = $ctx->false();
            return;
        }
        if (! is_object( $workflow ) ) {
            $repeat = $ctx->false();
            return;
        }
        $model = $workflow->model;
        $workspace_id = $workflow->workspace_id;
        if (! $counter ) {
            $previous = isset( $args['previous'] ) ? $args['previous'] : false;
            $next = isset( $args['next'] ) ? $args['next'] : false;
            $all = isset( $args['all'] ) ? $args['all'] : false;
            $params = ['lockout' => 0];
            if ( $previous || $next || $all ) {
                $group_name = $app->permission_group( $app->user, $model, $workspace_id );
                $users_draft =
                    $app->get_related_objs( $workflow, 'user', 'users_draft', $args );
                $users_review =
                    $app->get_related_objs( $workflow, 'user', 'users_review', $args );
                $users_publish =
                    $app->get_related_objs( $workflow, 'user', 'users_publish', $args );
                $users = array_merge( $users_draft, $users_review, $users_publish );
                $contains_me = false;
                foreach ( $users as $user ) {
                    if ( $user->id == $app->user->id ) {
                        $contains_me = true;
                        break;
                    }
                }
                $current_user = $app->user;
                if (! $contains_me ) {
                    if ( $app->mode == 'view' ) {
                        $obj = $ctx->stash( 'object' );
                        if ( $obj ) $current_user = $obj->user;
                    }
                    $add_me = false;
                    if ( $next && $workflow->approval_type == 'Parallel' ) {
                        $add_me = true;
                    } else if ( $previous && $workflow->remand_type == 'Parallel' ) {
                        $add_me = true;
                    }
                    if ( $add_me ) {
                        $me = $app->user;
                        if ( $group_name == 'creator' ) {
                            $me->relation_name = 'users_draft';
                            array_unshift( $users_draft, $me );
                        } else if ( $group_name == 'reviewer' ) {
                            $me->relation_name = 'users_review';
                            array_unshift( $users_review, $me );
                        } else if ( $group_name == 'publisher' ) {
                            $me->relation_name = 'users_publish';
                            array_unshift( $users_publish, $me );
                        }
                        $users = array_merge( $users_draft, $users_review, $users_publish );
                    }
                }
                $single = isset( $args['single'] ) ? $args['single'] : false;
                $object_user_id = isset( $ctx->vars['object_user_id'] )
                                ? $ctx->vars['object_user_id'] : null;
                if ( $next ) {
                    $_users = [];
                    $match = false;
                    foreach ( $users as $user ) {
                        if ( $object_user_id && 
                                $object_user_id == $user->id ) {
                            if ( $user->id == $current_user->id ) {
                                $match = true;
                            }
                            continue;
                        }
                        if ( $match ) {
                            $_users[] = $user;
                        }
                        if ( $user->id == $current_user->id ) {
                            $match = true;
                        }
                    }
                    if ( empty( $_users ) ) {
                        $repeat = $ctx->false();
                        return;
                    }
                    if ( $single ) {
                        $user = array_shift( $_users );
                        $users = [ $user ];
                    } else {
                        $users = $_users;
                    }
                } else if ( $previous ) {
                    $_users = [];
                    $match = false;
                    foreach ( $users as $user ) {
                        if (! $match && $user->id != $current_user->id ) {
                            $_users[] = $user;
                        }
                        if ( $user->id == $current_user->id ) {
                            if ( $object_user_id && 
                                $object_user_id == $user->id ) {
                            } else {
                                $_users[] = $user;
                            }
                            $match = true;
                            break;
                        }
                    }
                    if ( empty( $_users ) ) {
                        $repeat = $ctx->false();
                        return;
                    }
                    if ( $single ) {
                        $user = array_pop( $_users );
                        $users = [ $user ];
                    } else {
                        $users = $_users;
                    }
                }
            } else {
                $type = isset( $args['type'] ) ? $args['type'] : 'users_draft';
                $users = $app->get_related_objs( $workflow, 'user', $type, $params );
            }
            $query_string = $app->query_string();
            if ( strpos( $query_string, 'revision' ) !== false ) {
                $table = $app->get_table( $model );
                if ( $table->revisable ) {
                    $permitted_users = [];
                    $workspace = $app->workspace();
                    foreach ( $users as $user ) {
                        $can_revision =
                            $app->can_do( $model, 'revision', null, $workspace, $user );
                        if ( $can_revision ) {
                            $permitted_users[] = $user;
                        }
                    }
                }
                $users = $permitted_users;
            }
            $ctx->local_params = $users;
        }
        $users = $ctx->local_params;
        if ( empty( $users ) ) {
            $repeat = $ctx->false();
            return;
        }
        $ctx->set_loop_vars( $counter, $users );
        if ( isset( $users[ $counter ] ) ) {
            $user = $users[ $counter ];
            $user_values = $user->get_values();
            foreach ( $user_values as $key => $value ) {
                if ( stripos( $key, 'password' ) === false )
                    $ctx->local_vars[ 'workflow_' . $key ] = $value;
            }
            $rel_name = $user_values['relation_name'];
            $group_label = 'Publisher';
            if ( $rel_name == 'users_draft' ) {
                $group_label = 'Creator';
            } else if ( $rel_name == 'users_review' ) {
                $group_label = 'Reviewer';
            }
            $ctx->local_vars[ 'workflow_group_label' ] = $group_label;
            $repeat = true;
        } else {
            $repeat = false;
        }
        return ( $counter > 1 && isset( $args['glue'] ) )
            ? $args['glue'] . $content : $content;
    }

    function hdlr_objectcontext ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        $obj = null;
        if ( isset( $args['model'] ) && isset( $args['id'] ) ) {
            $id = (int) $args['id'];
            $obj = $app->db->model( $args['model'] )->load( $id );
        } else if ( isset( $args['from'] ) && isset( $args['id'] ) ) {
            $id = (int) $args['id'];
            $from = $app->db->model( $args['from'] )->load( $id );
            if ( $from && $from->has_column( 'object_id' )
                    && $from->has_column( 'model' ) ) {
                $object_id = $from->object_id;
                $model = $from->model;
                if ( (! $model || !$object_id ) && isset( $args['force'] ) && $args['force'] ) {
                    $from = $app->db->model( $args['from'] )->load( ['id' => $id ] );
                    if ( is_array( $from ) && count( $from ) ) {
                        $from = $from[0];
                        $object_id = $from->object_id;
                        $model = $from->model;
                    }
                }
                if ( $model && $object_id ) {
                    $obj = $app->db->model( $model )->load( (int) $object_id );
                }
            }
        } else {
            if (! isset( $args['model'] ) ) {
                $obj = $ctx->stash( 'object' );
            }
        }
        if (! $obj ) {
            if ( isset( $args['model'] ) ) {
                $model = $args['model'];
                $obj = $ctx->stash( $model );
            }
        }
        if (! $obj ) {
            return;
        }
        $var_prefix = isset( $args['prefix'] ) ? $args['prefix'] : 'object';
        $var_prefix .= '_';
        $vars = $obj->get_values();
        $colprefix = $obj->_colprefix;
        $ctx->stash( 'current_context', $obj->_model );
        $ctx->stash( $obj->_model, $obj );
        $column_defs = $app->db->scheme[ $obj->_model ]['column_defs'];
        $table = $app->get_table( $obj->_model );
        $primary = $table ? $table->primary : '';
        if (! $primary ) {
            if ( $obj->has_column( 'title' ) ) {
                $primary = 'title';
            } else if ( $obj->has_column( 'name' ) ) {
                $primary = 'name';
            } else if ( $obj->has_column( 'label' ) ) {
                $primary = 'label';
            }
        }
        if ( isset( $ctx->vars['forward_params'] ) ) {
            $column_names = array_keys( $column_defs );
            foreach ( $column_names as $name ) {
                $vars[ $name ] = $app->param( $name );
            }
        }
        $ctx->local_vars[ '_object_model' ] = $obj->_model;
        foreach ( $vars as $col => $value ) {
            if ( $colprefix ) $col = preg_replace( "/^$colprefix/", '', $col );
            if ( isset( $column_defs[ $col ]['type'] )
                && $column_defs[ $col ]['type'] === 'blob' ) {
                $value = $value ? 1 : '';
            }
            if ( $col == $primary ) {
                $ctx->local_vars[ '_object_primary' ] = $value;
            }
            $ctx->local_vars[ $var_prefix . $col ] = $value;
            if ( $col === 'status' ) {
                if ( $table = $app->get_table( $obj->_model ) ) {
                    $column = $app->db->model( 'column' )->get_by_key(
                        ['teble_id' => $table->id, 'name' => 'status'] );
                    $ctx->local_vars['status_options'] = $column->options;
                }
            }
        }
        $scheme = $app->get_scheme_from_db( $obj->_model );
        $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];
        if ( isset( $ctx->vars['forward_params'] ) ) {
            foreach ( $relations as $name => $to_obj ) {
                $rels = $app->param( $name );
                $ids = [];
                if ( is_array( $rels ) ) {
                    foreach ( $rels as $id ) {
                        if ( $id ) $ids[] = $id;
                    }
                }
                $ctx->local_vars[ $var_prefix . $name ] = $ids;
            }
        } else {
            if ( $obj->id ) {
                foreach ( $relations as $name => $to_obj ) {
                    $terms = ['from_id'  => $obj->id, 
                              'name'     => $name,
                              'from_obj' => $obj->_model ];
                    if ( $to_obj !== '__any__' ) $terms['to_obj'] = $to_obj;
                    $relations = $app->db->model( 'relation' )->load(
                                                $terms, ['sort' => 'order'] );
                    $ids = [];
                    foreach( $relations as $relation ) {
                        $model = $relation->to_obj;
                        if (! $model ) continue;
                        $ctx->local_vars['object_' . $name . '_model'] = $model;
                        $to_id = (int) $relation->to_id;
                        $rel_obj = $app->db->model( $model )->load( $to_id );
                        if ( $rel_obj ) $ids[] = $relation->to_id;
                    }
                    $ctx->local_vars[ $var_prefix . $name ] = $ids;
                }
            }
        }
        return true;
    }

    function hdlr_isadmin ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        if ( $user = $app->user() ) {
            return $user->is_superuser;
        }
        $workspace = isset( $args['workspace'] ) ? $args['workspace'] : false;
        if (! $workspace ) 
            $workspace = isset( $args['scope'] )
            && $args['scope'] == 'workspace' ? true : false;
        if ( $workspace ) return $app->can_do( 'workspace' );
        return false;
    }

    function hdlr_ifarchivetype ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        $type = isset( $args['archive_type'] ) ? $args['archive_type'] : $args['type'];
        if (! $type ) return false;
        $extra = '';
        $ws_attr = $this->include_exclude_workspaces( $app, $args );
        if ( $ws_attr ) {
            $extra = " AND urlinfo_workspace_id ${ws_attr}";
        }
        $terms = ['archive_type' => $type ];
        if (! $extra ) {
            $workspace = $ctx->stash( 'workspace' );
            if ( $workspace ) {
                $terms['workspace_id'] = $workspace->id;
            } else {
                $terms['workspace_id'] = 0;
            }
        }
        return $app->db->model( 'urlinfo' )->count( $terms, null, null, $extra );
    }

    function hdlr_ifuserrole ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        $name = isset( $args['role'] ) ? $args['role'] : '';
        if (! $name ) return false;
        if (!$user = $app->user() ) {
            return false;
        }
        $inherit = isset( $args['inherit'] ) ? $args['inherit'] : '';
        if ( $inherit && $user->is_superuser ) return true;
        $extra = '';
        $ws_attr = $this->include_exclude_workspaces( $app, $args );
        if ( $ws_attr ) {
            $ws_attr = " AND permission_workspace_id ${ws_attr}";
            $extra .= $ws_attr;
        }
        $terms = ['user_id' => $user->id ];
        if ( $app->workspace() ) {
            $workspace_id = (int) $app->workspace()->id;
            if ( $inherit ) {
                $perms = $app->permissions();
                if ( isset( $perms[ $workspace_id ] ) ) {
                    if ( in_array( 'workspace_admin', $perms[ $workspace_id ] ) ) {
                        return true;
                    }
                }
            }
            $terms['workspace_id'] = $workspace_id;
        } else if (! $extra ) {
            $terms['workspace_id'] = 0;
        }
        $permissions = $app->db->model( 'permission' )->load( $terms, [], '', $extra );
        if ( is_array( $permissions ) && count( $permissions ) ) {
            foreach ( $permissions as $permission ) {
                $roles = $app->get_related_objs( $permission, 'role', 'roles' );
                foreach ( $roles as $role ) {
                    if ( $role->name == $name ) {
                        return true;
                    }
                }
            }
        }
    }

    function hdlr_ifworkspacemodel ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        $model = $args['model'];
        $obj = $app->db->model( $model )->new();
        return $obj->has_column( 'workspace_id' );
    }

    function hdlr_ifhasthumbnail ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        $model = $args['model'];
        if ( $model == 'asset' ) return true;
        $scheme = $app->get_scheme_from_db( $model );
        $props = $scheme['edit_properties'];
        if ( is_array( $props ) ) {
            foreach ( $props as $prop => $type ) {
                if ( $type == 'file' ) {
                    $options = isset( $scheme['options'] ) ? $scheme['options'] : [];
                    if ( !empty( $options ) ) {
                        if ( isset( $options[ $prop ] )
                            && $options[ $prop ] == 'image' ) {
                            return true;
                        } else {
                            return true;
                        }
                    } else {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    function hdlr_ifcomponent ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        if ( isset( $args['component'] ) ) {
            $component = $app->component( $args['component'] );
            if ( is_object( $component ) ) {
                return true;
            }
        }
        return false;
    }

    function hdlr_iftagged ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        $current_context = $ctx->stash( 'current_context' );
        $obj = $ctx->stash( $current_context );
        if (! $obj ) return false;
        $tag = isset( $args['tag'] ) ? $args['tag'] : '';
        if (! $tag && isset( $args['name'] ) ) $tag = $args['name'];
        if ( $tag ) {
            $terms = ['name' => $tag ];
            if ( $obj->has_column( 'workspace_id' ) && $obj->workspace_id ) {
                $terms['workspace_id'] = $obj->workspace_id;
            } else {
                $terms['workspace_id'] = 0;
            }
            $tag_obj = $app->db->model( 'tag' )->load( $terms );
            if (! $tag_obj ) return false;
            $from_id = $obj->id;
            $terms = ['from_id'  => $obj->id, 'to_id' => $tag_obj->id,
                      'from_obj' => $obj->_model, 'to_obj' => 'tag' ];
            $cnt = $app->db->model( 'relation' )->count( $terms );
            return $cnt ? true : false;
        }
        if ( isset( $args['include_private'] ) && $args['include_private'] ) {
            $relations = $app->get_relations( $obj, 'tag' );
            if ( $relations && count( $relations ) ) return true;
        } else {
            $terms = ['from_id'  => $obj->id, 'from_obj' => $obj->_model,
                      'to_obj' => 'tag', 'name' => ['not like' => '@%'] ];
            $cnt = $app->db->model( 'relation' )->count( $terms );
            return $cnt ? true : false;
        }
        return false;
    }

    function hdlr_ifobjectexists ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        $model = isset( $args['model'] ) ? $args['model'] : null;
        $column = isset( $args['column'] ) ? $args['column'] : 'id';
        $value = isset( $args['value'] ) ? $args['value'] : null;
        if (! $model ) return false;
        if (! $value ) return false;
        $obj = $app->db->model( $model )->new();
        if (! $obj->has_column( $column ) ) {
            return false;
        }
        $terms = [ $column => $value ];
        $workspace_id = isset( $args['workspace_id'] )
                      ? (int) $args['workspace_id'] : 0;
        if ( $obj->has_column( 'workspace_id' ) ) {
            $workspace_id = (int) $workspace_id;
            $terms['workspace_id'] = $workspace_id;
        }
        $object = $app->db->model( $model )->get_by_key( $terms );
        if ( $object->id ) {
            return true;
        }
        return false;
    }

    function hdlr_ifusercan ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        if ( $user = $app->user() ) {
            $action = isset( $args['action'] ) ? $args['action'] : 'edit';
            $model = isset( $args['model'] ) ? $args['model'] : null;
            if ( isset( $args['workspace_id'] )
                && $args['workspace_id'] == 'any' && $model && $action ) {
                $permissions = $app->permissions();
                if ( empty( $permissions ) ) {
                    return false;
                }
                foreach ( $permissions as $perms ) {
                    if ( in_array( 'workspace_admin', $perms )
                        || in_array( "can_{$action}_{$model}", $perms ) ) {
                        return true;
                    }
                }
            } else {
                $workspace_id = isset( $args['workspace_id'] )
                          ? (int) $args['workspace_id'] : 0;
            }
            $workspace_id = (int) $workspace_id;
            $id = isset( $args['id'] ) ? (int) $args['id'] : null;
            $obj = null;
            $workspace = null;
            if ( $model ) {
                $obj = $id ? $app->db->model( $model )->load( $id )
                           : $app->db->model( $model )->new();
                if (! $obj ) $obj = $app->db->model( $model )->new();
                if (! $obj->id && $workspace_id && $obj->has_column( 'workspace_id' ) ) {
                    $obj->workspace_id( $workspace_id );
                }
                if ( $obj->has_column( 'workspace_id' ) && $obj->workspace_id ) {
                    $workspace = $obj->workspace;
                }
            }
            if ( $workspace_id && ! $workspace ) {
                $workspace = $app->db->model( 'workspace' )->load( $workspace_id );
            }
            if ( $action == 'list' ) {
                $table = $app->get_table( $model );
                if ( $table->dialog_view ) {
                    return true;
                }
            }
            if (! $workspace ) 
                $workspace = $app->db->model( 'workspace' )->new( ['id' => 0 ] );
            return $app->can_do( $model, $action, $obj, $workspace );
        }
        return false;
    }

    function hdlr_phpstart ( $args, $ctx ) {
        return '<?php';
    }

    function hdlr_phpend ( $args, $ctx ) {
        return '?>';
    }

    function hdlr_get_objectpath ( $args, $ctx ) {
        $app = $ctx->app;
        $current_context = $ctx->stash( 'current_context' );
        $obj = $ctx->stash( $current_context );
        $column = isset( $args['column'] ) ? $args['column'] : 'basename';
        $separator = isset( $args['separator'] ) ? $args['separator'] : '';
        $cache_key = 'objectpath_' . $column . '_' . $separator
                   . '_' . $current_context . '_' . $obj->id;
        if (! $separator ) $separator = '/';
        if ( $app->stash( $cache_key ) ) {
            return $app->stash( $cache_key );
        }
        $parent = $obj;
        $paths = [ $parent->$column ];
        while ( $parent !== null ) {
            if ( $parent_id = $parent->parent_id ) {
                $parent_id = (int) $parent_id;
                $parent = $app->db->model( $current_context )->get_by_key
                    ( $parent_id, [], "id,{$column},parent_id" );
                if ( $parent->id ) {
                    array_unshift( $paths, $parent->$column );
                } else {
                    $parent = null;
                }
            } else {
                $parent = null;
            }
        }
        $path = implode( $separator, $paths );
        $app->stash( $cache_key, $path );
        return $path;
    }

    function hdlr_geturlprimary ( $args, $ctx ) {
        $app = $ctx->app;
        $id = isset( $args['id'] ) ? (int) $args['id'] : null;
        if (! $id ) return '';
        $url = $app->db->model( 'urlinfo' )->load( $id );
        if (! $url ) return '(null)';
        if (! $url->model && ! $url->object_id ) {
            return '(null)';
        }
        $table = $app->get_table( $url->model );
        if (! $table ) return '(null)';
        $primary = $table->primary;
        $cols = "id,{$primary}";
        $id = (int) $url->object_id;
        $object = $app->db->model( $url->model )->get_by_key(
            ['id' => $id ], ['limit' => 1], $cols );
        $name = $object->$primary ? $object->$primary : "null(id:{$id})";
        return $name;
    }

    function hdlr_getactivity ( $args, $ctx ) {
        $app = $ctx->app;
        $from = isset( $args['from'] ) ? $args['from'] : date( "YmdHis" );
        $model = isset( $args['model'] ) ? $args['model'] : 'log';
        $column = isset( $args['column'] ) ? $args['column'] : 'created_on';
        $limit = isset( $args['limit'] ) ? $args['limit'] : 30;
        $limit += 0;
        if (! $limit ) return;
        $sort_order = isset( $args['sort_order'] ) ? $args['sort_order'] : 'ascend';
        $glue = isset( $args['glue'] ) ? $args['glue'] : ',';
        $data = isset( $args['data'] ) ? $args['data'] : '';
        $key = isset( $args['key'] ) ? $args['key'] : '';
        $sort_order = strtolower( $sort_order );
        $sort_order = $sort_order == 'ascend' ? 'ASC' : 'DESC';
        $format = isset( $args['format'] ) ? $args['format'] : 'm/d';
        $table = $app->get_table( $model );
        if (! $table ) return;
        $obj = $app->db->model( $model );
        if (! $obj->has_column( $column ) ) return;
        $from = preg_replace( "/[^0-9]/", '', $from );
        if (! preg_match( "/^[0-9]{14}$/", $from ) ) {
            $from = date( "YmdHis" );
        }
        $_from = $obj->ts2db( $from );
        $extra = '';
        $workspace_id = isset( $args['workspace_id'] ) ? $args['workspace_id'] : '';
        if ( $workspace_id && $obj->has_column( 'workspace_id' ) ) {
            $workspace_id += 0;
            $extra = " WHERE {$model}_workspace_id={$workspace_id} ";
            $extra.= " AND {$model}_{$column}<='{$_from}' ";
        } else {
            $extra = " WHERE {$model}_{$column}<='{$_from}' ";
        }
        if ( $table->revisable ) {
            $extra.= " AND {$model}_rev_type=0 ";
        }
        $dbprefix = DB_PREFIX;
        $sql = "SELECT DATE_FORMAT({$model}_{$column}, '%Y%m%d000000') AS time, COUNT(*) ";
        $sql.= "AS count FROM mt_{$model} {$extra} GROUP BY DATE_FORMAT({$model}_{$column}, '%Y%m%d000000') ";
        $sql.= "ORDER BY time {$sort_order} LIMIT {$limit}";
        $activities = $obj->load( $sql );
        $_activities = [];
        foreach ( $activities as $activity ) {
            $values = $activity->get_values();
            $_activities[ $values['time'] ] = (int) $values['count'];
        }
        $stmt = $sort_order == 'ASC' ? '-' : '+';
        $from = substr( $from, 0, 8 );
        $tmp_time = "{$from}000000";
        for ( $i = 0; $i < $limit; $i++ ) {
            $next = date( "YmdHis", strtotime("{$tmp_time} {$stmt}1 day" ));
            if (! isset( $_activities[ $next ] ) ) {
                $_activities[ $next ] = 0;
            }
            $tmp_time = $next;
        }
        if ( $sort_order == 'ASC' ) {
            ksort( $_activities );
        } else {
            krsort( $_activities );
        }
        $activities = [];
        foreach ( $_activities as $time => $count ) {
            $time = date( $format, strtotime( $time ) );
            $activities["'{$time}'"] = $count;
        }
        if ( $limit < count( $activities ) ) {
            if ( $sort_order == 'DESC' ) {
                $activities = array_slice( $activities, 0, $limit );
            } else {
                $offset = count( $activities ) - $limit - 1;
                $activities = array_slice( $activities, $offset, null );
            }
        }
        if ( $key ) {
            return implode( $glue, array_keys( $activities ) );
        } else if ( $data ) {
            return implode( $glue, array_values( $activities ) );
        } else {
            return implode( $glue, array_keys( $activities ) );
        }
    }

    function hdlr_customfieldvalues ( $args, $content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $localvars = ['customfield_value'];
        if (! $counter ) {
            $model = isset( $args['model'] ) ? $args['model'] : '';
            $basename = isset( $args['basename'] ) ? $args['basename'] : '';
            if (! $model || ! $basename ) {
                return '';
            }
            $obj = $ctx->stash( $model );
            if (! $obj ) return '';
            $meta = $obj->_customfields;
            if ( $meta === null ) {
                $app->get_meta( $obj, 'customfield', true );
                $meta = $obj->_customfields;
            }
            if (! $meta || empty( $meta ) ) {
                return '';
            }
            if ( isset( $meta[ $basename ] ) ) {
                $meta = $meta[ $basename ];
            } else {
                $repeat = $ctx->false();
                return;
            }
            $index = isset( $args['index'] ) ? (int) $args['index'] : null;
            if ( $index ) {
                if (! isset( $meta[ $index ] ) ) {
                    $repeat = $ctx->false();
                    return;
                } else {
                    $meta = [ $meta[ $index ] ];
                }
            }
            $params = [];
            foreach ( $meta as $field ) {
                $text = $field->text;
                if (! $text ) continue;
                $json = json_decode( $text, true );
                if ( $json !== null && count( $json ) == 1 ) {
                    $key = array_keys( $json )[0];
                    $value = $json[ $key ];
                    if ( is_array( $value ) ) {
                        $params = array_merge( $params, $value );
                    } else {
                        $params[] = $value;
                    }
                } else if ( $json !== null ) {
                    $params[] = $text;
                }
            }
            $ctx->localize( $localvars );
            $ctx->local_params = $params;
        }
        $params = $ctx->local_params;
        $ctx->set_loop_vars( $counter, $params );
        if ( isset( $params[ $counter ] ) ) {
            $value = $params[ $counter ];
            $ctx->stash( 'customfield_value', $value );
            $repeat = true;
        } else {
            $ctx->restore( $localvars );
            $repeat = false;
        }
        return $content;
    }

    function hdlr_currenturlmappingvalue ( $args, $ctx ) {
        $mapping = $ctx->stash( 'current_urlmapping' );
        $column = isset( $args['column'] ) ? $args['column'] : 'model';
        if (! $mapping ) return;
        if ( $mapping->has_column( $column ) ) {
            return $mapping->$column;
        }
    }

    function hdlr_websiteid ( $args, $ctx ) {
        $workspace = $ctx->stash( 'workspace' );
        return $workspace ? $workspace->id : '0';
    }

    function hdlr_websitename ( $args, $ctx ) {
        $workspace = $ctx->stash( 'workspace' );
        return $workspace ? $workspace->name : $ctx->app->appname;
    }

    function hdlr_websiteurl ( $args, $ctx ) {
        $workspace = $ctx->stash( 'workspace' );
        return $workspace ? $workspace->site_url : $ctx->app->site_url;
    }

    function hdlr_websitepath ( $args, $ctx ) {
        $workspace = $ctx->stash( 'workspace' );
        return $workspace ? $workspace->site_path : $ctx->app->site_path;
    }

    function hdlr_websitelanguage ( $args, $ctx ) {
        $workspace = $ctx->stash( 'workspace' );
        return $workspace ? $workspace->language : $ctx->app->sys_language;
    }

    function hdlr_websitecopyright ( $args, $ctx ) {
        $workspace = $ctx->stash( 'workspace' );
        return $workspace ? $workspace->copyright : $ctx->app->copyright;
    }

    function hdlr_websitedescription ( $args, $ctx ) {
        $workspace = $ctx->stash( 'workspace' );
        if ( $workspace ) {
            return $workspace->description;
        }
        $description = $ctx->app->get_config( 'description' );
        if ( is_object( $description ) ) return $description->value;
    }

    function hdlr_hex2rgba ( $args, $ctx ) {
        $color_code = isset( $args['hex'] ) ? $args['hex'] : '';
        $alpha = isset( $args['alpha'] ) ? $args['alpha'] : 0.5;
        $color_code = preg_replace( '/#/', '', $color_code );
        $rgba_code = [];
        $rgba_code['red']   = hexdec( substr( $color_code, 0, 2 ) );
        $rgba_code['green'] = hexdec( substr( $color_code, 2, 2 ) );
        $rgba_code['blue']  = hexdec( substr( $color_code, 4, 2 ) );
        $rgba_code['alpha'] = $alpha;
        return implode( ',', $rgba_code );
    }

    function hdlr_pluginsetting ( $args, $ctx ) {
        $app = $ctx->app;
        $component = isset( $args['component'] ) ? $args['component'] : '';
        $name = isset( $args['name'] ) ? $args['name'] : '';
        if (! $component || ! $name ) return '';
        $component = $app->component( $component );
        if (! $component ) return '';
        $workspace_id = isset( $args['workspace_id'] ) ? $args['workspace_id'] : 0;
        return $component->get_config_value( $name, (int) $workspace_id );
    }

    function hdlr_customfieldcount ( $args, $ctx ) {
        $model = isset( $args['model'] ) ? $args['model'] : '';
        $basename = isset( $args['basename'] ) ? $args['basename'] : '';
        if (! $model ) {
            return 0;
        }
        $obj = $ctx->stash( $model );
        if (! $obj ) return 0;
        $meta = $obj->_customfields;
        $app = $ctx->app;
        if ( $meta === null ) {
            $app->get_meta( $obj, 'customfield', true );
            $meta = $obj->_customfields;
        }
        if ( $basename && isset( $meta[ $basename ] ) ) {
            return count( $meta[ $basename ] );
        } else if (! $basename ) {
            $cf_count = 0;
            foreach ( $meta as $m ) {
                $cf_count += count( $m ); 
            }
            return $cf_count;
        }
        return 0;
    }

    function hdlr_customfieldvalue ( $args, $ctx ) {
        $app = $ctx->app;
        if (! isset( $args['name'] ) && isset( $args['key'] ) ) {
            $args['name'] = $args['key'];
        }
        if ( $ctx->stash( 'customfield_value' ) ) {
            if ( isset( $args['name'] ) && $args['name'] ) {
                $json = json_decode( $ctx->stash( 'customfield_value' ), true );
                if ( $json !== null && isset( $json[ $args['name'] ] ) ) {
                    $value = $json[ $args['name'] ];
                    if ( isset( $args['index'] ) && is_array( $value ) ) {
                        $index = (int) $args['index'];
                        return isset( $value[ $index ] ) ? $value[ $index ] : '';
                    }
                    return is_array( $value ) ? json_encode( $value ) : $value;
                }
            }
            return $ctx->stash( 'customfield_value' );
        }
        $model = isset( $args['model'] ) ? $args['model'] : '';
        $basename = isset( $args['basename'] ) ? $args['basename'] : '';
        if (! $model || ! $basename ) {
            return '';
        }
        $obj = $ctx->stash( $model );
        if (! $obj ) return '';
        $meta = $obj->_customfields;
        if ( $meta === null ) {
            $app->get_meta( $obj, 'customfield', true );
            $meta = $obj->_customfields;
        }
        if (! $meta || empty( $meta ) ) {
            return '';
        }
        if ( isset( $meta[ $basename ] ) ) {
            $index = isset( $args['index'] ) ? (int) $args['index'] : 0;
            if (! isset( $meta[ $basename ][ $index ] ) ) return '';
            $meta = $meta[ $basename ][ $index ];
            if ( $meta->value ) {
                return $meta->value;
            }
            $text = $meta->text;
            $json = json_decode( $text, true );
            if ( $json !== null && count( $json ) == 1 ) {
                $key = array_keys( $json )[0];
                return is_string( $json[ $key ] )
                        ? $json[ $key ] : json_encode( $json[ $key ] );
                if ( is_array( $json[ $key ] ) ) {
                    return json_encode( $json[ $key ] );
                }
                return $json[ $key ];
            } else if ( $json !== null && isset( $args['name'] )
                && isset( $json[ $args['name'] ] ) ) {
                if ( is_array( $json[ $args['name'] ] ) ) {
                    return json_encode( $json[ $args['name'] ] );
                }
                return $json[ $args['name'] ];
            }
            return $text;
        }
    }

    function hdlr_setrolecolumns ( $args, $ctx ) {
        $data = isset( $args['data'] ) ? $args['data'] : '';
        if ( $data ) {
            $data = json_decode( $data, true );
            foreach ( $data as $model => $cols ) {
                if ( is_string( $cols ) && $cols == 'all' ) {
                    $ctx->local_vars["columns_all_{$model}"] = 1;
                } else {
                    foreach ( $cols as $col ) {
                        if ( is_string( $col ) ) {
                            $ctx->local_vars["columns_{$model}_{$col}"] = 1;
                        }
                    }
                }
            }
        }
    }

    function hdlr_archivetitle ( $args, $ctx ) {
        $app = $ctx->app;
        $fmt = isset( $args['format'] ) ? $args['format'] : 0;
        $format_ts = isset( $args['format_ts'] ) ? $args['format_ts'] : 0;
        $title = $ctx->stash( 'current_archive_title' );
        $ts = $title;
        $ts = preg_replace( "/[^0-9]/", '', $ts );
        $at = $ctx->stash( 'current_archive_type' );
        if (! $format_ts && !$fmt ) {
            if ( $at === 'monthly' ) {
                $fmt = $app->translate( 'F, Y' );
                if ( strlen( $ts ) === 6 ) {
                    $ts .= '01000000';
                }
            } else if ( $at === 'yearly' ) {
                $fmt = $app->translate( 'Y' );
                if ( strlen( $ts ) === 4 ) {
                    $ts .= '0101000000';
                }
                return $ts;
            } else if ( $at === 'fiscal-yearly' ) {
                $fmt = $app->translate( '\F\i\s\c\a\l Y' );
                if ( strlen( $ts ) === 4 ) {
                    $ts .= '0101000000';
                }
            } else if ( $at === 'daily' ) {
                $fmt = $app->translate( 'F d, Y' );
                if ( strlen( $ts ) === 8 ) {
                    $ts .= '0101000000';
                }
            }
        } else if ( strpos( $at, 'yearly' ) !== false && strlen( $ts ) === 4 ) {
            $ts .= '0101000000';
            if (! $fmt ) {
                $fmt = $at == 'yearly' ? $app->translate( 'Y' ) : $app->translate( '\F\i\s\c\a\l Y' );
            }
        } else if ( $at == 'monthly' && strlen( $ts ) === 6 ) {
            $ts .= '01000000';
            if (! $fmt ) {
                $fmt = $app->translate( 'F, Y' );
            }
        } else if ( $at == 'daily' && strlen( $ts ) === 8 ) {
            $ts .= '000000';
            if (! $fmt ) {
                $fmt = $app->translate( 'F d, Y' );
            }
        }
        if ( $fmt && ! $format_ts ) {
            $args['ts'] = $ts;
            $args['format'] = $fmt;
            $ts = $ctx->function_date( $args, $ctx );
        }
        return $ts;
    }

    function hdlr_getchildrenids ( $args, $ctx ) {
        $app = $ctx->app;
        $id = isset( $args['id'] ) ? (int) $args['id'] : null;
        $model = isset( $args['model'] ) ? $args['model'] : null;
        if (! $id || ! $model ) return [];
        $obj = $app->db->model( $model )->load( $id );
        if (! $obj ) return [];
        $children = [];
        $children = $app->get_children( $obj, $children );
        $children_ids = [];
        foreach ( $children as $child ) {
            $children_ids[] = $child->id;
        }
        return $children_ids;
    }

    function hdlr_archivedate ( $args, $ctx ) {
        $ts = $ctx->stash( 'current_timestamp' );
        $args['ts'] = $ts;
        return $ctx->function_date( $args, $ctx );
    }

    function hdlr_archivelink ( $args, $ctx ) {
        return $ctx->stash( 'current_archive_link' );
    }

    function hdlr_statustext ( $args, $ctx ) {
        $app = $ctx->app;
        $int = isset( $args['status'] ) ? $args['status'] : null;
        $options = isset( $args['options'] ) ? $args['options'] : '';
        $icon = isset( $args['icon'] ) ? $args['icon'] : false;
        $text = isset( $args['text'] ) ? $args['text'] : false;
        $model = isset( $args['model'] ) ? $args['model'] : '';
        if (! $options && $model ) {
            $table = $app->get_table( $model );
            if ( $table ) {
                $col = $app->db->model( 'column' )->get_by_key(
                ['table_id' => $table->id, 'name' => 'status']);
                $options = $col->options;
            }
        }
        if ( $int === null || ! $options ) return;
        $options = explode( ',', $options );
        if ( count( $options ) === 2 ) {
            if (! $int ) return;
            $status = $options[ $int - 1 ];
        } else {
            $int = (int) $int;
            if (! isset( $options[ $int ] ) ) return;
            $status = $options[ $int ];
        }
        if ( strpos( $status, ':' ) !== false ) {
            list( $status, $option ) = explode( ':', $status );
        }
        $status = $app->translate( $status );
        if ( $icon ) {
            $tmpl = '<i class="status_icon fa fa-__" aria-hidden="true"></i>&nbsp;';
            $tmpl .= $text ? $status : "<span class=\"sr-only\">$status</span>";
            if ( count( $options ) === 2 ) {
                $int--;
                $icons = ['pencil-square-o', 'check-square-o'];
            } else {
                $icons = ['pencil', 'pencil-square',
                    'check-circle', 'clock-o' , 'check-square-o', 'calendar-times-o'];
            }
            if ( isset( $icons ) && isset( $icons[ $int ] ) ) {
                return str_replace( '__', $icons[ $int ], $tmpl );
            }
        }
        return $status;
    }

    function hdlr_include ( $args, $ctx, $no_build = false ) {
        $app = $ctx->app;
        $f = isset( $args['file'] ) ? $args['file'] : '';
        $m = isset( $args['module'] ) ? $args['module'] : '';
        $b = isset( $args['basename'] ) ? $args['basename'] : '';
        $workspace_id = isset( $args['workspace_id'] ) ? $args['workspace_id'] : 0;
        $cache_key = isset( $args['cache_key'] ) ? $args['cache_key'] : '';
        $request_id = $app->request_id;
        $db_cache_key = md5( "template-module-{$cache_key}-{$request_id}" );
        if ( $cache_key && $app->stash( "template-module-{$cache_key}" ) ) {
            return $app->stash( "template-module-{$cache_key}" );
        } else if ( $cache_key && !$app->no_cache ) {
            $session = $app->db->model( 'session' )->get_by_key( [
                           'name' => $db_cache_key, 
                           'kind' => 'CH',
                           'key'  => $cache_key ] );
            if ( $session->id && ( $session->expires > time() ) ) return $session->data;
        }
        if (! $f && ! $m && ! $b ) return '';
        if ( $f ) {
            $f = str_replace( '/', DS, $f );
            $sep = preg_quote( DS, '/' );
            $template_paths = $app->template_paths;
            foreach ( $template_paths as $path ) {
                $path = preg_replace( "/$sep$/", '', $path ) . DS;
                if ( file_exists( $path . $f ) ) {
                    $f = $path . $f;
                    break;
                }
            }
            if (!$f = $ctx->get_template_path( $f ) ) return '';
            if (!$tmpl = file_get_contents( $f ) ) return '';
        } else {
            $current_context = $ctx->stash( 'current_context' );
            $obj = $ctx->stash( $current_context );
            $terms = $m ? ['name' => $m, 'status' => 2, 'rev_type' => 0]
                        : ['basename' => $b, 'status' => 2, 'rev_type' => 0];
            $tmpl = [];
            $current_template = $ctx->stash( 'current_template' );
            $template_workspace_id = 0;
            if ( $workspace_id ) {
                $terms['workspace_id'] = $workspace_id;
                $tmpl = $app->db->model( 'template' )->load( $terms );
            }
            if ( empty( $tmpl ) && $current_template ) {
                if ( $ws_id = $current_template->workspace_id ) {
                    if ( $workspace_id != $ws_id ) {
                        $terms['workspace_id'] = $ws_id;
                        $tmpl = $app->db->model( 'template' )->load( $terms );
                    }
                }
            }
            if ( empty( $tmpl ) ) {
                $tmpl = $app->db->model( 'template' )->load(
                    ['name' => $m, 'workspace_id' => 0, 'status' => 2, 'rev_type' => 0 ] );
            }
            if ( empty( $tmpl ) ) return '';
            $tmpl = $tmpl[0];
            $_compiled = $tmpl->compiled;
            $_cache_key = $tmpl->cache_key;
            if ( $_compiled && $_cache_key ) {
                $ctx->compiled[ $_cache_key ] = $_compiled;
            }
            $app->modules[ $tmpl->id ] = $tmpl->get_values();
            if ( $no_build ) return;
            $tmpl = $tmpl->text;
        }
        if ( $no_build || ! $tmpl ) return '';
        $local_args = $args;
        unset( $local_args['file'] );
        unset( $local_args['module'] );
        unset( $local_args['cace_key'] );
        unset( $local_args['workspace_id'] );
        $old_vars = [];
        foreach ( $local_args as $k => $v ) {
            if ( isset( $ctx->vars[ $k ] ) ) {
                $old_vars[ $k ] = $ctx->vars[ $k ];
            }
            $ctx->vars[ $k ] = $v;
        }
        // if ( stripos( $tmpl, 'setvartemplate' ) !== false ) {
        //     $ctx->compile( $tmpl, false );
        // }
        $build = $ctx->build( $tmpl );
        foreach ( $old_vars as $k => $v ) {
            $ctx->vars[ $k ] = $v;
        }
        if ( $cache_key && !$app->no_cache ) {
            $app->stash( "template-module-{$cache_key}", $build );
            $session = $app->db->model( 'session' )->get_by_key( [
                           'name'  => $db_cache_key, 
                           'kind'  => 'CH',
                           'value' => $request_id,
                           'key'   => $cache_key ] );
            $session->start( time() );
            $session->expires( time() + $app->token_expires );
            $session->data( $build );
            $session->save();
        }
        return $build;
    }

    function hdlr_includeparts ( $args, $ctx ) {
        $app = $ctx->app;
        $screen = $args['screen'];
        $type = $args['type'];
        $model = $args['model'];
        $name = $args['name'];
        $template_paths = $app->template_paths;
        $sep = preg_quote( DS, '/' );
        $tmpl_path = 'include' . DS . $screen . DS;
        $tmpl_path .= $model . DS . 'column_' . $name . '.tmpl';
        foreach ( $template_paths as $path ) {
            $path = preg_replace( "/$sep$/", '', $path ) . DS;
            if ( file_exists( $path . $tmpl_path ) ) {
                $file_path = $path . $tmpl_path;
                break;
            }
        }
        if ( isset( $file_path ) && file_exists( $file_path ) ) {
            return $ctx->build( file_get_contents( $file_path ) );
        }
        if ( $type === 'column' ) {
            $common_path = 'include' . DS . $screen . DS . 'column_' . $name;
            foreach ( $template_paths as $path ) {
                $path = preg_replace( "/$sep$/", '', $path ) . DS;
                $common_tmpl = $path . $common_path . '.tmpl';
                if ( file_exists( $common_tmpl ) ) {
                    break;
                }
            }
        }
        if ( file_exists( $common_tmpl ) ) {
            return $ctx->build( file_get_contents( $common_tmpl ) );
        }
    }

    function hdlr_property ( $args, $ctx ) {
        $app = $ctx->app;
        $name = isset( $args['name'] ) ? $args['name'] : '';
        if ( $name && isset( $app->$name ) && strpos( $name, 'password' ) === false ) {
            return $app->$name;
        }
    }

    function hdlr_gettableid ( $args, $ctx ) {
        $app = $ctx->app;
        $name = isset( $args['name'] ) ? $args['name'] : '';
        if (! $name )
            $name = isset( $args['model'] ) ? $args['model'] : '';
        if (! $name ) return '';
        $table = $app->get_table( $name );
        $column = isset( $args['column'] ) ? $args['column'] : 'id';
        if ( is_object( $table ) ) return $table->$column;
    }

    function hdlr_objectvar ( $args, $ctx ) {
        $app = $ctx->app;
        $col = isset( $args['name'] ) ? $args['name'] : '';
        if (! $col ) return;
        if ( isset( $ctx->vars['forward_params'] ) ) {
            $value = isset( $ctx->__stash['forward_' . $col ] )
                ? $ctx->__stash['forward_' . $col ] : '';
            return $value;
        }
        if ( is_array( $col ) ) {
            $key = key( $col );
            $col = $col[ $key ];
        }
        if ( isset( $ctx->local_vars['object_' . $col ] ) ) 
            return $ctx->local_vars['object_' . $col ];
        $obj = $ctx->stash( 'object' )
             ? $ctx->stash( 'object' ) : null;
        if (! $obj ) {
            $obj = isset( $ctx->local_vars['__value__'] )
                 ? $ctx->local_vars['__value__'] : null;
        }
        if (! is_object( $obj ) ) {
            $id = isset( $args['id'] ) ? $args['id'] : '';
            $model = isset( $args['model'] ) ? $args['model'] : '';
            if ( $id && $model ) {
                $obj = $app->db->model( $model )->load( (int) $id );
            }
        }
        if (! is_object( $obj ) ) {
            return;
        }
        if ( $obj && $obj->has_column( $col ) ) {
            if ( $id = $obj->id ) {
                $cache_key = 'can_edit_' . $obj->_model . '_' . $id;
                $perm = $app->stash( $cache_key );
                if ( $perm === null ) {
                    $perm = $app->can_do( $obj->_model, 'edit', $obj );
                    $app->stash( $cache_key, $perm );
                }
                $ctx->local_vars['can_edit_object'] = $perm;
            }
            return $obj->$col;
        }
        if ( $obj && $col === 'permalink' ) {
            return $app->get_permalink( $obj );
        }
        return '';
    }

    function hdlr_getoption ( $args, $ctx ) {
        $app = $ctx->app;
        $key  = $args['key'];
        $kind = isset( $args['kind'] ) ? $args['kind'] : 'config';
        $obj = ( $kind === 'config' )
             ? $app->get_config( $key )
             : $app->db->model( 'option' )->load( ['kind' => $kind, 'key' => $key ] );
        if ( is_array( $obj ) && !empty( $obj ) ) {
            $obj = $obj[0];
        }
        if (! $obj ) return;
        return $obj->value;
    }

    function hdlr_assetproperty ( $args, $ctx ) {
        $app = $ctx->app;
        $property = isset( $args['property'] ) ? $args['property'] : '';
        $model = isset( $args['model'] ) ? $args['model'] : $app->param( 'model' );
        $name = isset( $args['name'] ) ? $args['name'] : 'file';
        if ( isset( $args['id'] ) && $args['id'] ) {
            $id = (int) $args['id'];
            $obj = $ctx->app->db->model( $model )->load( $id );
        }
        $current_context = $ctx->stash( 'current_context' );
        if (! isset( $obj ) && $current_context ) {
            $obj = $ctx->stash( $current_context );
        }
        $obj = isset( $obj ) ? $obj : $ctx->stash( $model );
        if (! $obj ) {
            $obj = $ctx->stash( 'workspace' );
        }
        $thumb_width = isset( $args['thumbnail_height'] ) ? $args['thumbnail_height'] : '';
        if ( isset( $ctx->vars['forward_params'] ) ) {
            $screen_id = $app->param( '_screen_id' );
            $attachmentfile = isset( $args['attachmentfile'] ) ? $args['attachmentfile'] : null;
            if ( $attachmentfile ) {
                $name = $attachmentfile;
            }
            $session_name = "{$screen_id}-{$name}";
            $session = $app->db->model( 'session' )->get_by_key(
                        ['name' => $session_name, 'user_id' => $app->user()->id ] );
            if ( $session->id ) {
                $ctx->stash( 'current_session_' . $name, $session );
                $assetproperty = $app->get_assetproperty( $obj, $name, $property );
                if ( $thumb_width ) {
                    if (! $assetproperty ) return;
                    $scale = $thumb_width / $assetproperty;
                    $height = $scale * $app->get_assetproperty( $obj, $name, 'image_height' );
                    return (int) $height;
                }
                return $assetproperty;
            }
        }
        if (! $obj || ! $obj->id ) return;
        $assetproperty = $app->get_assetproperty( $obj, $name, $property );
        if ( $thumb_width ) {
            if (! $thumb_width || ! $assetproperty ) {
                return;
            }
            $scale = $thumb_width / $assetproperty;
            $height = $scale * $app->get_assetproperty( $obj, $name, 'image_height' );
            return (int) $height;
        }
        return $assetproperty;
    }

    function hdlr_assetthumbnailurl ( $args, $ctx, &$meta = null ) {
        $app = $ctx->app;
        $current_context = $ctx->stash( 'current_context' );
        $obj = $ctx->stash( $current_context );
        if (! $obj ) return;
        require_once( 'class.PTUtil.php' );
        $url = PTUtil::thumbnail_url( $obj, $args );
        return $url;
    }

    function hdlr_assetthumbnail ( $args, $ctx, &$meta = null ) {
        $app = $ctx->app;
        $type = isset( $args['type'] ) ? $args['type'] : '';
        $model = isset( $args['model'] ) ? $args['model'] : $app->param( 'model' );
        $name = isset( $args['name'] ) ? $args['name'] : null;
        $square = isset( $args['square'] ) ? $args['square'] : null;
        $id = isset( $args['id'] ) ? $args['id'] : null;
        if (!$name && $model && $id ) {
            if ( $table = $app->get_table( $model ) ) {
                $columns = $app->db->model( 'column' )->load(
                 ['model' => $model, 'table_id' => $table->id, 'edit' => 'file'],
                 ['sort' => 'order', 'direction' => 'ascend'] );
                if (! empty( $columns ) ) {
                    foreach( $columns as $column ) {
                        if (! $column->options || $column->options === 'image' ) {
                            $name = $column->name;
                            break;
                        }
                    }
                }
            }
        }
        if (!$model ) $model = $ctx->stash( 'current_context' );
        if (!$model || !$name ) return;
        $data_uri = isset( $args['data_uri'] ) ? $args['data_uri'] : null;
        if (! $id ) {
            $obj = $ctx->stash( $model );
            if ( is_array( $obj ) ) $obj = $obj[0];
            if (! $obj && ! isset( $ctx->vars['forward_params'] ) ) return;
            $id = $obj ? $obj->id : 0;
        }
        $session = $ctx->stash( 'current_session_' . $name );
        if (! $id && ! isset( $ctx->vars['forward_params'] ) && ! $session) return;
        $data = '';
        $dynamic = isset( $args['dynamic'] ) ? $args['dynamic'] : null;
        if ( isset( $ctx->vars['forward_params'] ) || $session ) {
            $id = isset( $obj ) ? $obj->id : 0;
            $screen_id = $ctx->vars['screen_id'];
            $attachmentfile = isset( $args['attachmentfile'] ) ? $args['attachmentfile'] : null;
            if ( $attachmentfile ) {
                $name = $attachmentfile;
            }
            $screen_id .= '-' . $name;
            $cache = $ctx->stash( $model . '_session_' . $screen_id . '_' . $id );
            if (! $session ) {
                $session = $cache ? $cache : $app->db->model( 'session' )->get_by_key(
                ['name' => $screen_id, 'user_id' => $app->user()->id ] );
            }
            $ctx->stash( $model . '_session_' . $screen_id . '_' . $id, $session );
            if ( $session->id ) {
                if ( $type === 'default' ) {
                    if ( $square ) {
                        $data = $session->extradata;
                    } else {
                        $data = $session->metadata;
                    }
                    if ( $meta = $session->text ) {
                        $meta = json_decode( $meta, true );
                        $mime_type = $meta['mime_type'];
                    }
                }
                if ( $dynamic ) {
                    $data = base64_encode( $data );
                    $data = "data:{$mime_type};base64,{$data}";
                }
            }
        }
        $id = (int) $id;
        if (! $data && $type === 'default' ) {
            $cache = $ctx->stash( $model . '_meta_' . $name . '_' . $id );
            $metadata = $cache ? $cache : $app->db->model( 'meta' )->get_by_key(
                     ['model' => $model, 'object_id' => $id,
                      'kind' => 'metadata', 'key' => $name ] );
            $ctx->stash( $model . '_meta_' . $name . '_' . $id, $metadata );
            if (! $metadata->id ) {
                return;
            }
            if ( $dynamic ) {
                return $app->admin_url . '?__mode=get_thumbnail&id=' . $metadata->id;
            }
            if ( $meta = $metadata->text ) {
                $meta = json_decode( $meta, true );
                $mime_type = $meta['mime_type'];
            }
            if ( $square ) {
                $data = $metadata->metadata;
                if (! $data ) $data = $metadata->data;
            } else {
                $data = $metadata->data;
            }
        }
        if (! isset( $obj ) || !$obj ) {
            $obj = $app->db->model( $model )->load( $id );
            $ctx->stash( $model, $obj );
        }
        /*
        if (! $data && $type !== 'default' ) {
            // TODO
        }
        */
        if ( $data_uri && $data ) {
            $data = base64_encode( $data );
            $data = "data:{$mime_type};base64,{$data}";
        }
        return $data;
    }

    function hdlr_getobjectlabel ( $args, $ctx ) {
        $app = $ctx->app;
        $id = isset( $args['id'] ) ? (int) $args['id'] : null;
        $model = isset( $args['model'] ) ? $args['model'] : null;
        $_primary = isset( $args['primary'] ) ? $args['primary'] : null;
        if ( $_primary && $model ) {
            $table = $app->get_table( $model );
            if (! $table ) return;
            return $table->primary;
        }
        if (! $id || ! $model ) return;
        $obj = $app->db->model( $model )->load( $id );
        if (! $obj ) return;
        $column = isset( $args['column'] ) ? $args['column'] : null;
        if ( $column ) return $obj->$column;
        $table = $app->get_table( $obj->_model );
        if (! $table ) return;
        $primary = $table ? $table->primary : '';
        if (! $primary ) {
            if ( $obj->has_column( 'title' ) ) {
                $primary = 'title';
            } else if ( $obj->has_column( 'name' ) ) {
                $primary = 'name';
            } else if ( $obj->has_column( 'label' ) ) {
                $primary = 'label';
            }
        }
        return $obj->$primary;
    }

    function hdlr_getobjectname ( $args, $ctx ) {
        $app = $ctx->app;
        $type = isset( $args['type'] ) ? $args['type'] : '';
        if ( is_array( $type ) ) {
            $key = key( $type );
            $type = $type[ $key ];
        }
        $properties = explode( ':', $type );
        $model = isset( $properties[1] ) ? $properties[1] : '';
        $col = isset( $properties[2] ) ? $properties[2] : '';
        $model = (! $model && isset( $args['model'] ) ) ? $args['model'] : $model;
        $id = $args['id'];
        $id = (int) $id;
        if ( $model === '__any__' ) {
            $from_obj = $ctx->stash( 'object' )
                 ? $ctx->stash( 'object' ) : $ctx->local_vars['__value__'];
            $to_model = '';
            if ( $from_obj->has_column( 'model' ) ) {
                $to_model = $from_obj->model;
                $table = $app->get_table( $from_obj->model );
            } else if ( $from_obj->has_column( 'table_id' ) ) {
                $table = $app->db->model( 'table' )->load( (int) $from_obj->table_id );
                if ( $table ) {
                    $to_model = $to_model->name;
                }
            }
            if (! $to_model || ! $id || ! $table ) return;
            $rel_obj = $app->db->model( $to_model )->load( $id );
            if (! $rel_obj ) return;
            if ( $col == '__primary__' ) {
                $col = $table->primary;
            } else {
                $col = $rel_obj->has_column( 'title' ) ? 'title' : '';
                if (! $col ) $rel_obj->has_column( 'name' ) ? 'name' : '';
                if (! $col ) $rel_obj->has_column( 'label' ) ? 'label' : '';
            }
            if (! $col ) return;
            return $rel_obj->$col;
        }
        $name = isset( $args['name'] ) ? $args['name'] : '';
        $col = isset( $args['wants'] ) ? $args['wants'] : $col;
        if (! $id && $name ) {
            $this_model = isset( $args['model'] ) ? $args['model'] : '';
            $scheme = $app->get_scheme_from_db( $this_model );
            if ( isset( $scheme['relations'] ) ) {
                $from_id = isset( $args['from_id'] ) ? $args['from_id'] : '';
                if ( isset( $scheme['relations'][$name] ) && $from_id ) {
                    $obj = $app->db->model( $this_model )->new();
                    $obj->id = $from_id;
                    $relations = $app->get_relations( $obj, $model, $name );
                    $names = [];
                    foreach ( $relations as $r ) {
                        $rel_obj = $app->db->model( $model )->load( $r->to_id );
                        if ( is_object( $rel_obj ) && $rel_obj->has_column( $col ) ) {
                            $names[] = $ctx->modifier_truncate(
                                                        $rel_obj->$col, '10+...', $ctx );
                        }
                    }
                    return !empty( $names ) ? join( ', ', $names ) : '';
                }
            }
        }
        if (! $id ) return '';
        if ( $obj = $app->stash( "{$model}:{$id}" ) ) {
            return $obj->$col;
        }
        $obj = $app->db->model( $model )->load( $id );
        $app->stash( "{$model}:{$id}", $obj );
        if ( is_object( $obj ) ) {
            return $obj->$col;
        }
    }

    function hdlr_fieldloop ( $args, $content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        require_once( 'class.PTUtil.php' );
        if (! $counter ) {
            $model = $ctx->params['context_model'] ? $ctx->params['context_model'] : '';
            if (! $model ) $model = isset( $args['model'] ) ? $args['model'] : '';
            if (! $model ) {
                $repeat = $ctx->false();
                return;
            }
            $id = isset( $args['id'] ) ? (int) $args['id'] : '';
            $obj = $id ? $app->db->model( $model )->load( $id ) 
                       : $app->db->model( $model )->new();
            $object_fields = [];
            $meta = [];
            if ( isset( $ctx->vars['forward_params'] ) ) {
                $_obj_fields = PTUtil::get_fields( $model, 'types' );
                foreach ( $_obj_fields as $fld => $fieldtype ) {
                    $fld_value = $app->param( "{$fld}__c" );
                    $unique_ids = $app->param( "field-unique_id-{$fld}" );
                    if ( $fld_value !== null ) {
                        if (! is_array( $fld_value ) ) $fld_value = [ $fld_value ];
                        if (! is_array( $unique_ids ) ) $unique_ids = [ $unique_ids ];
                        $i = 0;
                        foreach ( $fld_value as $value ) {
                            $unique_id = $unique_ids[ $i ];
                            $i++;
                            if ( $value ) {
                                $fld_values = json_decode( $value, true );
                                if ( is_array( $fld_values ) ) {
                                    foreach ( $fld_values as $key => $val ) {
                                        if ( strpos( $key, $unique_id . '_' ) === 0 ) {
                                            $new_key = preg_replace( "/^{$unique_id}_/", '', $key );
                                            unset( $fld_values[ $key ] );
                                            $fld_values[ $new_key ] = $val;
                                        }
                                    }
                                }
                                $value = json_encode( $fld_values, JSON_UNESCAPED_UNICODE );
                                $meta_obj = $app->db->model( 'meta' )->get_by_key(
                                    ['object_id' => $id, 'model' => $obj->_model,
                                     'kind' => 'customfield', 'key' => $fld, 'number' => $i ] );
                                $meta_obj->text( $value );
                                $meta_obj->type( $fieldtype );
                                if ( count( $fld_values ) == 1 ) {
                                    $meta_key = key( $fld_values );
                                    $meta_obj->name( $meta_key );
                                    $meta_obj->value( $fld_values[ $meta_key ] );
                                } else {
                                    $meta_obj->value( '' );
                                }
                                $meta[] = $meta_obj;
                            }
                        }
                    }
                }
            } else if ( $id && $obj ) {
                $meta = $app->get_meta( $obj, 'customfield' );
            }
            if (! empty( $meta ) ) {
                foreach ( $meta as $cf ) {
                    $basename = $cf->key;
                    $custom_fields = isset( $object_fields[ $basename ] )
                                   ? $object_fields[ $basename ] : [];
                    $custom_fields[] = $cf;
                    $object_fields[ $basename ] = $custom_fields;
                }
            }
            $fields = PTUtil::get_fields( $model, $args );
            if ( empty( $fields ) ) {
                $repeat = $ctx->false();
                return;
            }
            $ctx->local_params = $fields;
            $ctx->stash( 'object_fields', $object_fields );
        }
        $params = $ctx->local_params;
        $object_fields = $ctx->stash( 'object_fields' );
        if ( empty( $params ) ) {
            $repeat = $ctx->false();
            return;
        }
        $ctx->set_loop_vars( $counter, $params );
        if ( isset( $params[ $counter ] ) ) {
            $repeat = true;
            $field = $params[ $counter ];
            $prefix = $field->_colprefix;
            $values = $field->get_values();
            $field_label = $field->label;
            $field_content = $field->content;
            if (! $field_content ) {
                $field_type = $field->fieldtype;
                if ( $field_type ) {
                    if (! $field_label ) $field_label = $field_type->label;
                    if (! $field_content ) $field_content = $field_type->content;
                }
            }
            unset( $ctx->local_vars['field__display'] );
            unset( $ctx->local_vars['field__html'] );
            $restore_vars = ['field_name', 'field_required', 'field_basename',
                             'field_options', 'field_uniqueid', 'field_label_html',
                             'field_content_html'];
            $ctx->local_vars['field_name'] = $field->translate
                ? $app->translate( $field->name ) : $field->name;
            $ctx->local_vars['field_required'] = $field->required;
            $basename = $field->basename;
            $ctx->local_vars['field_basename'] = $basename;
            $options = $field->options;
            $display = $field->display;
            if ( $options ) {
                $labels = $field->options_labels;
                $options = preg_split( '/\s*,\s*/', $options );
                $labels = $labels ? preg_split( '/\s*,\s*/', $labels ) : $options;
                if ( $field->translate_labels ) {
                    $trans_labels = [];
                    foreach ( $labels as $label ) {
                        $trans_labels[] = $app->translate( $label );
                    }
                    $labels = $trans_labels;
                }
                $i = 0;
                $field_options = [];
                foreach ( $options as $option ) {
                    $label = isset( $labels[ $i ] ) ? $labels[ $i ] : $option;
                    $field_options[] = ['field_label' => $label, 'field_option' => $option ];
                    $i++;
                }
                $ctx->vars['field_options'] = $field_options;
            }
            foreach ( $values as $key => $value ) {
                $key = preg_replace( "/^$prefix/", '', $key );
                if ( $field->translate && $key == 'name' ) {
                    $value = $app->translate( $value );
                }
                $ctx->local_vars[ 'field__' . $key ] = $value;
            }
            $ctx->local_vars['field__hide_label'] = $field->hide_label;
            $field_out = false;
            $field_contents = '';
            if (! empty( $object_fields ) ) {
                $content_tmpl = file_get_contents(
                    $ctx->get_template_path( 'field' . DS . 'content.tmpl' ) );
                $label_tmpl = file_get_contents(
                    $ctx->get_template_path( 'field' . DS . 'label.tmpl' ) );
                $wrapper_tmpl = file_get_contents(
                    $ctx->get_template_path( 'field' . DS . 'wrapper.tmpl' ) );
                $basename = $field->basename;
                if ( isset( $object_fields[ $basename ] ) ) {
                    $fields = $object_fields[ $basename ];
                    $field_counter = 0;
                    foreach ( $fields as $custom_field ) {
                        $set_keys = [];
                        if ( $custom_field->text ) {
                            $vars = json_decode( $custom_field->text, true );
                            if ( is_array( $vars ) ) {
                                foreach ( $vars as $key => $value ) {
                                    $ctx->local_vars['field.' . $key ] = $value;
                                    $set_keys[] = 'field.' . $key;
                                }
                            }
                        }
                        if (! $field_counter && $display ) {
                            $ctx->local_vars['field__not_delete'] = 1;
                        } else {
                            $ctx->local_vars['field__not_delete'] = 0;
                        }
                        $ctx->local_vars['field__out'] = $field_out;
                        $uniqueid = $app->magic();
                        $ctx->local_vars['field_uniqueid'] = $uniqueid;
                        $_fld_content = $ctx->build( $field_content );
                        $ctx->local_vars['field_content_html'] = $_fld_content;
                        $_fld_content = $ctx->build( $content_tmpl );
                        if (! $field->hide_label ) {
                            $_field_label = $ctx->build( $field_label );
                            $ctx->local_vars['field_label_html'] = $_field_label;
                            $_field_label = $ctx->build( $label_tmpl );
                            $ctx->local_vars['field_label_html'] = $_field_label;
                        }
                        $ctx->local_vars['field_content_html'] = $_fld_content;
                        $_fld_content = $ctx->build( $wrapper_tmpl );
                        PTUtil::add_id_to_field( $_fld_content, $uniqueid, $basename );
                        $field_contents .= $_fld_content;
                        foreach ( $set_keys as $key ) {
                            unset( $ctx->local_vars[ $key ] );
                        }
                        $field_out = true;
                        $display = true;
                        $field_counter++;
                    }
                }
            }
            if (! $field_out ) {
                $uniqueid = $app->magic();
                if ( $display ) {
                    $ctx->local_vars['field__not_delete'] = 1;
                } else {
                    $ctx->local_vars['field__not_delete'] = 0;
                }
                $ctx->local_vars['field_uniqueid'] = $uniqueid;
                if (! $field->hide_label ) {
                $field_label = $app->tmpl_markup === 'mt' ? $ctx->build( $field_label )
                                                          : $app->build( $field_label, $ctx );
                $ctx->local_vars['field_label_html'] = $field_label;
                $src = file_get_contents( TMPL_DIR . 'field' . DS . 'label.tmpl' );
                $field_label = $ctx->build( $src );
                $ctx->local_vars['field_label_html'] = $field_label;
                }
                $_fld_content = $app->tmpl_markup === 'mt' ? $ctx->build( $field_content )
                                                           : $app->build( $field_content, $ctx );
                $ctx->local_vars['field_content_html'] = $_fld_content;
                $src = file_get_contents( TMPL_DIR . 'field' . DS . 'content.tmpl' );
                $field_contents = $ctx->build( $src );
                PTUtil::add_id_to_field( $_fld_content, $uniqueid, $basename );
                $ctx->local_vars['field_content_html'] = $field_contents;
                $src = file_get_contents( TMPL_DIR . 'field' . DS . 'wrapper.tmpl' );
                $field_contents = $ctx->build( $src );
            }
            $field_contents = "<div id=\"field-{$basename}-wrapper\">{$field_contents}</div>";
            $src = file_get_contents( TMPL_DIR . 'field' . DS . 'footer.tmpl' );
            $field_contents .= $ctx->build( $src );
            $ctx->local_vars[ 'field__html' ] = $field_contents;
            foreach ( $restore_vars as $key ) {
                unset( $ctx->local_vars[ $key ] );
            }
            $ctx->local_vars['field__display'] = $display;
            if ( $display && ! $field->multiple ) {
                unset( $ctx->local_vars['field__menu'] );
            } else {
                $ctx->local_vars['field__menu'] = true;
            }
        }
        return ( $counter > 1 && isset( $args['glue'] ) )
            ? $args['glue'] . $content : $content;
    }

    function hdlr_referencecontext ( $args, $content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $this_tag = $args['this_tag'];
        $reference_tags = $ctx->stash( 'reference_tags' );
        $model = null;
        if ( isset( $reference_tags[ $this_tag ] ) ) {
            $prop = $reference_tags[ $this_tag ];
            $name = $prop[1];
        } else {
            $name = isset( $args['name'] ) ? $args['name'] : '';
            $model = isset( $args['model'] ) ? $args['model'] : '';
        }
        if (! $name && ! $model ) {
            $repeat = $ctx->false();
            return;
        }
        $localvars = ['current_context', 'reference_obj', 'object'];
        if (! $counter ) {
            $sess = null;
            if ( $model ) {
                $obj_id = isset( $args['id'] ) ? $args['id'] : '';
                if (! $obj_id ) {
                    $repeat = $ctx->false();
                    return;
                }
                $ref_model = $model;
            } else {
                $current_context = $reference_tags[ $this_tag ][0];
                $scheme = $app->get_scheme_from_db( $current_context );
                $obj = $ctx->stash( $current_context );
                if (! $obj && $current_context == 'template' ) {
                    $obj = $ctx->stash( 'current_template' );
                }
                if (! $obj ) {
                    $repeat = $ctx->false();
                    return;
                }
                if (! $obj->has_column( $name ) ) {
                    $name = strtolower( $name );
                    $labels = $scheme['labels'];
                    foreach ( $labels as $key => $val ) {
                        $val = strtolower( $val );
                        if ( $name == $val ) {
                            $name = $key;
                            break;
                        }
                    }
                }
                $preview_template = $ctx->stash( 'preview_template' );
                $in_preview = false;
                if ( $app->param( '_preview' ) && ! $preview_template ) {
                    $in_preview = true;
                }
                $props = $scheme['edit_properties'];
                $prop = $props[ $name ];
                $props = explode( ':', $prop );
                $ref_model = $props[1];
                if ( $in_preview && $ref_model == 'attachmentfile' ) {
                    $sess_name = $app->screen_id . "-{$name}";
                    $sess = $app->db->model( 'session' )->get_by_key(
                        ['name' => $sess_name, 'user->id' => $app->user()->id ] );
                }
                if (! $ref_model ) {
                    $repeat = $ctx->false();
                    return;
                }
                $obj_id = $obj->$name;
            }
            if ( isset( $args['force'] ) && $args['force'] ) {
                $app->db->caching = false;
            }
            $ref_obj = null;
            if (! $sess ) {
                if (! $obj_id ) {
                    $repeat = $ctx->false();
                    return;
                }
                $ref_obj = $app->db->model( $ref_model )->load( (int)$obj_id );
            } else if ( $sess->id ) {
                $ref_obj = PTUtil::session_to_attachmentfile( $sess, $obj );
            }
            if (! $ref_obj ||! is_object( $ref_obj ) ) {
                $repeat = $ctx->false();
                return;
            }
            $app->init_callbacks( $ref_model, 'post_load_object' );
            $callback = ['name' => 'post_load_object', 'model' => $ref_model ];
            $app->run_callbacks( $callback, $model, $ref_obj );
            $localvars[] = $ref_model;
            $ctx->localize( $localvars );
            $ctx->stash( 'reference_obj', $ref_model );
            $ctx->stash( 'current_context', $ref_model );
            $ctx->stash( $ref_model, $ref_obj );
            $ctx->stash( 'object', $ref_obj );
        }
        $ref_model = $ctx->stash( 'reference_obj' );
        if ( $counter ) {
            $localvars[] = $ref_model;
            $ctx->restore( $localvars );
            $repeat = false;
        }
        return $content;
    }

    function hdlr_get_relatedobjs ( $args, $content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $this_tag = $args['this_tag'];
        $block_relations = $ctx->stash( 'block_relations' );
        $current_context = $ctx->stash( 'current_context' );
        $settings = $block_relations[ $this_tag ];
        $model  = $settings[1];
        $to_obj = $settings[2];
        $colname = $settings[0];
        $obj = $ctx->stash( $model );
        $preview_template = $ctx->stash( 'preview_template' );
        $in_preview = false;
        if ( $app->param( '_preview' ) && ! $preview_template ) {
            $in_preview = true;
        }
        if (! $counter ) {
            $orig_args = $args;
            $include_draft = isset( $args['include_draft'] )
                           ? true : false;
            $objects = [];
            $ctx->localize( [ $model, 'current_context', 'to_object'] );
            if ( $in_preview ) {
                if ( $to_obj === '__any__' ) {
                    $to_obj = $app->param( "_{$colname}_model" );
                }
                $rels = $app->param( $colname ) ? $app->param( $colname ) : [];
                $i = -1;
                $insert_sessions = [];
                $ids = [];
                foreach ( $rels as $id ) {
                    if ( $id ) $i++;
                    if (! ctype_digit( $id ) && ! is_numeric( $id ) ) {
                        if ( $to_obj == 'attachmentfile' && $id ) {
                            $id = str_replace( 'session-', '', $id );
                            $sess = $app->db->model( 'session' )->load( (int) $id );
                            if ( $sess ) {
                                $insert_sessions[ $i ] = $sess;
                            }
                        }
                        continue;
                    }
                    if ( $id ) $ids[] = (int) $id;
                }
                if ( empty( $rels ) || ( empty( $ids ) && empty( $insert_sessions ) ) ) {
                    $ctx->restore( [ $model, 'current_context', 'to_object'] );
                    $repeat = $ctx->false();
                    return;
                }
                $terms = [];
                $objects = [];
                if (! empty( $ids ) ) {
                    $ids = array_unique( $ids );
                    $terms = ['id' => ['in' => $ids ] ];
                    if (! $include_draft ) {
                        $status = $app->status_published( $to_obj );
                        if ( $status ) {
                            $terms['status'] = $status;
                        }
                    }
                    $params = [];
                    if ( isset( $args['limit'] ) ) {
                        $params['limit'] = (int) $args['limit'];
                    }
                    $order_sort = true;
                    if ( isset( $args['offset'] ) ) {
                        $params['offset'] = (int) $args['offset'];
                    }
                    if ( isset( $args['sort_by'] ) ) {
                        $params['sort'] = $args['sort_by'];
                        $order_sort = false;
                    }
                    if ( isset( $args['sort_order'] ) ) {
                        $params['direction'] = $args['sort_order'];
                    }
                    $to_model = $app->db->model( $to_obj );
                    foreach ( $args as $arg => $v ) {
                        if ( $to_model->has_column( $arg ) ) {
                            $terms[ $arg ] = $v;
                        }
                    }
                    $objects = $app->db->model( $to_obj )->load( $terms, $params );
                    if ( $order_sort && count( $objects ) > 1 ) {
                        $arr = [];
                        foreach ( $objects as $obj ) {
                            $arr[ (int) $obj->id ] = $obj;
                        }
                        $objects = [];
                        foreach ( $ids as $id ) {
                            if ( isset( $arr[ $id ] ) ) {
                                $objects[] = $arr[ $id ];
                            }
                        }
                    }
                }
                if ( $to_obj == 'attachmentfile' ) {
                    if (! empty( $insert_sessions ) ) {
                        $preview_objs = [];
                        $i = 0;
                        if ( empty( $objects ) ) {
                            foreach ( $insert_sessions as $sess ) {
                                $preview_objs[] =
                                    PTUtil::session_to_attachmentfile( $sess, $obj );
                            }
                        } else {
                            foreach ( $objects as $existing_obj ) {
                                if ( isset( $insert_sessions[$i] ) ) {
                                    $sess = $insert_sessions[$i];
                                    $preview_objs[] =
                                        PTUtil::session_to_attachmentfile( $sess, $obj, $i + 1 );
                                }
                                $preview_objs[] = $existing_obj;
                                $i++;
                            }
                        }
                        $objects = $preview_objs;
                    }
                }
                if ( empty( $objects ) ) {
                    $ctx->restore( [ $model, 'current_context', 'to_object'] );
                    $repeat = $ctx->false();
                    return;
                }
            } else {
                if ( $to_obj === '__any__' ) {
                    if ( $obj->has_column( 'model' ) ) {
                        $to_obj = $obj->model;
                    } else if ( $obj->has_column( 'table_id' ) ) {
                        $table = $app->db->model( 'table' )->load( (int) $obj->table_id );
                        if ( $table ) {
                            $to_obj = $table->name;
                        }
                    }
                }
                if ( $to_obj === '__any__' ) {
                    $ctx->restore( [ $model, 'current_context', 'to_object'] );
                    $repeat = $ctx->false();
                    return;
                }
                $params = [];
                if (! $include_draft ) {
                    $params['published_only'] = true;
                }
                if ( isset( $args['limit'] ) ) {
                    $params['limit'] = (int) $args['limit'];
                }
                if ( isset( $args['offset'] ) ) {
                    $params['offset'] = (int) $args['offset'];
                }
                if ( isset( $args['sort_by'] ) ) {
                    $params['sort'] = $args['sort_by'];
                }
                if ( isset( $args['sort_order'] ) ) {
                    $params['direction'] = $args['sort_order'];
                }
                $terms = [];
                $to_model = $app->db->model( $to_obj );
                foreach ( $args as $arg => $v ) {
                    if ( $to_model->has_column( $arg ) ) {
                        $terms[ $arg ] = $v;
                    }
                }
                $select_cols = isset( $args['cols'] ) ? $args['cols'] : '*';
                if ( isset( $args['cols'] ) ) {
                    $select_cols = $this->select_cols( $app, $to_model, $select_cols );
                }
                $objects = $app->get_related_objs( $obj, $to_obj, $colname,
                                                   $params, $terms, $select_cols );
                $app->init_callbacks( $to_obj, 'post_load_objects' );
                $callback = ['name' => 'post_load_objects', 'model' => $to_obj ];
                $count_obj = count( $objects );
                $app->run_callbacks( $callback, $model, $objects, $count_obj );
            }
            if ( isset( $orig_args['__object_count'] ) ) {
                $ctx->restore( [ $model, 'current_context', 'to_object'] );
                return count( $objects );
            }
            $scheme = $app->db->scheme;
            if ( empty( $objects ) ) {
                $repeat = $ctx->false();
                $ctx->restore( [ $model, 'current_context', 'to_object'] );
                return;
            }
            $ctx->local_params = $objects;
            $ctx->stash( 'to_object', $to_obj );
        }
        $params = $ctx->local_params;
        $to_obj = $ctx->stash( 'to_object' );
        $ctx->set_loop_vars( $counter, $params );
        if ( isset( $params[ $counter ] ) ) {
            $obj = $params[ $counter ];
            $ctx->stash( $to_obj, $obj );
            $ctx->stash( 'current_context', $to_obj );
            $repeat = true;
        } else {
            $ctx->restore( [ $model, 'current_context', 'to_object'] );
        }
        return ( $counter > 1 && isset( $args['glue'] ) )
            ? $args['glue'] . $content : $content;
    }

    function hdlr_get_relationscount ( $args, $ctx ) {
        $content = null;
        $repeat = false;
        $counter = 0;
        $args['__object_count'] = true;
        $this_tag = $args['this_tag'];
        $this_tag = preg_replace( '/count$/', '', $this_tag );
        $args['this_tag'] = $this_tag;
        return $this->hdlr_get_relatedobjs( $args, $content, $ctx, $repeat, $counter );
    }

    function hdlr_get_objectcol ( $args, $ctx ) {
        $app = $ctx->app;
        $this_tag = $args['this_tag'];
        $current_context = $ctx->stash( 'current_context' );
        $function_tags = $ctx->stash( 'function_tags' );
        if ( isset( $function_tags[ $this_tag ] ) ) {
            list( $model, $col ) = $function_tags[ $this_tag ];
            $obj = $ctx->stash( $model );
            if ( isset( $obj ) && $obj->has_column( $col ) ) {
                return $obj->$col;
            }
        }
        if ( strpos( $this_tag, 'template' ) === 0 ) {
            if ( $current_context !== 'template' ) {
                $col_name = preg_replace( "/^template/", '', $this_tag );
                $template = $ctx->stash( 'current_template' );
                if ( $template->has_column( $col_name ) ) {
                    return $template->$col_name;
                }
            }
        }
        $workspace_tags = $ctx->stash( 'workspace_tags' );
        if ( $workspace_tags && in_array( $this_tag, $workspace_tags ) ) {
            $current_context = 'workspace';
        }
        $function_relations = $ctx->stash( 'function_relations' );
        $function_date = $ctx->stash( 'function_date' );
        $alias_functions = $ctx->stash( 'alias_functions' );
        if( isset( $alias_functions[ $this_tag ] ) ) {
            $this_tag = $alias_functions[ $this_tag ];
        }
        if ( $function_relations && isset( $function_relations[ $this_tag ] ) ) {
            $settings = $function_relations[ $this_tag ];
            $column = $settings[0];
        }
        $permalink_tags = $ctx->stash( 'permalink_tags' );
        if ( isset( $permalink_tags[ $this_tag ] ) ) {
            $current_context = $permalink_tags[ $this_tag ];
            $obj = $ctx->stash( $permalink_tags[ $this_tag ] );
            if ( $obj ) return $app->get_permalink( $obj, false, true );
        }
        $obj = $ctx->stash( $current_context );
        if (! $obj && $current_context == 'workspace' ) {
            if ( $this_tag == 'workspacename' ) {
                return $ctx->app->appname;
            } else if ( $this_tag == 'workspaceid' ) {
                return '0';
            }
        }
        if (! $obj ) return;
        $column = isset( $column ) ? 
            $column : preg_replace( "/^{$current_context}/", '', $this_tag );
        if (! $obj->has_column( $column ) ) {
            $column = str_replace( '_', '', $column );
        }
        if ( $obj->has_column( $column ) ) {
            $column = $obj->_colprefix . $column;
            $value = $obj->$column;
            if ( $value && isset( $settings ) ) {
                $value = (int) $value;
                $app = $ctx->app;
                $obj = $app->db->model( $settings[2] )->load( $value );
                $col = isset( $args['wants'] ) ? $args['wants'] : $settings[3];
                if ( $obj && $obj->has_column( $col ) ) {
                    $col = $obj->_colprefix . $col;
                    $value = $obj->$col;
                }
            }
            if ( $function_date && in_array( $this_tag, $function_date ) ) {
                $args['ts'] = $value;
                return $ctx->function_date( $args, $ctx );
            }
            return $value;
        }
        if ( $this_tag == $obj->_model . 'permalink' 
            || $this_tag == $obj->_model . 'archivelink' ) {
            return $app->get_permalink( $obj, false, true );
        }
    }

    function hdlr_columnproperty ( $args, $ctx ) {
        $app = $ctx->app;
        $model = isset( $args['model'] ) ? strtolower( $args['model'] ) : '';
        $name = isset( $args['name'] ) ? strtolower( $args['name'] ) : '';
        $property = isset( $args['property'] ) ? strtolower( $args['property'] ) : '';
        if (! $name || ! $model ||! $property ) return '';
        $table = $app->get_table( $model );
        if (! $table ) return;
        $column = $app->db->model( 'column' )->get_by_key( ['name' => $name ] );
        if ( $column->has_column( $property ) ) {
            return $column->$property;
        }
    }

    function hdlr_get_objecturl ( $args, $ctx ) {
        $app = $ctx->app;
        $this_tag = $args['this_tag'];
        $fileurl_tags = $ctx->stash( 'fileurl_tags' );
        $key = $fileurl_tags[ $this_tag ];
        $current_context = $ctx->stash( 'current_context' );
        $workspace_tags = $ctx->stash( 'workspace_tags' );
        if ( $workspace_tags && in_array( $this_tag, $workspace_tags ) ) {
            $current_context = 'workspace';
        }
        $in_preview = false;
        if ( $app->param( '_preview' ) ) {
            $in_preview = true;
            $sess_name = $app->param( '_screen_id' );
            $sess_name = "{$sess_name}-{$key}";
            $session = $app->db->model( 'session' )->get_by_key( ['name' => $sess_name ] );
            if( $session->id ) {
                $params = '?__mode=get_temporary_file&amp;data=1&amp;id=session-' . $session->id;
                return $app->admin_url . $params;
            }
        }
        $obj = $ctx->stash( $current_context );
        if (! $obj ) return;
        if (! is_object( $obj ) ) return;
        $urlinfo = $app->db->model( 'urlinfo' )->get_by_key(
            ['model' => $obj->_model, 'object_id' => $obj->id,
             'key' => $key, 'class' => 'file' ] );
        if (! $urlinfo->id ) {
            if ( $app->user() ) {
                $admin_url = $app->admin_url;
                if ( strpos( $admin_url, 'http' ) !== 0
                    && strpos( $admin_url, '/' ) === 0 ) {
                    $admin_url = $app->base . $admin_url;
                }
                if ( isset( $obj->__session ) && $obj->_model == 'attachmentfile' ) {
                    $session = $obj->__session;
                    $params = '?__mode=get_temporary_file&amp;data=1&amp;id=session-' . $session->id;
                    return $admin_url . $params;
                } else if ( $in_preview || $app->force_dynamic ) {
                    $params = "?__mode=view&view={$key}&_type=edit&_model={$current_context}&id=" . $obj->id;
                    return $admin_url . $params;
                }
            }
            return '';
        }
        return $urlinfo->url;
    }

    function hdlr_container_count ( $args, $ctx ) {
        $app = $ctx->app;
        $terms = [];
        $context = $ctx->stash( 'current_context' );
        $this_tag = $args['this_tag'];
        $count_tags = $ctx->stash( 'count_tags' );
        $at = $ctx->stash( 'current_archive_context' )
            ? $ctx->stash( 'current_archive_context' )
            : $ctx->stash( 'current_archive_type' );
        $workspace = $ctx->stash( 'workspace' );
        $model = isset( $args['container'] ) ? $args['container'] : $count_tags[ $this_tag ];
        $table = $app->get_table( $model );
        $archive_date_based = false;
        if ( $at && ( $at === 'monthly' || $at === 'yearly' || $at === 'fiscal-yearly' ) ) {
            $archive_date_based = true;
        }
        if ( $table->has_status ) {
            $include_draft = isset( $args['include_draft'] )
                           ? true : false;
            if (! $include_draft ) {
                $terms['status'] = $app->status_published( $model );
            }
        }
        if ( $table->revisable ) {
            $terms['rev_type'] = 0;
        }
        $extra = '';
        $callback = ['name' => 'pre_archive_count', 'model' => $model ];
        $app->init_callbacks( $model, 'pre_archive_count' );
        $_model = $app->db->model( $model );
        if ( $archive_date_based ) {
            $ts = $ctx->stash( 'current_timestamp' );
            if (! $ts ) return 0;
            $fiscal_start = null;
            if ( $at === 'fiscal-yearly' ) {
                $fiscal_start = isset( $args['fiscal_start'] ) ? $args['fiscal_start'] : 0;
                if (! $fiscal_start ) {
                    $map = $ctx->stash( 'current_urlmapping' );
                    if ( $map && $map->date_based === 'Fiscal-Yearly' ) {
                        $fiscal_start = $map->fiscal_start;
                    } else {
                        $map_terms = ['date_based' => 'Fiscal-Yearly'];
                        if ( $workspace ) $map_terms['workspace_id'] = $workspace->id;
                        $map = $app->db->model( 'urlmapping' )
                            ->load( $map_terms, ['limit' => 1] );
                        if ( is_array( $map ) && !empty( $map ) ) {
                            $map = $map[0];
                            $fiscal_start = $map->fiscal_start;
                        } else if ( isset( $map_terms['workspace_id'] ) ) {
                            unset( $map_terms['workspace_id'] );
                            $map = $app->db->model( 'urlmapping' )
                                ->load( $map_terms, ['limit' => 1] );
                            if ( is_array( $map ) && !empty( $map ) ) {
                                $map = $map[0];
                                $fiscal_start = $map->fiscal_start;
                            }
                        }
                    }
                }
                if (! $fiscal_start ) $fiscal_start = $app->fiscal_start;
            }
            list( $title, $start, $end ) = $app->title_start_end( $at, $ts, $fiscal_start );
            $ws_attr = '';
            if ( $_model->has_column( 'workspace_id' ) ) {
                $ws_attr = $this->include_exclude_workspaces( $app, $args );
                if ( $ws_attr ) {
                    $ws_attr = " AND {$model}_workspace_id ${ws_attr}";
                    $extra .= $ws_attr;
                }
            }
            if (! $extra && $workspace ) {
                $terms['workspace_id'] = $workspace->id;
            }
            $obj = $app->db->model( $model )->new();
            $date_col = $app->get_date_col( $obj );
            $terms[ $date_col ] = ['BETWEEN' => [ $start, $end ] ];
        } else {
            $args = [];
            $container = $ctx->stash( 'current_container' );
            $ws_attr = '';
            if ( $container != $context || $at == 'index' ) {
                if ( ( $at == 'index' )
                    || (! $container && $model )
                    || ( $container && ( $container != $model ) ) ) {
                    if ( $_model->has_column( 'workspace_id' ) ) {
                        $ws_attr = $this->include_exclude_workspaces( $app, $args );
                        if ( $ws_attr ) {
                            $ws_attr = " AND {$model}_workspace_id ${ws_attr}";
                            $extra .= $ws_attr;
                        }
                    }
                    if (! $extra && $workspace ) {
                        $terms['workspace_id'] = $workspace->id;
                    }
                    $start = $ctx->stash( 'current_timestamp' );
                    $end = $ctx->stash( 'current_timestamp_end' );
                    if ( $start && $end ) {
                        $date_based_col = $app->get_date_col( $_model );
                        $terms[ $date_based_col ] = ['BETWEEN' => [ $start, $end ] ];
                    }
                    $app->run_callbacks( $callback, $model, $terms, $args, $extra );
                    return $_model->count( $terms, [], '', $extra );
                }
            }
            $obj = $ctx->stash( $context );
            if (! $obj ) return 0;
            $relations = $app->db->model( 'relation' )->load( 
                                    ['to_id' => $obj->id, 'to_obj' => $context,
                                     'from_obj' => $model ], $args, 'from_id' );
            if ( empty( $relations ) ) return 0;
            if (! $table->has_status && ! $table->revisable )
                return count( $relations );
            $ids = [];
            foreach ( $relations as $rel ) {
                $ids[ (int) $rel->from_id ] = true;
            }
            if ( empty( $ids ) ) return 0;
            $ids = array_keys( $ids );
            $terms['id'] = ['IN' => $ids ];
        }
        if ( $model == 'tag' && !isset( $args['include_private'] )
            || ( isset( $args['include_private'] ) && !$args['include_private'] ) ) {
            $terms['name'] = ['not like' => '@%'];
        }
        $args = [];
        $app->init_callbacks( $model, 'pre_archive_count' );
        $app->run_callbacks( $callback, $model, $terms, $args, $extra );
        return $app->db->model( $model )->count( $terms, $args, '', $extra );
    }

    function hdlr_archivelist ( $args, &$content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $at = isset( $args['type'] ) ? strtolower( $args['type'] ) : '';
        if (! $at ) $at = isset( $args['archive_type'] ) ? strtolower( $args['archive_type'] ) : '';
        if (! $at ) {
            $repeat = $ctx->false();
            return;
        }
        $date_based = false;
        if ( $at === 'daily' || $at === 'monthly'
            || $at === 'yearly' || $at === 'fiscal-yearly' ) {
            $at = $at === 'fiscal-yearly' ? 'Fiscal-Yearly' : ucfirst( $at );
            $date_based = true;
        }
        $local_vars = ['current_archive_type', 'current_archive_title', 'archive_title',
                       'current_archive_link', 'current_timestamp', 'current_timestamp_end'];
        if (! $counter ) {
            $terms = [];
            $ctx->localize( $local_vars );
            $model = isset( $args['model'] ) ? $args['model'] : '';
            $container = isset( $args['container'] ) ? $args['container'] : '';
            if ( $model ) $terms['model'] = $model;
            if ( $container ) $terms['container'] = $container;
            $workspace = $ctx->stash( 'workspace' ) 
                       ? $ctx->stash( 'workspace' ) : $app->workspace();
            $workspace_id = 0;
            if ( $workspace ) {
                $terms['workspace_id'] = ['IN' => [0, $workspace->id ] ];
                $workspace_id = $workspace->id;
            } else {
                $terms['workspace_id'] = 0;
            }
            $urlmapping = isset( $args['urlmapping'] ) ? $args['urlmapping'] : '';
            if ( $urlmapping ) {
                $terms['name'] = $urlmapping;
            } else if ( $date_based ) {
                $terms['date_based'] = $at;
            } else {
                $obj = $app->db->model( $at )->new();
                if ( $obj ) {
                    $model = $model ? $model : $at;
                    $terms['model'] = $model;
                }
            }
            $_args = ['sort' => 'workspace_id', 'direction' => 'descend', 'limit' => 1];
            $urlmapping = $app->db->model( 'urlmapping' )->load( $terms, $_args );
            $fy_start = $app->fiscal_start;
            if (! empty( $urlmapping ) ) $urlmapping = $urlmapping[0];
            if ( $urlmapping && is_object( $urlmapping ) && $urlmapping->id ) {
                $model = $model ? $model : $urlmapping->model;
                $container = $container ? $container : $urlmapping->container;
                if (! $container && $date_based ) $container = 'entry';
                $workspace_id = $urlmapping->workspace_id;
                $fy_start = $urlmapping->fiscal_start;
                $ctx->stash( 'current_urlmapping', $urlmapping );
            } else {
                $repeat = $ctx->false();
                $ctx->restore( $local_vars );
                return;
            }
            $fy_end = $fy_start == 1 ? 12 : $fy_start - 1;
            require_once( 'class.PTUtil.php' );
            $archive_loop = [];
            if (! $date_based ) {
                if ( $model === 'template' ) {
                    $repeat = $ctx->false();
                    return;
                }
                $terms = [];
                $obj = $app->db->model( $model )->new();
                $table = $app->get_table( $model );
                if ( $obj->has_column( 'workspace_id' ) ) {
                    $obj->workspace_id( $workspace_id );
                }
                $load_args = [];
                if ( isset( $args['sort_order'] ) ) {
                    $sort_order = $args['sort_order'];
                    $sort_order = stripos( $sort_order, 'asc' ) === 0 ? 'ascend' : 'descend';
                    $load_args['order'] = $sort_order;
                }
                if ( isset( $args['sort_by'] ) ) {
                    $sort_by = $args['sort_by'];
                    if ( $obj->has_column( $sort_by ) ) {
                        $load_args['sort'] = $sort_by;
                    }
                }
                if ( isset( $args['limit'] ) ) {
                    $load_args['limit'] = (int) $args['limit'];
                }
                $terms = PTUtil::setup_terms( $obj, $terms );
                $objects = $app->db->model( $model )->load( $terms, $load_args );
                $title_col = isset( $args['title_col'] ) ? $args['title_col'] : $table->primary;
                foreach ( $objects as $object ) {
                    $url = $app->build_path_with_map( $object, $urlmapping, $table, null, true );
                    $archive_loop[] = [ 'archive_title' => $object->$title_col,
                                        'archive_link'  => $url ];
                }
            } else {
                $container_obj = $app->db->model( $container )->new();
                $table = $app->get_table( $container );
                $_colprefix = $container_obj->_colprefix;
                $date_col = $_colprefix
                          . $app->get_date_col( $container_obj );
                $_table = $app->db->prefix . $container;
                $day = '';
                if ( $at == 'Daily' ) {
                    $day = ", DAY({$date_col})";
                }
                $sql = "SELECT DISTINCT YEAR({$date_col}), MONTH({$date_col}){$day} FROM $_table";
                $status_published = $app->status_published( $container );
                $sql .= " WHERE ";
                $wheres = [];
                if ( $status_published ) {
                    $wheres[] = "{$_colprefix}status=$status_published";
                }
                if ( $container_obj->has_column( 'workspace_id' ) ) {
                    if ( $table->space_child && ! $workspace_id ) {
                    } else {
                        $wheres[] = "{$_colprefix}workspace_id=$workspace_id";
                    }
                }
                if ( $container_obj->has_column( 'rev_type' ) ) {
                    $wheres[] = "{$_colprefix}rev_type=0";
                }
                $app->init_callbacks( $container_obj->_model, 'pre_archive_list' );
                $callback = ['name' => 'pre_archive_list', 'model' => $container_obj->_model ];
                $app->run_callbacks( $callback, $container_obj->_model, $wheres );
                $sql .= join( ' AND ', $wheres );
                $request_id = $app->request_id;
                $cache_key = md5( "archive-list-{$sql}-{$request_id}" );
                $session = null;
                if (! $app->no_cache ) {
                    $session = $app->db->model( 'session' )->get_by_key( [
                               'name'  => $cache_key, 
                               'kind'  => 'CH',
                               'value' => $request_id,
                               'key'   => $cache_key ] );
                }
                $time_stamp = [];
                if ( $session && $session->id && ( $session->expires > time() ) ) {
                    $time_stamp = unserialize( $session->data );
                } else {
                    $year_month_day = $container_obj->load( $sql );
                    $time_stamp = [];
                    if ( $at == 'Daily' ) {
                        foreach ( $year_month_day as $ymd ) {
                            $ymd = $ymd->get_values();
                            $y = $ymd["YEAR({$date_col})"];
                            $m = $ymd["MONTH({$date_col})"];
                            $d = $ymd["DAY({$date_col})"];
                            $ts = "{$y}{$m}{$d}";
                            $time_stamp[ $ts ] = true;
                        }
                    }
                    foreach ( $year_month_day as $ym ) {
                        $ym = $ym->get_values();
                        $y = $ym["YEAR({$date_col})"];
                        $m = $ym["MONTH({$date_col})"];
                        $m = sprintf( '%02d', $m );
                        if ( $at === 'Fiscal-Yearly' ) {
                            if ( $m <= $fy_end ) $y--;
                            $time_stamp[ $y ] = true;
                        } else if ( $at === 'Yearly' ) {
                            $time_stamp[ $y ] = true;
                        } else if ( $at === 'Monthly' ) {
                            $time_stamp[ $y . $m ] = true;
                        }
                    }
                    $sort_order = 'ascend';
                    if ( isset( $args['sort_order'] ) ) {
                        $sort_order = $args['sort_order'];
                        $sort_order = stripos( $sort_order, 'asc' ) === 0 ? 'ascend' : 'descend';
                    }
                    if ( $sort_order == 'ascend' ) {
                        ksort( $time_stamp, SORT_NUMERIC );
                    } else {
                        krsort( $time_stamp, SORT_NUMERIC );
                    }
                    $time_stamp = array_keys( $time_stamp );
                    if (! $app->no_cache ) {
                        $ser = serialize( $time_stamp );
                        $session->start( time() );
                        $session->expires( time() + 120 );
                        $session->data( $ser );
                        $session->save();
                    }
                }
                $template = $urlmapping->template;
                $limit = 0;
                if ( isset( $args['limit'] ) ) {
                    $limit = (int) $args['limit'];
                }
                $i = 0;
                foreach ( $time_stamp as $time ) {
                    if ( $limit && $limit <= $i ) break;
                    $ts = '';
                    if ( $at === 'Fiscal-Yearly' ) {
                        $fy_start = sprintf( '%02d', $fy_start );
                        $ts = $time . $fy_start . '01000000';
                    } else if ( $at === 'Yearly' ) {
                        $ts = $time . '0101000000';
                    } else if ( $at === 'Monthly' ) {
                        $ts = $time . '01000000';
                    } else if ( $at === 'Daily' ) {
                        $ts = "{$y}{$m}{$d}000000";
                    }
                    $url = $app->build_path_with_map( $template, $urlmapping, $table, $ts, true );
                    $archive_loop[] = [ 'archive_title' => $time,
                                        'archive_link'  => $url,
                                        'current_timestamp' => $ts ];
                    $i++;
                }
            }
            $ctx->local_params = $archive_loop;
        }
        $params = $ctx->local_params;
        $ctx->set_loop_vars( $counter, $params );
        if ( isset( $params[ $counter ] ) ) {
            $archive = $params[ $counter ];
            $ctx->stash( 'current_archive_title', $archive['archive_title'] );
            $ctx->stash( 'current_archive_type', strtolower( $at ) );
            $ctx->stash( 'current_archive_link', $archive['archive_link'] );
            if ( isset( $archive['current_timestamp'] ) ) {
                $ctx->stash( 'current_timestamp', $archive['current_timestamp'] );
            }
            $repeat = true;
        } else {
            $ctx->restore( $local_vars );
            $repeat = false;
        }
        return ( $counter > 1 && isset( $args['glue'] ) )
            ? $args['glue'] . $content : $content;
    }

    function hdlr_nestableobjects ( $args, &$content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $model = isset( $args['model'] ) ? $args['model'] : '';
        $parent_id = isset( $args['parent_id'] ) ? $args['parent_id'] : '';
        $local_vars = [ $model, 'current_context' ];
        if (! $counter ) {
            if (! $model ) {
                $repeat = $ctx->false();
                return;
            }
            $workspace_id = isset( $args['workspace_id'] ) ? $args['workspace_id'] : null;
            $scheme = $app->get_scheme_from_db( $model );
            $obj = $app->db->model( $model );
            if (! $obj->has_column( 'parent_id' ) ) {
                $repeat = $ctx->false();
                return;
            }
            if (! $parent_id ) $parent_id = 0;
            $terms = ['parent_id' => $parent_id ];
            $workspace = $ctx->stash( 'workspace' ) 
                       ? $ctx->stash( 'workspace' ) : $app->workspace();
            if ( $workspace_id ) {
                $terms['workspace_id'] = $workspace_id;
            } else if ( $workspace ) {
                $terms['workspace_id'] = $workspace->id;
            } else if ( $obj->has_column( 'workspace_id' ) ) {
                $table = $app->get_table( $model );
                if (! $table->space_child ) {
                    $terms['workspace_id'] = 0;
                }
            }
            if ( $app->mode != 'view' ) {
                if ( $obj->has_column( 'status' ) ) {
                    $status = $app->status_published( $obj->model );
                    if ( $status ) {
                        $terms['status'] = $status;
                    }
                }
            }
            if ( $obj->has_column( 'rev_type' ) ) {
                $terms['rev_type'] = 0;
            }
            $cols = isset( $args['cols'] ) ? $args['cols'] : '*';
            if ( isset( $args['cols'] ) ) {
                $cols = $this->select_cols( $app, $obj, $cols );
            }
            $orig_args = $args;
            $args = [];
            if ( $obj->has_column( 'order' ) ) {
                $args = ['sort' => 'order', 'direction' => 'ascend'];
            }
            $table = $app->get_table( $model );
            $callback = ['name' => 'pre_listing', 'model' => $model,
                         'scheme' => $scheme, 'table' => $table,
                         'args' => $orig_args ];
            $extra = '';
            $app->init_callbacks( $model, 'pre_archive_list' );
            $app->run_callbacks( $callback, $model, $terms, $args, $extra );
            $objects = $ctx->stash( 'children_object_' . $model . '_' . $parent_id )
                ? $ctx->stash( 'children_object_' . $model . '_' . $parent_id )
                : $obj->load( $terms, $args, $cols, $extra );
            if (! is_array( $objects ) || empty( $objects ) ) {
                $repeat = $ctx->false();
                return;
            }
            $app->init_callbacks( $model, 'post_load_objects' );
            $callback = ['name' => 'post_load_objects', 'model' => $model,
                         'table' => $table ];
            $count_obj = count( $objects );
            $app->run_callbacks( $callback, $model, $objects, $count_obj );
            $ctx->localize( $local_vars );
            $ctx->local_params = $objects;
            $table = $app->get_table( $model );
            $ctx->stash( 'table', $table );
            $ctx->stash( 'current_context', $model );
        }
        $table = $ctx->stash( 'table' );
        $primary = $table->primary;
        $params = $ctx->local_params;
        $ctx->set_loop_vars( $counter, $params );
        if ( isset( $params[ $counter ] ) ) {
            $obj = $params[ $counter ];
            $ctx->stash( $model, $obj );
            $values = $obj->get_values();
            $colprefix = $obj->_colprefix;
            foreach ( $values as $key => $value ) {
                if ( $colprefix ) $key = preg_replace( "/^$colprefix/", '', $key );
                $ctx->local_vars[ $key ] = $value;
            }
            $ctx->local_vars[ 'object_label' ] = $obj->$primary;
            $args = [];
            if ( $obj->has_column( 'order' ) ) {
                $args = ['sort' => 'order', 'direction' => 'ascend'];
            }
            $children = $app->db->model( $model )->load( ['parent_id' => $obj->id ], $args );
            if ( is_array( $children ) && ! empty( $children ) ) {
                $ctx->local_vars[ 'has_children' ] = 1;
                $ctx->stash( 'children_object_' . $model . '_' . $obj->id , $children );
            } else {
                unset( $ctx->local_vars[ 'has_children' ] );
            }
            $repeat = true;
        } else {
            $ctx->restore( $local_vars );
        }
        return $content;
    }

    function hdlr_grouploop ( $args, &$content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        if (! $counter ) {
            $model = isset( $args['model'] ) ? $args['model'] : null;
            $name =  isset( $args['name'] ) ? $args['name'] : null;
            $id =  isset( $args['id'] ) ? $args['id'] : null;
            $workspace = $ctx->stash( 'workspace' );
            if (! $name && !id ) {
                $repeat = $ctx->false();
                return;
            }
            $group = null;
            if ( $id ) {
                $group = $app->db->model( 'group' )->load( $id );
            } else {
                $terms = ['name' => $name ];
                if ( $workspace ) {
                    $terms['workspace_id'] = $workspace->id;
                }
                $group = $app->db->model( 'group' )->load( $terms, ['limit' => 1] );
                if ( is_array( $group ) && count( $group ) ) {
                    $group = $group[0];
                } else {
                    $group = null;
                }
            }
            if (! $group ) {
                $repeat = $ctx->false();
                return;
            }
            $model = $group->model;
            $get_args = [];
            $include_draft = isset( $args['include_draft'] )
                ? $args['include_draft'] : false;
            if (! $include_draft ) {
                $get_args['published_only'] = true;
            }
            $related_objs = $app->get_related_objs( $group, $model, 'objects', $get_args );
            $app->init_callbacks( $model, 'post_load_objects' );
            $callback = ['name' => 'post_load_objects', 'model' => $model ];
            $count_obj = count( $related_objs );
            $app->run_callbacks( $callback, $model, $related_objs, $count_obj );
            $ctx->localize( [ $model ] );
            $ctx->local_params = $related_objs;
            $table = $app->get_table( $model );
            $ctx->stash( 'table', $table );
        }
        $table = $ctx->stash( 'table' );
        $model = $table->name;
        $primary = $table->primary;
        $params = $ctx->local_params;
        $ctx->set_loop_vars( $counter, $params );
        if ( isset( $params[ $counter ] ) ) {
            $obj = $params[ $counter ];
            $ctx->stash( $model, $obj );
            $ctx->stash( 'current_context', $obj->_model );
            $values = $obj->get_values();
            $colprefix = $obj->_colprefix;
            foreach ( $values as $key => $value ) {
                if ( $colprefix ) $key = preg_replace( "/^$colprefix/", '', $key );
                $ctx->local_vars[ $key ] = $value;
            }
            $ctx->local_vars['object_label'] = $obj->$primary;
            $repeat = true;
        } else {
            $ctx->restore( [ $model ] );
        }
        return $content;
    }

    function hdlr_tables ( $args, &$content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $type = isset( $args['type'] ) ? $args['type'] : 'display_system';
        $workspace_perm = isset( $args['workspace_perm'] ) ? $args['workspace_perm'] : null;
        $workspace_id = isset( $args['workspace_id'] ) ? $args['workspace_id'] : 0;
        if ( $workspace_perm && ! $workspace_id && $app->workspace() ) {
            $workspace_id = $app->workspace()->id;
        }
        if (! $counter ) {
            $orig_args = $args;
            $extra = '';
            $terms = [ $type => 1];
            $menu_type = isset( $args['menu_type'] ) ? $args['menu_type'] : 0;
            if ( $menu_type ) $terms['menu_type'] = (int) $menu_type;
            $permission = isset( $args['permission'] ) ? $args['permission'] : 0;
            $show_activity = isset( $args['show_activity'] ) ? $args['show_activity'] : 0;
            $models = [];
            $ws_admin = false;
            if ( $permission && !$app->user()->is_superuser ) {
                $permissions = $app->permissions();
                foreach ( $permissions as $ws_id => $perms ) {
                    if ( $workspace_id && $workspace_id != $ws_id ) continue;
                    if ( $workspace_perm && $app->workspace() ) {
                        if ( $ws_id != $app->workspace()->id ) {
                            continue;
                        }
                    }
                    if ( in_array( 'workspace_admin', $perms ) ) {
                        $ws_admin = true;
                        if ( $type == 'display_system' ) {
                            $show_tables = $app->stash( 'menu_show_tables' )
                                ? $app->stash( 'menu_show_tables' )
                                : $app->db->model( 'table' )->load( ['display_space' => 1] );
                            $app->stash( 'menu_show_tables', $show_tables );
                            foreach ( $show_tables as $show_table ) {
                                $models[ $show_table->name ] = true;
                            }
                        }
                    } else {
                        foreach ( $perms as $perm ) {
                            if ( strpos( $perm, 'can_list_' ) === 0 ) {
                                $perm = str_replace( 'can_list_', '', $perm );
                                $models[ $perm ] = true;
                            }
                        }
                    }
                }
                if (! $ws_admin ) {
                    if (! empty( $models ) ) {
                        $models = array_keys( $models );
                        $terms['name'] = ['IN' => $models ];
                    } else {
                        $repeat = $ctx->false();
                        return;
                    }
                } else if (! $workspace_perm ) {
                    if (! empty( $models ) ) {
                        $models = array_keys( $models );
                        $terms['name'] = ['IN' => $models ];
                    } else {
                        $terms['display_space'] = 1;
                    }
                    $terms['hierarchy'] = ['!=' => 1];
                    $extra = " OR ( table_menu_type=$menu_type AND table_display_system=1";
                    $extra .= " AND table_display_space=1 AND table_space_child=1 ";
                    $extra .= " AND table_hierarchy != 1 )";
                }
            }
            $im_export = isset( $args['im_export'] ) ? $args['im_export'] : 0;
            if ( $im_export ) {
                $terms['im_export'] = 1;
            }
            if ( $show_activity ) {
                $terms['show_activity'] = 1;
                if ( $app->workspace() ) {
                    $terms['display_space'] = 1;
                } else {
                    $terms['display_system'] = 1;
                }
            }
            $table_model = $app->db->model( 'table' );
            $select_cols = isset( $args['cols'] ) ? $args['cols'] : '*';
            if ( isset( $args['cols'] ) ) {
                $select_cols = $this->select_cols( $app, $table_model, $select_cols );
            }
            $args = ['sort' => 'order'];
            $cache_args = $args;
            $cache_args['extra'] = $extra;
            $app->init_callbacks( 'table', 'pre_load_objects' );
            $callback =
                ['name' => 'pre_load_objects', 'model' => 'table', 'args' => $orig_args ];
            $app->run_callbacks( $callback, 'table', $terms, $args, $select_cols, $extra );
            $cache_key = $app->make_cache_key( $terms, $cache_args, 'table' );
            $tables = $app->stash( $cache_key ) ? $app->stash( $cache_key )
                    : $table_model->load( $terms, $args, $select_cols, $extra );
            $app->stash( $cache_key, $tables );
            if (! is_array( $tables ) || empty( $tables ) ) {
                $app->stash( $cache_key, 1 );
                $repeat = $ctx->false();
                return;
            }
            $ctx->local_params = $tables;
        }
        $params = $ctx->local_params;
        $ctx->set_loop_vars( $counter, $params );
        if ( isset( $params[ $counter ] ) ) {
            $obj = $params[ $counter ];
            $values = $obj->get_values();
            $colprefix = $obj->_colprefix;
            foreach ( $values as $key => $value ) {
                if ( $colprefix ) $key = preg_replace( "/^$colprefix/", '', $key );
                $ctx->local_vars[ $key ] = $value;
            }
            $label = $app->translate( $obj->label );
            $plural = $app->translate( $obj->plural );
            $ctx->local_vars['label'] = $plural;
            $repeat = true;
        }
        return $content;
    }

    function hdlr_objectloop ( $args, &$content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $model = isset( $args['model'] ) ? $args['model'] : null;
        $this_tag = $args['this_tag'];
        $model = $model ? $model : $ctx->stash( 'blockmodel_' . $this_tag );
        $local_vars = [ $model, 'workspace', 'current_context', 'current_archive_context',
                        'current_container', 'current_workspace', 'object_count',
                        'offset_last', 'next_offset', 'current_offset', 'current_page',
                        'load_only_ids', 'select_cols'];
        $table = $model !== 'option' && $model !== 'column'
               ? $app->get_table( $model ) : null;
        if (!isset( $content ) ) {
            if (! $model ) {
                $repeat = $ctx->false();
                return;
            }
            $orig_args = $args;
            $ctx->local_vars['current_workspace'] = $ctx->stash( 'workspace' );
            $ctx->localize( $local_vars );
            $obj = $app->db->model( $model );
            $scheme = $app->get_scheme_from_db( $model );
            $loop_objects = $ctx->stash( 'loop_objects' );
            if (! $loop_objects ) {
                $table_id = isset( $args['table_id'] ) ? (int) $args['table_id'] : null;
                if ( isset( $args['table_id'] ) && !$table_id ) {
                    $repeat = $ctx->false();
                    return;
                }
                $ignore_context = isset( $args['ignore_archive_context'] ) ? 1 : 0;
                $terms = [];
                if ( $table_id ) {
                    $terms['table_id'] = $table_id;
                }
                if ( isset( $args['sort_by'] ) ) {
                    $sort_by = $args['sort_by'];
                }
                if ( isset( $sort_by ) && $obj->has_column( $sort_by ) ) {
                    $args['sort'] = $sort_by;
                }
                if ( isset( $args['sort_order'] ) ) {
                    $args['direction'] = ( stripos( $args['sort_order'], 'desc' )
                        !== false ) ? 'descend' : 'ascend';
                    if (! isset( $sort_by ) ) {
                        $sort_by = $app->get_date_col( $obj );
                        if (! $sort_by ) $sort_by = 'id';
                        $args['sort'] = $sort_by;
                    }
                }
                if ( isset( $args['offset'] ) ) {
                    $args['offset'] = (int) $args['offset'];
                }
                if ( isset( $args['limit'] ) ) {
                    if ( $args['limit'] ) {
                        $args['limit'] = (int) $args['limit'];
                    } else {
                        unset( $args['limit'] );
                    }
                }
                if ( isset( $args['options'] ) ) {
                    $options = $args['options'];
                    if ( is_array( $options ) ) {
                        $i = 1;
                        $col_opt;
                        foreach( $options as $option ) {
                            if ( $i % 2 ) {
                                $col_opt = $option;
                            } else {
                                if ( $obj->has_column( $col_opt ) ) {
                                    $terms[ $col_opt ] = $option;
                                }
                            }
                            $i++;
                        }
                    }
                }
                unset( $orig_args['table_id'], $orig_args['options'], $orig_args['limit'],
                       $orig_args['offset'], $orig_args['sort_order'], $orig_args['sort'],
                       $orig_args['sort_by'], $orig_args['this_tag'], $orig_args['model'],
                       $orig_args['ignore_archive_context'] );
                foreach( $orig_args as $key => $value ) {
                    if ( $obj->has_column( $key ) ) {
                        $terms[ $key ] = $value;
                    }
                }
                $current_template = $ctx->stash( 'current_template' );
                $container = $ctx->stash( 'current_container' );
                $context = $ctx->stash( 'current_context' );
                $context = $context == 'template' ? '' : $context;
                $has_relation = false;
                $relations = [];
                if (! $container && $app->db->model( $context )->has_column( 'model' ) ) {
                    $ctx_obj = $ctx->stash( $context );
                    $ctx_model = null;
                    if ( $ctx_obj && $ctx_obj->model ) {
                        $ctx_model = $app->get_table( $ctx_obj->model );
                    }
                    $preview_template = $ctx->stash( 'preview_template' );
                    if ( $app->param( '_preview' ) && ! $preview_template ) {
                        $_scheme = $app->get_scheme_from_db( $context );
                        if ( isset( $_scheme['relations'] ) ) {
                            $relations = $_scheme['relations'];
                            foreach ( $relations as $key => $value ) {
                                $rel_model = $app->param( "_{$key}_model" );
                                if ( $ctx_model && $rel_model &&
                                        $rel_model == $ctx_model->name ) {
                                    $preview_ids = $app->param( $key );
                                    $rel_ids = [];
                                    foreach ( $preview_ids as $id ) {
                                        if ( $id ) $rel_ids[] = (int) $id;
                                    }
                                    $terms['id'] = ['IN' => $rel_ids ];
                                }
                            }
                        }
                    } else {
                        if ( $ctx_model ) {
                            $relations = $app->db->model( 'relation' )->load( 
                                    ['from_id' => (int) $ctx_obj->id, 'from_obj' => $context,
                                     'to_obj' => $ctx_model->name ] );
                            $has_relation = true;
                            $relation_col = 'to_id';
                        }
                    }
                }
                // $relations = [];
                if ( $container && $context ) {
                    if ( $container == $model ) {
                        $to_obj = $ctx->stash( $context );
                        if ( $to_obj = $ctx->stash( $context ) ) {
                            $is_relation = false;
                            if ( isset( $scheme['relations'] ) ) {
                                $obj_rels = $scheme['relations'];
                                if ( array_search( $to_obj->_model, $obj_rels ) !== false ) {
                                    $is_relation = true;
                                }
                            }
                            if ( $is_relation ) {
                                $relations = $app->db->model( 'relation' )->load( 
                                    ['to_id' => (int) $to_obj->id, 'to_obj' => $context,
                                     'from_obj' => $obj->_model ] );
                                $has_relation = true;
                                $relation_col = 'from_id';
                                // todo check rev_type and status
                            } else {
                                $edit_properties = $scheme['edit_properties'];
                                foreach ( $edit_properties as $id_col => $props ) {
                                    if ( strpos( $props, ':' ) === false ) continue;
                                    $props = explode( ':', $props );
                                    if ( $props[0] == 'relation' && $props[1] == $context ) {
                                        $terms[ $id_col ] = $to_obj->id;
                                    }
                                }
                            }
                        }
                    }
                }
                if ( $has_relation && ! $ignore_context ) {
                    if ( count( $relations ) ) {
                        $rel_ids = [];
                        foreach ( $relations as $rel ) {
                            $rel_ids[] = (int) $rel->$relation_col;
                        }
                        $terms['id'] = ['IN' => $rel_ids ];
                    } else {
                        if ( $this_tag !== 'objectloop' ) {
                            $repeat = $ctx->false();
                            $ctx->restore( $local_vars );
                            return;
                        }
                    }
                }
                if ( $table ) {
                    if ( ! isset( $args['status_lt'] ) &&
                         ! isset( $args['status_not'] ) && ! isset( $args['status'] )
                        && ! isset( $args['include_draft'] ) ) {
                        if ( $table->has_status ) {
                            $status_published = $app->status_published( $model );
                            $terms['status'] = $status_published;
                        }
                    } else if ( isset( $args['status_not'] ) ) {
                        $not = (int) $args['status_not'];
                        $terms['status'] = ['!=' => $not ];
                    } else if ( isset( $args['status_lt'] ) ) {
                        $lt = (int) $args['status_lt'];
                        $terms['status'] = ['<' => $lt ];
                    }
                    if ( $table->revisable ) {
                        $workflow = isset( $args['workflow'] ) ? $args['workflow'] : null;
                        if ( $workflow ) {
                            $terms['rev_type'] = ['!=' => 1];
                        } else {
                            $terms['rev_type'] = 0;
                        }
                    }
                }
                $start = $ctx->stash( 'current_timestamp' );
                $end = $ctx->stash( 'current_timestamp_end' );
                $date_based = $ctx->stash( 'archive_date_based' );
                if (! $ignore_context && $date_based && $date_based == $obj->_model ) {
                    $date_based_col = $ctx->stash( 'archive_date_based_col' );
                    if ( $start && $end ) {
                        $terms[ $date_based_col ] = ['BETWEEN' => [ $start, $end ] ];
                    }
                } else if ( $start && $end && !$ignore_context ) {
                    $date_based_col = $app->get_date_col( $obj );
                    $terms[ $date_based_col ] = ['BETWEEN' => [ $start, $end ] ];
                }
                if ( $current_template
                    && $current_template->workspace_id && ! $ignore_context ) {
                }
                if ( isset( $args['can_access'] ) ) {
                    $user = $app->user();
                    if (! $user->is_superuser ) {
                        $permissions = array_keys( $app->permissions() );
                        if ( empty( $permissions ) ) {
                            $repeat = $ctx->false();
                            $ctx->restore( $local_vars );
                            return;
                        }
                        $terms['workspace_id'] = ['IN' => $permissions ];
                    }
                }
                unset( $args['sort_order'], $args['ignore_archive_context'],
                       $args['this_tag'], $args['options'], $args['table_id'] );
                $extra = '';
                $cols = '*';
                if ( $model == 'tag' && !isset( $args['include_private'] )
                    || ( isset( $args['include_private'] ) && !$args['include_private'] ) ) {
                    $terms['name'] = ['not like' => '@%'];
                }
                $_filter = $app->param( '_filter' );
                if ( ( $_filter && $_filter == $model ) || $app->force_filter ) {
                    $app->register_callback( $model, 'pre_listing', 'pre_listing', 1, $app );
                    $app->init_callbacks( $model, 'pre_listing' );
                    $callback = ['name' => 'pre_listing', 'model' => $model,
                                 'scheme' => $scheme, 'table' => $table,
                                 'args' => $orig_args ];
                    $app->run_callbacks( $callback, $model, $terms, $args, $extra );
                }
                $count_args = $args;
                unset( $count_args['limit'] );
                unset( $count_args['offset'] );
                if ( $obj->has_column( 'workspace_id' ) ) {
                    $ws_attr = $this->include_exclude_workspaces( $app, $args );
                    if ( $ws_attr ) {
                        $ws_attr = ' AND ' . $obj->_model . "_workspace_id ${ws_attr}";
                        $extra .= $ws_attr;
                    }
                    if (! $ws_attr ) {
                        $current_urlmap = $ctx->stash( 'current_urlmapping' );
                        if ( $current_urlmap ) {
                            if ( $current_urlmap->container_scope
                                && $current_urlmap->container
                                && $current_urlmap->container == $model
                                && ! $current_urlmap->workspace_id ) {
                                $extra .= " AND {$model}_workspace_id=0 ";
                            } else if ( $table->hierarchy ) {
                                $hierarchy_ws = $current_urlmap->workspace_id + 0;
                                $extra .= " AND {$model}_workspace_id={$hierarchy_ws} ";
                            }
                        }
                    }
                }
                $caching = $app->db->caching;
                $select_cols = isset( $args['cols'] ) ? $args['cols'] : null;
                $column_defs = $scheme['column_defs'];
                if ( $select_cols ) {
                    $select_cols = $this->select_cols( $app, $obj, $select_cols, $column_defs );
                    $args['cols'] = $select_cols;
                }
                $ctx->stash( 'select_cols', $select_cols );
                $ctx->stash( 'load_only_ids', false );
                $load_only_ids = false;
                if (! isset( $args['cols'] ) && count( $app->db->get_blob_cols( $model ) ) > 5 ) {
                    $app->db->caching = false;
                    $ctx->stash( 'load_only_ids', true );
                    $load_only_ids = true;
                    $cols = 'id';
                } else {
                    $cols = isset( $args['cols'] ) ? $args['cols'] : $cols;
                    if ( isset( $args['load_only_ids'] ) && $args['load_only_ids'] ) {
                        $args['cols'] = $cols;
                        $cols = 'id';
                        $ctx->stash( 'load_only_ids', true );
                        $load_only_ids = true;
                    }
                }
                $count_obj = $obj->count( $terms, $count_args, $cols, $extra );
                if ( isset( $args['limit'] ) && $args['limit'] && $args['limit'] > $count_obj ) {
                    $args['limit'] = $count_obj;
                }
                $loop_objects = $obj->load( $terms, $args, $cols, $extra );
                $app->init_callbacks( $model, 'post_load_objects' );
                $callback = ['name' => 'post_load_objects', 'model' => $model,
                             'table' => $table ];
                $app->run_callbacks( $callback, $model, $loop_objects, $count_obj );
                if ( empty( $loop_objects ) ) {
                    $repeat = $ctx->false();
                    $ctx->restore( $local_vars );
                    return;
                }
                $ctx->stash( 'object_count', $count_obj );
                if ( $load_only_ids && isset( $args['cols'] ) && $args['cols'] != '*' ) {
                    $ids = [];
                    foreach ( $loop_objects as $loop_object ) {
                        $ids[] = (int) $loop_object->id;
                    }
                    $terms = ['id' => ['IN' => $ids ] ];
                    $load_cols = $args['cols'];
                    $load_args = [];
                    if ( isset( $args['sort'] ) )
                        $load_args['sort'] = $args['sort'];
                    if ( isset( $args['direction'] ) )
                        $load_args['direction'] = $args['direction'];
                    $loop_objects = $obj->load( $terms, $load_args, $load_cols );
                    $ctx->stash( 'load_only_ids', false );
                }
                $offset_last = 0;
                $next_offset = 0;
                $prev_offset = 0;
                $current_offset = 0;
                $current_page = 0;
                if ( isset( $args['offset'] ) && isset( $args['limit'] ) ) {
                    $current_offset = $args['offset'] ? $args['offset'] : 0;
                    if ( $args['limit'] ) {
                        $limit = (int) $args['limit'];
                        $offset_last = $count_obj / $limit;
                        $offset_last = (int) $offset_last;
                        $current_page = ( $current_offset / $limit ) + 1;
                        $ctx->stash( 'current_page', (int) $current_page );
                        $ctx->stash( 'offset_last', $offset_last );
                        $next_offset = $current_offset + $limit;
                        $ctx->stash( 'next_offset', $next_offset );
                        $prev_offset = $current_offset - $limit;
                        $ctx->stash( 'prev_offset', $prev_offset );
                        $ctx->stash( 'current_offset', $current_offset );
                    }
                }
            }
            if ( empty( $loop_objects ) ) {
                $repeat = $ctx->false();
                $ctx->restore( $local_vars );
                return;
            }
            $ctx->stash( 'current_archive_context', $obj->_model );
            $ctx->stash( 'current_container', $obj->_model );
            $ctx->local_params = $loop_objects;
        }
        $params = $ctx->local_params;
        $current_page = $ctx->stash( 'current_page' );
        $count_obj = $ctx->stash( 'object_count' );
        $offset_last = $ctx->stash( 'offset_last' );
        $next_offset = $ctx->stash( 'next_offset' );
        $prev_offset = $ctx->stash( 'prev_offset' );
        $current_offset = $ctx->stash( 'current_offset' );
        $ctx->local_vars['current_page'] = $current_page ? $current_page : 1;
        $ctx->local_vars['object_count'] = $count_obj;
        $ctx->local_vars['offset_last'] = $offset_last;
        $ctx->local_vars['next_offset'] = $next_offset;
        $ctx->local_vars['prev_offset'] = $prev_offset;
        $ctx->local_vars['current_offset'] = $current_offset;
        $ctx->set_loop_vars( $counter, $params );
        $var_prefix = isset( $args['prefix'] ) ? $args['prefix'] : '';
        $var_prefix .= $var_prefix ? '_' : '';
        $primary = $table ? $table->primary : '';
        $load_only_ids = $ctx->stash( 'load_only_ids' );
        if ( isset( $params[ $counter ] ) ) {
            $obj = $params[ $counter ];
            if ( is_object( $obj ) ) {
                if ( $load_only_ids ) {
                    $cols = $ctx->stash( 'select_cols' )
                          ? $ctx->stash( 'select_cols' ) : '*';
                    $app->db->caching = false;
                    $objs = $app->db->model( $model )->load(
                            ['id' => $obj->id ], [], $cols );
                    if ( count( $objs ) ) {
                        $obj = $objs[0];
                    }
                    $app->db->caching = true;
                }
                $ctx->stash( $model, $obj );
                $ctx->stash( 'current_context', $model );
                $colprefix = $obj->_colprefix;
                $values = $obj->get_values();
                foreach ( $values as $key => $value ) {
                    if ( $colprefix ) $key = preg_replace( "/^$colprefix/", '', $key );
                    $ctx->local_vars[ $var_prefix . $key ] = $value;
                }
                if ( $ws = $obj->workspace ) {
                    $ctx->stash( 'workspace', $ws );
                }
                if ( $primary ) {
                    $ctx->local_vars[ $var_prefix . '_primary' ] = $obj->$primary;
                }
                $repeat = true;
            } else {
                $ctx->restore( $local_vars );
                $ws = $ctx->local_vars['current_workspace'];
                $ctx->stash( 'workspace', $ws );
                $repeat = false;
            }
        } else {
            $ctx->restore( $local_vars );
            $ws = $ctx->local_vars['current_workspace'];
            $ctx->stash( 'workspace', $ws );
            $repeat = false;
        }
        return ( $counter > 1 && isset( $args['glue'] ) )
            ? $args['glue'] . $content : $content;
    }

    public function include_exclude_workspaces ( $app, $args ) {
        $attr = null;
        $is_excluded = null;
        $workspace = $app->ctx->stash( 'workspace' );
        if ( isset( $args['workspace_ids'] ) ||
             isset( $args['include_workspaces'] ) ) {
            if (! isset( $args['workspace_ids'] ) ) {
                $args['workspace_ids'] = $args['include_workspaces'];
            }
            $attr = $args['workspace_ids'];
            if ( $attr && strtolower( $attr ) == 'this' ) {
                if ( $workspace && $workspace->id ) {
                    return ' = ' . $workspace->id;
                } else {
                    return ' = 0 ';
                }
            }
            unset( $args['workspace_ids'] );
            $is_excluded = 0;
        } elseif ( isset( $args['exclude_workspaces'] ) ) {
            $attr = $args['exclude_workspaces'];
            $is_excluded = 1;
        } elseif ( isset( $args['workspace_id'] ) &&
            is_numeric( $args['workspace_id'] ) ) {
            return ' = ' . $args['workspace_id'];
        } else {
            if ( $workspace && $workspace->id ) {
                return ' = ' . $workspace->id;
            }
        }
        if ( preg_match( '/-/', $attr ) ) {
            $list = preg_split( '/\s*,\s*/', $attr );
            $attr = '';
            foreach ( $list as $item ) {
                if ( preg_match('/(\d+)-(\d+)/', $item, $matches ) ) {
                    for ( $i = $matches[1]; $i <= $matches[2]; $i++ ) {
                        if ( $attr != '' ) $attr .= ',';
                        $attr .= $i;
                    }
                } else {
                    if ( $attr != '' ) $attr .= ',';
                    $attr .= $item;
                }
            }
        }
        $workspace_ids = preg_split( '/\s*,\s*/', $attr, -1, PREG_SPLIT_NO_EMPTY );
        $sql = '';
        if ( $is_excluded ) {
            $sql = ' not in ( ' . join( ',', $workspace_ids ) . ' )';
        } else if ( isset( $args['include_workspaces'] ) &&
            $args['include_workspaces'] == 'all' ) {
            $sql = '';
        } else {
            if ( count( $workspace_ids ) ) {
                array_walk( $workspace_ids, function( &$i ){ $i += 0; } );
                $sql = ' in ( ' . join( ',', $workspace_ids ) . ' ) ';
            } else {
                $sql = '';
            }
        }
        return $sql;
    }

    function select_cols ( $app, $obj, $select_cols, $column_defs = null ) {
        if ( is_string( $select_cols ) ) {
            if ( $select_cols == '*' ) return '*';
            $select_cols = preg_split( '/\s*,\s*/', $select_cols, -1, PREG_SPLIT_NO_EMPTY );
        }
        $caching = $app->db->caching;
        $app->db->caching = false;
        if (! $column_defs ) {
            $scheme = $app->get_scheme_from_db( $obj->_model );
            $column_defs = $scheme['column_defs'];
        }
        $_select_cols = [];
        foreach ( $select_cols as $select_col ) {
            $select_col = trim( $select_col );
            if ( $obj->has_column( $select_col ) ) {
                if ( isset( $column_defs[ $select_col ] ) 
                    && isset( $column_defs[ $select_col ]['type'] ) ) {
                    if ( $column_defs[ $select_col ]['type'] != 'relation' ) {
                        $_select_cols[] = $select_col;
                    }
                }
            }
            if (!in_array( 'id', $_select_cols ) ) {
                array_unshift( $_select_cols, 'id' );
            }
            if ( $obj->has_column( 'status' ) &&
                !in_array( 'status', $_select_cols ) ) {
                $_select_cols[] = 'status';
            }
            if ( $obj->has_column( 'workspace_id' ) &&
                !in_array( 'workspace_id', $_select_cols ) ) {
                $_select_cols[] = 'workspace_id';
            }
            if ( $obj->has_column( 'basename' ) &&
                !in_array( 'basename', $_select_cols ) ) {
                $_select_cols[] = 'basename';
            }
        }
        $select_cols = !empty( $_select_cols )
                     ? implode( ',', $_select_cols )
                     : '*';
        $app->db->caching = $caching;
        return $select_cols;
    }

    function hdlr_objectcols ( $args, &$content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $type = isset( $args['type'] ) ? $args['type'] : '';
        if (! $counter ) {
            $model = isset( $args['model'] ) ? $args['model'] : null;
            if (! $model ) {
                $model = $ctx->params['context_model'];
                $table = $ctx->params['context_table'];
            } else {
                $table = $app->get_table( $model );
            }
            $_model = $app->db->model( $model )->new();
            if (! $table ) {
                $repeat = $ctx->false();
                return;
            }
            $columns = $app->stash( $model . ':columns:' . $type );
            $file_col = $app->stash( $model . ':file_column' );
            if (! $columns ) {
                $terms = [];
                $terms['table_id'] = $table->id;
                if ( $type ) {
                    if ( $type === 'list' ) $terms['list'] = ['!=' => ''];
                    else if ( $type === 'edit' ) $terms['edit'] = ['!=' => ''];
                }
                $args = ['sort' => 'order'];
                // cache or schema_from table ?
                $columns = $app->db->model( 'column' )->load( $terms, $args );
                $app->stash( $model . ':columns:' . $type, $columns );
                $file_col = $app->db->model( 'column' )->load( [
                    'table_id' => $table->id,
                    'edit' => 'file'], ['limit' => 1] );
                if ( is_array( $file_col ) && count( $file_col ) ) {
                    $file_col = $file_col[0];
                    $app->stash( $model . ':file_column', $file_col );
                }
            }
            if ( $model == 'user' && !$app->user()->is_superuser ) {
                $new_columns = [];
                $not_changes = ['is_superuser', 'status', 'lock_out', 'last_login_on',
                    'uuid', 'lock_out_on', 'created_on'];
                foreach ( $columns as $col ) {
                    if ( in_array( $col->name, $not_changes ) == false ) {
                        $new_columns[] = $col;
                    }
                }
                $columns = $new_columns;
            }
            $scheme = $app->get_scheme_from_db( $model );
            $ctx->stash( 'scheme', $scheme );
            $hide_edit_options = isset( $scheme['hide_edit_options'] )
                               ? $scheme['hide_edit_options'] : [];
            $ctx->stash( 'hide_edit_options', $hide_edit_options );
            $ctx->local_vars['table_primary'] = $table->primary;
            $ctx->local_params = $columns;
            $ctx->stash( 'model', $model );
            $ctx->stash( 'file_col', $file_col );
        }
        $scheme = $ctx->stash( 'scheme' );
        $disable_edit_options = $ctx->stash( 'disable_edit_options' );
        $hide_edit_options = $ctx->stash( 'hide_edit_options' );
        $model = $ctx->stash( 'model' );
        $params = $ctx->local_params;
        $file_col = $ctx->stash( 'file_col' );
        if ( $file_col ) $ctx->local_vars['__file_col__'] = $file_col->name;
        $ctx->set_loop_vars( $counter, $params );
        $disp_option = $app->disp_option;
        if ( isset( $params[ $counter ] ) ) {
            $repeat = true;
            $obj = $params[ $counter ];
            $colprefix = $obj->_colprefix;
            $values = $obj->get_values();
            $ctx->local_vars['disable_edit_options'] = false;
            // $ctx->local_vars['hide_edit_options'] = false;
            if ( $type === 'list' || $app->param( '_type' ) === 'list' ) {
                if ( $disp_option && is_array( $disp_option ) ) {
                    $col = $values[ $colprefix . 'name'];
                    if (! in_array( $col, $disp_option ) ) {
                        return $counter === 1 ? $content : '';
                    }
                }
            } else if ( $type === 'edit' || $app->param( '_type' ) === 'edit' ) {
                if ( isset( $args['option'] ) && !empty( $disable_edit_options ) ) {
                    if ( in_array( $obj->name, $disable_edit_options ) ) {
                        $ctx->local_vars['disable_edit_options'] = true;
                    }
                }
            }
            foreach ( $values as $key => $value ) {
                if ( $colprefix ) $key = preg_replace( "/^$colprefix/", '', $key );
                if ( $key === 'edit' ) {
                    $ctx->local_vars['disp_option'] = '';
                    if ( strpos( $value, ':' ) && preg_match( '/:hierarchy$/', $value ) ) {
                        $props = explode( ':', $value );
                        $rel_table = $app->get_table( $props[1] );
                        if (! $rel_table || ! $rel_table->hierarchy ) {
                            $value = preg_replace( '/:hierarchy$/', ':checkbox', $value );
                        }
                    }
                    if ( strpos( $value, '(' ) ) {
                        list( $value, $opt ) = explode( '(', $value );
                        $opt = rtrim( $opt, ')' );
                        $ctx->local_vars['disp_option'] = $opt;
                    }
                }
                $ctx->local_vars[ $key ] = $value;
            }
            $ctx->local_vars['label'] = $app->translate( $obj->label );
        }
        return $content;
    }

    function filter_sec2hms ( $ts, $arg, $ctx ) {
        require_once( 'class.PTUtil.php' );
        list( $h, $m, $s ) = PTUtil::sec2hms( $ts );
        $app = $ctx->app;
        if ( $h ) return $app->translate( '%1$sh %2$smin %3$sseconds', [ $h, $m, $s ] );
        if ( $m ) return $app->translate( '%1$smin %2$sseconds', [ $m, $s ] );
        return $app->translate( '%sseconds', $s );
    }

    function filter_trans ( $str, $arg, $ctx ) {
        $app = $ctx->app;
        if (! $arg ) return $str;
        $component = $app->component( $arg );
        if ( $component ) {
            $arg = '';
        } else {
            $component = $app;
        }
        return $app->translate( $str, $arg, $component );
    }

    function filter_language ( $str, $arg, $ctx ) {
        $app = $ctx->app;
        return $app->translate( $str, null, null, $arg );
    }

    function filter_convert_breaks ( $text, $arg, $ctx ) {
        $text = trim( $text );
        if (! $text ) return;
        $app = $ctx->app;
        $current_context = $ctx->stash( 'current_context' );
        $obj = $ctx->stash( $current_context );
        if (! $obj ) return $text;
        $format = $arg == 'auto' ? $obj->text_format : $arg;
        if ( $format === 'markdown' ) {
            $cache_key = $obj->_model . DS . $obj->id. DS . 'md_' . md5( $text ) . '__c';
            $cache = $app->get_cache( $cache_key );
            if ( $cache ) return $cache;
            require_once( LIB_DIR . 'php-markdown'
                . DS . 'Michelf' . DS . 'Markdown.inc.php' );
            $text = Markdown::defaultTransform( $text );
            $app->set_cache( $cache_key, $text );
        } else if ( $format === 'convert_breaks' ) {
            $cache_key = $obj->_model . DS . $obj->id. DS . 'cb_' . md5( $text ) . '__c';
            $cache = $app->get_cache( $cache_key );
            if ( $cache ) return $cache;
            require_once( 'class.PTUtil.php' );
            $text = PTUtil::convert_breaks( $text );
            $app->set_cache( $cache_key, $text );
        }
        return $text;
    }

    function filter__eval ( $text, $arg, $ctx ) {
        $app = $ctx->app;
        if ( stripos( $text, '{{mt' ) !== false && stripos( $text, '}}' ) !== false ) {
            $text = preg_replace( "/\{\{\/{0,1}(mt.*?)\}\}/i", "<$1>", $text );
        }
        return $app->tmpl_markup === 'mt' ? $ctx->build( $text )
                                          : $app->build( $text, $ctx );
    }

    function filter_epoch2str ( $ts, $arg, $ctx ) {
        $app = $ctx->app;
        if (! $ts ) return $app->translate( 'Just Now' );
        $ts = time() - $ts;
        if ( $ts < 3600 ) {
            $str = $ts / 60;
            $str = round( $str );
            if ( $str < 5 ) return $app->translate( 'Just Now' );
            return $app->translate( '%s mins ago', $str );
        } else if ( $ts < 86400 ) {
            $str = $ts / 3600;
            $str = round( $str );
            return $app->translate( '%s hours ago', $str );
        } else if ( $ts < 604800 ) {
            $str = $ts / 86400;
            $str = round( $str );
            return $app->translate( '%s day(s) ago', $str );
        } else if ( $ts < 2678400 ) {
            $str = $ts / 604800;
            $str = round( $str );
            return $app->translate( '%s week(s) ago', $str );
        } else if ( $ts < 31536000 ) {
            return $app->translate( 'More than month ago' );
        } else {
            return $app->translate( 'More than year ago' );
        }
    }

}