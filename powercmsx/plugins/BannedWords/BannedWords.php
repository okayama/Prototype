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
        foreach ( $text_cols as $text_col ) {
            foreach ( $rules as $rule ) {
                if ( stripos( $obj->$text_col, $rule ) !== false ) {
                    $words = isset( $banned_words[ $text_col ] ) ? $banned_words[ $text_col ] : [];
                    $words[ $rule ] = mb_substr_count( strtolower( $obj->$text_col ), strtolower( $rule ) );
                    $banned_words[ $text_col ] = $words;
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
                    if ( stripos( $tags, $rule ) !== false ) {
                        if ( isset( $banned_words[ $app->translate( 'Tag' ) ] ) ) {
                            $banned_words[ $app->translate( 'Tag' ) ][ $rule ] = 1;
                        } else {
                            $banned_words[ $app->translate( 'Tag' ) ] = [ $rule => 1 ];
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
                    $key = key( $json );
                    $value .= $json[ $key ];
                }
            } else {
                $json = json_decode( $params, true );
                $key = key( $json );
                $value .= $json[ $key ];
            }
            $field_name = $field->translate ? $app->translate( $field->name ) : $field->name;
            foreach ( $rules as $rule ) {
                if ( stripos( $value, $rule ) !== false ) {
                    $words = isset( $banned_words[ $field_name ] ) ? $banned_words[ $field_name ] : [];
                    $words[ $rule ] = mb_substr_count( strtolower($value ), strtolower( $rule ) );
                    $banned_words[ $field_name ] = $words;
                }
            }
        }
        if (! empty( $banned_words ) ) {
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
            $cb['errors'] = array_merge( $cb['errors'], $error_messages);
            return false;
        }
        return true;
    }
}