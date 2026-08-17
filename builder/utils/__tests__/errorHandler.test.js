import { handleError, handleSuccess } from '../errorHandler';

/**
 * isDevelopment() falls through to `process.env.NODE_ENV` whenever
 * window.formturaBuilder.debug isn't set to true. Vite never defines a
 * `process` global for browser code (that's a webpack-ism), so in a real
 * production build this throws a ReferenceError on every single
 * handleError()/handleSuccess() call - the exact case ESLint's `no-undef`
 * flags once it actually runs over builder/, since this file was never
 * covered by a working lint config before.
 */
describe('errorHandler without a process global (matching a real browser build)', () => {
  const originalProcess = global.process;

  beforeEach(() => {
    delete window.formturaBuilder;
  });

  afterEach(() => {
    global.process = originalProcess;
  });

  it('handleError does not throw when process is undefined', () => {
    delete global.process;

    expect(() => handleError('boom', { showToast: false })).not.toThrow();
  });

  it('handleSuccess does not throw when process is undefined', () => {
    delete global.process;

    expect(() => handleSuccess('done', { showToast: false })).not.toThrow();
  });
});
