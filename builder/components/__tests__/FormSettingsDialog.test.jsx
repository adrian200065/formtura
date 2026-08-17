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
