<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );

class SearchEstraier extends PTPlugin {

    private $update_indexes = [];

    function __construct () {
        parent::__construct();
    }

    function searchestraier_post_init ( $app ) {
        $app->publish_callbacks = true;
    }

    function searchestraier_post_run ( $app ) {
        $update_indexes = $this->update_indexes;
        if (! empty( $update_indexes ) ) {
            $estcmd_path = $this->get_config_value( 'searchestraier_estcmd_path' );
            $mecab_path = $this->get_config_value( 'searchestraier_mecab_path' );
            if (! file_exists( $mecab_path ) ) {
                $mecab_path = null;
            }
            foreach ( $update_indexes as $path => $bool ) {
                $command = $mecab_path ? "{$estcmd_path} extkeys -um {$path}" : "{$estcmd_path} extkeys {$path}";
                shell_exec( $command );
                $command = "{$estcmd_path} optimize {$path}";
                shell_exec( $command );
            }
        }
    }

    function searchestraier_start_publish ( $cb, $app, $unlink ) {
        $ui = $cb['urlinfo'];
        if ( $ui->publish_file == 1 && ! $unlink ) {
            return;
        }
        $estcmd_path = $this->get_config_value( 'searchestraier_estcmd_path' );
        if (! file_exists( $estcmd_path ) ) {
            return;
        }
        $estcmd_path = escapeshellcmd( $estcmd_path );
        $workspace_id = (int) $ui->workspace_id;
        $archive_types = $this->get_config_value( 'searchestraier_archive_types', $workspace_id );
        if (! $archive_types ) {
            return;
        }
        $archive_types = preg_split( '/\s*,\s*/', $archive_types );
        if (! in_array( $ui->archive_type, $archive_types ) ) {
            return;
        }
        $enabled = $this->get_config_value( 'searchestraier_enabled', $workspace_id );
        if (! $enabled ) {
            return;
        }
        $data_dir = $this->get_config_value( 'searchestraier_data_dir', $workspace_id );
        $data_dir = $app->build( $data_dir );
        if (! $data_dir || !is_dir( $data_dir ) ) {
            return;
        }
        $url = escapeshellarg( $ui->url );
        $data_dir = escapeshellarg( $data_dir );
        if ( $unlink ) {
            $this->update_indexes[ $data_dir ] = true;
            $command = "{$estcmd_path} get {$data_dir} {$url}";
            $res = shell_exec( $command );
            if ( $res === null ) {
                return;
            }
            $command = "{$estcmd_path} out {$data_dir} {$url}";
            $res = shell_exec( $command );
        } else if ( $ui->publish_file == 6 || $ui->publish_file == 3 ) {
            $index_dinamic = $this->get_config_value( 'searchestraier_index_dinamic', $workspace_id );
            if ( $index_dinamic ) {
                $this->update_indexes[ $data_dir ] = true;
                require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPublisher.php' );
                $pub = new PTPublisher;
                $data = $pub->publish( $ui );
                if ( stripos( $data, '<html' ) === false ) {
                    return $this->searchestraier_start_publish( $cb, $app, true );
                }
                $ctx = $app->ctx;
                $obj = $cb['object'];
                $this->update_indexes[ $data_dir ] = true;
                $doc_title = $this->get_config_value( 'searchestraier_doc_title', $workspace_id );
                $ctx->local_vars['url'] = $ui->url;
                $ctx->local_vars['object_id'] = $ui->object_id;
                $ctx->local_vars['model'] = $ui->model;
                $ctx->local_vars['mime_type'] = $ui->mime_type;
                $ctx->local_vars['workspace_id'] = $workspace_id;
                $build = $this->get_draft( $app, $data, $obj, $doc_title );
                $out = $app->upload_dir() . DS . $ui->id . '.est';
                $fmgr = $app->fmgr;
                $fmgr->put( $out, $build );
                $data_dir = escapeshellarg( $data_dir );
                $this->update_indexes[ $data_dir ] = true;
                $command = "{$estcmd_path} put {$data_dir} {$out}";
                $res = shell_exec( $command );
            }
        }
    }

    function searchestraier_post_publish ( $cb, $app, $tmpl, $data ) {
        if ( stripos( $data, '<html' ) === false ) {
            return $this->searchestraier_start_publish( $cb, $app, true );
        }
        $ui = $cb['urlinfo'];
        $estcmd_path = $this->get_config_value( 'searchestraier_estcmd_path' );
        if (! file_exists( $estcmd_path ) ) {
            return;
        }
        $estcmd_path = escapeshellcmd( $estcmd_path );
        $workspace_id = (int) $ui->workspace_id;
        $archive_types = $this->get_config_value( 'searchestraier_archive_types', $workspace_id );
        if (! $archive_types ) {
            return;
        }
        $archive_types = preg_split( '/\s*,\s*/', $archive_types );
        if (! in_array( $ui->archive_type, $archive_types ) ) {
            return;
        }
        $enabled = $this->get_config_value( 'searchestraier_enabled', $workspace_id );
        if (! $enabled ) {
            return;
        }
        $data_dir = $this->get_config_value( 'searchestraier_data_dir', $workspace_id );
        $data_dir = $app->build( $data_dir );
        if (! $data_dir || !is_dir( $data_dir ) ) {
            return;
        }
        $url = escapeshellarg( $ui->url );
        $data_dir = escapeshellarg( $data_dir );
        $ctx = $app->ctx;
        $this->update_indexes[ $data_dir ] = true;
        $doc_title = $this->get_config_value( 'searchestraier_doc_title', $workspace_id );
        $obj = $cb['object'];
        $ctx->local_vars['url'] = $ui->url;
        $ctx->local_vars['object_id'] = $ui->object_id;
        $ctx->local_vars['model'] = $ui->model;
        $ctx->local_vars['mime_type'] = $ui->mime_type;
        $ctx->local_vars['workspace_id'] = $workspace_id;
        $this->update_indexes[ $data_dir ] = true;
        $build = $this->get_draft( $app, $data, $obj, $doc_title );
        $out = $app->upload_dir() . DS . $ui->id . '.est';
        $fmgr = $app->fmgr;
        $fmgr->put( $out, $build );
        $command = "{$estcmd_path} put {$data_dir} {$out}";
        $res = shell_exec( $command );
    }

    function searchestraier_update_idx ( $app ) {
        $counter = 0;
        $estcmd_path = $this->get_config_value( 'searchestraier_estcmd_path' );
        if (! file_exists( $estcmd_path ) ) {
            return;
        }
        $estcmd_path = escapeshellcmd( $estcmd_path );
        $mecab_path = $this->get_config_value( 'searchestraier_mecab_path' );
        if (! file_exists( $mecab_path ) ) {
            $mecab_path = null;
        }
        require_once( LIB_DIR . 'ExtractContent' . DS . 'ExtractContent.php' );
        require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPublisher.php' );
        $pub = new PTPublisher;
        $db = $app->db;
        $workspaces = $db->model( 'workspace' )->load( [], ['sort' => 'id', 'direction' => 'ascend'], 'id' );
        $workspace_ids = [0];
        foreach ( $workspaces as $workspace ) {
            $workspace_ids[] = (int)$workspace->id;
        }
        $fmgr = $app->fmgr;
        $language = $app->language;
        $ctx = $app->ctx;
        $upload_dir = $app->upload_dir();
        $tmp_dirs = [];
        $commands = [];
        $draft_tmpl = $this->path() . DS . 'tmpl' . DS . 'document_draft.tmpl';
        foreach ( $workspace_ids as $workspace_id ) {
            $archive_types = $this->get_config_value( 'searchestraier_archive_types', $workspace_id );
            if (! $archive_types ) {
                continue;
            }
            $enabled = $this->get_config_value( 'searchestraier_enabled', $workspace_id );
            if (! $enabled ) {
                continue;
            }
            $index_dinamic = $this->get_config_value( 'searchestraier_index_dinamic', $workspace_id );
            if ( $workspace_id ) {
                $workspace = $db->model( 'workspace' )->load( $workspace_id );
                $language = $workspace->language;
            }
            $doc_title = $this->get_config_value( 'searchestraier_doc_title', $workspace_id );
            $data_dir = $this->get_config_value( 'searchestraier_data_dir', $workspace_id );
            $data_dir = $app->build( $data_dir );
            if (!is_dir( $data_dir ) ) {
                $fmgr->mkpath( $data_dir );
            }
            if (!is_dir( "{$data_dir}.tmp" ) ) {
                $fmgr->mkpath( "{$data_dir}.tmp" );
            } else {
                $fmgr->rmdir( "{$data_dir}.tmp" );
                $fmgr->mkpath( "{$data_dir}.tmp" );
            }
            if ( is_dir( "{$data_dir}.backup" ) ) {
                $fmgr->rmdir( "{$data_dir}.backup" );
            }
            $tmp_dir = $upload_dir . DS . md5( $data_dir );
            $tmp_dirs[ $tmp_dir ] = $data_dir;
            if (!is_dir( $tmp_dir ) ) {
                $fmgr->mkpath( $tmp_dir );
            }
            $archive_types = preg_split( '/\s*,\s*/', $archive_types );
            $urls = $db->model( 'urlinfo' )->load( ['workspace_id' => $workspace_id, 'mime_type' => 'text/html',
                                                    'is_published' => 1,
                                                    'archive_type' => ['IN' => $archive_types ] ] );
            if ( is_array( $urls ) && empty( $urls ) ) {
                continue;
            }
            $data_dir_tmp = escapeshellarg( "{$data_dir}.tmp" );
            $esc_tmp_dir = escapeshellarg( $tmp_dir );
            $language = escapeshellarg( $language );
            $commands["{$estcmd_path} gather -il {$language} -sd {$data_dir_tmp} {$esc_tmp_dir}"] = $data_dir;
            foreach ( $urls as $url ) {
                $file_path = $url->file_path;
                $data = '';
                if ( $file_path && $fmgr->exists( $file_path ) ) {
                    $data = $fmgr->get( $file_path );
                } else if ( $index_dinamic ) {
                    $data = $pub->publish( $url );
                }
                if ( stripos( $data, '<html' ) === false ) {
                    continue;
                }
                $obj = null;
                if ( $url->object_id && $url->model ) {
                    $obj = $db->model( $url->model )->load( (int) $url->object_id );
                }
                $ctx->local_vars['url'] = $url->url;
                $ctx->local_vars['object_id'] = $url->object_id;
                $ctx->local_vars['model'] = $url->model;
                $ctx->local_vars['mime_type'] = $url->mime_type;
                $ctx->local_vars['workspace_id'] = $workspace_id;
                $build = $this->get_draft( $app, $data, $obj, $doc_title );
                $out = $tmp_dir . DS . $url->id . '.est';
                $fmgr->put( $out, $build );
                $counter++;
            }
        }
        foreach ( $commands as $command => $out ) {
            $out = trim( $out, "'" );
            if ( $fmgr->exists( "{$out}.backup" ) ) {
                $fmgr->rmdir( "{$out}.backup" );
            }
            shell_exec( $command );
            $command = $mecab_path ? "{$estcmd_path} extkeys -um -fc {$out}.tmp" : "{$estcmd_path} extkeys -fc {$out}.tmp";
            shell_exec( $command );
            if ( $fmgr->exists( $out ) ) {
                $fmgr->rename( $out, "{$out}.backup" );
            }
            $fmgr->rename( "{$out}.tmp", $out );
            if ( $fmgr->exists( "{$out}.backup" ) ) {
                $fmgr->rmdir( "{$out}.backup" );
            }
        }
        return $counter;
    }

    function get_draft ( $app, $data, $obj, $doc_title ) {
        $draft_tmpl = $this->path() . DS . 'tmpl' . DS . 'document_draft.tmpl';
        $ctx = $app->ctx;
        if ( is_object( $obj ) ) {
            $relation_labels = $this->get_relation_labels( $app, $obj );
            if (! empty( $relation_labels ) ) {
                $ctx->local_vars['metadata'] = implode( ',', $relation_labels );
                $tags = $this->get_relation_labels( $app, $obj, 'tags' );
                if (! empty( $tags ) ) {
                    $ctx->local_vars['tags'] = implode( ',', $tags );
                }
            }
        }
        require_once( LIB_DIR . 'ExtractContent' . DS . 'ExtractContent.php' );
        $extractor = new ExtractContent( $data );
        $data = $extractor->getTextContent();
        $title = '';
        if ( $doc_title == 'heading' ) {
            $title = $this->get_heading( $extractor->dom );
        } else if ( $doc_title == 'archive' ) {
            $title = $ctx->stash( 'current_archive_title' )
                   ? $ctx->stash( 'current_archive_title' )
                   : $ctx->vars['current_archive_title'];
        }
        $title = $title ? $title : $extractor->title;
        $title = str_replace( ["\r", "\n"], '', $title );
        $ctx->local_vars['title'] = $title;
        $ctx->local_vars['content'] = $data;
        $metadata = $this->get_meta( $extractor->dom );
        $ctx->local_vars['meta_loop'] = $metadata;
        $build = $app->build_page( $draft_tmpl, [], false );
        return $build;
    }

    function get_relation_labels ( $app, $obj, $name = '' ) {
        $relation_labels = [];
        $scheme = $app->get_scheme_from_db( $obj->_model );
        if (! is_array( $scheme ) || !isset( $scheme['edit_properties'] ) ) {
            return $relation_labels;
        }
        $edit_properties = $scheme['edit_properties'];
        $excludes = ['user_id'];
        foreach ( $edit_properties as $column => $props ) {
            if ( in_array( $column, $excludes ) ) {
                continue;
            }
            if ( strpos( $props, ':' ) === false ) {
                continue;
            }
            if ( $name && $column != $name ) {
                continue;
            }
            $props = explode( ':', $props );
            if ( $props[0] != 'relation' && $props[0] != 'reference' ) {
                continue;
            }
            $col_name = $props[2];
            $related_objs = $app->load_related_objs( $obj, $props[1], [], [], "id,{$col_name}" );
            if (! is_array( $related_objs ) || ! count( $related_objs ) ) {
                continue;
            }
            foreach ( $related_objs as $related_obj ) {
                $label = trim( $related_obj->$col_name );
                $label = trim( str_replace( ["\r", "\n"], '', $label ) );
                $relation_labels[] = $label;
            }
        }
        return $relation_labels;
    }

    function get_heading ( $dom ) {
        $arr = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        $title = null;
        foreach ( $arr as $tag ) {
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
        return $title;
    }

    function get_meta ( $dom ) {
        $meta_elements = $dom->getElementsByTagName( 'meta' );
        $metadata = [];
        if ( $meta_elements->length ) {
            $i = $meta_elements->length - 1;
            for ( $i = 0; $i < $meta_elements->length; $i++ ) {
                $ele = $meta_elements->item( $i );
                $name = strtolower( $ele->getAttribute( 'name' ) );
                $content = trim( $ele->getAttribute( 'content' ) );
                if ( $name && $content ) {
                    $name = str_replace( ["\r", "\n"], '', $name );
                    $content = str_replace( ["\r", "\n"], '', $content );
                    $metadata[ $name ] = $content;
                }
            }
        }
        return $metadata;
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

    function hdlr_estraier_search ( $args, $content, $ctx, &$repeat ) {
        $localvars = array( '_estraier_search_time', '_estraier_count', '_estraier_snippets',
            '_estraier_counter', '_estraier_search_hit', '_estraier_search_meta',
            '_estraier_search_results' );
        $default_limit = isset( $args['default_limit'] ) ? $args['default_limit'] : '-1';
        $prefix = isset( $args['prefix'] ) ? $args['prefix'] : '';
        $workspace = $ctx->stash( 'workspace' );
        $workspace_id = $workspace ? $workspace->id : 0;
        $app = $ctx->app;
        if (! isset( $content ) ) {
            setlocale( LC_CTYPE, 'UTF8', 'ja_JP.UTF-8' );
            $ctx->localize( $localvars );
            $offset = 0;
            if ( isset( $_REQUEST['limit'] ) ) {
                $limit = $_REQUEST['limit'];
                if (! ctype_digit((string) $limit ) ) {
                    $limit = $default_limit;
                }
            } else {
                $limit = $default_limit;
            }
            if ( isset( $_REQUEST['offset'] ) ) {
                $offset = $_REQUEST['offset'];
                if (! ctype_digit((string) $offset ) ) {
                    $offset = null;
                }
            }
            if ( isset( $offset ) ) {
                if ( isset( $_REQUEST['decrementoffset'] ) ) {
                    if ( $_REQUEST['decrementoffset'] ) {
                        $offset--;
                    }
                }
            }
            $offset = (int) $offset;
            $need_count = false;
            $json = isset( $args['json'] ) ? isset( $args['json'] ) : false;
            if ( isset( $args['count'] ) ) {
                if ( $args['count'] ) {
                    $need_count = 1;
                    $limit = 0;
                }
            }
            if ( isset( $args['ad_attr'] ) ) {
                $ad_attr = $args['ad_attr'];
            } else if ( isset( $args['ad_attrs'] ) ) {
                $ad_attr = $args['ad_attrs'];
            }
            if ( isset( $args['add_condition'] ) ) {
                $add_condition = $args['add_condition'];
            } else if ( isset( $args['add_conditions'] ) ) {
                $ad_attr = $args['add_conditions'];
            }
            if ( isset( $args['values'] ) ) {
                $values = $args['values'];
            } else if ( isset( $args['value'] ) ) {
                $ad_attr = $args['value'];
            }
            $phrase = '';
            if ( isset( $_REQUEST['phrase'] ) ) {
                $phrase = $_REQUEST['phrase'];
            } else if ( isset( $_REQUEST['query'] ) ) {
                $phrase = $_REQUEST['query'];
            }
            $raw_exp = '';
            if ( $phrase ) {
                if (! is_array( $phrase ) ) {
                    $phrase = mb_convert_kana( $phrase, 's' );
                    $raw_exp = $phrase;
                }
            }
            if ( isset( $ad_attr ) ) {
                if (! is_array( $ad_attr ) ) {
                    $ad_attr = [ $ad_attr ];
                }
            }
            if ( isset( $add_condition ) ) {
                if (! is_array( $add_condition ) ) {
                    $add_condition = [ $add_condition ];
                }
            }
            if ( isset( $values ) ) {
                if (! is_array( $values ) ) {
                    $values = [ $values ];
                }
            }
            $i = 0;
            $condition = '';
            if ( isset( $ad_attr ) && is_array( $ad_attr ) ) {
                foreach( $ad_attr as $attr ) {
                    $cond = $add_condition[ $i ];
                    $value = $values[ $i ];
                    $add_cond = " -attr " . escapeshellarg( "${attr} ${cond} ${value}" );
                    $condition .= $add_cond;
                    $i++;
                }
            }
            $index_path = $this->get_config_value( 'searchestraier_data_dir', $workspace_id );
            $settings = $app->db->model( 'option' )->load( ['key' => 'searchestraier_data_dir', 'kind' => 'plugin_setting' ] );
            $workspace_ids = [];
            foreach ( $settings as $setting ) {
                if ( $index_path == $setting->value ) {
                    $workspace_ids[] = (int) $setting->workspace_id;
                }
            }
            $index_path = $app->build( $index_path );
            if (! $index_path || ! file_exists( $index_path ) ) {
                $repeat = false;
                return;
            }
            $workspace_ids = $this->workspace_attr( $app, $args, $workspace_ids );
            if ( is_array( $workspace_ids ) && count( $workspace_ids ) ) {
                $workspace_ids = implode( ' ', $workspace_ids );
                $add_cond = " -attr " . escapeshellarg( "@workspace_id STROR ${workspace_ids}" );
                $condition .= $add_cond;
            }
            $cmd = escapeshellcmd( $this->get_config_value( 'searchestraier_estcmd_path' ) );
            if (! file_exists( $cmd ) ) {
                $repeat = false;
                return;
            }
            $cmd .= " search -vx";
            if ( $offset ) {
                $cmd .= " -sk ${offset}";
            }
            $cmd .= " -max ${limit}";
            $cmd .= " -sn 200 100 150";
            $cmd .= $condition;
            if (! $need_count ) {
                $sort_by = null;
                $sort_order = null;
                if ( isset( $args['sort_by'] ) ) {
                    $sort_by = $args['sort_by'];
                }
                if ( isset( $args['sort_order'] ) ) {
                    $sort_order = $args['sort_order'];
                }
                if ( $sort_by && $sort_order ) {
                    if ( stripos( $sort_order, 'asc' ) === 0 ) {
                        $sort_order = 'NUMA';
                    } else {
                        $sort_order = 'NUMD';
                    }
                    $cmd .= " -ord " . escapeshellarg( "${sort_by} ${sort_order}" );
                }
            }
            $index_path = escapeshellarg( $index_path );
            $cmd .= ' ' . $index_path;
            $raw_query = '';
            if ( $phrase ) {
                $and_or = isset( $args['and_or'] ) ? $args['and_or'] : '';
                $and_or = isset( $_REQUEST['and_or'] ) ? $_REQUEST['and_or'] : $and_or;
                if (! $and_or || ( $and_or != 'AND' && $and_or != 'OR' ) ) {
                    $and_or = 'OR';
                }
                $and_or = strtoupper( $and_or );
                $and_or = " ${and_or} ";
                if ( is_array( $phrase ) ) {
                    $phrase = escapeshellarg( implode( $and_or, $phrase ) );
                } else {
                    $phrase = escapeshellarg( $phrase );
                    if ( isset( $args['raw_query'] ) ) $raw_query = $args['raw_query'];
                    if (! $raw_query ) {
                        $separator = '';
                        if ( isset( $args['separator'] ) ) $separator = $args['separator'];
                        if (! $separator ) $separator = ' ';
                        if ( strpos( $phrase, $separator ) !== false ) {
                            $phrase = explode( $separator, $phrase );
                            $phrase = join( $and_or, $phrase );
                        }
                    }
                }
                $cmd .= " ${phrase}";
            } else {
                $repeat = false;
                return;
            }
            $ctx->__stash['vars']['estcmd_cmd'] = $cmd;
            $xml = shell_exec( $cmd );
            preg_match_all( "/<snippet>(.*?)<\/snippet>/s", $xml, $snippets );
            $snippets = $snippets[1];
            $ctx->stash( '_estraier_snippets', $snippets );
            $result = new SimpleXMLElement( $xml );
            $records = $result->document;
            $meta = $result->meta;
            $ctx->stash( '_estraier_search_meta', $meta );
            $hit = $meta->hit;
            $hit = $hit->attributes()->number;
            $hit = ( string ) $hit;
            if ( $need_count ) {
                $repeat = false;
                $ctx->restore( $localvars );
                return $hit;
            }
            $ctx->__stash['vars'][ $prefix . 'hit'] = $hit;
            $time = $meta->time;
            $time = $time->attributes()->time;
            $time = ( string ) $time;
            $ctx->stash( '_estraier_search_time', $time );
            $ctx->__stash['vars'][ $prefix . 'totaltime'] = $time;
            $ctx->stash( '_estraier_search_hit', $hit );
            $ctx->stash( '_estraier_counter', 0 );
            $counter = 0;
            $max = count( $records );
            $ctx->__stash['vars'][ $prefix . 'resultcount'] = $max;
            $ctx->stash( '_estraier_count', $max );
            $ctx->__stash['vars'][ $prefix . 'totalresult'] = $max;
            if ( $limit > 0 ) {
                $ctx->__stash['vars'][ $prefix . 'limit'] = $limit;
                $total = ceil( $hit / $limit );
                $ctx->__stash['vars'][ $prefix . 'pagertotal'] = $total;
                if ( ( $offset + $limit ) < $hit ) {
                    $ctx->__stash['vars'][ $prefix . 'nextoffset'] = $offset + $limit;
                }
                if ( $offset ) {
                    $prevoffset = $offset - $limit;
                    if ( $prevoffset < 0 ) {
                        $prevoffset = 0;
                    }
                    $ctx->__stash['vars'][ $prefix . 'prevoffset'] = $prevoffset;
                }
                $current = $offset / $limit + 1;
                $ctx->__stash['vars'][ $prefix . 'currentpage'] = floor( $current );
            }
            if ( isset( $args['shuffle'] ) ) {
                if ( $args['shuffle'] ) {
                    $_count = count( $records );
                    $_records = array();
                    for ( $i = 0; $i < $_count; $i++ ) {
                        $_records[] = $records[ $i ];
                    }
                    shuffle( $_records );
                    $records = $_records;
                }
            }
            if ( $json ) {
                $repeat = false;
                $ctx->restore( $localvars );
                $results = array( 'records' => $records, 'snippets' => $snippets,
                                  'raw_exp' => $raw_exp, 'time' => $time, 'hit' => $hit,
                                  'phrase' => $phrase, 'limit' => $limit, 'offset' => $offset );
                return $results;
            }
            $ctx->local_params = $records;
            $ctx->stash( '_estraier_search_results', $records );
        } else {
            $records = $ctx->stash( '_estraier_search_results' );
            $snippets = $ctx->stash( '_estraier_snippets' );
            $meta = $ctx->stash( '_estraier_search_meta' );
            $counter = $ctx->stash( '_estraier_counter' );
            $hit = $ctx->stash( '_estraier_search_hit' );
            $time = $ctx->stash( '_estraier_search_time' );
            $max = $ctx->stash( '_estraier_count' );
        }
        $params = $ctx->local_params;
        $ctx->set_loop_vars( $counter, $params );
        if ( $counter < $max ) {
            $record = $records[ $counter ];
            $attrs = $record->attribute;
            $ctx->__stash['vars'][ $prefix . 'title' ] = '';
            $_uri = null;
            foreach( $attrs as $attr ) {
                $val = $attr->attributes()->value[ 0 ];
                $val = ( string ) $val;
                $name = $attr->attributes()->name;
                $name = ( string ) $name;
                $name = ltrim( $name, '@' );
                if ( $name == 'tsutaeru_uri' ) {
                    $_uri = $val;
                }
                $ctx->__stash['vars'][ $prefix . $name ] = $val;
            }
            $ctx->stash( '_estraier_record', $record );
            if (! $_uri ) {
                $_uri = $record->attributes()->uri;
                $_uri = ( string )$_uri;
            }
            $_id = $record->attributes()->id;
            $_id = ( string )$_id; 
            $ctx->__stash['vars'][ $prefix . 'uri'] = $_uri;
            $ctx->__stash['vars'][ $prefix . 'id'] = $_id;
            $snippet = $snippets[ $counter ];
            $snippet = str_replace( '<key', '<strong', $snippet );
            $snippet = str_replace( '</key>', '</strong>', $snippet );
            $snippet = str_replace( '<delimiter/>', '... ', $snippet );
            $snippet = preg_replace( '/ normal=".*?"/', '', $snippet );
            $ctx->__stash['vars'][ $prefix . 'snippet'] = $snippet;
            $count = $counter + 1;
            $ctx->stash( '_estraier_counter', $count );
            $repeat = true;
        } else {
            $ctx->restore( $localvars );
            $repeat = false;
        }
        return $content;
    }

    function hdlr_estraier_json ( $args, $ctx ) {
        $time_start = microtime( true );
        $args['json'] = 1;
        $content = null;
        $repeat = true;
        $results = $this->hdlr_estraier_search( $args, $content, $ctx, $repeat );
        $jsonp = isset( $args['jsonp'] ) ? true : false;
        $callback = isset( $args['callback'] ) ? $args['callback'] : 'callback';
        $callback = isset( $_REQUEST['callback'] ) ? $_REQUEST['callback'] : $callback;
        if ( $callback && (! preg_match( "/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $callback ) ) ) {
            $callback = false;
            $jsonp = false;
        }
        if ( $callback ) {
            $jsonp = true;
        }
        $records = $results['records'];
        $snippets = $results['snippets'];
        $hit = (int) $results['hit'];
        $phrase = $results['phrase'];
        $raw_exp = $results['raw_exp'];
        $limit = $results['limit'];
        $prefix = isset( $args['prefix'] ) ? $args['prefix'] : '';
        if ( isset( $args['phrase'] ) ) {
            $query = $args['phrase'];
        } else if ( isset( $args['query'] ) ) {
            $query = $args['query'];
        }
        $i = 0;
        $items = [];
        foreach ( $records as $record ) {
            $resource = array();
            $attrs = $record->attribute;
            $id = (int) $record->attributes()->id;
            $snippet = $snippets[ $i ];
            $snippet = str_replace( '<key', '<strong', $snippet );
            $snippet = str_replace( '</key>', '</strong>', $snippet );
            $snippet = str_replace( '<delimiter/>', '... ', $snippet );
            $snippet = preg_replace( '/ normal=".*?"/', '', $snippet );
            $uri = ( string )$record->attributes()->uri;
            $resource['uri'] = $uri;
            $resource['id'] = $id;
            $resource['excerpt'] = $snippet;
            foreach ( $attrs as $attr ) {
                $val = $attr->attributes()->value[ 0 ];
                $val = ( string ) $val;
                $name = $attr->attributes()->name;
                $name = ( string ) $name;
                if ( $name === '@title' ) {
                    $resource['title'] = $val;
                } else if ( $name == 'tsutaeru_uri' ) {
                    $resource['uri'] = $val;
                }
            }
            $i++;
            $items[] = $resource;
        }
        $phrase = trim( $phrase, "'" );
        $results = array( 'total_match_items' => $hit, 'items' => $items, 'phrase' => $raw_exp );
        $url = $ctx->__stash['vars']['current_archive_url'];
        $base_url = $url . '?query=' . rawurlencode( $raw_exp ) . '&limit=' . $limit;
        if ( isset( $ctx->__stash['vars'][ $prefix . 'nextoffset'] ) ) {
            $next_offset = $ctx->__stash['vars'][ $prefix . 'nextoffset'];
            $next_url = "{$base_url}&offset={$next_offset}";
            $results['nextURL'] = $next_url;
        }
        if ( isset( $ctx->__stash['vars'][ $prefix . 'prevoffset'] ) ) {
            $prev_offset = $ctx->__stash['vars'][ $prefix . 'prevoffset'];
            $prev_url = "{$base_url}&offset={$prev_offset}";
            $results['previousURL'] = $prev_url;
        }
        if ( isset( $ctx->__stash['vars'][ $prefix . 'currentpage'] ) ) {
            $results['currentPage'] = $ctx->__stash['vars'][ $prefix . 'currentpage'];
        }
        if ( isset( $ctx->__stash['vars'][ $prefix . 'pagertotal'] ) ) {
            $results['totalPage'] = $ctx->__stash['vars'][ $prefix . 'pagertotal'];
        }
        $pagenavi = [];
        $total_pages = null;
        $active_page = null;
        if ( isset( $ctx->__stash['vars'][ $prefix . 'pagertotal'] ) ) {
            $total_pages = $ctx->__stash['vars'][ $prefix . 'pagertotal'];
        }
        if ( isset( $ctx->__stash['vars'][ $prefix . 'currentpage'] ) ) {
            $active_page = $ctx->__stash['vars'][ $prefix . 'currentpage'];
        }
        if ( $limit > 0 ) {
            $pagenavi['first_item'] = ['offset' => 0, 'number' => 1];
            $pagenavi['active_page'] = $active_page;
            $pagenavi['total_pages'] = $total_pages;
            if ( $active_page > 1 ) {
                $prev_item = $active_page - 1;
                $pagenavi['prev_item'] = ['number' => $prev_item ];
                $prev_item--;
                $pagenavi['prev_item']['offset'] = $limit * $prev_item;
            }
            if ( $active_page < $total_pages ) {
                $next_item = $active_page + 1;
                $pagenavi['next_item'] = ['number' => $next_item ];
                $next_item--;
                $pagenavi['next_item']['offset'] = $limit * $next_item;
            }
            if ( $total_pages > 1 ) {
                $last_item = $total_pages;
                $pagenavi['last_item'] = ['number' => $total_pages ];
                $last_item--;
                $pagenavi['last_item']['offset'] = $limit * $last_item;
                $nav_items = [];
                for ( $i = 0; $i <= $last_item; $i++ ) {
                    $nav_items[] = ['offset' => $i * $limit, 'number' => $i + 1];
                }
                $pagenavi['items'] = $nav_items;
            }
        }
        $results['pagenavi'] = $pagenavi;
        $results['time'] = microtime( true ) - $time_start;
        $result = json_encode( $results, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT );
        return $jsonp ? "{$callback}({$result})" : $result;
    }

    private function workspace_attr ( $app, $args, $all_ids ) {
        $attr = null;
        $is_excluded = null;
        $workspace = $app->ctx->stash( 'workspace' );
        if ( isset( $args['workspace_ids'] ) ||
             isset( $args['include_workspaces'] ) ) {
            if (! isset( $args['workspace_ids'] ) ) {
                $args['workspace_ids'] = $args['include_workspaces'];
            }
            $attr = $args['workspace_ids'];
            if ( $attr && strtolower( $attr ) == 'this' ) {
                if ( $workspace && $workspace->id ) {
                    return [(int) $workspace->id ];
                } else {
                    return [0];
                }
            }
            unset( $args['workspace_ids'] );
            $is_excluded = 0;
        } elseif ( isset( $args['exclude_workspaces'] ) ) {
            $attr = $args['exclude_workspaces'];
            $is_excluded = 1;
        } elseif ( isset( $args['workspace_id'] ) ) {
            return [(int) $args['workspace_id'] ];
        }
        if ( preg_match( '/-/', $attr ) ) {
            $list = preg_split( '/\s*,\s*/', $attr );
            $attr = '';
            foreach ( $list as $item ) {
                if ( preg_match('/(\d+)-(\d+)/', $item, $matches ) ) {
                    for ( $i = $matches[1]; $i <= $matches[2]; $i++ ) {
                        if ( $attr != '' ) $attr .= ',';
                        $attr .= $i;
                    }
                } else {
                    if ( $attr != '' ) $attr .= ',';
                    $attr .= $item;
                }
            }
        }
        $workspace_ids = preg_split( '/\s*,\s*/', $attr, -1, PREG_SPLIT_NO_EMPTY );
        if ( $is_excluded ) {
            asort( $workspace_ids );
            asort( $all_ids );
            return array_diff( $all_ids, $workspace_ids );
        } else if ( isset( $args['include_workspaces'] ) &&
            $args['include_workspaces'] == 'all' ) {
            return false;
        } else {
            if ( count( $workspace_ids ) ) {
                array_walk( $workspace_ids, function( &$i ){ $i += 0; } );
                return $workspace_ids;
            } else {
                return false;
            }
        }
        return false;
    }

}