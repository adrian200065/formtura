import { useState, useEffect, useCallback } from 'react';
import { CheckCircle, XCircle, AlertCircle, X } from 'lucide-react';

/**
 * Toast notification component
 */
const Toast = () => {
  const [toasts, setToasts] = useState([]);

  const removeToast = useCallback((id) => {
    setToasts((prev) => prev.filter((toast) => toast.id !== id));
  }, []);

  const addToast = useCallback((message, type = 'info', duration = 4000) => {
    const id = Date.now() + Math.random();
    const toast = { id, message, type, duration };

    setToasts((prev) => [...prev, toast]);

    if (duration > 0) {
      setTimeout(() => removeToast(id), duration);
    }

    return id;
  }, [removeToast]);

  // Expose API globally
  useEffect(() => {
    window.formturaToast = {
      success: (message, duration) => addToast(message, 'success', duration),
      error: (message, duration) => addToast(message, 'error', duration),
      warning: (message, duration) => addToast(message, 'warning', duration),
      info: (message, duration) => addToast(message, 'info', duration),
      remove: removeToast,
    };

    return () => {
      delete window.formturaToast;
    };
  }, [addToast, removeToast]);

  return (
    <div
      className="formtura-toast-container"
      role="region"
      aria-label="Notifications"
      aria-live="polite"
    >
      {toasts.map((toast) => (
        <ToastItem
          key={toast.id}
          toast={toast}
          onClose={() => removeToast(toast.id)}
        />
      ))}
    </div>
  );
};

/**
 * Individual toast item
 */
const ToastItem = ({ toast, onClose }) => {
  const getIcon = () => {
    switch (toast.type) {
      case 'success':
        return <CheckCircle size={20} aria-hidden="true" />;
      case 'error':
        return <XCircle size={20} aria-hidden="true" />;
      case 'warning':
        return <AlertCircle size={20} aria-hidden="true" />;
      default:
        return <AlertCircle size={20} aria-hidden="true" />;
    }
  };

  const getTypeClass = () => {
    return `formtura-toast-${toast.type}`;
  };

  return (
    <div
      className={`formtura-toast ${getTypeClass()}`}
      role="alert"
      aria-atomic="true"
    >
      <div className="formtura-toast-icon">
        {getIcon()}
      </div>
      <div className="formtura-toast-message">
        {toast.message}
      </div>
      <button
        className="formtura-toast-close"
        onClick={onClose}
        aria-label="Close notification"
        type="button"
      >
        <X size={16} aria-hidden="true" />
      </button>
    </div>
  );
};

export default Toast;
