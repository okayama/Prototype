<?php

class PTTags {

    function init_tags () {
        $app = Prototype::get_instance();
        if ( $app->init_tags ) return;
        $ctx = $app->ctx;
        $tables = $app->db->model( 'table' )->load( ['template_tags' => 1] );
        $block_relations = [];
        $function_relations = [];
        $function_date = [];
        $workspace_tags = [];
        $alias = [];
        $tags = $this;
        foreach ( $tables as $table ) {
            $plural = strtolower( $table->plural );
            $plural = preg_replace( '/[^a-z]/', '' , $plural );
            $ctx->register_tag( $plural, 'block', 'hdlr_objectloop', $tags );
            $ctx->stash( 'blockmodel_' . $plural, $table->name );
            $scheme = $app->get_scheme_from_db( $table->name );
            $columns = $scheme['column_defs'];
            $locale = $scheme['locale']['default'];
            $edit_properties = $scheme['edit_properties'];
            $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];
            $obj = $app->db->model( $table->name )->new();
            foreach ( $columns as $key => $props ) {
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
                    $ctx->register_tag(
                        $table->name . $tag_name, 'function', 'hdlr_get_objectcol', $tags );
                    if ( $table->name === 'workspace' ) {
                        $workspace_tags[] = $table->name . $tag_name;
                    }
                    if ( $label && $label != $tag_name ) {
                        if (! $obj->has_column( $label ) ) {
                            $ctx->register_tag(
                                $table->name . $label,
                                    'function', 'hdlr_get_objectcol', $tags );
                            $alias[ $table->name . $label ] 
                                = $table->name . $key;
                        }
                    }
                    if ( $key === 'published_on' ) {
                        $ctx->register_tag(
                            $table->name . 'date', 'function',
                                                'hdlr_get_objectcol', $tags );
                            $alias[ $table->name . 'date'] 
                                = $table->name . $key;
                    }
                }
                if ( preg_match( '/(^.*)_id$/', $key, $mts ) ) {
                    if ( isset( $edit_properties[ $key ] ) ) {
                        $prop = $edit_properties[ $key ];
                        if ( strpos( $prop, ':' ) !== false ) {
                            $edit = explode( ':', $prop );
                            if ( $edit[0] === 'relation' || $edit[0] === 'reference' ) {
                                $ctx->register_tag( $table->name . $mts[1],
                                    'function', 'hdlr_get_objectcol', $tags );
                                $function_relations[ $table->name . $mts[1] ]
                                    = [ $key, $table->name, $mts[1], $edit[2] ];
                                $alias[ $table->name . $label ] = $table->name . $mts[1];
                                if ( $key === 'user_id' ) {
                                        $ctx->register_tag( $table->name . 'author',
                                            'function', 'hdlr_get_objectcol', $tags );
                                    $alias[ $table->name . 'author']
                                        = $table->name . $mts[1];
                                }
                            }
                        }
                    }
                }
            }
            foreach ( $relations as $key => $model ) {
                $ctx->register_tag( $table->name . $key, 'block',
                    'hdlr_get_relatedobjs', $tags );
                $block_relations[ $table->name . $key ] = [ $key, $table->name, $model ];
            }
            if ( $table->taggable ) {
                // TODO $table->name . 'iftagged'
            }
        }
        $ctx->stash( 'workspace', $app->workspace() );
        $ctx->stash( 'function_relations', $function_relations );
        $ctx->stash( 'block_relations', $block_relations );
        $ctx->stash( 'function_date', $function_date );
        $ctx->stash( 'workspace_tags', $workspace_tags );
        $ctx->stash( 'alias_functions', $alias );
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
                        if ( method_exists( $component, $meth ) )
                            $ctx->register_tag( $name, $tag_kind, $meth, $component );
                    }
                }
            }
        }
        $app->init_tags = true;
    }

    function hdlr_tablehascolumn ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        $column = $args['column'];
        $model = isset( $args['model'] ) ? $args['model'] : '';
        $obj = $model ? $app->db->model( $model ) : $ctx->stash( 'object' );
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

    function hdlr_objectcontext ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        if ( isset( $args['model'] ) && isset( $args['id'] ) ) {
            $id = (int) $args['id'];
            $obj = $app->db->model( $args['model'] )->load( $id );
        } else {
            $obj = $ctx->stash( 'object' );
        }
        $vars = $obj->get_values();
        $colprefix = $obj->_colprefix;
        $ctx->stash( 'current_context', $obj->_model );
        $ctx->stash( $obj->_model, $obj );
        $column_defs = $app->db->scheme[ $obj->_model ]['column_defs'];
        if ( isset( $ctx->vars['forward_params'] ) ) {
            $column_names = array_keys( $column_defs );
            foreach ( $column_names as $name ) {
                $vars[ $name ] = $app->param( $name );
            }
        }
        foreach ( $vars as $col => $value ) {
            if ( $colprefix ) $col = preg_replace( "/^$colprefix/", '', $col );
            if ( $column_defs[ $col ]['type'] === 'blob' ) {
                $value = $value ? 1 : '';
            }
            $ctx->local_vars['object_' . $col ] = $value;
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
                $ctx->local_vars['object_' . $name ] = $ids;
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
                    $ctx->local_vars['object_' . $name ] = $ids;
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

    function hdlr_ifusercan ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        if ( $user = $app->user() ) {
            $action = $args['action'];
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

    function hdlr_archivetitle ( $args, $ctx ) {
        return $ctx->stash( 'current_archive_title' );
    }

    function hdlr_archivedate ( $args, $ctx ) {
        $ts = $ctx->stash( 'current_timestamp' );
        $args['ts'] = $ts;
        return $ctx->function_date( $args, $ctx );
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
            $terms = ['name' => $m ];
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
            $tmpl = $tmpl->text;
        }
        if (! $tmpl ) return '';
        return $ctx->build( $tmpl );
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
             ? $ctx->stash( 'object' ) : $ctx->local_vars['__value__'];
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
        $name = isset( $args['name'] ) ? $args['name'] : null;
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
        if (! $obj->id ) return;
        return $app->get_assetproperty( $obj, $name, $property );
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
            } else {
                $data = $metadata->data;
            }
        }
        if (! isset( $obj ) || !$obj ) {
            $obj = $app->db->model( $model )->load( $id );
            $ctx->stash( $model, $obj );
        }
        if (! $data && $type !== 'default' ) {
            if (! $obj->$name ) return;
            $height = isset( $args['height'] ) ? $args['height'] : null;
            $width = isset( $args['width'] ) ? $args['width'] : null;
            $scale = isset( $args['scale'] ) ? $args['scale'] : null;
            $upload_dir = $app->upload_dir();
            $file = $upload_dir . DS . $obj->file_name;
            file_put_contents( $file, $obj->$name );
            $_FILES = [];
            $_FILES['files'] = ['name' => basename( $file ), 'type' => $mime_type,
                      'tmp_name' => $file, 'error' => 0, 'size' => filesize( $file ) ];
            if ( (!$width && !$height ) && $scale ) {
                list( $w, $h ) = getimagesize( $file );
                $scale = $scale * 0.01;
                $width = $w * $scale;
                $height = $h * $scale;
            }
            if (!$height ||!$width ) {
                if (!$height ) $height = $width ? $width : null;
                if (!$width ) $width = $height ? $height : null;
            }
            $width = (int) $width;
            $height = (int) $height;
            $max_scale = ( $width > $height ) ? $width : $height;
            $image_versions = [
                ''          => ['auto_orient' => true ],
                'thumbnail' => ['max_width' => $max_scale, 'max_height' => $max_scale ] ];
            if ( $square ) {
                $image_versions['thumbnail']['crop'] = true;
            }
            $options = ['upload_dir' => $upload_dir . DS,
                        'prototype' => $app, 'print_response' => false,
                        'no_upload' => true, 'image_versions' => $image_versions ];
            $upload_handler = new UploadHandler( $options );
            $_SERVER['CONTENT_LENGTH'] = filesize( $file );
            $upload_handler->post( false );
            $thumbnail = $upload_dir . DS . 'thumbnail' . DS . basename( $file );
            $thumbnail_dir = dirname( $thumbnail );
            $data = file_get_contents( $thumbnail );
            list( $width, $height ) = getimagesize( $thumbnail );
            $meta = ['image_width' => $width, 'image_height' => $height ];
            unlink( $thumbnail );
            rmdir( $thumbnail_dir );
            unlink( $file );
            rmdir( $upload_dir );
        }
        if ( $data_uri && $data ) {
            $data = base64_encode( $data );
            $data = "data:{$mime_type};base64,{$data}";
        }
        return $data;
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
        if ( $model == '__any__' ) {
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
            $object_fields = [];
            if ( $id ) {
                $obj = $app->db->model( $model )->load( $id );
                if ( $obj ) {
                    $meta = $app->get_meta( $obj, 'customfield' );
                    foreach ( $meta as $cf ) {
                        $basename = $cf->key;
                        $custom_fields = isset( $object_fields[ $basename ] )
                                       ? $object_fields[ $basename ] : [];
                        $custom_fields[] = $cf;
                        $object_fields[ $basename ] = $custom_fields;
                    }
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
            $ctx->local_vars['field_name'] = $field->name;
            $ctx->local_vars['field_required'] = $field->required;
            $basename = $field->basename;
            $ctx->local_vars['field_basename'] = $basename;
            $options = $field->options;
            $display = $field->display;
            if ( $options ) {
                $labels = $field->options_labels;
                $options = preg_split( '/\s*,\s*/', $options );
                $labels = $labels ? preg_split( '/\s*,\s*/', $labels ) : $options;
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
                    foreach ( $fields as $custom_field ) {
                        $set_keys = [];
                        $vars = json_decode( $custom_field->text, true );
                        foreach ( $vars as $key => $value ) {
                            $ctx->local_vars['field.' . $key ] = $value;
                            $set_keys[] = 'field.' . $key;
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
                    }
                }
            }
            if (! $field_out ) {
                $field_label = $ctx->build( $field_label );
                $ctx->local_vars['field_label_html'] = $field_label;
                $field_label = $app->build_page( 'field' . DS . 'label.tmpl', $param, false );
                $uniqueid = $app->magic();
                $ctx->local_vars['field_uniqueid'] = $uniqueid;
                $_fld_content = $ctx->build( $field_content );
                $ctx->local_vars['field_content_html'] = $_fld_content;
                $field_contents = $app->build_page( 'field' . DS . 'content.tmpl', $param, false );
                PTUtil::add_id_to_field( $_fld_content, $uniqueid, $basename );
                $ctx->local_vars['field_label_html'] = $field_label;
                $ctx->local_vars['field_content_html'] = $field_contents;
                $field_contents = $app->build_page( 'field' . DS . 'wrapper.tmpl', $param, false );
            }
            $field_contents = "<div id=\"field-{$basename}-wrapper\">{$field_contents}</div>";
            $field_contents .= $app->build_page( 'field' . DS . 'footer.tmpl', $param, false );
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
                $objects = $app->db->model( $to_obj )->load( $terms );
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
                if ( $to_obj === '__any__' ) $to_obj = null;
                $relations = $app->get_relations( $obj, $to_obj, $colname );
                if ( empty( $relations ) ) {
                    $ctx->restore( [ $model, 'current_context', 'to_object'] );
                    $repeat = false;
                    return;
                }
                foreach ( $relations as $relation ) {
                    $id = (int) $relation->to_id;
                    $to_obj = $relation->to_obj;
                    $obj = $app->db->model( $to_obj )->load( $id );
                    if ( is_object( $obj ) ) {
                        if (! $include_draft ) {
                            $status = $app->status_published( $to_obj );
                            if ( $status && $obj->status != $status ) {
                                continue;
                            }
                        }
                        $objects[] = $obj;
                    }
                }
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
        $function_relations = $ctx->stash( 'function_relations' );
        $workspace_tags = $ctx->stash( 'workspace_tags' );
        $function_date = $ctx->stash( 'function_date' );
        $alias_functions = $ctx->stash( 'alias_functions' );
        if( isset( $alias_functions[ $this_tag ] ) ) {
            $this_tag = $alias_functions[ $this_tag ];
        }
        if ( $function_relations && isset( $function_relations[ $this_tag ] ) ) {
            $settings = $function_relations[ $this_tag ];
            $column = $settings[0];
        }
        $current_context = $ctx->stash( 'current_context' );
        if ( $workspace_tags && in_array( $this_tag, $workspace_tags ) ) {
            $current_context = 'workspace';
        }
        $obj = $ctx->stash( $current_context );
        if (! $obj ) return;
        $column = isset( $column ) ? 
            $column : preg_replace( "/^{$current_context}/", '', $this_tag );
        if (! $obj->has_column( $column ) ) {
            $column = str_replace( '_', '', $column );
        }
        if ( $obj->has_column( $column ) ) {
            $value = $obj->$column;
            if ( $value && isset( $settings ) ) {
                $value = (int) $value;
                $app = $ctx->app;
                $obj = $app->db->model( $settings[2] )->load( $value );
                $col = isset( $args['wants'] ) ? $args['wants'] : $settings[3];
                if ( $obj && $obj->has_column( $col ) ) {
                    $value = $obj->$col;
                }
            }
            if ( $function_date && in_array( $this_tag, $function_date ) ) {
                $args['ts'] = $value;
                return $ctx->function_date( $args, $ctx );
            }
            return $value;
        }
    }

    function hdlr_nestableobjects ( $args, &$content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $model = isset( $args['model'] ) ? $args['model'] : '';
        $parent_id = isset( $args['parent_id'] ) ? $args['parent_id'] : '';
        if (! $model ) {
            $repeat = false;
            return;
        }
        if (! $counter ) {
            $obj = $app->db->model( $model );
            if (! $parent_id ) $parent_id = 0;
            $terms = ['parent_id' => $parent_id];
            if ( $app->workspace() ) {
                $terms['workspace_id'] = $app->workspace()->id;
            }
            /* // TODO for not admin screen
            $status = $app->status_published( $to_obj );
            if ( $status ) {
                $terms['status'] = $status;
            }
            */
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
            $ctx->localize( [ $model ] );
            $ctx->local_params = $objects;
        }
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
            $ctx->restore( [ $model ] );
        }
        return $content;
    }

    function hdlr_tables ( $args, &$content, $ctx, &$repeat, $counter ) {
        $app = $ctx->app;
        $type = isset( $args['type'] ) ? $args['type'] : 'display_system';
        if (! $counter ) {
            $terms = [ $type => 1];
            $menu_type = isset( $args['menu_type'] ) ? $args['menu_type'] : 0;
            if ( $menu_type ) $terms['menu_type'] = $menu_type;
            $permission = isset( $args['permission'] ) ? $args['permission'] : 0;
            $models = [];
            if ( $permission && !$app->user()->is_superuser ) {
                $permissions = $app->permissions();
                foreach ( $permissions as $ws_id => $perms ) {
                    foreach ( $perms as $perm ) {
                        if ( strpos( $perm, 'can_list_' ) === 0 ) {
                            $perm = str_replace( 'can_list_', '', $perm );
                            $models[ $perm ] = true;
                        }
                    }
                }
                if (! empty( $models ) ) {
                    $models = array_keys( $models );
                    $terms['name'] = ['IN' => $models ];
                }
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
        if (! $counter ) {
            if (! $model ) {
                $repeat = false;
                return;
            }
            $orig_args = $args;
            $ctx->localize( [ $model ] );
            $loop_objects = $ctx->stash( 'loop_objects' );
            if (! $loop_objects ) {
                $obj = $app->db->model( $model );
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
                // TODO search and relations
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
                            $ctx->restore( [ $model ] );
                            return;
                        }
                    }
                }
                $table = $app->get_table( $model );
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
                if ( $date_based && $date_based == $obj->_model ) {
                    $date_based_col = $ctx->stash( 'archive_date_based_col' );
                    $start = $ctx->stash( 'current_timestamp' );
                    $end = $ctx->stash( 'current_timestamp_end' );
                    if ( $start && $end ) {
                        $terms[ $date_based_col ] = ['BETWEEN' => [ $start, $end ] ];
                    }
                }
                if ( isset( $args['can_access'] ) ) {
                    $user = $app->user();
                    if (! $user->is_superuser ) {
                        $permissions = array_keys( $app->permissions() );
                        $terms['workspace_id'] = ['IN' => $permissions ];
                    }
                }
                $loop_objects = $obj->load( $terms, $args );
            }
            if ( empty( $loop_objects ) ) {
                $repeat = false;
                $ctx->restore( [ $model ] );
                return;
            }
            $ctx->local_params = $loop_objects;
        }
        $params = $ctx->local_params;
        if ( empty( $params ) ) {
            $repeat = false;
            $ctx->restore( [ $model ] );
            return;
        }
        $ctx->set_loop_vars( $counter, $params );
        if ( isset( $params[ $counter ] ) ) {
            $obj = $params[ $counter ];
            if ( is_object( $obj ) ) {
                $ctx->stash( $model, $obj );
                $ctx->stash( 'current_context', $model );
                $colprefix = $obj->_colprefix;
                $values = $obj->get_values();
                foreach ( $values as $key => $value ) {
                    if ( $colprefix ) $key = preg_replace( "/^$colprefix/", '', $key );
                    $ctx->local_vars[ $key ] = $value;
                }
                $repeat = true;
            }
        }
        return ( $counter > 1 && isset( $args['glue'] ) )
            ? $args['glue'] . $content : $content;
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