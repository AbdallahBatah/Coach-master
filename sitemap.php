<?php
/** Served as /sitemap.xml (see .htaccess). Only the landing page is indexable. */
declare(strict_types=1);
define('APP_DEPTH', 0);
require __DIR__ . '/inc/bootstrap.php';
$c = load_content();
$file = DATA_DIR . '/content.json.php';
$mod = gmdate('Y-m-d', is_file($file) ? filemtime($file) : filemtime(__DIR__ . '/inc/defaults.php'));
header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php if (!empty($c['seo']['indexable'])): ?>
  <url>
    <loc><?= e(site_url($c)) ?></loc>
    <lastmod><?= $mod ?></lastmod>
  </url>
<?php endif; ?>
</urlset>
