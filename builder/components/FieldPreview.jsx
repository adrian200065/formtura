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
            {field.options?.map((option, index) => (
              <option key={index} value={option}>
                {option}
              </option>
            ))}
          </select>
        );

      case 'radio':
        return (
          <div>
            {field.options?.map((option, index) => (
              <div key={index} style={{ marginBottom: '0.5rem' }}>
                <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <input
                    type="radio"
                    name={field.id}
                    value={option}
                    required={field.required}
                    disabled
                  />
                  {option}
                </label>
              </div>
            ))}
          </div>
        );

      case 'checkbox':
        return (
          <div>
            {field.options?.map((option, index) => (
              <div key={index} style={{ marginBottom: '0.5rem' }}>
                <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <input
                    type="checkbox"
                    value={option}
                    disabled
                  />
                  {option}
                </label>
              </div>
            ))}
          </div>
        );

      case 'name':
        return (
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem' }}>
            <input type="text" placeholder="First Name" readOnly />
            <input type="text" placeholder="Last Name" readOnly />
          </div>
        );

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
