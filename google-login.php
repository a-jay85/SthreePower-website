<?php
/**
 * Step 1 of the Google OIDC flow.
 *
 * Requires the marketing-consent checkbox (passed as ?consent=yes by the join form),
 * records proof-of-consent in the session, then redirects to Google's authorize endpoint.
 * Only the public client_id is needed here — the client secret is used later, in
 * google-callback.php.
 */

declare(strict_types=1);

// SameSite=Lax (not Strict): the callback is a top-level GET redirect back from Google,
// and Strict would drop the session cookie -> phantom "state mismatch" failures.
session_set_cookie_params(['samesite' => 'Lax', 'httponly' => true, 'secure' => true]);
session_start();

require __DIR__ . '/config.php';

// Marketing consent is mandatory. The page disables the button until it's ticked, but
// re-check server-side so the endpoint can't be hit without it. The join form POSTs
// (so the email path keeps the address out of the URL); read consent from the body.
if (($_POST['consent'] ?? '') !== 'yes') {
    header('Location: ' . JOIN_PAGE . '?error=consent');
    exit;
}

// CSRF protection: random state echoed back by Google and verified in the callback.
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// Proof of consent, captured at the moment of opt-in (GDPR Art. 7(1)).
$_SESSION['consent']              = true;
$_SESSION['consent_text_version'] = CONSENT_TEXT_VERSION;
$_SESSION['consent_ts']           = gmdate('c');
$_SESSION['consent_ip']           = $_SERVER['REMOTE_ADDR'] ?? '';
$_SESSION['consent_ua']           = $_SERVER['HTTP_USER_AGENT'] ?? '';

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'response_type' => 'code',
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'scope'         => 'openid email profile',
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);

header('Location: ' . $authUrl);
exit;
