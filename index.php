<?php

const ROOT_DIR = __DIR__;

require ROOT_DIR . '/setup.php';

require ROOT_DIR . '/classes/CurlResponse.php';
require ROOT_DIR . '/classes/Storage.php';
require ROOT_DIR . '/classes/Curl.php';
require ROOT_DIR . '/classes/App.php';

const MAP_PATH = ROOT_DIR . '/formats.txt';
const LAST_ID_PATH = ROOT_DIR . '/last_id.txt';
const COUNTER_PATH = ROOT_DIR . '/counter.txt';
const OPTIONS_PATH = ROOT_DIR . '/options.json';

$options = jsonDecode(file_get_contents(OPTIONS_PATH));
date_default_timezone_set($options['timezone']);

$opts = ['allowed_classes' => false];

$counter = file_exists(COUNTER_PATH) ?
    (int) file_get_contents(COUNTER_PATH) :
    0;

if ($counter > $options['reset_limit']) {
    $counter = 0;
}

$map = $counter !== 0 && file_exists(MAP_PATH) ?
    unserialize(file_get_contents(MAP_PATH), $opts) :
    [];

$last = $counter !== 0 && file_exists(LAST_ID_PATH) ?
    unserialize(file_get_contents(LAST_ID_PATH), $opts) :
    null;

file_put_contents(
    LAST_ID_PATH,
    serialize((new App($map, $last, $options))->handle()->lastId),
);

file_put_contents(COUNTER_PATH, (string) ($counter + 1));
