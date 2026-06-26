<?php
/**
 * Configuration loader.
 *
 * Resolves each setting from, in order:
 *   1. config.local.php — an array returned from a file. Preferred location is OUTSIDE the
 *      document root (one level up); if your host won't allow that, place it NEXT TO this file
 *      in the webroot — it's a .php that PHP executes, so its contents are never served as
 *      source. Either location is checked below. This is the reliable option on Plesk/Windows-IIS,
 *      where PHP Settings values are php.ini directives and getenv() may not see them.
 *   2. getenv() — real OS environment variables, if the host exposes them.
 *
 * If config.local.php sits in the webroot, also deny it in web.config (requestFiltering →
 * hiddenSegments) as belt-and-suspenders against a server misconfig serving .php as plaintext.
 *
 * This file holds NO secret values, so it is safe to commit. Secret values live only in
 * config.local.php (git-ignored, outside webroot) or the host environment. See .env.example.
 */

declare(strict_types=1);

$GLOBALS['__sp_local'] = [];
// Prefer the copy above the document root; fall back to one alongside this file in the webroot.
foreach ([__DIR__ . '/../config.local.php', __DIR__ . '/config.local.php'] as $localPath) {
    if (is_readable($localPath)) {
        $loaded = require $localPath;
        if (is_array($loaded)) {
            $GLOBALS['__sp_local'] = $loaded;
        }
        break;
    }
}

function sp_env(string $key): string {
    $val = $GLOBALS['__sp_local'][$key] ?? getenv($key);
    if ($val === false || $val === null || $val === '') {
        // Don't leak which var is missing to the browser; log it for the operator.
        error_log('Missing required configuration value: ' . $key);
        http_response_code(500);
        exit('Server configuration error.');
    }
    return (string) $val;
}

define('GOOGLE_CLIENT_ID',     sp_env('GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET', sp_env('GOOGLE_CLIENT_SECRET'));
define('GOOGLE_REDIRECT_URI',  sp_env('GOOGLE_REDIRECT_URI'));
define('SHEET_WEBHOOK_URL',    sp_env('SHEET_WEBHOOK_URL'));
define('SHEET_SHARED_SECRET',  sp_env('SHEET_SHARED_SECRET'));

// Where to send the user after the flow (success or handled error). Relative (no leading
// slash) so it resolves against the directory the flow runs from — works whether the site
// is served from the web root or a subfolder like /new-twilight-site/.
define('JOIN_PAGE', 'index.html');

// Bump this string whenever the consent wording on the page changes — it is stored
// alongside each opt-in so you can prove exactly what the user agreed to (GDPR Art. 7).
define('CONSENT_TEXT_VERSION', '2026-06-24-v1');

// How long an email-confirmation link stays valid (double-opt-in). 3 days.
define('CONFIRM_TOKEN_TTL', 3 * 24 * 60 * 60);
