<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );

class BannedWords extends PTPlugin {

    public $check_tags = false;
    
    function __construct () {
        parent::__construct();
    }

    function post_init ( $app ) {
        if ( $app->id != 'Prototype' ) return;
        if ( $app->mode != 'save' ) return;
        $workspace_id = (int) $app->param( 'workspace_id' );
        $models = $this->get_config_value( 'bannedwords_models', $workspace_id );
        $models = explode( ',', $models );
        $model = $app->param( '_model' );
        if (! in_array( $model, $models ) ) {
            return;
        }
        $inheritance = $this->get_config_value( 'bannedwords_inheritance', $workspace_id );
        if ( $inheritance ) {
            $rules = trim( $this->get_config_value( 'bannedwords_rules', 0 ) );
        } else {
            $rules = trim( $this->get_config_value( 'bannedwords_rules', $workspace_id ) );
        }
        if (! $rules ) return;
        if ( in_array( 'tag', $models ) ) {
            $this->check_tags = true;
        }
        $app->register_callback( $model, 'pre_save', 'pre_save_object', 1, $this );
        return true;
    }

    function pre_save_object ( &$cb, $app, $obj, $original ) {
        $scheme  = $app->get_scheme_from_db( $obj->_model );
        $columns = $scheme['column_defs'];
        $excludes = ['text_format', 'basename', 'extra_path', 'rev_changed', 'rev_diff', 'rev_note', 'uuid'];
        $text_cols = [];
        foreach ( $columns as $column => $props ) {
            if ( $props['type'] == 'string' || $props['type'] == 'text' ) {
                if (! in_array( $column, $excludes ) ) {
                    $text_cols[] = $column;
                }
            }
        }
        $workspace_id = (int) $app->param( 'workspace_id' );
        $inheritance = $this->get_config_value( 'bannedwords_inheritance', $workspace_id );
        if ( $inheritance ) {
            $rules = trim( $this->get_config_value( 'bannedwords_rules', 0 ) );
        } else {
            $rules = trim( $this->get_config_value( 'bannedwords_rules', $workspace_id ) );
        }
        $rules = preg_replace( "/\r\n|\r/","\n", $rules );
        $rules = explode( "\n", $rules );
        $all_fields = PTUtil::get_fields( $obj, [], 'objects', true );
        $banned_words = [];
        $hilight_ids = [];
        $replace_map = [];
        $replace_columns = $app->param( 'banned_words_replace_columns' );
        foreach ( $text_cols as $text_col ) {
            foreach ( $rules as $rule ) {
                $cond = preg_split( '/\s*,\s*/', $rule );
                $rule = $cond[0];
                $replace = isset( $cond[1] ) ? $cond[1] : '';
                if ( stripos( $obj->$text_col, $rule ) !== false ) {
                    $continue = false;
                    if ( is_array( $replace_columns ) ) {
                        foreach ( $replace_columns as $replace_column ) {
                            $replace_column = explode( ',', $replace_column );
                            if ( $replace_column[0] == $text_col && $replace_column[1] == $rule ) {
                                $continue = true;
                                break;
                            }
                        }
                        if ( $continue ) {
                            $replaced = $obj->$text_col;
                            $search = preg_quote( $rule, '/' );
                            $replaced = preg_replace( "/$search/si", $replace, $replaced );
                            $obj->$text_col( $replaced );
                            $_REQUEST[ $text_col ] = $replaced;
                        }
                    }
                }
                if ( stripos( $obj->$text_col, $rule ) !== false ) {
                    $words = isset( $banned_words[ $text_col ] ) ? $banned_words[ $text_col ] : [];
                    $words[ $rule ] = mb_substr_count( strtolower( $obj->$text_col ), strtolower( $rule ) );
                    $banned_words[ $text_col ] = $words;
                    $hilight_ids["#{$text_col}" ] = true;
                    $hilight_ids["#editor-{$text_col}-wrapper"] = true;
                    if ( $replace ) {
                        $replace_vars = isset( $replace_map[ $text_col ] ) ? $replace_map[ $text_col ] : [];
                        $replace_vars[] =
                            ['banned_words_replace_field_name' => 
                                $app->translate( $app->translate( $text_col, '', $app, 'default' ) ),
                             'banned_words_replace_rule' => $rule,
                             'banned_words_replace_replace' => $replace ];
                        $replace_map[ $text_col ] = $replace_vars;
                    }
                }
            }
        }
        $_banned_words = [];
        foreach ( $banned_words as $column_name => $error ) {
            $column_name = $app->translate( $app->translate( $column_name, '', $app, 'default' ) );
            $_banned_words[ $column_name ] = $error;
        }
        $banned_words = $_banned_words;
        if ( $this->check_tags ) {
            if ( $tags = $app->param( 'additional_tags' ) ) {
                foreach ( $rules as $rule ) {
                    $cond = preg_split( '/\s*,\s*/', $rule );
                    $rule = $cond[0];
                    $replace = isset( $cond[1] ) ? $cond[1] : '';
                    if ( stripos( $tags, $rule ) !== false ) {
                        $continue = false;
                        if ( is_array( $replace_columns ) ) {
                            foreach ( $replace_columns as $replace_column ) {
                                $replace_column = explode( ',', $replace_column );
                                if ( $replace_column[0] == 'additional_tags' && $replace_column[1] == $rule ) {
                                    $continue = true;
                                    break;
                                }
                            }
                        }
                        if ( $continue ) {
                            $replaced = $tags;
                            $search = preg_quote( $rule, '/' );
                            $replaced = preg_replace( "/$search/si", $replace, $replaced );
                            $_REQUEST['additional_tags'] = $replaced;
                            $tags = $replaced;
                        }
                    }
                    if ( stripos( $tags, $rule ) !== false ) {
                        if ( isset( $banned_words[ $app->translate( 'Tag' ) ] ) ) {
                            $banned_words[ $app->translate( 'Tag' ) ][ $rule ] = 1;
                        } else {
                            $banned_words[ $app->translate( 'Tag' ) ] = [ $rule => 1 ];
                        }
                        $hilight_ids['#additional_tags'] = true;
                        if ( $replace ) {
                            $replace_vars = isset( $replace_map['additional_tags'] ) ? $replace_map['additional_tags'] : [];
                            $replace_vars[] =
                            ['banned_words_replace_field_name' => $app->translate( 'Tag' ),
                             'banned_words_replace_rule' => $rule,
                             'banned_words_replace_replace' => $replace ];
                            $replace_map['additional_tags'] = $replace_vars;
                        }
                    }
                }
            }
        }
        foreach ( $all_fields as $field ) {
            $param_name = $field->basename . '__c';
            $params = $app->param( $param_name );
            $value = '';
            if ( is_array( $params ) ) {
                foreach ( $params as $param ) {
                    $json = json_decode( $param, true );
                    $keys = array_keys( $json );
                    foreach ( $keys as $key ) {
                        $value .= $json[ $key ];
                    }
                }
            } else {
                $json = json_decode( $params, true );
                $keys = array_keys( $json );
                foreach ( $keys as $key ) {
                    $value .= $json[ $key ];
                }
            }
            $field_name = $field->translate ? $app->translate( $field->name ) : $field->name;
            $field_basename = $field->basename;
            foreach ( $rules as $rule ) {
                $cond = preg_split( '/\s*,\s*/', $rule );
                $rule = $cond[0];
                $replace = isset( $cond[1] ) ? $cond[1] : '';
                if ( stripos( $value, $rule ) !== false ) {
                    $continue = false;
                    if ( is_array( $replace_columns ) ) {
                        foreach ( $replace_columns as $replace_column ) {
                            $replace_column = explode( ',', $replace_column );
                            if ( $replace_column[0] == $param_name && $replace_column[1] == $rule ) {
                                $continue = true;
                                break;
                            }
                        }
                    }
                    if ( $continue ) {
                        $value = '';
                        if ( is_array( $params ) ) {
                            $new_params = [];
                            $params = $app->param( $param_name );
                            foreach ( $params as $param ) {
                                $json = json_decode( $param, true );
                                $keys = array_keys( $json );
                                $_new_params = [];
                                foreach ( $keys as $key ) {
                                    $_value = $json[ $key ];
                                    if ( stripos( $_value, $rule ) !== false ) {
                                        $search = preg_quote( $rule, '/' );
                                        $_value = preg_replace( "/$search/si", $replace, $_value );
                                        $value .= $_value;
                                        $new = [ $key => $_value ];
                                        $_new_params[] = $new;
                                    }
                                }
                                $param = json_encode( $_new_params, JSON_UNESCAPED_UNICODE );
                                $new_params[] = $param;
                            }
                            $params = $new_params;
                            $app->param( $param_name, $params );
                            $_GET[ $param_name ] = $params; // TODO
                        } else {
                            $json = json_decode( $params, true );
                            $keys = array_keys( $json );
                            $_new_params = [];
                            foreach ( $keys as $key ) {
                                $_value = $json[ $key ];
                                if ( stripos( $_value, $rule ) !== false ) {
                                    $search = preg_quote( $rule, '/' );
                                    $_value = preg_replace( "/$search/si", $replace, $_value );
                                }
                                $_new_params[ $key ] = $_value;
                                $value .= $_value;
                            }
                            $params = json_encode( $_new_params, JSON_UNESCAPED_UNICODE );
                            $app->param( $param_name, $params );
                            $_GET[ $param_name ] = $params; // TODO
                        }
                    }
                }
                if ( stripos( $value, $rule ) !== false ) {
                    $words = isset( $banned_words[ $field_name ] ) ? $banned_words[ $field_name ] : [];
                    $words[ $rule ] = mb_substr_count( strtolower($value ), strtolower( $rule ) );
                    $banned_words[ $field_name ] = $words;
                    $hilight_ids["#field-{$field_basename}-wrapper textarea"] = true;
                    $hilight_ids["#field-{$field_basename}-wrapper input"] = true;
                    $hilight_ids["#field-{$field_basename}-wrapper select"] = true;
                    if ( $replace ) {
                        $replace_vars = isset( $replace_map["{$field_basename}__c"] ) ? $replace_map["{$field_basename}__c"] : [];
                        $replace_vars[] = ['banned_words_replace_field_name' => $field_name,
                             'banned_words_replace_rule' => $rule,
                             'banned_words_replace_replace' => $replace ];
                        $replace_map["{$field_basename}__c"] = $replace_vars;
                    }
                }
            }
        }
        $ignore_uncheck = $app->param( 'banned_words_ignore_uncheck' );
        if (! $ignore_uncheck && ! empty( $banned_words ) ) {
            $error_messages = [];
            foreach ( $banned_words as $column_name => $error ) {
                $keys = array_keys( $error );
                $values = array_values( $error );
                $count = 0;
                foreach ( $values as $value ) {
                    $count += $value;
                }
                if ( $count == 1 ) {
                    $error_messages[] = 
                    $this->translate( 'The %s contains %s banned word( %s ).', [ $column_name, $count, implode( ', ', $keys ) ] );
                } else {
                    $error_messages[] = 
                    $this->translate( 'The %s contains %s banned words( %s ).', [ $column_name, $count, implode( ', ', $keys ) ] );
                }
            }
            $cb['errors'] = array_merge( $cb['errors'], $error_messages );
            $tmpl = $this->path() . DS . 'tmpl' . DS . 'form_header.tmpl';
            $app->ctx->vars['banned_words_hilight_ids'] = array_keys( $hilight_ids );
            $app->ctx->vars['banned_words_replace_map'] = $replace_map;
            $form_header = $app->ctx->build( file_get_contents( $tmpl ) );
            $app->ctx->vars['form_header'] = $form_header;
            $tmpl = $this->path() . DS . 'tmpl' . DS . 'form_footer.tmpl';
            $form_footer = $app->ctx->build( file_get_contents( $tmpl ) );
            $app->ctx->vars['form_footer'] = $form_footer;
            return false;
        }
        return true;
    }
}