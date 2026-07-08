const config = require('./config');
const logger = require('./logger');
const state = require('./state');
const sheets = require('./sheets');
const whatsapp = require('./whatsapp');
const { todayISODate } = require('./dateMatch');

async function run({ dryRun = false } = {}) {
  const dateKey = todayISODate(config.schedule.timezone);

  if (state.hasPostedToday(dateKey)) {
    logger.info(`Already posted today (${dateKey}) per local state — skipping`);
    return;
  }

  const post = await sheets.findTodaysPost();
  if (!post) {
    logger.info(`No scheduled post found for ${dateKey}`);
    return;
  }

  if (!post.message || !post.message.trim()) {
    logger.warn(`Row ${post.rowNumber} matched today's date but has an empty Message cell — skipping`);
    return;
  }

  if (dryRun) {
    logger.info(`[dry-run] Would send to ${config.whatsapp.targetChatId || '(unset)'}: "${post.message}"${post.mediaUrl ? ` [media: ${post.mediaUrl}]` : ''}`);
    return;
  }

  await whatsapp.sendText(config.whatsapp.targetChatId, post.message);
  logger.info(`Sent today's post (row ${post.rowNumber}) to ${config.whatsapp.targetChatId}`);

  // Mark locally BEFORE the Sheet write-back: local state is the source of truth for
  // "did we already send" so a crash between send and write-back can't cause a resend.
  state.markPosted(dateKey, { rowNumber: post.rowNumber, sheetWriteBackOk: null });

  try {
    await sheets.markRowPosted(post, new Date().toISOString());
    state.markPosted(dateKey, { rowNumber: post.rowNumber, sheetWriteBackOk: true });
  } catch (err) {
    logger.error(`Sheet write-back failed (message already sent, so this is non-fatal): ${err.message}`);
    state.markPosted(dateKey, { rowNumber: post.rowNumber, sheetWriteBackOk: false });
  }
}

module.exports = { run };
