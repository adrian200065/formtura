const FieldPreview = ({ field }) => {
  const fieldId = `field-preview-${field.id}`;
  const descriptionId = field.description ? `${fieldId}-description` : null;

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
        return (
          <input
            id={`${fieldId}-input`}
            type={field.type}
            placeholder={field.placeholder}
            required={field.required}
            readOnly
            aria-label={field.hideLabel ? field.label : undefined}
            aria-required={field.required}
          />
        );

      case 'number':
        return (
          <input
            id={`${fieldId}-input`}
            type="number"
            placeholder={field.placeholder}
            defaultValue={field.defaultValue !== undefined ? field.defaultValue : ''}
            min={field.minValue !== undefined ? field.minValue : undefined}
            max={field.maxValue !== undefined ? field.maxValue : undefined}
            required={field.required}
            readOnly
            aria-label={field.hideLabel ? field.label : undefined}
            aria-required={field.required}
          />
        );

      case 'textarea':
        return (
          <textarea
            id={`${fieldId}-input`}
            placeholder={field.placeholder}
            rows={field.rows || 4}
            required={field.required}
            readOnly
            aria-label={field.hideLabel ? field.label : undefined}
            aria-required={field.required}
          />
        );

      case 'select':
        // Get choices based on dynamic choices setting
        const getDropdownChoices = () => {
          if (field.dynamicChoices === 'post_type') {
            // Get post types from WordPress localized data
            const builtInPostTypes = [
              { value: 'post', label: 'Posts' },
              { value: 'page', label: 'Pages' },
              { value: 'attachment', label: 'Media' }
            ];
            const customPostTypes = window.formturaBuilderData?.postTypes || [];
            const allPostTypes = [...builtInPostTypes, ...customPostTypes];

            // Filter by selected post type source if set
            if (field.dynamicPostType) {
              // For preview, show sample items from the selected post type
              const selectedType = allPostTypes.find(pt => pt.value === field.dynamicPostType);
              if (selectedType) {
                // Return placeholder items representing posts of that type
                return [
                  { value: '1', label: `${selectedType.label} Item 1` },
                  { value: '2', label: `${selectedType.label} Item 2` },
                  { value: '3', label: `${selectedType.label} Item 3` }
                ];
              }
            }
            return allPostTypes;
          } else if (field.dynamicChoices === 'taxonomy') {
            // Get taxonomies from WordPress localized data
            const builtInTaxonomies = [
              { value: 'category', label: 'Categories' },
              { value: 'post_tag', label: 'Tags' }
            ];
            const customTaxonomies = window.formturaBuilderData?.taxonomies || [];
            const allTaxonomies = [...builtInTaxonomies, ...customTaxonomies];

            // Filter by selected taxonomy source if set
            if (field.dynamicTaxonomy) {
              const selectedTax = allTaxonomies.find(tax => tax.value === field.dynamicTaxonomy);
              if (selectedTax) {
                // Return placeholder items representing terms of that taxonomy
                return [
                  { value: '1', label: `${selectedTax.label} Term 1` },
                  { value: '2', label: `${selectedTax.label} Term 2` },
                  { value: '3', label: `${selectedTax.label} Term 3` }
                ];
              }
            }
            return allTaxonomies;
          }
          // Default: use manual choices
          return field.choices || field.options || [
            { value: '1', label: 'First Choice' },
            { value: '2', label: 'Second Choice' },
            { value: '3', label: 'Third Choice' }
          ];
        };

        const dropdownChoices = getDropdownChoices();
        const isMultiple = field.multipleSelection || false;

        if (isMultiple) {
          // Multi-select mode - render as a list box
          return (
            <select
              id={`${fieldId}-input`}
              multiple
              size={Math.min(dropdownChoices.length, 5)}
              required={field.required}
              disabled
              aria-label={field.hideLabel ? field.label : undefined}
              aria-required={field.required}
              style={{ minHeight: '80px' }}
            >
              {dropdownChoices.map((choice, index) => (
                <option key={index} value={choice.value || choice}>
                  {choice.label || choice}
                </option>
              ))}
            </select>
          );
        }

        // Single select mode - render as dropdown
        return (
          <select
            id={`${fieldId}-input`}
            required={field.required}
            disabled
            aria-label={field.hideLabel ? field.label : undefined}
            aria-required={field.required}
          >
            <option value="">{field.placeholder || 'Select an option'}</option>
            {dropdownChoices.map((choice, index) => (
              <option key={index} value={choice.value || choice}>
                {choice.label || choice}
              </option>
            ))}
          </select>
        );

      case 'radio':
      case 'checkbox': {
        // Get choices based on dynamic choices setting
        const getRadioChoices = () => {
          if (field.dynamicChoices === 'post_type') {
            const builtInPostTypes = [
              { value: 'post', label: 'Posts' },
              { value: 'page', label: 'Pages' },
              { value: 'attachment', label: 'Media' }
            ];
            const customPostTypes = window.formturaBuilderData?.postTypes || [];
            const allPostTypes = [...builtInPostTypes, ...customPostTypes];

            if (field.dynamicPostType) {
              const selectedType = allPostTypes.find(pt => pt.value === field.dynamicPostType);
              if (selectedType) {
                return [
                  { value: '1', label: `${selectedType.label} Item 1` },
                  { value: '2', label: `${selectedType.label} Item 2` },
                  { value: '3', label: `${selectedType.label} Item 3` }
                ];
              }
            }
            return allPostTypes;
          } else if (field.dynamicChoices === 'taxonomy') {
            const builtInTaxonomies = [
              { value: 'category', label: 'Categories' },
              { value: 'post_tag', label: 'Tags' }
            ];
            const customTaxonomies = window.formturaBuilderData?.taxonomies || [];
            const allTaxonomies = [...builtInTaxonomies, ...customTaxonomies];

            if (field.dynamicTaxonomy) {
              const selectedTax = allTaxonomies.find(tax => tax.value === field.dynamicTaxonomy);
              if (selectedTax) {
                return [
                  { value: '1', label: `${selectedTax.label} Term 1` },
                  { value: '2', label: `${selectedTax.label} Term 2` },
                  { value: '3', label: `${selectedTax.label} Term 3` }
                ];
              }
            }
            return allTaxonomies;
          }
          return field.choices || field.options || [
            { value: '1', label: 'First Choice' },
            { value: '2', label: 'Second Choice' },
            { value: '3', label: 'Third Choice' }
          ];
        };

        let radioChoices = getRadioChoices();

        // Randomize choices if enabled
        if (field.randomizeChoices) {
          radioChoices = [...radioChoices].sort(() => Math.random() - 0.5);
        }

        // Get layout class
        const getChoiceLayoutClass = () => {
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

        const layoutClass = getChoiceLayoutClass();
        const groupClass = field.type === 'checkbox' ? 'formtura-checkbox-choices-group' : 'formtura-radio-group';
        const itemClass = field.type === 'checkbox' ? 'formtura-checkbox-choice-item' : 'formtura-radio-item';

        return (
          <div className={`${groupClass} ${layoutClass}`}>
            {radioChoices.map((choice, index) => (
              <div key={index} className={itemClass}>
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
      }

      case 'checkboxes': {
        // Get choices based on dynamic choices setting
        const getCheckboxChoices = () => {
          if (field.dynamicChoices === 'post_type') {
            const builtInPostTypes = [
              { value: 'post', label: 'Posts' },
              { value: 'page', label: 'Pages' },
              { value: 'attachment', label: 'Media' }
            ];
            const customPostTypes = window.formturaBuilderData?.postTypes || [];
            const allPostTypes = [...builtInPostTypes, ...customPostTypes];

            if (field.dynamicPostType) {
              const selectedType = allPostTypes.find(pt => pt.value === field.dynamicPostType);
              if (selectedType) {
                return [
                  { value: '1', label: `${selectedType.label} Item 1` },
                  { value: '2', label: `${selectedType.label} Item 2` },
                  { value: '3', label: `${selectedType.label} Item 3` }
                ];
              }
            }
            return allPostTypes;
          } else if (field.dynamicChoices === 'taxonomy') {
            const builtInTaxonomies = [
              { value: 'category', label: 'Categories' },
              { value: 'post_tag', label: 'Tags' }
            ];
            const customTaxonomies = window.formturaBuilderData?.taxonomies || [];
            const allTaxonomies = [...builtInTaxonomies, ...customTaxonomies];

            if (field.dynamicTaxonomy) {
              const selectedTax = allTaxonomies.find(tax => tax.value === field.dynamicTaxonomy);
              if (selectedTax) {
                return [
                  { value: '1', label: `${selectedTax.label} Term 1` },
                  { value: '2', label: `${selectedTax.label} Term 2` },
                  { value: '3', label: `${selectedTax.label} Term 3` }
                ];
              }
            }
            return allTaxonomies;
          }
          return field.choices || field.options || [
            { value: '1', label: 'First Choice' },
            { value: '2', label: 'Second Choice' },
            { value: '3', label: 'Third Choice' }
          ];
        };

        let checkboxChoices = getCheckboxChoices();

        // Randomize choices if enabled
        if (field.randomizeChoices) {
          checkboxChoices = [...checkboxChoices].sort(() => Math.random() - 0.5);
        }

        // Get layout class
        const getCheckboxLayoutClass = () => {
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

        return (
          <div className={`formtura-checkboxes-group ${getCheckboxLayoutClass()}`}>
            {checkboxChoices.map((choice, index) => (
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
            <input type="text" placeholder={field.placeholder || 'Name'} defaultValue={firstDefault} readOnly />
          );
        } else if (nameFormat === 'first-middle-last') {
          return (
            <div className="formtura-name-field-group">
              <div className="formtura-name-field-item">
                <input type="text" placeholder={firstPlaceholder} defaultValue={firstDefault} readOnly />
                {!field.hideSublabels && <span className="formtura-sublabel">First Name</span>}
              </div>
              <div className="formtura-name-field-item">
                <input type="text" placeholder={middlePlaceholder} defaultValue={middleDefault} readOnly />
                {!field.hideSublabels && <span className="formtura-sublabel">Middle Name</span>}
              </div>
              <div className="formtura-name-field-item">
                <input type="text" placeholder={lastPlaceholder} defaultValue={lastDefault} readOnly />
                {!field.hideSublabels && <span className="formtura-sublabel">Last Name</span>}
              </div>
            </div>
          );
        } else {
          // first-last (default)
          return (
            <div className="formtura-name-field-group">
              <div className="formtura-name-field-item">
                <input type="text" placeholder={firstPlaceholder} defaultValue={firstDefault} readOnly />
                {!field.hideSublabels && <span className="formtura-sublabel">First Name</span>}
              </div>
              <div className="formtura-name-field-item">
                <input type="text" placeholder={lastPlaceholder} defaultValue={lastDefault} readOnly />
                {!field.hideSublabels && <span className="formtura-sublabel">Last Name</span>}
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
    <div className="formtura-field-preview" role="group" aria-labelledby={!field.hideLabel ? fieldId : undefined}>
      {!field.hideLabel && (
        <label id={fieldId} htmlFor={`${fieldId}-input`}>
          {field.label}
          {field.required && <span style={{ color: 'red' }} aria-label="required"> *</span>}
        </label>
      )}
      <div className={getFieldSizeClass()} aria-describedby={descriptionId}>
        {renderField()}
      </div>
      {field.description && (
        <p id={descriptionId} className="formtura-field-description-text">
          {field.description}
        </p>
      )}
    </div>
  );
};

export default FieldPreview;
