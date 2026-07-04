<?php
/*
 * PHP QR Code encoder
 *
 * Config file, feel free to modify
 */

if (!defined('QR_CACHEABLE')) {
    define('QR_CACHEABLE', true);
}
if (!defined('QR_CACHE_DIR')) {
    define('QR_CACHE_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR);
}
if (!defined('QR_LOG_DIR')) {
    define('QR_LOG_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
}

if (!defined('QR_FIND_BEST_MASK')) {
    define('QR_FIND_BEST_MASK', true);
}
if (!defined('QR_FIND_FROM_RANDOM')) {
    define('QR_FIND_FROM_RANDOM', false);
}
if (!defined('QR_DEFAULT_MASK')) {
    define('QR_DEFAULT_MASK', 2);
}

if (!defined('QR_PNG_MAXIMUM_SIZE')) {
    define('QR_PNG_MAXIMUM_SIZE', 1024);
}
