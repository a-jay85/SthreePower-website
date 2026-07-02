<?php
/**
 * Email-address join path, step 2 of 2 (double opt-in).
 *
 * The link target from the confirmation email. Verifies the signed token, then appends the
 * now-confirmed lead to the Sheet as source=email-confirmed. Reaching this point with a
 * valid token is the proof of consent: the recipient received mail at the address AND acted
 * on it, re-affirming the opt-in captured on the form.
 *
 * Two-step on purpose: a bare GET renders a "Confirm" button that POSTs the token back; only
 * the POST writes the lead. Email-security scanners (SafeLinks, Mimecast, Gmail, …) GET every
 * link before a human sees it — if the GET wrote the row, a scanner could auto-confirm and the
 * "a human clicked" proof would be worthless. So: scanners GET (harmless page), humans POST.
 */

declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/confirm-token.php';

/** Redirect back to the page with a short error code and stop. */
function fail(string $code): void {
    header('Location: ' . JOIN_PAGE . '?error=' . urlencode($code));
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$token  = $method === 'POST' ? (string) ($_POST['t'] ?? '') : (string) ($_GET['t'] ?? '');

$claims = confirm_token_verify($token, sp_env('CONFIRM_SECRET'), CONFIRM_TOKEN_TTL);
if ($claims === null) {
    fail('token'); // missing, tampered, or expired link
}

$email = (string) ($claims['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('token');
}

// --- GET: render the confirm button (no write). Humans click it; scanners that prefetch
//     the emailed link only ever reach here, never the POST below. ---
if ($method !== 'POST') {
    header('Content-Type: text/html; charset=utf-8');
    $safeToken = htmlspecialchars($token, ENT_QUOTES);
    $safeEmail = htmlspecialchars($email, ENT_QUOTES);
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Confirm your email · SthreePower</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{min-height:100vh;display:grid;place-items:center;background:#1E1830;color:#FBF8F1;
    font-family:'Hanken Grotesk',system-ui,Arial,sans-serif;padding:24px;text-align:center}
  .card{max-width:440px}
  h1{font-size:26px;font-weight:700;margin-bottom:14px}
  p{color:#CDC3D6;font-size:15.5px;line-height:1.6;margin-bottom:28px}
  p b{color:#FBF8F1;font-weight:600}
  button{font:inherit;cursor:pointer;border:none;background:#FBF8F1;color:#1E1830;
    padding:15px 30px;border-radius:999px;font-weight:700;font-size:15px}
  button:hover{box-shadow:0 14px 40px -12px rgba(227,171,140,.5)}
</style>
</head>
<body>
  <div class="card">
    <h1>One last click</h1>
    <p>Confirm <b>{$safeEmail}</b> to finish joining SthreePower and start receiving our emails.</p>
    <form method="post" action="confirm.php">
      <input type="hidden" name="t" value="{$safeToken}">
      <button type="submit">Confirm my email</button>
    </form>
  </div>
</body>
</html>
HTML;
    exit;
}

// --- POST: a human clicked. Append the confirmed lead (same 10 keys as the Google path). ---
// No `action` key, so the Apps Script falls through to appendRow(). `ip`/`cv` come from the
// signed token (the consent moment); `created_at` is the confirmation moment.
$row = [
    'secret'               => SHEET_SHARED_SECRET,
    'email'                => $email,
    'name'                 => '',
    'google_sub'           => '',
    'consent'              => 'true',
    'consent_text_version' => (string) ($claims['cv'] ?? ''),
    'ip'                   => (string) ($claims['ip'] ?? ''),
    'user_agent'           => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'source'               => 'email-confirmed',
    'created_at'           => gmdate('c'),
];

$ch = curl_init(SHEET_WEBHOOK_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($row),
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => true,
]);
$resp   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $status >= 400) {
    fail('store');
}

header('Location: ' . JOIN_PAGE . '?confirmed=1');
exit;
