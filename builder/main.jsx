import React from 'react';
import { createRoot } from 'react-dom/client';
import FormBuilder from './components/FormBuilder';
import Toast from './components/Toast';
import LiveRegion from './components/LiveRegion';
import { log, handleError, LogLevel } from './utils/errorHandler';
import './styles/builder.css';
import './styles/toast.css';
import './styles/accessibility.css';

// Initialize the form builder when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  log('DOMContentLoaded event fired', LogLevel.DEBUG);
  const container = document.getElementById('formtura-builder-root');

  log('Container element found', LogLevel.DEBUG, { container: !!container });

  if (container) {
    try {
      log('Creating React root', LogLevel.DEBUG);
      const root = createRoot(container);
      const formId = container.dataset.formId || null;

      log('Rendering FormBuilder', LogLevel.DEBUG, { formId });
      root.render(
        <React.StrictMode>
          <LiveRegion />
          <Toast />
          <FormBuilder formId={formId} />
        </React.StrictMode>
      );
      log('FormBuilder rendered successfully', LogLevel.DEBUG);
    } catch (error) {
      handleError(error, {
        userMessage: 'Failed to initialize form builder. Please refresh the page.',
      });
    }
  } else {
    handleError('Container element #formtura-builder-root not found', {
      userMessage: 'Form builder container not found. Please contact support.',
    });
  }
});
