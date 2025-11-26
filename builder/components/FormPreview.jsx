import { X } from 'lucide-react';

const FormPreview = ({ fields, formSettings, onClose }) => {
  const renderField = (field) => {
    const fieldClasses = `formtura-preview-field ${field.cssClasses || ''}`;
    const fieldSizeClass = field.fieldSize ? `field-size-${field.fieldSize}` : '';

    const renderInput = () => {
      switch (field.type) {
        case 'text':
        case 'email':
          return (
            <input
              type={field.type}
              placeholder={field.placeholder}
              required={field.required}
              readOnly={field.readOnly}
              className={fieldSizeClass}
            />
          );

        case 'number':
          return (
            <input
              type="number"
              placeholder={field.placeholder}
              defaultValue={field.defaultValue !== undefined ? field.defaultValue : ''}
              min={field.minValue !== undefined ? field.minValue : undefined}
              max={field.maxValue !== undefined ? field.maxValue : undefined}
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
          // Get choices based on dynamic choices setting
          const getSelectChoices = () => {
            if (field.dynamicChoices === 'post_type' || field.dynamicChoices === 'taxonomy') {
              // For preview, show placeholder dynamic choices
              return [
                { value: '1', label: 'Dynamic Choice 1' },
                { value: '2', label: 'Dynamic Choice 2' },
                { value: '3', label: 'Dynamic Choice 3' }
              ];
            }
            return field.choices || field.options || [];
          };

          const selectChoices = getSelectChoices();
          const isMultiple = field.multipleSelection || false;

          if (isMultiple) {
            return (
              <select
                multiple
                size={Math.min(selectChoices.length, 5)}
                required={field.required}
                className={fieldSizeClass}
                style={{ minHeight: '80px' }}
              >
                {selectChoices.map((choice, index) => (
                  <option key={index} value={choice.value || choice}>
                    {choice.label || choice}
                  </option>
                ))}
              </select>
            );
          }

          return (
            <select required={field.required} className={fieldSizeClass}>
              <option value="">{field.placeholder || 'Select an option'}</option>
              {selectChoices.map((choice, index) => (
                <option key={index} value={choice.value || choice}>
                  {choice.label || choice}
                </option>
              ))}
            </select>
          );

        case 'radio':
        case 'checkbox': {
          // Get choice layout class
          const getRadioLayoutClass = () => {
            switch(field.choiceLayout) {
              case 'two-columns':
                return 'formtura-choices-two-columns';
              case 'three-columns':
                return 'formtura-choices-three-columns';
              case 'inline':
                return 'formtura-choices-inline';
              case 'one-column':
              default:
                return 'formtura-choices-one-column';
            }
          };

          // Get choices based on dynamic choices setting
          const getRadioChoices = () => {
            if (field.dynamicChoices === 'post_type' || field.dynamicChoices === 'taxonomy') {
              return [
                { value: '1', label: 'Dynamic Choice 1' },
                { value: '2', label: 'Dynamic Choice 2' },
                { value: '3', label: 'Dynamic Choice 3' }
              ];
            }
            return field.choices || field.options || [];
          };

          let radioChoices = getRadioChoices();

          // Randomize choices if enabled
          if (field.randomizeChoices) {
            radioChoices = [...radioChoices].sort(() => Math.random() - 0.5);
          }

          const radioLayoutClass = getRadioLayoutClass();
          const radioGroupClass = field.type === 'checkbox' ? 'formtura-preview-checkbox-group' : 'formtura-preview-radio-group';

          return (
            <div className={`${radioGroupClass} ${radioLayoutClass}`}>
              {radioChoices.map((choice, index) => (
                <label key={index} className={field.type === 'checkbox' ? 'formtura-preview-checkbox' : 'formtura-preview-radio'}>
                  <input
                    type="radio"
                    name={field.id}
                    value={choice.value || choice}
                    defaultChecked={choice.isDefault || false}
                    required={field.required}
                  />
                  <span>{choice.label || choice}</span>
                </label>
              ))}
            </div>
          );
        }

        case 'checkboxes': {
          // Get choice layout class
          const getCheckboxesLayoutClass = () => {
            switch(field.choiceLayout) {
              case 'two-columns':
                return 'formtura-choices-two-columns';
              case 'three-columns':
                return 'formtura-choices-three-columns';
              case 'inline':
                return 'formtura-choices-inline';
              case 'one-column':
              default:
                return 'formtura-choices-one-column';
            }
          };

          // Get choices based on dynamic choices setting
          const getCheckboxesChoices = () => {
            if (field.dynamicChoices === 'post_type' || field.dynamicChoices === 'taxonomy') {
              return [
                { value: '1', label: 'Dynamic Choice 1' },
                { value: '2', label: 'Dynamic Choice 2' },
                { value: '3', label: 'Dynamic Choice 3' }
              ];
            }
            return field.choices || field.options || [];
          };

          let checkboxesChoices = getCheckboxesChoices();

          // Randomize choices if enabled
          if (field.randomizeChoices) {
            checkboxesChoices = [...checkboxesChoices].sort(() => Math.random() - 0.5);
          }

          return (
            <div className={`formtura-preview-checkboxes-group ${getCheckboxesLayoutClass()}`}>
              {checkboxesChoices.map((choice, index) => (
                <label key={index} className="formtura-preview-checkbox">
                  <input
                    type="checkbox"
                    value={choice.value || choice}
                    defaultChecked={choice.isDefault || false}
                  />
                  <span>{choice.label || choice}</span>
                </label>
              ))}
            </div>
          );
        }

        case 'name':
          const nameFormat = field.format || 'first-last';
          const firstPlaceholder = field.firstNamePlaceholder || 'First Name';
          const middlePlaceholder = field.middleNamePlaceholder || 'Middle Name';
          const lastPlaceholder = field.lastNamePlaceholder || 'Last Name';
          const firstDefault = field.firstNameDefault || '';
          const middleDefault = field.middleNameDefault || '';
          const lastDefault = field.lastNameDefault || '';

          if (nameFormat === 'simple') {
            return (
              <input
                type="text"
                placeholder={field.placeholder || 'Name'}
                defaultValue={firstDefault}
                className={fieldSizeClass}
              />
            );
          } else if (nameFormat === 'first-middle-last') {
            return (
              <div className="formtura-preview-name-group formtura-preview-name-group-3">
                <div className="formtura-preview-name-item">
                  <input type="text" placeholder={firstPlaceholder} defaultValue={firstDefault} className="formtura-preview-name-input" />
                  {!field.hideSublabels && <span className="formtura-preview-sublabel">First Name</span>}
                </div>
                <div className="formtura-preview-name-item">
                  <input type="text" placeholder={middlePlaceholder} defaultValue={middleDefault} className="formtura-preview-name-input" />
                  {!field.hideSublabels && <span className="formtura-preview-sublabel">Middle Name</span>}
                </div>
                <div className="formtura-preview-name-item">
                  <input type="text" placeholder={lastPlaceholder} defaultValue={lastDefault} className="formtura-preview-name-input" />
                  {!field.hideSublabels && <span className="formtura-preview-sublabel">Last Name</span>}
                </div>
              </div>
            );
          } else {
            // first-last (default)
            return (
              <div className="formtura-preview-name-group">
                <div className="formtura-preview-name-item">
                  <input type="text" placeholder={firstPlaceholder} defaultValue={firstDefault} className="formtura-preview-name-input" />
                  {!field.hideSublabels && <span className="formtura-preview-sublabel">First Name</span>}
                </div>
                <div className="formtura-preview-name-item">
                  <input type="text" placeholder={lastPlaceholder} defaultValue={lastDefault} className="formtura-preview-name-input" />
                  {!field.hideSublabels && <span className="formtura-preview-sublabel">Last Name</span>}
                </div>
              </div>
            );
          }

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

        case 'number-slider':
          const minValue = field.minValue !== undefined ? field.minValue : 0;
          const maxValue = field.maxValue !== undefined ? field.maxValue : 10;
          const defaultValue = field.defaultValue !== undefined ? field.defaultValue : minValue;
          const valueDisplay = field.valueDisplay || 'Selected Value: {value}';
          const displayText = valueDisplay.replace('{value}', defaultValue);

          return (
            <div className="formtura-slider-container">
              <input
                type="range"
                min={minValue}
                max={maxValue}
                defaultValue={defaultValue}
                step={field.increment || 1}
                className={fieldSizeClass}
                style={{ width: '100%' }}
                disabled={field.readOnly}
              />
              <div className="formtura-slider-value">{displayText}</div>
            </div>
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
