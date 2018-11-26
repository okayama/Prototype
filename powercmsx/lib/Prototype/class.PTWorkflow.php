<?php
class PTWorkflow {

    function workflow_post_save ( &$cb, $app, &$obj, $original, $clone_org ) {
        if ( $clone_org ) $original = $clone_org;
        $model = $obj->_model;
        $status_published = $app->status_published( $model );
        $status_reserved = $status_published - 1;
        $workspace_id = $obj->workspace_id ? $obj->workspace_id : 0;
        $workspace = $obj->workspace_id ? $obj->workspace : null;
        $workspace_name = $workspace ? $workspace->name : $app->name;
        $obj_user = $obj->user;
        $old_user = $obj_user;
        $new_user = null;
        $changed = false;
        $change_case = 1;
        $old_user_can_edit = true;
        $type = null;
        $tags = new PTTags();
        $send_user_ids = [];
        if ( $app->mode == 'save' ) {
            $type = $app->param( '__workflow_type' );
            $user_id = $obj->user_id;
            $new_id = $user_id;
            if ( $type ) {
                if ( $type == 1 ) {
                    $new_id = $app->param( '__workflow_remand' );
                } else if ( $type == 2 ) {
                    $new_id = $app->param( '__workflow_approval' );
                }
                $new_id = $new_id ? (int) $new_id : $user_id;
                if ( $new_id ) {
                    $new_user = $app->db->model( 'user' )->load( $new_id );
                    if (! $new_user ) return;
                    $group_name = $app->permission_group( $new_user, $model, $workspace_id );
                    if (! $group_name ) return;
                    if ( $group_name == 'creator' ) {
                        $obj->status( 0 );
                    } else if ( $group_name == 'reviewer' ) {
                        if ( $obj->status > 1 ) {
                            $obj->status( 1 );
                        }
                    } else if ( $group_name == 'publisher' ) {
                        if ( $obj->status < 2 ) {
                            $obj->status( 2 );
                        }
                    }
                    $obj->user_id( $new_id );
                    if ( $app->user() ) {
                        $obj->previous_owner( $app->user()->id );
                    }
                    $obj_user = $new_user;
                    $changed = true;
                    $change_case = 2;
                }
            }
        }
        if ( $change_case != 2 ) {
            $max_status = $app->max_status( $app->user(), $model , $workspace );
            $group_name = $app->permission_group( $obj_user, $model , $workspace_id );
            if (! $group_name ) {
                $new_user = $app->user();
                $obj->user_id( $new_user->id );
                $changed = true;
                $old_user_can_edit = false;
            } else if ( $group_name == 'creator' ) {
                if ( $obj->status > 0 && $max_status ) {
                    $new_user = $app->user();
                    $obj->user_id( $new_user->id );
                    $changed = true;
                    $old_user_can_edit = false;
                }
            } else if ( $group_name == 'reviewer' ) {
                if ( $obj->status > 1 && $max_status > 1 ) {
                    $new_user = $app->user();
                    $obj->user_id( $new_user->id );
                    $changed = true;
                    $old_user_can_edit = false;
                }
            }
        }
        if ( $changed ) {
            $obj->save();
            if (! $app->can_do( $model, 'edit', $obj ) ) {
                if (! $app->can_do( $model, 'list', $obj ) ) {
                    $cb['return_url'] = $app->admin_url .
                    '?__mode=dashboard' . $app->workspace_param .
                    '&workflow_change_user=1';
                } else {
                    $cb['return_url'] = $app->admin_url .
                    '?__mode=view&_type=list&_model=' . $model .
                    $app->workspace_param . '&workflow_change_user=1';
                }
            }
            $cb['change_case'] = $change_case;
            $cb['old_user'] = $old_user;
            $cb['new_user'] = $new_user;
            if ( $app->mode == 'save' ) {
                $ctx = $app->ctx;
                if ( $obj->has_column( 'rev_type' ) ) {
                    $revision = $app->db->model( $obj->_model )->get_by_key(
                        ['rev_object_id' => $obj->id, 'rev_type' => 1],
                        ['sort' => 'id', 'direction' => 'descend']
                    );
                    if ( $revision->id ) {
                        $difference = $this->get_diff( $app, $revision, $obj );
                        $ctx->vars['difference'] = $difference;
                    }
                }
                $ctx->vars['workspace_name'] = $workspace_name;
                $workflow = $app->stash( 'workflow' );
                $table = $app->stash( 'table' )
                       ? $app->stash( 'table' ) : $app->get_table( $model );
                $primary = $table->primary;
                $object_label = $app->translate( $table->label );
                $ctx->vars['object_label'] = $object_label;
                $object_name = $obj->$primary;
                $ctx->vars['object_name'] = $object_name;
                $id = $obj->id;
                $edit_link = $app->admin_url . "?__mode=view&_type=edit&_model={$model}&id={$id}";
                if ( $obj->has_column( 'workspace_id' ) && $obj->workspace_id ) {
                    $edit_link .= '&workspace_id=' . $obj->workspace_id;
                }
                $ctx->vars['edit_link'] = $edit_link;
                $by_user_id = $app->user()->id;
                $by_user = $app->user()->nickname;
                $ctx->vars['by_user'] = $by_user;
                $ctx->vars['by_user_id'] = $by_user_id;
                $ctx->vars['object_name'] = $obj->$primary;
                $ctx->vars['object_id'] = $obj->id;
                $ctx->vars['object_permalink'] = $app->get_permalink( $obj );
                $app->set_mail_param( $ctx );
                $tag_args = ['status' => $obj->status, 'model' => $model ];
                $status_text = $tags->hdlr_statustext( $tag_args, $ctx );
                $ctx->vars['status_text'] = $status_text;
                $previous_owner = $obj->previous_owner;
                $app_name = $app->workspace()
                          ? $app->workspace()->name : $app->appname;
                $portal_url = $app->workspace()
                          ? $app->workspace()->site_url : $app->site_url;
                $ctx->vars['app_name'] = $app_name;
                $ctx->vars['portal_url'] = $portal_url;
                if ( $workflow->notify_changes && $previous_owner && $old_user->id != $previous_owner &&
                   ( $obj->status == $status_published || $obj->status == $status_reserved )
                   && ( $previous_owner && $previous_owner != $app->user->id )
                   && ( $original->status < $status_reserved ) ) {
                    $previous_user = $app->model( 'user' )->load( (int) $previous_owner );
                    if ( $previous_user ) {
                        $cb['previous_user'] = $previous_user;
                        $prev_group = $app->permission_group( $previous_user,
                                                              $model , $workspace_id );
                        $previous_user_can_edit = false;
                        if ( $prev_group && $prev_group == 'publisher' ) {
                            $previous_user_can_edit = true;
                        }
                        $ctx->vars['previous_user_can_edit'] = $previous_user_can_edit;
                        $template = null;
                        $subject = null;
                        $tmpl = $app->get_mail_tmpl( 'notify_previous_owner', $template );
                        // Notyfy to previous user
                        if ( $template ) {
                            $subject = $template->subject;
                        }
                        if (! $subject ) {
                            $subject
                            = $app->translate ( '(%s)', $workspace_name ) .
                            $app->translate(
                        'The %1$s\'%2$s\' you requested approval has been approved by %3$s(Status changed to %4$s).',
                            [ $object_label, $object_name, $by_user, $status_text ] );
                        }
                        $subject = $app->build( $subject );
                        $body = $app->build( $tmpl );
                        $headers = ['From' => $app->user()->email ];
                        PTUtil::send_mail(
                            $previous_user->email, $subject, $body, $headers );
                        $send_user_ids[] = (int) $previous_user->id;
                    }
                }
                if ( $workflow->notify_changes && $old_user->id != $new_user->id ) {
                    $to_user = $new_user->nickname;
                    $from_user = $old_user->nickname;
                    $ctx->vars['to_user'] = $to_user;
                    $ctx->vars['from_user'] = $from_user;
                    $old_user_id = $old_user->id;
                    $new_user_id = $new_user->id;
                    $ctx->vars['old_user_can_edit'] = $old_user_can_edit;
                    $ctx->vars['old_user_id'] = $old_user_id;
                    $ctx->vars['new_user_id'] = $new_user_id;
                    $ctx->vars['notify_type'] = $type;
                    $ctx->vars['workflow_message'] = $app->param( '__workflow_message' );
                    $workflow_label = $type == 1 ? 'Remand' : 'Approval Request';
                    $workflow_label = $app->translate( $workflow_label );
                    $ctx->vars['workflow_label'] = $workflow_label;
                    if ( $old_user->id != $app->user()->id ) {
                        $ctx->vars['notify_type'] = 'old_user';
                        $subject = null;
                        $template = null;
                        $tmpl = $app->get_mail_tmpl( 'notify_old_user', $template );
                        // Notyfy to old user
                        if ( $template ) {
                            $subject = $template->subject;
                        }
                        if (! $subject ) {
                            $subject
                            = $app->translate ( '(%s)', $workspace_name ) .
                            $app->translate(
                            'Your responsible %1$s\'%2$s\' has been sent %3$s to another user %4$s by user %5$s.',
                            [ $object_label, $object_name, $workflow_label, $to_user, $by_user ] );
                        }
                        $subject = $app->build( $subject );
                        $body = $app->build( $tmpl );
                        $headers = ['From' => $app->user()->email ];
                        PTUtil::send_mail(
                            $old_user->email, $subject, $body, $headers );
                        $send_user_ids[] = (int) $old_user->id;
                    }
                    if ( $new_user->id != $app->user()->id ) {
                        // Notyfy to new user
                        $ctx->vars['notify_type'] = 'new_user';
                        $ctx->vars['edit_link'] = $edit_link;
                        $subject = null;
                        $template = null;
                        $tmpl = $app->get_mail_tmpl( 'notify_new_user', $template );
                        if ( $template ) {
                            $subject = $template->subject;
                        }
                        if (! $subject ) {
                            if ( $old_user_id != $by_user_id ) {
                                $subject = $app->translate ( '(%s)', $workspace_name ) .
                                $app->translate(
                                '%1$s for %2$s\'%3$s\' has been sent for you from another user %4$s by user %5$s.',
                                [ $workflow_label, $object_label, $object_name, $from_user, $by_user ] );
                            } else {
                                $subject = $app->translate ( '(%s)', $workspace_name ) .
                                $app->translate(
                                '%1$s for %2$s\'%3$s\' has been sent for you from another user %4$s',
                                [ $workflow_label, $object_label, $object_name, $from_user ] );
                            }
                        }
                        $subject = $app->build( $subject );
                        $body = $app->build( $tmpl );
                        $headers = ['From' => $app->user()->email ];
                        PTUtil::send_mail(
                            $new_user->email, $subject, $body, $headers );
                        $send_user_ids[] = (int) $new_user->id;
                    }
                }
            }
        }
        if ( $obj->status == $status_published
            && $original->status != $status_published ) {
            $this->publish_object( $app, $obj, $send_user_ids );
        }
    }

    function publish_object ( $app, $obj, $not_user_ids = null, $clone = null ) {
        $params = ['group' => ['created_by'] ];
        $terms  = ['model' => $obj->_model, 'object_id' => $obj->id ];
        if ( $clone ) {
            $terms['object_id'] = $clone->id;
        }
        $extra = '';
        if ( ( ! $not_user_ids || empty( $not_user_ids ) ) && $app->user ) {
            $not_user_ids = [ $app->user->id ];
        }
        if ( $not_user_ids ) {
            if (! is_array( $not_user_ids ) ) {
                $not_user_ids = [ $not_user_ids ];
            }
            if ( $app->user ) {
                $not_user_ids[] = $app->user->id;
            }
            $not_user_ids = array_unique( array_map( 'intval', $not_user_ids ) );
            $extra = ' AND log_created_by NOT IN (' . implode( ',', $not_user_ids ) . ')';
        }
        $user_logs = $app->db->model( 'log' )->count_group_by( $terms, $params, $extra );
        $user_ids = [];
        foreach ( $user_logs as $user_log ) {
            $user_ids[] = (int) $user_log['log_created_by'];
        }
        if (! count( $user_ids ) ) {
            return;
        }
        $by_user_id;
        if ( $app->user ) {
            $by_user_id = (int) $app->user->id;
        } else if ( $obj->has_column( 'modified_by' ) ) {
            $by_user_id = (int) $obj->modified_by;
        } else if ( $obj->has_column( 'user_id' ) ) {
            $by_user_id = (int) $obj->user_id;
        }
        if (! $by_user_id ) return;
        $by_user = $app->db->model( 'user' )->load( $by_user_id );
        $by_user_name = $by_user ? $by_user->nickname : $app->translate( 'Unknown %s', $app->translate( 'User' ) );
        $from = '';
        if ( $by_user ) {
            $from = $by_user->email;
        } else {
            $system_email = $app->get_config( 'system_email' );
            if (!$system_email ) {
                $app->log( [
                    'message'  => $app->translate( 'System Email Address is not set in System.' ),
                    'category' => 'workflow',
                    'model'    => $obj->_model,
                    'level'    => 'error'] );
                return;
            }
            $from = $system_email->value;
        }
        $addresses = [];
        $users = $app->db->model( 'user' )->load( ['id' => ['IN' => $user_ids ] ] );
        foreach ( $users as $user ) {
            if (! $app->user || ( $app->user->email != $user->email ) ) {
                $addresses[] = $user->email;
            }
        }
        $template = null;
        $subject = null;
        $tmpl = $app->get_mail_tmpl( 'notify_participants', $template );
        // Notyfy to previous users
        if ( $template ) {
            $subject = $template->subject;
        }
        $table = $app->get_table( $obj->_model );
        if (! $table ) return;
        $primary = $table->primary;
        $ctx = $app->ctx;
        $ctx->vars['by_user'] = $by_user_name;
        $ctx->vars['object_name'] = $obj->$primary;
        $ctx->vars['object_label'] = $app->translate( $table->label );
        $ctx->vars['object_id'] = $obj->id;
        $ctx->vars['object_permalink'] = $app->get_permalink( $obj );
        $app->set_mail_param( $ctx );
        $app_name = $obj->workspace
                  ? $obj->workspace->name : $app->appname;
        if (! $subject ) {
            $subject = $app->translate ( '(%s)', $app_name )
                     . $app->translate( 'The %1$s\'%2$s\' you creadted (or edited) has been approved and published by %3$s.',
                     [ $app->translate( $table->label ), $obj->$primary, $by_user_name ] );
        }
        $portal_url = $obj->workspace
                  ? $obj->workspace->site_url : $app->site_url;
        $ctx->vars['app_name'] = $app_name;
        $ctx->vars['portal_url'] = $portal_url;
        $subject = $app->build( $subject );
        $body = $app->build( $tmpl );
        $headers = ['From' => $from ];
        PTUtil::send_mail(
            $addresses, $subject, $body, $headers );
    }

    function get_diff ( $app, $obj1, $obj2, $excludes = [] ) {
        $app->get_scheme_from_db( $obj1->_model );
        $table = $app->get_table( $obj1->_model );
        $excludes = array_merge( $excludes, ['user_id', 'status', 'previous_owner'] );
        $excludes = array_unique( $excludes );
        $changed_cols = PTUtil::object_diff( $app, $obj1, $obj2, $excludes );
        $scheme = $app->get_scheme_from_db( $obj1->_model );
        $default_locale =  $scheme['locale']['default'];
        $column_changed = [];
        foreach ( $changed_cols as $name => $diff ) {
            $col = $app->db->model( 'column' )->get_by_key(
                ['name' => $name, 'table_id' => $table->id ] );
            $label = isset( $default_locale[ $name ] )
                   ? $default_locale[ $name ] : $name;
            if ( $col->id ) {
                $label = $col->label;
            }
            $label = $app->translate( $label );
            $column_changed[ $label ] = $diff;
        }
        return $column_changed;
    }
}