<?php

require_once( 'class.Prototype.php' );
$app = new Prototype();
// $app->debug = true;
$app->logging  = true;
$app->temp_dir = '/powercms/data/temp';
// $app->admin_url = 'http://prototype.localhost/apps/prototype/index.php';
$app->init();
