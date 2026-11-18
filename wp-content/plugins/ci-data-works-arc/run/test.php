<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

error_reporting(E_ALL);



$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
//$result = socket_connect($socket, 'php-fpm', 8684);
$result = socket_connect($socket, '192.168.81.113', 3306);
var_dump($result);
