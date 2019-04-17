<?php

class PTForm {

    public  $identifer;
    private $sessions = [];
    private $attachments = []; // Attch to Email

    function confirm ( $app, $url ) {
        $ctx = $app->ctx;
        $this->identifer = $app->param( 'magic_token' )
                         ? $app->param( 'magic_token' ) : $app->magic();
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
            $attachmentfiles = [];
            $q_map = [];
            $email = '';
            $primary_col = '';
            $confirm_ok = $this->validation( $app, $form, $values, $errors,
                                             $email, $mail_col, $params, $raw_params,
                                             $primary_col, $attachmentfiles, $q_map );
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
        $this->identifer = $app->param( 'magic_token' )
                         ? $app->param( 'magic_token' ) : $app->magic();
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
            $attachmentfiles = [];
            $q_map = [];
            $confirm_ok = $this->validation( $app, $form, $values, $errors, $email,
                                             $mail_col, $params, $raw_params,
                                             $primary_col, $attachmentfiles, $q_map );
            if (! empty( $errors ) ) {
                $ctx->vars['error'] = true;
            }
            $this->set_vars( $app, $ctx, $form, $params, $raw_params );
            if ( $confirm_ok ) {
                if ( $primary_col ) {
                    $primary = $values[ $primary_col ];
                    // unset( $values[ $primary_col ] );
                } else {
                    $primary = current( $values );
                    array_shift( $values );
                }
                $app->get_scheme_from_db( 'contact' );
                $contact = $app->db->model( 'contact' )->new();
                $contact->subject( $primary );
                $contact->email( $email );
                if ( $identifier = $app->param( '_identifier' ) ) {
                    $contact->identifier( $identifier );
                }
                // unset( $values[ $mail_col ] );
                $contact->data( json_encode( $values,
                                JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT ) );
                $contact->question_map( json_encode( $q_map,
                                JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT ) );
                $contact->form_id( $form_id );
                $contact->state( 1 );
                $object_id = $app->param( 'object_id' )
                           ? (int)  $app->param( 'object_id' )
                           : (int) $url->object_id;
                $model     = $app->param( 'model' )
                           ? $app->param( 'model' ) : $url->model;
                $ws_id = $form->workspace_id;
                if ( $ws_id ) $ws_id = (int) $ws_id;
                $contact->workspace_id( $ws_id );
                if ( $object_id && $model ) {
                    $app->get_scheme_from_db( $model );
                    $object = $app->db->model( $model )->load( $object_id );
                    if ( $object ) {
                        if ( $url->model != $model || $url->object_id != $object_id ) {
                            if ( $object->form_id != $form->id ) {
                                $model = $url->model;
                                $object_id = (int) $url->object_id;
                                $object = $app->db->model( $model )->load( $object_id );
                            }
                        }
                        if ( $object ) {
                            $contact->model( $object->_model );
                            $contact->object_id( $object->id );
                        }
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
                if (! $form->not_save ) {
                    if ( $contact->save() ) {
                        if (! empty( $attachmentfiles ) ) {
                            $to_ids = [];
                            foreach ( $attachmentfiles as $sess ) {
                                $attachment = $app->db->model('attachmentfile')->new();
                                $attachment->name( $sess->value );
                                $attachment->mime_type( $sess->key );
                                $attachment->workspace_id( $contact->workspace_id );
                                $attachment->file( $sess->data );
                                $json = json_decode( $sess->text );
                                $attachment->size( $json->file_size );
                                $app->set_default( $attachment );
                                $attachment->save();
                                $to_ids[] = $attachment->id;
                                $metadata = $app->db->model( 'meta' )->get_by_key(
                                   ['model' => 'attachmentfile', 'object_id' => $attachment->id,
                                                  'kind' => 'metadata', 'key' => 'file' ] );
                                $metadata->text( $sess->text );
                                $metadata->metadata( $sess->metadata );
                                $metadata->data( $sess->extradata );
                                $metadata->save();
                                $this->sessions[] = $sess;
                            }
                            $args = ['from_id' => $contact->id, 
                                     'name' => 'attachmentfiles',
                                     'from_obj' => 'contact',
                                     'to_obj' => 'attachmentfile'];
                            $app->set_relations( $args, $to_ids, true, $errors );
                        }
                        $app->init_callbacks( 'contact', 'post_save' );
                        $callback = ['name' => 'post_save',
                                     'form' => $form, 'values' => $values ];
                        $app->run_callbacks( $callback, 'contact', $contact );
                    }
                } else {
                    $message = $this->translate(
                            'Failed to save a contact for %s.', $form->name );
                    $app->log( ['message'   => $message,
                                'category'  => 'contact',
                                'model'     => 'form',
                                'object_id' => $form->id,
                                'level'     => 'error'] );
                    $ctx->vars['submit_ok'] = false;
                }
                $message = $this->translate(
                        'The contact posted for %s has been received.', $form->name );
                $app->log( ['message'   => $message,
                            'category'  => 'contact',
                            'model'     => 'form',
                            'object_id' => $form->id,
                            'level'     => 'info'] );
                $ctx->vars['contact_name'] = $contact->name;
                $ctx->vars['contact_email'] = $contact->email;
                $ctx->vars['contact_id'] = $contact->id;
                $err = '';
                if ( $form->send_email ) {
                    $from = $form->email_from ? $form->email_from : '';
                    $from = $from ? $app->build( $from ) : '';
                    $system_email = '';
                    if (! $from || ! $app->is_valid_email( $from, $err ) ) {
                        $system_email = $app->get_config( 'system_email' );
                        if (!$system_email ) {
                            return $app->error( 'System Email Address is not set in System.' );
                        }
                        $from = $system_email->value;
                        $system_email = $from;
                    }
                    $headers = ['From' => $from ];
                    $app->set_mail_param( $ctx );
                    $ctx->vars['form_name'] = $form->name;
                    if ( $form->send_thanks && $contact->email ) {
                        $subject = null;
                        $body = null;
                        $template = null;
                        $template_id = $form->thanks_template;
                        if ( $template_id ) {
                            $template =
                                $app->db->model( 'template' )->load( (int) $template_id );
                            if ( $template && $template->text ) {
                                $body = $template->text;
                            }
                            if ( is_object( $template ) ) {
                                $ctx->stash( 'current_template', $template );
                            }
                        }
                        if (! $body ) {
                            $body = $app->get_mail_tmpl( 'form_thanks', $template );
                        }
                        if ( $template ) {
                            $subject = $template->subject;
                        }
                        if (! $subject ) {
                            $subject = $this->translate(
                                'The inquiry you posted for %s has been received.', $form->name );
                        }
                        $ctx->vars['mail_type'] = 'thanks';
                        $subject = $app->build( $subject );
                        $body = $app->build( $body );
                        if ( $thanks_cc = $form->thanks_cc ) {
                            $thanks_cc = $app->build( $thanks_cc );
                            if ( $thanks_cc ) {
                                $headers['Cc'] = $thanks_cc;
                            }
                        }
                        if ( $thanks_bcc = $form->thanks_bcc ) {
                            $thanks_bcc = $app->build( $thanks_bcc );
                            if ( $thanks_bcc ) {
                                $headers['Bcc'] = $thanks_bcc;
                            }
                        }
                        $mail_error = '';
                        if (! PTUtil::send_mail( $contact->email,
                            $subject, $body, $headers, $mail_error ) ) {
                            $message =
                                $this->translate( 'Failed to send a thank you email.(%s)',
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
                        $from = $form->notify_from ? $form->notify_from : $system_email;
                        $from = $from ? $app->build( $from ) : '';
                        if (! $from || ! $app->is_valid_email( $from, $err ) ) {
                            $system_email = $app->get_config( 'system_email' );
                            if (!$system_email ) {
                                return $app->error( 'System Email Address is not set in System.' );
                            }
                            $from = $system_email->value;
                        }
                        $headers = ['From' => $from ];
                        $subject = null;
                        $body = null;
                        $template = null;
                        $template_id = $form->notify_template;
                        if ( $template_id ) {
                            $template = $app->
                                db->model( 'template' )->load( (int) $template_id );
                            if ( $template && $template->text ) {
                                $body = $template->text;
                            }
                            if ( is_object( $template ) ) {
                                $ctx->stash( 'current_template', $template );
                            }
                        }
                        if (! $body ) {
                            $body = $app->get_mail_tmpl( 'form_notify', $template );
                        }
                        if ( $template ) {
                            $subject = $template->subject;
                        }
                        if (! $subject ) {
                            $subject = $this->translate(
                                'The inquiry posted for %s has been received.', $form->name );
                        }
                        // ?__mode=view&_type=edit&_model=contact&id=n
                        $contact_param = '?__mode=view&_type=edit&_model=contact&id=';
                        $contact_param .= $contact->id;
                        if ( $contact->workspace_id ) {
                            $contact_param .= '&workspace_id=' . $contact->workspace_id;
                        }
                        $ctx->vars['contact_param'] = $contact_param;
                        $ctx->vars['mail_type'] = 'notify';
                        $subject = $app->build( $subject );
                        $body = $app->build( $body );
                        $mail_error = '';
                        $to = $form->notify_to;
                        if (! $to ) {
                            $form_user = $form->created_by ? $form->created_by : $form->modified_by;
                            if ( $form_user ) {
                                $form_user = $app->db->model( 'user' )->load( (int) $form_user );
                                if ( $form_user ) $to = $form_user->email;
                            }
                            if (! $to ) {
                                $to = $from;
                            }
                        }
                        $to = $app->build( $to );
                        unset( $headers['Cc'] );
                        unset( $headers['Bcc'] );
                        if ( $notify_cc = $form->notify_cc ) {
                            $notify_cc = $app->build( $notify_cc );
                            if ( $notify_cc ) {
                                $headers['Cc'] = $notify_cc;
                            }
                        }
                        if ( $notify_bcc = $form->notify_bcc ) {
                            $notify_bcc = $app->build( $notify_bcc );
                            if ( $notify_bcc ) {
                                $headers['Bcc'] = $notify_bcc;
                            }
                        }
                        $files = $this->attachments;
                        $res = empty( $files )
                             ? PTUtil::send_mail( $to, $subject, $body, $headers, $mail_error )
                             : PTUtil::send_multipart_mail( $to, $subject, $body, $headers, $files, $mail_error );
                        if (! $res ) {
                            $message =
                                $this->translate( 'Failed to send a notification email.(%s)',
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
                if (! empty( $this->sessions ) ) {
                    $sessions = $this->sessions;
                    $app->db->model( 'session' )->remove_multi( $sessions );
                }
                $redirect_url = $form->redirect_url;
                if ( $redirect_url ) {
                    $redirect_url = $app->build( $redirect_url );
                    return $app->redirect( $redirect_url );
                }
                $ctx->vars['submit_ok'] = true;
            } else {
                $ctx->vars['errors'] = $errors;
            }
        } else {
            if ( $form && $form->status == 5 ) {
                $errors[] = $this->translate( "The reception on '%s' has been closed.",
                    $form->name );
            } else {
                $errors[] = $this->translate( 'Invalid request.' );
            }
            $ctx->vars['errors'] = $errors;
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
        if ( $ws = $form->workspace ) {
            $ctx->stash( 'workspace', $ws );
            $ctx->vars['workspace_id'] = $ws->id;
        }
        if ( $form->requires_token ) {
            if ( $app->mode == 'confirm' ) {
                $token = $this->identifer;
                $sess = $app->db->model( 'session' )->get_by_key( ['name' => $token,
                                                                   'kind' => 'CR'] );
                $sess->start( time() );
                $sess->value( $form->id );
                if ( $ws ) {
                    $sess->workspace_id( $ws->id );
                }
                $form_expires = $form->token_expires
                              ? $form->token_expires : $app->form_expires;
                $sess->expires( time() + $form_expires );
                $sess->save();
                $ctx->vars['magic_token'] = $token;
                $ctx->local_vars['magic_token'] = $token;
            }
        }
    }

    function validation ( $app, $form, &$values = [], &$errors = [],
        &$email = '', &$mail_col = '', &$params = [], &$raw_params = [],
        &$primary_col = '', &$attachmentfiles = [], &$q_map ) {
        if ( $form->status == 5 ) {
            $errors[] = $this->translate( "The reception on '%s' has been closed.",
                $form->name );
        } else if ( $form->status != 4 ) {
            $errors[] = $this->translate( 'Invalid request.' );
        }
        $validations = isset( $app->registry['form_validations'] )
                     ? $app->registry['form_validations'] : [];
        $language = $app->param( '_language' );
        if ( $form->requires_token && $app->mode != 'confirm' ) {
            $token = $app->param( 'magic_token' );
            if (! $token ) {
                $errors[] = $this->translate( 'Invalid request.' );
                return false;
            }
            $sess = $app->db->model( 'session' )->get_by_key(
                ['name' => $token, 'kind' => 'CR'] );
            if (! $sess->id ) {
                $errors[] = $this->translate( 'Invalid request.' );
                return false;
            }
            if ( $sess->expires < time() ) {
                $this->sessions[] = $sess;
                $errors[] = $this->translate( 'Your session has expired.' );
                return false;
            }
            if ( $app->mode == 'submit' ) {
                $this->sessions[] = $sess;
            }
        }
        $spam = $app->db->model( 'remote_ip' )->count(
            ['remote_ip' => $app->remote_ip,
             'class' => 'spam'] );
        if ( $spam ) {
            $errors[] = $this->translate( 'Post not allowed.' );
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
                $this->translate(
                'Too many posts have been submitted from you in a short period of time.' )
                . $this->translate( 'Please try again in a short while.' );
            return false;
        }
        $confirm_ok = true;
        $ctx = $app->ctx;
        $questions = $app->get_related_objs( $form, 'question', 'questions' );
        foreach ( $questions as $question ) {
            $basename = $question->basename;
            $question_label = $question->label;
            $error_label = $language ? $this->translate( $question_label ) : $question_label;
            if ( $question->required && $question->is_primary && ! $primary_col ) {
                $primary_col = 'question_' . $question->id;
            }
            $qt = $question->questiontype;
            $question_type = null;
            if ( $qt ) {
                $question_type = $qt->class ? $qt->class : $qt->basename;
            }
            $images = $app->images;
            $videos = $app->videos;
            $audios = $app->audios;
            $value = '';
            if ( $question_type && $question_type == 'file' ) {
                $file_token = $this->identifer . '-' . $basename;
                $sess = $app->db->model( 'session' )
                    ->get_by_key( [ 'name' => $file_token, 'kind' => 'UP' ] );
                if ( $app->mode == 'confirm' ) {
                    if ( isset( $_FILES['question_' . $basename ] ) ) {
                        $upload_dir = $app->upload_dir();
                        $upload_path = $upload_dir . DS;
                        $filename = $_FILES['question_' . $basename ]['name'];
                        $value = $filename;
                        $app->ctx->vars['filename_' . $basename ] = $value;
                        $upload_path .= $filename;
                        if ( move_uploaded_file( $_FILES['question_'
                            . $basename ]['tmp_name'], $upload_path ) ) {
                            $error_msg = '';
                            $ext = strtolower( pathinfo( $upload_path, PATHINFO_EXTENSION ) );
                            $extensions = $question->options;
                            if ( $extensions ) {
                                $extensions = strtolower( $extensions );
                                $extensions = preg_split( '/\s*,\s*/', $extensions );
                                if (! in_array( $ext, $extensions ) ) {
                                    $error_msg =
                                        $this->translate(
                                            'The file (%s) that you uploaded is not allowed.',
                                            $filename );
                                }
                            }
                            $maxlength = $question->maxlength;
                            if (! $error_msg && $maxlength ) {
                                $unit = $question->unit;
                                $filesize = filesize( $upload_path );
                                if ( $unit && $unit == 'MB' ) {
                                    $maxlength = $maxlength * 1024 * 1024;
                                } else if ( $unit && $unit == 'KB' ) {
                                    $maxlength = $maxlength * 1024;
                                }
                                if ( $filesize >= $maxlength ) {
                                    $error_msg =
                                        $this->translate( 'The file you uploaded is too large.' );
                                }
                            }
                            if (! $error_msg ) {
                                $fileError = null;
                                $data = PTUtil::get_upload_info( $app, $upload_path, $fileError );
                                if (! $fileError ) {
                                    $thumbnail_small = isset( $data['thumbnail_small'] )
                                                     ? $data['thumbnail_small']
                                                     : '';
                                    $thumbnail_square= isset( $data['thumbnail_square'] )
                                                     ? $data['thumbnail_square']
                                                     : '';
                                    if ( $thumbnail_small ) {
                                        $sess->extradata( file_get_contents( $thumbnail_small ) );
                                    }
                                    if ( $thumbnail_square ) {
                                        $sess->metadata( file_get_contents( $thumbnail_square ) );
                                    }
                                    $metadata = $data['metadata'];
                                    $sess->text( json_encode( $metadata ) );
                                    $sess->data( file_get_contents( $upload_path ) );
                                    $sess->value( $filename );
                                    $sess->key( $_FILES['question_' . $basename ]['type'] );
                                    // $app->fmgr->delete( $upload_path );
                                    $sess->start( time() );
                                    $sess->workspace_id( $form->workspace_id );
                                    $form_expires = $form->token_expires
                                                  ? $form->token_expires : $app->form_expires;
                                    $sess->expires( time() + $form_expires );
                                    $sess->save();
                                } else {
                                    $error_msg = $this->translate( $fileError );
                                }
                            }
                        } else {
                            if (! $sess->id && $question->required ) {
                                $error_msg = $this->translate(
                                    '%s is required.', $error_label );
                            }
                        }
                    }
                }
                if ( $sess->id ) {
                    if ( $question->attach_to_email ) {
                        $this->attachments[] = $sess;
                    }
                    $attachmentfiles[] = $sess;
                    $value = $sess->value;
                    $app->ctx->vars['filename_' . $basename ] = $value;
                    $values['question_' . $question->id ] = $value;
                    $q_map['question_' . $question->id ] = $question->label;
                    $param = ['post_question' => $question->label, 'post_value' => $value ];
                    if (! $question->hide_in_email ) {
                        $params[] = $param;
                    }
                    $raw_params[] = $param;
                }
            } else {
                $value = $app->param( 'question_' . $basename );
                $normarize = $question->normarize;
                $error_msg = '';
                if ( $normarize ) {
                    if ( function_exists( 'normalizer_normalize' ) ) {
                        if ( is_array( $value ) ) {
                            $new_vars = [];
                            foreach ( $value as $v ) {
                                $v = normalizer_normalize( $v, Normalizer::NFKC );
                                $new_vars[] = $v; 
                            }
                            $value = $new_vars;
                            $_POST['question_' . $basename ] = $new_vars;
                            $_REQUEST['question_' . $basename ] = $new_vars;
                        } else {
                            $value = normalizer_normalize( $value, Normalizer::NFKC );
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
                                $error_msg = $this->translate(
                                    '%s is required.', $error_label );
                                continue;
                            }
                        }
                    } else {
                        if (! $value ) $error_msg = $this->translate(
                                    '%s is required.', $error_label );
                    }
                }
                $orig_values = null;
                if ( is_array( $value ) ) {
                    $orig_values = $value;
                    $connector = $question->connector;
                    if ( strpos( $connector, ',' ) !== false 
                        && trim( $connector ) != ',' ) {
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
                    if (! $question->required && ! $value ) {
                        continue;
                    }
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
                                    $this->translate( '%s is invalid.', $error_label );
                            }
                        }
                    }
                }
                if ( $question->multiple && is_array( $orig_values ) ) {
                    $values['question_' . $question->id ] = $orig_values;
                } else {
                    $values['question_' . $question->id ] = $value;
                }
                $q_map['question_' . $question->id ] = $question->label;
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
                        $error_msg = $this->translate( '%s is too long.', $error_label );
                    }
                }
                if (! $error_msg && $question->validation_type ) {
                    if (! $question->required && $question->connector ) {
                        $raw_value = str_replace( $question->connector, '', $value );
                        if (! $raw_value ) continue;
                    }
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
                            $error_msg = $this->translate( '%s is invalid.', $error_label );
                        }
                    } else if ( $vtype == 'Postal Code' ) {
                        if (! preg_match( "/^\d{3}\-{0,1}\d{4}$/", $value ) ) {
                            $error_msg = $this->translate( '%s is invalid.', $error_label );
                        }
                    } else if ( $vtype == 'Date' ) {
                        $check_val = preg_replace( "/[^0-9]*/", '', $value );
                        if (! preg_match( "/^[0-9]{8}$/", $check_val ) ) {
                            $error_msg = $this->translate( '%s is invalid.', $error_label );
                        } else {
                            $y = substr( $check_val, 0, 4 );
                            $m = substr( $check_val, 4, 2 );
                            $d = substr( $check_val, 6, 2 );
                            if (! checkdate( $m, $d, $y ) ) {
                                $error_msg = $this->translate( '%s is invalid.', $error_label );
                            }
                        }
                    } else if ( $vtype == 'Date & Time' ) {
                        $check_val = preg_replace( "/[^0-9]*/", '', $value );
                        if (! preg_match( "/^[0-9]{14}$/", $check_val ) ) {
                            $error_msg = $this->translate( '%s is invalid.', $error_label );
                        } else {
                            $y = substr( $check_val, 0, 4 );
                            $m = substr( $check_val, 4, 2 );
                            $d = substr( $check_val, 6, 2 );
                            if (! checkdate( $m, $d, $y ) ) {
                                $error_msg = $this->translate( '%s is invalid.', $error_label );
                            }
                        }
                        if (! $error_msg ) {
                            $time = substr( $check_val, 8, 6 );
                            if ( $time > 235959 ) {
                                $error_msg = $this->translate( '%s is invalid.', $error_label );
                            }
                        }
                    } else if ( $vtype == 'Selected Items' ) {
                        $items = trim( $question->values )
                               ? trim( $question->values )
                               : trim( $question->options );
                        if ( $normarize ) {
                            if ( function_exists( 'normalizer_normalize' ) ) {
                                $items = normalizer_normalize( $items, Normalizer::NFKC );
                            }
                        }
                        $items = preg_split( "/\s*,\s*/", $items );
                        if ( is_array( $orig_values ) ) {
                            foreach ( $orig_values as $v ) {
                                if (! $v && ! $question->required ) {
                                } else if (! in_array( $v, $items ) ) {
                                    $error_msg =
                                      $this->translate( '%s is invalid.', $error_label );
                                    continue;
                                }
                            }
                        } else {
                            if (! $value && ! $question->required ) {
                            } else if (! in_array( $value, $items ) ) {
                                $error_msg = $this->translate( '%s is invalid.', $error_label );
                            }
                        }
                    }
                }
            }
            if ( $error_msg ) {
                $ctx->vars['question_' . $basename . '_error'] = $error_msg;
                $errors[] = $error_msg;
                $confirm_ok = false;
            }
        }
        return $confirm_ok;
    }

    function translate ( $phrase, $params = '' ) {
        $app = Prototype::get_instance();
        if ( $app->param( '_language' ) ) {
            $lang = $app->param( '_language' );
        } else {
            $lang = $app->user() ? $app->user()->language : $app->language;
        }
        if (!$lang ) $lang = 'default';
        $dict = isset( $app->dictionary ) ? $app->dictionary : null;
        if ( $dict && isset( $dict[ $lang ] ) && isset( $dict[ $lang ][ $phrase ] ) )
             $phrase = $dict[ $lang ][ $phrase ];
        $phrase = is_string( $params )
            ? sprintf( $phrase, $params ) : vsprintf( $phrase, $params );
        return $phrase;
    }

}
