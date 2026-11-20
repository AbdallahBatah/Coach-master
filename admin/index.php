<?php
/**
 * Admin panel: edit every piece of content, manage leads, upload images.
 * Single file on purpose — nothing to install, no database.
 */
declare(strict_types=1);
define('APP_DEPTH', 1);
require dirname(__DIR__) . '/inc/bootstrap.php';

$schema = require ROOT . '/inc/schema.php';
start_session();
security_headers();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$self = base_path() . 'admin/';
$cfg = config();
$hasPassword = !empty($cfg['password_hash']);
$pwMark = $hasPassword ? substr((string) $cfg['password_hash'], -16) : '';
$authed = $hasPassword
    && !empty($_SESSION['admin'])
    && ($_SESSION['admin_seen'] ?? 0) > time() - 7200
    && hash_equals($pwMark, (string) ($_SESSION['admin_pw'] ?? ''));
if ($authed) {
    $_SESSION['admin_seen'] = time();
}
if (!$hasPassword && empty($cfg['setup_key'])) {
    // Fresh install without a key (for example deployed from Git, where /data is not versioned):
    // create one. Reading it requires access to the server's files, which proves ownership.
    $cfg['setup_key'] = strtoupper(implode('-', str_split(bin2hex(random_bytes(6)), 4)));
    save_config($cfg);
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}

/* ---------- small helpers ---------- */

function go(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['admin_flash'] = ['msg' => $msg, 'type' => $type];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function find_section(array $schema, string $key): ?array
{
    foreach ($schema as $sections) {
        if (isset($sections[$key])) {
            return $sections[$key];
        }
    }
    return null;
}

function clean_line($v, int $max = 500): string
{
    $v = is_string($v) ? $v : '';
    return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v) ?? ''), 0, $max);
}

function clean_block($v, int $max = 20000): string
{
    $v = is_string($v) ? str_replace(["\r\n", "\r"], "\n", $v) : '';
    return mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? ''), 0, $max);
}

function clean_image_path($v): string
{
    $v = clean_line($v, 400);
    if ($v === '' || preg_match('#^https://[^\s"\'<>]+$#i', $v)) {
        return $v;
    }
    return (preg_match('#^(assets|uploads)/[A-Za-z0-9._\-/]+$#', $v) && strpos($v, '..') === false) ? $v : '';
}

/** Returns [stored path|null, error|null]. */
function handle_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return [null, 'The image could not be uploaded. It may be larger than this server allows.'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return [null, 'Images must be 5 MB or smaller.'];
    }
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif', 'image/gif' => 'gif'];
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($types[$mime])) {
        $info = @getimagesize($file['tmp_name']);
        $mime = $info['mime'] ?? '';
    }
    if (!isset($types[$mime])) {
        return [null, 'Use a JPG, PNG, WebP, AVIF or GIF image.'];
    }
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo((string) $file['name'], PATHINFO_FILENAME))), '-');
    $name = mb_substr($base !== '' ? $base : 'image', 0, 60) . '-' . bin2hex(random_bytes(3)) . '.' . $types[$mime];
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        return [null, 'The image could not be saved. Check that the uploads folder is writable.'];
    }
    return ['uploads/' . $name, null];
}

/** Read one section's values from the submitted form, following the schema. */
function values_from_post(array $fields, array $posted, array $current, array &$errors, bool $top = true): array
{
    $out = [];
    foreach ($fields as $name => $f) {
        $raw = $posted[$name] ?? null;
        switch ($f['type']) {
            case 'toggle':
                $out[$name] = $raw === '1';
                break;
            case 'textarea':
                $out[$name] = clean_block($raw);
                break;
            case 'code':
                $out[$name] = is_string($raw) ? trim(str_replace(["\r\n", "\r"], "\n", $raw)) : '';
                break;
            case 'lines':
                $out[$name] = array_values(array_filter(array_map(fn($l) => clean_line($l, 1000), explode("\n", clean_block($raw))), fn($l) => $l !== ''));
                break;
            case 'url':
                $v = clean_line($raw, 500);
                if ($v !== '' && !preg_match('#^https?://[^\s"\'<>]+$#i', $v)) {
                    $errors[] = '"' . $f['label'] . '" must start with https:// — the previous value was kept.';
                    $v = (string) ($current[$name] ?? '');
                }
                $out[$name] = $v;
                break;
            case 'email':
                $v = clean_line($raw, 200);
                if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = $top
                        ? '"' . $f['label'] . '" is not a valid email address — the previous value was kept.'
                        : '"' . $v . '" is not a valid email address and was not saved.';
                    $v = (string) ($current[$name] ?? '');
                }
                $out[$name] = $v;
                break;
            case 'date':
                $v = clean_line($raw, 10);
                $out[$name] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && DateTime::createFromFormat('!Y-m-d', $v) ? $v : '';
                break;
            case 'color':
                $v = clean_line($raw, 7);
                $out[$name] = preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtoupper($v) : (string) ($current[$name] ?? '');
                break;
            case 'image':
                $out[$name] = clean_image_path($raw);
                if ($top && isset($_FILES['upload']['name'][$name])) {
                    [$path, $err] = handle_upload([
                        'name' => $_FILES['upload']['name'][$name],
                        'tmp_name' => $_FILES['upload']['tmp_name'][$name],
                        'error' => $_FILES['upload']['error'][$name],
                        'size' => $_FILES['upload']['size'][$name],
                    ]);
                    if ($err) {
                        $errors[] = $f['label'] . ': ' . $err;
                    } elseif ($path) {
                        $out[$name] = $path;
                    }
                }
                break;
            case 'list':
                $rows = [];
                foreach (is_array($raw) ? $raw : [] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $vals = values_from_post($f['fields'], $row, [], $errors, false);
                    if (!empty($f['require']) && ($vals[$f['require']] ?? '') === '') {
                        continue;
                    }
                    if (array_filter($vals, fn($x) => $x !== '' && $x !== false && $x !== [])) {
                        $rows[] = $vals;
                    }
                }
                $out[$name] = array_slice($rows, 0, 60);
                break;
            default:
                $out[$name] = clean_line($raw, 1000);
        }
    }
    return $out;
}

function field_control(array $f, $value, string $name, string $id): string
{
    $help = !empty($f['help']) ? '<p class="help" id="' . e($id) . '-help">' . e($f['help']) . '</p>' : '';
    $desc = !empty($f['help']) ? ' aria-describedby="' . e($id) . '-help"' : '';
    $count = !empty($f['count']) ? ' data-count="' . (int) $f['count'] . '"' : '';
    $label = '<label for="' . e($id) . '">' . e($f['label']) . '</label>';
    switch ($f['type']) {
        case 'toggle':
            return '<div class="f f--toggle"><input type="hidden" name="' . e($name) . '" value="0"><label class="switch"><input type="checkbox" id="' . e($id) . '" name="' . e($name) . '" value="1"' . ($value ? ' checked' : '') . $desc . '><span>' . e($f['label']) . '</span></label>' . $help . '</div>';
        case 'textarea':
        case 'code':
        case 'lines':
            $text = $f['type'] === 'lines' ? implode("\n", (array) $value) : (string) $value;
            $rows = (int) ($f['rows'] ?? ($f['type'] === 'lines' ? max(4, count((array) $value) + 1) : 4));
            return '<div class="f">' . $label . '<textarea id="' . e($id) . '" name="' . e($name) . '" rows="' . $rows . '"' . ($f['type'] === 'code' ? ' class="mono" spellcheck="false"' : '') . $desc . $count . '>' . e($text) . '</textarea>' . $help . '</div>';
        case 'image':
            $val = (string) $value;
            $prev = $val !== '' ? '<img src="' . e(preg_match('#^https?://#i', $val) ? $val : base_path() . $val) . '" alt="">' : '<span>No image</span>';
            return '<div class="f f--image">' . $label . '<div class="image-row"><div class="image-prev">' . $prev . '</div><div class="image-ctl">'
                . '<input type="file" name="upload[' . e(basename(str_replace(['[', ']'], ['/', ''], $name))) . ']" accept="image/jpeg,image/png,image/webp,image/avif,image/gif" aria-label="Upload a new ' . e($f['label']) . '">'
                . '<input type="text" id="' . e($id) . '" name="' . e($name) . '" value="' . e($val) . '" placeholder="uploads/… or https://…"' . $desc . '>'
                . '</div></div>' . $help . '</div>';
        case 'color':
            return '<div class="f f--color">' . $label . '<input type="color" id="' . e($id) . '" name="' . e($name) . '" value="' . e(valid_hex((string) $value)) . '"' . $desc . '>' . $help . '</div>';
        case 'date':
            return '<div class="f">' . $label . '<input type="date" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '"' . $desc . '>' . $help . '</div>';
        default:
            $type = $f['type'] === 'email' ? 'email' : ($f['type'] === 'url' ? 'url' : 'text');
            return '<div class="f">' . $label . '<input type="' . $type . '" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '"' . $desc . $count . '>' . $help . '</div>';
    }
}

function valid_hex(string $v): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : '#000000';
}

function list_row(array $f, string $field, string $rowId, array $row): string
{
    $o = '<div class="row" data-row><div class="row__fields">';
    foreach ($f['fields'] as $sub => $sf) {
        $o .= field_control($sf, $row[$sub] ?? '', "data[$field][$rowId][$sub]", "f-$field-$rowId-$sub");
    }
    $item = e($f['item'] ?? 'item');
    return $o . '</div><div class="row__tools"><button type="button" class="tool" data-move="-1" aria-label="Move ' . $item . ' up">↑</button><button type="button" class="tool" data-move="1" aria-label="Move ' . $item . ' down">↓</button><button type="button" class="tool tool--danger" data-remove aria-label="Remove ' . $item . '">Remove</button></div></div>';
}

function render_fields(array $fields, array $values): string
{
    $o = '';
    foreach ($fields as $name => $f) {
        if ($f['type'] === 'list') {
            $o .= '<fieldset class="list" data-list><legend>' . e($f['label']) . '</legend>' . (!empty($f['help']) ? '<p class="help list__help">' . e($f['help']) . '</p>' : '') . '<div data-rows>';
            foreach (array_values((array) ($values[$name] ?? [])) as $i => $row) {
                $o .= list_row($f, $name, 'r' . $i, (array) $row);
            }
            $o .= '</div><template>' . list_row($f, $name, '__ID__', []) . '</template>'
                . '<button type="button" class="btn btn--quiet" data-add>Add ' . e($f['item'] ?? 'item') . '</button></fieldset>';
            continue;
        }
        $o .= field_control($f, $values[$name] ?? '', "data[$name]", "f-$name");
    }
    return $o;
}

function lead_matches(array $l, string $q): bool
{
    if ($q === '') {
        return true;
    }
    $hay = mb_strtolower(implode(' ', [$l['name'] ?? '', $l['email'] ?? '', $l['phone'] ?? '', $l['country'] ?? '', $l['message'] ?? '', $l['notes'] ?? '', implode(' ', (array) ($l['source'] ?? []))]));
    return mb_strpos($hay, mb_strtolower($q)) !== false;
}

/* ---------- POST actions ---------- */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        flash('Your session expired. Please try again.', 'error');
        go($self);
    }
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'setup' && !$hasPassword) {
        $key = trim((string) ($_POST['setup_key'] ?? ''));
        $pw = (string) ($_POST['password'] ?? '');
        $user = clean_line($_POST['username'] ?? '', 150);
        $_SESSION['auth_user'] = $user;
        if (mb_strlen($user) < 3) {
            flash('Enter the email address or username you will sign in with (at least 3 characters).', 'error');
        } elseif (empty($cfg['setup_key']) || !hash_equals((string) $cfg['setup_key'], $key)) {
            flash('That setup key is not correct. Copy it from the file data/config.json.php on the server.', 'error');
        } elseif (strlen($pw) < 10) {
            flash('Choose a password with at least 10 characters.', 'error');
        } elseif ($pw !== (string) ($_POST['password2'] ?? '')) {
            flash('The two passwords do not match.', 'error');
        } else {
            $cfg['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
            $cfg['admin_user'] = $user;
            unset($cfg['setup_key']);
            save_config($cfg);
            unset($_SESSION['auth_user']);
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['admin_seen'] = time();
            $_SESSION['admin_pw'] = substr($cfg['password_hash'], -16);
            flash('Your account is ready. Welcome.');
        }
        go($self);
    }

    if ($action === 'login' && $hasPassword) {
        $user = clean_line($_POST['username'] ?? '', 150);
        $_SESSION['auth_user'] = $user;
        $pwOk = password_verify((string) ($_POST['password'] ?? ''), (string) $cfg['password_hash']);
        $userOk = empty($cfg['admin_user']) || hash_equals(mb_strtolower((string) $cfg['admin_user']), mb_strtolower($user));
        if (!rate_limit('login', 5, 900, false)) {
            flash('Too many attempts. Please wait 15 minutes and try again.', 'error');
        } elseif ($pwOk && $userOk) {
            rate_limit_clear('login');
            unset($_SESSION['auth_user']);
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['admin_seen'] = time();
            $_SESSION['admin_pw'] = $pwMark;
        } else {
            rate_limit('login', 5, 900);
            flash('The username or password is not correct.', 'error');
        }
        go($self);
    }

    if (!$authed) {
        go($self);
    }

    if ($action === 'logout') {
        unset($_SESSION['admin'], $_SESSION['admin_seen'], $_SESSION['admin_pw']);
        session_regenerate_id(true);
        go($self);
    }

    if ($action === 'save' || $action === 'reset') {
        $key = (string) ($_POST['section'] ?? '');
        $section = find_section($schema, $key);
        if ($section) {
            $ok = with_lock(function () use ($action, $key, $section, &$errors) {
                $stored = read_guarded('content.json.php');
                if ($action === 'reset') {
                    unset($stored[$key]);
                } else {
                    $errors = [];
                    $current = load_content()[$key] ?? [];
                    $stored[$key] = values_from_post($section['fields'], (array) ($_POST['data'] ?? []), $current, $errors);
                }
                return write_guarded('content.json.php', $stored);
            });
            if (!$ok) {
                flash('Changes could not be saved. Check that the "data" folder is writable on the server.', 'error');
            } elseif (!empty($errors)) {
                flash('Saved, with notes: ' . implode(' ', $errors), 'warn');
            } else {
                flash($action === 'reset' ? $section['label'] . ' is back to the original content.' : $section['label'] . ' saved.');
            }
        }
        go($self . '?p=edit&s=' . rawurlencode($key));
    }

    if ($action === 'lead_update' || $action === 'lead_delete') {
        $id = (string) ($_POST['id'] ?? '');
        with_lock(function () use ($action, $id) {
            $all = read_guarded('leads.json.php');
            foreach ($all as $i => $l) {
                if (($l['id'] ?? '') !== $id) {
                    continue;
                }
                if ($action === 'lead_delete') {
                    unset($all[$i]);
                } else {
                    $status = (string) ($_POST['status'] ?? '');
                    if (isset(lead_statuses()[$status])) {
                        $all[$i]['status'] = $status;
                    }
                    if (isset($_POST['notes'])) {
                        $all[$i]['notes'] = clean_block($_POST['notes'], 3000);
                    }
                }
            }
            write_guarded('leads.json.php', array_values($all));
        });
        flash($action === 'lead_delete' ? 'Lead deleted.' : 'Lead updated.');
        go($self . '?p=leads' . (isset($_POST['back']) ? '&' . preg_replace('/[^A-Za-z0-9=&%_.\-]/', '', (string) $_POST['back']) : ''));
    }

    if ($action === 'media_upload') {
        [$path, $err] = handle_upload($_FILES['file'] ?? []);
        flash($err ?: ($path ? 'Image uploaded: ' . $path : 'Choose an image to upload.'), $path ? 'ok' : 'error');
        go($self . '?p=media');
    }

    if ($action === 'media_delete') {
        $name = basename((string) ($_POST['file'] ?? ''));
        if (preg_match('/^[A-Za-z0-9._\-]+\.(jpg|png|webp|avif|gif)$/', $name) && is_file(UPLOAD_DIR . '/' . $name)) {
            unlink(UPLOAD_DIR . '/' . $name);
            flash('Image deleted.');
        }
        go($self . '?p=media');
    }

    if ($action === 'test_email') {
        $c = load_content();
        $to = lead_recipients($c);
        if (!$to) {
            flash('Add at least one email address and save before sending a test.', 'error');
        } else {
            $sent = 0;
            foreach ($to as $addr) {
                $sent += send_mail($addr, 'Test: lead notifications are working', "This is a test from the admin panel of " . site_url($c) . ".\n\nNew enquiries from the landing page will be sent to this address.", $c) ? 1 : 0;
            }
            if ($sent === count($to)) {
                flash('Test email sent to ' . implode(', ', $to) . '. If it has not arrived in a few minutes, check the spam folder and the "Send from" address.');
            } else {
                flash('This server did not accept the email for sending (' . $sent . ' of ' . count($to) . ' sent). Ask the hosting provider to enable PHP mail, or use the webhook to forward leads.', 'error');
            }
        }
        go($self . '?p=edit&s=notifications');
    }

    if ($action === 'password') {
        $new = (string) ($_POST['password'] ?? '');
        $user = clean_line($_POST['username'] ?? '', 150);
        if (!password_verify((string) ($_POST['current'] ?? ''), (string) $cfg['password_hash'])) {
            flash('Your current password is not correct.', 'error');
        } elseif (mb_strlen($user) < 3) {
            flash('The username needs at least 3 characters.', 'error');
        } elseif ($new !== '' && strlen($new) < 10) {
            flash('Choose a new password with at least 10 characters.', 'error');
        } elseif ($new !== (string) ($_POST['password2'] ?? '')) {
            flash('The two new passwords do not match.', 'error');
        } else {
            $cfg['admin_user'] = $user;
            if ($new !== '') {
                $cfg['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
            }
            save_config($cfg);
            $_SESSION['admin_pw'] = substr($cfg['password_hash'], -16);
            flash($new !== '' ? 'Sign-in details saved. Your password has changed.' : 'Sign-in details saved.');
        }
        go($self . '?p=account');
    }
    go($self);
}

/* ---------- CSV export ---------- */

$page = is_string($_GET['p'] ?? null) ? $_GET['p'] : 'dashboard';
if ($authed && $page === 'export') {
    $cols = ['id', 'created', 'status', 'name', 'email', 'phone', 'country', 'cohort', 'message', 'form', 'notes'];
    $src = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'referrer'];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="leads-' . gmdate('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // lets Excel read Arabic names correctly
    fputcsv($out, array_merge($cols, $src), ',', '"', '');
    $safe = fn($v) => preg_match('/^[=@]|^[+\-](?!\d)/', (string) $v) ? "'" . $v : (string) $v; // no spreadsheet formulas
    foreach (leads_all() as $l) {
        $row = [];
        foreach ($cols as $k) {
            $row[] = $safe($l[$k] ?? '');
        }
        foreach ($src as $k) {
            $row[] = $safe($l['source'][$k] ?? '');
        }
        fputcsv($out, $row, ',', '"', '');
    }
    exit;
}

/* ---------- views ---------- */

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);
$c = load_content();
$title = 'Admin';
ob_start();

if (!$authed) {
    $title = $hasPassword ? 'Sign in' : 'Create your admin account';
    $lastUser = (string) ($_SESSION['auth_user'] ?? '');
    unset($_SESSION['auth_user']);
    ?>
    <div class="auth">
      <aside class="auth__side">
        <p class="auth__site"><?= e($c['seo']['site_name']) ?></p>
        <p class="auth__tagline"><?= e($c['hero']['tagline']) ?></p>
        <svg class="auth__path" viewBox="0 0 300 60" aria-hidden="true" focusable="false">
          <path d="M0,42 L50,42 C75,42 87,36 100,30" stroke="#DCE9F0"/>
          <path d="M100,30 C113,24 125,18 150,18 C175,18 187,24 200,30" stroke="#1F8FCB"/>
          <path d="M200,30 C213,36 225,42 250,42 L300,42" stroke="#E8611C"/>
          <circle cx="50" cy="42" r="6" fill="#DCE9F0"/><circle cx="150" cy="18" r="6" fill="#1F8FCB"/><circle cx="250" cy="42" r="6" fill="#E8611C"/>
        </svg>
        <p class="auth__note">Manage the landing page content, search settings and the enquiries it brings in.</p>
      </aside>
      <div class="auth__main">
        <img src="<?= e(url_for((string) $c['brand']['logo'])) ?>" alt="<?= e($c['brand']['logo_alt']) ?>" class="auth__logo">
        <h1><?= e($title) ?></h1>
        <?php if ($flash): ?><p class="notice notice--<?= e($flash['type']) ?>" role="alert"><?= e($flash['msg']) ?></p><?php endif; ?>
        <form method="post" class="stack">
          <?= csrf_field() ?>
          <?php if (!$hasPassword): ?>
          <p class="muted">Choose how you will sign in to this website's admin panel. This screen appears only once.</p>
          <input type="hidden" name="action" value="setup">
          <div class="f"><label for="setup_key">Setup key</label><input type="text" id="setup_key" name="setup_key" required autocomplete="off" autocapitalize="characters"><p class="help">Open the file <code>data/config.json.php</code> on the server (hosting file manager or FTP) and copy the value of "setup_key".</p></div>
          <div class="f"><label for="username">Email or username</label><input type="text" id="username" name="username" value="<?= e($lastUser) ?>" required minlength="3" autocomplete="username" autocapitalize="none" spellcheck="false"></div>
          <div class="f"><label for="password">Password</label><div class="pw"><input type="password" id="password" name="password" required minlength="10" autocomplete="new-password"><button type="button" class="pw__toggle" data-show-password aria-controls="password" aria-pressed="false">Show</button></div><p class="help">At least 10 characters.</p></div>
          <div class="f"><label for="password2">Repeat the password</label><input type="password" id="password2" name="password2" required minlength="10" autocomplete="new-password"></div>
          <button class="btn btn--wide" type="submit">Create account</button>
          <?php else: ?>
          <input type="hidden" name="action" value="login">
          <div class="f"><label for="username">Email or username</label><input type="text" id="username" name="username" value="<?= e($lastUser) ?>" required autocomplete="username" autocapitalize="none" spellcheck="false"<?= $lastUser === '' ? ' autofocus' : '' ?>></div>
          <div class="f"><label for="password">Password</label><div class="pw"><input type="password" id="password" name="password" required autocomplete="current-password"<?= $lastUser !== '' ? ' autofocus' : '' ?>><button type="button" class="pw__toggle" data-show-password aria-controls="password" aria-pressed="false">Show</button></div></div>
          <button class="btn btn--wide" type="submit">Sign in</button>
          <?php endif; ?>
        </form>
        <?php if ($hasPassword): ?>
        <details class="auth__help">
          <summary>Forgot your password?</summary>
          <p>Delete the file <code>data/config.json.php</code> on the server (hosting file manager or FTP), then reload this page to create new sign-in details. Your content and leads are not affected.</p>
        </details>
        <?php endif; ?>
        <p class="auth__back"><a href="<?= e(base_path()) ?>">Back to the website</a></p>
      </div>
    </div>
    <?php
} elseif ($page === 'edit' && is_string($_GET['s'] ?? null) && ($section = find_section($schema, $_GET['s']))) {
    $key = $_GET['s'];
    $title = $section['label'];
    ?>
    <header class="head"><h1><?= e($section['label']) ?></h1><a class="btn btn--quiet" href="<?= e(base_path() . ($key === 'thankyou' ? 'thank-you/' : '')) ?>" target="_blank" rel="noopener">View page</a></header>
    <?php if (!empty($section['intro'])): ?><p class="intro"><?= e($section['intro']) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="editor" data-editor>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="section" value="<?= e($key) ?>">
      <?= render_fields($section['fields'], (array) ($c[$key] ?? [])) ?>
      <?php if ($key === 'seo'): ?>
      <div class="serp" aria-label="Preview of the Google search result">
        <p class="serp__label">How it can appear on Google</p>
        <p class="serp__url" data-serp-url><?= e(rtrim(site_url($c), '/')) ?></p>
        <p class="serp__title" data-serp-title></p>
        <p class="serp__desc" data-serp-desc></p>
      </div>
      <?php endif; ?>
      <div class="savebar"><button class="btn" type="submit">Save changes</button><span class="savebar__state" data-dirty hidden>You have unsaved changes</span></div>
    </form>
    <?php if ($key === 'notifications'): ?>
    <form method="post" class="panel panel--inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test_email">
      <div><h2>Check that emails arrive</h2><p class="muted">Sends a short test message to every saved address. Save your changes first.</p></div>
      <button class="btn btn--quiet" type="submit">Send test email</button>
    </form>
    <?php endif; ?>
    <details class="reset">
      <summary>Restore the original content of this section</summary>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset">
        <input type="hidden" name="section" value="<?= e($key) ?>">
        <p class="muted">This replaces everything in "<?= e($section['label']) ?>" with the content the website was delivered with. Other sections are not affected.</p>
        <button class="btn btn--danger" type="submit">Restore original content</button>
      </form>
    </details>
    <?php
} elseif ($page === 'leads') {
    $title = 'Leads';
    $statuses = lead_statuses();
    $fs = clean_line($_GET['status'] ?? '', 20);
    $fs = isset($statuses[$fs]) ? $fs : '';
    $q = clean_line($_GET['q'] ?? '', 100);
    $all = leads_all();
    $rows = array_values(array_filter($all, fn($l) => ($fs === '' || ($l['status'] ?? 'new') === $fs) && lead_matches($l, $q)));
    $per = 50;
    $pg = max(1, (int) ($_GET['pg'] ?? 1));
    $pages = max(1, (int) ceil(count($rows) / $per));
    $pg = min($pg, $pages);
    $slice = array_slice($rows, ($pg - 1) * $per, $per);
    $back = http_build_query(array_filter(['status' => $fs, 'q' => $q, 'pg' => $pg > 1 ? $pg : null]));
    ?>
    <header class="head"><h1>Leads</h1><?php if ($all): ?><a class="btn btn--quiet" href="?p=export">Download all as CSV</a><?php endif; ?></header>
    <form method="get" class="filters">
      <input type="hidden" name="p" value="leads">
      <div class="f"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, email, phone, campaign"></div>
      <div class="f"><label for="status">Status</label><select id="status" name="status"><option value="">All</option><?php foreach ($statuses as $k => $v): ?><option value="<?= e($k) ?>"<?= $fs === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      <button class="btn btn--quiet" type="submit">Filter</button>
    </form>
    <p class="recipients"><?php $rc = lead_recipients($c); if ($rc): ?>New leads are emailed to <strong><?= e(implode(', ', $rc)) ?></strong>. <?php else: ?>Nobody is emailed about new leads yet. <?php endif; ?><a href="?p=edit&amp;s=notifications"><?= $rc ? 'Change recipients' : 'Add recipients' ?></a></p>
    <?php if (!$all): ?>
    <div class="empty"><h2>No leads yet</h2><p>Enquiries sent from the landing page will appear here. Send a test from the <a href="<?= e(base_path()) ?>" target="_blank" rel="noopener">landing page</a> to see how it works.</p></div>
    <?php elseif (!$rows): ?>
    <div class="empty"><h2>No leads match this filter</h2><p><a href="?p=leads">Show all leads</a></p></div>
    <?php else: ?>
    <p class="muted"><?= count($rows) ?> <?= count($rows) === 1 ? 'lead' : 'leads' ?><?= $fs !== '' || $q !== '' ? ' match this filter' : '' ?></p>
    <div class="leads">
      <?php foreach ($slice as $l): $st = $l['status'] ?? 'new'; ?>
      <details class="lead lead--<?= e($st) ?>">
        <summary>
          <span class="lead__name"><?= e($l['name'] ?? '') ?></span>
          <span class="lead__meta"><?= e($l['country'] ?? '') ?><?= !empty($l['cohort']) ? ' · ' . e(fmt_date($l['cohort'], 'M Y')) . ' cohort' : '' ?></span>
          <time class="lead__time" datetime="<?= e($l['created'] ?? '') ?>" data-local><?= e(substr((string) ($l['created'] ?? ''), 0, 10)) ?></time>
          <span class="pill pill--<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span>
        </summary>
        <div class="lead__body">
          <dl>
            <div><dt>Email</dt><dd><a href="mailto:<?= e($l['email'] ?? '') ?>"><?= e($l['email'] ?? '') ?></a></dd></div>
            <div><dt>Phone</dt><dd><a href="tel:<?= e($l['phone'] ?? '') ?>"><?= e($l['phone'] ?? '') ?></a> · <a href="https://wa.me/<?= e(preg_replace('/\D+/', '', (string) ($l['phone'] ?? ''))) ?>" target="_blank" rel="noopener">WhatsApp</a></dd></div>
            <div><dt>Preferred cohort</dt><dd><?= !empty($l['cohort']) ? e(fmt_date($l['cohort'])) : 'Not sure yet' ?></dd></div>
            <div><dt>Form</dt><dd><?= ($l['form'] ?? '') === 'hero' ? 'Top of page' : 'Bottom of page' ?></dd></div>
            <?php if (!empty($l['message'])): ?><div class="wide"><dt>Message</dt><dd><?= nl2br(e($l['message']), false) ?></dd></div><?php endif; ?>
            <?php if (!empty($l['source'])): ?><div class="wide"><dt>Source</dt><dd><?php foreach ((array) $l['source'] as $k => $v): ?><span class="tag"><?= e($k) ?>: <?= e($v) ?></span> <?php endforeach; ?></dd></div><?php endif; ?>
          </dl>
          <form method="post" class="lead__form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="lead_update">
            <input type="hidden" name="id" value="<?= e($l['id'] ?? '') ?>">
            <input type="hidden" name="back" value="<?= e($back) ?>">
            <div class="f"><label for="st-<?= e($l['id']) ?>">Status</label><select id="st-<?= e($l['id']) ?>" name="status"><?php foreach ($statuses as $k => $v): ?><option value="<?= e($k) ?>"<?= $st === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
            <div class="f f--grow"><label for="nt-<?= e($l['id']) ?>">Internal notes</label><textarea id="nt-<?= e($l['id']) ?>" name="notes" rows="2"><?= e($l['notes'] ?? '') ?></textarea></div>
            <button class="btn" type="submit">Update lead</button>
          </form>
          <details class="reset">
            <summary>Delete this lead</summary>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="lead_delete">
              <input type="hidden" name="id" value="<?= e($l['id'] ?? '') ?>">
              <input type="hidden" name="back" value="<?= e($back) ?>">
              <p class="muted">This permanently removes <?= e($l['name'] ?? 'this lead') ?> and cannot be undone.</p>
              <button class="btn btn--danger" type="submit">Delete lead</button>
            </form>
          </details>
        </div>
      </details>
      <?php endforeach; ?>
    </div>
    <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Pages">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a href="?<?= e(http_build_query(array_filter(['p' => 'leads', 'status' => $fs, 'q' => $q, 'pg' => $i]))) ?>"<?= $i === $pg ? ' aria-current="page"' : '' ?>><?= $i ?></a>
      <?php endfor; ?>
    </nav>
    <?php endif; ?>
    <?php endif;
} elseif ($page === 'media') {
    $title = 'Images';
    $files = is_dir(UPLOAD_DIR) ? array_values(array_filter(scandir(UPLOAD_DIR), fn($f) => preg_match('/\.(jpg|png|webp|avif|gif)$/i', $f))) : [];
    ?>
    <header class="head"><h1>Images</h1></header>
    <p class="intro">Upload an image here, then paste its path into any image field. You can also upload directly from each image field.</p>
    <form method="post" enctype="multipart/form-data" class="filters">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="media_upload">
      <div class="f"><label for="file">Image</label><input type="file" id="file" name="file" accept="image/jpeg,image/png,image/webp,image/avif,image/gif" required><p class="help">JPG, PNG, WebP, AVIF or GIF, up to 5 MB.</p></div>
      <button class="btn" type="submit">Upload image</button>
    </form>
    <?php if (!$files): ?>
    <div class="empty"><h2>No uploaded images yet</h2><p>The images delivered with the website live in assets/img and stay available.</p></div>
    <?php else: ?>
    <ul class="media">
      <?php foreach ($files as $f): ?>
      <li>
        <img src="<?= e(base_path() . 'uploads/' . $f) ?>" alt="" loading="lazy">
        <input type="text" readonly value="uploads/<?= e($f) ?>" aria-label="Path of <?= e($f) ?>" data-select>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="media_delete"><input type="hidden" name="file" value="<?= e($f) ?>"><button class="tool tool--danger" type="submit">Delete</button></form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif;
} elseif ($page === 'account') {
    $title = 'Sign-in details';
    ?>
    <header class="head"><h1>Sign-in details</h1></header>
    <form method="post" class="editor editor--narrow">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <div class="f"><label for="username">Email or username</label><input type="text" id="username" name="username" value="<?= e($cfg['admin_user'] ?? '') ?>" required minlength="3" autocomplete="username" autocapitalize="none" spellcheck="false"></div>
      <div class="f"><label for="password">New password</label><input type="password" id="password" name="password" minlength="10" autocomplete="new-password"><p class="help">Leave empty to keep your current password. At least 10 characters.</p></div>
      <div class="f"><label for="password2">Repeat the new password</label><input type="password" id="password2" name="password2" minlength="10" autocomplete="new-password"></div>
      <div class="f"><label for="current">Current password</label><input type="password" id="current" name="current" required autocomplete="current-password"><p class="help">Needed to confirm any change on this screen.</p></div>
      <div class="savebar"><button class="btn" type="submit">Save sign-in details</button></div>
    </form>
    <?php
} else {
    $title = 'Overview';
    $page = 'dashboard';
    $all = leads_all();
    $new = count(array_filter($all, fn($l) => ($l['status'] ?? 'new') === 'new'));
    $week = count(array_filter($all, fn($l) => strtotime((string) ($l['created'] ?? '')) > time() - 7 * 86400));
    $next = upcoming_cohorts($c)[0] ?? null;
    $ids = array_filter([$c['tracking']['gtm_id'], $c['tracking']['ga4_id'], $c['tracking']['meta_pixel_id'], $c['tracking']['google_ads_id']]);
    $todo = [
        ['Lead notification emails', (bool) lead_recipients($c), 'Leads are saved here, but nobody is emailed about them yet. Add the addresses that should receive them.', 'notifications'],
        ['Site URL', trim((string) $c['seo']['site_url']) !== '', 'Set the final public address so canonical tags, the sitemap and sharing use it.', 'seo'],
        ['Tracking', (bool) $ids, 'No analytics or ad pixel is connected yet.', 'tracking'],
        ['Search engine indexing', !empty($c['seo']['indexable']), 'Indexing is turned off. Turn it on when the page is live on its final address.', 'seo'],
    ];
    ?>
    <header class="head"><h1>Overview</h1><a class="btn btn--quiet" href="<?= e(base_path()) ?>" target="_blank" rel="noopener">View landing page</a></header>
    <ul class="stats">
      <li><strong><?= count($all) ?></strong><span>Leads in total</span></li>
      <li><strong><?= $new ?></strong><span>Waiting for a first reply</span></li>
      <li><strong><?= $week ?></strong><span>Received in the last 7 days</span></li>
      <li><strong><?= $next ? e(fmt_date($next['start'], 'M j, Y')) : '—' ?></strong><span>Next cohort start</span></li>
    </ul>
    <section class="panel">
      <h2>Before you launch</h2>
      <ul class="todo">
        <?php foreach ($todo as [$label, $done, $why, $s]): ?>
        <li class="<?= $done ? 'is-done' : '' ?>"><span class="todo__state"><?= $done ? 'Done' : 'To do' ?></span><div><strong><?= e($label) ?></strong><?php if (!$done): ?><p><?= e($why) ?></p><?php endif; ?></div><a href="?p=edit&amp;s=<?= e($s) ?>"><?= $done ? 'Review' : 'Set up' ?></a></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <section class="panel">
      <h2>Latest leads</h2>
      <?php if (!$all): ?>
      <p class="muted">No leads yet. Enquiries sent from the landing page will appear here.</p>
      <?php else: ?>
      <ul class="latest">
        <?php foreach (array_slice($all, 0, 5) as $l): $st = $l['status'] ?? 'new'; ?>
        <li><strong><?= e($l['name'] ?? '') ?></strong><span><?= e($l['country'] ?? '') ?></span><time datetime="<?= e($l['created'] ?? '') ?>" data-local><?= e(substr((string) ($l['created'] ?? ''), 0, 10)) ?></time><span class="pill pill--<?= e($st) ?>"><?= e(lead_statuses()[$st] ?? $st) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <p><a href="?p=leads">See all leads</a></p>
      <?php endif; ?>
    </section>
    <?php
}
$body = ob_get_clean();
$current = $page === 'edit' && is_string($_GET['s'] ?? null) ? 'edit:' . $_GET['s'] : $page;
$newCount = $authed ? count(array_filter(leads_all(), fn($l) => ($l['status'] ?? 'new') === 'new')) : 0;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e($c['seo']['site_name']) ?> admin</title>
<link rel="icon" href="<?= e(url_for((string) $c['brand']['favicon'])) ?>">
<link rel="stylesheet" href="<?= e(asset('admin/admin.css')) ?>">
</head>
<body class="<?= $authed ? 'is-app' : 'is-auth' ?>">
<?php if ($authed): ?>
<aside class="side">
  <div class="side__top">
    <a class="side__brand" href="<?= e($self) ?>"><?= e($c['seo']['site_name']) ?></a>
    <button class="side__toggle" type="button" aria-expanded="false" aria-controls="side-nav">Menu</button>
  </div>
  <nav id="side-nav" class="side__nav" aria-label="Admin">
    <ul>
      <li><a href="<?= e($self) ?>"<?= $current === 'dashboard' ? ' aria-current="page"' : '' ?>>Overview</a></li>
      <li><a href="?p=leads"<?= $current === 'leads' ? ' aria-current="page"' : '' ?>>Leads<?php if ($newCount): ?><span class="count"><?= $newCount ?></span><?php endif; ?></a></li>
    </ul>
    <?php foreach ($schema as $group => $sections): ?>
    <h2><?= e($group) ?></h2>
    <ul>
      <?php foreach ($sections as $k => $s): ?>
      <li><a href="?p=edit&amp;s=<?= e($k) ?>"<?= $current === 'edit:' . $k ? ' aria-current="page"' : '' ?>><?= e($s['label']) ?></a></li>
      <?php endforeach; ?>
      <?php if ($group === 'Settings'): ?>
      <li><a href="?p=media"<?= $current === 'media' ? ' aria-current="page"' : '' ?>>Images</a></li>
      <li><a href="?p=account"<?= $current === 'account' ? ' aria-current="page"' : '' ?>>Sign-in details</a></li>
      <?php endif; ?>
    </ul>
    <?php endforeach; ?>
    <form method="post" class="side__out"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button type="submit">Sign out</button></form>
  </nav>
</aside>
<main class="main">
  <?php if ($flash): ?><p class="notice notice--<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></p><?php endif; ?>
  <?= $body ?>
</main>
<?php else: ?>
<main><?= $body ?></main>
<?php endif; ?>
<script src="<?= e(asset('admin/admin.js')) ?>" defer></script>
</body>
</html>
