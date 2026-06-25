<?php
/**
 * Email-address join path, step 1 of 2 (double opt-in).
 *
 * Validates the consent box + email, then asks the Apps Script webhook to send a
 * confirmation email containing a signed link back to confirm.php. NOTHING is written to
 * the Sheet here — a typed address is unproven until the recipient clicks that link, which
 * is what makes this a defensible record of consent (they control the inbox AND re-affirm).
 *
 * The lead is only stored once confirm.php verifies the token. See confirm-token.php.
 */

declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/confirm-token.php';

/** Redirect back to the page with a short error code and stop. */
function fail(string $code): void {
    header('Location: ' . JOIN_PAGE . '?error=' . urlencode($code));
    exit;
}

// Honeypot: a hidden field real users never fill. Bots that auto-complete every input
// trip it. Silently send them to the same "check your inbox" state — never reveal why.
if (($_POST['website'] ?? '') !== '') {
    header('Location: ' . JOIN_PAGE . '?check=1');
    exit;
}

// Marketing consent is mandatory. The page disables the button until it's ticked, but
// re-check server-side so the endpoint can't be hit without it.
if (($_POST['consent'] ?? '') !== 'yes') {
    fail('consent');
}

$email = trim((string) ($_POST['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('email');
}

// --- Build the signed confirmation link ---
// Claims travel inside the token (HMAC-signed), so confirm.php can write the lead with the
// original consent context without us storing anything. `ip` is the address at the consent
// moment; `cv` ties the row to the exact wording the user agreed to.
$token = confirm_token_make([
    'email' => $email,
    'cv'    => CONSENT_TEXT_VERSION,
    'ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
    'iat'   => time(),
], sp_env('CONFIRM_SECRET'));

// Absolute URL derived from this request, so it works whether the site is at the web root
// or a subfolder like /new-twilight-site/ (respecting a proxy's forwarded scheme/host).
$scheme = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
    || (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? 'off') !== 'off'))
    ? 'https' : 'http';
$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
$dir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$confirmUrl = $scheme . '://' . $host . $dir . '/confirm.php?t=' . $token;

// --- Ask the Apps Script webhook to send the confirmation email (action=send_confirmation,
//     so it sends mail and does NOT append a row). ---
$payload = [
    'secret'      => SHEET_SHARED_SECRET,
    'action'      => 'send_confirmation',
    'email'       => $email,
    'confirm_url' => $confirmUrl,
];

$ch = curl_init(SHEET_WEBHOOK_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 15,
    // Apps Script web apps answer with a 302 to script.googleusercontent.com — follow it.
    CURLOPT_FOLLOWLOCATION => true,
]);
$resp   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $status >= 400) {
    fail('send');
}

// "Check your inbox" state — the lead isn't live until they click the link.
header('Location: ' . JOIN_PAGE . '?check=1');
exit;
