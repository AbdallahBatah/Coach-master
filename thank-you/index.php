<?php
/** Thank-you page shown after a successful form submission. Not indexed; conversion events fire once. */
declare(strict_types=1);
define('APP_DEPTH', 1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/partials.php';

$c = load_content();
start_session();
$t = $c['thankyou'];
$home = base_path();

$lead = $_SESSION['lead'] ?? null;
$fresh = is_array($lead) && !empty($lead['fresh']);
if ($fresh) {
    $_SESSION['lead']['fresh'] = false; // a refresh must not count as a second conversion
}
$name = is_array($lead) ? trim((string) ($lead['name'] ?? '')) : '';
$title = $name !== '' ? str_replace('{name}', $name, (string) $t['title']) : (string) $t['title_no_name'];
$wa = whatsapp_url($c);
$upcoming = upcoming_cohorts($c);

page_head($c, [
    'title' => $t['meta_title'],
    'description' => strip_tags((string) $t['text']),
    'canonical' => site_url($c) . 'thank-you/',
    'indexable' => false,
    'lead' => $fresh ? $lead : null,
    'body_class' => 'page-thanks',
]);
site_header($c, $home);
?>
<main id="main">
  <section class="thanks" aria-labelledby="thanks-title">
    <div class="wrap thanks__in">
      <svg class="thanks__mark" viewBox="0 0 64 64" width="64" height="64" aria-hidden="true">
        <circle cx="32" cy="32" r="30" fill="none" stroke="currentColor" stroke-width="2.500" pathLength="1"/>
        <path d="M19 33 28.500 42 46 23" fill="none" stroke="currentColor" stroke-width="3.500" stroke-linecap="round" stroke-linejoin="round" pathLength="1"/>
      </svg>
      <h1 id="thanks-title"><?= e($title) ?></h1>
      <?= paragraphs((string) $t['text'], 'lead') ?>
      <div class="thanks__actions">
        <?php if ($wa !== ''): ?>
        <span class="thanks__or"><?= e($t['whatsapp_text']) ?></span>
        <a class="btn btn--wa" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon_whatsapp() ?><?= e($c['contact']['whatsapp_label']) ?></a>
        <?php endif; ?>
        <a class="link-cta link-cta--light" href="<?= e($home) ?>"><?= e($t['back_label']) ?></a>
      </div>
    </div>
  </section>

  <?php if ($t['steps']): ?>
  <section class="section" aria-labelledby="next-title">
    <div class="wrap">
      <h2 id="next-title" class="section__title"><?= e($t['steps_title']) ?></h2>
      <ol class="next-steps">
        <?php foreach ($t['steps'] as $i => $st): ?>
        <li class="next-steps__item next-steps__item--<?= ['a', 'b', 'c'][$i % 3] ?>">
          <span class="next-steps__n" aria-hidden="true"><?= $i + 1 ?></span>
          <h3><?= e($st['title'] ?? '') ?></h3>
          <p><?= e($st['text'] ?? '') ?></p>
        </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($t['show_dates']) && $upcoming): ?>
  <section class="section section--mist" aria-labelledby="ty-dates-title">
    <div class="wrap">
      <h2 id="ty-dates-title" class="section__title"><?= e($c['dates']['title']) ?></h2>
      <ul class="cohorts cohorts--plain">
        <?php foreach ($upcoming as $co): ?>
        <li class="cohort">
          <div class="cohort__dates">
            <p><span>Starting</span><time datetime="<?= e($co['start']) ?>"><?= e(fmt_date($co['start'])) ?></time></p>
            <?php if (!empty($co['end'])): ?><p><span>Ending</span><time datetime="<?= e($co['end']) ?>"><?= e(fmt_date($co['end'])) ?></time></p><?php endif; ?>
          </div>
          <ul class="cohort__meta">
            <?php foreach (['hours', 'duration', 'note'] as $k): if (trim((string) ($co[$k] ?? '')) !== ''): ?><li><?= e($co[$k]) ?></li><?php endif; endforeach; ?>
          </ul>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php site_footer($c, false);
