<?php
declare(strict_types=1);

/**
 * Example on-demand page render endpoint.
 *
 * Usage: render_page.php?job=job-123&side=old&page=42
 *
 * Looks up the job's manifest.json (written by PdfFastDiffEngine::run) to
 * find the original source PDF paths, then renders exactly one page at
 * full resolution and streams it back. This is what lets 'unchanged' pages
 * skip eager full-res publishing without losing the ability to zoom in.
 *
 * SECURITY NOTE: page/side/job are validated against the job's own manifest
 * rather than trusted directly -- never build a filesystem path straight
 * from request input, or you've built a path-traversal / arbitrary-file-read
 * endpoint.
 */

require __DIR__ . '/PdfFastDiffEngine.php';

// Adjust to wherever your app keeps per-job output directories.
const JOBS_ROOT = '/var/www/app/storage/diffs';

function fail(int $httpCode, string $message): never
{
    http_response_code($httpCode);
    header('Content-Type: text/plain');
    echo $message;
    exit;
}

$job = $_GET['job'] ?? '';
$side = $_GET['side'] ?? '';
$page = $_GET['page'] ?? '';

if (!preg_match('/^[a-zA-Z0-9_-]+$/', (string)$job)) {
    fail(400, 'Invalid job id');
}
if (!in_array($side, ['old', 'new'], true)) {
    fail(400, "side must be 'old' or 'new'");
}
if (!ctype_digit((string)$page) || (int)$page < 1) {
    fail(400, 'page must be a positive integer');
}
$page = (int)$page;

$jobDir = JOBS_ROOT . '/' . $job;
$manifestPath = $jobDir . '/manifest.json';
if (!is_file($manifestPath)) {
    fail(404, 'Unknown job');
}

$manifest = json_decode((string)file_get_contents($manifestPath), true);
$meta = $manifest['meta'] ?? null;
$pages = $manifest['pages'] ?? null;
if (!is_array($meta) || !is_array($pages)) {
    fail(500, 'Malformed manifest');
}
if ($page > (int)($meta['totalPages'] ?? 0)) {
    fail(404, 'Page out of range');
}

$sourcePath = $side === 'old' ? ($meta['sourceOld'] ?? null) : ($meta['sourceNew'] ?? null);
if (!is_string($sourcePath) || !is_file($sourcePath)) {
    // This is the persistence caveat from the engine's usage example: the
    // source PDFs must still exist at the recorded path.
    fail(410, 'Source PDF for this job is no longer available');
}

try {
    $png = PdfFastDiffEngine::renderSinglePage($sourcePath, $page, dpi: 200);
} catch (Throwable $e) {
    fail(500, 'Render failed: ' . $e->getMessage());
}

header('Content-Type: image/png');
header('Content-Length: ' . strlen($png));
header('Cache-Control: public, max-age=31536000, immutable');
echo $png;
