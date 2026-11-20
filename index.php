<?php
/** Landing page. All copy comes from the CMS (admin/) with shipped defaults in inc/defaults.php. */
declare(strict_types=1);
define('APP_DEPTH', 0);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/partials.php';

$c = load_content();
start_session();

// Remember where the visitor came from (first touch in this session) so it is saved with the lead.
if (!isset($_SESSION['attribution'])) {
    $attr = [];
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'] as $k) {
        if (isset($_GET[$k]) && is_string($_GET[$k]) && $_GET[$k] !== '') {
            $attr[$k] = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $_GET[$k]), 0, 200);
        }
    }
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $refHost = (string) parse_url($ref, PHP_URL_HOST);
    if ($refHost !== '' && $refHost !== preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''))) {
        $attr['referrer'] = mb_substr($ref, 0, 300);
    }
    $_SESSION['attribution'] = $attr;
}

$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
$preCohort = isset($_GET['cohort']) && is_string($_GET['cohort']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['cohort']) ? $_GET['cohort'] : '';

$upcoming = upcoming_cohorts($c);
$next = $upcoming[0] ?? null;
$wa = whatsapp_url($c);
$h = $c['hero'];
$ct = $c['contact'];
$check = '<svg class="tick" viewBox="0 0 20 20" width="20" height="20" aria-hidden="true"><path d="M4 10.500 8.200 14.500 16 6" fill="none" stroke="currentColor" stroke-width="2.200" stroke-linecap="round" stroke-linejoin="round"/></svg>';

page_head($c, [
    'title' => $c['seo']['title'],
    'description' => $c['seo']['description'],
    'og_title' => $c['seo']['og_title'] ?: $c['seo']['title'],
    'og_description' => $c['seo']['og_description'] ?: $c['seo']['description'],
    'canonical' => site_url($c),
    'schema' => landing_schema($c),
    'body_class' => 'page-landing',
]);
site_header($c);
?>
<main id="main">

  <section class="hero" aria-labelledby="hero-title">
    <div class="wrap hero__grid">
      <div class="hero__copy">
        <?php if (trim((string) $h['tagline']) !== ''): ?><p class="hero__tagline"><?= e($h['tagline']) ?></p><?php endif; ?>
        <h1 id="hero-title"><?= keep_hyphenated(e($h['title'])) ?></h1>
        <?= paragraphs((string) $h['text'], 'hero__text') ?>
        <?php if ($h['facts']): ?>
        <dl class="facts">
          <?php foreach ($h['facts'] as $f): if (trim((string) ($f['value'] ?? '')) === '') continue; ?>
          <div><dt><?= e($f['value']) ?></dt><dd><?= e($f['label'] ?? '') ?></dd></div>
          <?php endforeach; ?>
        </dl>
        <?php endif; ?>
        <div class="hero__badges">
          <?= img_tag((string) $c['accreditation']['badge_1'], (string) $c['accreditation']['badge_1_alt'], ['loading' => 'eager', 'class' => 'badge-l1']) ?>
          <?= img_tag((string) $c['accreditation']['badge_2'], (string) $c['accreditation']['badge_2_alt'], ['loading' => 'eager', 'class' => 'badge-icf']) ?>
        </div>
      </div>

      <div class="form-card" id="register-now">
        <?php if ($next): ?>
        <p class="form-card__next"><span><?= e($h['next_label']) ?></span> <strong><time datetime="<?= e($next['start']) ?>"><?= e(fmt_date($next['start'])) ?></time></strong></p>
        <?php endif; ?>
        <h2 class="form-card__title"><?= e($h['form_title']) ?></h2>
        <?php if (trim((string) $h['form_text']) !== ''): ?><p class="form-card__text"><?= e($h['form_text']) ?></p><?php endif; ?>
        <?php lead_form($c, 'hero', $flash, $preCohort); ?>
      </div>
    </div>

    <?php $steps = array_slice($c['framework']['steps'], 0, 3); if (!empty($h['show_path']) && count($steps) === 3): ?>
    <div class="wrap">
      <div class="path">
        <svg class="path__line" viewBox="0 0 600 100" preserveAspectRatio="none" aria-hidden="true" focusable="false">
          <path class="path__seg path__seg--a" pathLength="1" d="M0,70 L100,70 C150,70 175,60 200,50"/>
          <path class="path__seg path__seg--b" pathLength="1" d="M200,50 C225,40 250,30 300,30 C350,30 375,40 400,50"/>
          <path class="path__seg path__seg--c" pathLength="1" d="M400,50 C425,60 450,70 500,70 L600,70"/>
        </svg>
        <ol class="path__steps" aria-label="<?= e($c['framework']['title']) ?>">
          <?php foreach ($steps as $i => $s): ?>
          <li class="path__step path__step--<?= ['a', 'b', 'c'][$i] ?>">
            <span class="path__dot" aria-hidden="true"></span>
            <strong><?= e($s['name']) ?></strong>
            <em><?= e($s['question']) ?></em>
          </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <?php $s = $c['outcomes']; if (!empty($s['enabled'])): ?>
  <section class="section" id="outcomes" aria-labelledby="outcomes-title">
    <div class="wrap">
      <h2 id="outcomes-title" class="section__title"><?= e($s['title']) ?></h2>
      <ul class="outcomes">
        <?php foreach ($s['items'] as $item): ?>
        <li><?= $check ?><span><?= e($item) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php if (trim((string) $s['closing']) !== ''): ?><p class="statement"><?= e($s['closing']) ?></p><?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php $s = $c['program']; if (!empty($s['enabled'])): ?>
  <section class="section section--mist" id="program" aria-labelledby="program-title">
    <div class="wrap program">
      <div class="program__copy">
        <h2 id="program-title" class="section__title"><?= e($s['title']) ?></h2>
        <?= paragraphs((string) $s['lead'], 'lead') ?>
        <?= paragraphs((string) $s['text']) ?>
        <?php if (!empty($c['dates']['enabled']) && trim((string) $s['cta_label']) !== ''): ?>
        <p><a class="link-cta" href="#enrolment"><?= e($s['cta_label']) ?></a></p>
        <?php endif; ?>
      </div>
      <?php if ($s['phases']): ?>
      <ol class="phases">
        <?php foreach ($s['phases'] as $i => $p): ?>
        <li class="phase phase--<?= $i % 2 ? 'b' : 'a' ?>">
          <div class="phase__head"><span class="phase__name"><?= e($p['name'] ?? '') ?></span><span class="phase__duration"><?= e($p['duration'] ?? '') ?></span></div>
          <div class="phase__weeks" aria-hidden="true"><?= str_repeat('<i></i>', 8) ?></div>
          <h3><?= e($p['title'] ?? '') ?></h3>
          <?php if (trim((string) ($p['text'] ?? '')) !== ''): ?><p><?= e($p['text']) ?></p><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php $s = $c['framework']; if (!empty($s['enabled'])): $embed = video_embed_url((string) $s['video_url']); ?>
  <section class="section" id="framework" aria-labelledby="framework-title">
    <div class="wrap">
      <div class="section__intro">
        <h2 id="framework-title" class="section__title"><?= e($s['title']) ?></h2>
        <?= paragraphs((string) $s['text'], 'lead') ?>
      </div>
      <?php if ($s['steps']): ?>
      <ol class="acc">
        <?php foreach ($s['steps'] as $i => $st): ?>
        <li class="acc__step acc__step--<?= ['a', 'b', 'c'][$i % 3] ?>">
          <h3><?= e($st['name'] ?? '') ?></h3>
          <p class="acc__q"><?= e($st['question'] ?? '') ?></p>
          <p><?= e($st['text'] ?? '') ?></p>
        </li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
      <?php if (trim((string) $s['image']) !== ''): ?>
      <figure class="diagram">
        <?= img_tag((string) $s['image'], (string) $s['image_alt']) ?>
        <?php if (trim((string) $s['image_caption']) !== ''): ?><figcaption><?= e($s['image_caption']) ?></figcaption><?php endif; ?>
      </figure>
      <?php endif; ?>
      <?php if ($embed !== ''): ?>
      <div class="video">
        <iframe src="<?= e($embed) ?>" title="<?= e($s['video_title']) ?>" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php $s = $c['accreditation']; if (!empty($s['enabled'])): ?>
  <section class="section section--navy" id="accreditation" aria-labelledby="accreditation-title">
    <div class="wrap accred">
      <div class="accred__copy">
        <h2 id="accreditation-title" class="section__title"><?= e($s['title']) ?></h2>
        <?= paragraphs((string) $s['lead'], 'lead') ?>
        <?= paragraphs((string) $s['text']) ?>
        <?php if ($s['competencies']): ?>
        <ul class="competencies">
          <?php foreach ($s['competencies'] as $item): ?><li><?= $check ?><span><?= e($item) ?></span></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
      <div class="accred__panel">
        <div class="accred__badges">
          <?= img_tag((string) $s['badge_1'], (string) $s['badge_1_alt']) ?>
          <?= img_tag((string) $s['badge_2'], (string) $s['badge_2_alt']) ?>
        </div>
        <?php if ($s['points']): ?>
        <ul class="accred__points">
          <?php foreach ($s['points'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php $s = $c['director']; if (!empty($s['enabled'])): ?>
  <section class="section" id="lina-cherry" aria-labelledby="director-title">
    <div class="wrap director">
      <div class="director__photo"><?= img_tag((string) $s['photo'], (string) $s['photo_alt']) ?></div>
      <div class="director__copy">
        <p class="director__role"><?= e($s['role']) ?></p>
        <h2 id="director-title" class="section__title"><?= e($s['name']) ?></h2>
        <?php if (trim((string) $s['credential']) !== ''): ?><p class="director__credential"><?= e($s['credential']) ?></p><?php endif; ?>
        <?= paragraphs((string) $s['bio']) ?>
        <?php if (trim((string) $s['cta_label']) !== ''): ?><p><a class="btn btn--ghost" href="#register"><?= e($s['cta_label']) ?></a></p><?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php $s = $c['dates']; if (!empty($s['enabled'])): ?>
  <section class="section section--mist" id="enrolment" aria-labelledby="dates-title">
    <div class="wrap">
      <div class="section__intro">
        <h2 id="dates-title" class="section__title"><?= e($s['title']) ?></h2>
        <?= paragraphs($upcoming ? (string) $s['text'] : (string) $s['empty_text'], 'lead') ?>
      </div>
      <?php if ($upcoming): ?>
      <ul class="cohorts">
        <?php foreach ($upcoming as $co): ?>
        <li class="cohort">
          <div class="cohort__dates">
            <p><span>Starting</span><time datetime="<?= e($co['start']) ?>"><?= e(fmt_date($co['start'])) ?></time></p>
            <?php if (!empty($co['end'])): ?><p><span>Ending</span><time datetime="<?= e($co['end']) ?>"><?= e(fmt_date($co['end'])) ?></time></p><?php endif; ?>
          </div>
          <ul class="cohort__meta">
            <?php foreach (['hours', 'duration', 'note'] as $k): if (trim((string) ($co[$k] ?? '')) !== ''): ?><li><?= e($co[$k]) ?></li><?php endif; endforeach; ?>
          </ul>
          <a class="btn" href="?cohort=<?= e($co['start']) ?>#register" data-cohort="<?= e($co['start']) ?>"><?= e($s['cta_label']) ?></a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <p><a class="btn" href="#register"><?= e($ct['form_button']) ?></a></p>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php $s = $c['testimonials']; if (!empty($s['enabled']) && $s['items']): ?>
  <section class="section" id="testimonials" aria-labelledby="testimonials-title">
    <div class="wrap">
      <h2 id="testimonials-title" class="section__title"><?= e($s['title']) ?></h2>
      <div class="quotes">
        <?php foreach ($s['items'] as $i => $q): if (trim((string) ($q['quote'] ?? '')) === '') continue; ?>
        <figure class="quote<?= $i === 0 ? ' quote--lead' : '' ?>">
          <blockquote><p><?= e($q['quote']) ?></p></blockquote>
          <figcaption><strong><?= e($q['name'] ?? '') ?></strong><?php if (trim((string) ($q['country'] ?? '')) !== ''): ?><span><?= e($q['country']) ?></span><?php endif; ?></figcaption>
        </figure>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php $s = $c['faq']; if (!empty($s['enabled']) && $s['items']): ?>
  <section class="section section--rule" id="faq" aria-labelledby="faq-title">
    <div class="wrap faq">
      <h2 id="faq-title" class="section__title"><?= e($s['title']) ?></h2>
      <div class="faq__list">
        <?php foreach ($s['items'] as $it): if (trim((string) ($it['q'] ?? '')) === '') continue; ?>
        <details>
          <summary><h3><?= e($it['q']) ?></h3></summary>
          <?= paragraphs((string) ($it['a'] ?? '')) ?>
        </details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="section section--navy contact" id="register" aria-labelledby="contact-title">
    <div class="wrap contact__grid">
      <div class="contact__copy">
        <h2 id="contact-title" class="section__title"><?= e($ct['title']) ?></h2>
        <?= paragraphs((string) $ct['text'], 'lead') ?>
        <ul class="contact__ways">
          <?php if (trim((string) $ct['phone']) !== ''): ?><li><span>Phone</span><a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $ct['phone'])) ?>"><?= e($ct['phone']) ?></a></li><?php endif; ?>
          <?php if (trim((string) $ct['email']) !== ''): ?><li><span>Email</span><a href="mailto:<?= e($ct['email']) ?>"><?= e($ct['email']) ?></a></li><?php endif; ?>
        </ul>
        <?php if ($wa !== ''): ?><p><a class="btn btn--wa" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon_whatsapp() ?><?= e($ct['whatsapp_label']) ?></a></p><?php endif; ?>
      </div>
      <div class="form-card">
        <h3 class="form-card__title"><?= e($ct['form_title']) ?></h3>
        <?php lead_form($c, 'contact', $flash, $preCohort); ?>
      </div>
    </div>
  </section>

</main>
<?php site_footer($c);
