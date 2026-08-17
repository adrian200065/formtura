import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import FormBuilder from '../FormBuilder';

// Heavy children irrelevant to the settings-dialog wiring under test here.
jest.mock('../FieldLibrary', () => function MockFieldLibrary() {
  return <div data-testid="field-library" />;
});
jest.mock('../FormPreview', () => function MockFormPreview() {
  return <div data-testid="form-preview" />;
});
jest.mock('../FormCanvas', () => function MockFormCanvas({ formSettings }) {
  return <div data-testid="form-canvas">{formSettings.title || 'Untitled form'}</div>;
});

/**
 * Before this wiring existed, formSettings.title/description/submitButtonText/
 * successMessage were set once from useState's initial value and then only
 * ever read (FormCanvas.jsx, FormBuilder.jsx header) - there was no control
 * anywhere in the builder that changed them.
 */
describe('FormBuilder form settings', () => {
  it('has no form title to edit until the settings dialog is opened', () => {
    render(<FormBuilder />);

    expect(screen.getAllByText('Untitled form')[0]).toBeInTheDocument();
    expect(screen.queryByLabelText(/form title/i)).not.toBeInTheDocument();
  });

  it('opens the settings dialog from the header button', async () => {
    const user = userEvent.setup();
    render(<FormBuilder />);

    await user.click(screen.getByRole('button', { name: /form settings/i }));

    expect(screen.getByLabelText(/form title/i)).toBeInTheDocument();
  });

  it('saving a new title updates the builder header and canvas', async () => {
    const user = userEvent.setup();
    render(<FormBuilder />);

    await user.click(screen.getByRole('button', { name: /form settings/i }));
    await user.clear(screen.getByLabelText(/form title/i));
    await user.type(screen.getByLabelText(/form title/i), 'Support Request');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(screen.getAllByText('Support Request').length).toBeGreaterThan(0);
    });
  });
});
