const logger = require('./src/logger');
const whatsapp = require('./src/whatsapp');
const poster = require('./src/poster');
const scheduler = require('./src/scheduler');

const args = process.argv.slice(2);
const dryRun = args.includes('--dry-run');
const once = args.includes('--once');

process.on('unhandledRejection', (err) => {
  logger.error(`Unhandled rejection: ${err?.stack || err}`);
});
process.on('uncaughtException', (err) => {
  logger.error(`Uncaught exception: ${err?.stack || err}`);
  process.exit(1);
});

async function main() {
  if (dryRun) {
    // Dry run never touches WhatsApp — Sheet-only, safe to run without a logged-in session.
    await poster.run({ dryRun: true });
    process.exit(0);
  }

  logger.info('Initializing WhatsApp client (scan the QR code on first run)...');
  await whatsapp.initialize();

  if (once) {
    await poster.run();
    process.exit(0);
  }

  scheduler.start();
  logger.info('whatsapp-poster daemon running. Press Ctrl+C to stop.');
}

main().catch((err) => {
  logger.error(`Fatal startup error: ${err.stack || err.message}`);
  process.exit(1);
});
