<?php
/** Shared page chrome: <head>, header, footer, lead form, structured data and tracking tags. */
declare(strict_types=1);

function json_ld(array $data): string
{
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . "</script>\n";
}

function tracking_ids(array $c): array
{
    $t = $c['tracking'] ?? [];
    $pick = fn(string $k, string $re) => preg_match($re, trim((string) ($t[$k] ?? ''))) ? trim((string) $t[$k]) : '';
    return [
        'gtm' => $pick('gtm_id', '/^GTM-[A-Z0-9]+$/'),
        'ga4' => $pick('ga4_id', '/^G-[A-Z0-9]+$/'),
        'pixel' => $pick('meta_pixel_id', '/^\d{5,20}$/'),
        'ads' => $pick('google_ads_id', '/^AW-\d+$/'),
        'ads_label' => $pick('google_ads_label', '/^[A-Za-z0-9_\-]+$/'),
    ];
}

/** Tags for <head>. $lead is set only on the thank-you page right after a real submission. */
function tracking_head(array $c, ?array $lead = null): string
{
    $id = tracking_ids($c);
    $o = '';
    if ($lead) {
        $o .= '<script>window.dataLayer=window.dataLayer||[];dataLayer.push(' . json_encode(['event' => 'generate_lead', 'form_location' => $lead['form'] ?? '', 'cohort' => $lead['cohort'] ?? '', 'lead_id' => $lead['id'] ?? ''], JSON_HEX_TAG) . ");</script>\n";
    }
    if ($id['gtm']) {
        $o .= "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$id['gtm']}');</script>\n";
    }
    if ($id['ga4'] || $id['ads']) {
        $first = $id['ga4'] ?: $id['ads'];
        $o .= "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$first}\"></script>\n<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());";
        if ($id['ga4']) {
            $o .= "gtag('config','{$id['ga4']}');";
        }
        if ($id['ads']) {
            $o .= "gtag('config','{$id['ads']}');";
        }
        if ($lead) {
            if ($id['ga4']) {
                $o .= "gtag('event','generate_lead',{form_location:" . json_encode((string) ($lead['form'] ?? ''), JSON_HEX_TAG) . '});';
            }
            if ($id['ads'] && $id['ads_label']) {
                $o .= "gtag('event','conversion',{send_to:'{$id['ads']}/{$id['ads_label']}'});";
            }
        }
        $o .= "</script>\n";
    }
    if ($id['pixel']) {
        $o .= "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{$id['pixel']}');fbq('track','PageView');" . ($lead ? "fbq('track','Lead');" : '') . "</script>\n";
    }
    $o .= (string) ($c['tracking']['head_code'] ?? '') . "\n";
    return $o;
}

function tracking_body(array $c): string
{
    $id = tracking_ids($c);
    $o = '';
    if ($id['gtm']) {
        $o .= "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id={$id['gtm']}\" height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n";
    }
    return $o . (string) ($c['tracking']['body_code'] ?? '') . "\n";
}

function valid_color(string $v, string $fallback): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : $fallback;
}

/**
 * $o: title, description, canonical, indexable (bool), schema (array of JSON-LD nodes), lead (array|null), body_class
 */
function page_head(array $c, array $o): void
{
    $seo = $c['seo'];
    $b = $c['brand'];
    $indexable = ($o['indexable'] ?? true) && !empty($seo['indexable']);
    $ogImage = url_for((string) $seo['og_image'], true);
    $lang = preg_match('/^[a-z]{2}(-[A-Za-z]{2})?$/', (string) $seo['language']) ? $seo['language'] : 'en';
    security_headers();
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($o['title']) ?></title>
<meta name="description" content="<?= e($o['description']) ?>">
<link rel="canonical" href="<?= e($o['canonical']) ?>">
<meta name="robots" content="<?= $indexable ? 'index, follow, max-image-preview:large, max-snippet:-1' : 'noindex, nofollow' ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($seo['site_name']) ?>">
<meta property="og:title" content="<?= e($o['og_title'] ?? $o['title']) ?>">
<meta property="og:description" content="<?= e($o['og_description'] ?? $o['description']) ?>">
<meta property="og:url" content="<?= e($o['canonical']) ?>">
<?php if ($ogImage !== ''): ?>
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?= e($ogImage) ?>">
<?php endif; ?>
<meta name="twitter:title" content="<?= e($o['og_title'] ?? $o['title']) ?>">
<meta name="twitter:description" content="<?= e($o['og_description'] ?? $o['description']) ?>">
<meta name="theme-color" content="<?= e(valid_color((string) $b['color_navy'], '#0B3247')) ?>">
<?php if (!empty($b['favicon'])): ?>
<link rel="icon" href="<?= e(url_for((string) $b['favicon'])) ?>">
<link rel="apple-touch-icon" href="<?= e(url_for((string) $b['favicon'])) ?>">
<?php endif; ?>
<link rel="preload" href="<?= e(base_path()) ?>assets/fonts/source-sans-3-var.woff" as="font" type="font/woff" crossorigin>
<link rel="preload" href="<?= e(base_path()) ?>assets/fonts/lora-var.woff" as="font" type="font/woff" crossorigin>
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
<style>:root{--navy:<?= valid_color((string) $b['color_navy'], '#0B3247') ?>;--sky:<?= valid_color((string) $b['color_sky'], '#1F8FCB') ?>;--orange:<?= valid_color((string) $b['color_orange'], '#E8611C') ?>;--button:<?= valid_color((string) $b['color_button'], '#C84B0A') ?>}</style>
<?php
    foreach ($o['schema'] ?? [] as $node) {
        echo json_ld($node);
    }
    echo tracking_head($c, $o['lead'] ?? null);
    ?>
</head>
<body class="<?= e($o['body_class'] ?? '') ?>">
<?= tracking_body($c) ?>
<a class="skip" href="#main">Skip to content</a>
<?php
}

function site_header(array $c, string $home = ''): void
{
    $h = $c['header'];
    ?>
<header class="site-header" id="top">
  <div class="wrap site-header__in">
    <a class="logo" href="<?= e($home !== '' ? $home : '#top') ?>" aria-label="<?= e($c['seo']['site_name']) ?> home">
      <?= img_tag((string) $c['brand']['logo'], (string) $c['brand']['logo_alt'], ['loading' => 'eager', 'fetchpriority' => 'high']) ?>
    </a>
    <?php if ($h['links']): ?>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
    <nav class="site-nav" id="site-nav" aria-label="Main">
      <ul>
        <?php foreach ($h['links'] as $l): if (trim((string) ($l['label'] ?? '')) === '') continue; ?>
        <li><a href="<?= e($home . ($l['target'] ?? '#')) ?>"><?= e($l['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <?php endif; ?>
    <?php if (trim((string) $h['cta_label']) !== ''): ?>
    <a class="btn btn--small" href="<?= e($home) ?>#register"><?= e($h['cta_label']) ?></a>
    <?php endif; ?>
  </div>
</header>
<?php
}

function whatsapp_url(array $c): string
{
    $ct = $c['contact'];
    $num = preg_replace('/\D+/', '', (string) $ct['whatsapp_number']);
    if (empty($ct['whatsapp_enabled']) || $num === '') {
        return '';
    }
    return 'https://wa.me/' . $num . (trim((string) $ct['whatsapp_message']) !== '' ? '?text=' . rawurlencode((string) $ct['whatsapp_message']) : '');
}

function icon_whatsapp(): string
{
    return '<svg class="ico" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.200 8.200 0 0 1-4.200-1.150l-.3-.18-3 .78.800-2.900-.2-.300A8.200 8.200 0 1 1 12 20.200Zm4.500-6.100c-.250-.120-1.460-.720-1.690-.800-.220-.080-.390-.120-.550.120-.160.250-.630.800-.780.970-.140.160-.290.180-.530.060a6.700 6.700 0 0 1-3.330-2.910c-.250-.430.250-.400.720-1.330.080-.160.040-.300-.020-.430-.060-.120-.550-1.330-.760-1.820-.200-.480-.400-.410-.550-.420h-.470a.900.900 0 0 0-.650.300c-.220.250-.860.840-.860 2.050s.880 2.380 1 2.540c.120.160 1.730 2.640 4.190 3.700 1.560.670 2.170.730 2.950.610.470-.070 1.460-.600 1.670-1.170.200-.580.200-1.070.140-1.170-.060-.110-.220-.170-.470-.300Z"/></svg>';
}

function site_footer(array $c, bool $withSticky = true): void
{
    $f = $c['footer'];
    $ct = $c['contact'];
    $wa = whatsapp_url($c);
    ?>
<footer class="site-footer">
  <div class="wrap site-footer__in">
    <div class="site-footer__brand">
      <?= img_tag((string) $c['brand']['logo'], (string) $c['brand']['logo_alt']) ?>
      <?php if (trim((string) $f['text']) !== ''): ?><p><?= e($f['text']) ?></p><?php endif; ?>
    </div>
    <address class="site-footer__contact">
      <?php if (trim((string) $ct['phone']) !== ''): ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $ct['phone'])) ?>"><?= e($ct['phone']) ?></a><?php endif; ?>
      <?php if (trim((string) $ct['email']) !== ''): ?><a href="mailto:<?= e($ct['email']) ?>"><?= e($ct['email']) ?></a><?php endif; ?>
      <?php foreach ($f['links'] as $l): if (trim((string) ($l['label'] ?? '')) === '') continue; ?>
      <a href="<?= e($l['url'] ?? '#') ?>" rel="noopener"><?= e($l['label']) ?></a>
      <?php endforeach; ?>
    </address>
  </div>
  <div class="wrap site-footer__legal"><?= e(str_replace('{year}', gmdate('Y'), (string) $f['copyright'])) ?></div>
</footer>
<?php if ($withSticky): ?>
<div class="sticky-cta" data-sticky hidden>
  <a class="btn" href="#register"><?= e($ct['sticky_label']) ?></a>
  <?php if ($wa !== ''): ?><a class="btn btn--wa" href="<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="<?= e($ct['whatsapp_label']) ?>"><?= icon_whatsapp() ?></a><?php endif; ?>
</div>
<?php endif; ?>
<script src="<?= e(asset('assets/js/site.js')) ?>" defer></script>
</body>
</html>
<?php
}

/**
 * The lead form. $variant: "hero" (top of page) or "contact" (bottom). Both show the same fields.
 * $flash: ['form' => variant, 'errors' => [field => message], 'old' => [field => value]]
 */
function lead_form(array $c, string $variant, array $flash = [], string $preselectCohort = ''): void
{
    $ct = $c['contact'];
    $countries = require __DIR__ . '/countries.php';
    $mine = ($flash['form'] ?? '') === $variant;
    $errors = $mine ? ($flash['errors'] ?? []) : [];
    $old = $mine ? ($flash['old'] ?? []) : [];
    $id = fn(string $f) => $variant . '-' . $f;
    $val = fn(string $f, string $d = '') => (string) ($old[$f] ?? $d);
    $country = $val('country', isset($countries[$ct['default_country']]) ? (string) $ct['default_country'] : 'LB');
    $dial = $countries[$country][1] ?? '';
    $cohort = $val('cohort', $preselectCohort);
    $err = function (string $f) use ($errors, $id) {
        return isset($errors[$f]) ? '<p class="field__error" id="' . e($id($f)) . '-error">' . e($errors[$f]) . '</p>' : '';
    };
    $aria = fn(string $f) => isset($errors[$f]) ? ' aria-invalid="true" aria-describedby="' . e($id($f)) . '-error"' : '';
    ?>
<form class="lead-form" method="post" action="<?= e(base_path()) ?>submit.php" data-lead-form>
  <?php if ($errors): ?>
  <p class="lead-form__alert" role="alert" tabindex="-1"><?= e($errors['_form'] ?? 'Please check the highlighted fields and send the form again.') ?></p>
  <?php endif; ?>
  <input type="hidden" name="form" value="<?= e($variant) ?>">
  <input type="hidden" name="token" value="<?= e(form_token()) ?>">
  <div class="hp" aria-hidden="true">
    <label for="<?= e($id('company_website')) ?>">Leave this field empty</label>
    <input type="text" id="<?= e($id('company_website')) ?>" name="company_website" tabindex="-1" autocomplete="off">
  </div>
  <div class="field">
    <label for="<?= e($id('name')) ?>"><?= e($ct['label_name']) ?></label>
    <input type="text" id="<?= e($id('name')) ?>" name="name" value="<?= e($val('name')) ?>" autocomplete="name" required maxlength="100"<?= $aria('name') ?>>
    <?= $err('name') ?>
  </div>
  <div class="field">
    <label for="<?= e($id('email')) ?>"><?= e($ct['label_email']) ?></label>
    <input type="email" id="<?= e($id('email')) ?>" name="email" value="<?= e($val('email')) ?>" autocomplete="email" inputmode="email" required maxlength="150"<?= $aria('email') ?>>
    <?= $err('email') ?>
  </div>
  <div class="field-row">
    <div class="field">
      <label for="<?= e($id('country')) ?>"><?= e($ct['label_country']) ?></label>
      <select id="<?= e($id('country')) ?>" name="country" autocomplete="country" required data-country<?= $aria('country') ?>>
        <?php foreach ($countries as $iso => [$name, $code]): ?>
        <option value="<?= e($iso) ?>" data-dial="<?= e($code) ?>"<?= $iso === $country ? ' selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
      </select>
      <?= $err('country') ?>
    </div>
    <div class="field">
      <label for="<?= e($id('phone')) ?>"><?= e($ct['label_phone']) ?></label>
      <div class="phone">
        <span class="phone__dial" data-dial-out><?= $dial !== '' ? '+' . e($dial) : '+' ?></span>
        <input type="tel" id="<?= e($id('phone')) ?>" name="phone" value="<?= e($val('phone')) ?>" autocomplete="tel-national" inputmode="tel" required maxlength="20"<?= $aria('phone') ?>>
      </div>
      <?= $err('phone') ?>
    </div>
  </div>
  <?php $up = upcoming_cohorts($c); ?>
  <?php if ($up): ?>
  <div class="field">
    <label for="<?= e($id('cohort')) ?>"><?= e($ct['label_cohort']) ?></label>
    <select id="<?= e($id('cohort')) ?>" name="cohort" data-cohort-select>
      <option value="">Not sure yet</option>
      <?php foreach ($up as $co): ?>
      <option value="<?= e($co['start']) ?>"<?= $cohort === $co['start'] ? ' selected' : '' ?>><?= e(cohort_label($co)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <div class="field">
    <label for="<?= e($id('message')) ?>"><?= e($ct['label_message']) ?></label>
    <textarea id="<?= e($id('message')) ?>" name="message" rows="3" maxlength="2000" placeholder="<?= e($ct['placeholder_message']) ?>"><?= e($val('message')) ?></textarea>
  </div>
  <button class="btn btn--block" type="submit"><?= e($variant === 'hero' ? $c['hero']['form_button'] : $ct['form_button']) ?></button>
  <?php if (trim((string) $ct['privacy_note']) !== ''): ?><p class="lead-form__note"><?= e($ct['privacy_note']) ?></p><?php endif; ?>
</form>
<?php
}

/** Structured data for the landing page, built from the same content the visitor sees. */
function landing_schema(array $c): array
{
    $url = site_url($c);
    $orgId = $url . '#organization';
    $personId = $url . '#lina-cherry';
    $ct = $c['contact'];
    $d = $c['director'];
    $nodes = [];

    $org = [
        '@type' => 'EducationalOrganization',
        '@id' => $orgId,
        'name' => $c['seo']['site_name'],
        'url' => $url,
        'logo' => url_for((string) $c['brand']['logo'], true),
        'slogan' => $c['hero']['tagline'],
        'areaServed' => $c['seo']['region'],
    ];
    if (trim((string) $ct['email']) !== '' || trim((string) $ct['phone']) !== '') {
        $org['contactPoint'] = array_filter([
            '@type' => 'ContactPoint',
            'contactType' => 'admissions',
            'email' => $ct['email'],
            'telephone' => $ct['phone'],
        ]);
    }
    $person = array_filter([
        '@type' => 'Person',
        '@id' => $personId,
        'name' => $d['name'],
        'jobTitle' => $d['role'],
        'description' => $d['credential'],
        'image' => url_for((string) $d['photo'], true),
        'worksFor' => ['@id' => $orgId],
    ]);

    $instances = [];
    foreach (upcoming_cohorts($c) as $co) {
        $instances[] = array_filter([
            '@type' => 'CourseInstance',
            'courseMode' => 'Online',
            'startDate' => $co['start'],
            'endDate' => $co['end'] ?? '',
            'instructor' => ['@id' => $personId],
        ]);
    }
    $course = [
        '@type' => 'Course',
        '@id' => $url . '#course',
        'name' => $c['program']['title'] . ': ICF Level 1 coach training',
        'description' => $c['program']['lead'],
        'url' => $url,
        'inLanguage' => $c['seo']['language'],
        'provider' => ['@id' => $orgId],
        'educationalCredentialAwarded' => 'ICF Level 1 accredited coaching education',
        'timeRequired' => 'P16W',
    ];
    if ($instances) {
        $course['hasCourseInstance'] = $instances;
    }

    $page = [
        '@type' => 'WebPage',
        '@id' => $url . '#webpage',
        'url' => $url,
        'name' => $c['seo']['title'],
        'description' => $c['seo']['description'],
        'inLanguage' => $c['seo']['language'],
        'about' => ['@id' => $url . '#course'],
        'publisher' => ['@id' => $orgId],
        'primaryImageOfPage' => url_for((string) $c['seo']['og_image'], true),
    ];
    $nodes[] = ['@context' => 'https://schema.org', '@graph' => [$page, $org, $person, $course]];

    if (!empty($c['faq']['enabled']) && $c['faq']['items']) {
        $qa = [];
        foreach ($c['faq']['items'] as $it) {
            if (trim((string) ($it['q'] ?? '')) === '' || trim((string) ($it['a'] ?? '')) === '') {
                continue;
            }
            $qa[] = ['@type' => 'Question', 'name' => $it['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $it['a']]];
        }
        if ($qa) {
            $nodes[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $qa];
        }
    }
    return $nodes;
}

/** Turn a YouTube or Vimeo link into an embeddable URL; returns '' if it is not recognised. */
function video_embed_url(string $url): string
{
    $url = trim($url);
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_\-]{6,15})~', $url, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0';
    }
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return '';
}
