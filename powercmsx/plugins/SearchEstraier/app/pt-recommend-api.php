<?php
// pt-recommend-api.php?url=URL&workspace_ids=0,1[&workspace_id=0]&type=interest&limit=5
// pt-recommend-api.php?url=URL&workspace_ids=0,1[&workspace_id=0]&limit=5
if (! defined( 'DS' ) ) {
    define( 'DS', DIRECTORY_SEPARATOR );
}
$base_path = '..' . DS . '..' . DS . '..' . DS;
require_once( "{$base_path}class.Prototype.php" );
$recommend_api = $base_path . 'plugins' . DS . 'SearchEstraier' . DS .
          'lib' . DS . 'Prototype' . DS . 'class.PTRecommendAPI.php';
require_once( $recommend_api );
$app = new PTRecommendAPI();
$app->app_path = realpath( $base_path );
$app->init();
$app->run();