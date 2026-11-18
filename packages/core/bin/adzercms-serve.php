<?php

declare(strict_types=1);

$public = $_SERVER['DOCUMENT_ROOT'];
if (is_file($public.rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH)))) {
    return false;
}

$_SERVER['SCRIPT_FILENAME'] = $public.'/index.php';
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';

require $public.'/index.php';
