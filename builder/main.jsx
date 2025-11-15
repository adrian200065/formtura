import React from 'react';
import { createRoot } from 'react-dom/client';
import FormBuilder from './components/FormBuilder';
import './styles/builder.css';

// Initialize the form builder when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  console.log('Formtura: DOMContentLoaded event fired');
  const container = document.getElementById('formtura-builder-root');

  console.log('Formtura: Container element:', container);

  if (container) {
    try {
      console.log('Formtura: Creating React root');
      const root = createRoot(container);
      const formId = container.dataset.formId || null;

      console.log('Formtura: Rendering FormBuilder with formId:', formId);
      root.render(
        <React.StrictMode>
          <FormBuilder formId={formId} />
        </React.StrictMode>
      );
      console.log('Formtura: FormBuilder rendered successfully');
    } catch (error) {
      console.error('Formtura: Error rendering FormBuilder:', error);
    }
  } else {
    console.error('Formtura: Container element #formtura-builder-root not found');
  }
});
