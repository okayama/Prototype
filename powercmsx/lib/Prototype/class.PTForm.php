<?php

class PTForm {

    function confirm ( $app, $url ) {
        $ctx = $app->ctx;
        $form_id = $app->param( 'form_id' );
        if ( $form_id ) {
            $form = $app->db->model( 'form' )->load( (int) $form_id );
            if (! $form ) return;
            $values = [];
            $errors = [];
            $confirm_ok = $this->validation( $app, $form, $values, $errors );
            $ctx->vars['errors'] = $errors;
            $ctx->vars['confirm_ok'] = $confirm_ok;
        }
    }

    function submit ( $app, $url ) {
        $ctx = $app->ctx;
        $form_id = $app->param( 'form_id' );
        if ( $form_id ) {
            $form = $app->db->model( 'form' )->load( (int) $form_id );
            if (! $form ) return;
            $values = [];
            $errors = [];
            $email = '';
            $confirm_ok = $this->validation( $app, $form, $values,
                                             $errors, $email, $mail_col );
            if ( $confirm_ok ) {
                $primary = current( $values );
                $app->get_scheme_from_db( 'contact' );
                $contact = $app->db->model( 'contact' )->new();
                $contact->subject( $primary );
                $contact->email( $email );
                array_shift( $values );
                unset( $values[ $mail_col ] );
                $contact->data( json_encode( $values ) );
                $contact->form_id( $form_id );
                $object_id = (int) $url->object_id;
                $model = $url->model;
                $ws_id = $app->param( 'workspace_id' );
                if ( $ws_id ) $ws_id = (int) $ws_id;
                if ( $ws_id ) {
                    $contact->workspace_id( $ws_id );
                }
                if ( $object_id && $model ) {
                    $app->get_scheme_from_db( $model );
                    $object = $app->db->model( $model )->load( $object_id );
                    if ( $object ) {
                        $contact->model( $model );
                        $contact->object_id( $object_id );
                    }
                }
                $app->set_default( $contact );
                if (! $contact->save() ) {
                    // 
                }
                // TODO
                // Error handling, Custom Validation, Callback, Attachment File(s)
                // Admin Screen, Notification Email
                $ctx->vars['submit_ok'] = true;
            }
        }
    }

    function validation ( $app, $form, &$values = [], &$errors = [],
        &$email = '', &$mail_col = '' ) {
        $confirm_ok = true;
        $ctx = $app->ctx;
        $questions = $app->get_related_objs( $form, 'question', 'questions' );
        foreach ( $questions as $question ) {
            $basename = $question->basename;
            $value = $app->param( 'question_' . $basename );
            $normarize = $question->normarize;
            $error_msg = '';
            if ( $normarize ) {
                if ( function_exists( 'normalizer_normalize' ) ) {
                    if ( is_array( $value ) ) {
                        $new_vars = [];
                        foreach ( $value as $v ) {
                            $v = normalizer_normalize( $v, Normalizer::NFKD );
                            $new_vars[] = $v; 
                        }
                        $value = $new_vars;
                        $_POST['question_' . $basename ] = $new_vars;
                        $_REQUEST['question_' . $basename ] = $new_vars;
                    } else {
                        $value = normalizer_normalize( $value, Normalizer::NFKD );
                        $_POST['question_' . $basename ] = $value;
                        $_REQUEST['question_' . $basename ] = $value;
                    }
                }
            }
            if ( $question->format ) {
                if ( is_array( $value ) ) {
                    $new_vars = [];
                    $formats = preg_split( "/\s*,\s*/", $question->format );
                    $i = 0;
                    foreach ( $value as $v ) {
                        if ( isset( $formats[ $i ] ) ) {
                            $v = $v ? sprintf( $formats[ $i ], $v ) : $v;
                        }
                        $new_vars[] = $v;
                        $i++;
                    }
                    $value = $new_vars;
                    $_POST['question_' . $basename ] = $new_vars;
                    $_REQUEST['question_' . $basename ] = $new_vars;
                } else {
                    $value = $value ? sprintf( $question->format, $value ) : $value;
                }
            }
            if ( $question->required ) {
                if ( is_array( $value ) ) {
                    foreach ( $value as $v ) {
                        if (! $v ) {
                            $error_msg = $app->translate(
                                '%s is required.', $question->label );
                            continue;
                        }
                    }
                } else {
                    if (! $value ) $error_msg = $app->translate(
                                '%s is required.', $question->label );
                }
            }
            $orig_values = null;
            if ( is_array( $value ) ) {
                $orig_values = $value;
                $connector = $question->connector;
                if ( strpos( $connector, ',' ) !== false ) {
                    $connectors = preg_split( "/\s*,\s*/", $connector );
                    $i = 0;
                    $new_var = '';
                    foreach ( $value as $v ) {
                        $new_var .= $v;
                        $new_var .= isset( $connectors[$i] ) ? $connectors[$i] : '';
                        $i++;
                    }
                    $value = $new_var;
                } else {
                    $value = implode( $question->connector, $value );
                }
            }
            $values['question_' . $question->id ] = $value;
            $maxlength = $question->maxlength;
            if (! $error_msg && $maxlength ) {
                $multi_byte = $question->multi_byte;
                $length = $multi_byte ? mb_strlen( $value ) : strlen( $value );
                if ( $maxlength < $length ) {
                    $error_msg = $app->translate( '%s is too long.', $question->label );
                }
            }
            if (! $error_msg && $question->validation_type ) {
                $vtype = $question->validation_type;
                // Email,Select Items,Tel,Postal Code,URL
                if ( $vtype == 'Email' ) {
                    $error_msg = '';
                    if (!$app->is_valid_email( $value, $error_msg ) ) {
                    } else {
                        $email = $email ? $email : $value;
                        $mail_col = $mail_col ? $mail_col : 'question_' . $question->id;
                    }
                } else if ( $vtype == 'URL' ) {
                    if (!$app->is_valid_url( $value, $error_msg ) ) {
                    }
                } else if ( $vtype == 'Tel' ) {
                    $p = '/\A(((0(\d{1}[-(]?\d{4}|\d{2}[-(]?\d{3}|\d{3}[-(]?\d{2}|\d{4}'
                    .'[-(]?\d{1}|[5789]0[-(]?\d{4})[-)]?)|\d{1,4}\-?)\d{4}|0120[-(]?\d{3}'
                    .'[-)]?\d{3})\z/';
                    if (! preg_match( $p, $value ) ) {
                        $error_msg = $app->translate( '%s is invalid.', $question->label );
                    }
                } else if ( $vtype == 'Postal Code' ) {
                    if (! preg_match( "/^\d{3}\-{0,1}\d{4}$/", $value ) ) {
                        $error_msg = $app->translate( '%s is invalid.', $question->label );
                    }
                } else if ( $vtype == 'Date' ) {
                    $check_val = preg_replace( "/[^0-9]*/", '', $value );
                    if (! preg_match( "/^[0-9]{8}$/", $check_val ) ) {
                        $error_msg = $app->translate( '%s is invalid.', $question->label );
                    } else {
                        $y = substr( $check_val, 0, 4 );
                        $m = substr( $check_val, 4, 2 );
                        $d = substr( $check_val, 6, 2 );
                        if (! checkdate( $m, $d, $y ) ) {
                            $error_msg = $app->translate( '%s is invalid.', $question->label );
                        }
                    }
                } else if ( $vtype == 'Date & Time' ) {
                    $check_val = preg_replace( "/[^0-9]*/", '', $value );
                    if (! preg_match( "/^[0-9]{14}$/", $check_val ) ) {
                        $error_msg = $app->translate( '%s is invalid.', $question->label );
                    } else {
                        $y = substr( $check_val, 0, 4 );
                        $m = substr( $check_val, 4, 2 );
                        $d = substr( $check_val, 6, 2 );
                        if (! checkdate( $m, $d, $y ) ) {
                            $error_msg = $app->translate( '%s is invalid.', $question->label );
                        }
                    }
                    if (! $error_msg ) {
                        $time = substr( $check_val, 8, 6 );
                        if ( $time > 235959 ) {
                            $error_msg = $app->translate( '%s is invalid.', $question->label );
                        }
                    }
                } else if ( $vtype == 'Selected Items' ) {
                    $items = trim( $question->values )
                           ? trim( $question->values )
                           : trim( $question->options );
                    if ( $normarize ) {
                        if ( function_exists( 'normalizer_normalize' ) ) {
                            $items = normalizer_normalize( $items, Normalizer::NFKD );
                        }
                    }
                    $items = preg_split( "/\s*,\s*/", $items );
                    if ( is_array( $orig_values ) ) {
                        foreach ( $orig_values as $v ) {
                            if (! $v && ! $question->required ) {
                            } else if (! in_array( $v, $items ) ) {
                                $error_msg =
                                  $app->translate( '%s is invalid.', $question->label );
                                continue;
                            }
                        }
                    } else {
                        if (! $value && ! $question->required ) {
                        } else if (! in_array( $value, $items ) ) {
                            $error_msg = $app->translate( '%s is invalid.', $question->label );
                        }
                    }
                } else {
                    // by Plugins
                }
            }
            if ( $error_msg ) {
                $ctx->vars['question_' . $basename . '_error'] = $error_msg;
                $errors[] = $error_msg;
                $confirm_ok = false;
                continue;
            }
        }
        return $confirm_ok;
    }
}
