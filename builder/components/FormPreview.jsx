import { X } from 'lucide-react';

const FormPreview = ({ fields, formSettings, onClose }) => {
  const renderField = (field) => {
    const fieldClasses = `formtura-preview-field ${field.cssClasses || ''}`;
    const fieldSizeClass = field.fieldSize ? `field-size-${field.fieldSize}` : '';

    const renderInput = () => {
      switch (field.type) {
        case 'text':
        case 'email':
        case 'number':
          return (
            <input
              type={field.type}
              placeholder={field.placeholder}
              required={field.required}
              readOnly={field.readOnly}
              className={fieldSizeClass}
            />
          );

        case 'textarea':
          return (
            <textarea
              placeholder={field.placeholder}
              rows={field.rows || 4}
              required={field.required}
              readOnly={field.readOnly}
              className={fieldSizeClass}
            />
          );

        case 'select':
          return (
            <select required={field.required} className={fieldSizeClass}>
              <option value="">Select an option</option>
              {field.options?.map((option, index) => (
                <option key={index} value={option}>
                  {option}
                </option>
              ))}
            </select>
          );

        case 'radio':
          return (
            <div className="formtura-preview-radio-group">
              {field.options?.map((option, index) => (
                <label key={index} className="formtura-preview-radio">
                  <input
                    type="radio"
                    name={field.id}
                    value={option}
                    required={field.required}
                  />
                  <span>{option}</span>
                </label>
              ))}
            </div>
          );

        case 'checkbox':
          return (
            <div className="formtura-preview-checkbox-group">
              {field.options?.map((option, index) => (
                <label key={index} className="formtura-preview-checkbox">
                  <input type="checkbox" value={option} />
                  <span>{option}</span>
                </label>
              ))}
            </div>
          );

        case 'name':
          return (
            <div className="formtura-preview-name-group">
              <input type="text" placeholder="First Name" className="formtura-preview-name-input" />
              <input type="text" placeholder="Last Name" className="formtura-preview-name-input" />
            </div>
          );

        case 'phone':
          return (
            <input
              type="tel"
              placeholder={field.placeholder || '(123) 456-7890'}
              required={field.required}
              className={fieldSizeClass}
            />
          );

        case 'date':
          return (
            <input
              type="date"
              required={field.required}
              className={fieldSizeClass}
            />
          );

        default:
          return (
            <input
              type="text"
              placeholder={field.placeholder}
              className={fieldSizeClass}
            />
          );
      }
    };

    return (
      <div key={field.id} className={fieldClasses}>
        {!field.hideLabel && (
          <label className="formtura-preview-label">
            {field.label}
            {field.required && <span className="formtura-preview-required"> *</span>}
          </label>
        )}
        {renderInput()}
        {field.description && (
          <p className="formtura-preview-description">{field.description}</p>
        )}
      </div>
    );
  };

  // Helper function to check if a field has grid classes
  const hasGridClasses = (field) => {
    return field.cssClasses && (
      field.cssClasses.includes('fta-one-half') ||
      field.cssClasses.includes('fta-one-third') ||
      field.cssClasses.includes('fta-two-thirds') ||
      field.cssClasses.includes('fta-one-fourth') ||
      field.cssClasses.includes('fta-two-fourths') ||
      field.cssClasses.includes('fta-three-fourths')
    );
  };

  // Group fields into rows based on fta-first class, but only for fields with grid classes
  const groupFieldsIntoRows = () => {
    const items = [];
    let currentRow = [];

    fields.forEach((field, index) => {
      const hasGrid = hasGridClasses(field);
      const hasFirstClass = field.cssClasses?.includes('fta-first');

      if (hasGrid) {
        // This field has grid classes
        if (hasFirstClass && currentRow.length > 0) {
          // Start a new row
          items.push({ type: 'row', fields: currentRow });
          currentRow = [field];
        } else {
          // Add to current row
          currentRow.push(field);
        }
      } else {
        // This field has no grid classes - render it standalone
        // First, flush any pending row
        if (currentRow.length > 0) {
          items.push({ type: 'row', fields: currentRow });
          currentRow = [];
        }
        // Add this field as standalone
        items.push({ type: 'field', field: field });
      }
    });

    // Push any remaining row
    if (currentRow.length > 0) {
      items.push({ type: 'row', fields: currentRow });
    }

    return items;
  };

  const renderRows = () => {
    const items = groupFieldsIntoRows();

    return items.map((item, index) => {
      if (item.type === 'row') {
        // Render as a row with grid layout
        return (
          <div key={`row-${index}`} className="fta-form-row">
            {item.fields.map(field => renderField(field))}
          </div>
        );
      } else {
        // Render single field (no grid layout)
        return renderField(item.field);
      }
    });
  };

  return (
    <div className="formtura-preview-overlay" onClick={onClose}>
      <div className="formtura-preview-modal" onClick={(e) => e.stopPropagation()}>
        <div className="formtura-preview-header">
          <h2>Form Preview</h2>
          <button
            type="button"
            className="formtura-preview-close"
            onClick={onClose}
            aria-label="Close preview"
          >
            <X size={20} />
          </button>
        </div>

        <div className="formtura-preview-body">
          <form className="formtura-preview-form" onSubmit={(e) => e.preventDefault()}>
            {formSettings.title && (
              <h3 className="formtura-preview-form-title">{formSettings.title}</h3>
            )}
            {formSettings.description && (
              <p className="formtura-preview-form-description">{formSettings.description}</p>
            )}

            <div className="formtura-preview-fields">
              {fields.length === 0 ? (
                <p className="formtura-preview-empty">No fields added yet. Add fields to see the preview.</p>
              ) : (
                renderRows()
              )}
            </div>

            {fields.length > 0 && (
              <button type="submit" className="formtura-preview-submit">
                {formSettings.submitButtonText || 'Submit'}
              </button>
            )}
          </form>
        </div>
      </div>
    </div>
  );
};

export default FormPreview;
