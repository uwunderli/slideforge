<?php
require __DIR__ . '/../config.php';

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$css = FontLibrary::cssBlock('uploads/fonts/');
if ($css === '') {
    echo "/* keine benutzerdefinierten Schriften */\n";
} else {
    echo $css;
}
