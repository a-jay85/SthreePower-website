// One-time helper: log in via QR (shares the same session dir as index.js, so this
// IS the first login), then dump every joined chat's name + id so you can find the
// target group's id for WHATSAPP_TARGET_CHAT_ID in .env.
const fs = require('fs');
const path = require('path');
const logger = require('../src/logger');
const whatsapp = require('../src/whatsapp');

async function main() {
  logger.info('Logging in (scan the QR code)...');
  await whatsapp.initialize();

  const client = whatsapp.getClient();
  const chats = await client.getChats();

  const summary = chats.map((c) => ({ name: c.name, id: c.id._serialized, isGroup: c.isGroup }));

  console.log('\n--- Joined chats ---');
  for (const c of summary) {
    console.log(`${c.isGroup ? '[group]' : '[chat] '} ${c.id.padEnd(30)} ${c.name}`);
  }
  console.log('---------------------\n');
  console.log('Copy the id of your target group into WHATSAPP_TARGET_CHAT_ID in .env\n');

  const dumpPath = path.join(__dirname, '..', 'data', 'chats.json');
  fs.mkdirSync(path.dirname(dumpPath), { recursive: true });
  fs.writeFileSync(dumpPath, JSON.stringify(summary, null, 2));
  logger.info(`Also wrote this list to ${dumpPath}`);

  process.exit(0);
}

main().catch((err) => {
  logger.error(`list-chats failed: ${err.stack || err.message}`);
  process.exit(1);
});
