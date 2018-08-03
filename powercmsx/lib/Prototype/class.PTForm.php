<?php

class PTForm {

    function confirm ( $app, $url ) {
        $ctx = $app->ctx;
        $form_id = $app->param( 'form_id' );
        if ( $form_id ) {
            $form = $app->db->model( 'form' )->load( (int) $form_id );
            if (! $form ) return;
            $ctx->stash( 'form', $form );
            $ctx->stash( 'current_context', 'form' );
            $values = [];
            $errors = [];
            $params = [];
            $raw_params = [];
            $email = '';
            $confirm_ok = $this->validation( $app, $form, $values, $errors,
                                             $email, $mail_col, $params, $raw_params );
            if (! empty( $errors ) ) {
                $ctx->vars['error'] = true;
            }
            $ctx->vars['errors'] = $errors;
            $ctx->vars['confirm_ok'] = $confirm_ok;
            $this->set_vars( $app, $ctx, $form, $params, $raw_params );
        }
    }

    function submit ( $app, $url ) {
        $ctx = $app->ctx;
        $errors = [];
        $form_id = $app->param( 'form_id' );
        $form = null;
        if ( $form_id ) {
            $form = $app->db->model( 'form' )->load( (int) $form_id );
        }
        if ( $form && $app->request_method == 'POST' && $form->status == 4 ) {
            $ctx->stash( 'form', $form );
            $ctx->stash( 'current_context', 'form' );
            $values = [];
            $params = [];
            $raw_params = [];
            $email = '';
            $primary_col = '';
            $confirm_ok = $this->validation( $app, $form, $values, $errors, $email,
                                             $mail_col, $params, $raw_params, $primary_col );
            if (! empty( $errors ) ) {
                $ctx->vars['error'] = true;
            }
            $this->set_vars( $app, $ctx, $form, $params, $raw_params );
            if ( $confirm_ok ) {
                if ( $primary_col ) {
                    $primary = $values[ $primary_col ];
                    unset( $values[ $primary_col ] );
                } else {
                    $primary = current( $values );
                    array_shift( $values );
                }
                $app->get_scheme_from_db( 'contact' );
                $contact = $app->db->model( 'contact' )->new();
                $contact->subject( $primary );
                $contact->email( $email );
                unset( $values[ $mail_col ] );
                $contact->data( json_encode( $values,
                                JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT ) );
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
                $app->init_callbacks( 'contact', 'save_filter' );
                $app->init_callbacks( 'contact', 'pre_save' );
                $callback = ['name' => 'save_filter', 'error' => '',
                             'errors' => $errors, 'values' => $values,
                             'form' => $form ];
                $save_filter = $app->run_callbacks( $callback, 'contact', $contact );
                $errors = $callback['errors'];
                if ( $msg = $callback['error'] ) {
                    $errors[] = $msg;
                }
                if (! $save_filter || !empty( $errors ) ) {
                    $ctx->vars['error'] = true;
                    $ctx->vars['errors'] = $errors;
                    return;
                }
                $app->set_default( $contact );
                $callback = ['name' => 'pre_save', 'error' => '', 'is_new' => true,
                             'values' => $values, 'form' => $form ];
                $pre_save = $app->run_callbacks( $callback, 'contact', $contact, $contact );
                if ( $msg = $callback['error'] ) {
                    $errors[] = $msg;
                }
                if (! $pre_save || !empty( $errors ) ) {
                    $ctx->vars['error'] = true;
                    $ctx->vars['errors'] = $errors;
                    return;
                }
                if ( $contact->save() ) {
                    $app->init_callbacks( 'contact', 'post_save' );
                    $callback = ['name' => 'post_save',
                                 'form' => $form, 'values' => $values ];
                    $app->run_callbacks( $callback, 'contact', $contact );
                    $message = $app->translate(
                            'The contact posted for %s has been received.', $form->name );
                    $app->log( ['message'   => $message,
                                'category'  => 'contact',
                                'model'     => 'form',
                                'object_id' => $form->id,
                                'level'     => 'info'] );
                    $ctx->vars['contact_name'] = $contact->name;
                    $ctx->vars['contact_email'] = $contact->email;
                    $ctx->vars['contact_id'] = $contact->id;
                    if ( $form->send_email ) {
                        $from = $form->email_from ? $form->email_from : '';
                        if (! $from ) {
                            $system_email = $app->get_config( 'system_email' );
                            if (!$system_email ) {
                                return $app->error( 'System Email Address is not set in System.' );
                            }
                            $from = $system_email->value;
                        }
                        $headers = ['From' => $from ];
                        if ( $form->send_thanks && $contact->email ) {
                            $subject = $form->thanks_subject
                                     ? $form->thanks_subject : $app->translate(
                            'The inquiry you posted for %s has been received.', $form->name );
                            $template = $form->thanks_template;
                            $body = '';
                            if ( $template ) {
                                $tmpl = $app->
                                    db->model( 'template' )->load( (int) $template );
                                if ( $tmpl && $tmpl->text ) {
                                    $body = $tmpl->text;
                                }
                            }
                            if (! $body ) {
                                $tmpl = TMPL_DIR . 'email' . DS . 'form_thanks.tmpl';
                                $body = file_get_contents( $tmpl );
                            }
                            $subject = $app->build( $subject );
                            $body = $app->build( $body );
                            $mail_error = '';
                            if (! PTUtil::send_mail( $contact->email,
                                $subject, $body, $headers, $mail_error ) ) {
                                $message =
                                    $app->translate( 'Failed to send a thank you email.(%s)',
                                                     $mail_error );
                                $metadata = ['subject' => $subject, 'body' => $body ];
                                $metadata = json_encode( $metadata,
                                 JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT );
                                $app->log( ['message'   => $message,
                                            'category'  => 'contact',
                                            'metadata'  => $metadata,
                                            'model'     => 'form',
                                            'object_id' => $form->id,
                                            'level'     => 'error'] );
                            }
                        }
                        if ( $form->send_notify ) {
                            $subject = $form->notify_subject
                                     ? $form->notify_subject : $app->translate(
                            'The inquiry posted for %s has been received.', $form->name );
                            $template = $form->notify_template;
                            $body = '';
                            if ( $template ) {
                                $tmpl = $app->
                                    db->model( 'template' )->load( (int) $template );
                                if ( $tmpl && $tmpl->text ) {
                                    $body = $tmpl->text;
                                }
                            }
                            if (! $body ) {
                                $tmpl = TMPL_DIR . 'email' . DS . 'form_notify.tmpl';
                                $body = file_get_contents( $tmpl );
                            }
                            // ?__mode=view&_type=edit&_model=contact&id=8
                            $contact_param = '?__mode=view&_type=edit&_model=contact&id=';
                            $contact_param .= $contact->id;
                            if ( $contact->workspace_id ) {
                                $contact_param .= '&workspace_id=' . $contact->workspace_id;
                            }
                            $ctx->vars['contact_param'] = $contact_param;
                            $subject = $app->build( $subject );
                            $body = $app->build( $body );
                            $mail_error = '';
                            if (! PTUtil::send_mail( $contact->email,
                                $subject, $body, $headers, $mail_error ) ) {
                                $message =
                                    $app->translate( 'Failed to send a notification email.(%s)',
                                                     $mail_error );
                                $metadata = ['subject' => $subject, 'body' => $body ];
                                $metadata = json_encode( $metadata,
                                 JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT );
                                $app->log( ['message'   => $message,
                                            'category'  => 'contact',
                                            'metadata'  => $metadata,
                                            'model'     => 'form',
                                            'object_id' => $form->id,
                                            'level'     => 'error'] );
                            }
                        }
                    }
                    $redirect_url = $form->redirect_url;
                    if ( $redirect_url ) {
                        $redirect_url = $app->build( $redirect_url );
                        return $app->redirect( $redirect_url );
                    }
                    // TODO
                    // Attachment File(s)
                    // Mail Templates, Export CSV
                    $ctx->vars['submit_ok'] = true;
                } else {
                    $message = $app->translate(
                            'Failed to save a contact for %s.', $form->name );
                    $app->log( ['message'   => $message,
                                'category'  => 'contact',
                                'model'     => 'form',
                                'object_id' => $form->id,
                                'level'     => 'error'] );
                    $ctx->vars['submit_ok'] = false;
                }
            }
        } else {
            if ( $form && $form->status == 5 ) {
                $errors[] = $app->translate( "The reception on '%s' has been closed.",
                    $form->name );
            } else {
                $errors[] = $app->translate( 'Invalid request.' );
            }
        }
    }

    function set_vars ( $app, $ctx, $form, $params, $raw_params ) {
        $ctx->vars['post_params'] = $params;
        $ctx->vars['post_raw_params'] = $raw_params;
        $form_values = $form->get_values();
        foreach ( $form_values as $k => $v ) {
            $ctx->vars[ $k ] = $v;
        }
        $ctx->stash( 'current_context', 'form' );
        $ctx->stash( 'form', $form );
        $ws = null;
        if ( $ws = $app->workspace() ) {
            $ctx->stash( 'workspace', $ws );
            $ctx->vars['workspace_id'] = $ws->id;
        }
        if ( $form->requires_token ) {
            if ( $app->mode == 'confirm' ) {
                $token = $app->magic();
                $sess = $app->db->model( 'session' )->new();
                $sess->name( $token );
                $sess->kind( 'CR' );
                $sess->start( time() );
                $sess->value( $form->id );
                if ( $ws ) {
                    $sess->workspace_id( $ws->id );
                }
                $form_expires = $form->token_expires
                              ? $form->token_expires : $app->form_expires;
                $sess->expires( time() + $form_expires );
                $ctx->vars['magic_token'] = $token;
                $sess->save();
            }
        }
    }

    function validation ( $app, $form, &$values = [], &$errors = [],
        &$email = '', &$mail_col = '', &$params = [], $raw_params = [],
        &$primary_col = '' ) {
        if ( $form->status == 5 ) {
            $errors[] = $app->translate( "The reception on '%s' has been closed.",
                $form->name );
        } else if ( $form->status != 4 ) {
            $errors[] = $app->translate( 'Invalid request.' );
        }
        $validations = isset( $app->registry['form_validations'] )
                     ? $app->registry['form_validations'] : [];
        if ( $form->requires_token && $app->mode != 'confirm' ) {
            $token = $app->param( 'magic_token' );
            if (! $token ) {
                $errors[] = $app->translate( 'Invalid request.' );
                return false;
            }
            $sess = $app->db->model( 'session' )->get_by_key(
                ['name' => $token, 'kind' => 'CR'] );
            if (! $sess->id ) {
                $errors[] = $app->translate( 'Invalid request.' );
                return false;
            }
            if ( $sess->expires < time() ) {
                $errors[] = $app->translate( 'Your session has expired.' );
                return false;
            }
        }
        $spam = $app->db->model( 'remote_ip' )->count(
            ['remote_ip' => $app->remote_ip,
             'class' => 'spam'] );
        if ( $spam ) {
            $errors[] = $app->translate( 'Post not allowed.' );
            return false;
        }
        $form_interval = (int) $app->form_interval;
        $form_upper_limit = (int) $app->form_upper_limit;
        $tsfrom = $form->ts2db( date( 'YmdHis', strtotime( "-{$form_interval} second" ) ) );
        $logs = $app->db->model( 'log' )->count( [
            'category' => 'contact',
            'remote_ip' => $app->remote_ip, 'created_on' => ['>' => $tsfrom] ] );
        if ( $logs && $logs > $form_upper_limit ) {
            $errors[] =
                $app->translate(
                'Too many posts have been submitted from you in a short period of time.' )
                . $app->translate( 'Please try again in a short while.' );
            return false;
        }
        $confirm_ok = true;
        $ctx = $app->ctx;
        $questions = $app->get_related_objs( $form, 'question', 'questions' );
        foreach ( $questions as $question ) {
            $basename = $question->basename;
            if ( $question->required && $question->is_primary && ! $primary_col ) {
                $primary_col = 'question_' . $question->id;
            }
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
            if (! $error_msg && $question->validation_type ) {
                $vtype = $question->validation_type;
                // by Plugins
                if ( isset( $validations[ $vtype ] ) ) {
                    $validation = $validations[ $vtype ];
                    $component = $validation['component'];
                    $method = $validation['method'];
                    $class = $app->component( $component );
                    if ( $class && method_exists( $class, $method ) ) {
                        if (! $class->$method( $app, $question, $value, $error_msg ) ) {
                            $error_msg = $error_msg ? $error_msg : 
                                $app->translate( '%s is invalid.', $question->label );
                        }
                    }
                }
            }
            $values['question_' . $question->id ] = $value;
            $param = ['post_question' => $question->label, 'post_value' => $value ];
            if (! $question->hide_in_email ) {
                $params[] = $param;
            }
            $raw_params[] = $param;
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
