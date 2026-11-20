<?php
/**
 * Core helpers shared by the landing page, thank-you page, form handler and admin.
 * No database: everything lives in /data as guarded JSON files.
 */
declare(strict_types=1);

if (!defined('APP_DEPTH')) {
    define('APP_DEPTH', 0);
}
define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');
define('UPLOAD_DIR', ROOT . '/uploads');
// First line of every data file: if the file is ever requested directly, PHP exits before any output.
define('GUARD', "<?php http_response_code(403); exit; ?>\n");

if (!function_exists('array_is_list')) { // PHP 7.4 / 8.0
    function array_is_list(array $a): bool
    {
        return $a === [] || array_keys($a) === range(0, count($a) - 1);
    }
}

mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');

/* ---------- output helpers ---------- */

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Turn plain text into paragraphs: blank line = new paragraph, single line break = <br>. */
function paragraphs(string $text, string $class = ''): string
{
    $out = '';
    foreach (preg_split('/\n\s*\n/', trim($text)) as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        $out .= '<p' . ($class ? ' class="' . e($class) . '"' : '') . '>' . nl2br(e($p), false) . "</p>\n";
    }
    return $out;
}

/** Keep hyphenated words such as "ICF-accredited" on one line. Expects already-escaped text. */
function keep_hyphenated(string $escaped): string
{
    return preg_replace('/(?<![\w&#;])([\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)+)/u', '<span class="nb">$1</span>', $escaped);
}

function fmt_date(string $ymd, string $format = 'F j, Y'): string
{
    $d = DateTime::createFromFormat('!Y-m-d', $ymd);
    return $d ? $d->format($format) : $ymd;
}

/* ---------- storage ---------- */

function read_guarded(string $file, array $default = []): array
{
    $path = DATA_DIR . '/' . $file;
    if (!is_file($path)) {
        return $default;
    }
    $raw = (string) file_get_contents($path);
    if (strncmp($raw, '<?php', 5) === 0) {
        $pos = strpos($raw, "\n");
        $raw = $pos === false ? '' : substr($raw, $pos + 1);
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

function write_guarded(string $file, array $data): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $tmp = DATA_DIR . '/tmp-' . bin2hex(random_bytes(5)) . '-' . $file; // still ends in .php
    if (file_put_contents($tmp, GUARD . $json, LOCK_EX) === false) {
        return false;
    }
    return rename($tmp, DATA_DIR . '/' . $file);
}

/** Run a read-modify-write on a data file while holding an exclusive lock. */
function with_lock(callable $fn)
{
    $h = fopen(DATA_DIR . '/.lock', 'c');
    if ($h) {
        flock($h, LOCK_EX);
    }
    try {
        return $fn();
    } finally {
        if ($h) {
            flock($h, LOCK_UN);
            fclose($h);
        }
    }
}

/** Saved values win; anything not saved yet falls back to the shipped defaults. Lists are never merged. */
function merge_content(array $def, array $cur): array
{
    foreach ($cur as $k => $v) {
        if (is_array($v) && isset($def[$k]) && is_array($def[$k]) && $def[$k] !== [] && !array_is_list($def[$k])) {
            if ($v !== [] && !array_is_list($v)) {
                $def[$k] = merge_content($def[$k], $v);
            }
            continue;
        }
        $def[$k] = $v;
    }
    return $def;
}

function load_content(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = merge_content(require __DIR__ . '/defaults.php', read_guarded('content.json.php'));
    }
    return $cache;
}

function config(): array
{
    return read_guarded('config.json.php');
}

function save_config(array $cfg): bool
{
    return write_guarded('config.json.php', $cfg);
}

/** Per-install secret used to sign form tokens. Created on first use. */
function app_secret(): string
{
    $cfg = config();
    if (empty($cfg['secret'])) {
        $cfg['secret'] = bin2hex(random_bytes(32));
        save_config($cfg);
    }
    return $cfg['secret'];
}

/* ---------- URLs ---------- */

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

/** URL path of the site root with a trailing slash, e.g. "/" or "/landing/". */
function base_path(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    for ($i = 0; $i < APP_DEPTH; $i++) {
        $dir = dirname($dir);
    }
    $dir = str_replace('\\', '/', $dir);
    return rtrim($dir, '/') . '/';
}

/** Absolute site URL with trailing slash. Uses the value saved in the CMS, otherwise detects it. */
function site_url(?array $c = null): string
{
    $c = $c ?? load_content();
    $u = trim((string) ($c['seo']['site_url'] ?? ''));
    if ($u !== '' && preg_match('#^https?://#i', $u)) {
        return rtrim($u, '/') . '/';
    }
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return (is_https() ? 'https' : 'http') . '://' . $host . base_path();
}

/** Turn a stored path ("assets/img/x.avif", "uploads/y.png" or a full URL) into something usable in href/src. */
function url_for(string $path, bool $absolute = false): string
{
    $path = trim($path);
    if ($path === '' || preg_match('#^(https?:)?//#i', $path) || strncmp($path, 'data:', 5) === 0) {
        return $path;
    }
    return ($absolute ? site_url() : base_path()) . ltrim($path, '/');
}

/** Static asset URL with a cache-busting version. */
function asset(string $path): string
{
    $file = ROOT . '/' . $path;
    return base_path() . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/**
 * <img> with width/height (prevents layout shift). For bundled AVIF files that have a WebP twin,
 * outputs <picture> so older browsers still get an image.
 */
function img_tag(string $path, string $alt, array $attr = []): string
{
    if ($path === '') {
        return '';
    }
    $local = !preg_match('#^(https?:)?//#i', $path) ? ROOT . '/' . ltrim($path, '/') : null;
    if ($local && is_file($local) && !isset($attr['width'])) {
        $size = @getimagesize($local);
        if ($size) {
            $attr['width'] = $size[0];
            $attr['height'] = $size[1];
        }
    }
    $attr += ['loading' => 'lazy', 'decoding' => 'async'];
    if ($attr['loading'] === 'eager') {
        unset($attr['decoding']);
    }
    $a = '';
    foreach ($attr as $k => $v) {
        $a .= ' ' . $k . '="' . e($v) . '"';
    }
    $src = url_for($path);
    if ($local && preg_match('/\.avif$/i', $path)) {
        $webp = preg_replace('/\.avif$/i', '.webp', $path);
        if (is_file(ROOT . '/' . ltrim($webp, '/'))) {
            return '<picture><source srcset="' . e($src) . '" type="image/avif"><img src="' . e(url_for($webp)) . '" alt="' . e($alt) . '"' . $a . '></picture>';
        }
    }
    return '<img src="' . e($src) . '" alt="' . e($alt) . '"' . $a . '>';
}

/* ---------- sessions & security ---------- */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('cma_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_path(),
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function client_ip_hash(): string
{
    return substr(hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), app_secret()), 0, 20);
}

/** Simple sliding-window limiter stored in /data. Returns false when the caller is over the limit. */
function rate_limit(string $bucket, int $max, int $windowSeconds, bool $record = true): bool
{
    return with_lock(function () use ($bucket, $max, $windowSeconds, $record) {
        $all = read_guarded('ratelimit.json.php');
        $now = time();
        $key = $bucket . ':' . client_ip_hash();
        foreach ($all as $k => $times) {
            $all[$k] = array_values(array_filter((array) $times, fn($t) => $t > $now - 3600));
            if (!$all[$k]) {
                unset($all[$k]);
            }
        }
        $recent = array_filter($all[$key] ?? [], fn($t) => $t > $now - $windowSeconds);
        $ok = count($recent) < $max;
        if ($record) {
            $all[$key][] = $now;
        }
        write_guarded('ratelimit.json.php', $all);
        return $ok;
    });
}

function rate_limit_clear(string $bucket): void
{
    with_lock(function () use ($bucket) {
        $all = read_guarded('ratelimit.json.php');
        unset($all[$bucket . ':' . client_ip_hash()]);
        write_guarded('ratelimit.json.php', $all);
    });
}

/* ---------- public form token (signed timestamp, no cookies needed) ---------- */

function form_token(): string
{
    $ts = (string) time();
    return $ts . '.' . hash_hmac('sha256', 'form|' . $ts, app_secret());
}

/** Returns the age of the token in seconds, or null if it is not ours. */
function form_token_age(string $token): ?int
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0])) {
        return null;
    }
    if (!hash_equals(hash_hmac('sha256', 'form|' . $parts[0], app_secret()), $parts[1])) {
        return null;
    }
    return time() - (int) $parts[0];
}

/* ---------- cohorts ---------- */

/** Cohorts that have not started yet, soonest first. */
function upcoming_cohorts(array $c): array
{
    $today = gmdate('Y-m-d');
    $list = array_values(array_filter($c['dates']['items'] ?? [], fn($x) => !empty($x['start']) && $x['start'] >= $today));
    usort($list, fn($a, $b) => strcmp($a['start'], $b['start']));
    return $list;
}

function cohort_label(array $cohort): string
{
    return fmt_date($cohort['start']) . (!empty($cohort['end']) ? ' – ' . fmt_date($cohort['end']) : '');
}

/* ---------- leads ---------- */

function leads_all(): array
{
    return read_guarded('leads.json.php');
}

function lead_add(array $lead): void
{
    with_lock(function () use ($lead) {
        $all = read_guarded('leads.json.php');
        array_unshift($all, $lead);
        write_guarded('leads.json.php', $all);
    });
}

function lead_statuses(): array
{
    return ['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'enrolled' => 'Enrolled', 'closed' => 'Not proceeding'];
}

/* ---------- mail ---------- */

/** Email addresses that receive each new lead: the list from the CMS plus the older single field. */
function lead_recipients(array $c): array
{
    $n = $c['notifications'] ?? [];
    $all = [];
    foreach ((array) ($n['recipients'] ?? []) as $r) {
        $all[] = is_array($r) ? (string) ($r['email'] ?? '') : (string) $r;
    }
    foreach (preg_split('/[,;\s]+/', (string) ($n['notify_email'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $e) {
        $all[] = $e;
    }
    $out = [];
    foreach ($all as $e) {
        $e = strtolower(clean_header($e));
        if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
            $out[$e] = $e;
        }
    }
    return array_values($out);
}

function clean_header(string $s): string
{
    return trim(preg_replace('/[\r\n\t]+/', ' ', $s));
}

function send_mail(string $to, string $subject, string $body, array $c, string $replyTo = ''): bool
{
    $to = clean_header($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $host = preg_replace('/^www\./', '', preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')));
    $from = clean_header((string) ($c['notifications']['from_email'] ?? ''));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = 'no-reply@' . preg_replace('/[^A-Za-z0-9.\-]/', '', $host);
    }
    $fromName = clean_header((string) ($c['notifications']['from_name'] ?? 'Coach Masters Academy'));
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
    ];
    $replyTo = clean_header($replyTo);
    if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    return @mail($to, '=?UTF-8?B?' . base64_encode(clean_header($subject)) . '?=', $body, implode("\r\n", $headers));
}
