<?php

$loader = require __DIR__ . "/../vendor/autoload.php";
$loader->add('Drupal\\', __DIR__);
$loader->add('Drupal\Core', __DIR__ . "/../../core/lib");
$loader->add('Drupal\Component', __DIR__ . "/../../core/lib");

define('REQUEST_TIME', (int) $_SERVER['REQUEST_TIME']);
