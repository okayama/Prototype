<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );

class BatchApproval extends PTPlugin {

    public $tags = null;

    function __construct () {
        parent::__construct();
    }

    function post_init ( $app ) {
        if ( ( $app->mode == 'view' && $app->param( '_type' ) == 'list' )
            || $app->mode == 'list_action' ) {
            $model = $app->param( '_model' );
            $workflow = null;
            $workspace_id = $app->workspace() ? $app->workspace()->id : 0;
            $workflow = $app->db->model( 'workflow' )->get_by_key( [
                'model' => $model,
                'workspace_id' => $workspace_id ] );
            if ( $workflow->id ) {
                $registry = $app->registry;
                $list_actions = isset( $registry['list_actions'] )
                              ? $registry['list_actions'] : [];
                $input_options = [];
                $input_options[] = ['label' => 'Approval Request or Remand', 'value' => 1 ];
                $input_options[] = ['label' => 'Batch Approval', 'value' => 2 ];
                $param = ['name'  => 'batch_approval_objects',
                          'label' => 'Workflow',
                          'component' => 'BatchApproval',
                          'method' => 'batch_approval_objects',
                          'modal' => 1,
                          'input_options' => $input_options,
                          'input' => 1,
                          'columns' => '*'];
                $action = ['batch_approval_objects' => $param ];
                $list_actions['batch_approval_objects'] = [ $model => $action ];
                $app->registry['list_actions'] = $list_actions;
            }
        }
        return true;
    }

    function batch_approval_objects ( $app, $objects, $action ) {
        $ctx = $app->ctx;
        $ws_object_map = [];
        $workflow_map = [];
        $object_ids = [];
        $workspace_ids = [];
        $model = $app->param( '_model' );
        $table = $app->get_table( $model );
        $primary = $table->primary;
        $approval_type = $app->param( 'itemset_action_input' );
        foreach ( $objects as $obj ) {
            $workspace_id = $obj->has_column( 'workspace_id' ) && $obj->workspace_id
                          ? (int) $obj->workspace_id : 0;
            $workflow = isset( $workflow_map[ $workspace_id ] )
                      ? $workflow_map[ $workspace_id ]
                      : $app->db->model( 'workflow' )->get_by_key(
                                        ['model' => $model,
                                         'workspace_id' => $workspace_id ] );
            $workflow_map[ $workspace_id ] = $workflow;
            if ( $workflow->id ) {
                if ( $approval_type == 2 && $obj->user_id != $app->user()->id ) {
                    continue;
                }
                if (! isset( $ws_object_map[ $workspace_id ] ) ) {
                    $ws_object_map[ $workspace_id ] = [];
                }
                $ws_object_map[ $workspace_id ][] = $obj;
                $object_ids[] = $obj->id;
                $workspace_ids[ $workspace_id ] = true;
            }
        }
        $workspace_ids = array_keys( $workspace_ids );
        asort( $workspace_ids );
        asort( $object_ids );
        $label = $app->translate( $table->label );
        $plural = $app->translate( $table->plural );
        $ctx->vars['label'] = $label;
        $ctx->vars['plural'] = $plural;
        $ctx->vars['workspace_ids'] = $workspace_ids;
        $ctx->vars['object_ids'] = $object_ids;
        $ctx->vars['object_user_id'] = $app->user()->id;
        $ctx->vars['model'] = $model;
        $ctx->vars['workspace_id'] = $app->workspace() ? $app->workspace()->id : 0;
        $status_published = $app->status_published( $model );
        $app->core_save_callbacks();
        $tags = new PTTags();
        $list_actions = new PTListActions();
        $this->tags = $tags;
        $changes_workspace_ids = [];
        $ws_detach_user_map = [];
        $ws_changing_user_map = [];
        $ws_assign_user_map = [];
        $error = false;
        if ( $app->param( 'do_action' ) && $app->request_method == 'POST'
            && $approval_type == 1 ) {
            $app->db->begin_work();
            $counter = 0;
            $rebuild_ids = [];
            $publish_objs = [];
            $publish_originals = [];
            $old_objects = [];
            foreach ( $workspace_ids as $workspace_id ) {
                $workflow_type = $app->param( '__workflow_type_' . $workspace_id );
                if (! $workflow_type ) continue;
                $user_id = $workflow_type == 1
                         ? $app->param( '__workflow_remand_' . $workspace_id )
                         : $app->param( '__workflow_approval_' . $workspace_id );
                $workflow_label = $workflow_type == 1 ? 'Remand' : 'Approval Request';
                $workflow_label = $app->translate( $workflow_label );
                $ctx->vars['workflow_label'] = $workflow_label;
                $workflow_message = $app->param( '__workflow_message_' . $workspace_id );
                $ctx->vars['workflow_message'] = $workflow_message;
                if (! $user_id ) continue;
                $new_user = $app->db->model( 'user' )->load( (int) $user_id );
                $group_name = $app->permission_group( $new_user, $model, $workspace_id );
                $workflow = $workflow_map[ $workspace_id ];
                $workspace = $workflow->workspace;
                $workspace_name = $workspace ? $workspace->name : $app->name;
                $ctx->vars['workspace_name'] = $workspace_name;
                $action_objects = $ws_object_map[ $workspace_id ];
                foreach ( $action_objects as $obj ) {
                    $old_user = $obj->user;
                    $new_status = $obj->status;
                    $orig_status = $obj->status;
                    if ( $group_name == 'publisher' ) {
                        $new_status = 2;
                    } else if ( $group_name == 'creator' ) {
                        $new_status = 0;
                    } else if ( $group_name == 'reviewer' ) {
                        if ( $obj->status > 1 ) {
                            $new_status = 1;
                        }
                    }
                    if ( $obj->status == $new_status && $new_user->id == $obj->user_id ) {
                        continue;
                    }
                    if ( $obj->status != $new_status && 
                        ( $obj->status == $status_published ||
                        $new_status == $status_published ) ) {
                        if ( $app->get_permalink( $obj, true ) ) {
                            if ( $obj->has_column( 'workspace_id' )
                                && $obj->workspace_id != $app->param( 'workspace_id' ) ) {
                                $publish_objs[] = $obj;
                                $publish_originals[] = $original;
                            } else {
                                $rebuild_ids[] = $obj->id;
                            }
                        }
                    }
                    $original = clone $obj;
                    $obj->user_id( $new_user->id );
                    $obj->status( $new_status );
                    if (! $obj->save() ) $error = true;
                    $changes_workspace_ids[ $workspace_id ] = true;
                    $counter++;
                    $original->user_id( $old_user->id );
                    $original->status( $orig_status );
                    $old_objects[ $obj->id ] = $original;
                    $callback = ['name' => 'post_save', 'error' => '', 'is_new' => false ];
                    $app->run_callbacks( $callback, $model, $obj, $original );
                    $error = $error ? $error : $callback['error'];
                    if (! isset( $ws_changing_user_map[ $workspace_id ][ $old_user->id ] ) ) {
                        $ws_changing_user_map[ $workspace_id ][ $old_user->id ] = [];
                    }
                    $user_map[ $old_user->id ] = $old_user;
                    $user_map[ $new_user->id ] = $new_user;
                    $ws_changing_user_map[ $workspace_id ][ $old_user->id ][] = $obj;
                    if (! isset( $ws_assign_user_map[ $workspace_id ][ $new_user->id ] ) ) {
                        $ws_assign_user_map[ $workspace_id ][ $new_user->id ] = [];
                    }
                    $ws_assign_user_map[ $workspace_id ][ $new_user->id ][] = $obj;
                }
                if ( $error || !empty( $app->db->errors ) ) {
                    $errstr = $app->translate( 'An error occurred while saving %s.',
                              $app->translate( $table->label ) );
                    $app->rollback( $errstr );
                } else {
                    $app->db->commit();
                }
                if ( $workflow->notify_changes ) {
                    $object_label_plural = $app->translate( $table->plural );
                    $object_label = $app->translate( $table->label );
                    $ctx->vars['by_user'] = $app->user->nickname;
                    if (! empty( $changes_workspace_ids ) ) {
                        $workspace_ids = array_keys( $changes_workspace_ids );
                        foreach ( $workspace_ids as $workspace_id ) {
                            $list_url = $app->admin_url . '?__mode=view&_type=list&_model=' . $model;
                            if ( $workspace_id ) {
                                $list_url .= '&workspace_id=' . $workspace_id;
                            }
                            $workspace = $workspace_id
                                       ? $app->db->model( 'workspace' )->load( (int) $workspace_id )
                                       : null;
                            $template = null;
                            $tmpl = $app->get_mail_tmpl( 'approval_request_or_remand', $template, $workspace );
                            $ctx->stash( 'workspace', $workspace );
                            $ws_assigned = $ws_assign_user_map[ $workspace_id ];
                            $mail_footer = $this->get_config_value( 'batchapproval_mail_footer', (int) $workspace_id );
                            foreach ( $ws_assigned as $user_id => $user_objs ) {
                                $params = [];
                                $i = 0;
                                $changed_obj_ids = [];
                                foreach ( $user_objs as $changed_obj ) {
                                    $old_obj = $old_objects[ $changed_obj->id ];
                                    $old_user = $user_map[ $old_obj->user_id ];
                                    $new_user = $user_map[ $changed_obj->user_id ];
                                    $_params = $this->set_params( $app, $changed_obj, $new_user, $primary );
                                    $this->set_defferent_context( $ctx, $old_obj, $changed_obj,
                                                                  $old_user, $new_user );
                                    $changed_obj_ids[] = $changed_obj->id;
                                    $params[] = $_params;
                                    $i++;
                                }
                                $ctx->vars['object_label_plural'] = ( $i == 1 )
                                    ? $object_label_plural : $object_label;
                                $ctx->vars['assigned_object_loop'] = $params;
                                $ctx->vars['list_url'] = $list_url;
                                $ctx->vars['assigned_count_objects'] = $i;
                                $new_user = $user_map[ $user_id ];
                                if ( isset( $ws_changing_user_map[ $workspace_id ][ $user_id ] ) ) {
                                    $changing_user_map = $ws_changing_user_map[ $workspace_id ][ $user_id ];
                                    unset( $ws_changing_user_map[ $workspace_id ][ $user_id ] );
                                    $params = [];
                                    $i = 0;
                                    foreach ( $changing_user_map as $changed_obj ) {
                                        if ( isset( $changed_obj_ids[ $changed_obj->id ] ) )
                                            continue;
                                        $user = $user_map[ $user_id ];
                                        $_params = $this->set_params( $app, $changed_obj, $user, $primary );
                                        $old_obj = $old_objects[ $changed_obj->id ];
                                        $old_user = $user_map[ $old_obj->user_id ];
                                        $ctx->vars['to_user'] = $new_user->nickname;
                                        $this->set_defferent_context( $ctx, $old_obj, $changed_obj,
                                                                  $old_user, $new_user );
                                        $params[] = $_params;
                                        $i++;
                                    }
                                    $ctx->vars['changed_object_loop'] = $params;
                                    $ctx->vars['changed_count_objects'] = $i;
                                }
                                $subject = '';
                                if ( $template && $template->subject ) {
                                    $subject = $app->build( $template->subject );
                                } else {
                                    $subject = $app->translate ( '(%s)', $workspace_name ) .
                                        $this->translate( '%1$s %2$s has been sent %3$s for you by user %4$s.',
                                      [ $ctx->vars['assigned_count_objects'],
                                        $ctx->vars['object_label_plural'],
                                        $workflow_label,
                                        $ctx->vars['by_user'] ] );
                                }
                                $ctx->vars['mail_footer'] = $app->build( $mail_footer );
                                $ctx->vars['mail_type'] = 1;
                                $body = $app->build( $tmpl );
                                $headers = ['From' => $app->user()->email ];
                                PTUtil::send_mail(
                                    $new_user->email, $subject, $body, $headers );
                            }
                            if ( isset( $ws_changing_user_map[ $workspace_id ] ) ) {
                                $ws_changes = $ws_changing_user_map[ $workspace_id ];
                                foreach ( $ws_changes as $user_id => $user_objs ) {
                                    $params = [];
                                    $i = 0;
                                    $changed_obj_ids = [];
                                    $old_user = $user_map[ $user_id ];
                                    foreach ( $user_objs as $changed_obj ) {
                                        $old_obj = $old_objects[ $changed_obj->id ];
                                        $new_user = $user_map[ $changed_obj->user_id ];
                                        $ctx->vars['to_user'] = $new_user->nickname;
                                        $_params = $this->set_params( $app, $changed_obj, $new_user, $primary );
                                        $this->set_defferent_context( $ctx, $old_obj, $changed_obj,
                                                                      $old_user, $new_user );
                                        $changed_obj_ids[] = $changed_obj->id;
                                        $params[] = $_params;
                                        $i++;
                                    }
                                    $ctx->vars['assigned_object_loop'] = [];
                                    $ctx->vars['assigned_count_objects'] = 0;
                                    $ctx->vars['object_label_plural'] = ( $i == 1 )
                                        ? $object_label_plural : $object_label;
                                    $ctx->vars['changed_object_loop'] = $params;
                                    $ctx->vars['list_url'] = $list_url;
                                    $ctx->vars['changed_count_objects'] = $i;
                                    $ctx->vars['mail_type'] = 2;
                                    $subject = '';
                                    if ( $template && $template->subject ) {
                                        $subject = $app->build( $template->subject );
                                    } else {
                                        $subject = $app->translate ( '(%s)', $workspace_name ) .
                                        $this->translate( 'Your responsible %1$s %2$s has been sent %3$s to another user %4$s by %5$s.',
                                          [ $ctx->vars['changed_count_objects'],
                                            $ctx->vars['object_label_plural'],
                                            $workflow_label,
                                            $ctx->vars['to_user'],
                                            $ctx->vars['by_user'] ] );
                                    }
                                    $ctx->vars['mail_footer'] = $app->build( $mail_footer );
                                    $body = $app->build( $tmpl );
                                    $headers = ['From' => $app->user()->email ];
                                    PTUtil::send_mail(
                                        $old_user->email, $subject, $body, $headers );
                                }
                            }
                        }
                    }
                }
            }
            $action_name = $this->translate( 'Approval Request or Remand of %s', $plural );
            $list_actions->log( $action_name, $model, $counter );
            return $this->finish_action
                    ( $app, $rebuild_ids, $counter, $publish_objs, $publish_originals );
        } else if ( $app->param( 'do_action' ) && $app->request_method == 'POST'
            && $approval_type == 2 ) {
            $app->db->begin_work();
            $counter = 0;
            $rebuild_ids = [];
            $publish_objs = [];
            $publish_originals = [];
            $old_objects = [];
            foreach ( $workspace_ids as $workspace_id ) {
                $workflow = $workflow_map[ $workspace_id ];
                $workspace = $workflow->workspace;
                $workspace_name = $workspace ? $workspace->name : $app->name;
                $ctx->vars['workspace_name'] = $workspace_name;
                $workflow_status = $app->param( '__workflow_status_' . $workspace_id );
                $workflow_message = $app->param( '__workflow_message_' . $workspace_id );
                $ctx->vars['workflow_message'] = $workflow_message;
                if ( $workflow_status != 3 && $workflow_status != 4 ) {
                    continue;
                }
                $action_objects = $ws_object_map[ $workspace_id ];
                $mail_footer = $this->get_config_value( 'batchapproval_mail_footer', (int) $workspace_id );
                foreach ( $action_objects as $obj ) {
                    if ( $obj->status == $workflow_status ) {
                        continue;
                    }
                    if ( $app->get_permalink( $obj, true ) ) {
                        if ( $obj->has_column( 'workspace_id' )
                            && $obj->workspace_id != $app->param( 'workspace_id' ) ) {
                            $publish_objs[] = $obj;
                            $publish_originals[] = $original;
                        } else {
                            $rebuild_ids[] = $obj->id;
                        }
                    }
                    if ( $previous_owner = $obj->previous_owner ) {
                        $previous_user = $app->db->model( 'user' )->load( (int) $previous_owner );
                        if ( $previous_user ) {
                            if (! isset( $ws_changing_user_map[ $workspace_id ][ $previous_owner ] ) ) {
                                $ws_changing_user_map[ $workspace_id ][ $previous_owner ] = [];
                            }
                            $user_map[ $previous_owner ] = $previous_user;
                            $ws_changing_user_map[ $workspace_id ][ $previous_owner ][] = $obj;
                        }
                    }
                    $orig_status = $obj->status;
                    $original = clone $obj;
                    $obj->status( $workflow_status );
                    if (! $obj->save() ) $error = true;
                    $changes_workspace_ids[ $workspace_id ] = true;
                    $counter++;
                    $original->status( $orig_status );
                    $old_objects[ $obj->id ] = $original;
                    $callback = ['name' => 'post_save', 'error' => '', 'is_new' => false ];
                    $app->run_callbacks( $callback, $model, $obj, $original );
                    $error = $error ? $error : $callback['error'];
                }
                if ( $error || !empty( $app->db->errors ) ) {
                    $errstr = $app->translate( 'An error occurred while saving %s.',
                              $app->translate( $table->label ) );
                    $app->rollback( $errstr );
                } else {
                    $app->db->commit();
                }
                if ( $workflow->notify_changes ) {
                    $object_label_plural = $app->translate( $table->plural );
                    $object_label = $app->translate( $table->label );
                    $ctx->vars['by_user'] = $app->user->nickname;
                    $list_url = $app->admin_url . '?__mode=view&_type=list&_model=' . $model;
                    if ( $workspace_id ) {
                        $list_url .= '&workspace_id=' . $workspace_id;
                    }
                    $workspace = $workspace_id
                               ? $app->db->model( 'workspace' )->load( (int) $workspace_id )
                               : null;
                    $template = null;
                    $tmpl = $app->get_mail_tmpl( 'batch_approval_objects', $template, $workspace );
                    $ctx->stash( 'workspace', $workspace );
                    if (! empty( $ws_changing_user_map ) ) {
                        if ( isset ( $ws_changing_user_map[ $workspace_id ] ) ) {
                            $ws_changes = $ws_changing_user_map[ $workspace_id ];
                            foreach ( $ws_changes as $user_id => $user_objs ) {
                                $params = [];
                                $i = 0;
                                $changed_obj_ids = [];
                                $old_user = $user_map[ $user_id ];
                                foreach ( $user_objs as $changed_obj ) {
                                    $old_obj = $old_objects[ $changed_obj->id ];
                                    $new_user = $user_map[ $changed_obj->user_id ];
                                    $ctx->vars['to_user'] = $new_user->nickname;
                                    $_params = $this->set_params( $app, $changed_obj, $new_user, $primary );
                                    $this->set_defferent_context( $ctx, $old_obj, $changed_obj,
                                                                  $old_user, $app->user() );
                                    $changed_obj_ids[] = $changed_obj->id;
                                    $params[] = $_params;
                                    $i++;
                                }
                                $ctx->vars['object_label_plural'] = ( $i == 1 )
                                    ? $object_label_plural : $object_label;
                                $ctx->vars['changed_object_loop'] = $params;
                                $ctx->vars['list_url'] = $list_url;
                                $ctx->vars['changed_count_objects'] = $i;
                                $subject = '';
                                if ( $template && $template->subject ) {
                                    $subject = $app->build( $template->subject );
                                } else {
                                    if ( $i == 1 ) {
                                        $subject = $app->translate ( '(%s)', $workspace_name ) .
                                        $this->translate( '%s %s you requested for approval was approved by %s.',
                                          [ $ctx->vars['changed_count_objects'],
                                            $ctx->vars['object_label_plural'],
                                            $ctx->vars['by_user'] ] );
                                    } else {
                                        $subject = $app->translate ( '(%s)', $workspace_name ) .
                                        $this->translate( '%s %s you requested for approval were approved by %s.',
                                          [ $ctx->vars['changed_count_objects'],
                                            $ctx->vars['object_label_plural'],
                                            $ctx->vars['by_user'] ] );
                                    }
                                }
                                $ctx->vars['mail_footer'] = $app->build( $mail_footer );
                                $body = $app->build( $tmpl );
                                $headers = ['From' => $app->user()->email ];
                                $error = '';
                                PTUtil::send_mail(
                                    $old_user->email, $subject, $body, $headers, $error );
                            }
                        }
                    }
                }
            }
            $action_name = $this->translate( 'Approval Request or Remand of %s', $plural );
            $list_actions->log( $action_name, $model, $counter );
            return $this->finish_action
                    ( $app, $rebuild_ids, $counter, $publish_objs, $publish_originals );
        } else {
            $workflows = [];
            foreach ( $workspace_ids as $workspace_id ) {
                $workflow = $workflow_map[ $workspace_id ];
                $values = $workflow->get_values();
                $column_vars = [];
                foreach ( $values as $key => $value ) {
                    $column_vars["_{$key}"] = $value;
                }
                $group_name = $app->permission_group( $app->user(), $model, $workspace_id );
                $column_vars['_workflow_user_type'] = $group_name;
                $workspace = $workflow->workspace;
                if ( $workspace ) {
                    $column_vars['_workflow_workspace_name'] = $workspace->name;
                } else {
                    $column_vars['_workflow_workspace_name'] = $app->appname;
                }
                $column_vars['_workflow_object_count'] = count( $ws_object_map[ $workspace_id ] );
                $workflows[] = $column_vars;
            }
            $ctx->vars['workflows'] = $workflows;
        }
        if ( $approval_type == 1 ) {
            return $app->build_page( 'batch_approval_or_remand.tmpl' );
        } else {
            return $app->build_page( 'batch_approval_objects.tmpl' );
        }
    }

    function finish_action ( $app, $rebuild_ids, $counter, $publish_objs, $publish_originals ) {
        $model = $app->param( '_model' );
        $return_args = "does_act=1&__mode=view&_type=list&_model={$model}&"
                     . "apply_actions={$counter}" . $app->workspace_param;
        if (! empty( $publish_objs ) ) {
            $i = 0;
            foreach ( $publish_objs as $publish_obj ) {
                $original = $publish_originals[ $i ];
                $app->publish_obj( $publish_obj, $original, true );
                $i++;
            }
        }
        if (! empty( $rebuild_ids ) ) {
            $ids = join( ',', $rebuild_ids );
            $app->mode = 'rebuild_phase';
            $app->param( '__mode', 'rebuild_phase' );
            $app->param( 'ids', $ids );
            $app->param( 'apply_actions', $counter );
            $app->param( '_return_args', $return_args );
            return $app->rebuild_phase( $app, true, 0, true );
        }
        $app->redirect( $app->admin_url . '?' . $return_args );
    }

    function set_params ( $app, $changed_obj, $user, $primary ) {
        $_params = $changed_obj->get_values();
        $_params['object_name'] = $changed_obj->$primary;
        $_params['object_id'] = $changed_obj->id;
        $_params['object_permalink'] = $app->get_permalink( $changed_obj );
        $model = $changed_obj->_model;
        if ( $app->can_do( $model, 'edit',
                $changed_obj, $changed_obj->workspace, $user ) ) {
            $edit_link = $app->admin_url . '?__mode=view&_type=edit&_model=';
            $edit_link .= $model . '&id=' . $changed_obj->id;
            if ( $changed_obj->workspace_id ) {
                $edit_link .= '&workspace_id=' . $changed_obj->workspace_id;
            }
            $_params['edit_link'] = $edit_link;
        }
        return $_params;
    }
    
    function set_defferent_context ( &$ctx, $old_obj, $new_obj, $old_user, $new_user ) {
        $model = $new_obj->_model;
        $ctx->vars['object_user_change'] = '';
        if ( $old_user->id != $new_user->id ) {
            $ctx->vars['object_user_changed']
                = $this->translate( 'Changing user in charge(from %s to %s)',
                [ $old_user->nickname, $new_user->nickname ] );
        }
        $tags = $this->tags;
        $ctx->vars['object_status_changed'] = '';
        if ( $new_obj->status != $old_obj->status ) {
            $tag_args = ['status' => $old_obj->status, 'model' => $model ];
            $status_old = $tags->hdlr_statustext( $tag_args, $ctx );
            $tag_args = ['status' => $new_obj->status, 'model' => $model ];
            $status_new = $tags->hdlr_statustext( $tag_args, $ctx );
            $ctx->vars['object_status_changed']
                = $this->translate( 'Change status(from %s to %s)',
                [ $status_old, $status_new] );
        }
    }
}