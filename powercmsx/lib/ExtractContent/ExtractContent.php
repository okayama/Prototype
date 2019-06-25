<?php
class ExtractContent {
    /**
     * @var array
     */
    const DEFAULT_OPTIONS = [
        'threshold'          => 100,
        'min_length'         => 80,
        'decay_factor'       => 0.73,
        'continuous_factor'  => 1.62,
        'minimum_area'       => 1500,
        'content_length'     => 800,
        'punctuation_weight' => 10,
        'punctuations'       => '/([、。，．！？]|\.[^A-Za-z0-9]|,[^0-9]|!|\?)/u',
        'waste_expressions'  => '/Copyright | All Rights Reserved/iu',
        'dom_separator'      => '',
        'debug'              => false,
    ];

    /**
     * @var array
     */
    const CHARREF = [
        '&nbsp;'  => ' ',
        '&lt;'    => '<',
        '&gt;'    => '>',
        '&amp;'   => '&',
        '&laquo;' => "\xc2\xab",
        '&raquo;' => "\xc2\xbb",
    ];

    /**
     * target html string
     *
     * @var string
     */
    private $html    = '';
    public  $dom     = null;
    public  $title   = '';
    public  $content = '';

    /**
     * analyse options
     *
     * @var array
     */
    private $options = [];

    /**
     * ExtractContent constructor.
     *
     * @param string $html target html
     * @param array $options analyse options
     */
    public function __construct (string $html, array $options = []) {
        // libxml_use_internal_errors( true );
        $this->html = $html;
        try {
            $this->html = mb_convert_encoding($html, 'utf-8', [
                'UTF-7',
                'ISO-2022-JP',
                'UTF-8',
                'SJIS',
                'JIS',
                'eucjp-win',
                'sjis-win',
                'EUC-JP',
                'ASCII',
            ]);
        } catch (\Throwable $ex) {
            $this->html = mb_convert_encoding($html, 'utf-8', 'auto');
        }
        $this->options = $options + static::DEFAULT_OPTIONS;
    }

    /**
     * Update option value
     *
     * @param string $name option name
     * @param string $value option value
     */
    public function setOption (string $name, string $value) {
        $this->options[$name] = $value;
    }

    public function getTextContent () {
        $content = $this->get_main();
        $content = $this->title . "\n\n" . $content;
        $html_content = "<html><body>{$content}";
        $dom = new DomDocument();
        if (! $dom->loadHTML( mb_convert_encoding( $html_content, 'HTML-ENTITIES', 'utf-8' ) ) ) {
            return $content;
        }
        $elements = $dom->getElementsByTagName( 'img' );
        if ( $elements->length ) {
             for ( $i = 0; $i < $elements->length; $i++ ) {
                  $ele = $elements->item( $i );
                  $alt = $ele->getAttribute( 'alt' );
                  $text = $dom->createTextNode( $alt );
                  $parent = $ele->parentNode;
                  $parent->insertBefore( $text, $ele );
                  $parent->removeChild( $ele );
            }
        }
        $body_element = $dom->getElementsByTagName( 'body' );
        if ( $body_element->length ) {
            $content = $this->innerHTML( $body_element->item( 0 ) );
        }
        $content = strip_tags( $content );
        return $content;
    }

    public function get_main () {
        $data = $this->html;
        $dom = $this->dom;
        if (! $dom ) {
            $dom = new DomDocument();
            if (!$dom->loadHTML( mb_convert_encoding( $data, 'HTML-ENTITIES', 'utf-8' ) ) ) {
            }
            $this->dom = $dom;
        }
        $finder = new DomXPath( $dom );
        $main_element = $dom->getElementsByTagName( 'main' );
        $content = '';
        if ( $main_element->length ) {
            for ( $i = 0; $i < $main_element->length; $i++ ) {
                $ele = $main_element->item( $i );
                $content = $this->removeTags( $this->innerHTML( $ele ) );
                break;
            }
        } else {
            $content_length = $this->options['content_length'];
            // Yahoo! Japan
            $main_element = $finder->query( "//*[@class='article']" );
            if (! $main_element->length || $main_element->length != 1 ) {
                $main_element = $finder->query( "//*[@id='main']" );
            }
            if (! $main_element->length || $main_element->length != 1 ) {
                $main_element = $finder->query( "//*[@id='article']" );
            }
            if (! $main_element->length || $main_element->length != 1 ) {
                $main_element = $dom->getElementsByTagName( 'article' );
            }
            if ( $main_element->length == 1 ) {
                for ( $i = 0; $i < $main_element->length; $i++ ) {
                    $ele = $main_element->item( $i );
                    $content = $this->removeTags( $this->innerHTML( $ele ) );
                    break;
                }
            }
            if ( mb_strlen( $content ) < $content_length ) {
                $content = '';
            }
            if (! $content ) {
                $heading = $dom->getElementsByTagName( 'h1' );
                if ( $heading->length == 1 ) {
                    $ele = $heading->item(0);
                    if ( $ele->parentNode ) {
                        $parent = $ele->parentNode;
                        $content = $this->removeTags( $this->innerHTML( $parent ) );
                        if ( mb_strlen( $content ) < $content_length ) {
                            $content = '';
                            /*
                            $parent_cnt = 0;
                            $contentLength = mb_strlen( $content );
                            while ( $contentLength < $content_length ) {
                                $parent = $parent->parentNode;
                                $content = $this->removeTags( $this->innerHTML( $parent ) );
                                $contentLength = mb_strlen( $content );
                                $parent_cnt++;
                                if ( $parent_cnt > 4 ) {
                                    $contentLength = $content_length + 10;
                                }
                            }
                            */
                        }
                    }
                }
            }
        }
        $analysed = false;
        if (! $content ) {
            $result = $this->analyse();
            $content = $result[0];
            $analysed = true;
        }
        if ( $content ) {
            $html_content = "<html><body>{$content}";
            $_dom = new DomDocument();
            if (! $_dom->loadHTML( mb_convert_encoding( $html_content, 'HTML-ENTITIES', 'utf-8' ) ) ) {
                return $content;
            } else {
                $heading = $_dom->getElementsByTagName( 'h1' );
                if ( $heading->length ) {
                    for ( $i = 0; $i < $heading->length; $i++ ) {
                        $ele = $heading->item( $i );
                        $title = trim( $ele->textContent );
                        $children = $ele->childNodes;
                        if (! $title && $children->length ) {
                            $title = $this->get_title_from_node( $ele->childNodes, $title );
                        }
                        if ( $title ) $this->title = $title;
                        $parent = $ele->parentNode;
                        $parent->removeChild( $ele );
                        $childLength = 1;
                        while ( $childLength != 0 ) {
                            $childNodes = $parent->childNodes;
                            $childLength = $childNodes->length;
                            if ( $childLength ) {
                                $innerHTML = trim( $this->innerHTML( $parent ) );
                                if (! $innerHTML ) {
                                    $gParent = $parent->parentNode;
                                    $gParent->removeChild( $parent );
                                    $parent = $gParent;
                                } else {
                                    $childLength = 0;
                                }
                            }
                        }
                    }
                }
                // Social Widgets
                $parentULs = [];
                $iframes = $_dom->getElementsByTagName( 'iframe' );
                if ( $iframes->length ) {
                    for ( $i = 0; $i < $iframes->length; $i++ ) {
                        $ele = $iframes->item( $i );
                        $parent = $ele->parentNode;
                        $parentTag = $parent->tagName;
                        if ( $parentTag == 'li' ) {
                            $parent->removeChild( $ele );
                            $gParent = $parent->parentNode;
                            $gParent->removeChild( $parent );
                            $parentULs[] = $gParent;
                        } else {
                            $parent->removeChild( $ele );
                        }
                    }
                }
                $_finder = new DomXPath( $_dom );
                $classname = 'twitter-share-button';
                $elements = $_finder->query("//*[contains(@class, '$classname')]");
                if ( $elements->length ) {
                    for ( $i = 0; $i < $elements->length; $i++ ) {
                        $ele = $elements->item( $i );
                        $parent = $ele->parentNode;
                        $parentTag = $parent->tagName;
                        if ( $parentTag == 'li' ) {
                            $parent->removeChild( $ele );
                            $gParent = $parent->parentNode;
                            $gParent->removeChild( $parent );
                            $parentULs[] = $gParent;
                        } else {
                            $parent->removeChild( $ele );
                        }
                    }
                }
                foreach ( $parentULs as $UL ) {
                    if ( $UL->parentNode == null ) continue;
                    $innerHTML = trim( $this->innerHTML( $UL ) );
                    if (! $innerHTML ) {
                        $UL->parentNode->removeChild( $UL );
                    }
                }
                // topicpath, breadcrumb, date and navigation.
                $elements = $_dom->getElementsByTagName( '*' );
                if ( $elements->length ) {
                    for ( $i = 0; $i < $elements->length; $i++ ) {
                        $ele = $elements->item( $i );
                        $className = strtolower( $ele->getAttribute( 'class' ) );
                        if (! $className ) $className = '';
                        $className = strtolower( preg_replace( '/[^a-z]*/', '', $className ) );
                        $elementId = strtolower( $ele->getAttribute( 'id' ) );
                        if (! $elementId ) $elementId = '';
                        $elementId = preg_replace( '/[^a-z]*/', '', $elementId );
                        if ( stripos( $className, 'topicpath' ) !== false
                            || stripos( $className, 'breadcrumb' ) !== false
                            || stripos( $className, 'pankuzu' ) !== false
                            || stripos( $elementId, 'topicpath' ) !== false
                            || stripos( $elementId, 'breadcrumb' ) !== false
                            || stripos( $elementId, 'pankuzu' ) !== false
                            || stripos( $className, 'twitter' ) !== false
                            || stripos( $className, 'facebook' ) !== false
                            || stripos( $className, 'hatena' ) !== false
                            || stripos( $className, 'bookmark' ) !== false
                            || $className == 'print'
                            || $className == 'mail' ) {
                            if ( $ele->parentNode != null ) {
                                if ( stripos( $className, 'twitter' ) !== false
                                    || stripos( $className, 'facebook' ) !== false
                                    || stripos( $className, 'hatena' ) !== false
                                    || stripos( $className, 'bookmark' ) !== false
                                    || $className == 'print' || $className == 'mail'
                                    && $ele->parentNode->parentNode != null ) {
                                    $ele->parentNode->parentNode->removeChild( $ele->parentNode );
                                } else {
                                    $ele->parentNode->removeChild( $ele );
                                }
                            }
                        } else if ( stripos( $className, 'nav' ) === 0 || stripos( $elementId, 'nav' ) === 0 ) {
                            if ( $ele->parentNode != null ) {
                                $ele->parentNode->removeChild( $ele );
                            }
                        } else if ( $className == 'date' || $elementId == 'date' ) {
                            if ( $ele->parentNode != null ) {
                                $ele->parentNode->removeChild( $ele );
                            }
                        }
                        $tag_name = $ele->tagName;
                        // form elements
                        if ( $tag_name == 'input' || $tag_name == 'textarea' || $tag_name == 'select' ) {
                            if ( $parent = $ele->parentNode ) {
                                if ( $gParent = $parent->parentNode ) {
                                    if ( $gParent ) {
                                        $gParent->removeChild( $parent );
                                    }
                                }
                            }
                        }
                    }
                }
                $body_element = $_dom->getElementsByTagName( 'body' );
                if ( $body_element->length ) {
                    $content = $this->innerHTML( $body_element->item( 0 ) );
                }
                if (! $analysed ) {
                    // finish analyse
                    $this->html = $content;
                    $result = $this->analyse();
                    $content = $result[0];
                    // clean up html
                    $html_content = "<html><body>{$content}";
                    $_dom = new DomDocument();
                    if (! $_dom->loadHTML( mb_convert_encoding( $html_content, 'HTML-ENTITIES', 'utf-8' ) ) ) {
                    } else {
                        $body_element = $_dom->getElementsByTagName( 'body' );
                        if ( $body_element->length ) {
                            $content = $this->innerHTML( $body_element->item( 0 ) );
                        }
                    }
                }
            }
        }
        $this->content = $content;
        return $content;
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

    /**
     * @return array
     */
    public function analyse () {
        if ($this->isFramesetHtml() || $this->isRedirectHtml()) {
            return [
                '',
                $this->extractTitle($this->html),
            ];
        }
        $lib = LIB_DIR . 'simple_html_dom' . DS . 'simple_html_dom.php';
        require_once( $lib );
        // require_once( 'simple_html_dom.php' );
        $targetHtml = $this->html;
        // Title
        // libxml_use_internal_errors( true );
        $title = $this->extractTitle($targetHtml);
        if ( stripos( $targetHtml, '<body' ) !== false ) {
            $targetHtml = preg_replace( '/^.*?<body.*?>(.*$)/si', '$1', $targetHtml );
        }
        $targetHtml = $this->extractAdSection($targetHtml);
        $targetHtml = $this->removeTags($targetHtml);
        $targetHtml = $this->eliminateUselessTags($targetHtml);
        $targetHtml = $this->hBlockIncludingTitle($title, $targetHtml);
        // Extract text blocks
        $factor = 1.0;
        $continuous = 1.0;
        $body = '';
        $score = 0;
        $bodyList = [];
        $contentCounter = 0;
        $allContents = [];
        $matchContents = [];
        $list = preg_split('/<\/?(?:div|center)[^>]*>|<p\s*[^>]*class\s*=\s*["\']?(?:posted|plugin-\w+)[\'"]?[^>]*>/u', $targetHtml);
        // $list = preg_split('/<\/?(?:div|center|td)[^>]*>|<p\s*[^>]*class\s*=\s*["\']?(?:posted|plugin-\w+)[\'"]?[^>]*>/u', $targetHtml);
        $minimum_area = $this->options['minimum_area'];
        foreach ($list as $block) {
            if (empty($block)) {
                continue;
            }
            $block = trim($block);
            if ($this->hasOnlyTags($block)) {
                continue;
            }
            $contentCounter++;
            if (! empty($body) > 0) {
                $continuous /= $this->options['continuous_factor'];
            }
            $allContents[ $contentCounter ] = $block;
            // check link list
            $notLinked = $this->eliminateLink($block);
            if (strlen($notLinked) < $this->options['min_length']) {
                $has_large_image = false;
                if ( stripos( $block, '<img' ) !== false ) {
                    $html_content = "<html><body>{$block}";
                    $parser = str_get_html( $html_content );
                    $elements = $parser->find( 'img' );
                    foreach ( $elements as $element ) {
                        list( $w, $h ) = [0,0];
                        if ( isset( $element->width ) ) {
                            $w = $element->width;
                        }
                        if ( isset( $element->height ) ) {
                            $h = $element->height;
                        }
                        $area = $h * $w;
                        if ( $area > $minimum_area ) {
                            $has_large_image = true;
                            break;
                        }
                    }
                }
                if (! $has_large_image ) continue;
            }
            // calculate score
            $punctuations = preg_split($this->options['punctuations'], $notLinked);
            $c = strlen($notLinked) + count($punctuations) * $this->options['punctuation_weight'] * $factor;
            $factor *= $this->options['decay_factor'];
            $wasteBlock = preg_split($this->options['waste_expressions'], $block);
            $amazonBlock = preg_split('/amazon[a-z0-9\.\/\-\?&]+-22/iu', $block);
            $notBodyRate = count($wasteBlock) + count($amazonBlock) / 2.0;
            if ($notBodyRate > 0) {
                $c *= 0.72 ** $notBodyRate;
            }
            $c1 = $c * $continuous;
            if ($this->options['debug']) {
                $notLinkedCount = strlen($notLinked);
                $stripTags = substr(strip_tags($block), 0, 100);
                echo "----- {$c}*{$continuous}={$c1} {$notLinkedCount} \n{$stripTags}\n";
            }
            // extract block, add score
            if ($c1 > $this->options['threshold']) {
                $body .= $block . "\n";
                $score += $c1;
                $continuous = $this->options['continuous_factor'];
                $matchContents[ $contentCounter ] = $block;
            } elseif ($c > $this->options['threshold']) {
                $bodyList[] = [
                    $body,
                    $score,
                ];
                $body = $block . "\n";
                $matchContents[ $contentCounter ] = $block;
                $score = $c;
                $continuous = $this->options['continuous_factor'];
            }
        }
        $bodyList[] = [
            $body,
            $score,
        ];
        $body = array_reduce($bodyList, function ($a, $b) {
            if ($a[1] >= $b[1]) {
                return $a;
            } else {
                return $b;
            }
        });
        if (! empty ( $matchContents ) ) {
            $contentsRange = array_keys( $matchContents );
            $max = max($contentsRange);
            $min = min($contentsRange);
            $max++;
            $results = [];
            for ( $i = $min; $i < $max; $i++ ) {
                $results[] = $allContents[$i];
            }
            $contentsRerult = implode( "\n", $results );
            $contentsRerult = $this->removeTags($contentsRerult);
            $body[0] = trim( $contentsRerult );
        } else {
            $body[0] = '';
        }
        $this->title = $title;
        $this->content = $body[0];
        return [
            //trim(strip_tags($body[0])),
            $body[0],
            $title,
        ];
    }

    /**
     * @return bool
     */
    private function isFramesetHtml (): bool {
        return preg_match('/<\/frameset>/i', $this->html);
    }

    /**
     * @return bool
     */
    private function isRedirectHtml (): bool {
        return preg_match('/<meta\s+http-equiv\s*=\s*["\']?refresh[\'"]?[^>]*url/i', $this->html);
    }

    /**
     * @param string $html
     *
     * @return string
     */
    private function extractTitle (string $html): string {
        $result = '';
        if (preg_match('/<title[^>]*>\s*(.*?)\s*<\/title\s*>/iu', $html, $matches)) {
            $result = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES);
        }
        return $result;
    }

    /**
     * @param string $html
     *
     * @return string
     */
    private function extractAdSection (string $html): string {
        $html = preg_replace('/<!--\s*google_ad_section_start\(weight=ignore\)\s*-->.*?<!--\s*google_ad_section_end.*?-->/su', '', $html);
        if (preg_match('/<!--\s*google_ad_section_start[^>]*-->/u', $html)) {
            preg_match_all('/<!--\s*google_ad_section_start[^>]*-->(.*?)<!--\s*google_ad_section_end.*?-->/su', $html, $matches);
            $html = implode("\n", $matches[1]);
        }
        return $html;
    }

    /**
     * @param string $title
     * @param string $html
     *
     * @return string
     */
    private function hBlockIncludingTitle (string $title, string $html): string {
        return preg_replace_callback('/(<h\d\s*>\s*(.*?)\s*<\/h\d\s*>)/iu', function ($match) use ($title) {
            if (strlen($match[2]) >= 3 && strpos($title, $match[2]) !== false) {
                return '<div>' . $match[2] . '</div>';
            }
            return $match[1];
        }, $html);
    }

    /**
     * @param string $html
     *
     * @return bool
     */
    private function hasOnlyTags (string $html): bool {
        if ( stripos( $html, '<img' ) !== false ) return false;
        $html = preg_replace('/<[^>]*>/isu', '', $html);
        $html = str_replace('&nbsp;', '', $html);
        $html = trim($html);
        return strlen($html) === 0;
    }

    private function removeTags (string $html): string {
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

    /**
     * @param string $html
     *
     * @return string
     */
    private function eliminateUselessTags (string $html): string {
        // eliminate useless symbols
        $html = preg_replace('/[\342\200\230-\342\200\235]|[\342\206\220-\342\206\223]|[\342\226\240-\342\226\275]|[\342\227\206-\342\227\257]|\342\230\205|\342\230\206/u', '', $html);
        // eliminate useless html tags
        $html = preg_replace('/<(script|style|select|noscript)[^>]*>.*?<\/\1\s*>/isu', '', $html);
        $html = preg_replace('/<!--.*?-->/su', '', $html);
        $html = preg_replace('/<![A-Za-z].*?>/u', '', $html);
        $html = preg_replace('/<div\s[^>]*class\s*=\s*[\'"]?alpslab-slide["\']?[^>]*>.*?<\/div\s*>/su', '', $html);
        $html = preg_replace('/<div\s[^>]*(id|class)\s*=\s*[\'"]?\S*more\S*["\']?[^>]*>/iu', '', $html);
        return $html;
    }

    /**
     * @param string $html
     *
     * @return string
     */
    private function eliminateLink (string $html): string {
        $count = 0;
        $notLinked = preg_replace_callback('/<a\s[^>]*>.*?<\/a\s*>/ius', function () use (&$count) {
            $count++;
            return '';
        }, $html);
        $notLinked = preg_replace('/<form\s[^>]*>.*?<\/form\s *>/imsu', '', $notLinked);
        $notLinked = strip_tags($notLinked);
        if (strlen($notLinked) < 20 * $count || $this->isLinkList($html)) {
            return '';
        }
        return $notLinked;
    }

    /**
     * @param string $html
     *
     * @return bool
     */
    private function isLinkList (string $html): bool {
        if (preg_match('/<(?:ul|dl|ol)(.+?)<\/(?:ul|dl|ol)>/isu', $html, $matched)) {
            $listPart = $matched[1];
            $outside = preg_replace('/<(?:ul|dl)(.+?)<\/(?:ul|dl)>/isu', '', $html);
            $outside = preg_replace('/<.+?>/su', '', $outside);
            $outside = preg_replace('/\s+/su', ' ', $outside);
            $list = preg_split('/<li[^>]*>/u', $listPart);
            array_shift($list);
            $rate = $this->evaluateList($list);
            if ($rate == 1) {
                return false;
            }
            return strlen($outside) <= (strlen($html) / (45 / $rate));
        }
        return false;
    }

    /**
     * @param array $list
     *
     * @return float
     */
    private function evaluateList (array $list): float {
        if (empty($list)) {
            return 1;
        }
        $hit = 0;
        foreach ($list as $line) {
            if (preg_match('/<a\s+href=([\'"]?)([^"\'\s]+)\1/isu', $line)) {
                $hit++;
            }
        }
        return 9 * (1.0 * $hit / count($list)) ** 2 + 1;
    }
}
