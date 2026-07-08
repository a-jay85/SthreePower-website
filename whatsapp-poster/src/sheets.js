const { google } = require('googleapis');
const config = require('./config');
const logger = require('./logger');
const { normalizeCellToISODate, todayISODate } = require('./dateMatch');

let cachedAuthClient = null;
let cachedSheetTitle = null;

async function getSheetsClient() {
  if (!cachedAuthClient) {
    const auth = new google.auth.GoogleAuth({
      keyFile: config.sheets.serviceAccountKeyPath,
      scopes: ['https://www.googleapis.com/auth/spreadsheets'],
    });
    cachedAuthClient = await auth.getClient();
  }
  return google.sheets({ version: 'v4', auth: cachedAuthClient });
}

// values.get/update address ranges by sheet *title*, not gid — resolve once, cache for the process lifetime.
async function resolveSheetTitle(sheets) {
  if (cachedSheetTitle) return cachedSheetTitle;

  const res = await sheets.spreadsheets.get({
    spreadsheetId: config.sheets.sheetId,
    fields: 'sheets.properties(sheetId,title)',
  });

  const match = (res.data.sheets || []).find((s) => s.properties.sheetId === config.sheets.gid);
  if (!match) {
    throw new Error(`No tab with gid=${config.sheets.gid} found in spreadsheet ${config.sheets.sheetId}`);
  }

  cachedSheetTitle = match.properties.title;
  logger.info(`Resolved SHEET_GID=${config.sheets.gid} to tab title "${cachedSheetTitle}"`);
  return cachedSheetTitle;
}

function buildColumnIndex(headerRow) {
  const normalized = headerRow.map((h) => String(h || '').trim().toLowerCase());
  const find = (headerName) => normalized.indexOf(String(headerName).trim().toLowerCase());

  const index = {
    date: find(config.sheets.columnMap.date),
    message: find(config.sheets.columnMap.message),
    status: find(config.sheets.columnMap.status),
    mediaUrl: find(config.sheets.columnMap.mediaUrl),
  };

  if (index.date === -1) throw new Error(`Required column "${config.sheets.columnMap.date}" not found in sheet header row`);
  if (index.message === -1) throw new Error(`Required column "${config.sheets.columnMap.message}" not found in sheet header row`);
  if (index.status === -1) logger.warn(`Status column "${config.sheets.columnMap.status}" not found — write-back will be skipped`);
  if (index.mediaUrl === -1) logger.info(`Media URL column "${config.sheets.columnMap.mediaUrl}" not found — text-only posts assumed`);

  return index;
}

function columnIndexToLetter(index) {
  let n = index + 1;
  let letters = '';
  while (n > 0) {
    const rem = (n - 1) % 26;
    letters = String.fromCharCode(65 + rem) + letters;
    n = Math.floor((n - 1) / 26);
  }
  return letters;
}

// Returns { rowNumber, message, mediaUrl, statusColIndex, title } for today's unposted row, or null.
async function findTodaysPost() {
  const sheets = await getSheetsClient();
  const title = await resolveSheetTitle(sheets);

  const res = await sheets.spreadsheets.values.get({
    spreadsheetId: config.sheets.sheetId,
    range: title,
  });

  const rows = res.data.values || [];
  if (rows.length < 2) {
    logger.warn('Sheet has no data rows below the header');
    return null;
  }

  const columnIndex = buildColumnIndex(rows[0]);
  const today = todayISODate(config.schedule.timezone);

  for (let i = 1; i < rows.length; i += 1) {
    const row = rows[i];
    const dateISO = normalizeCellToISODate(row[columnIndex.date]);
    if (!dateISO) continue;
    if (dateISO !== today) continue;

    const statusValue = columnIndex.status !== -1 ? String(row[columnIndex.status] || '').trim() : '';
    if (statusValue.toLowerCase().startsWith(config.sheets.statusPostedValue.toLowerCase())) {
      logger.info(`Row ${i + 1} matches today but is already marked "${statusValue}" — skipping`);
      continue;
    }

    return {
      rowNumber: i + 1, // 1-based, for A1 ranges
      message: row[columnIndex.message] || '',
      mediaUrl: columnIndex.mediaUrl !== -1 ? row[columnIndex.mediaUrl] || '' : '',
      statusColIndex: columnIndex.status,
      title,
    };
  }

  return null;
}

async function markRowPosted({ title, rowNumber, statusColIndex }, isoTimestamp) {
  if (statusColIndex === -1 || statusColIndex === undefined) {
    logger.warn('No Status column configured — skipping Sheet write-back');
    return;
  }

  const sheets = await getSheetsClient();
  const colLetter = columnIndexToLetter(statusColIndex);
  const range = `${title}!${colLetter}${rowNumber}`;
  const value = `${config.sheets.statusPostedValue} ${isoTimestamp}`;

  await sheets.spreadsheets.values.update({
    spreadsheetId: config.sheets.sheetId,
    range,
    valueInputOption: 'USER_ENTERED',
    requestBody: { values: [[value]] },
  });

  logger.info(`Wrote back "${value}" to ${range}`);
}

module.exports = { findTodaysPost, markRowPosted };
