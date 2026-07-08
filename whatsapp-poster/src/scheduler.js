const cron = require('node-cron');
const { DateTime } = require('luxon');
const config = require('./config');
const logger = require('./logger');
const poster = require('./poster');
const state = require('./state');
const { todayISODate } = require('./dateMatch');

function start() {
  const [hour, minute] = config.schedule.postTime.split(':').map(Number);
  const cronExpr = `${minute} ${hour} * * *`;

  cron.schedule(
    cronExpr,
    () => {
      poster.run().catch((err) => logger.error(`Scheduled run failed: ${err.stack || err.message}`));
    },
    { timezone: config.schedule.timezone },
  );

  logger.info(`Scheduled daily post at ${config.schedule.postTime} (${config.schedule.timezone})`);

  // Startup catch-up: if we restarted after today's post time and haven't sent yet,
  // don't silently wait until tomorrow. Safe because poster.run() re-checks state itself.
  const now = DateTime.now().setZone(config.schedule.timezone);
  const postMoment = now.set({ hour, minute, second: 0, millisecond: 0 });
  const dateKey = todayISODate(config.schedule.timezone);

  if (now > postMoment && !state.hasPostedToday(dateKey)) {
    logger.info('Past today\'s post time and nothing sent yet — running catch-up now');
    poster.run().catch((err) => logger.error(`Catch-up run failed: ${err.stack || err.message}`));
  }
}

module.exports = { start };
