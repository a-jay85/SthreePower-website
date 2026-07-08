// Defensive date handling: Sheets can hand back a Date cell as a locale string
// ("7/8/2026"), an ISO string, or a raw serial number (days since 1899-12-30) depending
// on how it was read. Normalize everything to YYYY-MM-DD in the configured timezone
// before comparing, and never throw on a bad cell — just skip that row.
const { DateTime } = require('luxon');

const LOCALE_FORMATS = ['M/d/yyyy', 'MM/dd/yyyy', 'd/M/yyyy', 'yyyy-MM-dd'];

function sheetsSerialToISODate(serial) {
  // Sheets/Excel epoch is 1899-12-30 (accounts for the historical leap-year bug).
  const epoch = DateTime.fromISO('1899-12-30');
  return epoch.plus({ days: Number(serial) }).toISODate();
}

function normalizeCellToISODate(rawValue) {
  if (rawValue === undefined || rawValue === null || rawValue === '') return null;

  if (typeof rawValue === 'number' || /^\d+(\.\d+)?$/.test(String(rawValue).trim())) {
    const iso = sheetsSerialToISODate(rawValue);
    if (iso) return iso;
  }

  const asString = String(rawValue).trim();

  let dt = DateTime.fromISO(asString);
  if (dt.isValid) return dt.toISODate();

  for (const fmt of LOCALE_FORMATS) {
    dt = DateTime.fromFormat(asString, fmt);
    if (dt.isValid) return dt.toISODate();
  }

  return null;
}

function todayISODate(timezone) {
  return DateTime.now().setZone(timezone).toISODate();
}

module.exports = { normalizeCellToISODate, todayISODate };
