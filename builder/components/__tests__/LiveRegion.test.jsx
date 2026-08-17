import { render, screen, act } from '@testing-library/react';
import LiveRegion, { announce } from '../LiveRegion';

/**
 * window.formturaAnnounce accepted a `level` argument but never used it -
 * the region's aria-live was fixed to the politeness the component mounted
 * with, so a caller asking for an 'assertive' announcement (interrupting
 * the screen reader) silently got 'polite' (queued) instead, every time.
 */
describe('LiveRegion', () => {
  afterEach(() => {
    delete window.formturaAnnounce;
  });

  it('announces with aria-live="polite" by default', () => {
    render(<LiveRegion />);

    act(() => {
      announce('Field added');
    });

    expect(screen.getByRole('status')).toHaveAttribute('aria-live', 'polite');
    expect(screen.getByRole('status')).toHaveTextContent('Field added');
  });

  it('switches to aria-live="assertive" for an assertive announcement', () => {
    render(<LiveRegion />);

    act(() => {
      announce('Something urgent failed', 'assertive');
    });

    expect(screen.getByRole('status')).toHaveAttribute('aria-live', 'assertive');
  });

  it('returns to the region\'s default politeness for the next, unmarked announcement', () => {
    render(<LiveRegion />);

    act(() => {
      announce('Something urgent failed', 'assertive');
    });
    act(() => {
      announce('Field added');
    });

    expect(screen.getByRole('status')).toHaveAttribute('aria-live', 'polite');
  });
});
