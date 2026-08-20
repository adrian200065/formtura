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

/**
 * Notifications.php has always fully supported an admin notification, but
 * nothing created one for a form until the builder itself started
 * defaulting one in. That default must apply only to genuinely new forms -
 * not bleed into an existing form loaded from the server that has its own
 * (possibly absent) notification settings.
 */
describe('FormBuilder notification defaults', () => {
  afterEach(() => {
    delete global.fetch;
  });

  it('a brand new form defaults to one enabled admin notification', async () => {
    const user = userEvent.setup();
    render(<FormBuilder />);

    await user.click(screen.getByRole('button', { name: /form settings/i }));

    expect(screen.getByLabelText(/send email notification/i)).toBeChecked();
    expect(screen.getByLabelText(/^send to$/i)).toHaveValue('{admin_email}');
  });

  it('loading an existing form with no saved notification does not inherit the new-form default', async () => {
    global.fetch = jest.fn().mockResolvedValue({
      json: () => Promise.resolve({
        success: true,
        data: {
          title: 'Existing Form',
          form_data: JSON.stringify({ fields: [], settings: { title: 'Existing Form' } }),
        },
      }),
    });

    const user = userEvent.setup();
    render(<FormBuilder formId={5} />);

    await waitFor(() => expect(global.fetch).toHaveBeenCalled());
    await user.click(screen.getByRole('button', { name: /form settings/i }));

    expect(screen.getByLabelText(/send email notification/i)).not.toBeChecked();
  });

  it('loading an existing form applies its own saved notification, not the new-form default', async () => {
    global.fetch = jest.fn().mockResolvedValue({
      json: () => Promise.resolve({
        success: true,
        data: {
          title: 'Existing Form',
          form_data: JSON.stringify({
            fields: [],
            settings: {
              title: 'Existing Form',
              notifications: [{ enabled: true, to: 'owner@example.test' }],
            },
          }),
        },
      }),
    });

    const user = userEvent.setup();
    render(<FormBuilder formId={5} />);

    await waitFor(() => expect(global.fetch).toHaveBeenCalled());
    await user.click(screen.getByRole('button', { name: /form settings/i }));

    expect(screen.getByLabelText(/^send to$/i)).toHaveValue('owner@example.test');
  });
});
