/**
 * Translation helpers for the builder.
 *
 * Reads from window.wp.i18n rather than bundling @wordpress/i18n: the
 * `wp-i18n` script this bundle depends on (see Admin::enqueue_assets())
 * already provides that global, and wp_set_script_translations() calls
 * setLocaleData() on that same global instance. Bundling a separate copy
 * of @wordpress/i18n would create a second, disconnected instance that
 * wp_set_script_translations()'s injected script never reaches.
 *
 * @package Formtura
 */

const i18n = () => (typeof window !== 'undefined' && window.wp && window.wp.i18n) || null;

/**
 * @param {string} text
 * @param {string} domain
 * @returns {string}
 */
export const __ = (text, domain = 'formtura') => {
  const wpI18n = i18n();
  return wpI18n ? wpI18n.__(text, domain) : text;
};

/**
 * @param {string} text
 * @param {string} context
 * @param {string} domain
 * @returns {string}
 */
export const _x = (text, context, domain = 'formtura') => {
  const wpI18n = i18n();
  return wpI18n ? wpI18n._x(text, context, domain) : text;
};

/**
 * @param {string} single
 * @param {string} plural
 * @param {number} number
 * @param {string} domain
 * @returns {string}
 */
export const _n = (single, plural, number, domain = 'formtura') => {
  const wpI18n = i18n();
  return wpI18n ? wpI18n._n(single, plural, number, domain) : (number === 1 ? single : plural);
};

/**
 * @param {string} format
 * @param {...*} args
 * @returns {string}
 */
export const sprintf = (format, ...args) => {
  const wpI18n = i18n();
  if (wpI18n && typeof wpI18n.sprintf === 'function') {
    return wpI18n.sprintf(format, ...args);
  }

  // wp-i18n isn't guaranteed to be loaded in every context (e.g. before it
  // finishes registering); fall back to a minimal %s/%d substitution so
  // callers still get readable output instead of a crash.
  let i = 0;
  return format.replace(/%[sd]/g, () => {
    const value = args[i];
    i += 1;
    return value === undefined ? '' : String(value);
  });
};
