<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );
class EntryImporter extends PTPlugin {

    private $allowed = ['txt', 'xml', 'rss', 'rdf', 'csv', 'zip', 'gzip'];

    function __construct () {
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
                echo str_pad( '', 4096 ) . "\n";
                ob_end_flush();
                echo '<html><body>';
                return $component->$meth( $app, $session );
            }
        } else {
            return $app->error( 'Invalid request.' );
        }
    }

    function upload_import_file ( $app ) {
        $app->db->caching = false;
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

    function import_movabletype ( $app, $session ) {
        $app->db->caching = false;
        $app->db->max_queries = 100;
        $table = $app->get_table( 'entry' );
        $workspace_id = (int) $app->param( 'workspace_id' );
        $default_status = $table->default_status ? $table->default_status : 1;
        $import_files = unserialize( $session->metadata );
        if ( is_string( $import_files ) ) $import_files = [ $import_files ];
        $author_setting = $app->param( 'movabletype_author_setting' );
        $password = $app->param( 'movabletype_new_author_password' );
        if ( $author_setting != 1 && ! $password ) {
            $error = $this->translate( 'Default password for new users is required.' );
            print $error;
            exit();
        }
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
                while ( ( $_buffer = fgets( $handle, 4096 ) ) !== false ) {
                    $buffer = trim( $_buffer );
                    if ( $buffer == '-----' ) {
                        if ( $context == 'comment' ) {
                            $comments[] = $comment;
                            $comment = $app->db->model( 'comment' )->new();
                        }
                        $context = '';
                    } else if ( $buffer == '--------' ) {
                        $app->db->begin_work();
                        $error = false;
                        $app->set_default( $entry );
                        echo $this->translate( "Saving entry ('%s')...", 
                                htmlspecialchars( $entry->title ) );
                        if ( $entry->save() ) {
                            echo $this->translate( 'ok (ID %s)', $entry->id );
                            $counter++;
                        } else {
                            echo $this->translate( 'Saving entry failed.' );
                            $error = true;
                        }
                        echo "<br>\n";
                        flush();
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
                                        $error = true;
                                    }
                                    echo "<br>\n";
                                    flush();
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
                                    $error = true;
                                }
                                echo "<br>\n";
                                flush();
                            }
                            $entry->comment_count( count( $comments ) );
                            $entry->save();
                        }
                        if (! $error ) {
                            $app->db->commit();
                            $callback = ['name' => 'post_import',
                                         'format' => 'movabletype'];
                            $app->run_callbacks( $callback, 'entry', $entry );
                        } else {
                            $app->db->rollback();
                        }
                        // usleep( 50000 );
                        // $app->db->reconnect();
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

    function import_noren ( $app, $session ) {
        $app->db->caching = false;
        $app->db->max_queries = 100;
        $table = $app->get_table( 'entry' );
        $workspace_id = (int) $app->param( 'workspace_id' );
        // $default_status = $table->default_status ? $table->default_status : 1;
        $import_files = unserialize( $session->metadata );
        if ( is_string( $import_files ) ) $import_files = [ $import_files ];
        $author_setting = $app->param( 'noren_author_setting' );
        $password = $app->param( 'noren_new_author_password' );
        if ( $author_setting != 1 && ! $password ) {
            $error = $this->translate( 'Default password for new users is required.' );
            print $error;
            exit();
        }
        if ( $password )
            $password = password_hash( $password, PASSWORD_BCRYPT );
        $entry = $app->db->model( 'entry' )->new();
        $categories = [];
        $tags = [];
        $context = '';
        $users = [];
        $counter = 0;
        $scheme = $app->get_scheme_from_db( 'entry' );
        $column_defs = $scheme['column_defs'];
        $keywords_to = $app->param( 'keywords_to' );
        $field_settings = $app->param( 'noren_field_settings' );
        $text_format = $app->param( 'noren_text_format' );
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
        $article_settings = [];
        if ( count( $import_files ) > 1 ) {
            $new_files = [];
            foreach ( $import_files as $file ) {
                $basename = basename( $file );
                if ( strpos( $basename, 'article' ) === 0 ) {
                    $xmlstr = file_get_contents( $file );
                    $xml = new SimpleXMLElement( $xmlstr );
                    foreach ( $xml->article as $article ) {
                        $attrs = $article->attributes();
                        $articleId = (int) $attrs->artId;
                        $operDay = (string) $attrs->operDay;
                        $expireDay = (string) $attrs->expireDay;
                        if ( $operDay ) {
                            $operDay = preg_replace( '/[^0-9]*/', '', $operDay );
                            $operDay = $entry->ts2db( $operDay );
                        }
                        if ( $expireDay ) {
                            $expireDay = preg_replace( '/[^0-9]*/', '', $expireDay );
                            $expireDay = $entry->ts2db( $expireDay );
                        }
                        $useFlg = (string) $attrs->useFlg;
                        $useFlg = $useFlg == 'T' ? 4 : 1;
                        $settings = ['status' => $useFlg,
                                     'published_on' => $operDay,
                                     'unpublished_on' => $expireDay ];
                        $article_settings[ $articleId ] = $settings;
                    }
                } else {
                    $new_files[] = $file;
                }
            }
            $import_files = $new_files;
        }
        $ws = $app->workspace();
        $site_url = $ws->site_url;
        $extra_path = $ws->extra_path;
        if (! preg_match( "/\/$/", $site_url ) ) {
            $site_url .= '/';
        }
        if ( strpos( $extra_path, '/' ) === 0 ) {
            $extra_path = preg_replace( "/^\//", '', $extra_path );
        }
        $asset_path = $site_url . $extra_path;
        $asset_path = preg_replace( '/https{0,1}:\/\/.*?\//', '/', $asset_path );
        $user_arr = ['regUserId', 'modUserId'];
        require_once( 'class.PTUtil.php' );
        foreach ( $import_files as $file ) {
            $xmlstr = file_get_contents( $file );
            $xml = new SimpleXMLElement( $xmlstr );
            foreach ( $xml->content as $content ) {
                $app->db->begin_work();
                $error = false;
                $entry = $app->db->model( 'entry' )->new();
                $title = (string) $content->artSubject;
                $text = (string) $content->artCont;
                $entry->title( $title );
                if ( preg_match_all( "/<img[^>]*?>/s", $text, $matchs ) ) {
                // <img src="[[--ArtInImage,fileLoc:/2018/05/17/c_ns_2008/20171129-01.jpg--]]"
                // title="" alt="" style="vertical-align: baseline;
                // border: 0px solid rgb(0, 0, 0); width: 300px; height: 200px;" />
                    $images = $matchs[0];
                    foreach ( $images as $image ) {
                        if ( preg_match( '/\ssrc="(.*?)"/', $image, $src ) ) {
                            $src = $src[1];
                            $src = preg_replace( '/^\[\[\-\-.*?:\//', '', $src );
                            $src = preg_replace( '/\-\-\]\]$/', '', $src );
                            $src = $asset_path . $src;
                            $src = " src=\"{$src}\"";
                            $replace = preg_replace( '/\ssrc="(.*?)"/', 
                                $src, $image );
                            $text = str_replace( $image, $replace, $text );
                        }
                    }
                }
                $entry->text( $text );
                $app->set_default( $entry );
                $attrs = $content->attributes();
                $artId = (int) $attrs->artId;
                $mdate = (string) $attrs->modDay;
                $cdate = (string) $attrs->regDay;
                if ( $mdate ) {
                    $mdate = preg_replace( '/[^0-9]*/', '', $mdate );
                    $mdate = $entry->ts2db( $mdate );
                    $entry->modified_on( $mdate );
                }
                if ( $cdate ) {
                    $cdate = preg_replace( '/[^0-9]*/', '', $cdate );
                    $cdate = $entry->ts2db( $cdate );
                    $entry->created_on( $cdate );
                    $entry->published_on( $cdate );
                }
                // regUserId, modUserId, catId,
                $catId = (string) $attrs->catId;
                $user = $app->user();
                foreach ( $user_arr as $user_col ) {
                    $author = (string) $attrs->$user_col;
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
                    }
                    if ( $user_col == 'regUserId' ) {
                        $entry->user_id( $user->id );
                        $entry->created_by( $user->id );
                    } else {
                        $entry->modified_by( $user->id );
                    }
                }
                $keywords = [];
                foreach ( $content->keywords->keyword as $keyword ) {
                    $keyword = (string) $keyword;
                    $keywords[] = $keyword;
                }
                if (! empty( $keywords ) ) {
                    $keywords = array_unique( $keywords );
                    if ( $keywords_to != 2 ) {
                        $entry->keywords( implode( ', ', $keywords ) );
                    }
                }
                foreach ( $content->afields->afield as $afield ) {
                    $field_attrs = $afield->attributes();
                    $field_id = (string)$field_attrs->afieldId;
                    if ( isset( $field_mappings[ $field_id ] ) ) {
                        $to_field = $field_mappings[ $field_id ];
                        $field_var = (string)$afield->value;
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
                        if ( $to_field == 'keywords' ) {
                            if ( $entry->keywords ) {
                                $field_var .= ',' . $field_var;
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
                        $entry->$to_field( $field_var );
                    }
                }
                if ( isset( $article_settings[ $artId ] ) ) {
                    $article = $article_settings[ $artId ];
                    $status = $article['status'];
                    $published_on = $article['published_on'];
                    $unpublished_on = $article['unpublished_on'];
                    if ( $published_on )
                        $entry->published_on( $published_on );
                    if ( $unpublished_on )
                        $entry->unpublished_on( $unpublished_on );
                    // if ( $status == 4 ) {
                    // }
                }
                $entry->text_format( $text_format );
                echo $this->translate( "Saving entry ('%s')...", 
                        htmlspecialchars( $entry->title ) );
                if ( $entry->save() ) {
                    echo $this->translate( 'ok (ID %s)', $entry->id );
                    $counter++;
                } else {
                    echo $this->translate( 'Saving entry failed.' );
                    $error = true;
                }
                echo "<br>\n";
                flush();
                if ( $error ) {
                    $app->db->rollback();
                    continue;
                } else {
                    if (! empty( $keywords ) && $keywords_to != 1 ) {
                        $to_ids = [];
                        foreach ( $keywords as $tag ) {
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
                    if ( $catId ) {
                        // $cat = preg_replace( "/^c_ns_/", '', $catId ); // c_ns_2008
                        $cat = $catId;
                        $to_ids = [];
                        $category = $app->db->model( 'category' )->get_by_key(
                                                        ['basename' => $cat ] );
                        if (! $category->id ) {
                            $category = $app->db->model( 'category' )->get_by_key(
                                                        ['label' => $cat ] );
                        }
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
                                $error = true;
                            }
                            echo "<br>\n";
                            flush();
                        }
                        $to_ids[] = (int) $category->id;
                        $args = ['from_id'  => $entry->id, 
                                 'name'     => 'categories',
                                 'from_obj' => 'entry',
                                 'to_obj'   => 'category'];
                        $app->set_relations( $args, $to_ids );
                        $app->db->commit();
                        $callback = ['name' => 'post_import',
                                     'articles' => $article_settings,
                                     'format' => 'noren', 'xml' => $xml ];
                        $app->run_callbacks( $callback, 'entry', $entry );
                    }
                }
            }
        }
        $dir = $session->value;
        PTUtil::remove_dir( $dir );
        $session->remove();
        echo '<br>';
        echo $this->translate( 'Import %s entries successfully.', $counter );
        $this->scrollBottom();
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
            echo $ctx->build_page( $tmpl );
        }
        exit();
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