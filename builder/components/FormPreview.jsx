import { X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

// DateTimePicker Component
const DateTimePicker = ({ field, fieldSizeClass }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [selectedDate, setSelectedDate] = useState(null);
  const [currentMonth, setCurrentMonth] = useState(new Date().getMonth());
  const [currentYear, setCurrentYear] = useState(new Date().getFullYear());
  const containerRef = useRef(null);

  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const monthsFull = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

  // Calculate year range from field settings
  const currentYearNum = new Date().getFullYear();
  const startYear = field.yearRangeStart?.startsWith('+') || field.yearRangeStart?.startsWith('-')
    ? currentYearNum + parseInt(field.yearRangeStart || '-10')
    : parseInt(field.yearRangeStart) || currentYearNum - 10;
  const endYear = field.yearRangeEnd?.startsWith('+') || field.yearRangeEnd?.startsWith('-')
    ? currentYearNum + parseInt(field.yearRangeEnd || '+10')
    : parseInt(field.yearRangeEnd) || currentYearNum + 10;

  const years = [];
  for (let y = startYear; y <= endYear; y++) {
    years.push(y);
  }

  // Close calendar when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (containerRef.current && !containerRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const getDaysInMonth = (month, year) => {
    return new Date(year, month + 1, 0).getDate();
  };

  const getFirstDayOfMonth = (month, year) => {
    const day = new Date(year, month, 1).getDay();
    return day === 0 ? 6 : day - 1; // Adjust for Monday start
  };

  const handleDateSelect = (day) => {
    const date = new Date(currentYear, currentMonth, day);
    setSelectedDate(date);
    setIsOpen(false);
  };

  const formatDate = (date) => {
    if (!date) return '';
    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear();
    return `${month}/${day}/${year}`;
  };

  const prevMonth = () => {
    if (currentMonth === 0) {
      setCurrentMonth(11);
      setCurrentYear(currentYear - 1);
    } else {
      setCurrentMonth(currentMonth - 1);
    }
  };

  const nextMonth = () => {
    if (currentMonth === 11) {
      setCurrentMonth(0);
      setCurrentYear(currentYear + 1);
    } else {
      setCurrentMonth(currentMonth + 1);
    }
  };

  const renderCalendarDays = () => {
    const daysInMonth = getDaysInMonth(currentMonth, currentYear);
    const firstDay = getFirstDayOfMonth(currentMonth, currentYear);
    const days = [];
    const today = new Date();

    // Empty cells for days before the first day of month
    for (let i = 0; i < firstDay; i++) {
      days.push(<span key={`empty-${i}`}></span>);
    }

    // Days of the month
    for (let day = 1; day <= daysInMonth; day++) {
      const isToday = day === today.getDate() &&
                      currentMonth === today.getMonth() &&
                      currentYear === today.getFullYear();
      const isSelected = selectedDate &&
                         day === selectedDate.getDate() &&
                         currentMonth === selectedDate.getMonth() &&
                         currentYear === selectedDate.getFullYear();

      days.push(
        <span
          key={day}
          className={`formtura-calendar-day ${isToday ? 'formtura-calendar-today' : ''} ${isSelected ? 'formtura-calendar-selected' : ''}`}
          onClick={() => handleDateSelect(day)}
        >
          {day}
        </span>
      );
    }

    return days;
  };

  return (
    <div className="formtura-datetime-preview" ref={containerRef}>
      <input
        type="text"
        placeholder=""
        required={field.required}
        className={`formtura-datetime-input ${fieldSizeClass}`}
        value={formatDate(selectedDate)}
        onFocus={() => setIsOpen(true)}
        readOnly
      />
      {isOpen && (
        <div className="formtura-calendar-preview">
          <div className="formtura-calendar-header">
            <button type="button" className="formtura-calendar-nav" onClick={prevMonth}>&lt;</button>
            <select
              className="formtura-calendar-month"
              value={currentMonth}
              onChange={(e) => setCurrentMonth(parseInt(e.target.value))}
            >
              {months.map((month, index) => (
                <option key={month} value={index}>{month}</option>
              ))}
            </select>
            <select
              className="formtura-calendar-year"
              value={currentYear}
              onChange={(e) => setCurrentYear(parseInt(e.target.value))}
            >
              {years.map((year) => (
                <option key={year} value={year}>{year}</option>
              ))}
            </select>
            <button type="button" className="formtura-calendar-nav" onClick={nextMonth}>&gt;</button>
          </div>
          <div className="formtura-calendar-weekdays">
            <span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span><span>Su</span>
          </div>
          <div className="formtura-calendar-days">
            {renderCalendarDays()}
          </div>
        </div>
      )}
    </div>
  );
};

// Interactive Star Rating Component
const StarRatingPreview = ({ field }) => {
  const [hoverRating, setHoverRating] = useState(0);
  const [selectedRating, setSelectedRating] = useState(0);
  const maxStars = field.maxRating || 5;

  return (
    <div className="formtura-star-rating formtura-star-rating-interactive">
      {[...Array(maxStars)].map((_, index) => {
        const starValue = index + 1;
        const isFilled = starValue <= (hoverRating || selectedRating);
        return (
          <span
            key={index}
            className={`formtura-star ${isFilled ? 'formtura-star-filled' : 'formtura-star-empty'}`}
            onMouseEnter={() => setHoverRating(starValue)}
            onMouseLeave={() => setHoverRating(0)}
            onClick={() => setSelectedRating(starValue)}
            style={{ cursor: 'pointer' }}
          >
            ★
          </span>
        );
      })}
    </div>
  );
};

// Interactive Repeater Component
const RepeaterPreview = ({ field }) => {
  const [rows, setRows] = useState([{ id: 1 }]);
  const addLabel = field.addNewLabel || 'Add';
  const removeLabel = field.removeLabel || 'Remove';

  const addRow = () => {
    setRows([...rows, { id: Date.now() }]);
  };

  const removeRow = (id) => {
    if (rows.length > 1) {
      setRows(rows.filter(row => row.id !== id));
    }
  };

  return (
    <div className="formtura-repeater-preview">
      {rows.map((row, index) => (
        <div key={row.id} className="formtura-repeater-row">
          <div className="formtura-repeater-row-content">
            <span className="formtura-repeater-row-placeholder">Repeater row {index + 1}</span>
          </div>
          <button
            type="button"
            className="formtura-repeater-remove-btn"
            onClick={() => removeRow(row.id)}
            disabled={rows.length === 1}
          >
            <span>−</span> {removeLabel}
          </button>
        </div>
      ))}
      <button type="button" className="formtura-repeater-add-btn" onClick={addRow}>
        <span>+</span> {addLabel}
      </button>
    </div>
  );
};

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

        case 'datetime':
          return <DateTimePicker field={field} fieldSizeClass={fieldSizeClass} />;

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

        case 'rating':
          return <StarRatingPreview field={field} />;

        case 'repeater':
          return <RepeaterPreview field={field} />;

        case 'rich-text':
          return (
            <div className="formtura-richtext-editor-preview">
              <div className="formtura-richtext-toolbar-preview">
                <div className="formtura-richtext-buttons-preview">
                  <button type="button">b</button>
                  <button type="button"><em>i</em></button>
                  <button type="button"><u>link</u></button>
                  <button type="button">b-quote</button>
                  <button type="button">del</button>
                  <button type="button">ins</button>
                  <button type="button">img</button>
                  <button type="button">ul</button>
                  <button type="button">ol</button>
                  <button type="button">li</button>
                  <button type="button">code</button>
                  <button type="button">more</button>
                  <button type="button">close tags</button>
                </div>
                <div className="formtura-richtext-tabs-preview">
                  <button type="button" className="active">Visual</button>
                  <button type="button">Code</button>
                </div>
              </div>
              <div
                className="formtura-richtext-content-preview"
                contentEditable
                style={{ minHeight: `${(field.rows || 7) * 1.5}rem` }}
                dangerouslySetInnerHTML={{ __html: field.content || '' }}
              ></div>
            </div>
          );

        case 'file-upload':
          const previewUploadText = field.uploadText || 'Drop a file here or click to upload';
          const previewMaxFileSize = field.maxFileSize || '516';

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
                <div className="formtura-file-upload-text">{previewUploadText}</div>
                <div className="formtura-file-upload-size">Maximum file size: {previewMaxFileSize}MB</div>
              </div>
            </div>
          );

        case 'html':
          return (
            <div
              className="formtura-html-content"
              dangerouslySetInnerHTML={{ __html: field.description || field.content || '' }}
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
