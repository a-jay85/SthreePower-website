// Single source of truth for env vars. Every other module reads config from here,
// never process.env directly — mirrors config.php's "one place reads env" pattern.
require('dotenv').config();

function required(name, fallback) {
  const v = process.env[name] ?? fallback;
  if (v === undefined || v === '') {
    throw new Error(`Missing required env var: ${name} (copy .env.example to .env and fill it in)`);
  }
  return v;
}

function optional(name, fallback) {
  const v = process.env[name];
  return v === undefined || v === '' ? fallback : v;
}

const config = {
  whatsapp: {
    sessionDir: optional('WWEBJS_SESSION_DIR', '.wwebjs_auth'),
    targetChatId: optional('WHATSAPP_TARGET_CHAT_ID', ''), // validated lazily — only needed for real sends, not list-chats
  },
  sheets: {
    sheetId: required('SHEET_ID'),
    gid: Number(required('SHEET_GID')),
    serviceAccountKeyPath: optional('GOOGLE_SERVICE_ACCOUNT_KEY_PATH', './secrets/service-account.json'),
    statusPostedValue: optional('SHEET_STATUS_POSTED_VALUE', 'Posted'),
    columnMap: {
      date: optional('SHEET_COL_DATE', 'Date'),
      message: optional('SHEET_COL_MESSAGE', 'Message'),
      status: optional('SHEET_COL_STATUS', 'Status'),
      mediaUrl: optional('SHEET_COL_MEDIA_URL', 'Media URL'),
    },
  },
  schedule: {
    postTime: optional('POST_TIME', '09:00'),
    timezone: optional('TIMEZONE', 'America/New_York'),
  },
  state: {
    filePath: optional('STATE_FILE_PATH', './data/posted-state.json'),
  },
  logging: {
    dir: optional('LOG_DIR', './logs'),
    level: optional('LOG_LEVEL', 'info'),
  },
};

module.exports = config;
