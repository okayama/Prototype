<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );

class DisplayOptions extends PTPlugin {

    function __construct () {
        parent::__construct();
    }

    function post_init ( &$app ) {
        if (! $app->user() ) return;
        if ( $app->id != 'Prototype' ) {
            return;
        }
        $table = $app->get_table( 'displayoption' );
        if (! $table ) {
            return;
        }
        $app->stash( 'model_displayoption', $table );
        if ( $app->mode == 'view' && $app->param( '_type' ) == 'list' ) {
            $model = $app->param( '_model' );
            $workspace_id = $app->workspace() ? (int)$app->workspace()->id : 0;
            $displayoption = $app->db->model( 'displayoption' )->get_by_key(
                ['workspace_id' => $workspace_id, 'model' => $model] );
            if ( $displayoption->id ) {
                $app->register_callback( $model, 'start_listing', 'start_listing', 1, $this );
                $app->stash( 'current_displayoption', $displayoption );
            }
        }
    }

    function pre_load_objects ( &$cb, $app, &$terms, $args, $cols, &$extra = '' ) {
        if (! $app->stash( 'model_displayoption' ) ) return;
        $tag_args = $cb['args'];
        if (! isset( $tag_args['this_tag'] ) || $tag_args['this_tag'] != 'tables' ) {
            return;
        }
        $workspace_id = $app->workspace() ? (int)$app->workspace()->id : 0;
        if ( isset( $terms['display_system'] )
            && $terms['display_system'] && $workspace_id ) {
            $workspace_id = 0;
        }
        $displayoptions = $app->stash( 'current_displayoptions_' . $workspace_id )
                        ? $app->stash( 'current_displayoptions_' . $workspace_id )
                        : $app->db->model( 'displayoption' )->load(
                                        ['workspace_id' => $workspace_id] );
        $app->stash( 'current_displayoptions_' . $workspace_id , $displayoptions );
        if ( empty( $displayoptions ) ) return;
        $target_names = [];
        if ( isset( $terms['name'] ) && isset( $terms['name']['IN'] ) ) {
            $target_names = $terms['name']['IN'];
        }
        if ( isset( $terms['menu_type'] ) ) {
            $type = $terms['menu_type'];
            $exclude_models = [];
            $include_models = [];
            foreach ( $displayoptions as $displayoption ) {
                $pos = array_search( $displayoption->model , $target_names );
                if ( $displayoption->menu_type != $type ) {
                    $exclude_models[] = $displayoption->model;
                    if ( $pos ) {
                        unset( $target_names[ $pos ] );
                    }
                } else {
                    $include_models[] = $displayoption->model;
                    if (! $pos ) $target_names[] = $displayoption->model;
                }
            }
            $_extra = '';
            if (! empty( $exclude_models ) ) {
                $expressions = [];
                foreach ( $exclude_models as $model ) {
                    $expressions[] = " table_name!='{$model}' ";
                }
                if (! empty( $expressions ) ) {
                    $_extra .= ' AND (' . implode( ' AND ', $expressions ) . ') ';
                }
            }
            if (! empty( $include_models ) ) {
                $expressions = [];
                foreach ( $include_models as $model ) {
                    if (! $app->can_do( $model, 'list' ) ) {
                        continue;
                    }
                    $expressions[] = " table_name='{$model}' ";
                }
                if (! empty( $expressions ) ) {
                    $_extra .= ' OR (' . implode( ' OR ', $expressions ) . ') ';
                }
            }
            $extra .= $_extra;
            if (! empty( $target_names ) ) {
                $terms['name']['IN'] = $target_names;
            }
        }
    }

    function start_listing ( &$cb, $app ) {
        if (! $app->stash( 'model_displayoption' ) ) return;
        $workspace_id = $app->workspace() ? (int)$app->workspace()->id : 0;
        $model = $cb['model'];
        $list_option = $app->get_user_opt( $model, 'list_option', $workspace_id );
        $scheme = $cb['scheme'];
        $displayoption = $app->stash( 'current_displayoption' );
        if (! $displayoption ) return;
        if ( $this->exclude_case( $app, $displayoption, $workspace_id ) ) {
            return;
        }
        $app->stash( 'user_options', $app->db->model( 'option' )->new() );
        if ( $list_columns = $displayoption->list_columns ) {
            $list_columns = json_decode( $list_columns, true );
            if ( is_array( $list_columns ) && isset( $list_columns['columns'] ) ) {
                $columns = $list_columns['columns'];
                if ( $list_columns['can_hide_in_list'] ) {
                    if ( $list_option->id ) {
                        $list_options = explode( ',', $list_option->option );
                        foreach ( $columns as $idx => $value ) {
                            if (! in_array( $value, $list_options ) ) {
                                unset( $columns[ $idx ] );
                            }
                        }
                    }
                }
                $scheme['default_list_items'] = $columns;
                $cb['scheme'] = $scheme;
            }
        }
    }

    function exclude_case ( $app, $displayoption, $workspace_id ) {
        $group_name = $app->permission_group( $app->user(),
            $displayoption->model, $workspace_id );
        if ( $displayoption->exclude_ws_admin && $workspace_id ) {
            $is_admin = $app->is_workspace_admin( $workspace_id );
            if ( $is_admin ) return true;
        } else if ( $displayoption->exclude_superuser ) {
            if ( $app->user()->is_superuser ) return true;
        } else if ( $displayoption->exclude_publisher && $group_name == 'publisher' ) {
            return true;
        } else if ( $displayoption->exclude_reviewer && $group_name == 'reviewer' ) {
            return true;
        }
        return false;
    }

    function pre_save_displayoption ( $cb, $app, &$obj, $original ) {
        if (! $app->stash( 'model_displayoption' ) ) return;
        $params = $app->param();
        $columns = [];
        $displays = [];
        $orders = [];
        $data = [];
        $list_data = [];
        $list_columns = [];
        $list_displays = [];
        $can_save = false;
        foreach ( $params as $key => $value ) {
            if ( strpos( $key, '_column-' ) === 0 ) {
                $col_name = preg_replace( '/_column\-/', '', $key );
                $orders[] = $col_name;
                $displays[] = ['name' => $col_name, 'diaplay' => $value ];
                if ( $value ) {
                    $columns[] = $col_name;
                }
                $can_save = true;
            } else if ( strpos( $key, '_customize-' ) === 0 ) {
                $customize_name = preg_replace( '/_customize\-/', '', $key );
                $data[ $customize_name ] = $value;
                $can_save = true;
            } else if ( strpos( $key, '_list_column-' ) === 0 ) {
                $col_name = preg_replace( '/_list_column\-/', '', $key );
                $list_displays[] = ['name' => $col_name, 'diaplay' => $value ];
                if ( $value ) {
                    $list_columns[] = $col_name;
                }
                $can_save = true;
            } else if ( strpos( $key, '_customize_list-' ) === 0 ) {
                $customize_name = preg_replace( '/_customize_list\-/', '', $key );
                $list_data[ $customize_name ] = $value;
                $can_save = true;
            }
        }
        if ( $can_save ) {
            $data['columns'] = $columns;
            $data['orders'] = $orders;
            $data['displays'] = $displays;
            $list_data['columns'] = $list_columns;
            $list_data['displays'] = $list_displays;
            $obj->edit_columns( json_encode( $data ) );
            $obj->list_columns( json_encode( $list_data ) );
        }
        return true;
    }

    function template_source_list ( $cb, $app, $param, $src ) {
        if (! $app->stash( 'model_displayoption' ) ) return true;
        $ctx = $app->ctx;
        $model = $cb['model'];
        $displayoption = $app->stash( 'current_displayoption' );
        $workspace_id = $app->workspace() ? (int)$app->workspace()->id : 0;
        if (! $displayoption ) return true;
        if ( $this->exclude_case( $app, $displayoption, $workspace_id ) ) {
            return true;
        }
        if ( $displayoption && $displayoption->id ) {
            $list_columns = $displayoption->list_columns;
            $props = $list_columns ? json_decode( $list_columns, true ) : [];
            if ( empty( $props ) ) return true;
            $disp_options = $ctx->vars['disp_options'];
            $overwrite_options = [];
            $hide_list_options = $ctx->vars['hide_list_options'];
            $col_settings = $props['columns'];
            foreach ( $disp_options as $col => $disp_option ) {
                $disp_option[1] = in_array( $col, $col_settings ) ? 1 : 0;
                if (! in_array( $col, $col_settings ) ) {
                    $hide_list_options[] = $col;
                }
                $overwrite_options[ $col ] = $disp_option;
            }
            if (! $props['can_hide_in_list'] ) {
                $ctx->vars['disp_options'] = $overwrite_options;
            }
            $ctx->vars['can_hide_in_list'] = $props['can_hide_in_list'];
            $ctx->vars['hide_list_options'] = $hide_list_options;
        }
        return true;
    }

    function template_source_edit ( $cb, $app, $param, $src ) {
        if (! $app->stash( 'model_displayoption' ) ) return true;
        $ctx = $app->ctx;
        $model = $cb['model'];
        $workspace_id = $app->workspace() ? (int)$app->workspace()->id : 0;
        if ( $model == 'displayoption' ) {
            $obj = $ctx->stash( 'object' );
            if (! $obj ) return true;
            if (! $obj->model ) return true;
            $edit_columns = $obj->edit_columns;
            if ( $edit_columns ) {
                $props = json_decode( $edit_columns, true );
                if ( is_array( $props ) ) {
                    $ctx->vars['show_edit_columns'] = $props['columns'];
                    $orders = $props['orders'];
                    $ctx->vars['column_edit_orders'] = json_encode( $orders );
                    if ( isset( $props['can_hide_in_edit'] ) ) {
                        $ctx->vars['can_hide_in_edit'] = $props['can_hide_in_edit'];
                    }
                    if ( isset( $props['can_sort_in_edit'] ) ) {
                        $ctx->vars['can_sort_in_edit'] = $props['can_sort_in_edit'];
                    }
                }
            }
            $workflow = $app->db->model( 'workflow' )->get_by_key(
                ['model' => $obj->model, 'workspace_id' => $workspace_id ] );
            $ctx->vars['_force_display'] = [];
            if ( $workflow->id ) {
                $ctx->vars['_force_display'] = ['user_id', 'status'];
            }
            $table = $app->get_table( $obj->model );
            $model_obj = $app->db->model( $obj->model )->new();
            if ( $obj->menu_type ) {
                $ctx->vars['_menu_type'] = $obj->menu_type;
            } else {
                $ctx->vars['_menu_type'] = $table->menu_type;
            }
            $fields = PTUtil::get_fields( $model_obj );
            $customFields = [];
            foreach ( $fields as $field ) {
                $name = $field->name;
                $translate = $field->translate;
                if ( $translate ) $name = $app->translate( $name );
                $basename = $field->basename;
                $customFields[] = ['name' => $name, 'basename' => $basename ];
            }
            $ctx->vars['field_loop'] = $customFields;
            $scheme = $app->get_scheme_from_db( $obj->model );
            $list_columns = $obj->list_columns;
            if ( $list_columns ) {
                $props = json_decode( $list_columns, true );
                if ( is_array( $props ) ) {
                    $ctx->vars['show_list_columns'] = $props['columns'];
                    if ( isset( $props['can_hide_in_list'] ) )
                        $ctx->vars['can_hide_in_list'] = $props['can_hide_in_list'];
                }
            } else if ( isset( $scheme['default_list_items'] ) ) {
                $ctx->vars['show_list_columns'] = $scheme['default_list_items'];
            } else {
                $user_options = array_keys( $scheme['list_properties'] );
                $user_options = array_slice( $user_options, 0, 7 );
                if (! $app->workspace() && ! in_array( 'workspace_id', $user_options )
                    && $obj->has_column( 'workspace_id' ) ) {
                    $user_options[] = 'workspace_id';
                }
                if ( $table->primary && ! in_array( $table->primary, $user_options ) ) {
                    array_unshift( $user_options, $table->primary );
                }
                $ctx->vars['show_list_columns'] = $user_options;
            }
        }
        $displayoption = $app->db->model( 'displayoption' )->get_by_key(
            ['workspace_id' => $workspace_id, 'model' => $model] );
        if ( $displayoption->id ) {
            if ( $this->exclude_case( $app, $displayoption, $workspace_id ) ) {
                return true;
            }
            $table = $app->get_table( $model );
            $edit_columns = $displayoption->edit_columns;
            $props = $edit_columns ? json_decode( $edit_columns, true ) : [];
            if ( empty( $props ) ) return true;
            $overwrite_options = [];
            $can_hide_in_edit = false;
            $can_sort_in_edit = false;
            if ( is_array( $props ) && isset( $props['columns'] ) ) {
                $overwrite_options = $props['columns'];
            }
            if ( is_array( $props ) && isset( $props['can_hide_in_edit'] ) ) {
                $can_hide_in_edit = $props['can_hide_in_edit'];
            }
            if ( is_array( $props ) && isset( $props['can_sort_in_edit'] ) ) {
                $can_sort_in_edit = $props['can_sort_in_edit'];
            }
            if (! $can_hide_in_edit ) {
                $ctx->vars['_can_hide_edit_col'] = false;
                $hide_edit_options = $ctx->vars['hide_edit_options'];
                $display_options = $ctx->vars['display_options'];
                $columns = $app->db->model( 'column' )->load( ['table_id' => $table->id ] );
                foreach ( $columns as $column ) {
                    if (! in_array( $column->name, $overwrite_options ) ) {
                        $hide_edit_options[] = $column->name;
                    }
                }
                $obj = $ctx->stash( 'object' );
                $fields = PTUtil::get_fields( $obj );
                foreach ( $fields as $field ) {
                    $field_name = $field->basename;
                    $field_name = "field-{$field_name}";
                    if (! in_array( $field_name, $overwrite_options ) ) {
                        $hide_edit_options[] = $field_name;
                    }
                }
                $ctx->vars['hide_edit_options'] = $hide_edit_options;
            }
            if ( !empty( $overwrite_options ) ) {
                $ctx->vars['display_options'] = $props['columns'];
            }
            if (! $can_sort_in_edit ) {
                $ctx->vars['_can_sort_edit_col'] = false;
            }
            if ( is_array( $props ) && isset( $props['orders'] ) ) {
                $ctx->vars['_field_sort_order'] = implode( ',', $props['orders'] );
            }
        }
        return true;
    }
}