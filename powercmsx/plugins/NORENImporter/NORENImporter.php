<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );
class NORENImporter extends PTPlugin {

    function __construct () {
        $app = Prototype::get_instance();
        $app->db->caching = false;
        if ( $max_execution_time = $app->max_exec_time ) {
            $max_execution_time = (int) $max_execution_time;
            ini_set( 'max_execution_time', $max_execution_time );
        }
        parent::__construct();
    }

    // function init_migration ( $app, $migrator, &$import_files ) {
    // }

    function import_noren ( $app, $import_files, $session ) {
        $migrator = $app->component( 'DataMigrator' );
        $import_model = $app->param( 'import_model' );
        $set_folder = $app->param( 'noren_set_folder' );
        $table = $app->get_table( $import_model );
        $obj_label = $app->translate( $table->label );
        $obj_label_plural = $app->translate( $table->plural );
        $workspace_id = (int) $app->param( 'workspace_id' );
        $workspace = $app->workspace();
        $author_setting = $app->param( 'noren_author_setting' );
        $password = $app->param( 'noren_new_author_password' );
        if ( $author_setting != 1 && ! $password ) {
            $error = $migrator->translate( 'Default password for new users is required.' );
            echo $error;
            exit();
        }
        if ( $password )
            $password = password_hash( $password, PASSWORD_BCRYPT );
        $entry = $app->db->model( $import_model )->new();
        $categories = [];
        $tags = [];
        $context = '';
        $users = [];
        $counter = 0;
        $scheme = $app->get_scheme_from_db( $import_model );
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
                    $xmlstr = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $xmlstr );
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
                        $useFlg = $useFlg == 'T' ? 4 : 0;
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
        $site_url = $workspace ? $workspace->site_url : $app->site_url;
        $extra_path = $workspace ? $workspace->extra_path : $app->extra_path;
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
        $app->db->begin_work();
        $app->txn_active = true;
        foreach ( $import_files as $file ) {
            $xmlstr = file_get_contents( $file );
            $xmlstr = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $xmlstr );
            $xml = new SimpleXMLElement( $xmlstr );
            foreach ( $xml->content as $content ) {
                // $app->db->begin_work();
                $error = false;
                $entry = $app->db->model( $import_model )->new();
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
                                echo $migrator->translate( "Creating new user ('%s')...", 
                                        htmlspecialchars( $author ) );
                                if ( $user->save() ) {
                                    echo $migrator->translate( 'ok (ID %s)', $user->id );
                                    $users[ $author ] = $user;
                                } else {
                                    echo $migrator->translate( 'Saving user failed.' );
                                }
                            } else {
                                echo $migrator->translate( 'You do not have permission to create users. Import as me.' );
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
                        $entry->$to_field( $field_var );
                    }
                }
                if ( isset( $article_settings[ $artId ] ) ) {
                    $article = $article_settings[ $artId ];
                    $status = $article['status'];
                    if ( is_numeric( $status ) && $status >= 0 && $status < 6 ) {
                        $entry->status( $status );
                    }
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
                echo $migrator->translate( "Saving %s ('%s')...", [
                        $obj_label,
                        htmlspecialchars( $entry->title ) ] );
                if ( $entry->save() ) {
                    echo $migrator->translate( 'ok (ID %s)', $entry->id );
                    $counter++;
                } else {
                    echo $migrator->translate( 'Saving %s failed.', $obj_label );
                    $error = true;
                }
                echo "<br>\n";
                flush();
                if ( $error ) {
                    // $app->db->rollback();
                    // continue;
                } else {
                    if (! empty( $keywords ) && $keywords_to != 1 ) {
                        $to_ids = [];
                        foreach ( $keywords as $tag ) {
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
                    if ( $catId ) {
                        // $cat = preg_replace( "/^c_ns_/", '', $catId ); // c_ns_2008
                        $cat = $catId;
                        if ( $import_model == 'entry' ) {
                            $to_ids = [];
                            $category = $app->db->model( 'category' )->get_by_key(
                                                            ['basename' => $cat ] );
                            if (! $category->id ) {
                                $category = $app->db->model( 'category' )->get_by_key(
                                                            ['label' => $cat ] );
                            }
                            if (! $category->id ) {
                                if ( $app->can_do( 'category', 'create', null, $workspace ) ) {
                                    $category->workspace_id( $workspace_id );
                                    $basename = PTUtil::make_basename( $category, $cat, true );
                                    $category->basename( $basename );
                                    echo $migrator->translate( "Creating new category ('%s')...", 
                                            htmlspecialchars( $cat ) );
                                    $app->set_default( $category );
                                    if ( $category->save() ) {
                                        echo $migrator->translate( 'ok (ID %s)', $category->id );
                                    } else {
                                        echo $migrator->translate( 'Saving category failed.' );
                                        $error = true;
                                    }
                                    echo "<br>\n";
                                    flush();
                                }
                            }
                            if ( $category->id ) {
                                $to_ids[] = (int) $category->id;
                                $args = ['from_id'  => $entry->id, 
                                         'name'     => 'categories',
                                         'from_obj' => 'entry',
                                         'to_obj'   => 'category'];
                                $app->set_relations( $args, $to_ids );
                            }
                        } else if ( $set_folder ) {
                            $label = $cat;
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
                                echo $migrator->translate( "Creating new folder ('%s')...", 
                                        htmlspecialchars( $label ) );
                                if ( $folder->save() ) {
                                    echo $migrator->translate( 'ok (ID %s)', $folder->id );
                                } else {
                                    echo $migrator->translate( 'Saving folder failed.' );
                                    $error = true;
                                }
                                echo "<br>\n";
                                flush();
                                // echo $migrator->translate( 'You do not have permission to create Category.' );
                            }
                            if ( $folder->id ) {
                                $entry->folder_id( $folder->id );
                                $entry->save();
                            }
                        }
                        if ( $entry->status != 4 ) {
                            $app->publish_obj( $entry );
                        }
                        $callback = ['name' => 'post_import',
                                     'articles' => $article_settings,
                                     'format' => 'noren', 'xml' => $content ];
                        $app->run_callbacks( $callback, $import_model, $entry );
                    }
                }
            }
        }
        $app->db->commit();
        $app->txn_active = false;
        $migrator->end_import( $session, $counter, $obj_label_plural );
    }
}