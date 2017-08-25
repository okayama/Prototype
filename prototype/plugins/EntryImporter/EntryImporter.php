<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );
class EntryImporter extends PTPlugin {

    private $allowed = ['txt', 'xml', 'rss', 'rdf', 'csv', 'zip', 'gzip'];

    function __construct () {
        parent::__construct();
    }

    function start_import ( $app ) {
        $tmpl = dirname( $this->path ) . DS . 'tmpl' . DS . 'import.tmpl';
        $importers = $app->registry['import_format'];
        require_once( 'class.PTUtil.php' );
        PTUtil::sort_by_order( $importers );
        $importer_loop = [];
        foreach ( $importers as $importer ) {
            $component = $app->component( $importer['component'] );
            $importer['label'] = $app->translate( $importer['label'], null, $component );
            $importer_loop[] = $importer;
        }
        $ctx = $app->ctx;
        $ctx->vars['importer_loop'] = $importer_loop;
        return $app->build_page( $tmpl );
    }

    function import_entry ( $app ) {
        $app->validate_magic();
        $tmpl = dirname( $this->path ) . DS . 'tmpl' . DS . 'import.tmpl';
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
        $component = $app->component( $importer['component'] );
        $meth = $importer['method'];
        if ( $component && method_exists( $component, $meth ) ) {
            if ( $app->param( 'do_import' ) ) {
                $upload_dir = $app->upload_dir();
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
                echo '<html><body>';
                return $component->$meth( $app, $session );
            }
        } else {
            return $app->error( 'Invalid request.' );
        }
    }

    function upload_import_file ( $app ) {
        $app->validate_magic( true );
        if (! $app->can_do( 'entry', 'create' ) ) {
            $error = $app->translate( 'Permission denied.' );
            header( 'Content-type: application/json' );
            echo json_encode( ['message'=> $error ] );
            exit();
        }
        $magic = $app->magic();
        $upload_dir = $app->upload_dir();
        $options = ['upload_dir' => $upload_dir . DS, 'prototype' => $app,
                    'magic' => $magic ];
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

    function import_movabletype ( $app, $session ) {
        $table = $app->get_table( 'entry' );
        $workspace_id = (int) $app->param( 'workspace_id' );
        $default_status = $table->default_status ? $table->default_status : 1;
        $import_files = unserialize( $session->metadata );
        if ( is_string( $import_files ) ) $import_files = [ $import_files ];
        $author_setting = $app->param( 'movabletype_author_setting' );
        $password = $app->param( 'movabletype_new_author_password' );
        if ( $password )
            $password = password_hash( $password, PASSWORD_BCRYPT );
        $comment_status = (int) $app->param( 'movabletype_comment_status' );
        if (! $comment_status || $comment_status > 3 ) {
            $comment_status = (int) $table->default_status;
        }
        $status_map = ['publish' => 4, 'draft' => 1, 'future' => 3,
                       'review'  => 2, 'unpublish' => 5];
        $text_formats = ['markdown', 'markdown_with_smartypants', 'richtext', 'textile_2'];
        $default_formats = 'richtext';
        $entry = $app->db->model( 'entry' )->new();
        $comment = $app->db->model( 'comment' )->new();
        $comments = [];
        $categories = [];
        $tags = [];
        $context = '';
        $users = [];
        $counter = 0;
        require_once( 'class.PTUtil.php' );
        foreach ( $import_files as $file ) {
            $handle = @fopen( $file, "r" );
            if ( $handle ) {
                while ( ( $buffer = fgets( $handle, 4096 ) ) !== false ) {
                    $buffer = trim( $buffer );
                    if ( $buffer == '-----' ) {
                        if ( $context == 'comment' ) {
                            $comments[] = $comment;
                            $comment = $app->db->model( 'comment' )->new();
                        }
                        $context = '';
                    } else if ( $buffer == '--------' ) {
                        $app->set_default( $entry );
                        echo $this->translate( "Saving entry ('%s')...", 
                                htmlspecialchars( $entry->title ) );
                        if ( $entry->save() ) {
                            echo $this->translate( 'ok (ID %s)', $entry->id );
                            $counter++;
                        } else {
                            echo $this->translate( 'Saving entry failed.' );
                        }
                        echo "<br>\n";
                        if (! empty( $categories ) ) {
                            $categories = array_unique( $categories );
                            $to_ids = [];
                            foreach ( $categories as $cat ) {
                                $category = $app->db->model( 'category' )->get_by_key(
                                                                ['label' => $cat ] );
                                if (! $category->id ) {
                                    $basename = PTUtil::make_basename( $category, $cat, true );
                                    $category->basename( $basename );
                                    echo $this->translate( "Creating new category ('%s')...", 
                                            htmlspecialchars( $cat ) );
                                    $app->set_default( $category );
                                    if ( $category->save() ) {
                                        echo $this->translate( 'ok (ID %s)', $category->id );
                                    } else {
                                        echo $this->translate( 'Saving category failed.' );
                                    }
                                    echo "<br>\n";
                                }
                                $to_ids[] = (int) $category->id;
                            }
                            $args = ['from_id'  => $entry->id, 
                                     'name'     => 'categories',
                                     'from_obj' => 'entry',
                                     'to_obj'   => 'category'];
                            $app->set_relations( $args, $to_ids );
                        }
                        if (! empty( $tags ) ) {
                            $tags = array_unique( $tags );
                            $to_ids = [];
                            foreach ( $tags as $tag ) {
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
                                     'from_obj' => 'entry',
                                     'to_obj'   => 'tag'];
                            $app->set_relations( $args, $to_ids );
                        }
                        if (! empty( $comments ) ) {
                            foreach ( $comments as $comment ) {
                                $app->set_default( $comment );
                                $comment->object_id( $entry->id );
                                $comment->model = 'entry';
                                echo $this->translate( "Creating new comment (from '%s')...", 
                                            htmlspecialchars( $comment->name ) );
                                if ( $comment->save() ) {
                                    echo $this->translate( 'ok (ID %s)', $comment->id );
                                } else {
                                    echo $this->translate( 'Saving comment failed.' );
                                }
                                echo "<br>\n";
                            }
                            $entry->comment_count( count( $comments ) );
                            $entry->save();
                        }
                        $context = '';
                        $entry = $app->db->model( 'entry' )->new();
                        $categories = [];
                        $tags = [];
                        $comment = $app->db->model( 'comment' )->new();
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
                    } else {
                        if( !empty( $buffer ) )
                            $buffer .= "\n";
                        if ( $context == 'body' ) {
                            $entry->text .= $buffer;
                        } else if ( $context == 'extended' ) {
                            $entry->text_more .= $buffer;
                        } else if ( $context == 'excerpt' ) {
                            $entry->excerpt .= $buffer;
                        } else if ( $context == 'keywords' ) {
                            $entry->keywords .= $buffer;
                        } else if ( $context == 'comment' ) {
                            $comment->text .= $buffer;
                        } else if ( $context == 'ping' ) {
                        }
                    }
                }
                fclose( $handle );
            }
        }
        $dir = $session->value;
        PTUtil::remove_dir( $dir );
        $session->remove();
        echo '<br>';
        echo $this->translate( 'Import %s entries successfully.', $counter );
        $this->scrollBottom();
    }

}