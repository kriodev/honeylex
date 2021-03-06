<?php

$appEnv = 'production';
$appContext = 'web';
$localConfigDir = getenv('LOCAL_CONFIG_DIR') ?: '/usr/local/honeylex.local';

ini_set('display_errors', false);

require_once __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__.'/../app/bootstrap.php';
$app->run();
