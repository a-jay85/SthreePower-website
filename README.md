# SthreePower — Website

The homepage for **SthreePower®**, a global community where women build real skills, grow a professional network, and earn their way to financial independence.

This is the **Twilight Editorial** design direction: a dusk-dominant, editorial layout built on the locked Dusk & Ivory color system.

## Tech

Plain, static HTML/CSS/JS. No build step, no dependencies, no framework. Fonts (Newsreader, Hanken Grotesk, Space Mono) load from Google Fonts over CDN. Everything else — the dusk gradient, the horizon line, the icons — is hand-authored CSS and SVG, so there are no image assets to manage.

## Project structure

```
sthreepower-site/
├── index.html      # the entire homepage (markup + styles + script, self-contained)
├── README.md
└── .gitignore
```

## Preview locally

It's a single static file, so you can just open it:

```
open index.html          # macOS
# or double-click the file
```

For a closer-to-production preview (correct relative paths, no file:// quirks), serve it:

```
python3 -m http.server 8000
# then visit http://localhost:8000
```

## Deploy

Because it's static, almost any host works. A few options while you finalize hosting:

- **GitHub Pages** — push this repo, then in the repo's **Settings → Pages**, set the source to "Deploy from a branch", branch `main`, folder `/ (root)`. The site goes live at `https://<you>.github.io/<repo>/` within a minute or two. (`index.html` at the root is exactly what Pages expects.)
- **Netlify / Vercel / Cloudflare Pages** — drag the folder in, or connect the repo. No build command needed; the publish directory is the root.
- **Any web host / S3 / nginx** — upload `index.html` to the web root.

## Google sign-in join flow (`PostFeedbackGoogleButton.html`)

A variant of the post-feedback page replaces the WhatsApp CTA with **Sign in with Google**
(OAuth/OIDC) to capture verified name + email onto a consent-based marketing list. Unlike the
rest of the site, this flow needs **server-side PHP** (the OAuth token exchange uses a client
secret that must never reach the browser). Target host: **Plesk on Windows/IIS, PHP 8.3**.

**Files:** `PostFeedbackGoogleButton.html` (page + consent checkbox), `google-login.php`
(starts the flow), `google-callback.php` (token exchange → Google Sheet), `config.php`
(reads env vars), `privacy.html` (policy scaffold — needs counsel review), `.env.example`.

### Setup

1. **Google OAuth client** — Google Cloud Console → *APIs & Services*:
   - *OAuth consent screen*: User type **External**; app name "SthreePower"; support email;
     scopes `openid`, `email`, `profile` (all non-sensitive — no verification review needed);
     publish to Production.
   - *Credentials* → **Create OAuth client ID** → **Web application** → Authorized redirect
     URI `https://sthreepower.org/google-callback.php`. Copy the Client ID + Secret.
2. **Google Sheet capture** — create a Sheet with header row
   `email | name | google_sub | consent | consent_text_version | ip | user_agent | source | created_at`.
   Add an Apps Script (Extensions → Apps Script) and deploy as a **Web app** (Deploy → New
   deployment → Web app; *Execute as* = you, *Who has access* = Anyone):
   ```js
   const SECRET = 'CHANGE_ME'; // must match SHEET_SHARED_SECRET
   function doPost(e) {
     const d = JSON.parse(e.postData.contents);
     if (d.secret !== SECRET) return ContentService.createTextOutput('forbidden');
     SpreadsheetApp.getActiveSpreadsheet().getActiveSheet().appendRow([
       d.email, d.name, d.google_sub, d.consent, d.consent_text_version,
       d.ip, d.user_agent, d.source, d.created_at,
     ]);
     return ContentService.createTextOutput('ok');
   }
   ```
   Copy the deployment URL into `SHEET_WEBHOOK_URL`.
3. **Environment variables** — set on the host (Plesk → PHP Settings). See `.env.example`:
   `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `SHEET_WEBHOOK_URL`,
   `SHEET_SHARED_SECRET`. `config.php` holds no secrets itself — it only reads these.
4. **Sending** — a Google Sheet stores leads but **cannot send compliant email**. Connect an
   ESP (Brevo/Mailchimp/etc.) for unsubscribe, `List-Unsubscribe`, and the required physical
   address before the first send. First email should be a welcome/confirmation.

### Notes
- Consent is captured on the page, persisted in the PHP session by `google-login.php`, then
  written with the lead by `google-callback.php` (the callback can't see the original form).
- The session cookie is `SameSite=Lax` on purpose — `Strict` drops it on the redirect back
  from Google and breaks state validation.
- Finalise `privacy.html` (replace every highlighted placeholder) with legal review.

### Host gotchas (Plesk / Windows-IIS) — learned the hard way
- **Redirect URI must match the live host exactly, including subpath and www.** The site is
  served from `/new-twilight-site/` on the **www** host, so the Authorized redirect URI (Google
  Console) and `GOOGLE_REDIRECT_URI` are both
  `https://www.sthreepower.org/new-twilight-site/google-callback.php`. A non-www/www difference,
  a missing subpath, or a trailing slash all produce `Error 400: redirect_uri_mismatch`. Keep
  the whole flow on one canonical host or the session cookie won't survive the round-trip.
- **`JOIN_PAGE` is relative** (no leading slash) so post-flow redirects resolve inside the
  subfolder. A root-relative `/PostFeedbackGoogleButton.html` 404s when the site isn't at the
  web root.
- **ModSecurity WAF blocks the callback by default.** Google's `scope`/`iss` params contain
  `https://…` URLs and `.profile`, which trip OWASP CRS rules **931130** (RFI, off-domain
  reference) and **930120** (LFI, `.profile`) → HTTP **403** before PHP runs. Disable those two
  rule IDs for this domain (Plesk → site → Web Application Firewall → *Switch off security
  rules*). If you have Plesk **server admin**, prefer a path-scoped custom directive instead, so
  the rules stay active elsewhere:
  ```apache
  SecRule REQUEST_URI "@beginsWith /new-twilight-site/google-callback.php" \
    "id:1000100,phase:1,pass,nolog,\
     ctl:ruleRemoveTargetById=931130;ARGS:scope,\
     ctl:ruleRemoveTargetById=931130;ARGS:iss,\
     ctl:ruleRemoveTargetById=930120;ARGS:scope"
  ```
- IIS request filtering returns **404.x**, not 403 — so a 403 on the callback is the WAF, not
  request filtering (`allowDoubleEscaping` is a red herring here).

## Still to do before launch

- **Replace placeholder content.** All testimonials, member names, and figures are placeholder copy for layout. Swap in real quotes and numbers.
- **Wire up the links.** Nav links and the "Join free on WhatsApp" CTAs currently point to `#`. Point them at the real destinations.
- **Add a favicon** (currently none — browsers will show a default).
- **Add Open Graph / social-share tags** once you have a share image, so links preview nicely.
- **Confirm the footer details** (address and phone) are current.

## Notes

- The "Vinodha & Banumathi" services tile names the two flagship initiatives as placeholders; update copy as those programs firm up.
- No license file is included — by default this is "all rights reserved." Add one only if you intend to open-source it.
