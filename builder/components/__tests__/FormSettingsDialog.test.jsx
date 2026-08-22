import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import FormSettingsDialog from '../FormSettingsDialog';

/**
 * Before this dialog existed there was no way anywhere in the builder to set
 * a form's title, description, submit button text or success message - see
 * FormBuilder.jsx (title only ever rendered, never edited) and FormCanvas.jsx
 * (same). This is the interface that was missing.
 */
describe('FormSettingsDialog', () => {
  const settings = {
    title: 'Contact Us',
    description: 'Reach out any time.',
    submitButtonText: 'Send',
    successMessage: 'Thanks!',
    redirect_url: 'https://example.test/thanks',
  };

  const defaultProps = {
    isOpen: true,
    formSettings: settings,
    onSave: jest.fn(),
    onClose: jest.fn(),
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders nothing when closed', () => {
    const { container } = render(<FormSettingsDialog {...defaultProps} isOpen={false} />);
    expect(container).toBeEmptyDOMElement();
  });

  it('prefills every field with the current form settings', () => {
    render(<FormSettingsDialog {...defaultProps} />);

    expect(screen.getByLabelText(/form title/i)).toHaveValue('Contact Us');
    expect(screen.getByLabelText(/description/i)).toHaveValue('Reach out any time.');
    expect(screen.getByLabelText(/submit button text/i)).toHaveValue('Send');
    expect(screen.getByLabelText(/success message/i)).toHaveValue('Thanks!');
    expect(screen.getByLabelText(/redirect url/i)).toHaveValue('https://example.test/thanks');
  });

  it('saves the edited values, not the originals', async () => {
    const user = userEvent.setup();
    render(<FormSettingsDialog {...defaultProps} />);

    await user.clear(screen.getByLabelText(/form title/i));
    await user.type(screen.getByLabelText(/form title/i), 'Support Request');

    await user.click(screen.getByRole('button', { name: /save/i }));

    expect(defaultProps.onSave).toHaveBeenCalledWith(
      expect.objectContaining({ title: 'Support Request', submitButtonText: 'Send' })
    );
  });

  /**
   * redirect_url is fully wired server-side (Form_Builder::sanitize_settings_data(),
   * Submission::build_success_response(), assets/js/frontend.js already redirects
   * on it if present) but had no admin UI to set it - AUDIT_FINDINGS.md #5.
   * Saved under the snake_case `redirect_url` key, matching the only key
   * Form_Builder.php recognises (unlike submitButtonText/successMessage, there
   * is no camelCase alias for this one).
   */
  it('saves the redirect URL under the snake_case key the backend expects', async () => {
    const user = userEvent.setup();
    render(<FormSettingsDialog {...defaultProps} />);

    // fireEvent.change, not user.type: type="url" runs the input through the
    // browser's URL value-sanitization algorithm on every keystroke, and
    // typing it one character at a time is flaky under jsdom (drops the
    // final character intermittently in CI, though never locally) - setting
    // the full value in one shot sidesteps that per-keystroke sanitization
    // entirely.
    fireEvent.change(screen.getByLabelText(/redirect url/i), {
      target: { value: 'https://example.test/updated' },
    });

    await user.click(screen.getByRole('button', { name: /save/i }));

    expect(defaultProps.onSave).toHaveBeenCalledWith(
      expect.objectContaining({ redirect_url: 'https://example.test/updated' })
    );
  });

  it('closes after saving', async () => {
    const user = userEvent.setup();
    render(<FormSettingsDialog {...defaultProps} />);

    await user.click(screen.getByRole('button', { name: /save/i }));

    expect(defaultProps.onClose).toHaveBeenCalled();
  });

  it('discards edits when cancelled', async () => {
    const user = userEvent.setup();
    render(<FormSettingsDialog {...defaultProps} />);

    await user.clear(screen.getByLabelText(/form title/i));
    await user.type(screen.getByLabelText(/form title/i), 'Should not be saved');
    await user.click(screen.getByRole('button', { name: /cancel/i }));

    expect(defaultProps.onSave).not.toHaveBeenCalled();
    expect(defaultProps.onClose).toHaveBeenCalled();
  });

  it('closes on Escape without saving', () => {
    render(<FormSettingsDialog {...defaultProps} />);

    fireEvent.keyDown(document, { key: 'Escape' });

    expect(defaultProps.onSave).not.toHaveBeenCalled();
    expect(defaultProps.onClose).toHaveBeenCalled();
  });

  it('falls back to placeholder defaults when a setting is missing', () => {
    render(<FormSettingsDialog {...defaultProps} formSettings={{}} />);

    expect(screen.getByLabelText(/form title/i)).toHaveValue('');
    expect(screen.getByLabelText(/submit button text/i)).toHaveValue('');
  });
});

/**
 * The backend has always fully supported notifications (recipient, subject,
 * message, reply-to, cc, bcc, smart tags) - see Notifications.php - but
 * nothing in the builder could ever create or edit one. No form sent email
 * unless someone hand-wrote the settings JSON.
 */
describe('FormSettingsDialog notifications', () => {
  const settingsWithNotification = {
    title: 'Contact Us',
    notifications: [
      {
        enabled: true,
        to: 'owner@example.test',
        subject: 'New submission',
        message: 'You got mail.',
        reply_to: 'reply@example.test',
        cc: 'cc@example.test',
        bcc: 'bcc@example.test',
      },
    ],
  };

  const defaultProps = {
    isOpen: true,
    onSave: jest.fn(),
    onClose: jest.fn(),
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('shows the notification as enabled and prefills every field', () => {
    render(<FormSettingsDialog {...defaultProps} formSettings={settingsWithNotification} />);

    expect(screen.getByLabelText(/send email notification/i)).toBeChecked();
    expect(screen.getByLabelText(/^send to$/i)).toHaveValue('owner@example.test');
    expect(screen.getByLabelText(/^subject$/i)).toHaveValue('New submission');
    expect(screen.getByLabelText(/^message$/i)).toHaveValue('You got mail.');
    expect(screen.getByLabelText(/reply-to/i)).toHaveValue('reply@example.test');
    expect(screen.getByLabelText(/^cc$/i)).toHaveValue('cc@example.test');
    expect(screen.getByLabelText(/^bcc$/i)).toHaveValue('bcc@example.test');
  });

  it('hides the detail fields when no notification is configured', () => {
    render(<FormSettingsDialog {...defaultProps} formSettings={{ title: 'Contact' }} />);

    expect(screen.getByLabelText(/send email notification/i)).not.toBeChecked();
    expect(screen.queryByLabelText(/^send to$/i)).not.toBeInTheDocument();
  });

  it('reveals the detail fields when the toggle is checked', async () => {
    const user = userEvent.setup();
    render(<FormSettingsDialog {...defaultProps} formSettings={{ title: 'Contact' }} />);

    await user.click(screen.getByLabelText(/send email notification/i));

    expect(screen.getByLabelText(/^send to$/i)).toBeInTheDocument();
  });

  it('saves the edited notification, keyed as a one-element array', async () => {
    const user = userEvent.setup();
    render(<FormSettingsDialog {...defaultProps} formSettings={settingsWithNotification} />);

    await user.clear(screen.getByLabelText(/^subject$/i));
    await user.type(screen.getByLabelText(/^subject$/i), 'Updated subject');
    await user.click(screen.getByRole('button', { name: /save/i }));

    expect(defaultProps.onSave).toHaveBeenCalledWith(
      expect.objectContaining({
        notifications: [expect.objectContaining({ enabled: true, subject: 'Updated subject' })],
      })
    );
  });

  it('unchecking the toggle saves it disabled rather than deleting the notification', async () => {
    const user = userEvent.setup();
    render(<FormSettingsDialog {...defaultProps} formSettings={settingsWithNotification} />);

    await user.click(screen.getByLabelText(/send email notification/i));
    await user.click(screen.getByRole('button', { name: /save/i }));

    expect(defaultProps.onSave).toHaveBeenCalledWith(
      expect.objectContaining({
        notifications: [expect.objectContaining({ enabled: false, to: 'owner@example.test' })],
      })
    );
  });
});
