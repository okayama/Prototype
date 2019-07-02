<?php

class PTRecommendAPI extends Prototype {

    function __construct ( $options = [] ) {
        $this->id = 'RecommendAPI';
        parent::__construct( $options );
    }

    function run () {
        setlocale(LC_CTYPE, "UTF8", "ja_JP.UTF-8");
        $app = $this;
        $expire = $app->searchestraier_cookie_expires;
        $expire = time() + 60 * 60 * 24 * $expire;
        $path = $app->searchestraier_cookie_path;
        $cookie_name = $app->searchestraier_cookie_name;
        $url = $app->param( 'url' );
        $type = $app->param( 'type' );
        $msg = '';
        if ( !$app->is_valid_url( $url, $msg ) ) {
            $app->json_error( $msg, null, 500 );
        }
        $ui = $app->db->model( 'urlinfo' )->get_by_key( ['url' => $url ] );
        if (! $ui->id ) {
            $app->json_error( 'Page not found.', null, 404 );
        }
        $component = $app->component( 'SearchEstraier' );
        if ( $type == 'interest' ) {
            $by_scope = $component->get_config_value( 'searchestraier_cookie_by_scope', $ui->workspace_id );
            if ( $by_scope ) {
                $cookie_name .= '-' . $ui->workspace_id;
            }
        }
        $estcmd_path = $app->searchestraier_estcmd_path;
        if (! file_exists( $estcmd_path ) ) {
            $app->json_error( 'estcmd was not found.', null, 500 );
        }
        $estcmd_path = escapeshellcmd( $estcmd_path );
        $data_dir = $component->get_config_value( 'searchestraier_data_dir', $ui->workspace_id );
        $data_dir = $app->build( $data_dir );
        if (! $data_dir || !is_dir( $data_dir ) ) {
            $app->json_error( 'Index was not found.', null, 500 );
        }
        $url_original = $url;
        $url = escapeshellarg( $ui->url );
        $data_dir = escapeshellarg( $data_dir );
        $command = "{$estcmd_path} get {$data_dir} {$url}";
        $res = shell_exec( $command );
        if ( $res === null ) {
            $app->json_error( 'Page not found.', null, 404 );
        }
        $res = preg_replace( "/\r\n|\r|\n/", "\n", $res );
        $lines = explode( "\n", $res );
        $metadata = [];
        $doc_id = 0;
        foreach ( $lines as $line ) {
            if ( stripos( $line, '@' ) === 0 ) {
                $parts = explode( '=', $line );
                $key = array_shift( $parts );
                if ( $key == '@id' ) {
                    $doc_id = (int) $parts[0];
                }
                if ( $key == '@tags' || $key == '@metadata' ) {
                    $value = implode( '=', $parts );
                    $values = preg_split( '/\s*,\s*/', $value );
                    $metadata = array_merge( $metadata, $values );
                }
            }
            if (! $line ) break;
        }
        $limit = $app->param( 'limit' ) ? $app->param( 'limit' ) : 10;
        $limit = (int) $limit;
        $max = $limit;
        if ( $max ) $max++;
        $interests = [];
        $interests_original = [];
        if ( $type == 'interest' ) {
            $cookie_val = $app->cookie_val( $cookie_name );
            if ( $cookie_val ) {
                $interests = json_decode( $cookie_val, true );
                $interests_original = $interests;
            }
        }
        if ( $type == 'interest' ) {
            array_walk( $metadata, function( &$interest ){ $interest = escapeshellarg( $interest ); } );
            foreach ( $metadata as $meta ) {
                $count = isset( $interests[ $meta ] ) ? $interests[ $meta ] + 1 : 1;
                $interests[ $meta ] = $count;
            }
        }
        $condition = '';
        $workspace_ids = $app->param( 'workspace_ids' );
        $workspace_id = $app->param( 'workspace_id' );
        if ( $workspace_ids !== '' ) {
            $workspace_ids = preg_split( '/\s*,\s*/', $workspace_ids );
            $target_ids = [];
            foreach ( $workspace_ids as $id ) {
                $target_ids[] = (int) $id;
            }
            $target_ids = array_unique( $target_ids );
            if ( is_array( $target_ids ) && count( $target_ids ) ) {
                $workspace_ids = implode( ' ', $workspace_ids );
                $condition = " -attr " . escapeshellarg( "@workspace_id STROR ${workspace_ids}" );
            }
        } else if ( $workspace_id !== '' ) {
            $workspace_id = (int) $workspace_id;
            $condition = " -attr " . escapeshellarg( "@workspace_id STROR ${workspace_id}" );
        }
        $model = $app->param( 'model' );
        if ( $model ) {
            $condition = " -attr " . escapeshellarg( "@model STREQ ${model}" );
        }
        if ( $type != 'interest' ) {
            $command = "{$estcmd_path} search -vx -max {$max} -sim {$doc_id} {$condition} {$data_dir}";
        } else {
            $command = "{$estcmd_path} search -vx -max {$max} {$condition} {$data_dir} [SIMILAR]";
            $weight = $app->searchestraier_similar_weight;
            foreach ( $interests as $interest => $count ) {
                $count = $weight + $count;
                $command .= ' WITH ' . $count;
                $command .= " {$interest}";
            }
        }
        $res = shell_exec( $command );
        preg_match_all( "/<snippet>(.*?)<\/snippet>/s", $res, $snippets );
        $snippets = $snippets[1];
        $result = new SimpleXMLElement( $res );
        $records = $result->document;
        $results = [];
        $i = 0;
        foreach ( $records as $record ) {
            $result = [];
            $id = ( string )$record->attributes()->id;
            $attrs = $record->attribute;
            $doc_url = ( string )$record->attributes()->uri;
            $snippet = $snippets[ $i ];
            $snippet = str_replace( '<key', '<strong', $snippet );
            $snippet = str_replace( '</key>', '</strong>', $snippet );
            $snippet = str_replace( '<delimiter/>', '... ', $snippet );
            $snippet = preg_replace( '/ normal=".*?"/', '', $snippet );
            $i++;
            if ( $url_original == $doc_url ) {
                continue;
            }
            $result['snippet'] = $snippet;
            $result['uri'] = $doc_url;
            foreach( $attrs as $attr ) {
                $name = $attr->attributes()->name;
                $name = ( string ) $name;
                if ( strpos( $name, '_' ) === 0 ) continue;
                if ( strpos( $name, '@' ) === 0 ) {
                    $name = ltrim( $name, '@' );
                }
                $val = $attr->attributes()->value[ 0 ];
                $val = ( string ) $val;
                $result[ $name ] = $val;
            }
            $results[] = $result;
            if ( count( $results ) >= $limit ) {
                break;
            }
        }
        if ( $type == 'interest' ) {
            $_interests = json_encode( $interests, JSON_UNESCAPED_UNICODE );
            $i = 0;
            while ( strlen( $_interests ) > 4096 ) {
                array_pop( $interests );
                $_interests = json_encode( $interests, JSON_UNESCAPED_UNICODE );
                $i++;
                if ( $i > 200 ) {
                    $_interests = json_encode( $interests_original, JSON_UNESCAPED_UNICODE );
                    break;
                }
            }
            $app->bake_cookie( $cookie_name, $_interests, $expire, $path );
        }
        $app->print( json_encode( $results, JSON_UNESCAPED_UNICODE ), 'application/json; charset=utf-8', null, false );
    }
}
