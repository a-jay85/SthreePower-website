/**
 * SthreePower — Google Apps Script web app (lead capture + double-opt-in mail).
 *
 * This is the DEPLOYABLE SOURCE OF TRUTH for the script behind SHEET_WEBHOOK_URL.
 * The PHP layer (email-signup.php / confirm.php / google-callback.php) never sends mail or
 * writes to the Sheet directly — it POSTs here. Two responsibilities:
 *   1. action=send_confirmation  → email the double-opt-in link (writes nothing yet)
 *   2. (default)                 → append a verified/confirmed lead row
 *
 * DEPLOY: paste into the Sheet's Apps Script editor (Extensions → Apps Script), then
 *   Deploy → Manage deployments → Edit → New version. The deployment runs the editor's copy,
 *   NOT this repo — so re-deploy a NEW VERSION after every edit or the change won't go live.
 *   On first run after adding MailApp, authorize the mail scope when prompted.
 *   Web app settings: Execute as = you, Who has access = Anyone.
 */

const SECRET = 'CHANGE_ME';      // must match SHEET_SHARED_SECRET (Plesk env)
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
