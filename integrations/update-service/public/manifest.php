<?php
/**
 * Read-only public manifest. Deploy at updates.elearningcloud.io/manifest.json
 * through a server rewrite to this PHP script (or publish its generated JSON).
 * Requires private environment variable ECLOUD_UPDATE_MANIFEST_KEY.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
$source = getenv('ECLOUD_UPDATE_MANIFEST_FILE');
if (!$source || !is_file($source) || !is_readable($source)) {
    http_response_code(503);
    echo json_encode(['error' => 'Manifest unavailable']);
    exit;
}
if (filesize($source) > 131072) {
    http_response_code(503);
    echo json_encode(['error' => 'Manifest too large']);
    exit;
}
$json = file_get_contents($source);
$data = json_decode($json, true);
if (!is_array($data) || ($data['schemaVersion'] ?? null) !== 1 || !isset($data['releases']) || !is_array($data['releases'])) {
    http_response_code(503);
    echo json_encode(['error' => 'Invalid manifest']);
    exit;
}
echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
