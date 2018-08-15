<?php

use Michelf\Markdown;

class PTTags {

    function init_tags () {
        $app = Prototype::get_instance();
        if ( $app->init_tags ) return;
        $ctx = $app->ctx;
        $ctx->stash( 'workspace', $app->workspace() );
        $cache_key = 'template_tags__c';
        $cache = $app->get_cache( $cache_key );
        if ( $cache ) {
            $r_tags = $cache['tags'];
            // $components = $cache['components'];
            $blockmodels = $cache['blockmodels'];
            $function_relations = $cache['function_relations'];
            $block_relations = $cache['block_relations'];
            $function_date = $cache['function_date'];
            $fileurl_tags = $cache['fileurl_tags'];
            $workspace_tags = $cache['workspace_tags'];
            $alias = $cache['alias_functions'];
            $count_tags = $cache['count_tags'];
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
            $app->init_tags = true;
            return;
        }
        $tables = $app->db->model( 'table' )->load( ['template_tags' => 1] );
        $block_relations    = [];
        $function_relations = [];
        $function_date      = [];
        $workspace_tags     = [];
        $fileurl_tags       = [];
        $count_tags         = [];
        $alias              = [];
        $tags = $this;
        $r_tags             = [];
        $blockmodels        = [];
        // $components = ['pttags' => true];
        foreach ( $tables as $table ) {
            $plural = strtolower( $table->plural );
            $plural = preg_replace( '/[^a-z]/', '' , $plural );
            $label = strtolower( $table->label );
            $label = preg_replace( '/[^a-z]/', '' , $label );
            $this->register_tag( $ctx, $plural, 'block', 'hdlr_objectloop', $tags, $r_tags );
            $this->register_tag( $ctx, $label . 'referencecontext',
                'block', 'hdlr_referencecontext', $tags, $r_tags );
            $ctx->stash( 'blockmodel_' . $plural, $table->name );
            $blockmodels[ 'blockmodel_' . $plural ] = $table->name;
            $scheme = $app->get_scheme_from_db( $table->name );
            $columns = $scheme['column_defs'];
            $locale = $scheme['locale']['default'];
            $edit_properties = $scheme['edit_properties'];
            $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];
            $obj = $app->db->model( $table->name )->new();
            $excludes = ['rev_note', 'rev_changed', 'rev_diff', 'rev_type',
                         'rev_object_id'];
            foreach ( $columns as $key => $props ) {
                if ( in_array( $key, $excludes ) ) continue;
                if (! isset( $relations[ $key ] ) ) {
                    $label = strtolower( $locale[ $key ] );
                    $label = preg_replace( '/[^a-z]/', '' , $label );
                    $tag_name = str_replace( '_', '', $key );
                    if ( $props['type'] === 'datetime' ) {
                        $function_date[] = $table->name . $key;
                    }
                    if ( $key !== $tag_name ) {
                        $alias[ $table->name . $tag_name ] = $table->name . $key;
                    }
                    $this->register_tag( $ctx, $table->name . $tag_name, 'function',
                        'hdlr_get_objectcol', $tags, $r_tags );
                    if ( $table->name === 'workspace' ) {
                        $workspace_tags[] = $table->name . $tag_name;
                    }
                    if ( $label && $label != $tag_name ) {
                        if (! $obj->has_column( $label ) ) {
                            $this->register_tag( $ctx,
                                $table->name . $label,
                                    'function', 'hdlr_get_objectcol', $tags, $r_tags );
                            $alias[ $table->name . $label ] 
                                = $table->name . $key;
                        }
                    }
                    if ( $key === 'published_on' ) {
                        $this->register_tag( $ctx,
                            $table->name . 'date', 'function',
                                                'hdlr_get_objectcol', $tags, $r_tags );
                            $alias[ $table->name . 'date'] 
                                = $table->name . $key;
                    }
                    if ( isset( $edit_properties[ $key ] ) 
                        && $edit_properties[ $key ] === 'file' ) {
                        if (! $obj->has_column( $tag_name . 'url' ) ) {
                            $fileurl_tags[ $table->name . $tag_name . 'url'] = $key;
                            if ( $table->name === 'workspace' ) {
                                $workspace_tags[] = $table->name . $tag_name . 'url';
                            }
                            $this->register_tag( $ctx,
                                $table->name . $tag_name . 'url',
                                    'function', 'hdlr_get_objecturl', $tags, $r_tags );
                        }
                    }
                }
                if ( preg_match( '/(^.*)_id$/', $key, $mts ) ) {
                    if ( isset( $edit_properties[ $key ] ) ) {
                        $prop = $edit_properties[ $key ];
                        if ( strpos( $prop, ':' ) !== false ) {
                            $edit = explode( ':', $prop );
                            if ( $edit[0] === 'relation' || $edit[0] === 'reference' ) {
                                $this->register_tag( $ctx, $table->name . $mts[1],
                                    'function', 'hdlr_get_objectcol', $tags, $r_tags );
                                $function_relations[ $table->name . $mts[1] ]
                                    = [ $key, $table->name, $mts[1], $edit[2] ];
                                $alias[ $table->name . $label ] = $table->name . $mts[1];
                                if ( $key === 'user_id' ) {
                                        $this->register_tag( $ctx, $table->name . 'author',
                                            'function', 'hdlr_get_objectcol', $tags, $r_tags );
                                    $alias[ $table->name . 'author']
                                        = $table->name . $mts[1];
                                }
                            }
                        }
                    }
                }
            }
            $maps = $app->db->model( 'urlmapping' )->count( ['model' => $table->name ] );
            if ( $maps ) {
                $this->register_tag( $ctx,
                    $table->name . 'permalink',
                        'function', 'hdlr_get_objectcol', $tags, $r_tags );
            }
            $maps = $app->db->model( 'urlmapping' )->count( ['container' => $table->name ] );
            if ( $maps ) {
                $count_tagname = strtolower( $table->plural ) . 'count';
                $this->register_tag( $ctx,
                    $count_tagname,
                        'function', 'hdlr_container_count', $tags, $r_tags );
                $count_tags[ $count_tagname ] = $table->name;
            }
            foreach ( $relations as $key => $model ) {
                $this->register_tag( $ctx, $table->name . $key, 'block',
                    'hdlr_get_relatedobjs', $tags, $r_tags );
                $block_relations[ $table->name . $key ] = [ $key, $table->name, $model ];
            }
            if ( $table->taggable ) {
                $this->register_tag( $ctx, $table->name . 'iftagged',
                    'conditional', 'hdlr_iftagged', $tags, $r_tags );
            }
            if ( $table->hierarchy ) {
                $this->register_tag( $ctx, $table->name . 'path',
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
        /*
        $registry = $app->registry;
        if ( isset( $registry['tags'] ) ) {
            $tags = $registry['tags'];
            foreach ( $tags as $tag ) {
                $tag_kind = key( $tag );
                $props = $tag[ $tag_kind ];
                foreach ( $props as $name => $prop ) {
                    $plugin = $prop['component'];
                    if ( $component = $app->component( $plugin ) ) {
                        $meth = $prop['method'];
                        if ( method_exists( $component, $meth ) ) {
                            $this->register_tag( $ctx,
                                $name, $tag_kind, $meth, $component, $r_tags );
                            // $components[ strtolower( $plugin ) ] = true;
                        }
                    }
                }
            }
        }
        */
        $cache = ['tags' => $r_tags, // 'components' => $components,
                  'blockmodels' => $blockmodels, 'function_relations' => $function_relations,
                  'block_relations' => $block_relations, 'function_date' => $function_date,
                  'workspace_tags' => $workspace_tags, 'alias_functions' => $alias,
                  'count_tags' => $count_tags, 'fileurl_tags' => $fileurl_tags ];
        $app->set_cache( $cache_key, $cache );
        // $cache = serialize( $cache );
        // file_put_contents( $cache_path, $cache );
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

    function hdlr_countgroupby ( $args, $content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        if (! $counter ) {
            $model = $args['model'];
            $group = $args['group'];
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
            $params['count'] = $count;
            $group = $app->db->model( $model )->count_group_by( $terms, $params );
            if ( empty( $group ) ) {
                $repeat = false;
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
                if ( $model && $object_id ) {
                    $obj = $app->db->model( $model )->load( (int) $object_id );
                }
            }
        } else {
            $obj = $ctx->stash( 'object' );
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
                    // todo load join
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
        return false;
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
                      'to_obj' => 'tag', 'name' => ['not_like' => '@%'] ];
            $cnt = $app->db->model( 'relation' )->count( $terms );
            return $cnt ? true : false;
        }
        return false;
    }

    function hdlr_ifusercan ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        if ( $user = $app->user() ) {
            $action = isset( $args['action'] ) ? $args['action'] : 'edit';
            $workspace_id = isset( $args['workspace_id'] )
                          ? (int) $args['workspace_id'] : 0;
            $workspace_id = (int) $workspace_id;
            $id = isset( $args['id'] ) ? (int) $args['id'] : null;
            $model = isset( $args['model'] ) ? $args['model'] : null;
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
            if ( $action === 'rebuild' ) {
                return $app->can_do( 'rebuild', $workspace );
            }
            return $app->can_do( $model, $action, $obj, $workspace );
        }
        return false;
    }

    function hdlr_get_objectpath ( $args, $ctx ) {
        $app = $ctx->app;
        $current_context = $ctx->stash( 'current_context' );
        $obj = $ctx->stash( $current_context );
        $column = isset( $args['column'] ) ? $args['column'] : 'basename';
        $separator = isset( $args['separator'] ) ? $args['separator'] : '/';
        $parent = $obj;
        $paths = [ $parent->$column ];
        while ( $parent !== null ) {
            if ( $parent_id = $parent->parent_id ) {
                $parent_id = (int) $parent_id;
                $parent = $app->db->model( $current_context )->load( $parent_id );
                if ( $parent->id ) {
                    array_unshift( $paths, $parent->$column );
                } else {
                    $parent = null;
                }
            } else {
                $parent = null;
            }
        }
        return join( $separator, $paths );
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
        $format = isset( $args['format'] ) ? $args['format'] : 0;
        $title = $ctx->stash( 'current_archive_title' );
        if ( $format ) {
            $at = $ctx->stash( 'current_archive_type' );
            $fmt = '';
            $ts = $title;
            if ( $at === 'monthly' ) {
                $fmt = $app->translate( '\F, \Y' );
                $ts .= '01000000';
            } else if ( $at === 'yearly' ) {
                $fmt = $app->translate( '\Y' );
                $ts .= '0101000000';
            } else if ( $at === 'fiscal-yearly' ) {
                $fmt = $app->translate( '\F\i\s\c\a\l Y' );
                $ts .= '0101000000';
            }
            if ( $fmt ) {
                $args['ts'] = $ts;
                $args['format'] = $fmt;
                $title = $ctx->function_date( $args, $ctx );
            }
        }
        return $title;
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
        $int = isset( $args['status'] ) ? (int) $args['status'] : 0;
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
        if (! $int || ! $options ) return;
        $options = explode( ',', $options );
        $status = $options[ $int - 1 ];
        if ( strpos( $status, ':' ) !== false ) {
            list( $status, $option ) = explode( ':', $status );
        }
        $status = $app->translate( $status );
        if ( $icon ) {
            $tmpl = '<i class="fa fa-__" aria-hidden="true"></i>&nbsp;';
            $tmpl .= $text ? $status : "<span class=\"sr-only\">$status</span>";
            if ( count( $options ) === 5 ) {
                $icons = ['pencil', 'pencil-square',
                    'calendar', 'check-square-o', 'calendar-times-o'];
            } else if ( count( $options ) === 2 ) {
                $icons = ['pencil-square-o', 'check-square-o'];
            }
            if ( isset( $icons ) ) {
                return str_replace( '__', $icons[ $int - 1 ], $tmpl );
            }
        }
        return $status;
    }

    function hdlr_include ( $args, $ctx ) {
        $app = $ctx->app;
        $f = isset( $args['file'] ) ? $args['file'] : '';
        $m = isset( $args['module'] ) ? $args['module'] : '';
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
        if (! $f && ! $m ) return '';
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
            $terms = ['name' => $m, 'status' => 2, 'rev_type' => 0];
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
                    ['name' => $m, 'workspace_id' => 0 ] );
            }
            if ( empty( $tmpl ) ) return '';
            $tmpl = $tmpl[0];
            $_compiled = $tmpl->compiled;
            $_cache_key = $tmpl->cache_key;
            if ( $_compiled && $_cache_key ) {
                $ctx->compiled[ $_cache_key ] = $_compiled;
            }
            $app->modules[ $tmpl->id ] = $tmpl->get_values();
            $tmpl = $tmpl->text;
        }
        if (! $tmpl ) return '';
        $local_args = $args;
        unset( $local_args['file'] );
        unset( $local_args['module'] );
        unset( $local_args['cace_key'] );
        unset( $local_args['workspace_id'] );
        $old_vars = [];
        foreach ( $local_args as $k => $v ) {
            if ( isset( $ctx->local_vars[ $k ] ) ) {
                $old_vars[ $k ] = $ctx->local_vars[ $k ];
            }
            $ctx->local_vars[ $k ] = $v;
        }
        if ( stripos( $tmpl, 'setvartemplate' ) !== false ) {
            $ctx->compile( $tmpl, false );
        }
        $build = $ctx->build( $tmpl );
        foreach ( $old_vars as $k => $v ) {
            $ctx->local_vars[ $k ] = $v;
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
            $app = $ctx->app;
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
        if ( isset( $ctx->vars['forward_params'] ) ) {
            $screen_id = $app->param( '_screen_id' );
            $session_name = "{$screen_id}-{$name}";
            $session = $app->db->model( 'session' )->get_by_key(
                        ['name' => $session_name, 'user_id' => $app->user()->id ] );
            if ( $session->id ) {
                $ctx->stash( 'current_session_' . $name, $session );
                return $app->get_assetproperty( $obj, $name, $property );
            }
        }
        if (! $obj || ! $obj->id ) return;
        return $app->get_assetproperty( $obj, $name, $property );
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
        if ( isset( $ctx->vars['forward_params'] ) || $session ) {
            $id = isset( $obj ) ? $obj->id : 0;
            $screen_id = $ctx->vars['screen_id'];
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
        if (! $data && $type !== 'default' ) {
            /*
                TODO
            */
        }
        if ( $data_uri && $data ) {
            $data = base64_encode( $data );
            $data = "data:{$mime_type};base64,{$data}";
        }
        return $data;
    }

    function hdlr_getobjectlabel ( $args, $ctx ) {
        $id = isset( $args['id'] ) ? (int) $args['id'] : null;
        $model = isset( $args['model'] ) ? $args['model'] : null;
        if (! $id || ! $model ) return;
        $app = $ctx->app;
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
                $repeat = false;
                return;
            }
            $id = isset( $args['id'] ) ? (int) $args['id'] : '';
            $obj = $id ? $app->db->model( $model )->load( $id ) 
                       : $app->db->model( $model )->new();
            $object_fields = [];
            $meta = [];
            if ( isset( $ctx->vars['forward_params'] ) ) {
                require_once( 'class.PTUtil.php' );
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
            require_once( 'class.PTUtil.php' );
            $fields = PTUtil::get_fields( $model, $args );
            if ( empty( $fields ) ) {
                $repeat = false;
                return;
            }
            $ctx->local_params = $fields;
            $ctx->stash( 'object_fields', $object_fields );
        }
        $params = $ctx->local_params;
        $object_fields = $ctx->stash( 'object_fields' );
        if ( empty( $params ) ) {
            $repeat = false;
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
            $param = [];
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
                $ctx->local_vars[ 'field__' . $key ] = $value;
            }
            $field_out = false;
            $field_contents = '';
            if (! empty( $object_fields ) ) {
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
                        $_fld_content = $app->build_page( 'field' . DS . 'content.tmpl', $param, false );
                        $_field_label = $ctx->build( $field_label );
                        $ctx->local_vars['field_label_html'] = $_field_label;
                        $_field_label = $app->build_page( 'field' . DS . 'label.tmpl', $param, false );
                        $ctx->local_vars['field_label_html'] = $_field_label;
                        $ctx->local_vars['field_content_html'] = $_fld_content;
                        $_fld_content = $app->build_page( 'field' . DS . 'wrapper.tmpl', $param, false );
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
                $field_label = $app->tmpl_markup === 'mt' ? $ctx->build( $field_label )
                                                          : $app->build( $field_label, $ctx );
                $ctx->local_vars['field_label_html'] = $field_label;
                $src = file_get_contents( TMPL_DIR . 'field' . DS . 'label.tmpl' );
                $field_label = $ctx->build( $src );
                $_fld_content = $app->tmpl_markup === 'mt' ? $ctx->build( $field_content )
                                                           : $app->build( $field_content, $ctx );
                $ctx->local_vars['field_content_html'] = $_fld_content;
                $src = file_get_contents( TMPL_DIR . 'field' . DS . 'content.tmpl' );
                $field_contents = $ctx->build( $src );
                PTUtil::add_id_to_field( $_fld_content, $uniqueid, $basename );
                $ctx->local_vars['field_label_html'] = $field_label;
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
        $name = isset( $args['name'] ) ? $args['name'] : '';
        $model = isset( $args['model'] ) ? $args['model'] : '';
        if (! $name && ! $model ) {
            $repeat = false;
            return;
        }
        $localvars = ['current_context', 'reference_obj', 'object'];
        if (! $counter ) {
            if ( $model ) {
                $obj_id = isset( $args['id'] ) ? $args['id'] : '';
                if (! $obj_id ) {
                    $repeat = false;
                    return;
                }
                $ref_model = $model;
            } else {
                $current_context = $ctx->stash( 'current_context' );
                $scheme = $app->get_scheme_from_db( $current_context );
                $obj = $ctx->stash( $current_context );
                if (! $obj ) {
                    $repeat = false;
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
                if (! $obj->$name ) {
                    $repeat = false;
                    return;
                }
                $props = $scheme['edit_properties'];
                $prop = $props[ $name ];
                $props = explode( ':', $prop );
                $ref_model = $props[1];
                if (! $ref_model ) {
                    $repeat = false;
                    return;
                }
                $obj_id = $obj->$name;
            }
            if ( isset( $args['force'] ) && $args['force'] ) {
                $app->db->caching = false;
            }
            $ref_obj = $app->db->model( $ref_model )->load( (int)$obj_id );
            if (! $ref_obj ) {
                $repeat = false;
                return;
            }
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
        $obj = $ctx->stash( $current_context );
        $settings = $block_relations[ $this_tag ];
        $model  = $settings[1];
        $to_obj = $settings[2];
        $colname = $settings[0];
        $preview_template = $ctx->stash( 'preview_template' );
        $in_preview = false;
        if ( $app->param( '_preview' ) && ! $preview_template ) {
            $in_preview = true;
        }
        if (! $counter ) {
            $include_draft = isset( $args['include_draft'] )
                           ? true : false;
            $objects = [];
            $ctx->localize( [ $model, 'current_context', 'to_object'] );
            if ( $in_preview ) {
                if ( $to_obj === '__any__' ) {
                    $to_obj = $app->param( "_{$colname}_model" );
                }
                $rels = $app->param( $colname ) ? $app->param( $colname ) : [];
                $ids = [];
                foreach ( $rels as $id ) {
                    if ( $id ) $ids[] = (int) $id;
                }
                if ( empty( $rels ) || empty( $ids ) ) {
                    $ctx->restore( [ $model, 'current_context', 'to_object'] );
                    $repeat = false;
                    return;
                }
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
                $objects = $app->db->model( $to_obj )->load( $terms, $params );
                if ( empty( $objects ) ) {
                    $ctx->restore( [ $model, 'current_context', 'to_object'] );
                    $repeat = false;
                    return;
                }
                if ( count( $objects ) > 1 ) {
                    $arr = [];
                    foreach ( $objects as $obj ) {
                        $arr[ (int) $obj->id ] = $obj;
                    }
                    $objects = [];
                    foreach ( $ids as $id ) {
                        $objects[] = $arr[ $id ];
                    }
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
                    $repeat = false;
                    return;
                }
                $params = [];
                if (! $include_draft ) {
                    $params['published_only'] = true;
                }
                if ( isset( $args['limit'] ) ) {
                    $params['limit'] = (int) $args['limit'];
                }
                if ( isset( $args['sort_by'] ) ) {
                    $params['sort'] = $args['sort_by'];
                }
                if ( isset( $args['sort_order'] ) ) {
                    $params['direction'] = $args['sort_order'];
                }
                $objects = $app->get_related_objs( $obj, $to_obj, $colname, $params );
                $callback = ['name' => 'post_load_objects', 'model' => $to_obj ];
                $count_obj = count( $objects );
                $app->run_callbacks( $callback, $model, $objects, $count_obj );
            }
            // Filter Status
            $scheme = $app->db->scheme;
            if ( empty( $objects ) ) {
                $repeat = false;
                $ctx->restore( [ $model, 'current_context', 'to_object'] );
                return;
            }
            $ctx->local_params = $objects;
            $ctx->stash( 'to_object', $to_obj );
        }
        $params = $ctx->local_params;
        if ( empty( $params ) ) {
            $repeat = false;
            $ctx->restore( [ $model, 'current_context', 'to_object'] );
            return;
        }
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

    function hdlr_get_objectcol ( $args, $ctx ) {
        $this_tag = $args['this_tag'];
        $current_context = $ctx->stash( 'current_context' );
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
        $obj = $ctx->stash( $current_context );
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
        if ( $this_tag == $obj->_model . 'permalink' ) {
            $app = $ctx->app;
            return $app->get_permalink( $obj, false, true );
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
        $obj = $ctx->stash( $current_context );
        if (! $obj ) return;
        $urlinfo = $app->db->model( 'urlinfo' )->get_by_key(
            ['model' => $obj->_model, 'object_id' => $obj->id,
             'key' => $key, 'class' => 'file' ] );
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
        $model = $count_tags[ $this_tag ];
        $table = $app->get_table( $model );
        $archive_date_based = false;
        if ( $at && ( $at === 'monthly' || $at === 'yearly' || $at === 'fiscal-yearly' ) ) {
            $archive_date_based = true;
        }
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
            $obj = $app->db->model( $model )->new();
            $date_col = $app->get_date_col( $obj );
            $terms = [ $date_col => ['BETWEEN' => [ $start, $end ] ] ];
        } else {
            $obj = $ctx->stash( $context );
            if (! $obj ) return 0;
            $args = [];
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
            $terms = ['id' => ['IN' => $ids ] ];
        }
        if ( $table->has_status ) {
            $terms['status'] = $app->status_published( $model );
        }
        if ( $table->revisable ) {
            $terms['rev_type'] = 0;
        }
        $callback = ['name' => 'pre_archive_count', 'model' => $model ];
        $args = [];
        $extra = '';
        $app->run_callbacks( $callback, $model, $terms, $args, $extra );
        return $app->db->model( $model )->count( $terms, $args, $extra );
    }

    function hdlr_archivelist ( $args, &$content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $at = isset( $args['type'] ) ? strtolower( $args['type'] ) : '';
        if (! $at ) $at = isset( $args['archive_type'] ) ? strtolower( $args['archive_type'] ) : '';
        if (! $at ) {
            $repeat = false;
            return;
        }
        $date_based = false;
        if ( $at === 'monthly' || $at === 'yearly' || $at === 'fiscal-yearly' ) {
            $at = $at === 'fiscal-yearly' ? 'Fiscal-Yearly' : ucfirst( $at );
            $date_based = true;
        }
        $local_vars = ['current_archive_type', 'current_archive_title',
                       'current_archive_link', 'current_timestamp'];
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
                $repeat = false;
                $ctx->restore( $local_vars );
                return;
            }
            $fy_end = $fy_start == 1 ? 12 : $fy_start - 1;
            require_once( 'class.PTUtil.php' );
            $archive_loop = [];
            if (! $date_based ) {
                if ( $model === 'template' ) {
                    $repeat = false;
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
                $sql = "SELECT DISTINCT YEAR({$date_col}), MONTH({$date_col}) FROM $_table";
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
                $callback = ['name' => 'pre_archive_list', 'model' => $container_obj->_model ];
                $app->run_callbacks( $callback, $container_obj->_model, $wheres );
                $sql .= join( ' AND ', $wheres );
                $request_id = $app->request_id;
                $cache_key = md5( "archive-list-{$sql}-{$request_id}" );
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
                    $year_and_month = $container_obj->load( $sql );
                    $time_stamp = [];
                    foreach ( $year_and_month as $ym ) {
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
                        $session->expires( time() + $app->token_expires );
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
        if (! $model ) {
            $repeat = false;
            return;
        }
        $local_vars = [ $model, 'current_context' ];
        if (! $counter ) {
            $scheme = $app->get_scheme_from_db( $model );
            $obj = $app->db->model( $model );
            if (! $parent_id ) $parent_id = 0;
            $terms = ['parent_id' => $parent_id ];
            $workspace = $ctx->stash( 'workspace' ) 
                       ? $ctx->stash( 'workspace' ) : $app->workspace();
            if ( $workspace ) {
                $terms['workspace_id'] = $workspace->id;
            }
            if ( $app->mode != 'view' ) {
                if ( $obj->has_column( 'status' ) ) {
                    $status = $app->status_published( $obj );
                    if ( $status ) {
                        $terms['status'] = $status;
                    }
                }
            }
            if ( $obj->has_column( 'rev_type' ) ) {
                $terms['rev_type'] = 0;
            }
            $args = [];
            if ( $obj->has_column( 'order' ) ) {
                $args = ['sort' => 'order', 'direction' => 'ascend'];
            }
            $objects = $ctx->stash( 'children_object_' . $model . '_' . $parent_id )
                ? $ctx->stash( 'children_object_' . $model . '_' . $parent_id )
                : $obj->load( $terms, $args );
            if (! is_array( $objects ) || empty( $objects ) ) {
                $repeat = false;
                return;
            }
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
                $repeat = false;
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
                $repeat = false;
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
        if (! $counter ) {
            $terms = [ $type => 1];
            $menu_type = isset( $args['menu_type'] ) ? $args['menu_type'] : 0;
            if ( $menu_type ) $terms['menu_type'] = $menu_type;
            $permission = isset( $args['permission'] ) ? $args['permission'] : 0;
            $models = [];
            $ws_admin = false;
            if ( $permission && !$app->user()->is_superuser ) {
                $permissions = $app->permissions();
                foreach ( $permissions as $ws_id => $perms ) {
                    if ( $workspace_perm && $app->workspace() ) {
                        if ( $ws_id != $app->workspace()->id ) {
                            continue;
                        }
                    }
                    if ( $app->ws_menu_type == 1 &&
                        in_array( 'workspace_admin', $perms ) !== false ) {
                        $ws_admin = true;
                        continue;
                    }
                    foreach ( $perms as $perm ) {
                        if ( strpos( $perm, 'can_list_' ) === 0 ) {
                            $perm = str_replace( 'can_list_', '', $perm );
                            $models[ $perm ] = true;
                        }
                    }
                }
                if (! $ws_admin ) {
                    if (! empty( $models ) ) {
                        $models = array_keys( $models );
                        $terms['name'] = ['IN' => $models ];
                    } else {
                        $repeat = false;
                        return;
                    }
                }
            }
            $im_export = isset( $args['im_export'] ) ? $args['im_export'] : 0;
            if ( $im_export ) {
                $terms['im_export'] = 1;
            }
            $args = ['sort' => 'order'];
            $cache_key = $app->make_cache_key( $terms, $args, 'table' );
            $tables = $app->stash( $cache_key ) ? $app->stash( $cache_key )
                    : $app->db->model( 'table' )->load( $terms, $args );
            $app->stash( $cache_key, $tables );
            if (! is_array( $tables ) || empty( $tables ) ) {
                $app->stash( $cache_key, 1 );
                $repeat = false;
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
        $local_vars = [ $model, 'current_context', 'current_archive_context',
                        'current_container', 'current_workspace' ];
        if (! $counter ) {
            if (! $model ) {
                $repeat = false;
                return;
            }
            $orig_args = $args;
            $ctx->local_vars['current_workspace'] = $ctx->stash( 'workspace' );
            $ctx->localize( $local_vars );
            $obj = $app->db->model( $model );
            $app->get_scheme_from_db( $model );
            $loop_objects = $ctx->stash( 'loop_objects' );
            if (! $loop_objects ) {
                $table_id = isset( $args['table_id'] ) ? (int) $args['table_id'] : null;
                if ( isset( $args['table_id'] ) && !$table_id ) {
                    $repeat = false;
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
                    $args['limit'] = (int) $args['limit'];
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
                if (! $container && $app->db->model( $context )->has_column( 'model' ) ) {
                    $ctx_obj = $ctx->stash( $context );
                    $ctx_model = null;
                    if ( $ctx_obj && $ctx_obj->model ) {
                        $ctx_model = $app->get_table( $ctx_obj->model );
                    }
                    $preview_template = $ctx->stash( 'preview_template' );
                    if ( $app->param( '_preview' ) && ! $preview_template ) {
                        $scheme = $app->get_scheme_from_db( $context );
                        if ( isset( $scheme['relations'] ) ) {
                            $relations = $scheme['relations'];
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
                $relations = [];
                if ( $container && $context ) {
                    if ( $container == $model ) {
                        if ( $to_obj = $ctx->stash( $context ) ) {
                            $relations = $app->db->model( 'relation' )->load( 
                                ['to_id' => (int) $to_obj->id, 'to_obj' => $context,
                                 'from_obj' => $obj->_model ] );
                            $has_relation = true;
                            $relation_col = 'from_id';
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
                            $repeat = false;
                            $ctx->restore( $local_vars );
                            return;
                        }
                    }
                }
                $table = $model !== 'option' && $model !== 'column'
                       ? $app->get_table( $model ) : null;
                if ( $table ) {
                    if (! isset( $args['status'] ) && ! isset( $args['include_draft'] ) ) {
                        if ( $table->has_status ) {
                            $status_published = $app->status_published( $model );
                            $terms['status'] = $status_published;
                        }
                    }
                    if ( $table->revisable ) {
                        $terms['rev_type'] = 0;
                    }
                }
                $date_based = $ctx->stash( 'archive_date_based' );
                if (! $ignore_context && $date_based && $date_based == $obj->_model ) {
                    $date_based_col = $ctx->stash( 'archive_date_based_col' );
                    $start = $ctx->stash( 'current_timestamp' );
                    $end = $ctx->stash( 'current_timestamp_end' );
                    if ( $start && $end ) {
                        $terms[ $date_based_col ] = ['BETWEEN' => [ $start, $end ] ];
                    }
                }
                if ( $current_template
                    && $current_template->workspace_id && ! $ignore_context ) {
                    //
                }
                if ( isset( $args['can_access'] ) ) {
                    $user = $app->user();
                    if (! $user->is_superuser ) {
                        $permissions = array_keys( $app->permissions() );
                        $terms['workspace_id'] = ['IN' => $permissions ];
                    }
                }
                unset( $args['sort_order'], $args['ignore_archive_context'],
                       $args['this_tag'], $args['options'], $args['table_id'] );
                $extra = '';
                $cols = '*';
                if ( $app->param( '_filter' ) ) {
                    $table = $app->get_table( $model );
                    $scheme = $app->get_scheme_from_db( $model );
                    $app->register_callback( $model, 'pre_listing', 'pre_listing', 1, $app );
                    $app->init_callbacks( $model, 'pre_listing' );
                    $callback = ['name' => 'pre_listing', 'model' => $model,
                                 'scheme' => $scheme, 'table' => $table ];
                    $app->run_callbacks( $callback, $model, $terms, $args, $extra );
                    /*
                        _filter=1
                        &_filter_value_rent[]=1000
                        &_filter_value_rent[]=100000
                        &_filter_cond_rent[]=gt
                        &_filter_cond_rent[]=lt
                        &_filter_value_name[]=東
                        &_filter_cond_name[]=ct
                        &_filter_and_or_rent=OR
                    */
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
                }
                $count_obj = $obj->count( $terms, $count_args, $cols, $extra );
                $loop_objects = $obj->load( $terms, $args, $cols, $extra );
                $callback = ['name' => 'post_load_objects', 'model' => $model,
                             'table' => $table ];
                $app->run_callbacks( $callback, $model, $loop_objects, $count_obj );
                $ctx->stash( 'object_count', $count_obj );
            }
            $ctx->stash( 'current_archive_context', $obj->_model );
            if ( empty( $loop_objects ) ) {
                $repeat = false;
                $ctx->restore( $local_vars );
                return;
            }
            $ctx->local_params = $loop_objects;
        }
        $params = $ctx->local_params;
        if ( empty( $params ) ) {
            $repeat = false;
            $ctx->restore( $local_vars );
            return;
        }
        $count_obj = $ctx->stash( 'object_count' );
        $ctx->local_vars[ 'object_count' ] = $count_obj;
        $ctx->set_loop_vars( $counter, $params );
        $var_prefix = isset( $args['prefix'] ) ? $args['prefix'] : '';
        $var_prefix .= $var_prefix ? '_' : '';
        if ( isset( $params[ $counter ] ) ) {
            $obj = $params[ $counter ];
            if ( is_object( $obj ) ) {
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
                $repeat = true;
            }
        } else {
            $ctx->restore( $local_vars );
            $ws = $ctx->local_vars['current_workspace'];
            $ctx->stash( 'workspace', $ws );
        }
        return ( $counter > 1 && isset( $args['glue'] ) )
            ? $args['glue'] . $content : $content;
    }

    public function include_exclude_workspaces ( $app, $args ) {
        $attr = null;
        $is_excluded = null;
        if ( isset( $args['workspace_ids'] ) ||
             isset( $args['include_workspaces'] ) ) {
            if (! isset( $args['workspace_ids'] ) ) {
                $args['workspace_ids'] = $args['include_workspaces'];
            }
            $attr = $args['workspace_ids'];
            unset( $args['workspace_ids'] );
            $is_excluded = 0;
        } elseif ( isset( $args['exclude_workspaces'] ) ) {
            $attr = $args['exclude_workspaces'];
            $is_excluded = 1;
        } elseif ( isset( $args['workspace_id'] ) &&
            is_numeric( $args['workspace_id'] ) ) {
            return ' = ' . $args['workspace_id'];
        } else {
            $workspace = $app->ctx->stash( 'workspace' );
            if ( isset ( $workspace ) ) return ' = ' . $workspace->id;
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
            return '';
        } else {
            if ( count( $workspace_ids ) ) {
                $sql = ' in ( ' . join( ',', $workspace_ids ) . ' ) ';
            } else {
                return '';
            }
        }
        return $sql;
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
                $repeat = false;
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
            $ctx->local_vars['table_primary'] = $table->primary;
            $ctx->local_params = $columns;
            $ctx->stash( 'model', $model );
            $ctx->stash( 'file_col', $file_col );
        }
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
            if ( $type === 'list' || $app->param( '_type' ) === 'list' ) {
                if ( $disp_option && is_array( $disp_option ) ) {
                    $col = $values[ $colprefix . 'name'];
                    if (! in_array( $col, $disp_option ) ) {
                        return $counter === 1 ? $content : '';
                    }
                }
            }
            foreach ( $values as $key => $value ) {
                if ( $colprefix ) $key = preg_replace( "/^$colprefix/", '', $key );
                if ( $key === 'edit' ) {
                    $ctx->local_vars['disp_option'] = '';
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
        return $app->tmpl_markup === 'mt' ? $ctx->build( $text )
                                          : $app->build( $text, $ctx );
    }

    function filter_epoch2str ( $ts, $arg, $ctx ) {
        $app = $ctx->app;
        if (! $ts ) $app->translate( 'Just Now' );
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