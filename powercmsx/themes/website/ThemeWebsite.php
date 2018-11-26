<?php
class ThemeWebsite {

    /*
    function start_import ( $app, $theme, $workspace_id, $class ) {
        $views = $theme['views'];
    }
    */

    function post_import_objects ( $app, $imported_objects, $workspace_id, $class ) {
        $db = $app->db;
        $builds = ['form', 'widget', 'page', 'entry'];
        $global_nav_ids = [];
        $footer_nav_ids = [];
        $templates = $imported_objects['template'];
        foreach ( $templates as $template ) {
            if ( $template->name == '(Website) Latest News' ) {
                $app->publish_obj( $template, null, false );
                $url = $db->model( 'urlinfo' )->get_by_key(
                  ['relative_path' => '%r/news/index.html',
                   'class' => 'archive',
                   'model' => 'template',
                   'object_id' => $template->id,
                   'workspace_id' => $workspace_id,
                   'archive_type' => 'index'] );
                if ( $url->id ) {
                    $global_nav_ids[] = $url->id;
                }
            }
        }
        foreach ( $imported_objects as $model => $objects ) {
            if (! in_array( $model, $builds ) ) {
                continue;
            }
            foreach ( $objects as $obj ) {
                $app->publish_obj( $obj, null, false );
                if ( $model == 'page' &&
                   ( $obj->title == 'About Us' || $obj->title == 'Privacy Policy' ) ) {
                    if ( $obj->title == 'About Us' ) {
                        $relative_path = '%r/about/about_us.html';
                    } else {
                        $relative_path = '%r/folder/privacy_policy.html';
                    }
                    $url = $db->model( 'urlinfo' )->get_by_key(
                      ['class' => 'archive',
                       'model' => 'page',
                       'workspace_id' => $workspace_id,
                       'object_id' => $obj->id,
                       'archive_type' => 'page'] );
                    if ( $url->id ) {
                        if ( $obj->title == 'About Us' ) {
                            $global_nav_ids[] = $url->id;
                        } else {
                            $footer_nav_ids[] = $url->id;
                        }
                    }
                } else if ( $model == 'form' ) {
                    $url = $db->model( 'urlinfo' )->get_by_key(
                      ['workspace_id' => $workspace_id,
                       'class' => 'archive',
                       'model' => 'form',
                       'object_id' => $obj->id,
                       'archive_type' => 'form'] );
                    if ( $url->id ) {
                        $global_nav_ids[] = $url->id;
                    }
                }
            }
        }
        $global_nav_ids = array_reverse( $global_nav_ids );
        $menus = $imported_objects['menu'];
        $args = ['name' => 'urls',
                 'from_obj' => 'menu',
                 'to_obj' => 'urlinfo' ];
        foreach ( $menus as $menu ) {
            $args['from_id'] = $menu->id;
            if ( $menu->basename == 'website_global_navigation' ) {
                $app->set_relations( $args, $global_nav_ids );
            } else if ( $menu->basename == 'website_footer_navigation' ) {
                $app->set_relations( $args, $footer_nav_ids );
            }
        }
        $categories = $db->model( 'category' )->load( ['workspace_id' => $workspace_id ] );
        foreach ( $categories as $category ) {
            $app->publish_obj( $category, null, false );
        }
    }
    
    function post_apply_theme ( $app, $imported_objects, $workspace_id, $class ) {
        if ( $workspace_id ) {
            $workspace = $app->workspace();
            if (! $workspace->copyright ) {
                $workspace->copyright( 'Website : Copyright &copy; <mt:date format="Y"> Alfasado Inc. All rights reserved.' );
            }
        }
    }
    
}