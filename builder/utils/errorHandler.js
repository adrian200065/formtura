/**
 * Error handling utilities
 *
 * @package Formtura
 */

/**
 * Log levels
 */
export const LogLevel = {
  DEBUG: 'debug',
  INFO: 'info',
  WARN: 'warn',
  ERROR: 'error',
};

/**
 * Check if we're in development mode
 */
const isDevelopment = () => {
  return window.formturaBuilder?.debug === true || process.env.NODE_ENV === 'development';
};

/**
 * Log message to console (only in development)
 *
 * @param {string} message - Message to log
 * @param {string} level - Log level
 * @param {*} data - Additional data to log
 */
export const log = (message, level = LogLevel.INFO, data = null) => {
  if (!isDevelopment()) {
    return;
  }

  const prefix = '[Formtura]';
  const timestamp = new Date().toISOString();

  switch (level) {
    case LogLevel.DEBUG:
      console.debug(`${prefix} [${timestamp}] DEBUG:`, message, data);
      break;
    case LogLevel.INFO:
      console.info(`${prefix} [${timestamp}] INFO:`, message, data);
      break;
    case LogLevel.WARN:
      console.warn(`${prefix} [${timestamp}] WARN:`, message, data);
      break;
    case LogLevel.ERROR:
      console.error(`${prefix} [${timestamp}] ERROR:`, message, data);
      break;
    default:
      console.log(`${prefix} [${timestamp}]`, message, data);
  }
};

/**
 * Handle errors with user notification
 *
 * @param {Error|string} error - Error object or message
 * @param {Object} options - Options
 * @param {boolean} options.showToast - Show toast notification
 * @param {string} options.userMessage - User-friendly message
 * @param {Function} options.onError - Error callback
 */
export const handleError = (error, options = {}) => {
  const {
    showToast = true,
    userMessage = 'An error occurred. Please try again.',
    onError = null,
  } = options;

  // Log error in development
  const errorMessage = error instanceof Error ? error.message : error;
  const errorStack = error instanceof Error ? error.stack : null;

  log(errorMessage, LogLevel.ERROR, errorStack);

  // Show toast notification
  if (showToast && window.formturaToast) {
    window.formturaToast.error(userMessage);
  }

  // Call error callback
  if (onError && typeof onError === 'function') {
    onError(error);
  }

  // Return error info
  return {
    message: errorMessage,
    userMessage,
    timestamp: new Date().toISOString(),
  };
};

/**
 * Handle success with user notification
 *
 * @param {string} message - Success message
 * @param {Object} options - Options
 */
export const handleSuccess = (message, options = {}) => {
  const { showToast = true } = options;

  log(message, LogLevel.INFO);

  if (showToast && window.formturaToast) {
    window.formturaToast.success(message);
  }
};

/**
 * Async error boundary wrapper
 *
 * @param {Function} fn - Async function to wrap
 * @param {Object} options - Error handling options
 * @returns {Promise}
 */
export const asyncErrorHandler = async (fn, options = {}) => {
  try {
    return await fn();
  } catch (error) {
    handleError(error, options);
    throw error;
  }
};

export default {
  log,
  handleError,
  handleSuccess,
  asyncErrorHandler,
  LogLevel,
};
