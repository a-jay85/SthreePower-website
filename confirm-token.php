<?php
/**
 * Stateless confirmation tokens for the email double-opt-in flow.
 *
 * The token is self-contained: a base64url JSON payload plus an HMAC-SHA256 signature, so
 * nothing has to be stored server-side between the sign-up request and the confirmation
 * click (no database — handy on Plesk/Windows). The signature stops anyone forging or
 * editing the claims; the `iat` timestamp + a TTL bound how long the link stays valid.
 *
 * Note: being stateless, a token can't be marked single-use without storage. A user who
 * clicks twice produces two identical rows — dedupe by email in the Sheet/Apps Script if
 * that matters. Acceptable for a marketing list.
 */

declare(strict_types=1);

function b64url_encode(string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function b64url_decode(string $enc): string {
    return (string) base64_decode(strtr($enc, '-_', '+/'), false);
}

/** Build a signed "<payload>.<sig>" token from the given claims. */
function confirm_token_make(array $claims, string $secret): string {
    $body = b64url_encode((string) json_encode($claims, JSON_UNESCAPED_SLASHES));
    $sig  = b64url_encode(hash_hmac('sha256', $body, $secret, true));
    return $body . '.' . $sig;
}

/**
 * Verify the signature and TTL. Returns the claims array, or null if the token is
 * malformed, tampered with, or older than $ttlSeconds.
 */
function confirm_token_verify(string $token, string $secret, int $ttlSeconds): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }
    [$body, $sig] = $parts;

    // Constant-time signature check before we trust any of the payload.
    $expected = b64url_encode(hash_hmac('sha256', $body, $secret, true));
    if (!hash_equals($expected, $sig)) {
        return null;
    }

    $claims = json_decode(b64url_decode($body), true);
    if (!is_array($claims)) {
        return null;
    }

    $iat = (int) ($claims['iat'] ?? 0);
    if ($iat <= 0 || ($iat + $ttlSeconds) < time()) {
        return null; // never issued, or expired
    }

    return $claims;
}
