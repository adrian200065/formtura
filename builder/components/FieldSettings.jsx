import { Check, Info, List, Settings, Tag, Zap } from 'lucide-react';
import { useState } from 'react';

const FieldSettings = ({ field, formSettings, onFieldUpdate, onFormSettingsUpdate }) => {
  const [panelMode, setPanelMode] = useState('fields'); // 'fields' or 'options'
  const [activeTab, setActiveTab] = useState('general'); // 'general', 'advanced', 'smart-logic'

  // Switch to Field Options when a field is selected
  const effectivePanelMode = field ? 'options' : panelMode;

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
    </div>
  );
};

// Advanced Tab Component
const AdvancedTab = ({ field, onUpdate }) => {
  const handleChange = (key, value) => {
    onUpdate(field.id, { [key]: value });
  };

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
            Enable Calculation <span className="formtura-badge">PRO</span>
          </span>
        </div>
      </div>
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
