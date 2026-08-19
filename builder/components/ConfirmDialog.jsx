import { AlertCircle } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { __ } from '../utils/i18n';

/**
 * ConfirmDialog - A custom confirmation popup component
 *
 * @param {boolean} isOpen - Whether the dialog is open
 * @param {string} title - The dialog title
 * @param {string} message - The confirmation message
 * @param {string} confirmText - Text for the confirm button
 * @param {string} cancelText - Text for the cancel button
 * @param {function} onConfirm - Callback when confirmed
 * @param {function} onCancel - Callback when cancelled
 * @param {string} type - Dialog type: 'danger', 'warning', 'info'
 */
const ConfirmDialog = ({
  isOpen,
  title = __('Confirm', 'formtura'),
  message = __('Are you sure?', 'formtura'),
  confirmText = __('OK', 'formtura'),
  cancelText = __('Cancel', 'formtura'),
  onConfirm,
  onCancel,
  type = 'danger'
}) => {
  const dialogRef = useRef(null);
  const confirmButtonRef = useRef(null);
  const previouslyFocusedRef = useRef(null);

  // Handle escape key
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && isOpen) {
        onCancel();
      }
    };

    if (isOpen) {
      previouslyFocusedRef.current = document.activeElement;
      document.addEventListener('keydown', handleKeyDown);
      // Focus the confirm button when dialog opens
      setTimeout(() => {
        confirmButtonRef.current?.focus();
      }, 100);
    }

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      previouslyFocusedRef.current?.focus?.();
    };
  }, [isOpen, onCancel]);

  // Trap focus inside the dialog
  useEffect(() => {
    if (!isOpen) return;

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
      } else {
        if (document.activeElement === lastElement) {
          e.preventDefault();
          firstElement?.focus();
        }
      }
    };

    dialog?.addEventListener('keydown', handleTabKey);
    return () => dialog?.removeEventListener('keydown', handleTabKey);
  }, [isOpen]);

  if (!isOpen) return null;

  return (
    <div
      className="formtura-confirm-overlay"
      onClick={onCancel}
      role="presentation"
    >
      <div
        ref={dialogRef}
        className={`formtura-confirm-dialog formtura-confirm-dialog-${type}`}
        role="alertdialog"
        aria-modal="true"
        aria-labelledby={title ? 'confirm-dialog-title' : undefined}
        aria-describedby="confirm-dialog-message"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="formtura-confirm-icon">
          <AlertCircle size={48} strokeWidth={1.5} />
        </div>

        <div className="formtura-confirm-content">
          {title && (
            <h3 id="confirm-dialog-title" className="formtura-confirm-title">
              {title}
            </h3>
          )}
          <p id="confirm-dialog-message" className="formtura-confirm-message">
            {message}
          </p>
        </div>

        <div className="formtura-confirm-actions">
          <button
            ref={confirmButtonRef}
            type="button"
            className="formtura-confirm-btn formtura-confirm-btn-primary"
            onClick={onConfirm}
          >
            {confirmText}
          </button>
          <button
            type="button"
            className="formtura-confirm-btn formtura-confirm-btn-secondary"
            onClick={onCancel}
          >
            {cancelText}
          </button>
        </div>
      </div>
    </div>
  );
};

export default ConfirmDialog;
