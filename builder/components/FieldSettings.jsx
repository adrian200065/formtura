import { Check, GripVertical, Info, List, Minus, Plus, Settings, Tag, Wand2, Zap } from 'lucide-react';
import { useState } from 'react';

const FieldSettings = ({ field, onFieldUpdate }) => {
  const [, setPanelMode] = useState('fields'); // 'fields' or 'options'
  const [activeTab, setActiveTab] = useState('general'); // 'general', 'advanced', 'smart-logic'

  // Switch to Field Options when a field is selected

  if (!field) {
    return (
      <div className="formtura-settings">
        <div className="formtura-settings-header">
          <div className="formtura-panel-toggle">
            <button
              className="formtura-panel-toggle-btn active"
              type="button"
            >
              <List size={16} />
              Add Fields
            </button>
            <button
              className="formtura-panel-toggle-btn"
              type="button"
              disabled
            >
              <Settings size={16} />
              Field Options
            </button>
          </div>
        </div>
        <div className="formtura-settings-content">
          <div className="formtura-settings-empty">
            <Settings size={48} />
            <p>Select a field to edit its settings</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="formtura-settings">
      <div className="formtura-settings-header">
        <div className="formtura-panel-toggle">
          <button
            className="formtura-panel-toggle-btn"
            type="button"
            onClick={() => setPanelMode('fields')}
          >
            <List size={16} />
            Add Fields
          </button>
          <button
            className="formtura-panel-toggle-btn active"
            type="button"
          >
            <Check size={16} />
            Field Options
          </button>
        </div>
      </div>

      <div className="formtura-settings-tabs">
        <button
          className={`formtura-settings-tab ${activeTab === 'general' ? 'active' : ''}`}
          onClick={() => setActiveTab('general')}
          type="button"
        >
          General
        </button>
        <button
          className={`formtura-settings-tab ${activeTab === 'advanced' ? 'active' : ''}`}
          onClick={() => setActiveTab('advanced')}
          type="button"
        >
          Advanced
        </button>
        <button
          className={`formtura-settings-tab ${activeTab === 'smart-logic' ? 'active' : ''}`}
          onClick={() => setActiveTab('smart-logic')}
          type="button"
        >
          Smart Logic
        </button>
      </div>

      <div className="formtura-settings-content">
        {activeTab === 'general' && (
          <GeneralTab field={field} onUpdate={onFieldUpdate} />
        )}
        {activeTab === 'advanced' && (
          <AdvancedTab field={field} onUpdate={onFieldUpdate} />
        )}
        {activeTab === 'smart-logic' && (
          <SmartLogicTab field={field} onUpdate={onFieldUpdate} />
        )}
      </div>
    </div>
  );
};

// General Tab Component
const GeneralTab = ({ field, onUpdate }) => {
  const handleChange = (key, value) => {
    onUpdate(field.id, { [key]: value });
  };

  const hasChoices = ['select', 'radio', 'checkbox'].includes(field.type);

  // Choices management functions
  const handleChoiceChange = (index, value) => {
    const newOptions = [...(field.options || [])];
    newOptions[index] = value;
    handleChange('options', newOptions);
  };

  const handleAddChoice = () => {
    const newOptions = [...(field.options || []), `Option ${(field.options?.length || 0) + 1}`];
    handleChange('options', newOptions);
  };

  const handleRemoveChoice = (index) => {
    const newOptions = field.options.filter((_, i) => i !== index);
    handleChange('options', newOptions);
  };

  const handleBulkAdd = () => {
    const bulkText = prompt('Enter choices (one per line):');
    if (bulkText) {
      const newChoices = bulkText.split('\n').filter(line => line.trim());
      handleChange('options', [...(field.options || []), ...newChoices]);
    }
  };

  return (
    <div className="formtura-field-options">
      <div className="formtura-field-options-title">
        <strong>{field.label}</strong> <span className="formtura-field-id">(ID #{field.id.slice(-4)})</span>
      </div>

      <div className="formtura-form-group">
        <label htmlFor="field-label">
          Label <Info size={14} className="formtura-help-icon" />
        </label>
        <input
          id="field-label"
          type="text"
          value={field.label}
          onChange={(e) => handleChange('label', e.target.value)}
        />
      </div>

      {/* Name Field Format */}
      {field.type === 'name' && (
        <div className="formtura-form-group">
          <label htmlFor="field-format">
            Format <Info size={14} className="formtura-help-icon" />
          </label>
          <select
            id="field-format"
            value={field.format || 'first-last'}
            onChange={(e) => handleChange('format', e.target.value)}
          >
            <option value="first-last">First Last</option>
            <option value="first-middle-last">First Middle Last</option>
            <option value="simple">Simple</option>
          </select>
        </div>
      )}

      {hasChoices && (
        <div className="formtura-form-group">
          <div className="formtura-choices-header">
            <label>
              Choices <Info size={14} className="formtura-help-icon" />
            </label>
            <button
              className="formtura-btn-link formtura-bulk-add"
              type="button"
              onClick={handleBulkAdd}
            >
              <Plus size={14} /> Bulk Add
            </button>
          </div>

          <div className="formtura-choices-list">
            {(field.options || []).map((option, index) => (
              <div key={index} className="formtura-choice-item">
                <div className="formtura-choice-radio">
                  <input
                    type="radio"
                    name={`choice-${field.id}`}
                    disabled
                    checked={false}
                  />
                </div>
                <div className="formtura-choice-drag">
                  <GripVertical size={16} className="formtura-drag-handle" />
                </div>
                <input
                  type="text"
                  className="formtura-choice-input"
                  value={option}
                  onChange={(e) => handleChoiceChange(index, e.target.value)}
                  placeholder="Enter choice"
                />
                <button
                  className="formtura-choice-btn formtura-choice-btn-add"
                  type="button"
                  onClick={handleAddChoice}
                  title="Add choice"
                >
                  <Plus size={16} />
                </button>
                <button
                  className="formtura-choice-btn formtura-choice-btn-remove"
                  type="button"
                  onClick={() => handleRemoveChoice(index)}
                  disabled={field.options.length <= 1}
                  title="Remove choice"
                >
                  <Minus size={16} />
                </button>
              </div>
            ))}
          </div>

          <button
            className="formtura-btn-secondary formtura-generate-choices"
            type="button"
          >
            <Wand2 size={14} /> Generate Choices
          </button>

          <div className="formtura-form-group">
            <div className="formtura-toggle-group">
              <label className="formtura-toggle">
                <input
                  type="checkbox"
                  checked={field.addOtherChoice || false}
                  onChange={(e) => handleChange('addOtherChoice', e.target.checked)}
                />
                <span className="formtura-toggle-slider"></span>
              </label>
              <span className="formtura-toggle-label">
                Add Other Choice <Info size={14} className="formtura-help-icon" />
              </span>
            </div>
          </div>

          <div className="formtura-form-group">
            <div className="formtura-toggle-group">
              <label className="formtura-toggle">
                <input
                  type="checkbox"
                  checked={field.useImageChoices || false}
                  onChange={(e) => handleChange('useImageChoices', e.target.checked)}
                />
                <span className="formtura-toggle-slider"></span>
              </label>
              <span className="formtura-toggle-label">
                Use Image Choices <Info size={14} className="formtura-help-icon" />
              </span>
            </div>
          </div>

          <div className="formtura-form-group">
            <div className="formtura-toggle-group">
              <label className="formtura-toggle">
                <input
                  type="checkbox"
                  checked={field.useIconChoices || false}
                  onChange={(e) => handleChange('useIconChoices', e.target.checked)}
                />
                <span className="formtura-toggle-slider"></span>
              </label>
              <span className="formtura-toggle-label">
                Use Icon Choices <Info size={14} className="formtura-help-icon" />
              </span>
            </div>
          </div>
        </div>
      )}

      <div className="formtura-form-group">
        <label htmlFor="field-description">
          Description <Info size={14} className="formtura-help-icon" />
        </label>
        <textarea
          id="field-description"
          value={field.description || ''}
          onChange={(e) => handleChange('description', e.target.value)}
          rows={4}
        />
      </div>

      {/* Number Slider Fields - After Description */}
      {field.type === 'number-slider' && (
        <>
          <div className="formtura-form-group">
            <label>
              Value Range <Info size={14} className="formtura-help-icon" />
            </label>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
              <div>
                <input
                  type="number"
                  value={field.minValue !== undefined ? field.minValue : 0}
                  onChange={(e) => handleChange('minValue', parseInt(e.target.value) || 0)}
                  placeholder="0"
                />
                <span className="formtura-field-help">Minimum</span>
              </div>
              <div>
                <input
                  type="number"
                  value={field.maxValue !== undefined ? field.maxValue : 10}
                  onChange={(e) => handleChange('maxValue', parseInt(e.target.value) || 10)}
                  placeholder="10"
                />
                <span className="formtura-field-help">Maximum</span>
              </div>
            </div>
          </div>

          <div className="formtura-form-group">
            <label htmlFor="field-default-value">
              Default Value <Info size={14} className="formtura-help-icon" />
            </label>
            <input
              id="field-default-value"
              type="number"
              value={field.defaultValue !== undefined ? field.defaultValue : 0}
              onChange={(e) => handleChange('defaultValue', parseInt(e.target.value) || 0)}
              placeholder="0"
            />
          </div>

          <div className="formtura-form-group">
            <label htmlFor="field-increment">
              Increment <Info size={14} className="formtura-help-icon" />
            </label>
            <input
              id="field-increment"
              type="number"
              value={field.increment !== undefined ? field.increment : 1}
              onChange={(e) => handleChange('increment', parseInt(e.target.value) || 1)}
              placeholder="1"
              min="1"
            />
          </div>
        </>
      )}

      {/* Repeater Field - Collapsible and Repeat Layout */}
      {field.type === 'repeater' && (
        <>
          <div className="formtura-form-group">
            <div className="formtura-toggle-group">
              <label className="formtura-toggle">
                <input
                  type="checkbox"
                  checked={field.collapsible || false}
                  onChange={(e) => handleChange('collapsible', e.target.checked)}
                />
                <span className="formtura-toggle-slider"></span>
              </label>
              <span className="formtura-toggle-label">
                Collapsible <Info size={14} className="formtura-help-icon" title="Collapsible: This section will slide open and closed." />
              </span>
            </div>
          </div>

          <div className="formtura-form-group">
            <label htmlFor="field-repeat-layout">
              Repeat Layout <Info size={14} className="formtura-help-icon" />
            </label>
            <select
              id="field-repeat-layout"
              value={field.repeatLayout || 'default'}
              onChange={(e) => handleChange('repeatLayout', e.target.value)}
            >
              <option value="default">Default</option>
              <option value="inline">Inline</option>
              <option value="grid">Grid</option>
            </select>
            <span className="formtura-field-help">
              {field.repeatLayout === 'default' && 'No automatic formatting'}
              {field.repeatLayout === 'inline' && 'Display each field and label in one row'}
              {field.repeatLayout === 'grid' && 'Display labels as headings above rows of fields'}
            </span>
          </div>
        </>
      )}

      {/* Required toggle - not shown for number-slider or repeater */}
      {field.type !== 'number-slider' && field.type !== 'repeater' && (
        <div className="formtura-form-group">
          <div className="formtura-toggle-group">
            <label className="formtura-toggle">
              <input
                type="checkbox"
                checked={field.required || false}
                onChange={(e) => handleChange('required', e.target.checked)}
              />
              <span className="formtura-toggle-slider"></span>
            </label>
            <span className="formtura-toggle-label">
              Required <Info size={14} className="formtura-help-icon" />
            </span>
          </div>
        </div>
      )}
    </div>
  );
};

// Advanced Tab Component
const AdvancedTab = ({ field, onUpdate }) => {
  const handleChange = (key, value) => {
    onUpdate(field.id, { [key]: value });
  };

  // Number Slider has different Advanced options
  if (field.type === 'number-slider') {
    return (
      <div className="formtura-field-options">
        <div className="formtura-field-options-title">
          <strong>{field.label}</strong> <span className="formtura-field-id">(ID #{field.id.slice(-4)})</span>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-size">
            Field Size <Info size={14} className="formtura-help-icon" />
          </label>
          <select
            id="field-size"
            value={field.fieldSize || 'medium'}
            onChange={(e) => handleChange('fieldSize', e.target.value)}
          >
            <option value="small">Small</option>
            <option value="medium">Medium</option>
            <option value="large">Large</option>
          </select>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-value-display">
            Value Display <Info size={14} className="formtura-help-icon" />
          </label>
          <input
            id="field-value-display"
            type="text"
            value={field.valueDisplay || 'Selected Value: {value}'}
            onChange={(e) => handleChange('valueDisplay', e.target.value)}
            placeholder="Selected Value: {value}"
          />
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-css-classes">
            CSS Classes <Info size={14} className="formtura-help-icon" />
          </label>
          <input
            id="field-css-classes"
            type="text"
            value={field.cssClasses || ''}
            onChange={(e) => handleChange('cssClasses', e.target.value)}
          />
          <button className="formtura-btn-link" type="button">
            <Tag size={14} /> Show Layouts
          </button>
        </div>

        <div className="formtura-form-group">
          <div className="formtura-toggle-group">
            <label className="formtura-toggle">
              <input
                type="checkbox"
                checked={field.hideLabel || false}
                onChange={(e) => handleChange('hideLabel', e.target.checked)}
              />
              <span className="formtura-toggle-slider"></span>
            </label>
            <span className="formtura-toggle-label">
              Hide Label <Info size={14} className="formtura-help-icon" />
            </span>
          </div>
        </div>

        <div className="formtura-form-group">
          <div className="formtura-toggle-group">
            <label className="formtura-toggle">
              <input
                type="checkbox"
                checked={field.readOnly || false}
                onChange={(e) => handleChange('readOnly', e.target.checked)}
              />
              <span className="formtura-toggle-slider"></span>
            </label>
            <span className="formtura-toggle-label">
              Read-Only <Info size={14} className="formtura-help-icon" />
            </span>
          </div>
        </div>
      </div>
    );
  }

  // Repeater field has specific Advanced options
  if (field.type === 'repeater') {
    return (
      <div className="formtura-field-options">
        <div className="formtura-field-options-title">
          <strong>{field.label}</strong> <span className="formtura-field-id">(ID #{field.id.slice(-4)})</span>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-size">
            Field Size <Info size={14} className="formtura-help-icon" />
          </label>
          <select
            id="field-size"
            value={field.fieldSize || 'medium'}
            onChange={(e) => handleChange('fieldSize', e.target.value)}
          >
            <option value="small">Small</option>
            <option value="medium">Medium</option>
            <option value="large">Large</option>
          </select>
        </div>

        <div className="formtura-form-group formtura-form-group-row">
          <div className="formtura-form-group-col">
            <label htmlFor="field-add-label">
              Add New Label <Info size={14} className="formtura-help-icon" />
            </label>
            <input
              id="field-add-label"
              type="text"
              value={field.addNewLabel || 'Add'}
              onChange={(e) => handleChange('addNewLabel', e.target.value)}
              placeholder="Add"
            />
          </div>
          <div className="formtura-form-group-col">
            <label htmlFor="field-remove-label">
              Remove Label <Info size={14} className="formtura-help-icon" />
            </label>
            <input
              id="field-remove-label"
              type="text"
              value={field.removeLabel || 'Remove'}
              onChange={(e) => handleChange('removeLabel', e.target.value)}
              placeholder="Remove"
            />
          </div>
        </div>

        <div className="formtura-form-group formtura-form-group-row">
          <div className="formtura-form-group-col">
            <label htmlFor="field-min-rows">
              Min Repeater Rows <Info size={14} className="formtura-help-icon" />
            </label>
            <input
              id="field-min-rows"
              type="number"
              value={field.minRows || ''}
              onChange={(e) => handleChange('minRows', e.target.value)}
              placeholder=""
              min="0"
            />
          </div>
          <div className="formtura-form-group-col">
            <label htmlFor="field-max-rows">
              Max Repeater Rows <Info size={14} className="formtura-help-icon" title="The maximum number of times the end user is allowed to duplicate this section of fields in one entry." />
            </label>
            <input
              id="field-max-rows"
              type="number"
              value={field.maxRows || ''}
              onChange={(e) => handleChange('maxRows', e.target.value)}
              placeholder=""
              min="1"
            />
          </div>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-css-classes">
            CSS Classes <Info size={14} className="formtura-help-icon" />
          </label>
          <input
            id="field-css-classes"
            type="text"
            value={field.cssClasses || ''}
            onChange={(e) => handleChange('cssClasses', e.target.value)}
          />
          <button className="formtura-btn-link" type="button">
            <Tag size={14} /> Show Layouts
          </button>
        </div>

        <div className="formtura-form-group">
          <div className="formtura-toggle-group">
            <label className="formtura-toggle">
              <input
                type="checkbox"
                checked={field.hideLabel || false}
                onChange={(e) => handleChange('hideLabel', e.target.checked)}
              />
              <span className="formtura-toggle-slider"></span>
            </label>
            <span className="formtura-toggle-label">
              Hide Label <Info size={14} className="formtura-help-icon" />
            </span>
          </div>
        </div>
      </div>
    );
  }

  // Default Advanced Tab for other field types
  return (
    <div className="formtura-field-options">
      <div className="formtura-field-options-title">
        <strong>{field.label}</strong> <span className="formtura-field-id">(ID #{field.id.slice(-4)})</span>
      </div>

      <div className="formtura-form-group">
        <label htmlFor="field-size">
          Field Size <Info size={14} className="formtura-help-icon" />
        </label>
        <select
          id="field-size"
          value={field.fieldSize || 'medium'}
          onChange={(e) => handleChange('fieldSize', e.target.value)}
        >
          <option value="small">Small</option>
          <option value="medium">Medium</option>
          <option value="large">Large</option>
        </select>
      </div>

      <div className="formtura-form-group">
        <label htmlFor="field-placeholder">
          Placeholder Text <Info size={14} className="formtura-help-icon" />
        </label>
        <input
          id="field-placeholder"
          type="text"
          value={field.placeholder || ''}
          onChange={(e) => handleChange('placeholder', e.target.value)}
        />
      </div>

      <div className="formtura-form-group">
        <div className="formtura-toggle-group">
          <label className="formtura-toggle">
            <input
              type="checkbox"
              checked={field.limitLength || false}
              onChange={(e) => handleChange('limitLength', e.target.checked)}
            />
            <span className="formtura-toggle-slider"></span>
          </label>
          <span className="formtura-toggle-label">
            Limit Length <Info size={14} className="formtura-help-icon" />
          </span>
        </div>
      </div>

      <div className="formtura-form-group">
        <label htmlFor="field-default-value">
          Default Value <Info size={14} className="formtura-help-icon" />
        </label>
        <input
          id="field-default-value"
          type="text"
          value={field.defaultValue || ''}
          onChange={(e) => handleChange('defaultValue', e.target.value)}
        />
      </div>

      <div className="formtura-form-group">
        <label htmlFor="field-input-mask">
          Input Mask <Info size={14} className="formtura-help-icon" />
        </label>
        <input
          id="field-input-mask"
          type="text"
          value={field.inputMask || ''}
          onChange={(e) => handleChange('inputMask', e.target.value)}
        />
        <span className="formtura-field-help">See Examples & Docs</span>
      </div>

      <div className="formtura-form-group">
        <label htmlFor="field-css-classes">
          CSS Classes <Info size={14} className="formtura-help-icon" />
        </label>
        <input
          id="field-css-classes"
          type="text"
          value={field.cssClasses || ''}
          onChange={(e) => handleChange('cssClasses', e.target.value)}
        />
        <button className="formtura-btn-link" type="button">
          <Tag size={14} /> Show Layouts
        </button>
      </div>

      <div className="formtura-form-group">
        <div className="formtura-toggle-group">
          <label className="formtura-toggle">
            <input
              type="checkbox"
              checked={field.hideLabel || false}
              onChange={(e) => handleChange('hideLabel', e.target.checked)}
            />
            <span className="formtura-toggle-slider"></span>
          </label>
          <span className="formtura-toggle-label">
            Hide Label <Info size={14} className="formtura-help-icon" />
          </span>
        </div>
      </div>

      <div className="formtura-form-group">
        <div className="formtura-toggle-group">
          <label className="formtura-toggle">
            <input
              type="checkbox"
              checked={field.readOnly || false}
              onChange={(e) => handleChange('readOnly', e.target.checked)}
            />
            <span className="formtura-toggle-slider"></span>
          </label>
          <span className="formtura-toggle-label">
            Read-Only <Info size={14} className="formtura-help-icon" />
          </span>
        </div>
      </div>

      <div className="formtura-form-group">
        <div className="formtura-toggle-group">
          <label className="formtura-toggle">
            <input
              type="checkbox"
              checked={field.enableAutocomplete || false}
              onChange={(e) => handleChange('enableAutocomplete', e.target.checked)}
            />
            <span className="formtura-toggle-slider"></span>
          </label>
          <span className="formtura-toggle-label">
            Enable Address Autocomplete <span className="formtura-badge">PRO</span>
          </span>
        </div>
      </div>

      <div className="formtura-form-group">
        <div className="formtura-toggle-group">
          <label className="formtura-toggle">
            <input
              type="checkbox"
              checked={field.enableCalculation || false}
              onChange={(e) => handleChange('enableCalculation', e.target.checked)}
            />
            <span className="formtura-toggle-slider"></span>
          </label>
          <span className="formtura-toggle-label">
            Enable Calculation <Info size={14} className="formtura-help-icon" title="Enable mathematical calculations using values from other form fields." />
          </span>
        </div>
      </div>

      {field.enableCalculation && (
        <div className="formtura-form-group">
          <label htmlFor="field-calculation-formula">
            Calculation Formula
          </label>
          <textarea
            id="field-calculation-formula"
            value={field.calculationFormula || ''}
            onChange={(e) => handleChange('calculationFormula', e.target.value)}
            placeholder="e.g., {field_1} + {field_2} * 0.1"
            rows={3}
            className="formtura-textarea"
          />
          <p className="formtura-field-description">
            Use {'{field_ID}'} to reference other number fields. Supported operators: +, -, *, /, ()
          </p>
        </div>
      )}
    </div>
  );
};

// Smart Logic Tab Component
const SmartLogicTab = ({ field, onUpdate }) => {
  const handleChange = (key, value) => {
    onUpdate(field.id, { [key]: value });
  };

  return (
    <div className="formtura-field-options">
      <div className="formtura-field-options-title">
        <strong>{field.label}</strong> <span className="formtura-field-id">(ID #{field.id.slice(-4)})</span>
      </div>

      <div className="formtura-settings-group">
        <div className="formtura-settings-group-title">
          <Zap size={16} /> Conditional Logic
        </div>

        <div className="formtura-form-group">
          <div className="formtura-toggle-group">
            <label className="formtura-toggle">
              <input
                type="checkbox"
                checked={field.conditionalLogic?.enabled || false}
                onChange={(e) => handleChange('conditionalLogic', {
                  ...field.conditionalLogic,
                  enabled: e.target.checked
                })}
              />
              <span className="formtura-toggle-slider"></span>
            </label>
            <span className="formtura-toggle-label">
              Enable Conditional Logic <Info size={14} className="formtura-help-icon" />
            </span>
          </div>
          <p className="formtura-field-description">
            Show or hide this field based on other field values
          </p>
        </div>

        {field.conditionalLogic?.enabled && (
          <div className="formtura-logic-rules">
            <div className="formtura-form-group">
              <label>Show this field if:</label>
              <select
                value={field.conditionalLogic?.action || 'show'}
                onChange={(e) => handleChange('conditionalLogic', {
                  ...field.conditionalLogic,
                  action: e.target.value
                })}
              >
                <option value="show">Show</option>
                <option value="hide">Hide</option>
              </select>
            </div>

            <div className="formtura-form-group">
              <label>When the following match:</label>
              <select
                value={field.conditionalLogic?.match || 'all'}
                onChange={(e) => handleChange('conditionalLogic', {
                  ...field.conditionalLogic,
                  match: e.target.value
                })}
              >
                <option value="all">All conditions</option>
                <option value="any">Any condition</option>
              </select>
            </div>

            <button className="formtura-btn" type="button">
              + Add Condition
            </button>
          </div>
        )}
      </div>

      <div className="formtura-settings-group">
        <div className="formtura-settings-group-title">
          <Zap size={16} /> Field Behavior
        </div>

        <div className="formtura-form-group">
          <div className="formtura-toggle-group">
            <label className="formtura-toggle">
              <input
                type="checkbox"
                checked={field.dynamicDefault || false}
                onChange={(e) => handleChange('dynamicDefault', e.target.checked)}
              />
              <span className="formtura-toggle-slider"></span>
            </label>
            <span className="formtura-toggle-label">
              Dynamic Default Value <Info size={14} className="formtura-help-icon" />
            </span>
          </div>
          <p className="formtura-field-description">
            Auto-populate this field based on another field's value
          </p>
        </div>

        <div className="formtura-form-group">
          <div className="formtura-toggle-group">
            <label className="formtura-toggle">
              <input
                type="checkbox"
                checked={field.enableDisable || false}
                onChange={(e) => handleChange('enableDisable', e.target.checked)}
              />
              <span className="formtura-toggle-slider"></span>
            </label>
            <span className="formtura-toggle-label">
              Enable/Disable Based on Logic <Info size={14} className="formtura-help-icon" />
            </span>
          </div>
          <p className="formtura-field-description">
            Make field interactive or read-only based on conditions
          </p>
        </div>
      </div>

      <div className="formtura-settings-group">
        <div className="formtura-settings-group-title">
          <Zap size={16} /> Validation Rules
        </div>

        <div className="formtura-form-group">
          <div className="formtura-toggle-group">
            <label className="formtura-toggle">
              <input
                type="checkbox"
                checked={field.customValidation || false}
                onChange={(e) => handleChange('customValidation', e.target.checked)}
              />
              <span className="formtura-toggle-slider"></span>
            </label>
            <span className="formtura-toggle-label">
              Custom Validation Rules <Info size={14} className="formtura-help-icon" />
            </span>
          </div>
          <p className="formtura-field-description">
            Apply dynamic validation based on other field values
          </p>
        </div>

        {field.customValidation && (
          <div className="formtura-form-group">
            <label htmlFor="validation-rule">Validation Expression</label>
            <textarea
              id="validation-rule"
              value={field.validationRule || ''}
              onChange={(e) => handleChange('validationRule', e.target.value)}
              rows={3}
              placeholder="e.g., age >= 18"
            />
          </div>
        )}
      </div>

      <div className="formtura-settings-group">
        <div className="formtura-settings-group-title">
          <Zap size={16} /> Page Navigation
        </div>

        <div className="formtura-form-group">
          <div className="formtura-toggle-group">
            <label className="formtura-toggle">
              <input
                type="checkbox"
                checked={field.branchingLogic || false}
                onChange={(e) => handleChange('branchingLogic', e.target.checked)}
              />
              <span className="formtura-toggle-slider"></span>
            </label>
            <span className="formtura-toggle-label">
              Branching/Skip Logic <Info size={14} className="formtura-help-icon" />
            </span>
          </div>
          <p className="formtura-field-description">
            Redirect users to different pages based on their answer
          </p>
        </div>

        {field.branchingLogic && (
          <div className="formtura-form-group">
            <label htmlFor="branch-target">Go to Page</label>
            <select
              id="branch-target"
              value={field.branchTarget || ''}
              onChange={(e) => handleChange('branchTarget', e.target.value)}
            >
              <option value="">Select page...</option>
              <option value="page-2">Page 2</option>
              <option value="page-3">Page 3</option>
              <option value="submit">Submit Form</option>
            </select>
          </div>
        )}
      </div>
    </div>
  );
};


export default FieldSettings;
