<?php
    require_once( __DIR__ . DIRECTORY_SEPARATOR .'class.Prototype.php' );
    $app = new Prototype();
    require_once( LIB_DIR . 'PAML' . DS .'class.paml.php' );
    $ctx = new PAML();
    $ctx->prefix = 'mt';
    $ctx->app = $app;
    $ctx->default_component = $app;
    $ctx->csv_delimiter = $app->csv_delimiter;
    $ctx->force_compile = true;
    $ctx->init();
    foreach ( $app->template_paths as $tmpl_dir ) {
        $ctx->include_paths[ $tmpl_dir ] = true;
    }
    $ctx->vars['appname'] = 'PowerCMS X';
    $ctx->vars['html_head'] = '<style>.nav-top,.navbar-brand,.dropdown-menu, .nav-top a, footer{ background-color: black !important; color: white !important; }</style>';
    $language = 'en';
    if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) )
        $language = substr( $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2 );
    $ctx->language = $language;
    $ctx->vars['user_language'] = $language;
    $locale_dir = __DIR__ . DS . 'locale';
    $locale = $locale_dir . DS . $language . '.json';
    if ( file_exists( $locale ) ) {
        $dict = json_decode( file_get_contents( $locale ), true );
        $app->dictionary[ $language ] = $dict;
    }
    $app->ctx = $ctx;
    $ctx->vars['page_title'] = $app->translate( 'PowerCMS X System Check' );
    $error_messages = [];
    $warning_messages = [];
    $cfg = __DIR__ . DS . 'db-config.php';
    $db_connect = false;
    include_once( $cfg );
    $max_allowed_packet = '';
    $pdo_mysql_version = '';
    $php_version = phpversion();
    $available = $php_version >= 7;
    if (! $available ) {
        $error_messages[] = $app->translate( 'The version of PHP installed on your server (%s) is lower than the minimum supported version %s.', [ $php_version, '7.0' ] );
    }
    if ( file_exists( $cfg ) ) {
        $dbhost = PADO_DB_HOST;
        $dbname = PADO_DB_NAME;
        $dbport = PADO_DB_PORT;
        $dbcharset = 'utf8mb4';
        $dsn = "mysql:host={$dbhost};dbname={$dbname};charset={$dbcharset};port={$dbport}";
        try {
            $dbh = new PDO( $dsn , PADO_DB_USER, PADO_DB_PASSWORD );
            $db_connect = true;
            $sql = "SHOW VARIABLES LIKE 'max_allowed_packet'";
            $max_allowed_packet = $dbh->query( $sql )->fetchAll();
            if ( isset( $max_allowed_packet[0] ) ) {
                $max_allowed_packet = $max_allowed_packet[0]['Value'];
                if ( $max_allowed_packet && ctype_digit( $max_allowed_packet ) ) {
                    $max_allowed_packet = $max_allowed_packet / 1014 /1024;
                    $max_allowed_packet = (int) $max_allowed_packet;
                } else if ( stripos( 'MB', $max_allowed_packet ) !== false ) {
                    $max_allowed_packet = str_replace( 'MB', '', strtoupper( $max_allowed_packet ) );
                }
            }
            $pdo_mysql_version = $dbh->query('select version()')->fetchColumn();
            if ( $pdo_mysql_version <= 5.5 ) {
                $error_messages[] = $app->translate( 'The version of MySQL installed on your server (%s) is lower than the minimum supported version %s.', [ $pdo_mysql_version, '5.6' ] );
            }
            $tbl_name = DB_PREFIX . 'table';
            $pcmsx_table = $dbh->query("SHOW TABLES LIKE '{$tbl_name}'")->fetchColumn();
            if ( $pcmsx_table !== false && $pcmsx_table == 'mt_table' ) {
                $app->init();
                $app->ctx = $ctx;
                $ctx->vars['already_installed'] = 1;
                if ( $app->user() && $app->user()->is_superuser ) {
                } else {
                    $ctx->vars['already_installed'] = 1;
                    $ctx->vars['do_not_show_result'] = 1;
                    $app->print( $app->build_page( 'pt_check.tmpl' ) );
                }
            }
        } catch ( PDOException $e ){
            $error_messages[] = $app->translate( 'MySQL connection failed( %s ).', $e->getMessage() );
        }
    } else {
        $error_messages[] = $app->translate( 'MySQL connection check was skipped because db-config.php does not exist.' );
    }
    ob_start();
    phpinfo();
    $res = ob_get_clean();
    $lib = LIB_DIR . 'simple_html_dom' . DS . 'simple_html_dom.php';
    require_once( $lib );
    $parser = str_get_html( $res );
    $elements = $parser->find( 'a[name=module_dom]' );
    $dom = false;
    $libxml_version = '';
    $libxml_version_ok = false;
    foreach ( $elements as $element ) {
        if ( trim( strtolower( $element->plaintext ) ) == 'dom' ) {
            $dom = true;
            $table = $element->parentNode()->nextSibling()->childNodes();
            foreach ( $table as $cell ) {
                $th = $cell->firstChild();
                $td = $th->nextSibling();
                $name = strtolower( trim( $th->plaintext ) );
                $value = strtolower( trim( $td->plaintext ) );
                if ( $name == 'dom/xml' && $value != 'enabled' ) {
                    $error_messages[] = $app->translate( '%s is not enabled.', 'DOM/XML' );
                    $dom = false;
                }
                if ( $name == 'libxml version' ) {
                    $libxml_version = $value;
                    if ( $value < "2.7.8" ) {
                        $error_messages[] = $app->translate( 'libxml Version 2.7.8 or greater is needed.' );
                        $dom = false;
                    } else {
                        $libxml_version_ok = true;
                    }
                }
                if ( $name == 'html support' && $value != 'enabled' ) {
                    $dom = false;
                    $error_messages[] = $app->translate( '%s is not enabled.', 'HTML Support' );
                }
            }
        }
    }
    if (! $dom ) {
        $error_messages[] = $app->translate( '%s is not enabled.', 'dom' );
    }
    $elements = $parser->find( 'a[name=module_pdo_mysql]' );
    $pdo_mysql = false;
    foreach ( $elements as $element ) {
        if ( trim( strtolower( $element->plaintext ) ) == 'pdo_mysql' ) {
            $pdo_mysql = true;
            $table = $element->parentNode()->nextSibling()->childNodes();
            foreach ( $table as $cell ) {
                $th = $cell->firstChild();
                $td = $th->nextSibling();
                $name = strtolower( trim( $th->plaintext ) );
                $value = strtolower( trim( $td->plaintext ) );
                if ( $name == 'pdo driver for mysql' && $value != 'enabled' ) {
                    $error_messages[] = $app->translate( '%s is not enabled.', 'PDO MySQL' );
                    $pdo_mysql = false;
                    break;
                }
            }
        }
    }
    if (! $pdo_mysql ) {
        $error_messages[] = $app->translate( '%s is not enabled.', 'PDO MySQL' );
    }
    $elements = $parser->find( 'a[name=module_json]' );
    $json = false;
    $json_version = '';
    foreach ( $elements as $element ) {
        if ( trim( strtolower( $element->plaintext ) ) == 'json' ) {
            $json = true;
            $table = $element->parentNode()->nextSibling()->childNodes();
            foreach ( $table as $cell ) {
                $th = $cell->firstChild();
                $td = $th->nextSibling();
                $name = strtolower( trim( $th->plaintext ) );
                $value = strtolower( trim( $td->plaintext ) );
                if ( $name == 'json support' && $value != 'enabled' ) {
                    $error_messages[] = $app->translate( '%s is not enabled.', 'json' );
                    $json = false;
                }
                if ( $name == 'json version' ) {
                    $json_version = $value;
                }
            }
        }
    }
    if (! $json ) {
        $error_messages[] = $app->translate( '%s is not enabled.', 'json' );
    }
    $elements = $parser->find( 'a[name=module_simplexml]' );
    $simplexml = false;
    foreach ( $elements as $element ) {
        if ( trim( strtolower( $element->plaintext ) ) == 'simplexml' ) {
            $simplexml = true;
            $table = $element->parentNode()->nextSibling()->childNodes();
            foreach ( $table as $cell ) {
                $th = $cell->firstChild();
                $td = $th->nextSibling();
                $name = strtolower( trim( $th->plaintext ) );
                $value = strtolower( trim( $td->plaintext ) );
                if ( $name == 'simplexml support' && $value != 'enabled' ) {
                    $simplexml = false;
                    break;
                }
            }
        }
    }
    if (! $simplexml ) {
        $error_messages[] = $app->translate( '%s is not enabled.', 'SimpleXML' );
    }
    $elements = $parser->find( 'a[name=module_gd]' );
    $gd = false;
    $gd_supports = [];
    $gd_version = '';
    foreach ( $elements as $element ) {
        if ( trim( strtolower( $element->plaintext ) ) == 'gd' ) {
            $gd = true;
            $table = $element->parentNode()->nextSibling()->childNodes();
            foreach ( $table as $cell ) {
                $th = $cell->firstChild();
                $td = $th->nextSibling();
                $name = strtolower( trim( $th->plaintext ) );
                $value = strtolower( trim( $td->plaintext ) );
                if ( $name == 'gd support' && $value != 'enabled' ) {
                    $gd = false;
                    break;
                }
                if ( $name == 'gif read support' && $value == 'enabled' ) {
                    $gd_supports[] = 'GIF';
                }
                if ( $name == 'jpeg support' && $value == 'enabled' ) {
                    $gd_supports[] = 'JPEG';
                }
                if ( $name == 'png support' && $value == 'enabled' ) {
                    $gd_supports[] = 'PNG';
                }
                if ( $name == 'gd version' ) {
                    $gd_version = $value;
                }
            }
        }
    }
    if (! $gd ) {
        $error_messages[] = $app->translate( '%s is not enabled.', 'gd' );
    }
    $elements = $parser->find( 'a[name=module_zip]' );
    $zip = false;
    $zip_version = '';
    foreach ( $elements as $element ) {
        if ( trim( strtolower( $element->plaintext ) ) == 'zip' ) {
            $zip = true;
            $table = $element->parentNode()->nextSibling()->childNodes();
            foreach ( $table as $cell ) {
                $th = $cell->firstChild();
                $td = $th->nextSibling();
                $name = strtolower( trim( $th->plaintext ) );
                $value = strtolower( trim( $td->plaintext ) );
                if ( $name == 'zip' && $value != 'enabled' ) {
                    $zip = false;
                }
                if ( $name == 'zip version' ) {
                    $zip_version = $value;
                }
            }
        }
    }
    if (! $zip ) {
        $error_messages[] = $app->translate( '%s is not enabled.', 'zip' );
    }
    $elements = $parser->find( 'a[name=module_xdiff]' );
    $xdiff = false;
    $xdiff_version = '';
    foreach ( $elements as $element ) {
        if ( trim( strtolower( $element->plaintext ) ) == 'xdiff' ) {
            $xdiff = true;
            $table = $element->parentNode()->nextSibling()->childNodes();
            foreach ( $table as $cell ) {
                $th = $cell->firstChild();
                $td = $th->nextSibling();
                $name = strtolower( trim( $th->plaintext ) );
                $value = strtolower( trim( $td->plaintext ) );
                if ( $name == 'xdiff support' && $value != 'enabled' ) {
                    $xdiff = false;
                }
                if ( $name == 'extension version' ) {
                    $xdiff_version = $value;
                }
            }
        }
    }
    if (! $xdiff ) {
        $warning_messages[] = $app->translate( '%s is not enabled.', 'xdiff' );
    }
    $elements = $parser->find( 'a[name=module_memcached]' );
    $memcached = false;
    $memcached_version = '';
    foreach ( $elements as $element ) {
        if ( trim( strtolower( $element->plaintext ) ) == 'memcached' ) {
            $memcached = true;
            $table = $element->parentNode()->nextSibling()->childNodes();
            foreach ( $table as $cell ) {
                $th = $cell->firstChild();
                $td = $th->nextSibling();
                $name = strtolower( trim( $th->plaintext ) );
                $value = strtolower( trim( $td->plaintext ) );
                if ( $name == 'memcached support' && $value != 'enabled' ) {
                    $memcached = false;
                }
                if ( $name == 'version' ) {
                    $memcached_version = $value;
                }
            }
        }
    }
    if (! $memcached ) {
        $warning_messages[] = $app->translate( '%s is not enabled.', 'memcached' );
    }
    $max_input_vars = ini_get( 'max_input_vars' );
    if ( $max_input_vars <= 2000 ) {
        $warning_messages[] = $app->translate( "( %s ) '%s' recommended value is %s or more.", ['php.ini', 'max_input_vars', 2000 ] );
    }
    if ( $max_allowed_packet && $max_allowed_packet < 16 ) {
        $warning_messages[] = $app->translate( "( %s ) '%s' recommended value is %s or more.", ['MySQL', 'max_allowed_packet', '16MB' ] );
    }
    $normalizer = true;
    if (! function_exists( 'normalizer_normalize' ) ) {
        $normalizer = false;
        $warning_messages[] = $app->translate( '%s is not enabled.', 'normalizer' );
    }
    $error_messages = array_unique( $error_messages );
    $warning_messages = array_unique( $warning_messages );
    $ctx->vars['error_messages'] = $error_messages;
    $ctx->vars['warning_messages'] = $warning_messages;
    $ctx->vars['php_version'] = $php_version;
    $ctx->vars['version_ok'] = $available;
    $ctx->vars['libxml_version'] = $libxml_version;
    $ctx->vars['libxml_version_ok'] = $libxml_version_ok;
    $ctx->vars['pdo_mysql'] = $pdo_mysql;
    $ctx->vars['pdo_mysql_version'] = $pdo_mysql_version;
    $ctx->vars['json'] = $json;
    $ctx->vars['json_version'] = $json_version;
    $ctx->vars['simplexml'] = $simplexml;
    $ctx->vars['gd'] = $gd;
    $ctx->vars['gd_version'] = $gd_version;
    $ctx->vars['gd_supports'] = $gd_supports;
    $ctx->vars['zip'] = $zip;
    $ctx->vars['zip_version'] = $zip_version;
    $ctx->vars['xdiff'] = $xdiff;
    $ctx->vars['xdiff_version'] = $xdiff_version;
    $ctx->vars['memcached'] = $memcached;
    $ctx->vars['memcached_version'] = $memcached_version;
    $ctx->vars['max_input_vars'] = $max_input_vars;
    $ctx->vars['db_connect'] = $db_connect;
    $ctx->vars['normalizer'] = $normalizer;
    $ctx->vars['max_allowed_packet'] = $max_allowed_packet;
    $app->print( $app->build_page( 'pt_check.tmpl' ) );