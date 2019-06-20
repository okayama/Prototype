<?php

class PTMembers extends Prototype {

    public $cookie_name = 'pt-member';
    public $member = null;
    public $excludes = ['id', 'notification', 'status', 'delete_flag', 'created_by',
                        'lock_out', 'modified_by', 'lock_out_on', 'created_on',
                        'modified_on', 'last_login_on', 'reg_workspace_id'];
    public $non_editable = [];

    function __construct ( $options = [] ) {
        $this->id = 'Members';
        parent::__construct( $options );
    }

    function user () {
        return;
    }

    function login ( $model = 'member', $return_url = null ) {
        $app = $this;
        $component = $app->component( 'Members' );
        $workspace_id = $app->workspace_id
                      ? $app->workspace_id : $app->param( 'workspace_id' );
        if ( $app->request_method == 'POST' ) {
            $app->two_factor_auth = $component->get_config_value( 'members_two_factor_auth', $workspace_id );
            $app->sess_timeout = $component->get_config_value( 'members_sess_timeout', $workspace_id );
        }
        return parent::login( 'member', $this->get_return_url() );
    }

    function is_login ( $model = 'member' ) {
        return parent::is_login( 'member' );
    }

    function run () {
        $app = $this;
        $component = $app->component( 'Members' );
        $ctx = $app->ctx;
        $workspace_id = $app->workspace_id
                      ? $app->workspace_id : $app->param( 'workspace_id' );
        if (! $workspace_id ) $workspace_id = 0;
        if ( $app->mode == 'login' && $app->request_method == 'POST' ) {
            $app->two_factor_auth = $component->get_config_value( 'members_two_factor_auth', $workspace_id );
        }
        $member = $this->member ? $this->member : $component->member( $app );
        $allow_login = $component->get_config_value( 'members_allow_login', $workspace_id );
        if (! $allow_login ) return $app->logout( $member );
        $app->member = $member;
        if ( $member ) {
            // $ctx->vars['member_id'] = $member->id;
            $member_values = $member->get_values();
            foreach ( $member_values as $colName => $colValue ) {
                if ( strpos( $colName, 'password' ) !== false ) continue;
                $ctx->vars[ $colName ] = $colValue;
            }
        } else {
            if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) )
                $this->language = substr( $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2 );
            $ctx->vars['user_language'] = $this->language;
        }
        $ctx->vars['workspace_id'] = $workspace_id;
        $workspace = $workspace_id ?
                     $app->db->model( 'workspace' )->load( (int) $workspace_id ) : null;
        if ( $workspace_id && ! $workspace ) {
            return $app->error( 'Invalid request.' );
        }
        if ( $workspace ) {
            $ctx->stash( 'workspace', $workspace );
        }
        if ( $workspace_id ) {
            $app->workspace_id = $workspace_id;
            if ( $workspace ) {
                $ctx->stash( 'workspace', $workspace );
            }
        }
        if ( $app->mode == 'logout' ) return $app->logout( $member );
        if ( $app->mode == 'login' && !$member ) return $app->logout();
        if ( $app->mode == 'start_recover' ) return $app->__mode( 'start_recover' );
        $app_url = $component->get_config_value( 'members_app_url', $workspace_id, true );
        if (! $app_url ) {
            $component->set_config_value( 'members_app_url', $app->admin_url, $workspace_id );
        }
        $allow_sign_up = null;
        if ( $app->mode == 'do_signup' ) {
            $allow_sign_up = $component->get_config_value( 'members_allow_sign_up', $workspace_id );
            if (! $allow_sign_up ) {
                return $app->logout( $member );
            }
            $sign_up_status = $component->get_config_value( 'members_sign_up_status', $workspace_id, true );
            $ctx->vars['sign_up_status'] = $sign_up_status;
            return $app->do_signup();
        }
        if ( $app->mode == 'unsubscribe' && !$member ) return $app->logout();
        if ( $app->mode == 'unsubscribe' ) return $app->unsubscribe();
        $non_editable =
            $component->get_config_value( 'members_non_editable_cols', $workspace_id, true );
        if ( $non_editable ) {
            $non_editable = preg_split( '/\s*,\s*/', $non_editable );
            $app->non_editable = $non_editable;
            $ctx->vars['non_editable_columns'] = $non_editable;
        }
        if ( $app->mode == 'edit_profile' && !$member ) return $app->logout();
        if ( $app->mode == 'edit_profile' ) return $app->edit_profile();
        if ( $app->mode == 'sign_up' ) {
            $allow_sign_up = $allow_sign_up ? $allow_sign_up
                                            : $component->get_config_value( 'members_allow_sign_up', $workspace_id );
            if (! $allow_sign_up ) {
                return $app->logout( $member );
            }
            return $app->request_method == 'GET' ? $app->__mode( 'sign_up' )
                                                 : $app->sign_up();
        }
        if ( $app->param( '_lockedout' ) ) {
            return $app->logout( $member );
        }
        if ( $app->mode == 'recover_password' ) return $app->recover_password( 'member' );
        if ( $member ) return $app->redirect( $app->get_return_url() );
        $redirect_url = $app->admin_url . '?__mode=login';
        $redirect_url .= $workspace_id ? '&workspace_id=' . $workspace_id : '';
        $app->redirect( $redirect_url );
    }

    function unsubscribe () {
        $app = $this;
        $component = $app->component( 'Members' );
        $member = $this->member ? $this->member : $component->member( $this );
        $ctx = $app->ctx;
        $_type = $app->param( '_type' );
        $workspace_id = $app->workspace_id;
        $workspace = $ctx->stash( 'workspace' );
        if ( $_type && $_type == 'confirm' ) {
            $magic = $app->magic();
            $sess = $app->db->model( 'session' )->get_by_key( ['name' => $magic,
                                                               'kind' => 'CR'] );
            $sess->start( time() );
            $expires =
                $component->get_config_value( 'members_unsubscribe_timeout', $workspace_id, true );
            $sess->expires( time() + $expires );
            $sess->workspace_id( $workspace_id );
            $sess->save();
            $ctx->vars['magic_token'] = $magic;
            $ctx->local_vars['magic_token'] = $magic;
            return $this->__mode( 'unsubscribe' );
        }
        if ( $_type && $_type == 'unsubscribe' && $app->request_method == 'POST' ) {
            $token = $app->param( 'magic_token' );
            if (! $token ) {
                return $app->error( 'Invalid request.' );
            }
            $sess = $app->db->model( 'session' )->get_by_key( ['name' => $token, 'kind' => 'CR' ] );
            if ( !$sess->id ) {
                return $app->error( 'Invalid request.' );
            }
            if ( $sess->expires < time() ) {
                return $app->error( 'Your session has expired. Please unregister again.' );
            }
            $member->status( 1 );
            $member->delete_flag( 1 );
            if ( $member->save() ) {
                //$sess->remove();
                $mailto = $component->get_config_value(
                    'members_unsubscribe_notify_mailto', $workspace_id, true );
                if ( $mailto ) {
                    // send mail to administer
                    $app->set_mail_param( $ctx );
                    $subject = null;
                    $body = null;
                    $template = null;
                    $body = $app->get_mail_tmpl( 'members_unsubscribe_notification', $template, $workspace );
                    if ( $template ) {
                        $subject = $template->subject;
                    }
                    if (! $subject ) {
                        $subject = $app->translate( "A member '%s' unsubscribe on '%s'",
                            [ $member->name, $ctx->vars['app_name'] ] );
                    }
                    $mail_from = $component->mail_from( $app, $workspace_id );
                    $error = '';
                    $pt_path = dirname( $app->pt_path );
                    $search = preg_quote( $app->document_root, '/' );
                    $pt_path = preg_replace( "/^$search/", '', $pt_path );
                    $pt_path = str_replace( '\\', '/', $pt_path );
                    $admin_url = $app->base . $pt_path . '/index.php';
                    $ctx->vars['admin_url'] = $admin_url;
                    $ctx->vars['member_id'] = $member->id;
                    $ctx->vars['member_name'] = $member->name;
                    $ctx->vars['member_email'] = $member->email;
                    $ctx->vars['member_nickname'] = $member->nickname;
                    $body = $app->build( $body );
                    $subject = $app->build( $subject );
                    $mail_from = $app->build( $mail_from );
                    $mailto = $app->build( $mailto );
                    $headers = ['From' => $mail_from ];
                    $error = '';
                    if (! PTUtil::send_mail(
                        $mailto, $subject, $body, $headers, $error ) ) {
                        // Not show error for member
                    }
                }
            } else {
                return $app->error( 'An error occurred while saving %s.', $app->translate( 'Member' ) );
            }
            $return_url = $ctx->vars['script_uri'] . '?__mode=logout';
            $return_url .= $workspace_id ? '&workspace_id=' . $workspace_id : '';
            $app->redirect( $return_url );
        }
    }

    function edit_profile () {
        $app = $this;
        $db = $app->db;
        $ctx = $app->ctx;
        $component = $app->component( 'Members' );
        $workspace_id = $app->workspace_id;
        $workspace = $ctx->stash( 'workspace' );
        $table = $app->get_table( 'member' );
        $columns = $db->model( 'column' )->load( ['table_id' => $table->id ],
                                            ['sort' => 'order', 'direction' => 'ascend'] );
        $excludes = $app->excludes;
        $member = $app->member;
        $fmgr = $app->fmgr;
        $scheme = $app->get_scheme_from_db( 'member' );
        $normarize =
            $component->get_config_value( 'members_sign_up_normarize', $workspace_id, true );
        $non_editable = $app->non_editable;
        if ( $app->request_method == 'POST' ) {
            $app->validate_magic();
            $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];
            $errors = [];
            $messages = [];
            $file_names = [];
            $values = [];
            $related_objs = [];
            foreach ( $columns as $column ) {
                if ( in_array( $column->name, $excludes ) ) {
                    continue;
                } else if (! empty( $non_editable ) && in_array( $column->name, $non_editable ) ) {
                    continue;
                }
                $col = $column->name;
                if ( $column->unique ) {
                    if (! $app->validate_unique( $column, $member, $errors ) ) {
                        continue;
                    }
                }
                if ( $column->edit == 'file' && $column->type == 'blob' ) {
                    $file_names[ $column->name ] = $app->param( $column->name );
                    $base64 = $app->param( $column->name . '_base64' );
                    if ( $base64 ) {
                        $ctx->vars[ $column->name . '_base64'] = $base64;
                        $values[ $column->name ] = $base64;
                    }
                    $data = $app->base64_encode_file( $column, $errors, $messages );
                    if ( $data ) {
                        $ctx->vars[ $column->name . '_base64'] = $data;
                        $_REQUEST[ $column->name ] = $_FILES[ $column->name ]['name'];
                        $values[ $column->name ] = $data;
                    }
                } else if ( $column->edit == 'password(hash)' ) {
                    $pass = $app->param( $column->name );
                    $verify = $app->param( $column->name . '-verify' );
                    if ( $pass || $verify ) {
                        $msg = '';
                        if (!$app->is_valid_password( $pass, $verify, $msg ) ) {
                            $errors[] = $msg;
                            continue;
                        }
                    }
                    if ( $pass ) {
                        $values[ $column->name ] = password_hash( $pass, PASSWORD_BCRYPT );
                    }
                } else if ( $column->name == 'email' ) {
                    $data = $app->param( $column->name );
                    $msg = '';
                    if ( $normarize && function_exists( 'normalizer_normalize' ) ) {
                        $data = normalizer_normalize( $data, Normalizer::NFKC );
                        $_REQUEST[ $column->name ] = $data;
                    }
                    if (!$app->is_valid_email( $data, $msg ) ) {
                        $errors[] = $msg;
                        continue;
                    }
                    $values[ $column->name ] = $data;
                } else if ( $column->edit == 'datetime' || $column->edit == 'date' ) {
                    $data = $app->validate_date( $column, $member, $errors );
                    $values[ $column->name ] = $data;
                } else if ( $column->edit == 'selection' && $column->disp_edit == 'checkbox' ) {
                    $data = $app->param( $column->name );
                    if ( is_array( $data ) ) {
                        if ( empty( $data ) && $column->not_null ) {
                            $errors[] = $app->translate( '%s is required.', $app->translate( $column->label ) );
                            continue;
                        }
                        $data = implode( ',', $data );
                        if ( $normarize && function_exists( 'normalizer_normalize' ) ) {
                            $data = normalizer_normalize( $data, Normalizer::NFKC );
                        }
                        $_REQUEST[ $column->name ] = $data;
                        $values[ $column->name ] = $data;
                    }
                } else if ( $column->type == 'relation' & strpos( $column->edit, 'relation:' ) === 0 ) {
                    list( $edit, $rel_model, $rel_col, $rel_type ) = explode( ':', $column->edit );
                    $ids = $app->param( $column->name );
                    $relatedObjs = is_array( $ids )
                                  ? $db->model( $rel_model )->load( ['id' => ['IN' => $ids ] ] )
                                  : $db->model( $rel_model )->load( (int) $ids );
                    $to_ids = [];
                    if ( is_array( $relatedObjs ) && count( $relatedObjs ) ) {
                        foreach ( $relatedObjs as $rerated_obj ) {
                            $to_ids[] = (int) $rerated_obj->id;
                        }
                    } else if ( is_object( $relatedObjs ) ) {
                        $to_ids[] = (int) $relatedObjs->id;
                    }
                    $related_objs[ $column->name ] = $to_ids;
                } else {
                    if (! isset( $_REQUEST[ $column->name ] ) ) continue;
                    $data = $app->param( $column->name );
                    if ( $column->not_null && ! $data ) {
                        $errors[] = $app->translate( '%s is required.', $app->translate( $column->label ) );
                        continue;
                    }
                    if ( $normarize && function_exists( 'normalizer_normalize' ) ) {
                        $data = normalizer_normalize( $data, Normalizer::NFKC );
                    }
                    $_REQUEST[ $column->name ] = $data;
                    $values[ $column->name ] = $data;
                }
            }
            if ( empty( $errors ) ) {
                foreach ( $columns as $column ) {
                    if ( in_array( $column->name, $excludes ) ) {
                        continue;
                    }
                    $upload_dir = $app->upload_dir();
                    $key = $column->name;
                    if (! isset( $values[ $key ] ) ) continue;
                    if ( $column->edit == 'file' && $column->type == 'blob' ) {
                        $file_name = $file_names[ $key ];
                        $value = $values[ $key ];
                        $base64 = explode( ',', $value );
                        $mime_type = explode( ':', explode( ';', $base64[0] )[0] )[1];
                        $parts = explode( '.', $file_name );
                        $extIndex = count( $parts ) - 1;
                        $extension = strtolower( @$parts[ $extIndex ] );
                        unset( $base64[0] );
                        $data = '';
                        foreach ( $base64 as $d ) {
                            $data .= $d;
                        }
                        $data = base64_decode( $data );
                        $member->$key( $data );
                        $basename = tempnam( $upload_dir, '' );
                        unlink( $basename );
                        $out = $basename . DS . $file_name;
                        $fmgr->put( $out, $data );
                        PTUtil::file_attach_to_obj( $app, $member, $key, $out );
                    } else {
                        $member->$key( $values[ $key ] );
                    }
                }
                if (! empty( $related_objs ) ) {
                    foreach ( $related_objs as $col => $to_ids ) {
                        $rel_model = isset( $relations[ $col ] ) ? $relations[ $col ] : null;
                        if (! $rel_model ) continue;
                        $args = ['from_id' => $member->id, 
                                 'name' => $col,
                                 'from_obj' => 'member',
                                 'to_obj' => $rel_model ];
                        $app->set_relations( $args, $to_ids, false, $errors );
                    }
                }
                $member->save();
                $return_url = $ctx->vars['script_uri'] . '?__mode=edit_profile&saved=1';
                $return_url .= $workspace_id ? '&workspace_id=' . $workspace_id : '';
                $app->redirect( $return_url );
            } else {
                $ctx->vars['errors'] = $errors;
                $ctx->vars['messages'] = $messages;
            }
        } else {
            foreach ( $columns as $column ) {
                if ( in_array( $column->name, $excludes ) ) {
                    continue;
                }
                $col = $column->name;
                if ( $column->edit == 'file' && $column->type == 'blob' ) {
                    if ( $member->$col ) {
                        $meta = $db->model( 'meta' )->get_by_key( ['model' => 'member',
                                                                   'kind' => 'metadata',
                                                                   'key' => $col,
                                                                   'object_id' => $member->id ] );
                        if ( $meta->text ) {
                            $metadata = json_decode( $meta->text, true );
                            $_REQUEST[ $col ] = $metadata['file_name'];
                            $mime_type = $metadata['mime_type'];
                            $data = base64_encode( $member->$col );
                            $data = "data:{$mime_type};base64,{$data}";
                            $ctx->vars[ $col . '_base64' ] = $data;
                        }
                    }
                } else if ( $column->edit == 'datetime' || $column->edit == 'date' ) {
                    if ( $member->$col ) {
                        $date = $member->ts2db( $member->db2ts( $member->$col ) );
                        list( $d, $t ) = explode( ' ', $date );
                        $_REQUEST[ $col ] = $d;
                        $_REQUEST[ "{$col}_time" ] = $t;
                    }
                } else if ( $column->type == 'relation' && strpos( $column->edit, 'relation:' ) === 0 ) {
                    list( $edit, $rel_model, $rel_col, $rel_type ) = explode( ':', $column->edit );
                    $relations = $app->get_relations( $member, $rel_model );
                    if ( $rel_type == 'checkbox' || $rel_type == 'hierarchy' || $rel_type == 'dialog' ) {
                        $rel_ids = [];
                        foreach ( $relations as $relation ) {
                            $rel_ids[] = $relation->to_id;
                        }
                        $_REQUEST[ $col ] = $rel_ids;
                    } else {
                        if ( count( $relations ) ) {
                            $relation = $relations[0];
                            $_REQUEST[ $col ] = $relation->to_id;
                        }
                    }
                } else {
                    $_REQUEST[ $col ] = $member->$col;
                }
            }
        }
        return $app->__mode( 'edit_profile' );
    }

    function do_signup () {
        $app = $this;
        $db = $app->db;
        $db->begin_work();
        $app->txn_active = true;
        $scheme = $app->get_scheme_from_db( 'member' );
        $relations = isset( $scheme['relations'] ) ? $scheme['relations'] : [];
        $component = $app->component( 'Members' );
        $ctx = $app->ctx;
        $workspace_id = $app->workspace_id ? $app->workspace_id : 0;
        $workspace = $ctx->stash( 'workspace' );
        if ( $app->param( 'signup' ) ) {
            return $this->__mode( 'do_signup' );
        }
        $token = $app->param( 'token' );
        if (! $token ) {
            return $app->error( 'Invalid request.' );
        }
        $sess = $app->db->model( 'session' )->get_by_key( ['name' => $token, 'kind' => 'TM' ] );
        if ( !$sess->id ) {
            return $app->error( 'Invalid request.' );
        }
        if ( $sess->expires < time() ) {
            return $app->error( 'Your confirmation has expired. Please register again.' );
        }
        $data = json_decode( $sess->data, true );
        $values = $data['values'];
        $file_names = $data['file_names'];
        $member = $app->db->model( 'member' )->new();
        $upload_dir = $app->upload_dir();
        $fmgr = $app->fmgr;
        $files = [];
        $related_objs = [];
        foreach ( $values as $key => $value ) {
            if ( isset( $file_names[ $key ] ) && $value ) {
                $base64 = explode( ',', $value );
                $mime_type = explode( ':', explode( ';', $base64[0] )[0] )[1];
                $file_name = $file_names[ $key ];
                $parts = explode( '.', $file_name );
                $extIndex = count( $parts ) - 1;
                $extension = strtolower( @$parts[ $extIndex ] );
                unset( $base64[0] );
                $data = '';
                foreach ( $base64 as $d ) {
                    $data .= $d;
                }
                $data = base64_decode( $data );
                $member->$key( $data );
                $basename = tempnam( $upload_dir, '' );
                unlink( $basename );
                $file_name = $file_names[ $key ];
                $out = $basename . DS . $file_name;
                $fmgr->put( $out, $data );
                $files[ $key ] = $out;
            } else {
                if ( count( $relations ) && isset( $relations[ $key ] ) ) {
                    $related_objs[ $key ] = $value;
                } else {
                    $member->$key( $value );
                }
            }
        }
        $app->set_default( $member );
        // default_status
        $status = $component->get_config_value( 'members_sign_up_status', $workspace_id, true );
        $member->status( (int) $status );
        $member->reg_workspace_id( $workspace_id );
        if ( $member->save() ) {
            $sess->remove();
        } else {
            return $app->error( 'An error occurred while saving %s.', $app->translate( 'Member' ) );
        }
        foreach ( $files as $col => $path ) {
            PTUtil::file_attach_to_obj( $app, $member, $col, $path );
        }
        if (!empty( $related_objs ) ) {
            // set relations
            foreach ( $related_objs as $col => $ids ) {
                $rel_model = $relations[ $col ];
                $rerated_objs = is_array( $ids )
                              ? $db->model( $rel_model )->load( ['id' => ['IN' => $ids ] ] )
                              : $db->model( $rel_model )->load( (int) $ids );
                $to_ids = [];
                if ( is_array( $rerated_objs ) && count( $rerated_objs ) ) {
                    foreach ( $rerated_objs as $rerated_obj ) {
                        $to_ids[] = (int) $rerated_obj->id;
                    }
                } else if ( is_object( $rerated_objs ) ) {
                    $to_ids[] = (int) $rerated_objs->id;
                }
                $args = ['from_id' => $member->id, 
                         'name' => $col,
                         'from_obj' => 'member',
                         'to_obj' => $rel_model ];
                $errors = [];
                $app->set_relations( $args, $to_ids, false, $errors );
                if (!empty( $errors ) ) {
                    return $app->rollback( join( ',', $errors ) );
                }
            }
        }
        $db->commit();
        $app->txn_active = false;
        $app->publish_obj( $member );
        $mailto = $component->get_config_value(
            'members_sign_up_notify_mailto', $workspace_id, true );
        if ( $mailto ) {
            // send mail to administer
            $app->set_mail_param( $ctx );
            $subject = null;
            $body = null;
            $template = null;
            $body = $app->get_mail_tmpl( 'members_sign_up_notification', $template, $workspace );
            if ( $template ) {
                $subject = $template->subject;
            }
            if (! $subject ) {
                $subject = $app->translate( "A new member '%s' registered on '%s'",
                    [ $member->name, $ctx->vars['app_name'] ] );
            }
            $mail_from = $component->mail_from( $app, $workspace_id );
            $error = '';
            $pt_path = dirname( $app->pt_path );
            $search = preg_quote( $app->document_root, '/' );
            $pt_path = preg_replace( "/^$search/", '', $pt_path );
            $pt_path = str_replace( '\\', '/', $pt_path );
            $admin_url = $app->base . $pt_path . '/index.php';
            $ctx->vars['admin_url'] = $admin_url;
            $ctx->vars['member_id'] = $member->id;
            $ctx->vars['member_name'] = $member->name;
            $ctx->vars['member_email'] = $member->email;
            $ctx->vars['member_nickname'] = $member->nickname;
            $body = $app->build( $body );
            $subject = $app->build( $subject );
            $mail_from = $app->build( $mail_from );
            $mailto = $app->build( $mailto );
            $headers = ['From' => $mail_from ];
            $error = '';
            if (! PTUtil::send_mail(
                $mailto, $subject, $body, $headers, $error ) ) {
                $ctx->vars['error'] = $error;
                return $app->error( $error );
            }
        }
        $return_url = $ctx->vars['script_uri'] . '?__mode=do_signup&signup=1';
        $return_url .= $workspace_id ? '&workspace_id=' . $workspace_id : '';
        $app->redirect( $return_url );
    }

    function sign_up () {
        $app = $this;
        $ctx = $app->ctx;
        $component = $this->component( 'members' );
        $workspace_id = $app->workspace_id;
        $workspace = $ctx->stash( 'workspace' );
        $table = $app->get_table( 'member' );
        $columns = $app->db->model( 'column' )->load( ['table_id' => $table->id ],
                                            ['sort' => 'order', 'direction' => 'ascend'] );
        $excludes = $app->excludes;
        $errors = [];
        $messages = [];
        $values = [];
        $file_names = [];
        $passwords = [];
        $_type = $app->param( '_type' );
        $obj = $app->db->model( 'member' )->new();
        if ( $app->request_method == 'POST' && $app->param( 'language' ) ) {
            $app->language = $app->param( 'language' );
            $ctx->language = $app->param( 'language' );
        }
        $email = null;
        $sess = null;
        $normarize =
            $component->get_config_value( 'members_sign_up_normarize', $workspace_id, true );
        if ( $_type == 'sign_up' ) {
            // token for CSRF
            $token = $app->param( 'magic_token' );
            if (! $token ) {
                return $app->error( 'Invalid request.' );
            }
            $sess = $app->db->model( 'session' )->get_by_key( ['name' => $token, 'kind' => 'CR' ] );
            if ( !$sess->id ) {
                return $app->error( 'Invalid request.' );
            }
            if ( $sess->expires < time() ) {
                return $app->error( 'Your session has expired. Please register again.' );
            }
        }
        $fmgr = $app->fmgr;
        $non_editable = $app->non_editable;
        foreach ( $columns as $column ) {
            if ( in_array( $column->name, $excludes ) ) {
                continue;
            } else if (! empty( $non_editable ) && in_array( $column->name, $non_editable ) ) {
                continue;
            }
            if ( $column->unique ) {
                if (! $app->validate_unique( $column, null, $errors ) ) {
                    continue;
                }
            }
            if ( $column->edit == 'file' && $column->type == 'blob' ) {
                $base64 = $app->param( $column->name . '_base64' );
                if ( $base64 ) {
                    $ctx->vars[ $column->name . '_base64'] = $base64;
                    $values[ $column->name ] = $base64;
                }
                if ( $_type == 'confirm' ) {
                    $data = $app->base64_encode_file( $column, $errors, $messages );
                    if ( $data ) {
                        $values[ $column->name ] = $data;
                        $ctx->vars[ $column->name . '_base64'] = $data;
                        $_REQUEST[ $column->name ] = $_FILES[ $column->name ]['name'];
                    }
                } else {
                    $data = $app->param( $column->name );
                    if ( $column->not_null && ! $data ) {
                        $errors[] = $app->translate( '%s is required.', $app->translate( $column->label ) );
                        continue;
                    } else {
                        $file_names[ $column->name ] = $data;
                    }
                }
            } else if ( $column->edit == 'password(hash)' ) {
                $pass = $app->param( $column->name );
                if ( $column->not_null && ! $pass ) {
                    $errors[] = $app->translate( '%s is required.', $app->translate( $column->label ) );
                    continue;
                }
                if ( $_type == 'confirm' ) {
                    $verify = $app->param( $column->name . '-verify' );
                    $msg = '';
                    if (!$app->is_valid_password( $pass, $verify, $msg ) ) {
                        $errors[] = $msg;
                        continue;
                    }
                }
                $values[ $column->name ] = $pass;
                $passwords[] = $column->name;
            } else if ( $column->name == 'email' ) {
                $data = $app->param( $column->name );
                if ( $normarize && function_exists( 'normalizer_normalize' ) ) {
                    $data = normalizer_normalize( $data, Normalizer::NFKC );
                    $_REQUEST[ $column->name ] = $data;
                }
                $msg = '';
                if (!$app->is_valid_email( $data, $msg ) ) {
                    $errors[] = $msg;
                } else {
                    $email = $data;
                    $values[ $column->name ] = $data;
                }
            } else if ( $column->edit == 'datetime' || $column->edit == 'date' ) {
                $value = $app->validate_date( $column, null, $errors );
                if ( $value ) {
                    $values[ $column->name ] = $value;
                }
            } else if ( $column->edit == 'selection' && $column->disp_edit == 'checkbox' ) {
                $data = $app->param( $column->name );
                if ( is_array( $data ) ) {
                    if ( empty( $data ) && $column->not_null ) {
                        $errors[] = $app->translate( '%s is required.', $app->translate( $column->label ) );
                        continue;
                    }
                    $data = implode( ',', $data );
                    if ( $normarize && function_exists( 'normalizer_normalize' ) ) {
                        $data = normalizer_normalize( $data, Normalizer::NFKC );
                    }
                    $values[ $column->name ] = $data;
                    $_REQUEST[ $column->name ] = $data;
                }
            } else if ( $column->type == 'relation' && strpos( $column->edit, 'relation:' ) === 0 ) {
                $data = $app->param( $column->name );
                list( $edit, $rel_model, $rel_col, $rel_type ) = explode( ':', $column->edit );
                if ( $rel_type == 'checkbox' || $rel_type == 'hierarchy' || $rel_type == 'dialog' ) {
                    if (! $data || ( is_array( $data ) && empty( $data ) ) ) {
                        if ( $column->not_null ) {
                            $errors[] = $app->translate( '%s is required.', $app->translate( $column->label ) );
                        }
                        continue;
                    }
                }
                if ( is_string( $data ) && $normarize && function_exists( 'normalizer_normalize' ) ) {
                    $data = normalizer_normalize( $data, Normalizer::NFKC );
                    $_REQUEST[ $column->name ] = $data;
                }
                if ( is_string( $data ) && $column->not_null && ! $data ) {
                    $errors[] = $app->translate( '%s is required.', $app->translate( $column->label ) );
                    continue;
                } else if ( is_array( $data ) && $column->not_null && empty( $data ) ) {
                    $errors[] = $app->translate( '%s is required.', $app->translate( $column->label ) );
                    continue;
                }
                $values[ $column->name ] = $data;
            } else {
                $data = $app->param( $column->name );
                if ( is_string( $data ) && $normarize && function_exists( 'normalizer_normalize' ) ) {
                    $data = normalizer_normalize( $data, Normalizer::NFKC );
                    $_REQUEST[ $column->name ] = $data;
                }
                if ( is_string( $data ) && $column->not_null && ! $data ) {
                    $errors[] = $app->translate( '%s is required.', $app->translate( $column->label ) );
                    continue;
                } else if ( is_array( $data ) && $column->not_null && empty( $data ) ) {
                    $errors[] = $app->translate( '%s is required.', $app->translate( $column->label ) );
                    continue;
                }
                $values[ $column->name ] = $data;
            }
        }
        if (!isset( $values['language'] ) || !$values['language'] ) {
            $values['language'] = $app->language;
        }
        $ctx->vars['messages'] = $messages;
        if ( empty( $errors ) ) {
            if ( $sess ) $sess->remove();
            $ctx->vars['confirm_ok'] = 1;
            if ( $_type == 'confirm' ) { 
                $magic = $app->magic();
                $sess = $app->db->model( 'session' )->get_by_key( ['name' => $magic,
                                                                   'kind' => 'CR'] );
                $sess->start( time() );
                $expires =
                    $component->get_config_value( 'members_sign_up_timeout', $workspace_id, true );
                $sess->expires( time() + $expires );
                $sess->workspace_id( $workspace_id );
                $sess->save();
                $ctx->vars['magic_token'] = $magic;
                $ctx->local_vars['magic_token'] = $magic;
            }
        } else {
            $ctx->vars['errors'] = $errors;
        }
        if ( empty( $errors ) && $_type == 'sign_up' ) {
            if (! empty( $passwords ) ) {
                foreach ( $values as $key => $value ) {
                    if ( in_array( $key, $passwords ) ) {
                        $values[ $key ] = password_hash( $value, PASSWORD_BCRYPT );
                    }
                }
            }
            $column_values = ['values' => $values, 'file_names' => $file_names ];
            $magic = $app->magic();
            $sess = $app->db->model( 'session' )->get_by_key(
                ['name' => $magic, 'kind' => 'TM'] );
            $sess->data( json_encode( $column_values ) );
            $sess->workspace_id( $workspace_id );
            $app->set_mail_param( $ctx );
            $subject = null;
            $body = null;
            $template = null;
            $body = $app->get_mail_tmpl( 'members_sign_up_confirm', $template, $workspace );
            if ( $template ) {
                $subject = $template->subject;
            }
            if (! $subject ) {
                $subject = $app->translate( "Your account confirmation on '%s'.", $ctx->vars['app_name'] );
            }
            $expires =
                $component->get_config_value( 'members_sign_up_expires', $workspace_id, true );
            $sess->expires( time() + $expires );
            $sess->start( time() );
            $sess->save();
            $ctx->vars['token'] = $sess->name;
            $body = $app->build( $body );
            $subject = $app->build( $subject );
            $mail_from = $component->mail_from( $app, $workspace_id );
            $mail_from = $app->build( $mail_from );
            $headers = ['From' => $mail_from ];
            $error = '';
            if (! PTUtil::send_mail(
                $email, $subject, $body, $headers, $error ) ) {
                $ctx->vars['error'] = $error;
                return $app->error( $error );
            }
            $return_url = $ctx->vars['script_uri'] . '?__mode=sign_up&submit=1';
            $return_url .= $workspace_id ? '&workspace_id=' . $workspace_id : '';
            $app->redirect( $return_url );
        }
        return $this->__mode( 'sign_up' );
    }

    function __mode ( $mode, $t = null ) {
        $component = $this->component( 'members' );
        $workspace_id = $this->workspace_id;
        $status_published = $this->status_published( 'template' );
        $workspace_ids = $workspace_id ? [0, $workspace_id ] : [ $workspace_id ];
        $tmpl = $this->db->model( 'template' )->load(
                      ['basename' => 'member_' . $mode,
                       'workspace_id' => ['IN' => $workspace_ids ],
                       'rev_type' => 0,
                       'class' => 'Member',
                       'status' => $status_published ],
                       ['sort' => 'workspace_id', 'direction' => 'descend', 'limit' => 1] );
        $tmpl = ( is_array( $tmpl ) && !empty( $tmpl ) ) ? $tmpl[0] : null;
        if (! $tmpl ) {
            $tmpl_path = $component->path() . DS . 'tmpl' . DS . 'member_' . $mode . '.tmpl';
            if ( file_exists( $tmpl_path ) ) {
                $mtml = file_get_contents( $tmpl_path );
                $tmpl = $this->db->model( 'template' )->new( ['text' => $mtml ] );
            }
        }
        $ctx = $this->ctx;
        $ctx->vars['mamber_id'] = isset( $ctx->vars['user_id'] )
                                ? $ctx->vars['user_id'] : null;
        $ctx->vars['user_id'] = null;
        return parent::__mode( $mode, $tmpl );
    }

    function base64_encode_file ( $column, &$errors, &$messages ) {
        $app = $this;
        $ctx = $app->ctx;
        if ( isset( $_FILES[ $column->name ] )
            && is_uploaded_file( $_FILES[ $column->name ]['tmp_name'] ) ){
            $parts = explode( '.', $_FILES[ $column->name ]['name'] );
            $extIndex = count( $parts ) - 1;
            $extension = strtolower( @$parts[ $extIndex ] );
            $denied_exts = $app->denied_exts;
            $denied_exts = preg_split( '/\s*,\s*/', $denied_exts );
            $upload_dir = $app->upload_dir();
            $file_name = $upload_dir . DS . $_FILES[ $column->name ]['name'];
            move_uploaded_file( $_FILES[ $column->name ]['tmp_name'], $file_name );
            if ( $column->options == 'image'
                && ! in_array( $extension, $app->images ) ) {
                $errors[] = $app->translate( 'Invalid image file format.' );
                return false;
            } else if ( in_array( $extension, $denied_exts ) ) {
                $errors[] = $app->translate( "Invalid File extension'%s'.", $extension );
                return false;
            } else {
                $error = '';
                $res = PTUtil::upload_check( $column->extra,
                    $file_name, false, $error );
                if ( $error ) {
                    $errors[] = $error;
                    return false;
                }
                if ( $res == 'resized' )
                    $messages[] =
                        $app->translate(
                          "It has been reduced as it exceeds the maximum size of the image file '%s'.",
                          basename( $file_name ) );
                $data = file_get_contents( $file_name );
                $mime_type = $_FILES[ $column->name ]['type'];
                $data = base64_encode( $data );
                $data = "data:{$mime_type};base64,{$data}";
                return $data;
            }
        } else {
            if ( $column->not_null ) {
                $errors[] = $app->translate( '%s is required.', $app->translate( $column->label ) );
            }
        }
        return false;
    }

    function validate_date ( $column, $member = null, &$errors ) {
        $app = $this;
        $data = $app->param( $column->name );
        if ( $column->not_null && ! $data ) {
            $errors[] = $app->translate( '%s is required.', $app->translate( $column->label ) );
            return false;
        }
        $component = $app->component( 'Members' );
        $workspace_id = $app->workspace_id;
        $normarize =
            $component->get_config_value( 'members_sign_up_normarize', $workspace_id, true );
        if ( $data ) {
            if ( $normarize && function_exists( 'normalizer_normalize' ) ) {
                $data = normalizer_normalize( $data, Normalizer::NFKC );
                $_REQUEST[ $column->name ] = $data;
            }
            if ( preg_match( '!^([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})$!', $data, $matches ) ) {
                list( $y, $m, $d ) = [ $matches[1], $matches[2], $matches[3] ];
                $data = sprintf('%04d-%02d-%02d', $y, $m, $d );
            }
            if ( strpos( $data, '-' ) === false ) {
                list( $Y, $m, $d ) =
                  [ substr( $data, 0, 4 ), substr( $data, 4, 2 ), substr( $data, 6, 2 ) ];
                $data = "{$Y}-{$m}-{$d}";
            }
            list( $Y, $m, $d ) = explode( '-', $data );
            if ( checkdate( (int) $m, (int) $d, (int) $Y ) !== true ) {
                $errors[] = $app->translate( '%s is invalid date.',
                            $app->translate( $column->label ) );
                return false;
            }
            $_REQUEST[ $column->name ] = $data;
            $time = $column->edit == 'date' ? '0000'
                  : $app->param( $column->name . '_time' );
            if ( $normarize && function_exists( 'normalizer_normalize' ) ) {
                $time = normalizer_normalize( $time, Normalizer::NFKC );
                $_REQUEST[ $column->name . '_time' ] = $time;
            }
            $time = preg_replace( '/[^0-9]/', '', $time );
            if ( strlen( $time ) == 4 ) {
                $time .= '00';
            }
            if ( $time >= 0 && $time < 240000 ) {
                $t = substr( $time, 0, 2 ) . ':' . substr( $time, 4, 2 );
                $_REQUEST[ $column->name . '_time' ] = $t;
            } else {
                $errors[] = $app->translate( '%s is invalid date.', $app->translate( $column->label ) );
                return false;
            }
            $member = $member ? $member : $app->db->model('member')->new();
            $ts = $member->db2ts( $data . $time );
            $value = $member->ts2db( $ts );
            return $value;
        } else if ( $column->not_null ) {
            $errors[] = $app->translate( '%s is required.', $app->translate( $column->label ) );
            return false;
        }
    }

    function validate_unique ( $column, $member = null, &$errors ) {
        $app = $this;
        $ctx = $app->ctx;
        $data = $app->param( $column->name );
        if (! $data && $column->not_null ) {
            $errors[] = $app->translate( '%s is required.', $app->translate( $column->label ) );
            return false;
        }
        $component = $app->component( 'Members' );
        $workspace_id = $app->workspace_id;
        $normarize =
            $component->get_config_value( 'members_sign_up_normarize', $workspace_id, true );
        if ( $normarize && function_exists( 'normalizer_normalize' ) ) {
            $data = normalizer_normalize( $data, Normalizer::NFKC );
            $_REQUEST[ $column->name ] = $data;
        }
        $terms = [ $column->name => $data ];
        if ( $member ) $terms['id'] = ['!=' => $member->id ];
        $existings = $app->db->model( 'member' )->count( $terms );
        if ( $existings ) {
            $errors[] = $app->translate(
                "A %1\$s with the same %2\$s '%3\$s' already exists.",
                [ $app->translate( 'Member' ),
                  $app->translate( $column->label ),
                  $app->translate( $data ) ] );
            return false;
        }
        // Check session.
        $sessions = $app->db->model( 'session' )->load(
            ['kind' => 'TM', 'expires' => ['>' => time()] ] );
        foreach ( $sessions as $sess ) {
            $values = json_decode( $sess->data, true );
            $values = $values['values'];
            if ( isset( $values[ $column->name ] ) ) {
                if ( $values[ $column->name ] == $data ) {
                    $errors[] = $app->translate(
                        "A %1\$s with the same %2\$s '%3\$s' already exists.",
                        [ $app->translate( 'Member' ),
                          $app->translate( $column->label ),
                          $app->translate( $data ) ] );
                    return false;
                }
            }
        }
        return true;
    }

    function get_return_url () {
        $app = $this;
        $ctx = $app->ctx;
        $workspace_id = $app->workspace_id;
        $workspace = $ctx->stash( 'workspace' );
        $return_url = $this->param( 'return_url' ) ? $this->param( 'return_url' ) : null;
        if (! $return_url ) {
            $return_url = $workspace ? $workspace->site_url : $app->site_url;
        }
        $return_url = $return_url
                    ? preg_replace( '/^https{0,1}:\/\/.*?\//', '/', $return_url ) : null;
        if (! $return_url ) $return_url = '/';
        return $return_url;
    }
}
