<?php
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php' );
class SiteMap extends PTPlugin {

    private $icon_base = '';
    private $contains_assets = 0;
    private $contains_attachmentfiles = 0;
    private $contains_files = 0;
    private $contains_thumbnails = 0;
    public  $target_blank = ['application/javascript', 'application/json', 'application/pdf'];

    function __construct () {
        parent::__construct();
    }

    function get_sitemap_tree ( $app ) {
        setlocale( LC_ALL, 'ja_JP.UTF-8' );
        $app->get_scheme_from_db( 'urlinfo' );
        $prototype_path = $app->ctx->vars['prototype_path'];
        $this->icon_base = "{$prototype_path}plugins/SiteMap/assets/icons/";
        $params = ['group' => ['dirname'], 'count' => 'dirname'];
        $workspace_id = 0;
        $terms = [];
        if ( $app->workspace() ) {
            $workspace_id = $app->workspace()->id;
            $terms['workspace_id'] = $workspace_id;
        }
        $this->contains_assets = $this->get_config_value( 'sitemap_contains_assets', $workspace_id );
        $this->contains_attachmentfiles = $this->get_config_value( 'sitemap_contains_attachmentfiles', $workspace_id );
        $this->contains_files = $this->get_config_value( 'sitemap_contains_files', $workspace_id );
        $this->contains_thumbnails = $this->get_config_value( 'sitemap_contains_thumbnails', $workspace_id );
        $extra = ' AND urlinfo_dirname IS NOT NULL AND urlinfo_dirname!= \'\' ';
        if (! $this->contains_thumbnails ) {
            $extra .= ' AND ( urlinfo_meta_id=0 OR urlinfo_meta_id IS NULL )';
        }
        if (! $this->contains_files ) {
            if ( $this->contains_assets && $this->contains_attachmentfiles ) {
                $extra .= ' AND ( urlinfo_class !=\'file\' OR urlinfo_model=\'asset\' OR urlinfo_model=\'attachmentfile\' ) ';
            } else if ( $this->contains_assets ) {
                $extra .= ' AND ( urlinfo_class !=\'file\' OR urlinfo_model=\'asset\' ) ';
            } else {
                $extra .= ' AND urlinfo_class!=\'file\' ';
            }
        }
        $extra .= ' AND urlinfo_delete_flag=0 ';
        $groups = $app->db->model( 'urlinfo' )->count_group_by( $terms, $params, $extra );
        $directories = [];
        if (! count( $groups ) ) {
            $site_url = $app->get_config( 'site_url' );
            $dirname = $site_url->value;
            $depth = mb_substr_count( $dirname, '/' ) - 3;
            $directories[ $depth ] = [ $dirname ];
        } else {
            foreach ( $groups as $group ) {
                $dirname = $group['urlinfo_dirname'];
                $depth = mb_substr_count( $dirname, '/' ) - 3;
                if ( isset( $directories[ $depth ] ) ) {
                    $directories[ $depth ][] = $dirname;
                } else {
                    $directories[ $depth ] = [ $dirname ];
                }
            }
        }
        $directories = $this->build_tree( $directories );
        ksort( $directories );
        $tree = [];
        foreach ( $directories as $depth => $dirs ) {
            if ( isset( $directories[ $depth + 1 ] ) ) {
                $children = $directories[ $depth + 1 ];
                foreach ( $dirs as $dir ) {
                    $child_dirs = [];
                    foreach ( $children as $child ) {
                        $parent = dirname( $child ) . '/';
                        if ( $dir == $parent ) {
                            $child_dirs[] = $child;
                        }
                    }
                    $tree[ $dir ] = $child_dirs;
                }
            }
        }
        $root_dirs = $directories[0];
        $sitemap = $this->get_dir( $app, $root_dirs, $tree, $workspace_id, 0, true );
        header( 'Content-type: application/json' );
        echo json_encode( $sitemap );
        exit();
    }

    function template_source_dashboard ( $cb, $app, $tmpl ) {
        $prototype_path = $app->ctx->vars['prototype_path'];
        $style = "<link rel=\"stylesheet\" href=\"{$prototype_path}plugins/SiteMap/assets/js/themes/default/style.css\" />";
        $app->ctx->vars['html_head'] = isset( $app->ctx->vars['html_head'] )
                                     ? $app->ctx->vars['html_head'] . $style : $style;
        return true;
    }

    function get_dir ( $app, $list, $dirs, $workspace_id, $counter, $root = false ) {
        $admin_url = $app->admin_url;
        $image_icon = $this->icon_base . 'image.png';
        $file_icon = $this->icon_base . 'file.png';
        $edit_label = $app->translate( 'Edit' );
        $permissions = $app->permissions();
        $tree = array();
        $length = count( $list );
        for( $i = 0; $i < $length ; $i++ ) {
            $sub = [];
            if( isset( $dirs[ $list[ $i ] ] ) &&
                $sub = $this->get_dir( $app, $dirs[ $list[ $i ] ], $dirs,
                                       $workspace_id, $counter ) ) {
                $url = $list[ $i ];
                $text = $url;
                $counter++;
                if (! $root ) {
                    $text = basename( $text );
                    //$tree[] = ['text' => $text, 'children' => $sub,
                    //           'icon' => 'jstree-folder'];
                } else {
                    //$tree[] = ['text' => $text, 'children' => $sub,
                    //           'a_attr' => ['href' => $url, 'target' => '_blank' ],
                    //           'icon' => 'jstree-folder'];
                }
            }
            $url = $list[ $i ];
            $terms = ['dirname' => $url ];
            if ( $workspace_id ) {
                $terms['workspace_id'] = $workspace_id;
            }
            $extra = '';
            if (! $this->contains_thumbnails ) {
                $extra .= ' AND ( urlinfo_meta_id=0 OR urlinfo_meta_id IS NULL ) ';
            }
            if (! $this->contains_files ) {
                if ( $this->contains_assets && $this->contains_attachmentfiles ) {
                    $extra .= ' AND ( urlinfo_class !=\'file\' OR urlinfo_model=\'asset\' OR urlinfo_model=\'attachmentfile\' ) ';
                } else if ( $this->contains_assets ) {
                    $extra .= ' AND ( urlinfo_class !=\'file\' OR urlinfo_model=\'asset\' ) ';
                } else {
                    $extra .= ' AND urlinfo_class!=\'file\' ';
                }
            }
            $extra .= ' AND urlinfo_delete_flag=0 ';
            $urls = $app->db->model( 'urlinfo' )->load( $terms, [], '*', $extra );
            $children = [];
            foreach ( $urls as $obj ) {
                $file_url = $obj->url;
                $file_url = basename( $file_url );
                $counter++;
                $icon = strpos( $obj->mime_type, 'image' ) === 0 ? $image_icon : $file_icon;
                $child = ['text' => $file_url,
                       'a_attr' => ['href' => $obj->url ],
                       'icon' => $icon ];
                if ( strpos( $obj->mime_type, 'text' ) === 0 ||
                    strpos( $obj->mime_type, 'image' ) === 0 || in_array( $obj->mime_type, $this->target_blank ) ) {
                    $child['a_attr']['target'] = '_blank';
                }
                $can_edit = false;
                if ( $obj->model && $obj->object_id ) {
                    $object_id = $obj->object_id;
                    $object_model = $obj->model;
                    $table = $app->get_table( $object_model );
                    $primary = $table->primary;
                    $_obj = $app->db->model( $object_model )->load(
                            (int)$object_id, [],
                            'id,status,created_by,user_id,status,workspace_id,' . $primary );
                    if ( $_obj ) {
                        $params = "?__mode=view&_type=edit&_model={$object_model}&id={$object_id}";
                        $can_edit = $app->user()->is_superuser ? true : false;
                        if ( $ws_id = $obj->workspace_id ) {
                            $params .= "&workspace_id=$ws_id";
                            if (! $can_edit && isset( $permissions[(int)$ws_id ] ) ) {
                                if ( in_array( 'workspace_admin', $permissions[(int)$ws_id ] ) ) {
                                    $can_edit = true;
                                }
                            }
                        }
                        if (! $can_edit ) {
                            $can_edit = $app->can_do( $obj->model, 'edit', $_obj );
                        }
                        $is_published = $obj->is_published;
                        if (! $can_edit && ! $is_published && $obj->publish_file == 6 ) {
                            if ( $_obj->has_column( 'status' ) ) {
                                $status_published = $app->status_published( $object_model );
                                if ( $_obj->status == $status_published ) {
                                    $is_published = true;
                                }
                            }
                        }
                        if (! $can_edit && ! $is_published ) continue;
                        $label = $_obj->$primary ? $_obj->$primary : '(null)';
                        $child['a_attr']['title'] = $label;
                        $label = $app->ctx->modifier_truncate( $label, '14+...', $app->ctx );
                        $text = $child['text'] .= " <span class=\"text-sm\">( {$label} )</span> ";
                        if ( $can_edit ) {
                            $edit_link = $admin_url . $params;
                            $child['a_attr']['data-edit'] = $edit_link;
                            $text .= " &nbsp; <a data-edit=\"{$edit_link}\" title=\"{$edit_label}\""
                            . "onclick=\"location.href=$(this).attr('data-edit');window.event.stopPropagation();\""
                            . "href=\"javascript:void(0);\""
                            . " class=\"fa fa-pencil\" aria-hidden=\"true\"><span class=\"sr-only\">"
                            . "{$edit_label}</span></a>";
                        }
                        $child['text'] = $text;
                    }
                }
                if (! $can_edit ) {
                    if (! $obj->is_published || !file_exists( $obj->file_path ) ) {
                        continue;
                    }
                }
                $children[] = $child;
            }
            $text = $url;
            if (! $root ) {
                $text = basename( $text );
            }
            $counter++;
            $children = is_array( $sub ) ? array_merge( $children, $sub ) : $children;
            $tree[] = ['text' => $text,
                       'icon' => 'jstree-folder',
                       'children' => $children ];
        }
        return $tree;
    }

    function build_tree ( $directories, $continue = false ) {
        krsort( $directories );
        foreach ( $directories as $depth => $dirs ) {
            if ( $depth < 1 ) {
                continue;
            }
            foreach ( $dirs as $dir ) {
                $parent = dirname( $dir ) . '/';
                if ( isset( $directories[ $depth -1 ] ) ) {
                    if (! in_array( $parent, $directories[ $depth -1 ] ) ) {
                        $directories[ $depth -1 ][] = $parent;
                        $continue = true;
                    }
                } else {
                    $directories[ $depth -1 ] = [ $parent ];
                    $continue = true;
                }
            }
        }
        if ( $continue ) return $this->build_tree( $directories, false );
        return $directories;
    }
}
