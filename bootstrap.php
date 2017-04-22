<?php

define('ROOT', __DIR__ . DIRECTORY_SEPARATOR);
define('DATA', ROOT . 'data' . DIRECTORY_SEPARATOR);

if (!is_dir(DATA))
    mkdir(DATA);

$cur_dir = basename(ROOT);
if (strtolower(substr($_SERVER['REQUEST_URI'], 1, strlen($cur_dir))) === strtolower($cur_dir))
    define('HOST', $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/' . $cur_dir . '/');
else
    define('HOST', $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/');

error_reporting(0);

require_once 'core/helpers.php';

spl_autoload_register(function($class_name) {
    // load from core classes
    $class_path = __DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'classes' .
            DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class_name) . '.php';
    if (is_readable($class_path))
        require_once $class_path;
    // load from app classes
    $class_path = __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $class_name) . '.php';
    if (is_readable($class_path))
        require_once $class_path;
}, true);

include_once 'vendor/autoload.php';
