<?php
class PTTheme {

    public $skipped = [];
    public $skipped_count = 0;

    function manage_theme ( $app ) {
        if (! $app->can_do( 'import_objects' ) ) {
            return $app->error( 'Permission denied.' );
        }
        $themes_dir = dirname( $app->pt_path ) . DS . 'themes';
        $items = scandir( $themes_dir );
        $theme_loop = [];
        $themes = [];
        foreach ( $items as $theme ) {
            if ( strpos( $theme, '.' ) === 0 ) continue;
            $json = $themes_dir . DS . $theme . DS . 'theme.json';
            if (! file_exists( $json ) ) continue;
            $configs = json_decode( file_get_contents( $json ), true );
            $lang = $app->language;
            $locale = $themes_dir . DS . $theme . DS . 'locale' . DS . $lang . '.json';
            if ( file_exists( $locale ) ) {
                $map = json_decode( file_get_contents( $locale ), true );
                if ( isset( $app->dictionary[ $lang ] ) ) {
                    $app->dictionary[ $lang ] = array_merge( $app->dictionary[ $lang ], $map );
                } else {
                    $app->dictionary[ $lang ] = $map;
                }
            }
            $themes[ $theme ] = $configs;
            $label = $app->translate( $configs['label'] );
            $theme_vars = [];
            $theme_vars['theme_id'] = $theme;
            $theme_vars['label'] = $label;
            if ( isset( $configs['description'] ) ) {
                $description = $app->translate( $configs['description'] );
                $theme_vars['description'] = $description;
            }
            if ( isset( $configs['version'] ) ) {
                $theme_vars['version'] = $configs['version'];
            }
            if ( isset( $configs['author'] ) ) {
                $theme_vars['author'] = $configs['author'];
                if ( isset( $configs['author_link'] ) ) {
                    $theme_vars['author_link'] = $configs['author_link'];
                }
            }
            if ( isset( $configs['thumbnail'] ) && $configs['thumbnail'] ) {
                $theme_vars['thumbnail'] = $app->path . "themes/{$theme}/" . $configs['thumbnail'];
            } else {
                $theme_vars['thumbnail'] = $app->path . 'assets/img/model-icons/default.png';
            }
            $theme_loop[] = $theme_vars;
        }
        $workspace_id = (int) $app->param( 'workspace_id' );
        $current = $app->get_config( 'theme', $workspace_id );
        if ( $current ) {
            $current_id = $current->value;
            if ( isset( $themes[ $current_id ] ) && $themes[ $current_id ]['label'] ) {
                $app->ctx->vars['current_label'] = $app->translate( 
                                                      $themes[ $current_id ]['label'] );
            }
            $app->ctx->vars['current_theme'] = $current_id;
        }
        $app->ctx->vars['theme_loop'] = $theme_loop;
        if ( $app->param( '_type' ) == 'apply_theme' && $app->request_method === 'POST' ) {
            $db = $app->db;
            $app->init_tags();
            $app->validate_magic();
            $db->caching = false;
            $db->begin_work();
            $app->txn_active = true;
            $theme_id = $app->param( 'theme_id' );
            $theme = $themes[ $theme_id ];
            $component = null;
            if ( isset( $theme['component'] ) && $theme['component'] ) {
                $class = $themes_dir . DS . $theme_id . DS . $theme['component'] . '.php';
                if ( file_exists( $class ) ) {
                    include_once( $class );
                    $class_name = $theme['component'];
                    $component = new $class_name();
                }
            }
            if (! $theme ) {
                return $app->error( 'Invalid request.' );
            }
            if ( $component !== null && method_exists( $component, 'start_import' ) ) {
                $component->start_import( $app, $theme, $workspace_id, $this );
            }
            $views = isset( $theme['views'] ) ? $theme['views'] : [];
            $terms = [];
            $terms['workspace_id'] = $workspace_id;
            $terms['rev_type'] = 0;
            $app->get_scheme_from_db( 'template' );
            $forms = [];
            $template_map = [];
            $uuid_map = [];
            $rebuilds = [];
            $templates_installed = [];
            foreach ( $views as $uuid => $view ) {
                $urlmappings = isset( $view['urlmappings'] ) ? $view['urlmappings'] : [];
                $form = isset( $view['form'] ) ? $view['form'] : [];
                unset( $view['urlmappings'] );
                unset( $view['form'] );
                $basename = $db->quote( $view['basename'] );
                $name = $db->quote( $view['name'] );
                $_uuid = $db->quote( $uuid );
                $extra = " AND ( template_name={$name} OR template_basename={$basename} OR template_uuid={$_uuid} ) ";
                $templates = $db->model( 'template' )->load( $terms, [], '*', $extra );
                $imported_objects = [];
                $old_id = null;
                $old_template = null;
                $rev_note = null;
                $ts_name = date( 'Y-m-d H:i:s' );
                if (! empty( $templates ) ) {
                    foreach ( $templates as $template ) {
                        if ( count( $templates ) == 1
                            && $template->basename == $view['basename'] && $template->uuid == $uuid ) {
                            $old_template = PTUtil::clone_object( $app, $template );
                            $old_id = (int) $template->id;
                            $rev_note = $app->translate( $app->translate( 'Backup of %s(%s).', [ $template->name, $ts_name ] ) );
                            $template->remove();
                        } else {
                            $ts_basename = date( '_YmdHis' );
                            $template->class( 'Backup' );
                            $template->basename( $template->basename . $ts_basename );
                            $tmpl_name = $template->name;
                            $tmpl_name = $app->translate( 'Backup of %s(%s).', [ $tmpl_name, $ts_name ] );
                            $template->name( $tmpl_name );
                            $template->status(1);
                            if ( $template->uuid == $uuid ) {
                                $template->uuid( $app->generate_uuid( 'template' ) );
                            }
                            $template->save();
                        }
                    }
                }
                $path = $themes_dir . DS . $theme_id . DS . 'views' . DS . $uuid . '.tmpl';
                $new_template = $db->model( 'template' )->new( $view );
                $new_template->workspace_id( $workspace_id );
                $new_template->status( 2 );
                if ( file_exists( $path ) ) {
                    $new_template->text( file_get_contents( $path ) );
                }
                $new_template->uuid( $uuid );
                $app->set_default( $new_template );
                if ( $old_id && $old_template ) {
                    $new_template->id = $old_id;
                }
                $new_template->save();
                $templates_installed[] = $new_template;
                if ( $old_id && $old_template ) {
                    $old_template->rev_type( 1 );
                    $old_template->rev_object_id( $old_id );
                    $old_template->rev_note( $rev_note );
                    $old_template->save();
                }
                $uuid_map[ $uuid ] = $new_template;
                if (! empty( $form ) ) {
                    $forms[ $new_template->id ] = $form;
                    $template_map[ $new_template->id ] = $new_template;
                }
                if (! isset( $imported_objects['template'] ) ) {
                    $imported_objects['template'] = [];
                }
                $imported_objects['template'][] = $new_template;
                if (! $old_id && is_array( $urlmappings ) && !empty( $urlmappings ) ) {
                    $rebuilds[] = $new_template;
                    foreach ( $urlmappings as $urlmapping ) {
                        $triggers = isset( $urlmapping['triggers'] )
                                  ? $urlmapping['triggers'] : [];
                        unset( $urlmapping['triggers'] );
                        $urlmap = $db->model( 'urlmapping' )->new( $urlmapping );
                        $urlmap->workspace_id( $workspace_id );
                        $urlmap->template_id( $new_template->id );
                        $app->set_default( $urlmap );
                        $urlmap->save();
                        if (! isset( $imported_objects['urlmapping'] ) ) {
                            $imported_objects['urlmapping'] = [];
                        }
                        $imported_objects['urlmapping'][] = $urlmap;
                        if ( count( $triggers ) ) {
                            $i = 1;
                            foreach ( $triggers as $trigger ) {
                                $table = $app->get_table( $trigger );
                                if (! $table ) continue;
                                $relation = $db->model( 'relation' )->new();
                                $relation->name( 'triggers' );
                                $relation->from_id( $urlmap->id );
                                $relation->from_obj( 'urlmapping' );
                                $relation->to_obj( 'table' );
                                $relation->order( $i );
                                $relation->to_id( $table->id );
                                $relation->save();
                                $i++;
                            }
                        }
                    }
                }
            }
            if (! empty( $forms ) ) {
                foreach ( $forms as $template_id => $form ) {
                    $template = $template_map[ $template_id ];
                    $basename = $form['basename'];
                    $existing_terms = ['basename' => $basename, 'workspace_id' => $workspace_id ];
                    $existing = $db->model( 'form' )->get_by_key( $existing_terms );
                    if ( $existing->id ) {
                        $template->form_id( $existing->id );
                        $template->save();
                        $this->skipped['form'][] = $existing;
                        if (! isset( $imported_objects['form'] ) ) {
                            $imported_objects['form'] = [];
                        }
                        $imported_objects['form'][] = $existing;
                        $this->skipped_count++;
                        $msg = $app->translate(
                          "The %s '%s' has been skipped because it already existed." ,
                          [ $app->translate( 'Form' ), $basename ] );
                        $app->log( ['message'  => $msg,
                                    'category' => 'import',
                                    'model'    => 'form',
                                    'metadata' => json_encode( $form, JSON_UNESCAPED_UNICODE ),
                                    'level'    => 'info'] );
                        continue;
                    }
                    $thanks_template = $form['thanks_template'];
                    $notify_template = $form['notify_template'];
                    $questions = isset( $form['questions'] ) ? $form['questions'] : [];
                    unset( $form['thanks_template'] );
                    unset( $form['notify_template'] );
                    unset( $form['questions'] );
                    $new_form = $urlmap = $db->model( 'form' )->new( $form );
                    if ( $thanks_template ) {
                        if ( isset( $uuid_map[ $thanks_template ] ) ) {
                            $new_form->thanks_template( $uuid_map[ $thanks_template ]->id );
                        }
                    }
                    if ( $notify_template ) {
                        if ( isset( $uuid_map[ $notify_template ] ) ) {
                            $new_form->notify_template( $uuid_map[ $notify_template ]->id );
                        }
                    }
                    $new_form->workspace_id( $workspace_id );
                    $app->set_default( $new_form );
                    $new_form->status( 4 );
                    $new_form->save();
                    if (! isset( $imported_objects['form'] ) ) {
                        $imported_objects['form'] = [];
                    }
                    $imported_objects['form'][] = $new_form;
                    $template->form_id( $new_form->id );
                    $template->save();
                    if ( count( $questions ) ) {
                        $to_ids = [];
                        foreach ( $questions as $uuid => $question ) {
                            $basename = $question['basename'];
                            $existing_terms = ['basename' => $basename, 'workspace_id' => $workspace_id ];
                            $existing = $db->model( 'question' )->get_by_key( $existing_terms );
                            if ( $existing->id ) {
                                $to_ids[] = $existing->id;
                                $this->skipped['question'][] = $existing;
                                $msg = $app->translate(
                                  "The %s '%s' has been skipped because it already existed." ,
                                  [ $app->translate( 'Question' ), $basename ] );
                                $app->log( ['message'  => $msg,
                                            'category' => 'import',
                                            'model'    => 'question',
                                            'metadata' => json_encode( $question, JSON_UNESCAPED_UNICODE ),
                                            'level'    => 'info'] );
                                continue;
                            }
                            $questiontype_id = $question['questiontype_id'];
                            unset( $question['questiontype_id'] );
                            $questiontype = $db->model( 'questiontype' )->get_by_key(
                                ['basename' => $questiontype_id ] );
                            if ( $questiontype->id ) {
                                $question['questiontype_id'] = $questiontype->id;
                            }
                            $new_question = $db->model( 'question' )->new( $question );
                            $new_question->workspace_id( $workspace_id );
                            // $new_question->uuid( $uuid );
                            $app->set_default( $new_question );
                            $path = $themes_dir . DS . $theme_id . DS . 'questions' . DS . $uuid . '.tmpl';
                            if ( file_exists( $path ) ) {
                                $new_question->template( file_get_contents( $path ) );
                            }
                            $new_question->save();
                            if (! isset( $imported_objects['question'] ) ) {
                                $imported_objects['question'] = [];
                            }
                            $imported_objects['question'][] = $new_question;
                            $to_ids[] = $new_question->id;
                        }
                        $args = ['from_id' => $new_form->id, 
                                 'name' => 'questions',
                                 'from_obj' => 'form',
                                 'to_obj' => 'question' ];
                        $app->set_relations( $args, $to_ids, true );
                    }
                }
            }
            $db->commit();
            $app->txn_active = false;
            $objects = isset( $theme['objects'] ) ? $theme['objects'] : [];
            $importer = new PTImporter();
            $importer->print_state = false;
            $importer->apply_theme = true;
            foreach ( $objects as $model ) {
                $dirname = $themes_dir . DS . $theme_id . DS . 'objects' . DS . $model;
                if ( is_dir( $dirname ) ) {
                    $items = scandir( $dirname );
                    foreach ( $items as $import_file ) {
                        if ( strpos( $import_file, '.' ) === 0 ) continue;
                        $import_file = $dirname . DS . $import_file;
                        if ( preg_match( '/\.csv$/i', $import_file ) ) {
                            $import_file = [ $import_file ];
                            $imported_objects[ $model ] =
                                  $importer->import_from_files( $app, $model, $dirname, $import_file );
                        }
                    }
                }
            }
            $skipped = $this->skipped_count;
            $skipped += $importer->theme_skipped;
            if ( $component !== null && method_exists( $component, 'post_import_objects' ) ) {
                $component->post_import_objects( $app, $imported_objects, $workspace_id, $this );
            }
            if ( count( $rebuilds ) ) {
                foreach ( $rebuilds as $rebuild ) {
                    $app->publish_obj( $rebuild, null, false );
                }
            }
            if ( $component !== null && method_exists( $component, 'post_apply_theme' ) ) {
                $component->post_apply_theme( $app, $imported_objects, $workspace_id, $this );
            }
            $app->set_config( ['theme' => $theme_id ], $workspace_id );
            if ( $workspace_id ) {
                $workspace = $app->workspace();
                $workspace->last_update( time() );
                $workspace->save();
            }
            $app->init_tags( true );
            foreach ( $templates_installed as $template ) {
                $template->save();
            }
            $theme_label = isset( $theme['label'] ) ? $app->translate( $theme['label'] ) : $theme_id;
            $msg = $app->translate(
                "The theme '%1\$s' has been applied by %2\$s.",
                [ $theme_label, $app->user()->nickname ] );
            $app->log( ['message'  => $msg,
                        'category' => 'theme',
                        'model'    => 'template',
                        'level'    => 'info'] );
            $return_args = "__mode=manage_theme&apply_theme=1";
            if ( $workspace_id ) {
                $return_args .= '&workspace_id=' . $workspace_id;
            }
            if ( $skipped ) {
                $return_args .= '&skipped=' . $skipped;
            }
            $app->redirect( $app->admin_url . '?' . $return_args );
        }
        return $app->__mode( 'manage_theme' );
    }
}