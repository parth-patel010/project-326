<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    fail('slug required');
}

$stmt = db()->prepare('SELECT slug, title, body_html, updated_at FROM cms_pages WHERE slug = :s LIMIT 1');
$stmt->execute([':s' => $slug]);
$page = $stmt->fetch();
if (!$page) {
    fail('Page not found', 404);
}

respond([
    'ok' => true,
    'page' => $page,
]);
