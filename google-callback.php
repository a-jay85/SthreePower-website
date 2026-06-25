<?php
/**
 * Step 2 of the Google OIDC flow (the Authorized redirect URI).
 *
 * Validates state, exchanges the auth code for tokens (server-side, with the client
 * secret), reads the verified email/name from the id_token, then appends a row to the
 * Google Sheet via its Apps Script webhook. Finally redirects the user back to the page.
 */

declare(strict_types=1);

session_set_cookie_params(['samesite' => 'Lax', 'httponly' => true, 'secure' => true]);
session_start();

require __DIR__ . '/config.php';

/** Redirect back to the page with a short error code and stop. */
function fail(string $code): void {
    header('Location: ' . JOIN_PAGE . '?error=' . urlencode($code));
    exit;
}

// User denied consent on Google, or Google returned an error.
if (isset($_GET['error'])) {
    fail('google_' . preg_replace('/[^a-z_]/', '', (string) $_GET['error']));
}

// CSRF: the state must match what we stored in google-login.php.
$state = $_GET['state'] ?? '';
if ($state === '' || !hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
    fail('state');
}
unset($_SESSION['oauth_state']);

$code = $_GET['code'] ?? '';
if ($code === '') {
    fail('nocode');
}

// --- Exchange the authorization code for tokens (back channel, with the secret) ---
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]),
    CURLOPT_TIMEOUT        => 15,
]);
$tokenResp   = curl_exec($ch);
$tokenStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($tokenResp === false)   fail('token_curl');
if ($tokenStatus !== 200)   fail('token_http');

$token   = json_decode((string) $tokenResp, true);
$idToken = $token['id_token'] ?? '';
if ($idToken === '') {
    fail('noidtoken');
}

// --- Decode the id_token claims ---
// The token arrived directly from Google over the TLS back channel, so we trust it
// without verifying the JWKS signature. (Optional hardening: verify with firebase/php-jwt
// against https://www.googleapis.com/oauth2/v3/certs.)
$parts = explode('.', $idToken);
if (count($parts) !== 3) {
    fail('badjwt');
}
$payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
$claims  = $payload === false ? null : json_decode($payload, true);
if (!is_array($claims)) {
    fail('badclaims');
}

// Gate: only accept Google-verified emails onto the marketing list.
$email    = $claims['email'] ?? '';
$verified = $claims['email_verified'] ?? false;
if ($verified === 'true') {
    $verified = true; // some payloads encode the boolean as a string
}
if ($email === '' || $verified !== true) {
    fail('unverified');
}

// --- Append the lead + proof-of-consent to the Google Sheet webhook ---
$row = [
    'secret'               => SHEET_SHARED_SECRET,
    'email'                => $email,
    'name'                 => $claims['name'] ?? '',
    'google_sub'           => $claims['sub'] ?? '',
    'consent'              => !empty($_SESSION['consent']) ? 'true' : 'false',
    'consent_text_version' => $_SESSION['consent_text_version'] ?? '',
    'ip'                   => $_SESSION['consent_ip'] ?? '',
    'user_agent'           => $_SESSION['consent_ua'] ?? '',
    'source'               => 'google-signin',
    'created_at'           => $_SESSION['consent_ts'] ?? gmdate('c'),
];

$ch = curl_init(SHEET_WEBHOOK_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($row),
    CURLOPT_TIMEOUT        => 15,
    // Apps Script web apps answer with a 302 to script.googleusercontent.com — follow it.
    CURLOPT_FOLLOWLOCATION => true,
]);
$sheetResp   = curl_exec($ch);
$sheetStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($sheetResp === false || $sheetStatus >= 400) {
    fail('store');
}

// Done — clear the session and send the user back to the success state.
session_unset();
session_destroy();

header('Location: ' . JOIN_PAGE . '?joined=1');
exit;
