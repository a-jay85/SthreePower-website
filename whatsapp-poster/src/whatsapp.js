const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const config = require('./config');
const logger = require('./logger');

let client = null;
let ready = false;

function getClient() {
  if (client) return client;

  client = new Client({
    authStrategy: new LocalAuth({ dataPath: config.whatsapp.sessionDir }),
    puppeteer: { args: ['--no-sandbox', '--disable-setuid-sandbox'] },
  });

  client.on('qr', (qr) => {
    logger.info('Scan this QR code with the WhatsApp account you want this bot to use:');
    qrcode.generate(qr, { small: true });
  });

  client.on('ready', () => {
    ready = true;
    logger.info('WhatsApp client ready');
  });

  client.on('auth_failure', (msg) => {
    ready = false;
    logger.error(`WhatsApp auth failure: ${msg}`);
  });

  client.on('disconnected', (reason) => {
    ready = false;
    logger.error(`WhatsApp client disconnected: ${reason}`);
  });

  return client;
}

function initialize() {
  const c = getClient();
  return new Promise((resolve, reject) => {
    c.once('ready', resolve);
    c.once('auth_failure', reject);
    c.initialize().catch(reject);
  });
}

function isReady() {
  return ready;
}

async function sendText(chatId, message) {
  if (!ready) throw new Error('WhatsApp client is not ready — cannot send yet');
  if (!chatId || !/@(g\.us|c\.us)$/.test(chatId)) {
    throw new Error(`WHATSAPP_TARGET_CHAT_ID looks wrong (expected it to end in @g.us or @c.us): "${chatId}"`);
  }
  return getClient().sendMessage(chatId, message);
}

// Extension point for when the Sheet's Media URL column is populated — not yet wired into poster.js.
async function sendMedia(chatId, mediaUrl, caption) {
  if (!ready) throw new Error('WhatsApp client is not ready — cannot send yet');
  const media = await MessageMedia.fromUrl(mediaUrl, { unsafeMime: true });
  return getClient().sendMessage(chatId, media, { caption });
}

module.exports = { getClient, initialize, isReady, sendText, sendMedia };
