import { useEffect, useRef, useState } from 'react';
import { __ } from '../utils/i18n';

/**
 * FormSettingsDialog - Edit the form-level settings the builder otherwise
 * only ever displays: title, description, submit button text and success
 * message. FormCanvas and the FormBuilder header both render
 * formSettings.title, but nothing in the builder previously let an
 * administrator change it.
 *
 * @param {boolean} isOpen - Whether the dialog is open.
 * @param {object} formSettings - Current form settings.
 * @param {function} onSave - Called with the edited settings object.
 * @param {function} onClose - Called when the dialog is dismissed, saved or not.
 */
const FormSettingsDialog = ({ isOpen, formSettings = {}, onSave, onClose }) => {
  const [draft, setDraft] = useState(formSettings);
  const dialogRef = useRef(null);
  const titleInputRef = useRef(null);
  const previouslyFocusedRef = useRef(null);

  // Re-seed the draft from the latest saved settings each time the dialog
  // opens, so a previous cancelled edit does not leak into the next open.
  useEffect(() => {
    if (isOpen) {
      setDraft(formSettings);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen]);

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };

    if (isOpen) {
      previouslyFocusedRef.current = document.activeElement;
      document.addEventListener('keydown', handleKeyDown);
      setTimeout(() => {
        titleInputRef.current?.focus();
      }, 100);
    }

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      previouslyFocusedRef.current?.focus?.();
    };
  }, [isOpen, onClose]);

  useEffect(() => {
    if (!isOpen) return undefined;

    const dialog = dialogRef.current;
    const focusableElements = dialog?.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    const firstElement = focusableElements?.[0];
    const lastElement = focusableElements?.[focusableElements.length - 1];

    const handleTabKey = (e) => {
      if (e.key !== 'Tab') return;

      if (e.shiftKey) {
        if (document.activeElement === firstElement) {
          e.preventDefault();
          lastElement?.focus();
        }
      } else if (document.activeElement === lastElement) {
        e.preventDefault();
        firstElement?.focus();
      }
    };

    dialog?.addEventListener('keydown', handleTabKey);
    return () => dialog?.removeEventListener('keydown', handleTabKey);
  }, [isOpen]);

  if (!isOpen) return null;

  const update = (key, value) => setDraft((current) => ({ ...current, [key]: value }));

  const notification = draft.notifications?.[0] || {};

  const updateNotification = (key, value) => {
    setDraft((current) => ({
      ...current,
      notifications: [{ ...(current.notifications?.[0] || {}), [key]: value }],
    }));
  };

  const handleSave = () => {
    onSave(draft);
    onClose();
  };

  return (
    <div className="formtura-confirm-overlay" onClick={onClose} role="presentation">
      <div
        ref={dialogRef}
        className="formtura-confirm-dialog formtura-settings-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="form-settings-dialog-title"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="formtura-confirm-content">
          <h3 id="form-settings-dialog-title" className="formtura-confirm-title">
            {__('Form settings', 'formtura')}
          </h3>

          <div className="formtura-form-group">
            <label htmlFor="formtura-settings-title">{__('Form Title', 'formtura')}</label>
            <input
              id="formtura-settings-title"
              ref={titleInputRef}
              type="text"
              value={draft.title || ''}
              onChange={(e) => update('title', e.target.value)}
              placeholder={__('Untitled form', 'formtura')}
            />
          </div>

          <div className="formtura-form-group">
            <label htmlFor="formtura-settings-description">{__('Description', 'formtura')}</label>
            <textarea
              id="formtura-settings-description"
              value={draft.description || ''}
              onChange={(e) => update('description', e.target.value)}
              rows={3}
            />
          </div>

          <div className="formtura-form-group">
            <label htmlFor="formtura-settings-submit-text">{__('Submit Button Text', 'formtura')}</label>
            <input
              id="formtura-settings-submit-text"
              type="text"
              value={draft.submitButtonText || ''}
              onChange={(e) => update('submitButtonText', e.target.value)}
              placeholder={__('Submit', 'formtura')}
            />
          </div>

          <div className="formtura-form-group">
            <label htmlFor="formtura-settings-success-message">{__('Success Message', 'formtura')}</label>
            <textarea
              id="formtura-settings-success-message"
              value={draft.successMessage || ''}
              onChange={(e) => update('successMessage', e.target.value)}
              rows={3}
              placeholder={__('Thank you for your submission!', 'formtura')}
            />
          </div>

          <div className="formtura-form-group">
            <label htmlFor="formtura-settings-redirect-url">{__('Redirect URL', 'formtura')}</label>
            <input
              id="formtura-settings-redirect-url"
              type="url"
              value={draft.redirect_url || ''}
              onChange={(e) => update('redirect_url', e.target.value)}
              placeholder={__('https://example.com/thank-you', 'formtura')}
            />
          </div>

          <h4 className="formtura-settings-section-title">{__('Email Notification', 'formtura')}</h4>

          <div className="formtura-form-group">
            <div className="formtura-toggle-group">
              <label className="formtura-toggle">
                <input
                  id="formtura-settings-notify-enabled"
                  type="checkbox"
                  checked={notification.enabled || false}
                  onChange={(e) => updateNotification('enabled', e.target.checked)}
                />
                <span className="formtura-toggle-slider"></span>
              </label>
              <label className="formtura-toggle-label" htmlFor="formtura-settings-notify-enabled">
                {__('Send Email Notification', 'formtura')}
              </label>
            </div>
          </div>

          {notification.enabled && (
            <>
              <div className="formtura-form-group">
                <label htmlFor="formtura-settings-notify-to">{__('Send To', 'formtura')}</label>
                <input
                  id="formtura-settings-notify-to"
                  type="text"
                  value={notification.to || ''}
                  onChange={(e) => updateNotification('to', e.target.value)}
                  placeholder="{admin_email}"
                />
              </div>

              <div className="formtura-form-group">
                <label htmlFor="formtura-settings-notify-subject">{__('Subject', 'formtura')}</label>
                <input
                  id="formtura-settings-notify-subject"
                  type="text"
                  value={notification.subject || ''}
                  onChange={(e) => updateNotification('subject', e.target.value)}
                  placeholder={__('New Form Submission from {site_name}', 'formtura')}
                />
              </div>

              <div className="formtura-form-group">
                <label htmlFor="formtura-settings-notify-message">{__('Message', 'formtura')}</label>
                <textarea
                  id="formtura-settings-notify-message"
                  value={notification.message || ''}
                  onChange={(e) => updateNotification('message', e.target.value)}
                  rows={4}
                  placeholder={__('You have received a new form submission.', 'formtura')}
                />
              </div>

              <div className="formtura-form-group">
                <label htmlFor="formtura-settings-notify-reply-to">{__('Reply-To', 'formtura')}</label>
                <input
                  id="formtura-settings-notify-reply-to"
                  type="text"
                  value={notification.reply_to || ''}
                  onChange={(e) => updateNotification('reply_to', e.target.value)}
                />
              </div>

              <div className="formtura-form-group">
                <label htmlFor="formtura-settings-notify-cc">{__('CC', 'formtura')}</label>
                <input
                  id="formtura-settings-notify-cc"
                  type="text"
                  value={notification.cc || ''}
                  onChange={(e) => updateNotification('cc', e.target.value)}
                />
              </div>

              <div className="formtura-form-group">
                <label htmlFor="formtura-settings-notify-bcc">{__('BCC', 'formtura')}</label>
                <input
                  id="formtura-settings-notify-bcc"
                  type="text"
                  value={notification.bcc || ''}
                  onChange={(e) => updateNotification('bcc', e.target.value)}
                />
              </div>
            </>
          )}
        </div>

        <div className="formtura-confirm-actions">
          <button
            type="button"
            className="formtura-confirm-btn formtura-confirm-btn-primary"
            onClick={handleSave}
          >
            {__('Save', 'formtura')}
          </button>
          <button
            type="button"
            className="formtura-confirm-btn formtura-confirm-btn-secondary"
            onClick={onClose}
          >
            {__('Cancel', 'formtura')}
          </button>
        </div>
      </div>
    </div>
  );
};

export default FormSettingsDialog;
