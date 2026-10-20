<?php
if (!defined('ABSPATH')) { exit; }
spl_autoload_register(function ($class) {
    if (strpos($class, 'CalicoMCP\\') !== 0) { return; }
    static $map;
    if ($map === null) { $map = require __DIR__ . '/classmap.php'; }
    if (isset($map[$class])) { require_once __DIR__ . '/' . $map[$class]; }
});
