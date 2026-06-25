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
(starts the flow), `google-callback.php` (token exchange → Google Sheet), `email-signup.php`
+ `confirm.php` + `confirm-token.php` (email double-opt-in alternative — see below),
`config.php` (reads env vars), `privacy.html` (policy scaffold — needs counsel review),
`.env.example`.

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
   const SECRET = 'CHANGE_ME';      // must match SHEET_SHARED_SECRET
   const FROM_NAME = 'SthreePower'; // sender name on confirmation emails

   function doPost(e) {
     const d = JSON.parse(e.postData.contents);
     if (d.secret !== SECRET) return ContentService.createTextOutput('forbidden');

     // Email double-opt-in (email-signup.php): send the confirmation link, write nothing yet.
     if (d.action === 'send_confirmation') {
       sendConfirmation(d.email, d.confirm_url);
       return ContentService.createTextOutput('ok');
     }

     // Default: append a verified/confirmed lead (Google flow + confirm.php).
     SpreadsheetApp.getActiveSpreadsheet().getActiveSheet().appendRow([
       d.email, d.name, d.google_sub, d.consent, d.consent_text_version,
       d.ip, d.user_agent, d.source, d.created_at,
     ]);
     return ContentService.createTextOutput('ok');
   }

   // Twilight Editorial brand: dusk canvas, ivory text, peach glow accent, serif headline
   // with an italic-glow flourish. Email-safe — table layout, inline styles, and web-font
   // fallbacks (Gmail/Outlook won't load Newsreader/Hanken/Space Mono, so Georgia / Arial /
   // Courier stand in). Solid colors only (no gradients) so Outlook renders it cleanly.
   function sendConfirmation(email, url) {
     const subject = "You're almost in — confirm your email";
     const htmlBody = `
   <div style="display:none;max-height:0;overflow:hidden;opacity:0">Confirm your email to claim your place in the SthreePower community.</div>
   <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#15101F;margin:0;padding:0">
     <tr><td align="center" style="padding:32px 16px">
       <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#1E1830;border-radius:14px;overflow:hidden;border:1px solid #3E3357">
         <tr><td style="height:3px;background:#E3AB8C;line-height:3px;font-size:0">&nbsp;</td></tr>
         <tr><td align="center" style="padding:42px 40px 0">
           <div style="font-family:Georgia,'Times New Roman',serif;font-size:24px;font-weight:700;color:#FBF8F1">SthreePower<sup style="font-size:11px;font-weight:400;color:#B6A6C2">&reg;</sup></div>
         </td></tr>
         <tr><td align="center" style="padding:36px 40px 0">
           <div style="font-family:Georgia,'Times New Roman',serif;font-style:italic;font-size:17px;letter-spacing:.01em;color:#E3AB8C">One last step</div>
         </td></tr>
         <tr><td align="center" style="padding:14px 40px 0">
           <div style="font-family:Georgia,'Times New Roman',serif;font-size:40px;line-height:1.04;color:#FBF8F1;font-weight:600">You&rsquo;re almost <span style="font-style:italic;color:#E3AB8C">in.</span></div>
         </td></tr>
         <tr><td align="center" style="padding:24px 54px 0">
           <div style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.65;color:#D8CEDE">Welcome to SthreePower &mdash; a community of women building the skills employers are hiring for, growing a real network, and earning their way to financial independence.</div>
         </td></tr>
         <tr><td align="center" style="padding:18px 54px 0">
           <div style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.65;color:#D8CEDE">Confirm your email and your place in the community is set.</div>
         </td></tr>
         <tr><td align="center" style="padding:36px 40px 4px">
           <table role="presentation" cellpadding="0" cellspacing="0"><tr>
             <td align="center" bgcolor="#FBF8F1" style="border-radius:999px">
               <a href="${url}" style="display:inline-block;padding:16px 40px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#1E1830;text-decoration:none;border-radius:999px">Confirm my email</a>
             </td>
           </tr></table>
         </td></tr>
         <tr><td align="center" style="padding:26px 44px 0">
           <div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;color:#8E7CA3">Button not working? Paste this link into your browser:</div>
           <div style="font-family:'Courier New',monospace;font-size:12px;line-height:1.6;color:#B6A6C2;word-break:break-all;margin-top:8px">${url}</div>
         </td></tr>
         <tr><td style="padding:36px 40px 0"><div style="height:1px;background:#3E3357;line-height:1px;font-size:0">&nbsp;</div></td></tr>
         <tr><td align="center" style="padding:26px 44px 42px">
           <div style="font-family:Arial,Helvetica,sans-serif;font-size:12.5px;line-height:1.6;color:#8E7CA3">You&rsquo;re receiving this because this address was entered at sthreepower.org. Not you? Ignore this email &mdash; nothing happens until you confirm.</div>
           <div style="font-family:Georgia,'Times New Roman',serif;font-style:italic;font-size:14.5px;color:#B6A6C2;margin-top:20px">Empowering women to grow as people and professionals.</div>
           <div style="font-family:Arial,Helvetica,sans-serif;font-size:11.5px;line-height:1.6;color:#5A4C74;margin-top:14px">SthreePower&reg; &middot; 105 Oak Park Dr, Suite C, Irmo, SC 29063</div>
         </td></tr>
       </table>
     </td></tr>
   </table>`;
     MailApp.sendEmail({ to: email, name: FROM_NAME, subject: subject, htmlBody: htmlBody });
   }
   ```
   Copy the deployment URL into `SHEET_WEBHOOK_URL`. (`MailApp` sends from the Google account
   running the script — free tier ~100 recipients/day. **Re-deploy the web app after editing
   the script**, and on the first run authorize the new mail scope when prompted.)
3. **Environment variables** — set on the host (Plesk → PHP Settings). See `.env.example`:
   `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `SHEET_WEBHOOK_URL`,
   `SHEET_SHARED_SECRET`, and `CONFIRM_SECRET` (a long random string for signing the email
   confirmation links). `config.php` holds no secrets itself — it only reads these.
4. **Sending** — a Google Sheet stores leads but **cannot send compliant marketing email**.
   The confirmation email above is a one-off transactional message; for actual campaigns,
   connect an ESP (Brevo/Mailchimp/etc.) for unsubscribe, `List-Unsubscribe`, and the
   required physical address before the first send.

### Email double-opt-in flow (no Google account needed)

So people without a Google account can still join, the form also takes a plain email address.
Because a typed address is unproven, it goes through **double opt-in**, which is what makes it
a defensible record of consent (the recipient controls the inbox *and* re-affirms by clicking):

1. `email-signup.php` — validates consent + email + honeypot, mints an **HMAC-signed token**
   (`confirm-token.php`) carrying the email, consent-text version, IP, and issue time, then
   asks the webhook (`action=send_confirmation`) to email a link to `confirm.php?t=…`. Nothing
   is written to the Sheet yet. The page shows "check your inbox" (`?check=1`).
2. `confirm.php` — verifies the token's signature + 3-day expiry (`CONFIRM_TOKEN_TTL`). On a
   plain GET (the emailed link) it renders a **"Confirm my email" button that POSTs** the token
   back; only that POST appends the lead as **`source=email-confirmed`** and shows confirmed
   (`?confirmed=1`). This is deliberate: email-security scanners (SafeLinks, Mimecast, Gmail)
   GET every link before a human sees it, so a GET-write would let a scanner auto-confirm and
   void the "a human clicked" proof. Scanners GET (harmless page); humans POST.

**Two operational notes:**
- **WAF:** the confirm link is `confirm.php?t=<base64url>.<base64url>` — url-safe, no `://` or
  `.profile`, so it shouldn't trip the ModSecurity rules documented above for the callback. But
  this host has surprised us before; click a real confirmation link in prod and confirm it isn't
  403'd before shipping.
- **Unauthenticated trigger:** anyone can POST `email-signup.php` with any recipient. The
  honeypot stops dumb bots, but a targeted script could burn the MailApp ~100/day quota (and
  relay nuisance mail). Add light per-IP rate-limiting if that becomes a problem.

Sheet `source` values distinguish lead quality: `google-signin` (Google-verified) and
`email-confirmed` (double-opt-in). The token is **stateless** — no database — but therefore
not single-use; a double-click yields a duplicate row, so dedupe by email in the
Sheet/Apps Script if it matters.

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
