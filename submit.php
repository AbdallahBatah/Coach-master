<?php
/** Lead form handler: validate, store, notify, then redirect to the thank-you page. */
declare(strict_types=1);
define('APP_DEPTH', 0);
require __DIR__ . '/inc/bootstrap.php';

$c = load_content();
start_session();
$home = base_path();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . $home, true, 303);
    exit;
}

$clean = function (string $key, int $max): string {
    $v = $_POST[$key] ?? '';
    $v = is_string($v) ? $v : '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr(trim($v), 0, $max);
};

$variant = $clean('form', 20) === 'hero' ? 'hero' : 'contact';
$anchor = $variant === 'hero' ? '#register-now' : '#register';
$countries = require __DIR__ . '/inc/countries.php';

$in = [
    'name' => preg_replace('/\s+/u', ' ', $clean('name', 100)),
    'email' => strtolower($clean('email', 150)),
    'country' => strtoupper($clean('country', 2)),
    'phone' => $clean('phone', 30),
    'cohort' => $clean('cohort', 10),
    'message' => $clean('message', 2000),
];

$fail = function (array $errors) use ($variant, $in, $home, $anchor) {
    $_SESSION['flash'] = ['form' => $variant, 'errors' => $errors, 'old' => $in];
    header('Location: ' . $home . $anchor, true, 303);
    exit;
};

// --- spam checks: honeypot, signed token age, rate limit. Bots get a normal-looking success.
$age = form_token_age($clean('token', 120));
$isBot = $clean('company_website', 200) !== '' || ($age !== null && $age < 2);
if ($isBot) {
    $_SESSION['lead'] = ['name' => '', 'fresh' => false];
    header('Location: ' . $home . 'thank-you/', true, 303);
    exit;
}
if ($age === null || $age > 86400) {
    $fail(['_form' => 'This form expired while the page was open. Please send it again.']);
}
if (!rate_limit('lead', 5, 600)) {
    $fail(['_form' => 'Too many requests from this connection. Please wait a few minutes, or contact us by phone or email.']);
}

// --- validation
$errors = [];
if (mb_strlen($in['name']) < 2) {
    $errors['name'] = 'Enter your full name.';
}
if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address, such as name@example.com.';
}
if (!isset($countries[$in['country']])) {
    $errors['country'] = 'Select your country.';
}
$digits = preg_replace('/\D+/', '', $in['phone']);
if (strlen($digits) < 6 || strlen($digits) > 15 || !preg_match('/^[\d\s()+.\-]+$/', $in['phone'])) {
    $errors['phone'] = 'Enter a phone number we can reach you on.';
}
if ($in['cohort'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['cohort'])) {
    $in['cohort'] = '';
}
if ($errors) {
    $fail($errors);
}

// --- normalise phone to international format
$dial = $countries[$in['country']][1];
if (strncmp($in['phone'], '+', 1) === 0) {
    $phone = '+' . $digits;
} elseif (strncmp($digits, '00', 2) === 0) {
    $phone = '+' . substr($digits, 2);
} elseif ($dial !== '' && strncmp($digits, $dial, strlen($dial)) === 0 && strlen($digits) > strlen($dial) + 6) {
    $phone = '+' . $digits;
} else {
    $phone = ($dial !== '' ? '+' . $dial : '') . ltrim($digits, '0');
}

$lead = [
    'id' => strtoupper(bin2hex(random_bytes(4))),
    'created' => gmdate('c'),
    'status' => 'new',
    'form' => $variant,
    'name' => $in['name'],
    'email' => $in['email'],
    'phone' => $phone,
    'country' => $countries[$in['country']][0],
    'cohort' => $in['cohort'],
    'message' => $in['message'],
    'source' => $_SESSION['attribution'] ?? [],
    'notes' => '',
];
lead_add($lead);

// --- notifications (failures here never block the visitor)
$n = $c['notifications'];
$lines = [
    'New enquiry from the landing page',
    '',
    'Name:     ' . $lead['name'],
    'Email:    ' . $lead['email'],
    'Phone:    ' . $lead['phone'],
    'Country:  ' . $lead['country'],
    'Cohort:   ' . ($lead['cohort'] !== '' ? fmt_date($lead['cohort']) : 'Not sure yet'),
    'Message:  ' . ($lead['message'] !== '' ? $lead['message'] : '-'),
    '',
    'Form:     ' . $lead['form'],
];
foreach ($lead['source'] as $k => $v) {
    $lines[] = str_pad($k . ':', 10) . $v;
}
$lines[] = '';
$lines[] = 'Manage leads: ' . site_url($c) . 'admin/?p=leads';
foreach (lead_recipients($c) as $to) {
    send_mail($to, 'New enquiry: ' . $lead['name'] . ' (' . $lead['country'] . ')', implode("\n", $lines), $c, $lead['email']);
}
if (!empty($n['autoreply_enabled'])) {
    $first = explode(' ', $lead['name'])[0];
    $body = str_replace(['{name}', '{first_name}'], [$lead['name'], $first], (string) $n['autoreply_body']);
    send_mail($lead['email'], (string) $n['autoreply_subject'], $body, $c, (string) $c['contact']['email']);
}
$hook = trim((string) $n['webhook_url']);
if (preg_match('#^https://#i', $hook) && function_exists('curl_init')) {
    $ch = curl_init($hook);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($lead, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

$_SESSION['lead'] = [
    'name' => explode(' ', $lead['name'])[0],
    'id' => $lead['id'],
    'form' => $variant,
    'cohort' => $lead['cohort'],
    'fresh' => true,
];
header('Location: ' . $home . 'thank-you/', true, 303);
exit;
