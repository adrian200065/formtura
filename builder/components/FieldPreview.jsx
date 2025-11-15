const FieldPreview = ({ field }) => {
  // Determine field size class
  const getFieldSizeClass = () => {
    switch(field.fieldSize) {
      case 'small':
        return 'formtura-field-size-small';
      case 'large':
        return 'formtura-field-size-large';
      case 'medium':
      default:
        return 'formtura-field-size-medium';
    }
  };

  const renderField = () => {
    switch (field.type) {
      case 'text':
      case 'email':
      case 'number':
        return (
          <input
            type={field.type}
            placeholder={field.placeholder}
            required={field.required}
            readOnly
          />
        );

      case 'textarea':
        return (
          <textarea
            placeholder={field.placeholder}
            rows={field.rows || 4}
            required={field.required}
            readOnly
          />
        );

      case 'select':
        return (
          <select required={field.required} disabled>
            <option value="">Select an option</option>
            {(field.choices || field.options)?.map((choice, index) => (
              <option key={index} value={choice.value || choice}>
                {choice.label || choice}
              </option>
            ))}
          </select>
        );

      case 'radio':
        return (
          <div className="formtura-radio-group">
            {(field.choices || field.options)?.map((choice, index) => (
              <div key={index} className="formtura-radio-item">
                <label>
                  <input
                    type="radio"
                    name={field.id}
                    value={choice.value || choice}
                    checked={choice.isDefault || false}
                    required={field.required}
                    disabled
                  />
                  <span>{choice.label || choice}</span>
                </label>
              </div>
            ))}
          </div>
        );

      case 'checkbox':
        return (
          <div className="formtura-checkbox-choices-group">
            {(field.choices || field.options)?.map((choice, index) => (
              <div key={index} className="formtura-checkbox-choice-item">
                <label>
                  <input
                    type="radio"
                    name={field.id}
                    value={choice.value || choice}
                    checked={choice.isDefault || false}
                    required={field.required}
                    disabled
                  />
                  <span>{choice.label || choice}</span>
                </label>
              </div>
            ))}
          </div>
        );

      case 'checkboxes':
        return (
          <div className="formtura-checkboxes-group">
            {(field.choices || field.options)?.map((choice, index) => (
              <div key={index} className="formtura-checkbox-item">
                <label>
                  <input
                    type="checkbox"
                    value={choice.value || choice}
                    checked={choice.isDefault || false}
                    disabled
                  />
                  <span>{choice.label || choice}</span>
                </label>
              </div>
            ))}
          </div>
        );

      case 'name':
        const nameFormat = field.format || 'first-last';
        if (nameFormat === 'simple') {
          return (
            <input type="text" placeholder="Name" readOnly />
          );
        } else if (nameFormat === 'first-middle-last') {
          return (
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '0.5rem' }}>
              <input type="text" placeholder="First Name" readOnly />
              <input type="text" placeholder="Middle Name" readOnly />
              <input type="text" placeholder="Last Name" readOnly />
            </div>
          );
        } else {
          // first-last (default)
          return (
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem' }}>
              <input type="text" placeholder="First Name" readOnly />
              <input type="text" placeholder="Last Name" readOnly />
            </div>
          );
        }

      case 'phone':
        return (
          <input
            type="tel"
            placeholder={field.placeholder || '(123) 456-7890'}
            required={field.required}
            readOnly
          />
        );

      case 'date':
        return (
          <input
            type="date"
            required={field.required}
            readOnly
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
              value={defaultValue}
              step={field.increment || 1}
              readOnly
              style={{ width: '100%' }}
            />
            <div className="formtura-slider-value">{displayText}</div>
          </div>
        );

      default:
        return (
          <input
            type="text"
            placeholder={field.placeholder}
            readOnly
          />
        );
    }
  };

  return (
    <div className="formtura-field-preview">
      {!field.hideLabel && (
        <label>
          {field.label}
          {field.required && <span style={{ color: 'red' }}> *</span>}
        </label>
      )}
      <div className={getFieldSizeClass()}>
        {renderField()}
      </div>
      {field.description && (
        <p className="formtura-field-description-text">
          {field.description}
        </p>
      )}
    </div>
  );
};

export default FieldPreview;
