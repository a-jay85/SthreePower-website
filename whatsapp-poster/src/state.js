// Local JSON idempotency store — the actual dedupe source of truth (cheap, no network).
// Sheet write-back is best-effort/for-humans; its failure must not risk a double-send.
const fs = require('fs');
const path = require('path');
const config = require('./config');
const logger = require('./logger');

function load() {
  try {
    const raw = fs.readFileSync(config.state.filePath, 'utf8');
    return JSON.parse(raw);
  } catch (err) {
    if (err.code !== 'ENOENT') logger.warn(`Could not read state file, starting fresh: ${err.message}`);
    return {};
  }
}

function save(data) {
  const filePath = config.state.filePath;
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  const tmpPath = `${filePath}.tmp`;
  fs.writeFileSync(tmpPath, JSON.stringify(data, null, 2));
  fs.renameSync(tmpPath, filePath); // atomic on the same filesystem — avoids corruption on a killed process
}

function hasPostedToday(dateKey) {
  const data = load();
  return Boolean(data[dateKey]?.posted);
}

function markPosted(dateKey, meta) {
  const data = load();
  data[dateKey] = { posted: true, postedAt: new Date().toISOString(), ...meta };
  save(data);
}

module.exports = { load, save, hasPostedToday, markPosted };
