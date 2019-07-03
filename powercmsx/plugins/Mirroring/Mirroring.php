<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );

class Mirroring extends PTPlugin {

    public $upgrade_functions = [ ['version_limit' => 0.1, 'method' => 'add_custom_permission'] ];

    function __construct () {
        $app = Prototype::get_instance();
        if ( $max_execution_time = $app->max_exec_time ) {
            $max_execution_time = (int) $max_execution_time;
            ini_set( 'max_execution_time', $max_execution_time );
        }
        parent::__construct();
    }

    function activate ( $app, $plugin, $version, &$errors ) {
        return $this->add_custom_permission( $app, $plugin, $version, $errors );
    }

    function deactivate ( $app, $plugin, $version, &$errors ) {
        $column = $app->db->model( 'column' )->get_by_key( ['table_id' => $table->id, 'name' => 'can_mirroring'] );
        if (! $column->id ) return true;
        $column->remove();
        return true;
    }

    function has_settings ( $app, $workspace, $menu ) {
        $workspace_id = $workspace ? (int) $workspace->id : 0;
        $cmd = $app->mirroring_lftp_path;
        if (! file_exists( $cmd ) ) {
            return;
        }
        $commands = [];
        $rsync_commands = [];
        $commands_debug = [];
        $commands_dummy = [];
        $commands_dummy_debug = [];
        $servers = [];
        $dirs = [];
        $has_setting = false;
        $this->get_commands( $cmd, $workspace_id, $commands, $rsync_commands, $commands_debug,
        $commands_dummy, $commands_dummy_debug, $servers, $dirs, $has_setting );
        return $has_setting;
    }

    function add_custom_permission ( $app, $plugin, $version, &$errors ) {
        $table = $app->get_table( 'role' );
        $column = $app->db->model( 'column' )->get_by_key( ['table_id' => $table->id, 'name' => 'can_mirroring'] );
        if ( $column->id ) return true;
        $upgrader = new PTUpgrader;
        $can_mirroring = ['type' => 'tinyint', 'label' => 'Can Mirroring', 'index' => 1,
                          'size' => 4, 'order' => 185, 'edit' => 'checkbox', 'list' =>  'checkbox', 'not_null' => 1];
        $upgrade = $upgrader->make_column( $table, 'can_mirroring', $can_mirroring, false );
        $upgrader->upgrade_scheme( 'role' );
        return true;
    }

    function mirroring_lftp_test ( $app ) {
        $cmd = $app->mirroring_lftp_path;
        $cmd = escapeshellcmd( $cmd );
        if (! file_exists( $cmd ) ) {
            $cmd = $app->escape( $cmd );
            return $app->error( $this->translate( '%s was not found on this server.', $cmd ) );
        }
        if ( basename( $cmd ) !== 'lftp.exe' && basename( $cmd ) !== 'lftp' ) {
            return $app->error( $this->translate( 'Invalid command(%s).', $app->escape( $cmd ) ) );
        }
        $output = [];
        $return_var = '';
        $test = "{$cmd} -h";
        exec( $test, $output, $return_var );
        if ( $return_var !== 0 ) {
            return $app->error( $this->translate( "Can't execute command '%s' from PHP.", $test ) );
        }
        $app->ctx->vars['lftp_cmd'] = $cmd;
        return $app->__mode( 'mirroring_lftp_test' );
    }

    function mirroring_the_website ( $app ) {
        $cmd = $app->mirroring_lftp_path;
        $cmd = escapeshellcmd( $cmd );
        if (! file_exists( $cmd ) ) {
            return $app->error( $this->translate( '%s was not found on this server.', $app->escape( $cmd ) ) );
        }
        if ( basename( $cmd ) !== 'lftp.exe' && basename( $cmd ) !== 'lftp' ) {
            return $app->error( $this->translate( 'Invalid command(%s).', $app->escape( $cmd ) ) );
        }
        $output = [];
        $return_var = '';
        $test = "{$cmd} -h";
        exec( $test, $output, $return_var );
        if ( $return_var !== 0 ) {
            return $app->error( $this->translate( "Can't execute command '%s' from PHP.", $test ) );
        }
        $ctx = $app->ctx;
        $workspace_id = (int) $app->param( 'workspace_id' );
        $mirroring_reserved = (int) trim( $this->get_config_value( 'mirroring_reserved', $workspace_id ) );
        $commands = [];
        $rsync_commands = [];
        $commands_debug = [];
        $commands_dummy = [];
        $commands_dummy_debug = [];
        $servers = [];
        $dirs = [];
        $has_setting = false;
        $this->get_commands( $cmd, $workspace_id, $commands, $rsync_commands, $commands_debug,
                             $commands_dummy, $commands_dummy_debug, $servers, $dirs, $has_setting );
        $does_act = '';
        if ( $app->request_method == 'POST' ) {
            $app->validate_magic();
            $results = [];
            $type = $app->param( 'type' );
            if ( $type == 'debug' ) {
                $i = 0;
                foreach ( $commands_debug as $cmd ) {
                    $output = [];
                    $return_var = '';
                    exec( $cmd, $output, $return_var );
                    if ( $return_var !== 0 ) {
                        return $app->error( $this->translate( 'An error occurred while mirroring (error code %s).', $return_var ) );
                    }
                    $ctx->vars['mirroring_done'] = 1;
                    $show_command = $commands_dummy_debug[ $i ];
                    $results[] = $show_command;
                    $results[] = '-------------------------------------------------';
                    $results = array_merge( $results, $output );
                    $i++;
                }
                $does_act = 'debug';
            } else if ( $type == 'staging' ) {
                $cmd = $app->mirroring_rsync_path;
                $cmd = escapeshellcmd( $cmd );
                if (! file_exists( $cmd ) ) {
                    $cmd = '';
                }
                if ( basename( $cmd ) !== 'rsync.exe' && basename( $cmd ) !== 'rsync' ) {
                    $cmd = '';
                }
                $output = [];
                $return_var = '';
                $test = "{$cmd} -h";
                exec( $test, $output, $return_var );
                if ( $return_var !== 0 ) {
                    $cmd = '';
                }
                if ( $cmd ) {
                    $i = 0;
                    foreach ( $rsync_commands as $cmd ) {
                        $output = [];
                        $return_var = '';
                        exec( $cmd, $output, $return_var );
                        $server = $dirs[ $i ];
                        $ctx->vars['mirroring_done'] = 1;
                        $results[] = $cmd;
                        $results[] = '-------------------------------------------------';
                        $results = array_merge( $results, $output );
                        array_unshift( $output, $cmd );
                        $app->log( ['message'   => $this->translate( 'Mirroring to staging directory %s has been performed.', $server ),
                                    'category'  => 'mirroring',
                                    'metadata'  => json_encode( $output ),
                                    'level'     => 'info'] );
                        $i++;
                    }
                } else {
                    $server = $this->get_config_value( 'mirroring_staging1', $workspace_id );
                    $results = $this->sync_directories( $workspace_id );
                    $app->log( ['message'   => $this->translate( 'Mirroring to staging directory %s has been performed.', $server ),
                                'category'  => 'mirroring',
                                'metadata'  => json_encode( $results ),
                                'level'     => 'info'] );
                }
                $does_act = 'staging';
            } else if ( $type == 'mirroring' ) {
                $i = 0;
                foreach ( $commands as $cmd ) {
                    $output = [];
                    $return_var = '';
                    exec( $cmd, $output, $return_var );
                    $server = $servers[ $i ];
                    if ( $return_var !== 0 ) {
                        return $app->error( $this->translate( 'An error occurred while mirroring to %s.', $server ) );
                    }
                    $ctx->vars['mirroring_done'] = 1;
                    $show_command = $commands_dummy[ $i ];
                    $results[] = $show_command;
                    $results[] = '-------------------------------------------------';
                    $results = array_merge( $results, $output );
                    array_unshift( $output, $show_command );
                    $app->log( ['message'   => $this->translate( 'Mirroring to %s has been performed.', $server ),
                                'category'  => 'mirroring',
                                'metadata'  => json_encode( $output ),
                                'level'     => 'info'] );
                    $i++;
                }
                $does_act = 'mirroring';
            } else if ( $type == 'reserve' ) {
                $date = $app->param( 'reserve_date' );
                $time = $app->param( 'reserve_time' );
                $ts = "{$date}{$time}";
                $ts = preg_replace( '/[^0-9]/', '', $ts );
                $y = (int) substr( $ts, 0, 4 );
                $m = (int) substr( $ts, 4, 2 );
                $d = (int) substr( $ts, 6, 2 );
                if (! checkdate( $m, $d, $y ) ) {
                    $ts = null;
                }
                if ( preg_match( '/^[0-9]{12}$/', $ts ) ) {
                    $ts .= '00';
                }
                $now = date( 'YmdHis' );
                if (! $ts || $ts < $now ) {
                    $msg = $ts ? $this->translate( 'Please specify the correct date.' )
                               : $this->translate( 'The past date can not be specified.' );
                    return $app->error( $msg );
                }
                if (! preg_match( '/^[0-9]{14}$/', $ts ) ) {
                    return $app->error( $this->translate( 'Please specify the correct date.' ) );
                }
                $this->set_config_value( 'mirroring_reserved', $ts, $workspace_id );
                $does_act = 'reserve';
            } else if ( $type == 'cancel_reservation' ) {
                $this->set_config_value( 'mirroring_reserved', '', $workspace_id );
                $mirroring_reserved = '';
                $does_act = 'cancel_reservation';
            }
            $ctx->vars['mirroring_results'] = $results;
            if ( $does_act ) {
                $session_id = '';
                $return_args = "does_act={$does_act}&__mode=mirroring_the_website"
                             . $app->workspace_param;
                if ( count( $results ) ) {
                    $sess = $app->db->model( 'session' )->new();
                    $sess->name( $app->magic() );
                    $sess->workspace_id( $workspace_id );
                    $sess->kind( 'MR' );
                    $sess->user_id( $app->user()->id );
                    $sess->text( json_encode( $results ) );
                    $sess->start( time() );
                    $sess->expires( time() + 100 );
                    $sess->save();
                    $session_id = $sess->id;
                }
                if ( $session_id ) $return_args .= "&session_id={$session_id}";
                $app->redirect( $app->admin_url . '?' . $return_args );
            }
        }
        if ( $session_id = $app->param( 'session_id' ) ) {
            $sess = $app->db->model( 'session' )->load( ['id' => (int) $session_id, 'kind' => 'MR',
                        'workspace_id' => $workspace_id, 'user_id' => $app->user()->id ] );
            if ( is_array( $sess ) && count( $sess ) ) {
                $sess = $sess[0];
                $results = json_decode( $sess->text, true );
                $ctx->vars['mirroring_results'] = $results;
                $ctx->vars['mirroring_done'] = 1;
                $sess->remove();
            }
        }
        $staging = $this->get_config_value( 'mirroring_staging1', $workspace_id );
        $staging = rtrim( $staging, DS );
        $match_root = false;
        if (! $staging || ! is_dir( $staging ) ) {
        } else {
            $staging_root_path = $app->mirroring_staging_root_path;
            $staging_root_paths = preg_split( "/\s*,\s*/", $staging_root_path );
            foreach ( $staging_root_paths as $root_path ) {
                if ( stripos( $staging, $root_path ) === 0 ) {
                    $match_root = true;
                    break;
                }
            }
        }
        $ctx->vars['has_staging'] = $match_root;
        $mirroring_reserved = $mirroring_reserved ? $mirroring_reserved : null;
        $ctx->vars['mirroring_reserved'] = $mirroring_reserved;
        $mirroring_reserved_time = $mirroring_reserved
                                 ? substr( $mirroring_reserved, 8, 2 )
                                 . ':' . substr( $mirroring_reserved, 10, 2 )
                                 . ':' . substr( $mirroring_reserved, 12, 2 ) : '00:00:00';
        $mirroring_reserved_date = $mirroring_reserved_time ? substr( $mirroring_reserved, 0, 4 )
                                 . '-' . substr( $mirroring_reserved, 4, 2 )
                                 . '-' . substr( $mirroring_reserved, 6, 2 ) : '';
        $ctx->vars['mirroring_reserved_date'] = $mirroring_reserved_date;
        $ctx->vars['mirroring_reserved_time'] = $mirroring_reserved_time;
        $ctx->vars['mirroring_commands'] = $commands_dummy;
        $ctx->vars['mirroring_has_setting'] = $has_setting;
        return $app->__mode( 'mirroring_the_website' );
    }

    function sync_directories ( $workspace_id ) {
        $start = microtime( true );
        $messages = [];
        $app = Prototype::get_instance();
        $staging = $this->get_config_value( 'mirroring_staging1', $workspace_id );
        $hidden = $this->get_config_value( 'mirroring_hidden1', $workspace_id );
        $delete = $this->get_config_value( 'mirroring_delete1', $workspace_id );
        $excludes = $this->get_config_value( 'mirroring_excludes1', $workspace_id );
        $excludes = preg_split( "/\s*,\s*/", $excludes );
        $hidden = $hidden ? false : true;
        $staging = rtrim( $staging, DS );
        if (! $staging || ! is_dir( $staging ) ) {
            return [];
        }
        $staging_root_path = $app->mirroring_staging_root_path;
        $staging_root_paths = preg_split( "/\s*,\s*/", $staging_root_path );
        $match_root = false;
        foreach ( $staging_root_paths as $root_path ) {
            if ( stripos( $staging, $root_path ) === 0 ) {
                $match_root = true;
                break;
            }
        }
        if (! $match_root ) {
            return [];
        }
        $dirs = [];
        $staging_files = [];
        PTUtil::file_find( $staging, $staging_files, $dirs, $hidden );
        $site_path = $workspace_id ? $app->workspace()->site_path : $app->site_path;
        $site_path = rtrim( $site_path, DS );
        if (! $site_path || ! is_dir( $site_path ) ) {
            return [];
        }
        $counter = 0;
        $site_files = [];
        $dirs = [];
        PTUtil::file_find( $site_path, $site_files, $dirs, $hidden );
        $search_path = preg_quote( $site_path, '/' );
        $mirroring = [];
        $delete_files = [];
        foreach ( $site_files as $site_file ) {
            $relative = preg_replace( "/^$search_path/", '', $site_file );
            foreach ( $excludes as $exclude ) {
                if ( preg_match( "!{$exclude}!", ltrim( $relative, DS ) ) ) {
                    continue 2;
                }
            }
            if (! file_exists( $staging . $relative ) ) {
                $mirroring[ $site_file ] = $staging . $relative;
            } else {
                $stage_ts = filemtime( $staging . $relative );
                $site_ts = filemtime( $site_file );
                if ( $stage_ts < $site_ts ) {
                    $mirroring[ $site_file ] = $staging . $relative;
                }
            }
        }
        $messages[] = 'building file list ... done';
        $messages[] = '';
        $search_staging = preg_quote( $staging, '/' );
        if ( $delete ) {
            foreach ( $staging_files as $stage_file ) {
                $relative = preg_replace( "/^$search_staging/", '', $stage_file );
                foreach ( $excludes as $exclude ) {
                    if ( preg_match( "!{$exclude}!", ltrim( $relative, DS ) ) ) {
                        continue 2;
                    }
                }
                if (! file_exists( $site_path . $relative ) ) {
                    $delete_files[ $stage_file ] = true;
                }
            }
        }
        $output = [];
        $cmd = '';
        if ( substr( PHP_OS, 0, 3 ) != 'WIN' ) {
            $return_var = '';
            $test = "which cp";
            exec( $test, $output, $return_var );
            if ( $return_var === 0 ) {
                $cmd = $output[0];
            }
        }
        $fmgr = $app->fmgr;
        foreach ( $mirroring as $from => $to ) {
            $relative = preg_replace( "/^$search_path/", '', $from );
            $messages[] = "copying {$relative}";
            $counter++;
            if ( $cmd ) {
                $fmgr->copy( $from, $to, $cmd, '-p' );
            } else {
                $fmgr->copy( $from, $to );
            }
        }
        foreach ( $delete_files as $file => $bool ) {
            $relative = preg_replace( "/^$search_staging/", '', $file );
            $counter++;
            $messages[] = "deleting {$relative}";
            $fmgr->delete( $file );
        }
        $past = microtime( true ) - $start;
        $past = round( $past * 100000 ) / 100000;
        if (! $counter ) {
            $messages[] = 'No files have been changed.';
        } else {
            $messages[] = '';
        }
        $messages[] = "It took {$past} seconds to process";
        return $messages;
    }

    function get_commands ( $cmd, $workspace_id, &$commands, &$rsync_commands, &$commands_debug,
        &$commands_dummy, &$commands_dummy_debug, &$servers, &$dirs, &$has_setting ) {
        $app = Prototype::get_instance();
        $upload_dir = $app->upload_dir();
        $rsync = $app->mirroring_rsync_path;
        $rsync = escapeshellcmd( $rsync );
        if (! file_exists( $rsync ) ) {
            return;
        }
        if ( basename( $rsync ) !== 'rsync.exe' && basename( $rsync ) !== 'rsync' ) {
            $rsync = '';
        }
        $staging_root_path = $app->mirroring_staging_root_path;
        $staging_root_paths = preg_split( "/\s*,\s*/", $staging_root_path );
        $mirroring_servers = $app->mirroring_servers;
        for ( $i = 1; $i <= $mirroring_servers; $i++ ) {
            $protocol = $this->get_config_value( 'mirroring_protocol' . $i, $workspace_id );
            if ( $protocol == 'sftp' || $protocol == 'ftp' || $protocol == 'ftps' ) {
                $port = (int) $this->get_config_value( 'mirroring_port' . $i, $workspace_id );
                if (! $port ) {
                    if ( $protocol == 'sftp' ) {
                        $port = 22;
                    } else if ( $protocol == 'ftp' ) {
                        $port = 20;
                    } else if ( $protocol == 'ftps' ) {
                        $port = 21;
                    }
                }
                $server = trim( $this->get_config_value( 'mirroring_mirroring' . $i, $workspace_id ) );
                $dir = trim( $this->get_config_value( 'mirroring_staging' . $i, $workspace_id ) );
                if (! is_dir( $dir ) || $dir == '/' || !$staging_root_path ) {
                    $dir = '';
                } else {
                    $dir = rtrim( $dir, DS );
                    $dir = escapeshellarg( $dir );
                    $dir = preg_replace( "/^'/", '', $dir );
                    $dir = preg_replace( "/'$/", '', $dir );
                    $match_root = false;
                    foreach ( $staging_root_paths as $root_path ) {
                        if ( stripos( $dir, $root_path ) === 0 ) {
                            $match_root = true;
                            break;
                        }
                    }
                    if (! $match_root ) $dir = '';
                }
                $dirs[] = $dir;
                $login_id = $this->get_config_value( 'mirroring_login_id' . $i, $workspace_id );
                $login_pw = $this->get_config_value( 'mirroring_login_pw' . $i, $workspace_id );
                if (! $server ||! $login_id ) continue;
                $has_setting = true;
                $login_pw_dummy = '';
                $login_pw_dummy = $login_pw ? preg_replace( '/./', '*', $login_pw ) : '';
                $login_id_dummy = $login_pw_dummy ? "{$login_id},{$login_pw_dummy}" : $login_id;
                $login_id = $login_pw ? "{$login_id},{$login_pw}" : $login_id;
                $delete = $this->get_config_value( 'mirroring_delete' . $i, $workspace_id );
                $delete = $delete ? ' --delete' : '';
                $hidden = $this->get_config_value( 'mirroring_hidden' . $i, $workspace_id );
                $rsync_options = '';
                if ( $hidden ) {
                    $rsync_options = " --exclude='.*'";
                    $hidden = ' --exclude=^\.';
                }
                $excludes = trim( $this->get_config_value( 'mirroring_excludes' . $i, $workspace_id ) );
                $add_options = '';
                if ( $excludes ) {
                    $excludessplits = preg_split( '/\s*,\s*/', $excludes );
                    foreach ( $excludessplits as $excludessplit ) {
                        $r_exclude = $excludessplit;
                        $excludessplit = escapeshellarg( $excludessplit );
                        $excludessplit = preg_replace( "/^'/", '', $excludessplit );
                        $excludessplit = preg_replace( "/'$/", '', $excludessplit );
                        $add_options .= " --exclude={$excludessplit}";
                        if ( strpos( $r_exclude, '^' ) === 0 ) {
                            $r_exclude = preg_replace( '/^\^/', '/', $r_exclude );
                            $r_exclude = escapeshellarg( $r_exclude );
                            $rsync_options .= " --exclude={$r_exclude}";
                        }
                    }
                }
                $site_path = $workspace_id ? $app->workspace()->site_path : $app->site_path;
                $site_path = rtrim( $site_path, DS );
                $site_path = escapeshellarg( $site_path );
                $site_path = preg_replace( "/^'/", '', $site_path );
                $site_path = preg_replace( "/'$/", '', $site_path );
                $path = trim( $this->get_config_value( 'mirroring_path' . $i, $workspace_id ) );
                $path = escapeshellarg( $path );
                $path = preg_replace( "/^'/", '', $path );
                $path = preg_replace( "/'$/", '', $path );
                if ( $rsync && $dir ) {
                    $rcmd = "{$rsync} -rvtu{$delete}{$rsync_options} " . $site_path . DS . " {$dir}";
                    if ( strpos( $rcmd, "\r" ) !== false || strpos( $excec_cmd, "\n" ) !== false ) {
                        $rcmd = $app->escape( $rcmd );
                        return $app->error( $this->translate( 'Invalid command(%s).', $rcmd ) );
                    }
                    $rsync_commands[] = $rcmd;
                }
                if ( $dir ) $site_path = $dir;
                $opt = "mirror --verbose=3 --only-newer -R{$delete}{$hidden}{$add_options} {$site_path} {$path};quit";
                $excec_cmd = "{$cmd} -u {$login_id} -p {$port} -e '{$opt}' {$protocol}://{$server}";
                if ( strpos( $excec_cmd, "\r" ) !== false || strpos( $excec_cmd, "\n" ) !== false ) {
                    $excec_cmd = $app->escape( $excec_cmd );
                    return $app->error( $this->translate( 'Invalid command(%s).', $excec_cmd ) );
                }
                $debug = $upload_dir . DS . md5( $excec_cmd ) . '.txt';
                $opt_debug = "mirror --verbose=3 --dry-run={$debug} --only-newer -R{$delete}{$hidden}{$add_options} {$site_path} {$path};quit";
                $cmd_dummy = "{$cmd} -u {$login_id_dummy} -p {$port} -e '{$opt}' {$protocol}://{$server}";
                $commands[] = $excec_cmd;
                $commands_dummy[] = $cmd_dummy;
                $commands_debug[] = "{$cmd} -u {$login_id} -p {$port} -e '{$opt_debug}' {$protocol}://{$server}";
                $opt_debug = "mirror --only-newer -R{$delete}{$hidden}{$add_options} {$site_path} {$path};quit";
                $commands_dummy_debug[] = "{$cmd} -u {$login_id_dummy} -p {$port} -e '{$opt_debug}' {$protocol}://{$server}";
                $servers[] = "{$protocol}://{$server}{$path}";
            }
        }
    }

    function pre_save_plugin_config ( $cb, $app, $component, &$errors ) {
        if ( $component->id != 'mirroring' ) {
            return true;
        }
        if ( $app->param( 'reset_config' ) ) return true;
        $staging_root_path = $app->mirroring_staging_root_path;
        $staging_root_paths = preg_split( "/\s*,\s*/", $staging_root_path );
        $dir = $app->param( 'setting_mirroring_staging1' );
        if (! $dir ) return true;
        $match_root = false;
        foreach ( $staging_root_paths as $root_path ) {
            if ( stripos( $dir, $root_path ) === 0 ) {
                $match_root = true;
                break;
            }
        }
        if (! $match_root ) {
            $errors[] = $this->translate( "The staging directory's path does not forward match the environment variable 'mirroring_staging_root_path'." );
            $errors[] = $app->translate( "Failed to save plugin settings." );
            return false;
        }
        return true;
    }

    function mirroring_scheduled_tasks ( $app ) {
        $cmd = $app->mirroring_lftp_path;
        $cmd = escapeshellcmd( $cmd );
        if (! file_exists( $cmd ) ) {
            return;
        }
        if ( basename( $cmd ) !== 'lftp.exe' && basename( $cmd ) !== 'lftp' ) {
            return;
        }
        $mirroring_reserveds = $app->db->model( 'option' )->load( ['key' => 'mirroring_reserved',
                                                                   'kind' => 'plugin_setting',
                                                                   'extra' => 'mirroring'] );
        $do = 0;
        foreach ( $mirroring_reserveds as $mirroring_reserved ) {
            if (! $mirroring_reserved->value ) continue;
            $ts = date( 'YmdHis' );
            if ( $mirroring_reserved->value < $ts ) {
                $workspace_id = (int) $mirroring_reserved->workspace_id;
                $commands = [];
                $commands_debug = [];
                $commands_dummy = [];
                $commands_dummy_debug = [];
                $servers = [];
                $has_setting = false;
                $this->get_commands( $cmd, $workspace_id, $commands,
                    $commands_debug, $commands_dummy, $commands_dummy_debug, $servers, $has_setting );
                if (! $has_setting ) continue;
                $results = [];
                $i = 0;
                foreach ( $commands as $cmd ) {
                    $output = [];
                    $return_var = '';
                    exec( $cmd, $output, $return_var );
                    $server = $servers[ $i ];
                    $show_command = $commands_dummy[ $i ];
                    if ( $return_var !== 0 ) {
                        $app->log( ['message'   => $this->translate( 'An error occurred while mirroring to %s.', $server ),
                                    'category'  => 'mirroring',
                                    'metadata'  => json_encode( [ $show_command ] ),
                                    'level'     => 'error'] );
                        $i++;
                        continue;
                    }
                    $results[] = $show_command;
                    $results[] = '-------------------------------------------------';
                    $results = array_merge( $results, $output );
                    array_unshift( $output, $show_command );
                    $i++;
                    $mirroring_reserved->value('');
                    $mirroring_reserved->save();
                    $app->log( ['message'   => $this->translate( 'Mirroring to %s has been performed.', $server ),
                                'category'  => 'mirroring',
                                'metadata'  => json_encode( $output ),
                                'level'     => 'info'] );
                    $do++;
                }
            }
        }
        return $do;
    }
}