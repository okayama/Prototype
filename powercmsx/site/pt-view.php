<?php
// =====================================================
// Specify the application's path according to the server environment.

$pcmsx_path = dirname( __DIR__ ) . DIRECTORY_SEPARATOR;

// If the domain of the CMS is different from the domain of the site,
// Please specify the URL of parent directory of the 'assets/'.

// $asset_parent = '/powercmsx/';

// =====================================================

$allow_login  = false; // Specify true to display unpublish URLs to login users.

$workspace_id = 0;
require_once( $pcmsx_path . 'class.Prototype.php' );
$app = new Prototype();
require_once( LIB_DIR . 'Prototype' . DS . 'class.PTViewer.php' );
$bootstrapper = new PTViewer;
if ( isset( $asset_parent ) ) {
    $bootstrapper->prototype_path = $asset_parent;
}
if ( isset( $allow_login ) ) {
    $bootstrapper->allow_login = $allow_login;
}
$bootstrapper->view( $app, $workspace_id );