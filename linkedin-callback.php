<?php
/**
 * Step 2 of the LinkedIn OAuth 2.0 flow (the Authorized Redirect URL).
 *
 * Validates state, exchanges the auth code for an access token, then calls
 * LinkedIn's OpenID Connect /v2/userinfo endpoint to get a verified email and
 * name. Appends a lead row to the Google Sheet via the Apps Script webhook.
 *
 * Requires the "Sign In with LinkedIn using OpenID Connect" product to be added
 * to the LinkedIn app (gives you the openid, profile, and email scopes).
 */

declare(strict_types=1);

session_set_cookie_params(['samesite' => 'Lax', 'httponly' => true, 'secure' => true]);
session_start();

require __DIR__ . '/config.php';

function fail(string $code): void {
    header('Location: ' . JOIN_PAGE . '?error=' . urlencode($code));
    exit;
}

if (isset($_GET['error'])) {
    fail('linkedin_' . preg_replace('/[^a-z_]/', '', (string) $_GET['error']));
}

$state = $_GET['state'] ?? '';
if ($state === '' || !hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
    fail('state');
}
if (($_SESSION['oauth_provider'] ?? '') !== 'linkedin') {
    fail('provider_mismatch');
}
unset($_SESSION['oauth_state'], $_SESSION['oauth_provider']);

$code = $_GET['code'] ?? '';
if ($code === '') {
    fail('nocode');
}

// --- Exchange authorization code for access token ---
$ch = curl_init('https://www.linkedin.com/oauth/v2/accessToken');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => LINKEDIN_REDIRECT_URI,
        'client_id'     => LINKEDIN_CLIENT_ID,
        'client_secret' => LINKEDIN_CLIENT_SECRET,
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT        => 15,
]);
$tokenResp   = curl_exec($ch);
$tokenStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($tokenResp === false) fail('token_curl');
if ($tokenStatus !== 200)  fail('token_http');

$token       = json_decode((string) $tokenResp, true);
$accessToken = $token['access_token'] ?? '';
if ($accessToken === '') {
    fail('noaccesstoken');
}

// --- Fetch verified email + name from the OpenID Connect userinfo endpoint ---
$ch = curl_init('https://api.linkedin.com/v2/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
    CURLOPT_TIMEOUT        => 15,
]);
$userResp   = curl_exec($ch);
$userStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($userResp === false) fail('userinfo_curl');
if ($userStatus !== 200)  fail('userinfo_http');

$user = json_decode((string) $userResp, true);

// LinkedIn's userinfo endpoint only includes email_verified when the "email" scope was granted.
$email    = $user['email'] ?? '';
$verified = $user['email_verified'] ?? false;
$name     = trim(($user['given_name'] ?? '') . ' ' . ($user['family_name'] ?? ''));

if ($email === '' || $verified !== true) {
    fail('unverified');
}

// --- Append lead + consent proof to Google Sheet webhook ---
$row = [
    'secret'               => SHEET_SHARED_SECRET,
    'email'                => $email,
    'name'                 => $name,
    'linkedin_sub'         => $user['sub'] ?? '',
    'consent'              => !empty($_SESSION['consent']) ? 'true' : 'false',
    'consent_text_version' => $_SESSION['consent_text_version'] ?? '',
    'ip'                   => $_SESSION['consent_ip'] ?? '',
    'user_agent'           => $_SESSION['consent_ua'] ?? '',
    'source'               => 'linkedin-signin',
    'created_at'           => $_SESSION['consent_ts'] ?? gmdate('c'),
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
$sheetResp   = curl_exec($ch);
$sheetStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($sheetResp === false || $sheetStatus >= 400) {
    fail('store');
}

session_unset();
session_destroy();

header('Location: ' . JOIN_PAGE . '?joined=1');
exit;
