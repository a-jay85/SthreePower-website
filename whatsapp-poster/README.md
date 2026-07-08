# whatsapp-poster

Sends one message a day into a specific WhatsApp group/conversation, drafted ahead of
time in a Google Sheet. Standalone Node daemon — not part of the PHP/static site.

## Read this first: how it works and the tradeoff

Meta's official WhatsApp Cloud API **cannot send into group chats** (only 1:1 messages
to opted-in numbers via pre-approved templates). Since the target here is a group/
community, this uses [`whatsapp-web.js`](https://wwebjs.dev/), which automates a real
WhatsApp Web session (via a headless Chromium browser) logged in once via QR code.

This is **not an official API** — it's against WhatsApp's Terms of Service, and the
account used carries a real risk of being rate-limited or banned.

- **Use a dedicated/secondary phone number for this bot**, not your primary personal
  WhatsApp, so a ban doesn't affect your own account.
- Keep volume to one message/day (what this is built for) — don't repurpose this to
  send more without reconsidering the risk.
- `whatsapp-web.js` relies on reverse-engineered WhatsApp Web internals and can break
  when WhatsApp changes its client. The version is pinned in `package.json`; expect to
  bump it if sends start failing.

## Setup

1. **Google service account**
   - Google Cloud Console → IAM & Admin → Service Accounts → create one → generate a
     JSON key → save it to `secrets/service-account.json`.
   - Enable the **Google Sheets API** on that project.
   - Open the target Sheet → Share → add the service account's `client_email`
     (from the JSON key) as **Editor** (write access is needed for the Status write-back).

2. **Install dependencies**
   ```
   cd whatsapp-poster
   npm install
   ```

3. **Configure**
   ```
   cp .env.example .env
   ```
   `SHEET_ID` and `SHEET_GID` are already filled in for the sheet you gave me. Leave
   `WHATSAPP_TARGET_CHAT_ID` blank for now — the next step finds it.

4. **First login + find the target chat ID**
   ```
   npm run list-chats
   ```
   Scan the printed QR code with the WhatsApp account you're dedicating to this bot.
   Once logged in, it prints every joined chat with its id (e.g. `1203630XXXXXXXXXX@g.us`
   for groups). Copy the target group's id into `WHATSAPP_TARGET_CHAT_ID` in `.env`.
   The session is saved to `.wwebjs_auth/`, so you won't need to re-scan on later runs.

5. **Dry run** (validates Sheet access + column parsing, sends nothing)
   ```
   npm run dry-run
   ```
   If your sheet's headers don't match the defaults (`Date`, `Message`, `Status`,
   `Media URL`), override them via `SHEET_COL_*` in `.env`.

6. **One real send**
   ```
   npm run once
   ```
   Confirms an actual WhatsApp send into the right chat, and the Status write-back
   on the sheet.

7. **Run as a persistent daemon**
   ```
   npm i -g pm2
   pm2 start index.js --name whatsapp-poster
   pm2 save
   pm2 startup   # follow the printed instructions to survive reboots
   pm2 logs whatsapp-poster
   ```
   This must run on an always-on host you control — your own machine left on, or a
   small VPS. **Not** the existing Plesk/Windows-IIS PHP host — it isn't set up for a
   long-running Node/Chromium process. On a fresh Linux VPS you may need Puppeteer's
   system dependencies (`libnss3`, `libatk1.0-0`, etc.) — see the
   [whatsapp-web.js docs](https://wwebjs.dev/guide/creating-your-bot/handling-attachments.html)
   / Puppeteer's troubleshooting guide if the browser fails to launch.

## How row selection works

Each day, the bot looks for a row where the `Date` column equals today (in the
`TIMEZONE` from `.env`) and the `Status` column isn't already `Posted`. If none is
found, it logs "no post scheduled" and does nothing — a gap day in the sheet is normal,
not an error.

If your sheet is actually a plain ordered queue with no per-row date, the row-selection
logic in `src/sheets.js`'s `findTodaysPost()` is the one place to change.

## Idempotency

A local file (`data/posted-state.json`) is checked *before* touching the Sheet or
WhatsApp — it's the actual source of truth for "did we send today," not the Sheet's
Status column (which is best-effort, for-humans record-keeping). This means a crashed
process, an accidental double-run, or a failed Sheet write-back can't cause a duplicate
send.

## Flags

- `--dry-run` — logs what would be sent, touches neither WhatsApp nor the Sheet.
- `--once` — sends today's post (if any) immediately, then exits. Useful for manual
  testing without waiting for the schedule.
- (no flags) — starts the daemon: keeps the WhatsApp session alive and fires once a
  day at `POST_TIME`/`TIMEZONE`. If restarted after today's post time with nothing
  sent yet, it catches up immediately instead of waiting until tomorrow.
