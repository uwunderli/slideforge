#!/usr/bin/env php
<?php
/**
 * Lädt Pixabay-Demo-Medien nach seed/demo-showcase/assets/.
 * Audio: Pixabay-CDN ist oft ohne API-Key gesperrt → ffmpeg-Fallback.
 * Aufruf: php scripts/build_demo_showcase.php
 */
declare(strict_types=1);

$base = dirname(__DIR__);
$assets = $base . '/seed/demo-showcase/assets';
if (!is_dir($assets)) {
    mkdir($assets, 0770, true);
}

$ctx = stream_context_create([
    'http' => ['header' => "User-Agent: SlideForge/1.0\r\n"],
    'ssl' => ['verify_peer' => true],
]);

$sources = [
    'demo-photo.jpg' => 'https://cdn.pixabay.com/photo/2015/04/23/22/00/tree-736885_640.jpg',
    'demo-clip.mp4' => 'https://cdn.pixabay.com/video/2015/08/08/125-135736646_small.mp4',
];

foreach ($sources as $name => $url) {
    $dest = $assets . '/' . $name;
    echo "Download $name …\n";
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || $data === '') {
        fwrite(STDERR, "Fehler beim Download: $url\n");
        exit(1);
    }
    file_put_contents($dest, $data);
    echo '  → ' . number_format(strlen($data)) . " Bytes\n";
}

$audioDest = $assets . '/demo-audio.mp3';
$pixabayAudio = getenv('PIXABAY_API_KEY')
    ? fetchPixabayAudio(getenv('PIXABAY_API_KEY'), $ctx)
    : false;

if ($pixabayAudio !== false && $pixabayAudio !== '') {
    file_put_contents($audioDest, $pixabayAudio);
    echo "Audio von Pixabay-API → " . number_format(strlen($pixabayAudio)) . " Bytes\n";
} elseif (is_file($audioDest) && filesize($audioDest) > 0) {
    echo "Audio demo-audio.mp3 bereits vorhanden – überspringe.\n";
} else {
    echo "Audio per ffmpeg (Pixabay-CDN ohne API-Key nicht erreichbar) …\n";
    $cmd = 'ffmpeg -y -hide_banner -loglevel error -f lavfi -i "sine=frequency=440:duration=8"'
        . ' -af "afade=t=in:st=0:d=0.3,afade=t=out:st=7:d=0.8,volume=0.2"'
        . ' -codec:a libmp3lame -qscale:a 6 ' . escapeshellarg($audioDest);
    exec($cmd, $out, $code);
    if ($code !== 0 || !is_file($audioDest)) {
        fwrite(STDERR, "ffmpeg fehlgeschlagen (Code $code).\n");
        exit(1);
    }
    echo '  → ' . number_format(filesize($audioDest)) . " Bytes\n";
}

echo "Fertig.\n";

/** @param resource $ctx */
function fetchPixabayAudio(string $apiKey, $ctx): string|false
{
    $url = 'https://pixabay.com/api/audio/?key=' . urlencode($apiKey) . '&q=nature&per_page=3';
    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) {
        return false;
    }
    $data = json_decode($json, true);
    $audioUrl = $data['hits'][0]['audio'] ?? $data['hits'][0]['previewURL'] ?? null;
    if (!is_string($audioUrl) || $audioUrl === '') {
        return false;
    }
    return @file_get_contents($audioUrl, false, $ctx);
}
