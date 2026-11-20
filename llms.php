<?php
/** Served as /llms.txt (see .htaccess): a plain-text summary of the page for AI search assistants. */
declare(strict_types=1);
define('APP_DEPTH', 0);
require __DIR__ . '/inc/bootstrap.php';
$c = load_content();
header('Content-Type: text/plain; charset=UTF-8');
$one = fn($s) => trim(preg_replace('/\s+/', ' ', (string) $s));
echo '# ' . $c['seo']['site_name'] . "\n\n";
echo '> ' . $one($c['seo']['description']) . "\n\n";
echo "## Program\n\n";
echo '- Name: ' . $one($c['program']['title']) . "\n";
echo '- ' . $one($c['program']['lead']) . "\n";
foreach ($c['program']['phases'] as $p) {
    echo '- ' . $one(($p['name'] ?? '') . ': ' . ($p['title'] ?? '') . ' (' . ($p['duration'] ?? '') . ')') . "\n";
}
echo "\n## Accreditation\n\n";
echo '- ' . $one($c['accreditation']['title']) . "\n";
echo '- ' . $one($c['accreditation']['text']) . ' ' . implode(', ', array_map($one, $c['accreditation']['competencies'])) . ".\n";
echo "\n## Framework\n\n";
echo '- ' . $one($c['framework']['title']) . ': ' . $one($c['framework']['text']) . "\n";
echo "\n## Led by\n\n";
echo '- ' . $one($c['director']['name']) . ', ' . $one($c['director']['credential']) . ', ' . $one($c['director']['role']) . "\n";
$up = upcoming_cohorts($c);
if ($up) {
    echo "\n## Upcoming cohorts\n\n";
    foreach ($up as $co) {
        echo '- ' . cohort_label($co) . ' (' . $one(($co['hours'] ?? '') . ', ' . ($co['duration'] ?? '')) . ")\n";
    }
}
if (!empty($c['faq']['enabled'])) {
    echo "\n## Frequently asked questions\n\n";
    foreach ($c['faq']['items'] as $it) {
        echo '### ' . $one($it['q'] ?? '') . "\n" . $one($it['a'] ?? '') . "\n\n";
    }
}
echo "## Contact\n\n";
echo '- Register your interest: ' . site_url($c) . "#register\n";
if (trim((string) $c['contact']['email']) !== '') {
    echo '- Email: ' . $one($c['contact']['email']) . "\n";
}
