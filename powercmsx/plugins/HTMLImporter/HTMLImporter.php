<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );
class HTMLImporter extends PTPlugin {

    public $migrator;
    public $session;

    function __construct () {
        $app = Prototype::get_instance();
        $app->db->caching = false;
        parent::__construct();
    }

    function htmlimporter_post_init ( $app ) {
        if ( $app->mode != 'data_migration' && $app->mode != 'start_migration' ) {
            return;
        }
        $workspace = $app->workspace() ? $app->workspace() : null;
        $models = $app->db->model( 'table' )->load( ['im_export' => 1, 'menu_type' => ['IN' => [1,2,6] ] ] );
        $entry_settings = ['primary' => $app->translate( 'Title' ), 'body' => $app->translate( 'Body' ), 'keywords' => true,
                           'taggable' => true, 'text_format' => true, 'excerpt' => true, 'has_assets' => true ];
        $models_mapping = [ 'entry' => $entry_settings, 'page' => $entry_settings ];
        foreach ( $models as $table ) {
            $app->registry['import_format']['html']['models'][] = $table->name;
            if ( $app->mode == 'data_migration' ) {
                continue;
            }
            $primary = $table->primary;
            $primary_col = $app->db->model( 'column' )->get_by_key( ['table_id' => $table->id, 'name' => $primary ] );
            $models_map = ['primary' => $app->translate( $app->escape( $primary_col->label ) ) ];
            $body_col = $app->db->model( 'column' )->get_by_key( ['table_id' => $table->id, 'type' => 'text'],
                                                          ['sort' => 'order', 'direction' => 'ascend'] );
            if (! $body_col->id ) {
                $column = $app->db->model( 'column' )->get_by_key( ['table_id' => $table->id, 'type' => 'string', 'name' => ['!=' => $table->primary ] ],
                                                              ['sort' => 'order', 'direction' => 'ascend'] );
            }
            $body = '';
            if ( $body_col->id ) {
                $body = $app->translate( $app->escape( $body_col->label ) );
            }
            $models_map['body'] = $body;
            $keywords = $app->db->model( 'column' )->get_by_key( ['table_id' => $table->id, 'name' => 'keywords', 'type' => ['IN' => ['string', 'text'] ] ] );
            if ( $keywords->id ) {
                $models_map['keywords'] = true;
            } else {
                $models_map['keywords'] = false;
            }
            if ( $table->taggable ) {
                $models_map['taggable'] = true;
            } else {
                $models_map['taggable'] = false;
            }
            $text_format = $app->db->model( 'column' )->get_by_key( ['table_id' => $table->id, 'name' => 'text_format'] );
            if ( $text_format->id ) {
                $models_map['text_format'] = true;
            } else {
                $models_map['text_format'] = false;
            }
            $excerpt = $app->db->model( 'column' )->get_by_key( ['table_id' => $table->id, 'name' => 'excerpt', 'type' => ['IN' => ['string', 'text'] ] ] );
            if ( $excerpt->id ) {
                $models_map['excerpt'] = 'excerpt';
            } else {
                $excerpt = $app->db->model( 'column' )->get_by_key( ['table_id' => $table->id, 'name' => 'description', 'type' => ['IN' => ['string', 'text'] ] ] );
                if ( $excerpt->id ) {
                    $models_map['excerpt'] = 'description';
                } else {
                    $models_map['excerpt'] = false;
                }
            }
            $models_map['has_assets'] = $table->has_assets ? true : false;
            $models_mapping[ $table->name ] = $models_map;
        }
        if ( $app->can_do( 'asset', 'create', null, $workspace ) ) {
            $app->ctx->vars['html_can_asset'] = 1;
        }
        $app->ctx->vars['html_models_mapping'] = json_encode( $models_mapping );
    }

    function pre_url_get ( &$cb, $app ) {
        // url, http_option, table, model,
        $http_option = $cb['http_option'];
        $http_option[] = 'User-Agent: Mozilla/5.0';
        $cb['http_option'] = $http_option;
        return true;
    }

    function html_importer_send_urls ( $app ) {
        $app->validate_magic();
        $workspace = $app->workspace() ? $app->workspace() : null;
        $import_model = $app->param( 'import_model' );
        if (! $app->can_do( $import_model, 'create', null, $workspace ) ) {
            return $app->print( json_encode( [
                'status' => 500,
                'message' => $app->translate( 'Permission denied.' ) ] ), 'application/json' );
        }
        $urls = $app->param( 'urls' );
        $urls = trim( $urls );
        $urls = preg_replace( "/\r\n?/", "\n/", $urls );
        if (! $urls ) {
            return $app->print( json_encode( [
                'status' => 500,
                'message' => $this->translate( 'URLs is empty.' ) ] ), 'application/json' );
        }
        $curls = explode( "\n", $urls );
        $counter = 0;
        $errors = 0;
        $msg;
        $valid_urls = [];
        foreach ( $curls as $url ) {
            if ( !$app->is_valid_url( $url, $msg ) ) {
                $errors++;
            } else {
                $counter++;
                $valid_urls[] = $url;
            }
        }
        if (! $counter ) {
            $msg = $app->translate( 'Please enter a valid URL.' );
            return $app->print( json_encode( [
                'status' => 500,
                'message' => $msg ] ), 'application/json' );
        }
        $magic = $app->magic();
        $session = $app->db->model( 'session' )->new( ['name' => $magic, 'kind' => 'UP' ] );
        $session->user_id( $app->user()->id );
        $meta = ['file_size' => strlen($urls), 'extension' => 'text', 'class' => 'file',
                 'basename' => 'url_list', 'mime_type' => 'text/plain',
                 'image_width' => null, 'image_height' => null, 'file_name' => 'url_list.txt' ];
        $meta['uploaded'] = date( 'Y-m-d H:i:s' );
        $meta['user_id'] = $app->user()->id;
        $session->text( json_encode( $meta ) );
        $session->data( implode( "\n", $valid_urls ) );
        $session->start( time() );
        $session->expires( time() + $app->sess_timeout );
        if ( $app->workspace() ) {
            $session->workspace_id( $app->workspace()->id );
        }
        $session->save();
        if ( $errors ) {
            if ( $errors == 1 ) {
                $msg = $this->translate( '%s URLs have been registered( %s URL is invalid. ).', [ $counter, $errors ] );
            } else {
                $msg = $this->translate( '%s URLs have been registered( %s URLs are invalid. ).', [ $counter, $errors ] );
            }
        } else {
            $msg = $this->translate( '%s URLs have been registered.', $counter );
        }
        return $app->print( json_encode( [
            'status' => 200,
            'session_id' => $magic,
            'message' => $msg ] ), 'application/json' );
    }

    function import_html ( $app, $import_files, $session ) {
        $migrator = $app->component( 'DataMigrator' );
        require_once( 'extlib' . DS . 'ExtractContent.php' );
        require_once( 'extlib' . DS . 'outputfilter.trimwhitespace.php' );
        // TODO:: Convert href to root relative path.
        // TODO:: Set datetime from server timestamp.
        // TODO:: Topic Path or breadcrumb.
        ini_set( 'max_execution_time', 0 );
        $this->migrator = $migrator;
        $this->session = $session;
        $counter = 0;
        $import_type = $app->param( 'html_import_type' );
        $workspace = $app->workspace() ? $app->workspace() : null;
        $workspace_id = $workspace ? (int) $workspace->id : 0;
        $site_url = $workspace ? $workspace->site_url : $app->site_url;
        $site_path = preg_replace( '!^https{0,1}://.*?/!', '', $site_url );
        $site_path = rtrim( $site_path, '/' );
        $model = $app->param( 'import_model' );
        $model_type = $model == 'entry' || $model == 'page' ? 'entry' : 'other';
        $table = $app->get_table( $model );
        $scheme = $app->get_scheme_from_db( $model );
        $column_defs = $scheme['column_defs'];
        $edit_properties = $scheme['edit_properties'];
        $obj_label_plural = $app->translate( $table->plural );
        $urls = [];
        $asset_paths = [];
        $insert_asset_paths = [];
        $import_base = $session->value;
        $denied_exts = $app->denied_exts;
        $denied_exts = preg_split( '/\s*,\s*/', $denied_exts );
        $asset_exts = $app->param( 'html_asset_exts' );
        $asset_extensions = [];
        if ( $asset_exts ) {
            $asset_extensions = preg_split( '/\s*,\s*/', $asset_exts );
        }
        $images = $app->images;
        if ( $import_type == 'url' ) {
            $urls = file_get_contents( $import_files[0] );
            $urls = explode( "\n", $urls );
        } else {
            foreach ( $import_files as $import_file ) {
                $mime_type = PTUtil::get_mime_type( $import_file );
                if ( $mime_type === 'text/html' ) {
                    $urls[] = $import_file;
                } else {
                    $ext = preg_replace("/^.*\.([^\.].*$)/is", '$1', basename( $import_file ) );
                    if (! $ext || in_array( $ext, $denied_exts ) ) {
                    } else if ( in_array( $ext, $asset_extensions ) || in_array( $ext, $images ) ) {
                        $asset_paths[] = $import_file;
                        $insert_asset_paths[ $import_file ] = true;
                    }
                }
            }
            usort( $asset_paths, function( $a, $b ) {
            return strlen( $a ) - strlen( $b );
            });
        }
        $auth_user = $app->param( 'html_auth_user' );
        $auth_pwd = $app->param( 'html_auth_pwd' );
        $http_option = [];
        if ( $auth_user && $auth_pwd ) {
            $http_option[] = 'Authorization: Basic ' . base64_encode( "{$auth_user}:{$auth_pwd}" );
        }
        $title_perttern = $app->param( 'html_title_perttern' ) ? $app->param( 'html_title_perttern' ) : 'heading';
        $title_option = $app->param( 'html_title_option' ) ? $app->param( 'html_title_option' ) : '';
        $body_perttern = $app->param( 'html_body_perttern' ) ? $app->param( 'html_body_perttern' ) : '';
        $body_option = $app->param( 'html_body_option' ) ? $app->param( 'html_body_option' ) : '';
        $overwrite_same = $app->param( 'html_overwrite_same' );
        $field_settings = $app->param( 'html_field_settings' );
        $field_settings = preg_replace( "/\r\n?/", "\n", $field_settings );
        $field_settings = $field_settings ? explode( "\n", $field_settings ) : [];
        $import_assets  = $app->param( 'html_import_assets' );
        $meta_ogimage   = $app->param( 'html_meta_ogimage' );
        $minifying_html   = $app->param( 'html_minifying_html' );
        if ( $import_assets || $meta_ogimage ) {
            if (! $app->can_do( 'asset', 'create', null, $workspace ) ) {
                return $app->error( $this->translate( 'You do not have permission to import assets.' ) );
            }
        }
        $text_format = $app->param( 'html_text_format' );
        libxml_use_internal_errors( true );
        $start = '';
        $end = '';
        $body_start = '';
        $body_end = '';
        $asset_table = $app->get_table( 'asset' );
        $rel_column = $app->db->model( 'column' )->get_by_key(
            ['table_id' => $asset_table->id, 'name' => 'file' ] );
        $asset_extra = $rel_column->extra;
        if ( $title_perttern == 'title' ) {
            preg_quote( $title_option, '/' );
        } else if ( $title_perttern == 'start_end' ) {
            if (! $title_option || strpos( $title_option, ',' ) === false ) {
                $this->print_error(
                    $this->translate( 'The start and end part of %s are not specified.',
                    $app->translate( 'Title' ) ) );
            }
            list( $start, $end ) = explode( ',', $title_option );
            $start = preg_quote( $start, '/' );
            $end = preg_quote( $end, '/' );
        } else if ( $title_perttern == 'regex' ) {
            $title_option = $this->sanitize_regex( $title_option );
        }
        if ( $body_perttern == 'start_end' ) {
            if (! $body_option || strpos( $body_option, ',' ) === false ) {
                $this->print_error(
                    $this->translate( 'The start and end part of %s are not specified.',
                    $app->translate( 'Body' ) ) );
            }
            list( $body_start, $body_end ) = explode( ',', $body_option );
            $body_start = preg_quote( $body_start, '/' );
            $body_end = preg_quote( $body_end, '/' );
        } else if ( $body_perttern == 'regex' ) {
            $body_option = $this->sanitize_regex( $body_option );
        }
        $title_col = $model_type == 'entry' ? 'title' : '';
        $body_col = $model_type == 'entry' ? 'text' : '';
        if ( $model_type != 'entry' ) {
            $title_col = $table->primary;
            $column = $app->db->model( 'column' )->get_by_key( ['table_id' => $table->id, 'type' => 'text'],
                                                          ['sort' => 'order', 'direction' => 'ascend'] );
            if (! $column->id ) {
                $column = $app->db->model( 'column' )->get_by_key( ['table_id' => $table->id, 'type' => 'string', 'name' => ['!=' => $table->primary ] ],
                                                              ['sort' => 'order', 'direction' => 'ascend'] );
            }
            if ( $column->id ) {
                $body_col = $column->name;
            }
        }
        $asset_extra_path = $workspace ? $workspace->extra_path : $app->extra_path;
        $app->init_callbacks( 'htmlimporter', 'pre_url_get' );
        foreach ( $urls as $url ) {
            $callback = ['name' => 'pre_url_get', 'model' => $model, 'url' => $url,
                         'http_option' => $http_option, 'table' => $table ];
            $app->run_callbacks( $callback, 'htmlimporter' );
            $http_option = $callback['http_option'];
            $url = $callback['url'];
            $model = $callback['model'];
            $table = $callback['table'];
            $http_option = ['http' => [ 'header' => implode("\r\n", $http_option ) ] ];
            $http_option = stream_context_create( $http_option );
            $data = file_get_contents( $url, false, $http_option );
            if ( $data === false ) {
                $migrator->print( $this->translate( "An error occurred while fetch URL '%s'.", $url ) );
                continue;
            }
            if ( preg_match( '!/$!', $url ) ) {
                $url .= 'index.html';
            }
            $dom = new DomDocument();
            try {
                $data = mb_convert_encoding( $data, 'utf-8', [
                    'UTF-7',
                    'ISO-2022-JP',
                    'UTF-8',
                    'SJIS',
                    'JIS',
                    'eucjp-win',
                    'sjis-win',
                    'EUC-JP',
                    'ASCII',
                ] );
            } catch ( \Throwable $e ) {
                $data = mb_convert_encoding( $data, 'utf-8', 'auto' );
            }
            if (!$dom->loadHTML( mb_convert_encoding( $data, 'HTML-ENTITIES', 'utf-8' ) ) ) {
                $migrator->print( $this->translate( "An error occurred while parsing data from '%s'.", $url ) );
                continue;
            }
            $meta_url = $url;
            if ( $import_type != 'url' ) {
                $search = preg_quote( $import_base, '/' );
                $meta_url = preg_replace( "/^$search/", '', $meta_url );
            }
            $is_new = true;
            $entry = $app->db->model( $model )->new();
            $meta = null;
            if ( $overwrite_same ) {
                $metaObjs = $app->db->model( 'meta' )->load( ['model' => $model,
                                                    'text' => $meta_url, 'kind' => 'import_url',
                                                    'number' => $workspace_id ], ['sort' => 'object_id',
                                                                                  'direction' => 'ascend'] );
                foreach ( $metaObjs as $meta ) {
                    $entry = $app->db->model( $model )->load( (int) $meta->object_id );
                    
                    if ( $table->revisable ) {
                        if ( $entry && ! $entry->rev_type ) {
                            $is_new = false;
                            break;
                        }
                    }
                }
            }
            if (! $meta ) {
                $meta = $app->db->model( 'meta' )->get_by_key( ['model' => $model,
                                                    'text' => $meta_url, 'kind' => 'import_url',
                                                    'number' => $workspace_id ]);
            }
            $original = null;
            if ( $entry->id && $table->revisable ) {
                $original = clone $entry;
                $original->id( null );
                $orig_relations = $app->get_relations( $entry );
                $orig_metadata  = $app->get_meta( $entry );
                $original->_relations = $orig_relations;
                $original->_meta = $orig_metadata;
            }
            $entry->workspace_id( $workspace_id );
            $finder = new DomXPath( $dom );
            $title = '';
            $description = '';
            $keywords = '';
            $body = '';
            $og_image = '';
            $attachment_labels = [];
            $attachment_basenames = [];
            if ( $title_perttern == 'heading' ) {
                $arr = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
                foreach ( $arr as $tag ) {
                    if (! $title ) {
                        $elements = $dom->getElementsByTagName( $tag );
                        if ( $elements->length ) {
                            for ( $i = 0; $i < $elements->length; $i++ ) {
                                $ele = $elements->item( $i );
                                $title = trim( $ele->textContent );
                                $children = $ele->childNodes;
                                if (! $title && $children->length ) {
                                    $title = $this->get_title_from_node( $ele->childNodes, $title );
                                }
                                if ( $title ) break;
                            }
                        }
                        if ( $title ) break;
                    }
                }
            } else if ( $title_perttern == 'title' ) {
                $elements = $dom->getElementsByTagName( 'title' );
                if ( $elements->length ) {
                    $ele = $elements->item(0);
                    $title = $ele->nodeValue;
                    if ( $title_option ) {
                        $title = preg_replace( "/^(.*?){$title_option}.*$/", '$1', $title );
                    }
                }
            } else if ( $title_perttern == 'start_end' ) {
                preg_match( "/{$start}(.*?){$end}/si",  $data, $matches );
                $title = isset( $matches[1] ) ? $matches[1] : '';
            } else if ( $title_perttern == 'regex' ) {
                preg_match( "$title_option",  $data, $matches );
                $title = isset( $matches[1] ) ? $matches[1] : '';
            } else if ( $title_perttern == 'xpath' ) {
                $elements = $finder->query( $title_option );
                if ( $elements->length ) {
                    if ( $elements->length ) {
                        $ele = $elements->item(0);
                        $title = $ele->textContent;
                    }
                }
            }
            if (! $title ) {
                $elements = $dom->getElementsByTagName( 'title' );
                if ( $elements->length ) {
                    $ele = $elements->item(0);
                    $title = $ele->nodeValue;
                }
            }
            $title = str_replace( ["\r", "\n"], '', $title );
            $meta_elements = $dom->getElementsByTagName( 'meta' );
            if ( $app->param( 'html_meta_description' ) ) {
                if ( $meta_elements->length ) {
                    $i = $meta_elements->length - 1;
                    for ( $i = 0; $i < $meta_elements->length; $i++ ) {
                        $ele = $meta_elements->item( $i );
                        $name = strtolower( $ele->getAttribute( 'name' ) );
                        if ( $name && $name == 'description' ) {
                            $description = $ele->getAttribute( 'content' );
                            break;
                        }
                    }
                }
            }
            if ( $app->param( 'html_meta_keywords' ) || $app->param( 'html_meta_tags' ) ) {
                if ( $meta_elements->length ) {
                    for ( $i = 0; $i < $meta_elements->length; $i++ ) {
                        $ele = $meta_elements->item( $i );
                        $name = strtolower( $ele->getAttribute( 'name' ) );
                        if ( $name && $name == 'keywords' ) {
                            $keywords = trim( $ele->getAttribute( 'content' ) );
                            break;
                        }
                    }
                }
            }
            if ( $meta_ogimage ) {
                if ( $meta_elements->length ) {
                    for ( $i = 0; $i < $meta_elements->length; $i++ ) {
                        $ele = $meta_elements->item( $i );
                        $property = strtolower( $ele->getAttribute( 'property' ) );
                        if ( $property && $property == 'og:image' ) {
                            $og_image = trim( $ele->getAttribute( 'content' ) );
                            break;
                        }
                    }
                }
            }
            if ( $body_perttern == 'start_end' ) {
                preg_match( "/{$body_start}(.*?){$body_end}/si",  $data, $matches );
                $body = isset( $matches[1] ) ? $matches[1] : '';
            } else if ( $body_perttern == 'regex' ) {
                preg_match( "$body_option",  $data, $matches );
                $body = isset( $matches[1] ) ? $matches[1] : '';
            } else if ( $body_perttern == 'xpath' ) {
                $elements = $finder->query( $body_option );
                if ( $elements->length ) {
                    for ( $i = 0; $i < $elements->length; $i++ ) {
                        $ele = $elements->item( $i );
                        $body .= $this->innerHTML( $ele );
                    }
                }
            } else if ( $body_perttern == 'auto' ) {
                $extractor = new ExtractContent( $data );
                $extractor->dom = $dom;
                $content = $extractor->get_main();
                $body = $content;
            }
            $field_values = [ $title_col => $title ];
            if ( $body_col ) {
                $field_values[ $body_col ] = $body;
            }
            if ( $entry->has_column( 'keywords' ) ) {
                $field_values['keywords'] = $keywords;
            }
            if ( $entry->has_column( 'excerpt' ) ) {
                $field_values['excerpt'] = $description;
            } else if ( $entry->has_column( 'description' ) ) {
                $field_values['description'] = $description;
            }
            $base = dirname( $url ) . '/';
            foreach ( $field_settings as $field_setting ) {
                $parts = explode( '=', $field_setting );
                $column_name = array_shift( $parts );
                $condition = implode( '=', $parts );
                if (! $condition ) continue;
                $perttern = '';
                if ( strpos( $condition, '!' ) === 0 ) {
                    $perttern = 'regex';
                    $condition = $this->sanitize_regex( $condition );
                } else if ( strpos( $condition, '/' ) === 0 ) {
                    $perttern = 'xpath';
                } else if ( strpos( $condition, ',' ) !== false ) {
                    $perttern = 'start_end';
                }
                if (! $perttern ) continue;
                if ( $entry->has_column( $column_name ) ) {
                    $col_type = $column_defs[ $column_name ]['type'];
                    $edit_prop = isset( $edit_properties[ $column_name ] ) ? $edit_properties[ $column_name ] : '';
                    $value = '';
                    if ( $perttern == 'start_end' ) {
                        list( $colStart, $colEnd ) = explode( ',', $condition );
                        $colStart = preg_quote( $colStart, '/' );
                        $colEnd = preg_quote( $colEnd, '/' );
                        preg_match( "/{$colStart}(.*?){$colEnd}/si", $data, $matches );
                        $value = isset( $matches[1] ) ? $matches[1] : '';
                    } else if ( $perttern == 'regex' ) {
                        preg_match( "$condition",  $data, $matches );
                        $value = isset( $matches[1] ) ? $matches[1] : '';
                    } else if ( $perttern == 'xpath' ) {
                        $elements = $finder->query( $condition );
                        if ( $elements->length ) {
                            $i = $elements->length - 1;
                            for ( $i = 0; $i < $elements->length; $i++ ) {
                                $ele = $elements->item( $i );
                                if ( $ele->tagName == 'meta' || $ele->tagName == 'img' || $ele->tagName == 'a' ) {
                                    if ( $col_type == 'blob' ) {
                                        $path = '';
                                        if ( $ele->tagName == 'meta' ) {
                                            $path = trim( $ele->getAttribute( 'content' ) );
                                        } else if ( $ele->tagName == 'img' ) {
                                            $path = trim( $ele->getAttribute( 'src' ) );
                                        } else if ( $ele->tagName == 'a' ) {
                                            $path = trim( $ele->getAttribute( 'href' ) );
                                        }
                                        if ( strpos( $path, '?' ) !== false ) {
                                            list( $path, $_param ) = explode( '?', $path );
                                        }
                                        $attach_label = '';
                                        if ( $ele->tagName == 'img' || $ele->tagName == 'a' ) {
                                            if ( $import_type == 'url' ) {
                                                $path = $this->convert2abs( $path, $base );
                                            } else {
                                                if ( strpos( $path, '.' ) === 0 ) {
                                                    $path = dirname( $url ) . DS . $path;
                                                } else if ( strpos( $path, '/' ) === 0 ) {
                                                    $search = preg_quote( $path, '/' );
                                                    foreach ( $asset_paths as $file_path ) {
                                                        if ( preg_match( "/{$search}$/", $file_path ) ) {
                                                            $path = $file_path;
                                                            break;
                                                        }
                                                    }
                                                }
                                            }
                                            if ( $ele->tagName == 'img' ) {
                                                $attach_label = trim( $ele->getAttribute( 'alt' ) );
                                            } else if ( $ele->tagName == 'a' ) {
                                                $attach_label = trim( strip_tags( $this->innerHTML( $ele ) ) );
                                            }
                                        }
                                        $attach_label = $attach_label ? $attach_label : basename( $path );
                                        if ( $app->is_valid_url( $path ) || file_exists( $path ) ) {
                                            $attachment_labels[ $column_name ] = $attach_label;
                                            $attachment_basenames[ $column_name ] = basename( $path );
                                            $value = file_get_contents( $path, false, $http_option );
                                            break;
                                        }
                                    }
                                }
                                $value .= $this->innerHTML( $ele );
                            }
                        }
                    }
                    if ( isset( $field_values[ $column_name ] ) ) {
                        $field_values[ $column_name ] = $field_values[ $column_name ] . $value;
                    } else {
                        $field_values[ $column_name ] = $value;
                    }
                }
            }
            $asset_ids = [];
            $binaries = [];
            foreach ( $field_values as $column_name => $field_value ) {
                if ( $entry->has_column( $column_name ) ) {
                    $col_type = $column_defs[ $column_name ]['type'];
                    $edit_prop = isset( $edit_properties[ $column_name ] ) ? $edit_properties[ $column_name ] : '';
                    if ( $edit_prop == 'primary' || $edit_prop == 'text' || $edit_prop == 'text_short' ) {
                        $field_value = trim( $field_value );
                        $field_value = str_replace( ["\r", "\n"], '', $field_value );
                    } else if ( $col_type == 'text' || $edit_prop == 'textarea' || $edit_prop == 'textarea' ) {
                        if ( $minifying_html ) {
                            $field_value = smarty_outputfilter_trimwhitespace( $field_value );
                        }
                    } else if ( $col_type == 'blob' && $field_value ) {
                        $blob_column = $app->db->model( 'column' )->get_by_key(
                            ['table_id' => $table->id, 'name' => $column_name ] );
                        $blob_extra = $blob_column->extra;
                        $upload_dir = $app->upload_dir();
                        $basename = $attachment_basenames[ $column_name ];
                        $asset_path = $upload_dir. DS . $basename;
                        file_put_contents( $asset_path, $field_value );
                        $error = '';
                        $res = PTUtil::upload_check( $blob_extra, $asset_path, false, $error );
                        if ( $res == 'resized' ) {
                            $migrator->print( $app->translate(
                            "The image (%s) was larger than the size limit, so it was reduced.",
                            htmlspecialchars( $basename ) ) );
                        } else if ( $error ) {
                            $migrator->print( $error );
                            $field_value = null;
                            continue;
                        }
                        $binaries[ $column_name ] = ['path' => $asset_path, 'label' => $attachment_labels[ $column_name ] ];
                        continue;
                    } else if ( stripos( $col_type, 'date' ) === 0 ) {
                        if ( $field_value ) {
                            $field_value = $this->str2DateTime( $field_value );
                        }
                    }
                    $entry->$column_name( $field_value );
                    if (! $import_assets ) {
                        continue;
                    }
                    $replaced_url = [];
                    $replaced_href = [];
                    $_dom = new DomDocument();
                    $text_content = "<html><body>{$field_value}";
                    if (! $_dom->loadHTML( mb_convert_encoding( $text_content, 'HTML-ENTITIES', 'utf-8' ) ) ) {
                        continue;
                    }
                    $import_elements = [];
                    $elements = $_dom->getElementsByTagName( 'img' );
                    if ( $elements->length ) {
                         for ( $i = 0; $i < $elements->length; $i++ ) {
                            $import_elements[] = $elements->item( $i );
                        }
                    }
                    if ( $asset_exts ) {
                        $elements = $_dom->getElementsByTagName( 'a' );
                        if ( $elements->length ) {
                            for ( $i = 0; $i < $elements->length; $i++ ) {
                                $ele = $elements->item( $i );
                                $src_url = $ele->getAttribute( 'href' );
                                if ( strpos( $src_url, '?' ) !== false ) {
                                    list( $src_url, $_param ) = explode( '?', $src_url );
                                }
                                $ext = preg_replace("/^.*\.([^\.].*$)/is", '$1', basename( $src_url ) );
                                if ( in_array( $ext, $asset_extensions ) ) {
                                    $import_elements[] = $ele;
                                }
                            }
                        }
                    }
                    if ( $og_image ) {
                        array_unshift( $import_elements, $og_image );
                    }
                    $asset_cnt = 0;
                    foreach ( $import_elements as $ele ) {
                        $tagName = '';
                        $label = '';
                        if ( $og_image && ! $asset_cnt ) {
                            $label = $title;
                            $og_image = '';
                        }
                        $asset_cnt++;
                        if ( is_string( $ele ) ) {
                            $src_url = $ele;
                        } else {
                            $tagName = $ele->tagName;
                            $src_url = $tagName == 'a' ? $ele->getAttribute( 'href' )
                                                       : $ele->getAttribute( 'src' );
                        }
                        $origURL = $src_url;
                        if ( $src_url && strpos( $src_url, 'mailto:' ) !== 0 && strpos( $src_url, 'data:' ) !== 0
                            && strpos( $src_url, 'javascript:' ) !== 0 && strpos( $src_url, '#' ) !== 0 ) {
                            if ( strpos( $src_url, '?' ) !== false ) {
                                list( $src_url, $_param ) = explode( '?', $src_url );
                            }
                            $error = '';
                            $asset_path = '';
                            if ( $import_type == 'url' ) {
                                $src_url = $this->convert2abs( $src_url, $base );
                            } else {
                                if ( strpos( $src_url, '.' ) === 0 ) {
                                    $src_url = dirname( $url ) . DS . $src_url;
                                    $asset_path = $src_url;
                                } else if ( strpos( $src_url, '/' ) === 0 ) {
                                    $search = preg_quote( $src_url, '/' );
                                    foreach ( $asset_paths as $file_path ) {
                                        if ( strpos( $file_path, '?' ) !== false ) {
                                            list( $file_path, $_param ) = explode( '?', $file_path );
                                        }
                                        if ( preg_match( "/{$search}$/", $file_path ) ) {
                                            $res = PTUtil::upload_check( $asset_extra, $file_path, false, $error );
                                            if ( $res == 'resized' ) {
                                                $migrator->print( $app->translate(
                                                "The image (%s) was larger than the size limit, so it was reduced.",
                                                htmlspecialchars( basename( $file_path ) ) ) );
                                            } else if ( $error ) {
                                                $migrator->print( $error );
                                                $i -= 1;
                                                continue;
                                            }
                                            $asset_path = $file_path;
                                            unset( $insert_asset_paths[ $file_path ] );
                                            break;
                                        }
                                    }
                                }
                            }
                            $ext = preg_replace("/^.*\.([^\.].*$)/is", '$1', basename( $src_url ) );
                            if (! $ext || in_array( $ext, $denied_exts ) ) {
                                $migrator->print( $this->translate(
                                "The import is skipped because the file (%s) is not allowed.", $src_url ) );
                                $i -= 1;
                                continue;
                            }
                            if ( strpos( $src_url, 'http' ) === 0 ) {
                                $asset_data = file_get_contents( $src_url, false, $http_option );
                                if ( $asset_data === false ) continue;
                                $upload_dir = $app->upload_dir();
                                $asset_path = $upload_dir. DS . basename( $src_url );
                                file_put_contents( $asset_path, $asset_data );
                                $res = PTUtil::upload_check( $asset_extra, $asset_path, false, $error );
                                if ( $res == 'resized' ) {
                                    $migrator->print( $app->translate(
                                    "The image (%s) was larger than the size limit, so it was reduced.",
                                    htmlspecialchars( basename( $asset_path ) ) ) );
                                } else if ( $error ) {
                                    $migrator->print( $error );
                                    $i -= 1;
                                    continue;
                                }
                            }
                            if ( $import_type == 'url' ) {
                                $extra_path = $this->get_extra_path( $src_url, $site_path, $import_type );
                            } else {
                                $extra_path = $this->get_extra_path( $asset_path, $site_path, $import_type );
                            }
                            if (! $asset_path || ( $asset_path && file_exists( $asset_path ) == false ) ) {
                                $migrator->print( $this->translate(
                                "The import is skipped because file was not found '%s'.",
                                $origURL ) );
                                $i -= 1;
                                continue;
                            }
                            if (! $extra_path ) {
                                $extra_path = $asset_extra_path;
                            }
                            $printPath = htmlspecialchars( $extra_path . basename( $asset_path ) );
                            $asset = $app->db->model( 'asset' )->get_by_key(
                               ['extra_path' => $extra_path,
                                'file_name' => basename( $asset_path ),
                                'workspace_id' => $workspace_id,
                                'rev_type' => 0]
                            );
                            if ( $tagName == 'a' ) {
                                $label = trim( $ele->textContent ) ? trim( $ele->textContent ) : basename( $asset_path );
                            } else if ( $tagName == 'img' ) {
                                $label = $ele->getAttribute( 'alt' ) ? $ele->getAttribute( 'alt' ) : basename( $asset_path );
                            } else {
                                $label = $label ? $label : basename( $asset_path );
                            }
                            if ( $asset->id ) {
                                $asset_ids[] = (int) $asset->id;
                                $migrator->print( $this->translate(
                                "The import is skipped because the image '%s' exists.", $printPath ) );
                                $i -= 1;
                                $url_obj = $app->db->model( 'urlinfo' )->load( [
                                    'model' => 'asset', 'object_id' => $asset->id, 'class' => 'file'] );
                                if ( is_array( $url_obj ) && ! empty( $url_obj ) ) {
                                    $url_obj = $url_obj[0];
                                    $newURL = $url_obj->url;
                                    $newURL = preg_replace( '!^https{0,1}://.*?/!', '/', $newURL );
                                    if ( $origURL != $newURL ) {
                                        if ( $tagName == 'a' ) {
                                            $replaced_href[ $origURL ] = $newURL;
                                        } else if ( $tagName == 'img' ) {
                                            $replaced_url[ $origURL ] = $newURL;
                                        }
                                    }
                                }
                                continue;
                            }
                            $logging = $app->logging;
                            $app->logging = false;
                            $error_reporting = ini_get( 'error_reporting' ); 
                            error_reporting(0);
                            $metadata = PTUtil::get_upload_info( $app, $asset_path, $error );
                            $app->logging = $logging;
                            error_reporting( $error_reporting );
                            if ( $error ) {
                                $migrator->print( $this->translate(
                                "The import is skipped because error occurred while importing '%s'.",
                                $printPath ) );
                                $i -= 1;
                                continue;
                            }
                            $metadata = $metadata['metadata'];
                            $asset->label( $label );
                            $this->set_asset_meta( $asset, $metadata );
                            $asset->file( $asset_data );
                            $app->set_default( $asset );
                            $asset->save();
                            PTUtil::file_attach_to_obj ( $app, $asset, 'file', $asset_path, $label, $error );
                            $app->publish_obj( $asset );
                            $url_obj = $app->db->model( 'urlinfo' )->load( [
                                'model' => 'asset', 'object_id' => $asset->id, 'class' => 'file'] );
                            if ( is_array( $url_obj ) && ! empty( $url_obj ) ) {
                                $url_obj = $url_obj[0];
                                $newURL = $url_obj->url;
                                $newURL = preg_replace( '!^https{0,1}://.*?/!', '/', $newURL );
                                if ( $origURL != $newURL ) {
                                    if ( $tagName == 'a' ) {
                                        $replaced_href[ $origURL ] = $newURL;
                                    } else {
                                        $replaced_url[ $origURL ] = $newURL;
                                    }
                                }
                            }
                            $migrator->print( $app->translate( 'Create %s (%s / ID : %s).', [ $app->translate('Asset'), $printPath, $asset->id ] ) );
                            $asset_ids[] = (int) $asset->id;
                        }
                        $i -= 1;
                    }
                    if ( count( $replaced_url ) ) {
                        foreach ( $replaced_url as $old => $new ) {
                            if ( mb_substr_count( $field_value, $old ) ) {
                                if ( mb_substr_count( $field_value, $old ) == 1 ) {
                                    $field_value = str_replace( $old, $new, $field_value );
                                } else {
                                    $regex = preg_quote( $old, '/' );
                                    $regex = "/(src=['\"]{0,1}){$regex}(['\"]{0,1})/si";
                                    $field_value = preg_replace( $regex, "$1$new$2", $field_value );
                                }
                            }
                        }
                    }
                    if ( count( $replaced_href ) ) {
                        foreach ( $replaced_href as $old => $new ) {
                            if ( mb_substr_count( $field_value, $old ) ) {
                                if ( mb_substr_count( $field_value, $old ) == 1 ) {
                                    $field_value = str_replace( $old, $new, $field_value );
                                } else {
                                    $regex = preg_quote( $old, '/' );
                                    $regex = "/(href=['\"]{0,1}){$regex}(['\"]{0,1})/si";
                                    $field_value = preg_replace( $regex, "$1$new$2", $field_value );
                                }
                            }
                        }
                    }
                    $entry->$column_name( $field_value );
                }
            }
            if ( $entry->has_column( 'text_format' ) ) {
                $entry->text_format( $text_format );
            }
            $extra_path = '';
            if ( $import_type == 'url' ) {
                $rel_path = preg_replace( '!^https{0,1}://.*?/!', '', $url );
                if ( strpos( $rel_path, '/' ) !== false ) {
                    $extra_path = dirname( preg_replace( '!^https{0,1}://.*?/!', '', $url ) );
                }
            } else {
                $begin = preg_quote( $import_base, '/' );
                $extra_path = dirname( preg_replace( "/^{$begin}/", '', $url ) );
                $extra_path = str_replace( DS, '/', $extra_path );
                $extra_paths = explode( '/', $extra_path );
                $first = array_shift( $extra_paths );
                if (! $first ) {
                    array_shift( $extra_paths );
                }
                $extra_path = implode( '/', $extra_paths );
            }
            if ( $extra_path ) {
                if ( stripos( $extra_path, $site_path ) === 0 ) {
                    $search = preg_quote( $site_path, '/' );
                    $extra_path = preg_replace( "/^{$search}/", '', $extra_path );
                    $extra_path = ltrim( $extra_path, '/' );
                }
                $extra_path = ltrim( $extra_path, '/' );
                $extra_path = $app->sanitize_dir( $extra_path );
            }
            if ( $entry->has_column( 'extra_path' ) && ! $entry->extra_path ) {
                $entry->extra_path( $extra_path );
            }
            $lastCat = null;
            if ( $app->param( 'html_create_categories' ) && $extra_path
                && $model_type == 'entry' ) {
                $extra_path = rtrim( $extra_path, '/' );
                $extra_paths = explode( '/', $extra_path );
                $catModel = $model == 'entry' ? 'category' : 'folder';
                $parent_id = 0;
                $terms = [];
                $terms['workspace_id'] = $workspace_id;
                foreach ( $extra_paths as $path ) {
                    $terms['basename'] = $path;
                    $terms['parent_id'] = $parent_id;
                    $cat = $app->db->model( $catModel )->get_by_key( $terms );
                    if (! $cat->id ) {
                        $label = $app->translate( $path , '', $app, 'defailt' );
                        $cat->label( $app->translate( $label ) );
                        $app->set_default( $cat );
                        $cat->save();
                    }
                    $lastCat = $cat;
                    $parent_id = (int) $cat->id;
                }
                if ( $lastCat !== null ) {
                    if ( $model == 'page' ) {
                        $entry->folder_id( $lastCat->id );
                    }
                }
            }
            $basename_set = $entry->has_column( 'basename' ) && $entry->basename;
            $app->set_default( $entry );
            $entry->save();
            if ( count( $asset_ids ) && $table->has_assets ) {
                $args = ['from_id' => $entry->id, 
                         'name' => 'assets',
                         'from_obj' => $model,
                         'to_obj' => 'asset' ];
                $app->set_relations( $args, $asset_ids, true );
            }
            if ( $lastCat !== null && $model == 'entry' ) {
                $args = ['from_id' => $entry->id, 
                         'name' => 'categories',
                         'from_obj' => $model,
                         'to_obj' => 'category' ];
                $app->set_relations( $args, [ $lastCat->id ], false );
            }
            if ( count( $binaries ) ) {
                foreach ( $binaries as $binary => $props ) {
                    PTUtil::file_attach_to_obj(
                    $app, $entry, $binary, $props['path'], $props['label'] );
                }
            }
            if ( $app->param( 'html_meta_tags' ) && $keywords ) {
                $keywords = preg_split( '/\s*,\s*/', $keywords );
                $tag_ids = [];
                foreach ( $keywords as $tag ) {
                    if ( function_exists( 'normalizer_normalize' ) ) {
                        $tag = normalizer_normalize( $tag, Normalizer::NFKC );
                    }
                    $normalize = str_replace( ' ', '', trim( mb_strtolower( $tag ) ) );
                    if (!$normalize ) continue;
                    $terms = ['normalize' => $normalize, 'class' => $model ];
                    $terms['workspace_id'] = $workspace_id;
                    $tag_obj = $app->db->model( 'tag' )->get_by_key( $terms );
                    if (!$tag_obj->id ) {
                        $tag_obj->name( $tag );
                        $app->set_default( $tag_obj );
                        $order = $app->get_order( $tag_obj );
                        $tag_obj->order( $order );
                        $tag_obj->save();
                    }
                    $tag_ids[] = $tag_obj->id;
                }
                if ( count( $tag_ids ) ) {
                    $args = ['from_id' => $entry->id, 
                             'name' => 'tags',
                             'from_obj' => $model,
                             'to_obj' => 'tag' ];
                    $app->set_relations( $args, $tag_ids, false );
                }
            }
            $message = $is_new
                        ? $app->translate( 'Create %s (%s / ID : %s).',
                            [ $app->translate( $table->label ),
                              $entry->$title_col,
                              $entry->id ] )
                        : $app->translate( 'Update %s (%s / ID : %s).',
                            [ $app->translate( $table->label ),
                              $entry->$title_col,
                              $entry->id ] );
            $migrator->print( $message );
            $meta->object_id( $entry->id );
            $meta->save();
            if ( $entry->has_column( 'basename' ) && ! $basename_set ) {
                if ( strpos( $url, '?' ) !== false ) {
                    list( $url, $_param ) = explode( '?', $url );
                }
                $pathinfo = pathinfo( $url );
                $basename = isset( $pathinfo ) && $pathinfo['filename'] ? $pathinfo['filename'] : '';
                $basename = PTUtil::make_basename( $entry, $basename, true );
                $entry->basename( $basename );
                $entry->save();
            }
            $app->publish_obj( $entry );
            $counter++;
            if ( $original ) {
                PTUtil::pack_revision( $entry, $original );
            }
            $callback = ['name' => 'post_import', 'url' => $url,
                         'data' => $data, 'format' => 'html',
                         'dom' => $dom, 'finder' => $finder ];
            $app->run_callbacks( $callback, $entry->_model, $entry, $original );
        }
        if ( $import_assets && count( $insert_asset_paths ) ) {
            foreach ( $insert_asset_paths as $asset_path => $bool ) {
                $extra_path = $this->get_extra_path( $asset_path, $site_path, $import_type );
                $printPath = htmlspecialchars( $extra_path . basename( $asset_path ) );
                $asset = $app->db->model( 'asset' )->get_by_key(
                   ['extra_path' => $extra_path,
                    'file_name' => basename( $asset_path ),
                    'workspace_id' => $workspace_id,
                    'rev_type' => 0]
                );
                if ( $asset->id ) {
                    $asset_ids[] = (int) $asset->id;
                    $migrator->print( $this->translate(
                    "The import is skipped because the image '%s' exists.", $printPath ) );
                    continue;
                }
                $asset_data = file_get_contents( $asset_path );
                $error = '';
                $logging = $app->logging;
                $app->logging = false;
                $error_reporting = ini_get( 'error_reporting' ); 
                error_reporting(0);
                $metadata = PTUtil::get_upload_info( $app, $asset_path, $error );
                $app->logging = $logging;
                error_reporting( $error_reporting );
                if ( $error ) {
                    $migrator->print( $this->translate(
                    "The import is skipped because error occurred while importing '%s'.",
                    $printPath ) );
                    $i -= 1;
                    continue;
                }
                $metadata = $metadata['metadata'];
                $label = basename( $asset_path );
                $asset->label( $label );
                $this->set_asset_meta( $asset, $metadata );
                $asset->file( $asset_data );
                $app->set_default( $asset );
                $asset->save();
                PTUtil::file_attach_to_obj ( $app, $asset, 'file', $asset_path, $label, $error );
                $app->publish_obj( $asset );
                $url_obj = $app->db->model( 'urlinfo' )->load( [
                    'model' => 'asset', 'object_id' => $asset->id, 'class' => 'file'] );
                if ( is_array( $url_obj ) && ! empty( $url_obj ) ) {
                    $url_obj = $url_obj[0];
                    $newURL = $url_obj->url;
                    $newURL = preg_replace( '!^https{0,1}://.*?/!', '/', $newURL );
                    if ( $origURL != $newURL ) {
                        if ( $tagName == 'a' ) {
                            $replaced_href[ $origURL ] = $newURL;
                        } else {
                            $replaced_url[ $origURL ] = $newURL;
                        }
                    }
                }
                $migrator->print( $app->translate( 'Create %s (%s / ID : %s).', [ $app->translate('Asset'), $printPath, $asset->id ] ) );
            }
        }
        $migrator->end_import( $session, $counter, $obj_label_plural );
    }

    function set_asset_meta ( &$asset, $metadata ) {
        $asset->mime_type( $metadata['mime_type'] );
        $asset->file_ext( $metadata['extension'] );
        $asset->size( $metadata['file_size'] );
        $asset->image_width( $metadata['image_width'] );
        $asset->image_height( $metadata['image_height'] );
        $asset->class( $metadata['class'] );
        return $asset;
    }

    function get_extra_path ( $url, $site_path, $type = 'url' ) {
        $app = Prototype::get_instance();
        if ( $type == 'url' ) {
            $extra_path = dirname( preg_replace( '!^https{0,1}://.*?/!', '', $url ) );
            if ( $extra_path == '.' ) {
                return '';
            }
            if ( stripos( $extra_path, $site_path ) === 0 ) {
                $search = preg_quote( $site_path, '/' );
                $extra_path = preg_replace( "/^{$search}/", '', $extra_path );
                $extra_path = ltrim( $extra_path, '/' );
            }
        } else {
            $begin = preg_quote( $this->session->value, '/' );
            $extra_path = dirname( preg_replace( "/^{$begin}/", '', $url ) );
            $extra_paths = explode( DS, $extra_path );
            $first = array_shift( $extra_paths );
            if (! $first ) {
                array_shift( $extra_paths );
            }
            $extra_path = implode( '/', $extra_paths );
            if ( stripos( $extra_path, $site_path ) === 0 ) {
                $search = preg_quote( $site_path, '/' );
                $extra_path = preg_replace( "/^{$search}/", '', $extra_path );
            }
        }
        $extra_path = ltrim( $extra_path, '/' );
        $extra_path = $app->sanitize_dir( $extra_path );
        $extra_path = str_replace( DS, '/', $extra_path );
        return $extra_path;
    }

    function convert2abs ( $target_path, $base ) {
        $component = parse_url( $base );
        $directory = preg_replace( '!/[^/]*$!', '/', $component['path'] );
        switch ( true ) {
            case preg_match( "/^http/", $target_path ) :
                $uri =  $target_path;
                break;
            case preg_match( "/^\/\/.+/", $target_path ) :
                $uri =  $component['scheme'].":".$target_path;
                break;
            case preg_match( "/^\/[^\/].+/", $target_path ) :
                $uri =  $component['scheme'] . "://" . $component['host'] . $target_path;
                break;
            case preg_match( "/^\/$/", $target_path ) :
                $uri =  $component['scheme'] . "://" . $component['host'] . $target_path;
                break;
            case preg_match( "/^\.\/(.+)/", $target_path, $maches ) :
                $uri =  $component['scheme']
                     . "://" . $component['host'] . $directory.$maches[1];
                break;
            case preg_match( "/^([^\.\/]+)(.*)/", $target_path, $maches ):
                $uri =  $component['scheme']
                     . "://" . $component['host'] . $directory . $maches[1] . $maches[2];
                break;
            case ( preg_match( "/^\.\.\/.+/", $target_path ) || $target_path == '../' ):
                preg_match_all( "!\.\./!", $target_path, $matches );
                $nest = count( $matches[0] );
                $dir = preg_replace( '!/[^/]*$!', '/', $component['path'] ) . "\n";
                $dir_array = explode( '/', $dir );
                array_shift( $dir_array );
                array_pop( $dir_array );
                $dir_count = count( $dir_array );
                $count = $dir_count - $nest;
                $pathto = '';
                $i = 0;
                while ( $i < $count ) {
                    $pathto .= '/' . $dir_array[ $i ];
                    $i++;
                }
                $file = str_replace( '../', '', $target_path );
                $uri =  $component['scheme']
                     . '://' . $component['host'] . $pathto . '/' . $file;
                break;
            default:
                $uri = $target_path;
        }
        return $uri;
    }

    function innerHTML ( $element ) { 
        $innerHTML = ""; 
        $children  = $element->childNodes;
        foreach ( $children as $child ) { 
            $innerHTML .= $element->ownerDocument->saveHTML( $child );
        }
        return $innerHTML; 
    } 

    function get_title_from_node ( $children, &$title ) {
        for ( $i = 0; $i < $children->length; $i++ ) {
            $child = $children->item( $i );
            $title .= trim( $child->textContent );
            if ( $child->nodeName == 'img' ) {
                $title .= trim( $child->getAttribute( 'alt' ) );
            }
            $grandchildren = $child->childNodes;
            if ( $grandchildren->length ) {
                $this->get_title_from_node( $grandchildren, $title );
            }
        }
        return $title;
    }

    function sanitize_regex ( $str ) {
        if ( preg_match('!([a-zA-Z\s]+)$!s', $str, $matches ) && ( preg_match('/[eg]/', $matches[1] ) ) ) {
            $str = substr( $str, 0, - strlen( $matches[1] ) )
                 . preg_replace( '/[eg\s]+/', '', $matches[1] );
        }
        return $str;
    }

    function str2DateTime ( $date ) {
        $ts = '';
        $date = str_replace( ["\r", "\n"], '', $date );
        $date = str_replace( ' ', '', strip_tags( trim( $date ) ) );
        $Y = '';
        if ( preg_match( '/[^0-9]{1,}/', $date ) ) {
            $parts = preg_split( '/[^0-9]{1,}/', $date );
            $Y = isset( $parts[0] ) ? $parts[0] : '';
            $m = isset( $parts[1] ) ? sprintf('%02d', $parts[1] ) : '';
            $d = isset( $parts[2] ) ? sprintf('%02d', $parts[2] ) : '';
            $H = isset( $parts[3] ) ? sprintf('%02d', $parts[3] ) : '00';
            $i = isset( $parts[4] ) ? sprintf('%02d', $parts[4] ) : '00';
            $s = isset( $parts[5] ) ? sprintf('%02d', $parts[5] ) : '00';
        } else if ( preg_match( '/^[0-9]{8,}$/', $date ) ) {
            $Y = substr( $date, 0, 4 );
            $m = substr( $date, 4, 2 );
            $d = substr( $date, 6, 2 );
            $H = substr( $date, 8, 2 ) ? substr( $date, 8, 2 ) : '00';
            $i = substr( $date, 10, 2 ) ? substr( $date, 10, 2 ) : '00';
            $s = substr( $date, 12, 2 ) ? substr( $date, 12, 2 ) : '00';
        }
        if ( $Y && checkdate( $m, $d, $Y ) ) {
            $ts = "$Y$m$d$H$i$s";
        }
        return $ts;
    }

    function removeTags( $html ) {
        $html = preg_replace( '/<form[^>]*?>.*?<\/form>/si', '', $html );
        $html = preg_replace( '/<nav[^>]*?>.*?<\/nav>/si', '', $html );
        $html = preg_replace( '/<menu[^>]*?>.*?<\/menu>/si', '', $html );
        $html = preg_replace( '/<header[^>]*?>.*?<\/header>/si', '', $html );
        $html = preg_replace( '/<footer[^>]*?>.*?<\/footer>/si', '', $html );
        $html = preg_replace( '/<script[^>]*?>.*?<\/script>/si', '', $html );
        $html = preg_replace( '/<noscript[^>]*?>.*?<\/noscript>/si', '', $html );
        $html = preg_replace( '/<aside[^>]*?>.*?<\/aside>/si', '', $html );
        $html = preg_replace( '/<style[^>]*?>.*?<\/style>/si', '', $html );
        $html = preg_replace('#<!--.*?-->#msi', '', $html);
        return $html;
    }

    function print_error ( $message ) {
        $app = Prototype::get_instance();
        $migrator = $this->migrator;
        $session = $this->session;
        $migrator->print( $message );
        $dir = $session->value;
        PTUtil::remove_dir( $dir );
        $session->remove();
        $migrator->pause( $app );
    }
}