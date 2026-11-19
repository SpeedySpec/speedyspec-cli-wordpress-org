<?php

declare(strict_types=1);

/*
 * A router for PHP's built-in server standing in for api.wordpress.org and downloads.wordpress.org in tests/cli.sh:
 * the plugin akismet (5.3.4 and 5.3.5) and the theme twentytwentyfive (1.2), with zips under /zips/.
 */

$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = 'http://' . $_SERVER['HTTP_HOST'];
if (!str_starts_with($path, '/plugins/') && !str_starts_with($path, '/themes/'))
{
    return false;
}
$known = [
    'plugins' => ['akismet' => ['5.3.4', '5.3.5']],
    'themes' => ['twentytwentyfive' => ['1.2']],
];
$what = str_starts_with($path, '/themes/') ? 'themes' : 'plugins';
$slug = $_GET['request']['slug'] ?? '';
header('Content-Type: application/json');
if (!isset($known[$what][$slug]))
{
    http_response_code(404);
    echo json_encode(['error' => 'Not found.']);

    return true;
}
$versions = [];
foreach ($known[$what][$slug] as $version)
{
    $versions[$version] = "{$base}/zips/{$slug}.{$version}.zip";
}
$latest = array_key_last($versions);
echo json_encode(['slug' => $slug, 'version' => $latest, 'download_link' => $versions[$latest], 'versions' => $versions]);

return true;
