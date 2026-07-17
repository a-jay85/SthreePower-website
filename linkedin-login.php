<?php
/**
 * Step 1 of the LinkedIn OAuth 2.0 flow.
 *
 * Validates marketing consent, stores proof-of-consent in the session, then
 * redirects to LinkedIn's authorize endpoint.
 *
 * LinkedIn uses standard OAuth 2.0 (not OIDC), so the id_token trick used in
 * google-login.php is unavailable. The callback exchanges the code for an access
 * token and then calls the /v2/userinfo endpoint to get the verified email.
 */

declare(strict_types=1);

session_set_cookie_params(['samesite' => 'Lax', 'httponly' => true, 'secure' => true]);
session_start();

require __DIR__ . '/config.php';

if (($_POST['consent'] ?? '') !== 'yes') {
    header('Location: ' . JOIN_PAGE . '?error=consent');
    exit;
}

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state']           = $state;
$_SESSION['oauth_provider']        = 'linkedin';
$_SESSION['consent']               = true;
$_SESSION['consent_text_version']  = CONSENT_TEXT_VERSION;
$_SESSION['consent_ts']            = gmdate('c');
$_SESSION['consent_ip']            = $_SERVER['REMOTE_ADDR'] ?? '';
$_SESSION['consent_ua']            = $_SERVER['HTTP_USER_AGENT'] ?? '';

$authUrl = 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
    'response_type' => 'code',
    'client_id'     => LINKEDIN_CLIENT_ID,
    'redirect_uri'  => LINKEDIN_REDIRECT_URI,
    // openid + profile + email requires the "Sign In with LinkedIn using OpenID Connect" product
    // to be added to the LinkedIn app. See setup guide in README / backend setup notes.
    'scope'         => 'openid profile email',
    'state'         => $state,
]);

header('Location: ' . $authUrl);
exit;
