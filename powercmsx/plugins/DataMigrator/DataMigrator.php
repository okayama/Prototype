<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );
class DataMigrator extends PTPlugin {

    private $allowed = ['txt', 'xml', 'rss', 'rdf', 'csv', 'zip', 'gzip'];
    public  $import_format = '';
    public  $import_model;

    function __construct () {
        $app = Prototype::get_instance();
        $app->db->caching = false;
        if ( $max_execution_time = $app->max_exec_time ) {
            $max_execution_time = (int) $max_execution_time;
            ini_set( 'max_execution_time', $max_execution_time );
        }
        parent::__construct();
    }

    function list_options () {
        $app = Prototype::get_instance();
        $export_format = $app->registry['export_format'];
        $list_options = [];
        foreach ( $export_format as $format => $prop ) {
            $list_options[] = ['label' => $prop['label'], 'value' => $format ];
        }
        return $list_options;
    }

    function start_migration ( $app ) {
        $tmpl = 'data_migration.tmpl';
        $importers = $app->registry['import_format'];
        require_once( 'class.PTUtil.php' );
        PTUtil::sort_by_order( $importers );
        $workspace = $app->workspace() ? $app->workspace() : null;
        $importer_loop = [];
        $import_models = [];
        $import_labels = [];
        $importer_models = [];
        foreach ( $importers as $importer ) {
            $component = $app->component( $importer['component'] );
            $importer['label'] = $app->translate( $importer['label'], null, $component );
            $importer_loop[] = $importer;
            if ( isset( $importer['models'] ) ) {
                $models = $importer['models'];
                foreach ( $models as $model ) {
                    if (! in_array( $model, $import_models ) ) {
                        if ( $app->can_do( $model, 'create', null, $workspace ) ) {
                            $table = $app->get_table( $model );
                            if ( $table ) {
                                $import_models[] = $model;
                                $import_labels[] = $table->plural;
                            }
                        }
                    }
                }
            }
        }
        $ctx = $app->ctx;
        $ctx->vars['importer_loop'] = $importer_loop;
        $ctx->vars['import_models'] = $import_models;
        $ctx->vars['import_labels'] = $import_labels;
        return $app->build_page( $tmpl );
    }

    function data_migration ( $app ) {
        $app->validate_magic();
        $workspace = $app->workspace() ? $app->workspace() : null;
        $import_model = $app->param( 'import_model' );
        $this->import_model = $import_model;
        if (! $app->can_do( $import_model, 'create', null, $workspace ) ) {
            return $app->error( 'Permission denied.' );
        }
        $tmpl = 'data_migration.tmpl';
        $session_id = $app->param( 'magic' );
        if (! $session_id ) {
            return $app->error( 'Invalid request.' );
        }
        $session = $app->db->model( 'session' )->get_by_key( ['name' => $session_id ] );
        if (! $session->id || $session->user_id != $app->user()->id ) {
            return $app->error( 'Invalid request.' );
        }
        $import_format = $app->param( 'import_format' );
        $importers = $app->registry['import_format'];
        if (! $import_format || ! isset( $importers[ $import_format ] ) ) {
            $error = $this->translate( 'Unknown import format \'%s\'', $import_format );
            return $app->error( $error );
        }
        $importer = $importers[ $import_format ];
        if (! isset( $importer['models'] ) || ! in_array( $import_model, $importer['models'] ) ) {
            return $app->error( 'Invalid request.' );
        }
        $component = $app->component( $importer['component'] );
        $meth = $importer['method'];
        if ( $component && method_exists( $component, $meth ) ) {
            if ( $app->param( 'do_import' ) ) {
                $upload_dir = $app->upload_dir( false );
                $session->value( $upload_dir );
                $meta = json_decode( $session->text, true );
                $import_file = $upload_dir . DS . $meta['file_name'];
                file_put_contents( $import_file, $session->data );
                $extension = $meta['extension'];
                list ( $files, $dirs ) = [ [], [] ];
                if ( strtolower( $extension ) == 'zip' ) {
                    $zip = new ZipArchive();
                    $res = $zip->open( $import_file );
                    if ( $res === true ) {
                        $zip->extractTo( dirname( $import_file ) );
                        $zip->close();
                        unlink( $import_file );
                        require_once( 'class.PTUtil.php' );
                        $list = PTUtil::file_find( dirname( $import_file ), $files, $dirs );
                        if ( $files == 0 ) {
                            $error = $this->translate( 'Could not expand ZIP archive.' );
                            return $app->error( $error );
                        }
                        if ( count( $files ) == 1 ) {
                            $import_file = $files[0];
                        } else {
                            $import_file = $files;
                        }
                    } else {
                        $error = $this->translate( 'Could not expand ZIP archive.' );
                        return $app->error( $error );
                    }
                }
                $session->metadata( serialize( $import_file ) );
                $session->key( $import_format );
                $session->save();
                $app->ctx->vars['magic'] = $session->name;
                $app->ctx->vars['import_format'] = $import_format;
                if ( isset( $importer['options'] ) ) {
                    $params = [];
                    $options = $importer['options'];
                    foreach( $options as $option ) {
                        $key = "{$import_format}_{$option}";
                        $params[ $key ] = $app->param( $key );
                    }
                    $app->ctx->vars['import_options'] = http_build_query( $params );
                }
                return $app->build_page( $tmpl );
            } else {
                $app->init_callbacks( $import_model, 'post_import' );
                $import_files = unserialize( $session->metadata );
                if ( is_string( $import_files ) ) $import_files = [ $import_files ];
                $this->import_format = $import_format;
                $scheme = $app->get_scheme_from_db( $import_model );
                if ( method_exists( $component, 'init_migration' ) ) {
                    $component->init_migration( $app, $this, $import_files );
                }
                echo str_pad( '', 4096 ) . "\n";
                // ob_end_flush();
                echo '<html><body style="font-family: sans-serif">';
                return $component->$meth( $app, $import_files, $session );
            }
        } else {
            return $app->error( 'Invalid request.' );
        }
    }

    function upload_migration_file ( $app ) {
        $app->db->caching = false;
        $app->validate_magic( true );
        $magic = $app->magic();
        $upload_dir = $app->upload_dir( false );
        $options = ['upload_dir' => $upload_dir . DS, 'prototype' => $app,
                    'magic' => $magic, 'user_id' => $app->user()->id ];
        $name = $_FILES['files']['name'];
        $extension = strtolower( pathinfo( $name )['extension'] );
        if (! in_array( $extension, $this->allowed ) ) {
            $error = $app->translate( 'Invalid File extension\'%s\'.', $extension, $this );
            header( 'Content-type: application/json' );
            echo json_encode( ['message'=> $error ] );
            exit();
        }
        $upload_handler = new UploadHandler( $options );
    }

    function import_movabletype ( $app, $import_files, $session ) {
        $import_model = $app->param( 'import_model' );
        $set_folder = $app->param( 'movabletype_set_folder' );
        $table = $app->get_table( $import_model );
        $obj_label = $app->translate( $table->label );
        $obj_label_plural = $app->translate( $table->plural );
        $workspace_id = (int) $app->param( 'workspace_id' );
        $workspace = $app->workspace();
        $default_status = $table->default_status ? $table->default_status : 1;
        $author_setting = $app->param( 'movabletype_author_setting' );
        $password = $app->param( 'movabletype_new_author_password' );
        if ( $author_setting != 1 && ! $password ) {
            $error = $this->translate( 'Default password for new users is required.' );
            echo $error;
            exit();
        }
        if ( $password )
            $password = password_hash( $password, PASSWORD_BCRYPT );
        $comment_status = (int) $app->param( 'movabletype_comment_status' );
        if (! $comment_status || $comment_status > 3 ) {
            $comment_status = 1;
        }
        $status_map = ['publish' => 4, 'draft' => 0, 'future' => 3,
                       'review'  => 1, 'unpublish' => 5];
        $text_formats = ['markdown', 'markdown_with_smartypants', 'richtext', 'textile_2'];
        $default_formats = 'richtext';
        $entry = $app->db->model( $import_model )->new();
        $field_settings = $app->param( 'movabletype_field_settings' );
        $field_mappings = [];
        if ( $field_settings ) {
            $field_settings = preg_replace("/\r\n|\r|\n/", "\n", $field_settings );
            $lines = explode( "\n", $field_settings );
            foreach ( $lines as $line ) {
                $kv = preg_split( "/\s*=\s*/", $line );
                list( $k, $v ) = [ $kv[0], $kv[1] ];
                if ( $k && $v && $entry->has_column( $v ) ) {
                    $field_mappings[ $k . ':'] = $v;
                }
            }
        }
        $scheme = $app->get_scheme_from_db( $import_model );
        $column_defs = $scheme['column_defs'];
        $entry->workspace_id( $workspace_id );
        $comment = $app->db->model( 'comment' )->new();
        $comment->workspace_id( $workspace_id );
        $comments = [];
        $categories = [];
        $tags = [];
        $context = '';
        $users = [];
        $counter = 0;
        require_once( 'class.PTUtil.php' );
        $app->db->begin_work();
        $app->txn_active = true;
        foreach ( $import_files as $file ) {
            $handle = @fopen( $file, "r" );
            if ( $handle ) {
                while ( ( $_buffer = fgets( $handle, 4096 ) ) !== false ) {
                    $buffer = trim( $_buffer );
                    $buffer = preg_replace( "/\r\n|\r/","\n", $buffer );
                    $sep_fields = explode( ':', $buffer );
                    if ( strpos( $buffer, ':' ) !== false ) {
                        $sep_fields[0] = $sep_fields[0] . ':';
                    }
                    if ( $buffer == '-----' ) {
                        if ( $context == 'comment' ) {
                            $comments[] = $comment;
                            $comment = $app->db->model( 'comment' )->new();
                            $comment->workspace_id( $workspace_id );
                        }
                        $context = '';
                    } else if ( $buffer == '--------' ) {
                        // $app->db->begin_work();
                        $error = false;
                        $entry->comment_count( count( $comments ) );
                        $entry->text( rtrim( $entry->text ) );
                        $entry->excerpt( rtrim( $entry->excerpt ) );
                        $entry->text_more( rtrim( $entry->text_more ) );
                        $entry->keywords( rtrim( $entry->keywords ) );
                        $app->set_default( $entry );
                        echo $this->translate( "Saving %s ('%s')...", [
                                $obj_label,
                                htmlspecialchars( $entry->title ) ] );
                        if ( $entry->save() ) {
                            echo $this->translate( 'ok (ID %s)', $entry->id );
                            $counter++;
                        } else {
                            echo $this->translate( 'Saving %s failed.', $obj_label );
                            $error = true;
                        }
                        echo "<br>\n";
                        flush();
                        if (! empty( $categories ) ) {
                            $categories = array_unique( $categories );
                            if ( $import_model == 'entry' ) {
                                $to_ids = [];
                                foreach ( $categories as $cat ) {
                                    $category = $app->db->model( 'category' )->get_by_key(
                                                                    ['label' => $cat ] );
                                    if (! $category->id ) {
                                        if ( $app->can_do( 'category', 'create', null, $workspace ) ) {
                                            $category->workspace_id( $workspace_id );
                                            $basename = PTUtil::make_basename( $category, $cat, true );
                                            $category->basename( $basename );
                                            echo $this->translate( "Creating new category ('%s')...", 
                                                    htmlspecialchars( $cat ) );
                                            $app->set_default( $category );
                                            if ( $category->save() ) {
                                                echo $this->translate( 'ok (ID %s)', $category->id );
                                            } else {
                                                echo $this->translate( 'Saving category failed.' );
                                                $error = true;
                                            }
                                            echo "<br>\n";
                                            flush();
                                            // echo $this->translate( 'You do not have permission to create Category.' );
                                        }
                                    }
                                    if ( $category->id )
                                        $to_ids[] = (int) $category->id;
                                }
                                if ( count( $to_ids ) ) {
                                    $args = ['from_id'  => $entry->id, 
                                             'name'     => 'categories',
                                             'from_obj' => 'entry',
                                             'to_obj'   => 'category'];
                                    $app->set_relations( $args, $to_ids );
                                }
                            } else if ( $set_folder ) {
                                $label = $categories[0];
                                $folder = $app->db->model( 'folder' )->get_by_key(
                                                                    ['label' => $label ] );
                                if (! $folder->id ) {
                                    $folder = $app->db->model( 'folder' )->get_by_key(
                                                                        ['basename' => $label ] );
                                }
                                if (! $folder->id && $app->can_do( 'folder', 'create', null, $workspace ) ) {
                                    if (! $folder->label ) {
                                        $folder->label( $label );
                                    }
                                    $app->set_default( $folder );
                                    echo $this->translate( "Creating new folder ('%s')...", 
                                            htmlspecialchars( $label ) );
                                    if ( $folder->save() ) {
                                        echo $this->translate( 'ok (ID %s)', $folder->id );
                                    } else {
                                        echo $this->translate( 'Saving folder failed.' );
                                        $error = true;
                                    }
                                    echo "<br>\n";
                                    flush();
                                    // echo $this->translate( 'You do not have permission to create Category.' );
                                }
                                if ( $folder->id ) {
                                    $entry->folder_id( $folder->id );
                                    $entry->save();
                                }
                            }
                        }
                        if (! empty( $tags ) ) {
                            $tags = array_unique( $tags );
                            $to_ids = [];
                            foreach ( $tags as $tag ) {
                                if ( function_exists( 'normalizer_normalize' ) ) {
                                    $tag = normalizer_normalize( $tag, Normalizer::NFKC );
                                }
                                $normalize = preg_replace( '/\s+/', '',
                                                    trim( strtolower( $tag ) ) );
                                if (! $tag ) continue;
                                $terms = ['normalize' => $normalize ];
                                $terms['workspace_id'] = $workspace_id;
                                $tag_obj = $app->db->model( 'tag' )->get_by_key( $terms );
                                if (! $tag_obj->id ) {
                                    $tag_obj->name( $tag );
                                    $app->set_default( $tag_obj );
                                    $order = $app->get_order( $tag_obj );
                                    $tag_obj->order( $order );
                                    $tag_obj->save();
                                }
                                $to_ids[] = (int) $tag_obj->id;
                            }
                            $args = ['from_id'  => $entry->id, 
                                     'name'     => 'tags',
                                     'from_obj' => $import_model,
                                     'to_obj'   => 'tag'];
                            $app->set_relations( $args, $to_ids );
                        }
                        if (! empty( $comments ) ) {
                            foreach ( $comments as $comment ) {
                                $app->set_default( $comment );
                                $comment->object_id( $entry->id );
                                $comment->model = $import_model;
                                $comment->status( $comment_status );
                                echo $this->translate( "Creating new comment (from '%s')...", 
                                            htmlspecialchars( $comment->name ) );
                                $comment->text( rtrim( $comment->text ) );
                                if ( $comment->save() ) {
                                    echo $this->translate( 'ok (ID %s)', $comment->id );
                                } else {
                                    echo $this->translate( 'Saving comment failed.' );
                                    $error = true;
                                }
                                echo "<br>\n";
                                flush();
                            }
                            $entry->save();
                        }
                        if (! $error ) {
                            // $app->db->commit();
                            $callback = ['name' => 'post_import',
                                         'format' => 'movabletype'];
                            if ( $entry->status != 4 ) {
                                $app->publish_obj( $entry );
                            }
                            $app->run_callbacks( $callback, $import_model, $entry );
                        // } else {
                            // $app->db->rollback();
                        }
                        // usleep( 50000 );
                        // $app->db->reconnect();
                        $context = '';
                        $entry = $app->db->model( $import_model )->new();
                        $entry->workspace_id( $workspace_id );
                        $categories = [];
                        $tags = [];
                        $comment = $app->db->model( 'comment' )->new();
                        $comment->workspace_id( $workspace_id );
                        $comments = [];
                    } else if ( $buffer == 'BODY:' ) {
                        $context = 'body';
                    } else if ( $buffer == 'EXTENDED BODY:' ) {
                        $context = 'extended';
                    } else if ( $buffer == 'EXCERPT:' ) {
                        $context = 'excerpt';
                    } else if ( $buffer == 'KEYWORDS:' ) {
                        $context = 'keywords';
                    } else if ( $buffer == 'COMMENT:' ) {
                        $context = 'comment';
                    } else if ( $buffer == 'PING:' ) {
                        $context = 'ping';
                    } else if ( strpos( $buffer, 'AUTHOR:') === 0 ) {
                        $author = trim( substr( $buffer, strlen('AUTHOR:')) );
                        if (! $context ) {
                            if ( $author && $author_setting != 1 ) {
                                $user = null;
                                if ( isset( $users[ $author ] ) ) {
                                    $user = $users[ $author ];
                                } else {
                                    $user = $app->db->model( 'user' )
                                        ->load( ['name' => $author ] );
                                    if ( is_array( $user ) && !empty( $user ) ) {
                                        $user = $user[0];
                                        $users[ $author ] = $user;
                                    }
                                }
                                if (! $user ) {
                                    if ( $app->can_do( 'user', 'save' ) ) {
                                        $user = $app->db->model( 'user' )->get_by_key(
                                                                    ['name' => $author ] );
                                        $user->nickname( $user );
                                        $user->password( $password );
                                        $user->status( 2 );
                                        $user->language( $app->language );
                                        $app->set_default( $user );
                                        echo $this->translate( "Creating new user ('%s')...", 
                                                htmlspecialchars( $author ) );
                                        if ( $user->save() ) {
                                            echo $this->translate( 'ok (ID %s)', $user->id );
                                            $users[ $author ] = $user;
                                        } else {
                                            echo $this->translate( 'Saving user failed.' );
                                        }
                                    } else {
                                        echo $this->translate( 'You do not have permission to create users. Import as me.' );
                                        $user = $app->user();
                                    }
                                    echo "<br>\n";
                                    flush();
                                }
                                $entry->user_id( $user->id );
                            }
                        } else if ( $context == 'comment' ) {
                            $comment->name( $author );
                        }
                    } else if (! $context && strpos( $buffer, 'TITLE:' ) === 0 ) {
                        $title = trim( substr( $buffer, strlen( 'TITLE:' ) ) );
                        if (! $context ) {
                            $entry->title( $title );
                        }
                    } else if (! $context && strpos( $buffer, 'BASENAME:' ) === 0 ) {
                        $basename = trim( substr( $buffer, strlen( 'BASENAME:' ) ) );
                        $basename = PTUtil::make_basename( $entry, $basename, true );
                        $entry->basename( $basename );
                    } else if (! $context && strpos( $buffer, 'STATUS:' ) === 0 ) {
                        $status = trim( strtolower( substr( $buffer, strlen( 'STATUS:' ) ) ) );
                        if ( $status && isset( $status_map[ $status ] ) ) {
                            $status = $status_map[ $status ];
                        } else {
                            $status = $default_status;
                        }
                        $entry->status( $status );
                    } else if (! $context && strpos( $buffer, 'ALLOW COMMENTS:' ) === 0 ) {
                        $allow = trim( preg_replace( "/^ALLOW COMMENTS:/", '', $buffer ) );
                        if ( $allow == 1 ) {
                            $entry->allow_comment( 1 );
                        }
                    } else if (! $context && strpos( $buffer, 'CONVERT BREAKS:' ) === 0 ) {
                        $text_format = trim( preg_replace( "/^CONVERT BREAKS:/", '', $buffer ) );
                        if (! $text_format ) {
                            $text_format = '';
                        } else if ( $text_format == '__default__' ) {
                            $text_format = 'convert_breaks';
                        } else {
                            if (! in_array( $text_format, $text_formats ) ) {
                                $text_format = $default_formats;
                            }
                        }
                        $entry->text_format( $text_format );
                    } else if ( strpos( $buffer, 'ALLOW PINGS:' ) === 0 ) {
                    } else if (! $context && strpos( $buffer, 'CATEGORY:') === 0 ) {
                        $category = trim( preg_replace( "/^CATEGORY:/", '', $buffer ) );
                        if (! in_array( $category, $categories ) ) {
                            $categories[] = $category;
                        }
                    } else if (! $context && strpos( $buffer, 'PRIMARY CATEGORY:' ) === 0 ) {
                        $category = trim( preg_replace( "/^PRIMARY CATEGORY:/", '', $buffer ) );
                        array_unshift( $categories, $category );
                    } else if (! $context && strpos( $buffer, 'TAGS:' ) === 0 ) {
                        $tag = trim( preg_replace( "/^TAGS:/", '', $buffer ) );
                        $tags = str_getcsv( $tag, ',', '"' );
                    } else if ( strpos( $buffer, 'DATE:' ) === 0 ) {
                        $date = trim( preg_replace( "/^DATE:/", '', $buffer ) );
                        $date = strtotime( $date );
                        $date = date('Y-m-d H:i:s', $date);
                        if (! $context ) {
                            $entry->published_on( $date );
                            $entry->created_on( $date );
                        } else if ( $context == 'comment' ) {
                            $comment->created_on( $date );
                        } else if ( $context == 'ping' ) {
                        }
                    } else if ( $context == 'comment' && strpos( $buffer, 'EMAIL:' ) === 0 ) {
                        $email = trim( preg_replace( "/^EMAIL:/", '', $buffer ) );
                        $comment->email( $email );
                    } else if ( $context == 'comment' && strpos( $buffer, 'IP:' ) === 0 ) {
                        $ip = trim( preg_replace( "/^IP:/", '', $buffer ) );
                        $comment->remote_ip( $ip );
                    } else if ( $context == 'comment' && strpos( $buffer, 'URL:' ) === 0 ) {
                        $url = trim( preg_replace( "/^URL:/", '', $buffer ) );
                        $comment->url( $url );
                    } else if ( strpos( $buffer, 'BLOG NAME:' ) === 0 ) {
                    } else if ( isset( $field_mappings[ $sep_fields[0] ] ) ) {
                        $_field = preg_quote( $sep_fields[0], '/' );
                        $field_var = preg_replace( "/^{$_field}/", '', $buffer );
                        $to_field = $field_mappings[ $sep_fields[0] ];
                        $type_text = false;
                        $col_type = '';
                        if ( isset( $column_defs[ $to_field ] ) ) {
                            $col_setting = $column_defs[ $to_field ];
                            $col_type = $col_setting['type'];
                            if ( $col_type == 'datetime' ) {
                                $field_var = preg_replace( '/[^0-9]*/', '', $field_var );
                                $field_var = $entry->ts2db( $field_var );
                            } else if ( $col_type == 'text' || $col_type == 'string' ) {
                                $type_text = true;
                            }
                        }
                        if ( $field_var ) {
                            $field_var = trim( $field_var );
                            if ( $entry->has_column( $to_field ) ) {
                                if ( $to_field == 'keywords' ) {
                                    if ( $entry->keywords ) {
                                        $field_var = $entry->keywords . ',' . $field_var;
                                    }
                                    $field_vars = preg_split( '/\s*,\s*/', $field_var );
                                    $field_var = implode( ', ', $field_vars );
                                } elseif ( $type_text ) {
                                    $orig_var = $entry->$to_field;
                                    if ( $col_type == 'text'
                                        && ! preg_match( "/\n$/s", $orig_var ) ) {
                                        $orig_var .= "\n";
                                    }
                                    if ( $col_type == 'text' ) {
                                        $orig_var .= "\n";
                                    }
                                    $field_var = $orig_var . $field_var;
                                }
                                $entry->$to_field = $field_var;
                            }
                        } else {
                            $orig_var = $entry->$to_field;
                            if ( $to_field == 'text' ) {
                                $context = 'body';
                            } else if ( $to_field == 'text_more' ) {
                                $context = 'extended';
                            } else {
                                $context = $to_field;
                            }
                            if ( $to_field == 'keywords' && $entry->keywords ) {
                                $entry->keywords .= ',';
                            } elseif ( $type_text && $entry->$to_field
                                && !preg_match( "/\n$/s", $orig_var ) ) {
                                $entry->$to_field .= "\n";
                            }
                        }
                    } else {
                        if( !empty( $buffer ) )
                            $buffer .= "\n";
                        if ( $context == 'body' ) {
                            $entry->text .= $_buffer;
                        } else if ( $context == 'extended' ) {
                            $entry->text_more .= $_buffer;
                        } else if ( $context == 'excerpt' ) {
                            $entry->excerpt .= $_buffer;
                        } else if ( $context == 'keywords' ) {
                            $entry->keywords .= $_buffer;
                        } else if ( $context == 'comment' ) {
                            $comment->text .= $_buffer;
                        } else if ( $context == 'ping' ) {
                        } else if ( $context && $entry->has_column( $context ) ) {
                            $entry->$context .= $_buffer;
                        }
                    }
                }
                // echo "<script>window.parent.importDone = true;</script>";
                // exit();
                fclose( $handle );
            }
        }
        $app->db->commit();
        $app->txn_active = false;
        $this->end_import( $session, $counter, $obj_label_plural );
    }

    function import_wordpress ( $app, $import_files, $session ) {
        $import_model = $app->param( 'import_model' );
        $set_folder = $app->param( 'wordpress_set_folder' );
        $table = $app->get_table( $import_model );
        $obj_label = $app->translate( $table->label );
        $obj_label_plural = $app->translate( $table->plural );
        $workspace_id = (int) $app->param( 'workspace_id' );
        $workspace = $app->workspace();
        $default_status = $table->default_status ? $table->default_status : 1;
        $author_setting = $app->param( 'wordpress_author_setting' );
        $password = $app->param( 'wordpress_new_author_password' );
        if ( $author_setting != 1 && ! $password ) {
            $error = $this->translate( 'Default password for new users is required.' );
            echo $error;
            exit();
        }
        if ( $password )
            $password = password_hash( $password, PASSWORD_BCRYPT );
        $comment_status = (int) $app->param( 'wordpress_comment_status' );
        if (! $comment_status || $comment_status > 3 ) {
            $comment_status = 1;
        }
        $text_format = $app->param( 'wordpress_text_format' );
        $entry = $app->db->model( $import_model );
        $field_settings = $app->param( 'wordpress_field_settings' );
        $field_mappings = [];
        if ( $field_settings ) {
            $field_settings = preg_replace("/\r\n|\r|\n/", "\n", $field_settings );
            $lines = explode( "\n", $field_settings );
            foreach ( $lines as $line ) {
                $kv = preg_split( "/\s*=\s*/", $line );
                list( $k, $v ) = [ $kv[0], $kv[1] ];
                if ( $k && $v && $entry->has_column( $v ) ) {
                    $field_mappings[ $k ] = $v;
                }
            }
        }
        $users = [];
        $counter = 0;
        $app->db->begin_work();
        $app->txn_active = true;
        foreach ( $import_files as $file ) {
            $xmlstr = file_get_contents( $file );
            $xmlstr = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $xmlstr );
            $xml = new SimpleXMLElement( $xmlstr );
            foreach ( $xml->channel->item as $item ) {
                $error = false;
                $title = (string) $item->title;
                $this->print( $title );
                $post_date = (string) $item->children( 'wp', true )->post_date;
                $entry = $app->db->model( $import_model )->new();
                $entry->text_format( $text_format );
                $basename = (string) $item->children( 'wp', true )->post_name;
                $basename = strtolower( $basename );
                $basename = preg_replace( "/[^a-z0-9\-]/", ' ', $basename );
                $basename = preg_replace( "/\s{1,}/", ' ', $basename );
                $basename = str_replace( ' ', '_', $basename );
                $basename = trim( $basename, '_' );
                $text = (string) $item->children( 'content', true )->encoded;
                $excerpt = (string) $item->children( 'excerpt', true )->encoded;
                $entry->title( $title );
                $entry->text( $text );
                $entry->excerpt( $excerpt );
                $entry->published_on( $post_date );
                $entry->created_on( $post_date );
                $entry->workspace_id( $workspace_id );
                $basename = PTUtil::make_basename( $entry, $basename, true );
                $entry->basename( $basename );
                $author = (string) $item->children( 'dc', true )->creator;
                if ( $author && $author_setting != 1 ) {
                    $user = null;
                    if ( isset( $users[ $author ] ) ) {
                        $user = $users[ $author ];
                    } else {
                        $user = $app->db->model( 'user' )
                            ->load( ['name' => $author ] );
                        if ( is_array( $user ) && !empty( $user ) ) {
                            $user = $user[0];
                            $users[ $author ] = $user;
                        }
                    }
                    if (! $user ) {
                        if ( $app->can_do( 'user', 'save' ) ) {
                            $user = $app->db->model( 'user' )->get_by_key(
                                                        ['name' => $author ] );
                            $user->nickname( $user );
                            $user->password( $password );
                            $user->status( 2 );
                            $user->language( $app->language );
                            $app->set_default( $user );
                            echo $this->translate( "Creating new user ('%s')...", 
                                    htmlspecialchars( $author ) );
                            if ( $user->save() ) {
                                echo $this->translate( 'ok (ID %s)', $user->id );
                                $users[ $author ] = $user;
                            } else {
                                echo $this->translate( 'Saving user failed.' );
                            }
                        } else {
                            echo $this->translate( 'You do not have permission to create users. Import as me.' );
                            $user = $app->user();
                        }
                        echo "<br>\n";
                        flush();
                    }
                    $entry->user_id( $user->id );
                }
                $app->set_default( $entry );
                echo $this->translate( "Saving %s ('%s')...", [
                        $obj_label,
                        htmlspecialchars( $entry->title ) ] );
                if ( $entry->save() ) {
                    echo $this->translate( 'ok (ID %s)', $entry->id );
                    $counter++;
                } else {
                    echo $this->translate( 'Saving %s failed.', $obj_label );
                    $error = true;
                }
                echo "<br>\n";
                flush();
                $_categories = [];
                $basename_map = [];
                foreach ( $item->category as $category ) {
                    $domain = (string) $category['domain'];
                    $value = (string) $category[0];
                    if ( $value == 'Uncategorized' ||
                        $value == $app->translate( 'Uncategorized' ) ) {
                        continue;
                    }
                    if ( $domain == 'category' ) {
                        $nicename = (string) $category['nicename'];
                        $basename_map[ $value ] = $nicename;
                    }
                    if ( isset( $categories[ $domain ] ) ) {
                        $old_vars = $categories[ $domain ];
                        $old_vars[] = $value;
                        $_categories[ $domain ] = $old_vars;
                    } else {
                        $_categories[ $domain ] = [ $value ];
                    }
                }
                if ( isset( $_categories['category'] ) ) {
                    $categories = $_categories['category'];
                    $categories = array_unique( $categories );
                    if ( $import_model == 'entry' ) {
                        $to_ids = [];
                        foreach ( $categories as $cat ) {
                            $category = $app->db->model( 'category' )->get_by_key(
                                                            ['label' => $cat ] );
                            if (! $category->id ) {
                                if ( $app->can_do( 'category', 'create', null, $workspace ) ) {
                                    $category->workspace_id( $workspace_id );
                                    $basename = $basename_map[ $cat ];
                                    $basename = PTUtil::make_basename( $category, $basename, true );
                                    $category->basename( $basename );
                                    echo $this->translate( "Creating new category ('%s')...", 
                                            htmlspecialchars( $cat ) );
                                    $app->set_default( $category );
                                    if ( $category->save() ) {
                                        echo $this->translate( 'ok (ID %s)', $category->id );
                                    } else {
                                        echo $this->translate( 'Saving category failed.' );
                                        $error = true;
                                    }
                                    echo "<br>\n";
                                    flush();
                                }
                            }
                            if ( $category->id )
                                $to_ids[] = (int) $category->id;
                        }
                        if ( count( $to_ids ) ) {
                            $args = ['from_id'  => $entry->id, 
                                     'name'     => 'categories',
                                     'from_obj' => 'entry',
                                     'to_obj'   => 'category'];
                            $app->set_relations( $args, $to_ids );
                        }
                    } else if ( $set_folder ) {
                        $label = $categories[0];
                        $folder = $app->db->model( 'folder' )->get_by_key(
                                                            ['label' => $label ] );
                        if (! $folder->id ) {
                            $folder = $app->db->model( 'folder' )->get_by_key(
                                                                ['basename' => $label ] );
                        }
                        if (! $folder->id && $app->can_do( 'folder', 'create', null, $workspace ) ) {
                            if (! $folder->label ) {
                                $folder->label( $label );
                            }
                            $app->set_default( $folder );
                            echo $this->translate( "Creating new folder ('%s')...", 
                                    htmlspecialchars( $label ) );
                            if ( $folder->save() ) {
                                echo $this->translate( 'ok (ID %s)', $folder->id );
                            } else {
                                echo $this->translate( 'Saving folder failed.' );
                                $error = true;
                            }
                            echo "<br>\n";
                            flush();
                            // echo $this->translate( 'You do not have permission to create Category.' );
                        }
                        if ( $folder->id ) {
                            $entry->folder_id( $folder->id );
                            $entry->save();
                        }
                    }
                }
                if ( isset( $_categories['post_tag'] ) ) {
                    $tags = array_unique( $_categories['post_tag'] );
                    $to_ids = [];
                    foreach ( $tags as $tag ) {
                        if ( function_exists( 'normalizer_normalize' ) ) {
                            $tag = normalizer_normalize( $tag, Normalizer::NFKC );
                        }
                        $normalize = preg_replace( '/\s+/', '',
                                            trim( strtolower( $tag ) ) );
                        if (! $tag ) continue;
                        $terms = ['normalize' => $normalize ];
                        $terms['workspace_id'] = $workspace_id;
                        $tag_obj = $app->db->model( 'tag' )->get_by_key( $terms );
                        if (! $tag_obj->id ) {
                            $tag_obj->name( $tag );
                            $app->set_default( $tag_obj );
                            $order = $app->get_order( $tag_obj );
                            $tag_obj->order( $order );
                            $tag_obj->save();
                        }
                        $to_ids[] = (int) $tag_obj->id;
                    }
                    $args = ['from_id'  => $entry->id, 
                             'name'     => 'tags',
                             'from_obj' => $import_model,
                             'to_obj'   => 'tag'];
                    $app->set_relations( $args, $to_ids );
                }
                $meta_vars = [];
                foreach ( $item->children( 'wp', true )->postmeta as $postmeta ) {
                    $meta_key = (string) $postmeta->children( 'wp', true )->meta_key;
                    $meta_value = (string) $postmeta->children( 'wp', true )->meta_value;
                    if ( isset( $meta_vars[ $meta_key ] ) ) {
                        $old_vars = $meta_vars[ $meta_key ];
                        $old_vars[] = $meta_value;
                        $meta_vars[ $meta_key ] = $old_vars;
                    } else {
                        $meta_vars[ $meta_key ] = [ $meta_value ];
                    }
                }
                $meta_count = 0;
                foreach ( $field_mappings as $old => $new ) {
                    if (! isset ( $meta_vars[ $old ] ) ) {
                        continue;
                    }
                    $vars = $meta_vars[ $old ];
                    if ( is_string( $new ) ) {
                        $value = $vars[0];
                        $value = $entry->$new ? $entry->$new . "\n" . $value : $value;
                        $entry->$new( $value );
                        $meta_count++;
                    }
                }
                $comment_count = 0;
                foreach ( $item->children( 'wp', true )->comment as $_comment ) {
                    $comment = $app->db->model( 'comment' )->new();
                    $comment->workspace_id( $workspace_id );
                    $name = (string) $_comment->children( 'wp', true )->comment_author;
                    $email = (string) $_comment->children( 'wp', true )->comment_author_email;
                    $url = (string) $_comment->children( 'wp', true )->comment_author_url;
                    $remote_ip = (string) $_comment->children( 'wp', true )->comment_author_IP;
                    $created_on = (string) $_comment->children( 'wp', true )->comment_date;
                    $text = (string) $_comment->children( 'wp', true )->comment_content;
                    $comment->name( $name );
                    $comment->email( $email );
                    $comment->url( $url );
                    $comment->text( $text );
                    $comment->status( $comment_status );
                    $comment->created_on( $created_on );
                    $comment->remote_ip( $remote_ip );
                    $comment->object_id( $entry->id );
                    $comment->model = $import_model;
                    $app->set_default( $comment );
                    echo $this->translate( "Creating new comment (from '%s')...", 
                                htmlspecialchars( $comment->name ) );
                    if ( $comment->save() ) {
                        echo $this->translate( 'ok (ID %s)', $comment->id );
                    } else {
                        echo $this->translate( 'Saving comment failed.' );
                        $error = true;
                    }
                    echo "<br>\n";
                    flush();
                    $comment_count++;
                }
                if ( $comment_count || $meta_count ) {
                    $entry->comment_count( $comment_count );
                    $entry->save();
                }
                $callback = ['name' => 'post_import',
                             'meta' => $meta_vars,
                             'categories' => $_categories,
                             'format' => 'wordpress', 'xml' => $item ];
                if ( $entry->status != 4 ) {
                    $app->publish_obj( $entry );
                }
                $app->run_callbacks( $callback, $import_model, $entry );
            }
        }
        $app->db->commit();
        $app->txn_active = false;
        $this->end_import( $session, $counter, $obj_label_plural );
    }

    function export_movabletype ( $app, $objects, $action ) {
        $ctx = $app->ctx;
        $ctx->stash( 'current_context', 'entry' );
        $app->caching = false;
        $app->db->caching = false;
        $ctx->force_compile = true;
        $ctx->caching = false;
        $app->init_tags();
        $tmpl = $this->path() . DS . 'tmpl' . DS . 'movabletype.tmpl';
        $file_name = 'export_movabletype.txt';
        header( "Content-Type: text/plain" );
        header( "Content-Disposition: attachment;"
            . " filename=\"{$file_name}\"" );
        header( 'Pragma: ' );
        foreach ( $objects as $obj ) {
            $id = (int)$obj->id;
            $entry = $app->db->model( 'entry' )->load( $id );
            $ctx->stash( 'entry', $entry );
            echo $ctx->build_page( $tmpl ), "\n";
        }
        exit();
    }

    function print ( $message, $param = '', $component = null ) {
        if (! $component ) $component = Prototype::get_instance();
        if ( is_array( $param ) ) {
            foreach( $param as $key => $value ) {
                $param[ $key ] = htmlspecialchars( $value );
            }
        } else {
            $param = htmlspecialchars( $param );
        }
        $message = $component->translate( $message, $param );
        echo $message, "<br>\n";
        flush();
    }

    function pause ( $app = null ) {
        if (! $app ) $app = Prototype::get_instance();
        echo "<script>window.parent.importDone = true;</script>";
        if ( $app->txn_active ) {
            $app->db->rollback();
            $app->txn_active = false;
        }
        exit();
    }

    function end_import ( $session, $counter, $obj_label_plural = 'Entries' ) {
        $app = Prototype::get_instance();
        echo '<br>';
        $obj_label_plural = $app->translate( $obj_label_plural );
        $message = 'Import %s %s successfully.';
        $message = $this->translate( $message, [ $counter, $obj_label_plural ] );
        echo $message;
        echo "<script>window.parent.importDone = true;</script>";
        $dir = $session->value;
        PTUtil::remove_dir( $dir );
        $session->remove();
        $model = $this->import_model ? $this->import_model : 'entry';
        $import_format = $this->import_format;
        $app->log( ['message'  => $message,
                    'category' => 'import',
                    'model'    => $model,
                    'metadata' => json_encode( ['import_format' => $import_format ] ),
                    'level'    => 'info'] );
    }

    function export_entry ( $app, $objects, $action ) {
        $input = $app->param( 'itemset_action_input' );
        $exporters = $app->registry['export_format'];
        if ( isset( $exporters[ $input ] ) ) {
            $prop = $exporters[ $input ];
            $component = $app->component( $prop['component'] );
            $method = $prop['method'];
            if ( $component && method_exists( $component, $method ) ) {
                return $component->$method( $app, $objects, $action );
            }
        }
        return $app->error( 'Invalid request.' );
    }
}