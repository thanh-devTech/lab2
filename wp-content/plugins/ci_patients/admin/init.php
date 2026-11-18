<?php
spl_autoload_register(function ($classname) {
    if (class_exists($classname)) return;

    $dir = dirname(__FILE__);
    $classname = strtolower($classname);
    $filenames = array();
    $filenames[] = $dir . "/classes/" . $classname;
    $filenames[] = dirname($dir) . "/inc/" . $classname;

    foreach ($filenames as $fn) {
        $full_path = $fn . ".php";

        if (is_file($full_path)) {
            include_once $full_path;
            break;
        }
    }
});
