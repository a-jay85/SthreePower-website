const winston = require('winston');
require('winston-daily-rotate-file');
const config = require('./config');

const fileTransport = new winston.transports.DailyRotateFile({
  dirname: config.logging.dir,
  filename: 'poster-%DATE%.log',
  datePattern: 'YYYY-MM-DD',
  maxSize: '20m',
  maxFiles: '14d',
});

const logger = winston.createLogger({
  level: config.logging.level,
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.printf(({ timestamp, level, message, ...meta }) => {
      const extra = Object.keys(meta).length ? ` ${JSON.stringify(meta)}` : '';
      return `${timestamp} [${level.toUpperCase()}] ${message}${extra}`;
    }),
  ),
  transports: [
    new winston.transports.Console({ format: winston.format.combine(winston.format.colorize(), winston.format.simple()) }),
    fileTransport,
  ],
});

module.exports = logger;
