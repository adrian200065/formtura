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
              className="formtura-select-multiple"
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

      case 'radio': {
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

        return (
          <div className={`formtura-radio-group ${layoutClass}`}>
            {radioChoices.map((choice, index) => (
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
      }

      // `checkboxes` is the pre-1.0.3 slug for this field.
      case 'checkbox':
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

      case 'datetime':
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
            />
            <div className="formtura-slider-value">{displayText}</div>
          </div>
        );

      case 'total':
        if (field.enableSummary) {
          // Show order summary table
          return (
            <table className="formtura-order-summary">
              <thead>
                <tr>
                  <th scope="col">Item</th>
                  <th scope="col">Quantity</th>
                  <th scope="col">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Example Product 1</td>
                  <td>3</td>
                  <td>$30.00</td>
                </tr>
                <tr>
                  <td>Example Product 2</td>
                  <td>2</td>
                  <td>$20.00</td>
                </tr>
                <tr>
                  <td>Example Product 3</td>
                  <td>1</td>
                  <td>$10.00</td>
                </tr>
                <tr className="formtura-order-summary-total">
                  <th scope="row">Total</th>
                  <td />
                  <td>$60.00</td>
                </tr>
              </tbody>
            </table>
          );
        }
        // Default total display (without summary)
        return (
          <div className="formtura-total-display">
            <span>Total</span>
            <span>$0.00</span>
          </div>
        );

      case 'file-upload':
        const uploadText = field.uploadText || 'Drop a file here or click to upload';
        const maxFileSizeDisplay = field.maxFileSize || '516';

        return (
          <div className="formtura-file-upload-preview">
            <div className="formtura-file-upload-dropzone">
              <div className="formtura-file-upload-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                  <polyline points="17 8 12 3 7 8" />
                  <line x1="12" y1="3" x2="12" y2="15" />
                </svg>
              </div>
              <div className="formtura-file-upload-text">{uploadText}</div>
              <div className="formtura-file-upload-size">Maximum file size: {maxFileSizeDisplay}MB</div>
            </div>
          </div>
        );

      case 'repeater':
        const addLabel = field.addNewLabel || 'Add';
        const removeLabel = field.removeLabel || 'Remove';

        return (
          <div className="formtura-repeater-container">
            <div className="formtura-repeater-controls">
              <button
                type="button"
                className="formtura-repeater-btn formtura-repeater-btn-add"
                onClick={(e) => e.stopPropagation()}
              >
                <span className="formtura-repeater-btn-icon">+</span> {addLabel}
              </button>
              <button
                type="button"
                className="formtura-repeater-btn formtura-repeater-btn-remove"
                onClick={(e) => e.stopPropagation()}
              >
                <span className="formtura-repeater-btn-icon">−</span> {removeLabel}
              </button>
            </div>
            <div className="formtura-repeater-dropzone">
              <span className="formtura-repeater-dropzone-text">Add Fields Here</span>
            </div>
          </div>
        );

      case 'rating':
        const maxStars = field.maxRating || 5;
        const stars = [];
        for (let i = 0; i < maxStars; i++) {
          stars.push(
            <span key={i} className="formtura-star formtura-star-empty">★</span>
          );
        }
        return (
          <div className="formtura-star-rating">
            {stars}
          </div>
        );

      case 'rich-text':
        // Renders as a plain textarea on the frontend, so the preview must
        // not advertise an editor toolbar the visitor never gets.
        return (
          <textarea
            rows={field.rows || 7}
            placeholder={field.placeholder}
            readOnly
          />
        );

      case 'address':
        return (
          <div className="formtura-address-preview">
            <input type="text" placeholder="Address Line 1" readOnly />
            <input type="text" placeholder="Address Line 2" readOnly />
            <div className="formtura-address-preview-row">
              <input type="text" placeholder="City" readOnly />
              <input type="text" placeholder={field.scheme === 'international' ? 'State / Province / Region' : 'State'} readOnly />
            </div>
            <div className="formtura-address-preview-row">
              <input type="text" placeholder={field.scheme === 'international' ? 'Postal Code' : 'ZIP Code'} readOnly />
              <input type="text" placeholder="Country" readOnly />
            </div>
          </div>
        );

      case 'camera':
        return (
          <div className="formtura-file-upload-preview">
            <div className="formtura-file-upload-dropzone">
              <div className="formtura-file-upload-text">Take a photo or choose an image</div>
              <div className="formtura-file-upload-size">Images only</div>
            </div>
          </div>
        );

      case 'signature':
        return (
          <div className="formtura-signature-preview">
            <div className="formtura-signature-preview-canvas">✕ Sign here</div>
            <button type="button" onClick={(e) => e.stopPropagation()}>Clear</button>
          </div>
        );

      case 'payment-single':
        return (
          <div className="formtura-total-display">
            <span>{field.label || 'Single Item'}</span>
            <span>${parseFloat(field.price || 0).toFixed(2)}</span>
          </div>
        );

      case 'payment-checkbox':
      case 'payment-multiple':
        return (
          <div className="formtura-choices-preview">
            {(field.items || []).map((item, i) => (
              <div key={i} className="formtura-choice-preview-item">
                <input type={field.type === 'payment-checkbox' ? 'checkbox' : 'radio'} readOnly disabled />
                <span>
                  {item.label}
                  {field.showPriceAfterLabels !== false && ` — $${parseFloat(item.price || 0).toFixed(2)}`}
                </span>
              </div>
            ))}
          </div>
        );

      case 'payment-dropdown':
        return (
          <select disabled>
            {(field.items || []).map((item, i) => (
              <option key={i}>
                {item.label}
                {field.showPriceAfterLabels !== false && ` — $${parseFloat(item.price || 0).toFixed(2)}`}
              </option>
            ))}
          </select>
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
          {field.required && <span className="formtura-required" aria-label="required"> *</span>}
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
