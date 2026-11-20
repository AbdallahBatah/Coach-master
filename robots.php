<?php
/** Served as /robots.txt (see .htaccess). */
declare(strict_types=1);
define('APP_DEPTH', 0);
require __DIR__ . '/inc/bootstrap.php';
$c = load_content();
$path = base_path();
header('Content-Type: text/plain; charset=UTF-8');
echo "User-agent: *\n";
if (empty($c['seo']['indexable'])) {
    echo "Disallow: /\n";
    exit;
}
echo "Disallow: {$path}admin/\n";
echo "Disallow: {$path}thank-you/\n";
echo "Disallow: {$path}submit.php\n";
echo "\nSitemap: " . site_url($c) . "sitemap.xml\n";
