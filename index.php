<?php

require 'vendor/autoload.php';
require 'config.php';
require 'RuntasticToStravaSync.php';

$runtasticToStravaSync = new RuntasticToStravaSync($syncConfig);
$runtasticToStravaSync->sync();