import { useDraggable } from '@dnd-kit/core';
import {
    Calendar,
    Camera,
    CheckSquare,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Circle,
    Code,
    CreditCard,
    DollarSign,
    Eye,
    FileText,
    Globe,
    Hash,
    Info,
    Layout,
    List,
    Lock,
    Mail,
    MapPin,
    MessageSquare,
    Minus,
    PenTool,
    Phone,
    Settings,
    ShoppingCart,
    Star,
    Tag,
    TrendingUp,
    Type,
    Upload,
    User,
    Zap
} from 'lucide-react';
import React from 'react';

const fieldTypes = [
  {
    category: 'Standard Fields',
    fields: [
      { type: 'text', label: 'Single Line Text', icon: Type },
      { type: 'textarea', label: 'Paragraph Text', icon: MessageSquare },
      { type: 'select', label: 'Dropdown', icon: ChevronDown },
      { type: 'checkbox', label: 'Multiple Choice', icon: CheckSquare },
      { type: 'checkboxes', label: 'Checkboxes', icon: CheckSquare },
      { type: 'number', label: 'Numbers', icon: Hash },
      { type: 'name', label: 'Name', icon: User },
      { type: 'email', label: 'Email', icon: Mail },
      { type: 'number-slider', label: 'Number Slider', icon: TrendingUp },
      { type: 'captcha', label: 'CAPTCHA', icon: Lock },
    ],
  },
  {
    category: 'Fancy Fields',
    fields: [
      { type: 'phone', label: 'Phone', icon: Phone },
      { type: 'address', label: 'Address', icon: MapPin },
      { type: 'datetime', label: 'Date / Time', icon: Calendar },
      { type: 'website', label: 'Website / URL', icon: Globe },
      { type: 'password', label: 'Password', icon: Lock },
      { type: 'hidden', label: 'Hidden Field', icon: Eye },
      { type: 'file-upload', label: 'File Upload', icon: Upload },
      { type: 'camera', label: 'Camera', icon: Camera },
      { type: 'layout', label: 'Layout', icon: Layout },
      { type: 'repeater', label: 'Repeater', icon: Circle },
      { type: 'page-break', label: 'Page Break', icon: Minus },
      { type: 'section-divider', label: 'Section Divider', icon: Minus },
      { type: 'rich-text', label: 'Rich Text', icon: FileText },
      { type: 'content', label: 'Content', icon: FileText },
      { type: 'html', label: 'HTML', icon: Code },
      { type: 'entry-preview', label: 'Entry Preview', icon: Eye },
      { type: 'signature', label: 'Signature', icon: PenTool },
      { type: 'custom-captcha', label: 'Custom Captcha', icon: Lock },
      { type: 'rating', label: 'Rating', icon: Star },
      { type: 'likert-scale', label: 'Likert Scale', icon: TrendingUp },
      { type: 'net-promoter', label: 'Net Promoter Score', icon: TrendingUp },
    ],
  },
  {
    category: 'Payment Fields',
    fields: [
      { type: 'payment-single', label: 'Single Item', icon: DollarSign },
      { type: 'payment-checkbox', label: 'Checkbox Items', icon: CheckSquare },
      { type: 'payment-multiple', label: 'Multiple Items', icon: ShoppingCart },
      { type: 'payment-dropdown', label: 'Dropdown Items', icon: ChevronDown },
      { type: 'paypal', label: 'PayPal Commerce', icon: CreditCard },
      { type: 'stripe', label: 'Stripe Credit Card', icon: CreditCard },
      { type: 'square', label: 'Square', icon: CreditCard },
      { type: 'authorize-net', label: 'Authorize.Net', icon: CreditCard },
      { type: 'coupon', label: 'Coupon', icon: DollarSign },
      { type: 'total', label: 'Total', icon: DollarSign },
    ],
  },
];

const DraggableField = ({ type, label, icon: Icon }) => {
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
    id: `library-${type}`,
    data: { type },
  });

  return (
    <div
      ref={setNodeRef}
      {...listeners}
      {...attributes}
      className={`formtura-field-item ${isDragging ? 'dragging' : ''}`}
    >
      <Icon className="formtura-field-icon" size={20} />
      <span className="formtura-field-label">{label}</span>
    </div>
  );
};

const FieldLibrary = ({ selectedField, fields, onFieldUpdate, isCollapsed, onToggleCollapse }) => {
  const [searchTerm, setSearchTerm] = React.useState('');
  const [activeTab, setActiveTab] = React.useState('add'); // 'add' or 'options'
  const [optionsTab, setOptionsTab] = React.useState('general'); // 'general', 'advanced', 'smart-logic'
  const [collapsedGroups, setCollapsedGroups] = React.useState({}); // Track which groups are collapsed

  // Get the selected field data
  const field = fields?.find(f => f.id === selectedField);

  // Auto-switch to Field Options when a field is selected
  React.useEffect(() => {
    if (selectedField && field) {
      setActiveTab('options');
    }
  }, [selectedField, field]);

  // Toggle field group collapse
  const toggleGroup = (category) => {
    setCollapsedGroups(prev => ({
      ...prev,
      [category]: !prev[category]
    }));
  };

  const filteredFieldTypes = fieldTypes.map(group => ({
    ...group,
    fields: group.fields.filter(field =>
      field.label.toLowerCase().includes(searchTerm.toLowerCase())
    )
  })).filter(group => group.fields.length > 0);

  return (
    <div className={`formtura-sidebar ${isCollapsed ? 'collapsed' : ''}`}>
      <button
        className="formtura-sidebar-collapse-btn"
        onClick={onToggleCollapse}
        type="button"
        title={isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
      >
        {isCollapsed ? <ChevronRight size={20} /> : <ChevronLeft size={20} />}
      </button>

      {!isCollapsed && (
        <>
          <div className="formtura-sidebar-header">
            <div className="formtura-panel-toggle">
              <button
                className={`formtura-panel-toggle-btn ${activeTab === 'add' ? 'active' : ''}`}
                onClick={() => setActiveTab('add')}
                type="button"
              >
                <List size={16} />
                Add Fields
              </button>
              <button
                className={`formtura-panel-toggle-btn ${activeTab === 'options' ? 'active' : ''}`}
                onClick={() => setActiveTab('options')}
                disabled={!field}
                type="button"
              >
                <Settings size={16} />
                Field Options
              </button>
            </div>
          </div>

          {activeTab === 'add' && (
            <>
              <div className="formtura-sidebar-search">
                <input
                  type="text"
                  placeholder="Search fields..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="formtura-search-input"
                />
              </div>

              <div className="formtura-sidebar-content">
                {filteredFieldTypes.map((group) => (
                  <div key={group.category} className="formtura-field-group">
                    <button
                      className="formtura-field-group-title"
                      onClick={() => toggleGroup(group.category)}
                      type="button"
                    >
                      <span>{group.category}</span>
                      <ChevronDown
                        size={16}
                        className={`formtura-group-chevron ${collapsedGroups[group.category] ? 'collapsed' : ''}`}
                      />
                    </button>
                    {!collapsedGroups[group.category] && (
                      <div className="formtura-field-grid">
                        {group.fields.map((field) => (
                          <DraggableField
                            key={field.type}
                            type={field.type}
                            label={field.label}
                            icon={field.icon}
                          />
                        ))}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </>
          )}

          {activeTab === 'options' && (
            <>
              {!field ? (
                <div className="formtura-sidebar-content">
                  <div className="formtura-settings-empty">
                    <Settings size={48} />
                    <p>Select a field to view options</p>
                  </div>
                </div>
              ) : (
                <>
                  <div className="formtura-settings-tabs">
                    <button
                      className={`formtura-settings-tab ${optionsTab === 'general' ? 'active' : ''}`}
                      onClick={() => setOptionsTab('general')}
                      type="button"
                    >
                      General
                    </button>
                    <button
                      className={`formtura-settings-tab ${optionsTab === 'advanced' ? 'active' : ''}`}
                      onClick={() => setOptionsTab('advanced')}
                      type="button"
                    >
                      Advanced
                    </button>
                    <button
                      className={`formtura-settings-tab ${optionsTab === 'smart-logic' ? 'active' : ''}`}
                      onClick={() => setOptionsTab('smart-logic')}
                      type="button"
                    >
                      Smart Logic
                    </button>
                  </div>

                  <div className="formtura-sidebar-content">
                    {optionsTab === 'general' && (
                      <GeneralTab field={field} onUpdate={onFieldUpdate} />
                    )}
                    {optionsTab === 'advanced' && (
                      <AdvancedTab field={field} onUpdate={onFieldUpdate} />
                    )}
                    {optionsTab === 'smart-logic' && (
                      <SmartLogicTab field={field} onUpdate={onFieldUpdate} />
                    )}
                  </div>
                </>
              )}
            </>
          )}
        </>
      )}
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
  const [showLayouts, setShowLayouts] = React.useState(false);

  const handleChange = (key, value) => {
    onUpdate(field.id, { [key]: value });
  };

  const handleLayoutSelect = (layout, position) => {
    let cssClasses = '';
    const isFirst = position === 0;

    switch(layout) {
      case '1-1': // 2 columns (1/2 + 1/2)
        cssClasses = isFirst ? 'fta-one-half fta-first' : 'fta-one-half';
        break;
      case '1-2': // 3 columns (1/3 + 1/3 + 1/3)
        cssClasses = isFirst ? 'fta-one-third fta-first' : 'fta-one-third';
        break;
      case '1-3': // 4 columns (1/4 + 1/4 + 1/4 + 1/4)
        cssClasses = isFirst ? 'fta-one-fourth fta-first' : 'fta-one-fourth';
        break;
      case '2-1': // 2 columns (1/3 + 2/3)
        if (position === 0) {
          cssClasses = 'fta-one-third fta-first';
        } else {
          cssClasses = 'fta-two-thirds';
        }
        break;
      case '2-2': // 2 columns (2/3 + 1/3)
        if (position === 0) {
          cssClasses = 'fta-two-thirds fta-first';
        } else {
          cssClasses = 'fta-one-third';
        }
        break;
      case '3-1': // 2 columns (1/4 + 3/4)
        if (position === 0) {
          cssClasses = 'fta-one-fourth fta-first';
        } else {
          cssClasses = 'fta-three-fourths';
        }
        break;
      case '3-2': // 2 columns (3/4 + 1/4)
        if (position === 0) {
          cssClasses = 'fta-three-fourths fta-first';
        } else {
          cssClasses = 'fta-one-fourth';
        }
        break;
      case '4-1': // 3 columns (1/4 + 2/4 + 1/4)
        if (position === 0) {
          cssClasses = 'fta-one-fourth fta-first';
        } else if (position === 1) {
          cssClasses = 'fta-two-fourths';
        } else {
          cssClasses = 'fta-one-fourth';
        }
        break;
      default:
        cssClasses = '';
    }
    handleChange('cssClasses', cssClasses);
    setShowLayouts(false);
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
        <label htmlFor="field-css-classes">
          CSS Classes <Info size={14} className="formtura-help-icon" />
        </label>
        <input
          id="field-css-classes"
          type="text"
          value={field.cssClasses || ''}
          onChange={(e) => handleChange('cssClasses', e.target.value)}
        />
        <button
          className="formtura-btn-link"
          type="button"
          onClick={() => setShowLayouts(!showLayouts)}
        >
          <Tag size={14} /> {showLayouts ? 'Hide Layouts' : 'Show Layouts'}
        </button>

        {showLayouts && (
          <div className="formtura-layout-selector">
            <p className="formtura-layout-title">Select your layout</p>
            <div className="formtura-layout-grid">
              <div className="formtura-layout-option" title="2 Columns (1/2 + 1/2)">
                <div className="formtura-layout-preview">
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    onClick={() => handleLayoutSelect('1-1', 0)}
                    title="First column (1/2)"
                  ></button>
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    onClick={() => handleLayoutSelect('1-1', 1)}
                    title="Second column (1/2)"
                  ></button>
                </div>
              </div>
              <div className="formtura-layout-option" title="3 Columns (1/3 + 1/3 + 1/3)">
                <div className="formtura-layout-preview">
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    onClick={() => handleLayoutSelect('1-2', 0)}
                    title="First column (1/3)"
                  ></button>
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    onClick={() => handleLayoutSelect('1-2', 1)}
                    title="Second column (1/3)"
                  ></button>
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    onClick={() => handleLayoutSelect('1-2', 2)}
                    title="Third column (1/3)"
                  ></button>
                </div>
              </div>
              <div className="formtura-layout-option" title="4 Columns (1/4 + 1/4 + 1/4 + 1/4)">
                <div className="formtura-layout-preview">
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    onClick={() => handleLayoutSelect('1-3', 0)}
                    title="First column (1/4)"
                  ></button>
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    onClick={() => handleLayoutSelect('1-3', 1)}
                    title="Second column (1/4)"
                  ></button>
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    onClick={() => handleLayoutSelect('1-3', 2)}
                    title="Third column (1/4)"
                  ></button>
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    onClick={() => handleLayoutSelect('1-3', 3)}
                    title="Fourth column (1/4)"
                  ></button>
                </div>
              </div>
              <div className="formtura-layout-option" title="2 Columns (1/3 + 2/3)">
                <div className="formtura-layout-preview">
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    style={{flex: '1'}}
                    onClick={() => handleLayoutSelect('2-1', 0)}
                    title="First column (1/3)"
                  ></button>
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    style={{flex: '2'}}
                    onClick={() => handleLayoutSelect('2-1', 1)}
                    title="Second column (2/3)"
                  ></button>
                </div>
              </div>
              <div className="formtura-layout-option" title="2 Columns (2/3 + 1/3)">
                <div className="formtura-layout-preview">
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    style={{flex: '2'}}
                    onClick={() => handleLayoutSelect('2-2', 0)}
                    title="First column (2/3)"
                  ></button>
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    style={{flex: '1'}}
                    onClick={() => handleLayoutSelect('2-2', 1)}
                    title="Second column (1/3)"
                  ></button>
                </div>
              </div>
              <div className="formtura-layout-option" title="2 Columns (1/4 + 3/4)">
                <div className="formtura-layout-preview">
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    style={{flex: '1'}}
                    onClick={() => handleLayoutSelect('3-1', 0)}
                    title="First column (1/4)"
                  ></button>
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    style={{flex: '3'}}
                    onClick={() => handleLayoutSelect('3-1', 1)}
                    title="Second column (3/4)"
                  ></button>
                </div>
              </div>
              <div className="formtura-layout-option" title="2 Columns (3/4 + 1/4)">
                <div className="formtura-layout-preview">
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    style={{flex: '3'}}
                    onClick={() => handleLayoutSelect('3-2', 0)}
                    title="First column (3/4)"
                  ></button>
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    style={{flex: '1'}}
                    onClick={() => handleLayoutSelect('3-2', 1)}
                    title="Second column (1/4)"
                  ></button>
                </div>
              </div>
              <div className="formtura-layout-option" title="3 Columns (1/4 + 2/4 + 1/4)">
                <div className="formtura-layout-preview">
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    style={{flex: '1'}}
                    onClick={() => handleLayoutSelect('4-1', 0)}
                    title="First column (1/4)"
                  ></button>
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    style={{flex: '2'}}
                    onClick={() => handleLayoutSelect('4-1', 1)}
                    title="Second column (2/4)"
                  ></button>
                  <button
                    type="button"
                    className="formtura-layout-col formtura-layout-col-clickable"
                    style={{flex: '1'}}
                    onClick={() => handleLayoutSelect('4-1', 2)}
                    title="Third column (1/4)"
                  ></button>
                </div>
              </div>
            </div>
          </div>
        )}
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

export default FieldLibrary;
