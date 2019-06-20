<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );

class Members extends PTPlugin {

    public $cookie_name = 'pt-member';
    public $member = null;

    function __construct () {
        parent::__construct();
    }

    function hdlr_if_login ( $args, $content, $ctx, $repeat, $counter ) {
        $member = $this->member( $ctx->app );
        if ( $member ) {
            if ( isset( $args['setcontext'] ) && $args['setcontext'] ) {
                $ctx->stash( 'member', $member );
            }
            return true;
        }
        return false;
    }

    function hdlr_member_context ( $args, $content, $ctx, $repeat, $counter ) {
        $localvars = ['member'];
        $member = null;
        if (! $counter ) {
            $member = $this->member( $ctx->app );
            if (! $member ) {
                $repeat = false;
                return;
            }
            $ctx->localize( $localvars );
            $ctx->stash( 'member', $member );
        }
        $member = $ctx->stash( 'member' );
        if ( $member ) {
            $ctx->stash( 'member', $member );
        }
        if ( $counter ) {
            $ctx->restore( $localvars );
            $repeat = false;
        }
        return $content;
    }

    function hdlr_if_members_app_url ( $args, $content, $ctx, $repeat, $counter ) {
        $app = $ctx->app;
        $workspace_id = isset( $args['workspace_id'] ) && ctype_digit( $args['workspace_id'] )
                      ? (int) $args['workspace_id'] : 'any';
        if ( $workspace_id == 'any' ) {
            $options = $app->db->model( 'option' )->load( ['kind' => 'plugin_setting',
                                                           'key' => 'members_app_url', 'extra' => 'members'] );
            if ( is_array( $options ) && count( $options ) ) {
                foreach ( $options as $option ) {
                    if ( $option->value && $app->is_valid_url( $option->value ) ) {
                        return true;
                    }
                }
            }
        } else {
            $app_url =
                $this->get_config_value( 'members_app_url', $workspace_id );
            if ( $app_url && $app->is_valid_url( $app_url ) ) {
                return true;
            }
        }
        return false;
    }

    function hdlr_members_app_url ( $args, $ctx ) {
        $workspace_id = isset( $args['workspace_id'] ) && ctype_digit( $args['workspace_id'] )
                      ? (int) $args['workspace_id'] : 0;
        $app_url = $this->get_config_value( 'members_app_url', $workspace_id, true );
        return $app_url;
    }

    function pre_save_member ( &$cb, $app, &$obj, $original ) {
        if ( $app->param( 'reg_workspace_id_selector' ) == 'system' ) {
            $obj->reg_workspace_id( 0 );
        }
        if ( $app->param( 'member_send_notification' ) && !$obj->notification ) {
            $component = $this;
            $obj->notification( 1 );
            $workspace_id = $obj->reg_workspace_id;
            $workspace = $workspace_id ?
                $app->db->model( 'workspace' )->load( (int) $workspace_id ) : null;
            // send notification
            $ctx = $app->ctx;
            $app->set_mail_param( $ctx );
            $subject = null;
            $body = null;
            $template = null;
            $body = $app->get_mail_tmpl( 'members_activate_notification', $template, $workspace );
            if ( $template ) {
                $subject = $template->subject;
            }
            if (! $subject ) {
                $subject = $app->translate( "Your account of '%s' was activated by administrator.", $ctx->vars['app_name'] );
            }
            $mail_from = $this->mail_from( $app, $workspace_id );
            $app_url =
                $component->get_config_value( 'members_app_url', $workspace_id, true );
            if (! $app_url ) {
                $cb['error'] = $app->translate( 'App URL has not been set.' );
                return false;
            }
            $ctx->vars['admin_url'] = $app_url;
            $ctx->vars['workspace_id'] = $workspace_id;
            $headers = ['From' => $mail_from ];
            $error = '';
            $body = $app->build( $body );
            $subject = $app->build( $subject );
            $mail_from = $app->build( $mail_from );
            if (! PTUtil::send_mail(
                $obj->email, $subject, $body, $headers, $error ) ) {
                $cb['errors'][] = $error;
                return false;
            }
        }
        if (! $obj->notification ) {
            $obj->notification( 0 );
        }
        return true;
    }

    function mail_from ( $app, $workspace_id = 0 ) {
        $mail_from =
            $this->get_config_value( 'members_email_from', $workspace_id, true );
        if (! $mail_from ) {
            $system_email = $app->get_config( 'system_email' );
            if (!$system_email ) {
                return $app->error( 'System Email Address is not set in System.' );
            }
            $mail_from = $system_email->value;
        }
        return $mail_from;
    }

    function member ( $app ) {
        if ( $this->member ) {
            return $this->member;
        }
        if (! $app ) $app = Prototype::get_instance();
        $cookie = $app->cookie_val( $this->cookie_name );
        if (!$cookie ) return null;
        $sess = $app->db->model( 'session' )->get_by_key(
            ['name' => $cookie, 'kind' => 'US', 'key' => 'member'] );
        if (! $sess->id ) return null;
        if (! $sess->user_id ) return null;
        $member = $app->db->model( 'member' )->get_by_key( (int) $sess->user_id );
        if (! $member->id || $member->delete_flag ) return null;
        $status_published = $app->status_published( 'member' );
        if ( $member->status != $status_published ) {
            return null;
        }
        $app->language = $member->language;
        $app->ctx->vars['user_language'] = $member->language;
        if ( $member->lock_out ) return null;
        if ( $app->always_update_login ) {
            $workspace_id = $app->workspace_id
                          ? $app->workspace_id : $app->param( 'workspace_id' );
            if (! $workspace_id ) $workspace_id = 0;
            $expires =
                time() + $this->get_config_value( 'members_sess_timeout', $workspace_id, true );
            if ( $sess->expires < $expires ) {
                $sess->expires( $expires );
                $sess->save();
            }
            $member->last_login_on( date( 'YmdHis' ) );
            $member->last_login_ip( $app->remote_ip );
            $member->save();
        }
        $this->member = $member;
        return $member;
    }

}