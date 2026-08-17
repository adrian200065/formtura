import { __, _x, _n, sprintf } from '../i18n';

/**
 * These helpers read window.wp.i18n rather than bundling their own copy of
 * @wordpress/i18n, so wp_set_script_translations()'s injected setLocaleData()
 * call (which only ever touches the global instance) actually reaches
 * whatever __() the builder calls. jest.setup.js mocks window.wp.i18n
 * globally; the fallback tests below temporarily remove it to prove the
 * helpers degrade to the raw string instead of throwing when wp-i18n hasn't
 * loaded yet.
 */
describe('builder i18n helpers', () => {
  const originalWp = window.wp;

  afterEach(() => {
    window.wp = originalWp;
  });

  it('__ delegates to window.wp.i18n.__', () => {
    window.wp = { i18n: { __: jest.fn(() => 'Traducido') } };

    expect(__('Translated')).toBe('Traducido');
    expect(window.wp.i18n.__).toHaveBeenCalledWith('Translated', 'formtura');
  });

  it('__ falls back to the raw text when wp.i18n is unavailable', () => {
    delete window.wp;

    expect(__('Translated')).toBe('Translated');
  });

  it('_x delegates to window.wp.i18n._x with its context', () => {
    window.wp = { i18n: { _x: jest.fn(() => 'Publicar') } };

    expect(_x('Post', 'verb')).toBe('Publicar');
    expect(window.wp.i18n._x).toHaveBeenCalledWith('Post', 'verb', 'formtura');
  });

  it('_n falls back to a plain singular/plural pick when wp.i18n is unavailable', () => {
    delete window.wp;

    expect(_n('%d item', '%d items', 1)).toBe('%d item');
    expect(_n('%d item', '%d items', 3)).toBe('%d items');
  });

  it('sprintf delegates to window.wp.i18n.sprintf', () => {
    window.wp = { i18n: { sprintf: jest.fn(() => '3 fields') } };

    expect(sprintf('%d fields', 3)).toBe('3 fields');
    expect(window.wp.i18n.sprintf).toHaveBeenCalledWith('%d fields', 3);
  });

  it('sprintf falls back to substituting %s/%d in order when wp.i18n is unavailable', () => {
    delete window.wp;

    expect(sprintf('%d fields renamed to %s', 3, 'Contact')).toBe('3 fields renamed to Contact');
  });
});
