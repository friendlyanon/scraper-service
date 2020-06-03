<?php /** @noinspection PhpDocSignatureInspection */

set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

ob_start(static function ($buffer) {
    static $file;
    if ($file === null) {
        $file = fopen(__DIR__ . '/log-' . date('Y-m-d_His') . '.log', 'wb');
    }
    fwrite($file, $buffer);
}, 1);

date_default_timezone_set((require OPTIONS_PATH)['timezone']);

/** @see json_decode */
function jsonDecode($json, $options = 0, $assoc = true, $depth = 512)
{
    return json_decode($json, $assoc, $depth, $options | JSON_THROW_ON_ERROR);
}

/** @see json_encode */
function jsonEncode($value, $options = 0, $depth = 512)
{
    $options |= JSON_UNESCAPED_SLASHES;
    return json_encode($value, $options | JSON_THROW_ON_ERROR, $depth);
}
