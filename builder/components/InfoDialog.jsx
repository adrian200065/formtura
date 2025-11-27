import { AlertCircle } from 'lucide-react';
import { useEffect, useRef } from 'react';

/**
 * InfoDialog - A simple informational popup with a single button
 *
 * @param {boolean} isOpen - Whether the dialog is open
 * @param {string} title - The dialog title
 * @param {string|React.ReactNode} message - The message to display
 * @param {string} buttonText - Text for the button
 * @param {function} onClose - Callback when closed
 */
const InfoDialog = ({
  isOpen,
  title = 'Heads up!',
  message = '',
  buttonText = 'OK',
  onClose,
}) => {
  const dialogRef = useRef(null);
  const buttonRef = useRef(null);

  // Handle escape key
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };

    if (isOpen) {
      document.addEventListener('keydown', handleKeyDown);
      // Focus the button when dialog opens
      setTimeout(() => {
        buttonRef.current?.focus();
      }, 100);
    }

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  return (
    <div
      className="formtura-confirm-overlay"
      onClick={onClose}
      role="presentation"
    >
      <div
        ref={dialogRef}
        className="formtura-confirm-dialog formtura-info-dialog"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="info-dialog-title"
        aria-describedby="info-dialog-message"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="formtura-confirm-icon" style={{ color: 'var(--fta-builder-primary)' }}>
          <AlertCircle size={48} strokeWidth={1.5} />
        </div>

        <div className="formtura-confirm-content">
          {title && (
            <h3 id="info-dialog-title" className="formtura-confirm-title">
              {title}
            </h3>
          )}
          <div id="info-dialog-message" className="formtura-confirm-message">
            {message}
          </div>
        </div>

        <div className="formtura-confirm-actions">
          <button
            ref={buttonRef}
            type="button"
            className="formtura-confirm-btn formtura-confirm-btn-primary"
            onClick={onClose}
          >
            {buttonText}
          </button>
        </div>
      </div>
    </div>
  );
};

export default InfoDialog;
