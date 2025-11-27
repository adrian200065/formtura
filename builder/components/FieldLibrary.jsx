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
    Download,
    Eye,
    FileText,
    Globe,
    GripVertical,
    Hash,
    HelpCircle,
    Layout,
    List,
    Lock,
    Mail,
    MapPin,
    MessageSquare,
    Minus,
    MoreHorizontal,
    PenTool,
    Phone,
    Plus,
    Settings,
    ShoppingCart,
    Star,
    Tag,
    TrendingUp,
    Type,
    Upload,
    User,
    Wand2,
    Zap
} from 'lucide-react';
import React from 'react';
import InfoDialog from './InfoDialog';

// Tooltip Component
const Tooltip = ({ text, children }) => {
  const [isVisible, setIsVisible] = React.useState(false);
  const tooltipRef = React.useRef(null);

  return (
    <span
      className="formtura-tooltip-wrapper"
      onMouseEnter={() => setIsVisible(true)}
      onMouseLeave={() => setIsVisible(false)}
      onFocus={() => setIsVisible(true)}
      onBlur={() => setIsVisible(false)}
      ref={tooltipRef}
    >
      {children || <HelpCircle size={14} className="formtura-help-icon" tabIndex={0} aria-label="Help" />}
      {isVisible && (
        <span className="formtura-tooltip" role="tooltip">
          {text}
        </span>
      )}
    </span>
  );
};

// Smart Tags data
const smartTagsData = [
  { category: 'OTHER', tags: [
    { label: 'Site Administrator Email', value: '{admin_email}' },
    { label: 'Form ID', value: '{form_id}' },
    { label: 'Form Name', value: '{form_name}' },
    { label: 'Embedded Post/Page Title', value: '{page_title}' },
    { label: 'Embedded Post/Page URL', value: '{page_url}' },
    { label: 'Embedded Post/Page ID', value: '{page_id}' },
    { label: 'Date', value: '{date}' },
    { label: 'Query String Variable', value: '{query_var key=""}' },
    { label: 'User IP Address', value: '{user_ip}' },
    { label: 'User ID', value: '{user_id}' },
    { label: 'User Display Name', value: '{user_display_name}' },
    { label: 'User Full Name', value: '{user_full_name}' },
    { label: 'User First Name', value: '{user_first_name}' },
    { label: 'User Last Name', value: '{user_last_name}' },
    { label: "Logged-in User's Email", value: '{user_email}' },
    { label: 'User Meta', value: '{user_meta key=""}' },
    { label: 'Author ID', value: '{author_id}' },
    { label: 'Author Name', value: '{author_name}' },
    { label: 'Author Email', value: '{author_email}' },
    { label: 'Referrer URL', value: '{referrer_url}' },
    { label: 'Login URL', value: '{login_url}' },
    { label: 'Logout URL', value: '{logout_url}' },
    { label: 'Register URL', value: '{register_url}' },
    { label: 'Lost Password URL', value: '{lost_password_url}' },
    { label: 'Unique Value', value: '{unique_value}' },
    { label: 'Site Name', value: '{site_name}' },
    { label: 'Order Summary', value: '{order_summary}' },
  ]},
];

// SmartTagsPopup Component
const SmartTagsPopup = ({ isOpen, onClose, onSelect, position }) => {
  const [searchTerm, setSearchTerm] = React.useState('');
  const popupRef = React.useRef(null);

  // Close popup when clicking outside
  React.useEffect(() => {
    const handleClickOutside = (event) => {
      if (popupRef.current && !popupRef.current.contains(event.target)) {
        onClose();
      }
    };

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  const filteredTags = smartTagsData.map(category => ({
    ...category,
    tags: category.tags.filter(tag =>
      tag.label.toLowerCase().includes(searchTerm.toLowerCase())
    )
  })).filter(category => category.tags.length > 0);

  return (
    <div
      ref={popupRef}
      className="formtura-smart-tags-popup"
      style={{
        position: 'absolute',
        top: position?.top || '100%',
        right: position?.right || 0,
        zIndex: 1000,
      }}
    >
      <div className="formtura-smart-tags-header">
        <strong>Smart Tags</strong>
      </div>
      <div className="formtura-smart-tags-search">
        <input
          type="text"
          placeholder="Search"
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className="formtura-smart-tags-search-input"
          autoFocus
        />
      </div>
      <div className="formtura-smart-tags-list">
        {filteredTags.map((category, catIndex) => (
          <div key={catIndex} className="formtura-smart-tags-category">
            <div className="formtura-smart-tags-category-title">{category.category}</div>
            {category.tags.map((tag, tagIndex) => (
              <button
                key={tagIndex}
                type="button"
                className="formtura-smart-tags-item"
                onClick={() => {
                  onSelect(tag.value);
                  onClose();
                }}
              >
                {tag.label}
              </button>
            ))}
          </div>
        ))}
      </div>
    </div>
  );
};

// SmartTagButton Component - Reusable button with popup
const SmartTagButton = ({ onSelect }) => {
  const [isOpen, setIsOpen] = React.useState(false);
  const buttonRef = React.useRef(null);

  return (
    <div style={{ position: 'relative' }}>
      <button
        ref={buttonRef}
        type="button"
        className="formtura-smart-tag-btn"
        title="Smart Tags"
        onClick={() => setIsOpen(!isOpen)}
      >
        <Tag size={16} />
      </button>
      <SmartTagsPopup
        isOpen={isOpen}
        onClose={() => setIsOpen(false)}
        onSelect={onSelect}
        position={{ top: '100%', right: 0 }}
      />
    </div>
  );
};

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
      { type: 'rating', label: 'Star Rating', icon: Star },
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

// Non-draggable field that triggers a click action (for CAPTCHA, etc.)
const ClickableField = ({ type, label, icon: Icon, onClick }) => {
  return (
    <div
      className="formtura-field-item formtura-field-item-clickable"
      onClick={onClick}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onClick();
        }
      }}
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
  const [showCaptchaDialog, setShowCaptchaDialog] = React.useState(false); // CAPTCHA info dialog
  const [showStripeDialog, setShowStripeDialog] = React.useState(false); // Stripe info dialog
  const [showPayPalDialog, setShowPayPalDialog] = React.useState(false); // PayPal info dialog
  const [showSquareDialog, setShowSquareDialog] = React.useState(false); // Square info dialog

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
                        {group.fields.map((fieldItem) => {
                          // CAPTCHA fields show a popup instead of being draggable
                          if (fieldItem.type === 'captcha' || fieldItem.type === 'custom-captcha') {
                            return (
                              <ClickableField
                                key={fieldItem.type}
                                type={fieldItem.type}
                                label={fieldItem.label}
                                icon={fieldItem.icon}
                                onClick={() => setShowCaptchaDialog(true)}
                              />
                            );
                          }
                          // Stripe field shows a popup for connection setup
                          if (fieldItem.type === 'stripe') {
                            return (
                              <ClickableField
                                key={fieldItem.type}
                                type={fieldItem.type}
                                label={fieldItem.label}
                                icon={fieldItem.icon}
                                onClick={() => setShowStripeDialog(true)}
                              />
                            );
                          }
                          // PayPal field shows a popup for connection setup
                          if (fieldItem.type === 'paypal') {
                            return (
                              <ClickableField
                                key={fieldItem.type}
                                type={fieldItem.type}
                                label={fieldItem.label}
                                icon={fieldItem.icon}
                                onClick={() => setShowPayPalDialog(true)}
                              />
                            );
                          }
                          // Square field shows a popup for connection setup
                          if (fieldItem.type === 'square') {
                            return (
                              <ClickableField
                                key={fieldItem.type}
                                type={fieldItem.type}
                                label={fieldItem.label}
                                icon={fieldItem.icon}
                                onClick={() => setShowSquareDialog(true)}
                              />
                            );
                          }
                          return (
                            <DraggableField
                              key={fieldItem.type}
                              type={fieldItem.type}
                              label={fieldItem.label}
                              icon={fieldItem.icon}
                            />
                          );
                        })}
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

      {/* CAPTCHA Info Dialog */}
      <InfoDialog
        isOpen={showCaptchaDialog}
        title="Heads up!"
        message={
          <>
            Please complete the CAPTCHA setup in your{' '}
            <a
              href={`${window.formturaBuilder?.adminUrl || '/wp-admin/'}admin.php?page=formtura-settings&tab=captcha`}
              style={{ color: 'var(--fta-builder-primary)', textDecoration: 'underline' }}
            >
              Formtura Settings
            </a>
            {' '}to enable CAPTCHA protection on your forms.
          </>
        }
        buttonText="OK"
        onClose={() => setShowCaptchaDialog(false)}
      />

      {/* Stripe Info Dialog */}
      <InfoDialog
        isOpen={showStripeDialog}
        title="Heads up!"
        message={
          <>
            <p style={{ marginBottom: '12px' }}>
              Stripe account connection is required when using the Stripe Credit Card field.
            </p>
            <p>
              To proceed, please go to{' '}
              <a
                href={`${window.formturaBuilder?.adminUrl || '/wp-admin/'}admin.php?page=formtura-settings&tab=payments`}
                style={{ color: 'var(--fta-builder-primary)', textDecoration: 'none', fontWeight: '600' }}
              >
                Formtura Settings » Payments » Stripe
              </a>
              {' '}and press{' '}
              <strong>Connect with Stripe</strong> button.
            </p>
          </>
        }
        buttonText="OK"
        onClose={() => setShowStripeDialog(false)}
      />

      {/* PayPal Info Dialog */}
      <InfoDialog
        isOpen={showPayPalDialog}
        title="Heads up!"
        message={
          <>
            <p style={{ marginBottom: '12px' }}>
              PayPal account connection is required when using the PayPal Commerce field.
            </p>
            <p>
              To proceed, please go to{' '}
              <a
                href={`${window.formturaBuilder?.adminUrl || '/wp-admin/'}admin.php?page=formtura-settings&tab=payments`}
                style={{ color: 'var(--fta-builder-primary)', textDecoration: 'none', fontWeight: '600' }}
              >
                Formtura Settings » Payments » PayPal
              </a>
              {' '}and press{' '}
              <strong>Connect with PayPal</strong> button.
            </p>
          </>
        }
        buttonText="OK"
        onClose={() => setShowPayPalDialog(false)}
      />

      {/* Square Info Dialog */}
      <InfoDialog
        isOpen={showSquareDialog}
        title="Heads up!"
        message={
          <>
            <p style={{ marginBottom: '12px' }}>
              Square account connection is required when using the Square field.
            </p>
            <p>
              To proceed, please go to{' '}
              <a
                href={`${window.formturaBuilder?.adminUrl || '/wp-admin/'}admin.php?page=formtura-settings&tab=payments`}
                style={{ color: 'var(--fta-builder-primary)', textDecoration: 'none', fontWeight: '600' }}
              >
                Formtura Settings » Payments » Square
              </a>
              {' '}and press{' '}
              <strong>Connect with Square</strong> button.
            </p>
          </>
        }
        buttonText="OK"
        onClose={() => setShowSquareDialog(false)}
      />
    </div>
  );
};

// WYSIWYG Editor Component for HTML field
const WysiwygEditor = ({ value, onChange }) => {
  const editorRef = React.useRef(null);
  const [mode, setMode] = React.useState('visual'); // 'visual' or 'code'
  const [codeValue, setCodeValue] = React.useState(value || '');

  // Sync content from prop to editor on initial render
  React.useEffect(() => {
    if (mode === 'visual' && editorRef.current && value !== editorRef.current.innerHTML) {
      editorRef.current.innerHTML = value || '';
    }
    if (mode === 'code') {
      setCodeValue(value || '');
    }
  }, []); // Only on mount

  // Sync when switching modes
  React.useEffect(() => {
    if (mode === 'visual' && editorRef.current) {
      editorRef.current.innerHTML = value || '';
    } else if (mode === 'code') {
      setCodeValue(value || '');
    }
  }, [mode]);

  const handleInput = () => {
    if (editorRef.current) {
      onChange(editorRef.current.innerHTML);
    }
  };

  const handleCodeChange = (e) => {
    const newValue = e.target.value;
    setCodeValue(newValue);
    onChange(newValue);
  };

  const execCommand = (command, cmdValue = null) => {
    document.execCommand(command, false, cmdValue);
    editorRef.current?.focus();
    handleInput();
  };

  const handleLink = () => {
    const url = prompt('Enter URL:', 'https://');
    if (url) {
      execCommand('createLink', url);
    }
  };

  const insertTag = (tag) => {
    const textarea = document.getElementById('formtura-code-editor');
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = codeValue.substring(start, end);
    let newText;

    switch(tag) {
      case 'b':
        newText = `<strong>${selectedText}</strong>`;
        break;
      case 'i':
        newText = `<em>${selectedText}</em>`;
        break;
      case 'link':
        const url = prompt('Enter URL:', 'https://');
        if (!url) return;
        newText = `<a href="${url}">${selectedText || url}</a>`;
        break;
      case 'b-quote':
        newText = `<blockquote>${selectedText}</blockquote>`;
        break;
      case 'del':
        newText = `<del>${selectedText}</del>`;
        break;
      case 'ins':
        newText = `<ins>${selectedText}</ins>`;
        break;
      case 'img':
        const imgUrl = prompt('Enter image URL:', 'https://');
        if (!imgUrl) return;
        newText = `<img src="${imgUrl}" alt="${selectedText || ''}" />`;
        break;
      case 'ul':
        newText = `<ul>\n  <li>${selectedText || 'Item'}</li>\n</ul>`;
        break;
      case 'ol':
        newText = `<ol>\n  <li>${selectedText || 'Item'}</li>\n</ol>`;
        break;
      case 'li':
        newText = `<li>${selectedText}</li>`;
        break;
      case 'code':
        newText = `<code>${selectedText}</code>`;
        break;
      case 'more':
        newText = `<!--more-->`;
        break;
      default:
        newText = selectedText;
    }

    const newValue = codeValue.substring(0, start) + newText + codeValue.substring(end);
    setCodeValue(newValue);
    onChange(newValue);
  };

  const tagButtonStyle = {
    padding: '2px 8px',
    border: '1px solid #c3c4c7',
    background: '#f6f7f7',
    borderRadius: '3px',
    cursor: 'pointer',
    fontSize: '12px',
    color: '#2271b1',
    fontFamily: 'inherit',
  };

  const tabStyle = (active) => ({
    padding: '6px 12px',
    border: 'none',
    background: active ? '#fff' : 'transparent',
    cursor: 'pointer',
    fontSize: '13px',
    fontWeight: active ? '600' : '400',
    color: active ? '#1e1e1e' : '#646970',
    borderRadius: '3px 3px 0 0',
    marginLeft: '4px',
  });

  return (
    <div className="formtura-wysiwyg-editor">
      {/* Toolbar Row */}
      <div style={{
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'flex-end',
        background: '#f0f0f1',
        border: '1px solid #c3c4c7',
        borderBottom: 'none',
        borderRadius: '4px 4px 0 0',
        padding: '0',
      }}>
        {/* Quick Tags for Code mode / Format buttons for Visual */}
        <div style={{
          display: 'flex',
          gap: '2px',
          padding: '8px',
          flexWrap: 'wrap',
        }}>
          {mode === 'code' ? (
            <>
              <button type="button" onClick={() => insertTag('b')} style={tagButtonStyle}>b</button>
              <button type="button" onClick={() => insertTag('i')} style={tagButtonStyle}>i</button>
              <button type="button" onClick={() => insertTag('link')} style={tagButtonStyle}>link</button>
              <button type="button" onClick={() => insertTag('b-quote')} style={tagButtonStyle}>b-quote</button>
              <button type="button" onClick={() => insertTag('del')} style={tagButtonStyle}>del</button>
              <button type="button" onClick={() => insertTag('ins')} style={tagButtonStyle}>ins</button>
              <button type="button" onClick={() => insertTag('img')} style={tagButtonStyle}>img</button>
              <button type="button" onClick={() => insertTag('ul')} style={tagButtonStyle}>ul</button>
              <button type="button" onClick={() => insertTag('ol')} style={tagButtonStyle}>ol</button>
              <button type="button" onClick={() => insertTag('li')} style={tagButtonStyle}>li</button>
              <button type="button" onClick={() => insertTag('code')} style={tagButtonStyle}>code</button>
              <button type="button" onClick={() => insertTag('more')} style={tagButtonStyle}>more</button>
            </>
          ) : (
            <>
              <button type="button" onClick={() => execCommand('bold')} style={tagButtonStyle} title="Bold">
                <strong>B</strong>
              </button>
              <button type="button" onClick={() => execCommand('italic')} style={tagButtonStyle} title="Italic">
                <em>I</em>
              </button>
              <button type="button" onClick={handleLink} style={tagButtonStyle} title="Insert Link">
                link
              </button>
              <button type="button" onClick={() => execCommand('formatBlock', 'blockquote')} style={tagButtonStyle} title="Blockquote">
                b-quote
              </button>
              <button type="button" onClick={() => execCommand('strikeThrough')} style={tagButtonStyle} title="Strikethrough">
                del
              </button>
              <button type="button" onClick={() => execCommand('underline')} style={tagButtonStyle} title="Underline">
                ins
              </button>
              <button type="button" onClick={() => execCommand('insertUnorderedList')} style={tagButtonStyle} title="Bullet List">
                ul
              </button>
              <button type="button" onClick={() => execCommand('insertOrderedList')} style={tagButtonStyle} title="Numbered List">
                ol
              </button>
              <button type="button" onClick={() => execCommand('removeFormat')} style={tagButtonStyle} title="Clear Formatting">
                close tags
              </button>
            </>
          )}
        </div>

        {/* Visual / Code Tabs */}
        <div style={{ display: 'flex', paddingRight: '4px' }}>
          <button
            type="button"
            onClick={() => setMode('visual')}
            style={tabStyle(mode === 'visual')}
          >
            Visual
          </button>
          <button
            type="button"
            onClick={() => setMode('code')}
            style={tabStyle(mode === 'code')}
          >
            Code
          </button>
        </div>
      </div>

      {/* Content Area */}
      {mode === 'visual' ? (
        <div
          ref={editorRef}
          contentEditable
          onInput={handleInput}
          onBlur={handleInput}
          className="formtura-wysiwyg-content"
          style={{
            minBlockSize: '32rem',
            maxBlockSize: '40rem',
            overflowY: 'auto',
            padding: '12px',
            border: '1px solid #c3c4c7',
            borderBlockStart: 'none',
            borderRadius: '0 0 4px 4px',
            outline: 'none',
            background: '#fff',
            lineHeight: '1.6',
          }}
          dangerouslySetInnerHTML={{ __html: value || '' }}
        />
      ) : (
        <textarea
          id="formtura-code-editor"
          value={codeValue}
          onChange={handleCodeChange}
          style={{
            inlineSize: '100%',
            minBlockSize: '32rem',
            maxBlockSize: '40rem',
            padding: '12px',
            border: '1px solid #c3c4c7',
            borderBlockStart: 'none',
            borderRadius: '0 0 4px 4px',
            outline: 'none',
            background: '#fff',
            fontFamily: 'Consolas, Monaco, "Andale Mono", "Ubuntu Mono", monospace',
            fontSize: '13px',
            lineHeight: '1.5',
            resize: 'vertical',
            boxSizing: 'border-box',
          }}
          placeholder="Enter HTML code here..."
        />
      )}
    </div>
  );
};

// General Tab Component
const GeneralTab = ({ field, onUpdate }) => {
  const [showBulkAdd, setShowBulkAdd] = React.useState(false);
  const [bulkText, setBulkText] = React.useState('');

  const handleChange = (key, value) => {
    onUpdate(field.id, { [key]: value });
  };

  // Initialize choices if not present
  React.useEffect(() => {
    if ((field.type === 'select' || field.type === 'checkbox' || field.type === 'checkboxes') && !field.choices) {
      handleChange('choices', [
        { label: 'First Choice', value: 'first-choice', isDefault: false },
        { label: 'Second Choice', value: 'second-choice', isDefault: false },
        { label: 'Third Choice', value: 'third-choice', isDefault: false }
      ]);
    }
    if (field.type === 'payment-dropdown' && !field.items) {
      handleChange('items', [
        { label: 'First Item', value: 'first-item', price: '10.00', isDefault: false },
        { label: 'Second Item', value: 'second-item', price: '25.00', isDefault: false },
        { label: 'Third Item', value: 'third-item', price: '50.00', isDefault: false }
      ]);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [field.type]);

  const handleChoiceChange = (index, key, value) => {
    const newChoices = [...(field.choices || [])];
    newChoices[index] = { ...newChoices[index], [key]: value };
    handleChange('choices', newChoices);
  };

  const handleItemChange = (index, key, value) => {
    const newItems = [...(field.items || [])];
    newItems[index] = { ...newItems[index], [key]: value };
    handleChange('items', newItems);
  };

  const addChoice = () => {
    const newChoices = [...(field.choices || [])];
    newChoices.push({
      label: `Choice ${newChoices.length + 1}`,
      value: `choice-${newChoices.length + 1}`,
      isDefault: false
    });
    handleChange('choices', newChoices);
  };

  const removeChoice = (index) => {
    const newChoices = field.choices.filter((_, i) => i !== index);
    handleChange('choices', newChoices);
  };

  const addItem = () => {
    const newItems = [...(field.items || [])];
    newItems.push({
      label: `Item ${newItems.length + 1}`,
      value: `item-${newItems.length + 1}`,
      price: '0.00',
      isDefault: false
    });
    handleChange('items', newItems);
  };

  const removeItem = (index) => {
    const newItems = field.items.filter((_, i) => i !== index);
    handleChange('items', newItems);
  };

  const handleBulkAdd = () => {
    if (!bulkText.trim()) return;

    const lines = bulkText.split('\n').filter(line => line.trim());
    const newChoices = lines.map((line) => {
      const trimmed = line.trim();
      return {
        label: trimmed,
        value: trimmed.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, ''),
        isDefault: false
      };
    });

    handleChange('choices', newChoices);
    setBulkText('');
    setShowBulkAdd(false);
  };

  const generateChoices = () => {
    // Simple example - generate some sample choices
    const samples = ['Option A', 'Option B', 'Option C', 'Option D', 'Option E'];
    const newChoices = samples.map((label, index) => ({
      label,
      value: label.toLowerCase().replace(/\s+/g, '-'),
      isDefault: index === 0
    }));
    handleChange('choices', newChoices);
  };

  // Render field-specific options based on type
  const renderFieldSpecificOptions = () => {
    // Dropdown field (select)
    if (field.type === 'select') {
      return (
        <>
          <div className="formtura-form-group">
            <label>
              Choices <Tooltip text="Add dropdown options. Use the radio button to set a default selection." />
              <button
                type="button"
                className="formtura-bulk-add-btn"
                onClick={() => setShowBulkAdd(!showBulkAdd)}
                style={{ float: 'right', fontSize: '12px', padding: '4px 8px' }}
              >
                <Download size={12} /> Bulk Add
              </button>
            </label>

            {showBulkAdd && (
              <div className="formtura-bulk-add-container" style={{ marginBottom: '12px' }}>
                <textarea
                  placeholder="Paste your list here (one item per line)&#10;Example:&#10;Alabama&#10;Alaska&#10;Arizona"
                  value={bulkText}
                  onChange={(e) => setBulkText(e.target.value)}
                  rows={6}
                  style={{ width: '100%', marginBottom: '8px', padding: '8px' }}
                />
                <button
                  type="button"
                  onClick={handleBulkAdd}
                  className="formtura-btn-primary"
                  style={{ marginRight: '8px' }}
                >
                  Add Choices
                </button>
                <button
                  type="button"
                  onClick={() => { setBulkText(''); setShowBulkAdd(false); }}
                  className="formtura-btn-secondary"
                >
                  Cancel
                </button>
              </div>
            )}

            <div className="formtura-choices-list">
              {(field.choices || []).map((choice, index) => (
                <div key={index} className="formtura-choice-item" style={{ display: 'flex', gap: '8px', marginBottom: '8px', alignItems: 'center' }}>
                  <input
                    type="radio"
                    checked={choice.isDefault || false}
                    onChange={() => {
                      const newChoices = field.choices.map((c, i) => ({
                        ...c,
                        isDefault: i === index
                      }));
                      handleChange('choices', newChoices);
                    }}
                    style={{ flexShrink: 0 }}
                  />
                  <GripVertical size={16} style={{ color: '#999', cursor: 'move', flexShrink: 0 }} />
                  <input
                    type="text"
                    value={choice.label}
                    onChange={(e) => handleChoiceChange(index, 'label', e.target.value)}
                    placeholder="Choice label"
                    style={{ flex: 1 }}
                  />
                  <button
                    type="button"
                    onClick={addChoice}
                    className="formtura-icon-btn formtura-btn-add"
                    title="Add choice"
                  >
                    <Plus size={16} />
                  </button>
                  <button
                    type="button"
                    onClick={() => removeChoice(index)}
                    className="formtura-icon-btn formtura-btn-remove"
                    title="Remove choice"
                    disabled={field.choices.length <= 1}
                  >
                    <Minus size={16} />
                  </button>
                </div>
              ))}
            </div>

            <button
              type="button"
              onClick={generateChoices}
              className="formtura-btn-secondary"
              style={{ marginTop: '8px', fontSize: '13px' }}
            >
              <Wand2 size={14} /> Generate Choices
            </button>
          </div>
        </>
      );
    }

    // Multiple Choice field (checkbox - radio buttons)
    if (field.type === 'checkbox') {
      return (
        <>
          <div className="formtura-form-group">
            <label>
              Choices <Tooltip text="Add options for users to select. Use the radio button to set a default selection." />
              <button
                type="button"
                className="formtura-bulk-add-btn"
                onClick={() => setShowBulkAdd(!showBulkAdd)}
                style={{ float: 'right', fontSize: '12px', padding: '4px 8px' }}
              >
                <Download size={12} /> Bulk Add
              </button>
            </label>

            {showBulkAdd && (
              <div className="formtura-bulk-add-container" style={{ marginBottom: '12px' }}>
                <textarea
                  placeholder="Paste your list here (one item per line)"
                  value={bulkText}
                  onChange={(e) => setBulkText(e.target.value)}
                  rows={6}
                  style={{ width: '100%', marginBottom: '8px', padding: '8px' }}
                />
                <button
                  type="button"
                  onClick={handleBulkAdd}
                  className="formtura-btn-primary"
                  style={{ marginRight: '8px' }}
                >
                  Add Choices
                </button>
                <button
                  type="button"
                  onClick={() => { setBulkText(''); setShowBulkAdd(false); }}
                  className="formtura-btn-secondary"
                >
                  Cancel
                </button>
              </div>
            )}

            <div className="formtura-choices-list">
              {(field.choices || []).map((choice, index) => (
                <div key={index} className="formtura-choice-item" style={{ display: 'flex', gap: '8px', marginBottom: '8px', alignItems: 'center' }}>
                  <input
                    type="radio"
                    checked={choice.isDefault || false}
                    onChange={() => {
                      const newChoices = field.choices.map((c, i) => ({
                        ...c,
                        isDefault: i === index
                      }));
                      handleChange('choices', newChoices);
                    }}
                    style={{ flexShrink: 0 }}
                  />
                  <GripVertical size={16} style={{ color: '#999', cursor: 'move', flexShrink: 0 }} />
                  <input
                    type="text"
                    value={choice.label}
                    onChange={(e) => handleChoiceChange(index, 'label', e.target.value)}
                    placeholder="Choice label"
                    style={{ flex: 1 }}
                  />
                  <button
                    type="button"
                    onClick={addChoice}
                    className="formtura-icon-btn formtura-btn-add"
                    title="Add choice"
                  >
                    <Plus size={16} />
                  </button>
                  <button
                    type="button"
                    onClick={() => removeChoice(index)}
                    className="formtura-icon-btn formtura-btn-remove"
                    title="Remove choice"
                    disabled={field.choices.length <= 1}
                  >
                    <Minus size={16} />
                  </button>
                </div>
              ))}
            </div>

            <button
              type="button"
              onClick={generateChoices}
              className="formtura-btn-secondary"
              style={{ marginTop: '8px', fontSize: '13px' }}
            >
              <Wand2 size={14} /> Generate Choices
            </button>
          </div>

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
                Add Other Choice <Tooltip text="Allow users to enter a custom response if none of the provided options apply." />
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
                Use Image Choices <Tooltip text="Display images alongside or instead of text labels for each choice." />
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
                Use Icon Choices <Tooltip text="Display icons alongside or instead of text labels for each choice." />
              </span>
            </div>
          </div>
        </>
      );
    }

    // Checkboxes field
    if (field.type === 'checkboxes') {
      return (
        <>
          <div className="formtura-form-group">
            <label>
              Choices <Tooltip text="Add checkbox options. Users can select multiple options. Check boxes to set default selections." />
              <button
                type="button"
                className="formtura-bulk-add-btn"
                onClick={() => setShowBulkAdd(!showBulkAdd)}
                style={{ float: 'right', fontSize: '12px', padding: '4px 8px' }}
              >
                <Download size={12} /> Bulk Add
              </button>
            </label>

            {showBulkAdd && (
              <div className="formtura-bulk-add-container" style={{ marginBottom: '12px' }}>
                <textarea
                  placeholder="Paste your list here (one item per line)"
                  value={bulkText}
                  onChange={(e) => setBulkText(e.target.value)}
                  rows={6}
                  style={{ width: '100%', marginBottom: '8px', padding: '8px' }}
                />
                <button
                  type="button"
                  onClick={handleBulkAdd}
                  className="formtura-btn-primary"
                  style={{ marginRight: '8px' }}
                >
                  Add Choices
                </button>
                <button
                  type="button"
                  onClick={() => { setBulkText(''); setShowBulkAdd(false); }}
                  className="formtura-btn-secondary"
                >
                  Cancel
                </button>
              </div>
            )}

            <div className="formtura-choices-list">
              {(field.choices || []).map((choice, index) => (
                <div key={index} className="formtura-choice-item" style={{ display: 'flex', gap: '8px', marginBottom: '8px', alignItems: 'center' }}>
                  <input
                    type="checkbox"
                    checked={choice.isDefault || false}
                    onChange={(e) => handleChoiceChange(index, 'isDefault', e.target.checked)}
                    style={{ flexShrink: 0 }}
                  />
                  <GripVertical size={16} style={{ color: '#999', cursor: 'move', flexShrink: 0 }} />
                  <input
                    type="text"
                    value={choice.label}
                    onChange={(e) => handleChoiceChange(index, 'label', e.target.value)}
                    placeholder="Choice label"
                    style={{ flex: 1 }}
                  />
                  <button
                    type="button"
                    onClick={addChoice}
                    className="formtura-icon-btn formtura-btn-add"
                    title="Add choice"
                  >
                    <Plus size={16} />
                  </button>
                  <button
                    type="button"
                    onClick={() => removeChoice(index)}
                    className="formtura-icon-btn formtura-btn-remove"
                    title="Remove choice"
                    disabled={field.choices.length <= 1}
                  >
                    <Minus size={16} />
                  </button>
                </div>
              ))}
            </div>

            <button
              type="button"
              onClick={generateChoices}
              className="formtura-btn-secondary"
              style={{ marginTop: '8px', fontSize: '13px' }}
            >
              <Wand2 size={14} /> Generate Choices
            </button>
          </div>
        </>
      );
    }

    // Dropdown Items (payment-dropdown)
    if (field.type === 'payment-dropdown') {
      return (
        <>
          <div className="formtura-form-group">
            <label>
              Items <Tooltip text="Add payment items with prices. Use the radio button to set a default selection." />
            </label>

            <div className="formtura-items-list">
              {(field.items || []).map((item, index) => (
                <div key={index} className="formtura-item-row" style={{ display: 'flex', gap: '8px', marginBottom: '8px', alignItems: 'center' }}>
                  <input
                    type="radio"
                    checked={item.isDefault || false}
                    onChange={() => {
                      const newItems = field.items.map((it, i) => ({
                        ...it,
                        isDefault: i === index
                      }));
                      handleChange('items', newItems);
                    }}
                    style={{ flexShrink: 0 }}
                  />
                  <GripVertical size={16} style={{ color: '#999', cursor: 'move', flexShrink: 0 }} />
                  <input
                    type="text"
                    value={item.label}
                    onChange={(e) => handleItemChange(index, 'label', e.target.value)}
                    placeholder="Item name"
                    style={{ flex: 1 }}
                  />
                  <input
                    type="number"
                    value={item.price}
                    onChange={(e) => handleItemChange(index, 'price', e.target.value)}
                    placeholder="0.00"
                    step="0.01"
                    min="0"
                    style={{ width: '80px' }}
                  />
                  <button
                    type="button"
                    onClick={addItem}
                    className="formtura-icon-btn formtura-btn-add"
                    title="Add item"
                  >
                    <Plus size={16} />
                  </button>
                  <button
                    type="button"
                    onClick={() => removeItem(index)}
                    className="formtura-icon-btn formtura-btn-remove"
                    title="Remove item"
                    disabled={field.items.length <= 1}
                  >
                    <Minus size={16} />
                  </button>
                </div>
              ))}
            </div>
          </div>

          <div className="formtura-form-group">
            <div className="formtura-toggle-group">
              <label className="formtura-toggle">
                <input
                  type="checkbox"
                  checked={field.showPriceAfterLabel || false}
                  onChange={(e) => handleChange('showPriceAfterLabel', e.target.checked)}
                />
                <span className="formtura-toggle-slider"></span>
              </label>
              <span className="formtura-toggle-label">
                Show Price After Item Labels <Tooltip text="Display the price next to each item label in the dropdown." />
              </span>
            </div>
          </div>

          <div className="formtura-form-group">
            <div className="formtura-toggle-group">
              <label className="formtura-toggle">
                <input
                  type="checkbox"
                  checked={field.enableQuantity || false}
                  onChange={(e) => handleChange('enableQuantity', e.target.checked)}
                />
                <span className="formtura-toggle-slider"></span>
              </label>
              <span className="formtura-toggle-label">
                Enable Quantity <Tooltip text="Allow users to specify the quantity of the selected item." />
              </span>
            </div>
          </div>
        </>
      );
    }

    // Default: no field-specific options
    return null;
  };

  return (
    <div className="formtura-field-options">
      <div className="formtura-field-options-title">
        <strong>{field.label}</strong> <span className="formtura-field-id">(ID #{field.id.slice(-4)})</span>
      </div>

      <div className="formtura-form-group">
        <label htmlFor="field-label">
          Label <Tooltip text="Enter text for the form field label. Labels are recommended but can be hidden in Advanced Settings." />
        </label>
        <input
          id="field-label"
          type="text"
          value={field.label}
          onChange={(e) => handleChange('label', e.target.value)}
        />
      </div>

      {/* File Upload Options Section - After Label for file-upload field type */}
      {field.type === 'file-upload' && (
        <div className="formtura-collapsible-section">
          <details open>
            <summary className="formtura-collapsible-header">
              <span>File Upload Options</span>
              <ChevronDown size={16} className="formtura-collapsible-icon" />
            </summary>
            <div className="formtura-collapsible-content">
              {/* Warning notice */}
              <div className="formtura-warning-notice" style={{
                background: '#fef3c7',
                border: '1px solid #f59e0b',
                borderRadius: '6px',
                padding: '12px',
                marginBottom: '16px',
                display: 'flex',
                alignItems: 'flex-start',
                gap: '8px',
              }}>
                <span style={{ fontSize: '16px' }}>⚠</span>
                <span style={{ fontSize: '13px', color: '#92400e', lineHeight: '1.5' }}>
                  Uploads are public. File access can be updated in{' '}
                  <a href="#" style={{ color: '#1e73be', textDecoration: 'underline' }}>Form Permissions Settings</a>.{' '}
                  <Tooltip text="Files uploaded with this field can be viewed by anyone with access to a link and could be indexed by search engines. If this is a concern, we recommend enabling file protection and turning off indexing." />
                </span>
              </div>

              {/* Toggle options */}
              <div className="formtura-form-group">
                <div className="formtura-toggle-group">
                  <label className="formtura-toggle">
                    <input
                      type="checkbox"
                      checked={field.allowMultiple || false}
                      onChange={(e) => handleChange('allowMultiple', e.target.checked)}
                    />
                    <span className="formtura-toggle-slider"></span>
                  </label>
                  <span className="formtura-toggle-label">Allow multiple files to be uploaded</span>
                </div>
              </div>

              <div className="formtura-form-group">
                <div className="formtura-toggle-group">
                  <label className="formtura-toggle">
                    <input
                      type="checkbox"
                      checked={field.attachToEmail || false}
                      onChange={(e) => handleChange('attachToEmail', e.target.checked)}
                    />
                    <span className="formtura-toggle-slider"></span>
                  </label>
                  <span className="formtura-toggle-label">Attach this file to the email notification</span>
                </div>
              </div>

              <div className="formtura-form-group">
                <div className="formtura-toggle-group">
                  <label className="formtura-toggle">
                    <input
                      type="checkbox"
                      checked={field.deleteOnReplace || false}
                      onChange={(e) => handleChange('deleteOnReplace', e.target.checked)}
                    />
                    <span className="formtura-toggle-slider"></span>
                  </label>
                  <span className="formtura-toggle-label">Permanently delete old files when replaced or when the entry is deleted</span>
                </div>
              </div>

              <div className="formtura-form-group">
                <div className="formtura-toggle-group">
                  <label className="formtura-toggle">
                    <input
                      type="checkbox"
                      checked={field.autoResize || false}
                      onChange={(e) => handleChange('autoResize', e.target.checked)}
                    />
                    <span className="formtura-toggle-slider"></span>
                  </label>
                  <span className="formtura-toggle-label">
                    Automatically resize files before upload{' '}
                    <Tooltip text="When a large image is uploaded, resize it before you save it to your site." />
                  </span>
              </div>
              </div>

              {/* Allowed file types */}
              <div className="formtura-form-group">
                <label>Allowed file types</label>
                <div className="formtura-radio-inline-group">
                  <label className="formtura-radio-inline">
                    <input
                      type="radio"
                      name={`file-types-${field.id}`}
                      checked={field.allowedFileTypes === 'all'}
                      onChange={() => handleChange('allowedFileTypes', 'all')}
                    />
                    <span>Allow all file types</span>
                  </label>
                  <label className="formtura-radio-inline">
                    <input
                      type="radio"
                      name={`file-types-${field.id}`}
                      checked={field.allowedFileTypes === 'specify' || !field.allowedFileTypes}
                      onChange={() => handleChange('allowedFileTypes', 'specify')}
                    />
                    <span>Specify allowed types</span>
                  </label>
                </div>
                {(field.allowedFileTypes === 'specify' || !field.allowedFileTypes) && (
                  <select
                    value={field.specifiedTypes || 'jpg, jpeg, jpe, png, gif'}
                    onChange={(e) => handleChange('specifiedTypes', e.target.value)}
                    style={{ marginTop: '8px' }}
                  >
                    <option value="jpg, jpeg, jpe, png, gif">jpg, jpeg, jpe, png, gif</option>
                    <option value="pdf">pdf</option>
                    <option value="doc, docx">doc, docx</option>
                    <option value="xls, xlsx">xls, xlsx</option>
                    <option value="jpg, jpeg, jpe, png, gif, pdf">Images & PDF</option>
                    <option value="jpg, jpeg, jpe, png, gif, pdf, doc, docx">Images, PDF & Documents</option>
                  </select>
                )}
              </div>

              {/* File size limits */}
              <div className="formtura-form-group">
                <label>File size limits</label>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginTop: '8px' }}>
                  <div>
                    <label style={{ fontSize: '13px', color: '#6b7280', display: 'flex', alignItems: 'center', gap: '4px' }}>
                      Min file size (MB){' '}
                      <Tooltip text="Set the minimum file size limit for each file uploaded." />
                    </label>
                    <input
                      type="number"
                      min="0"
                      step="0.1"
                      value={field.minFileSize || ''}
                      onChange={(e) => handleChange('minFileSize', e.target.value)}
                      placeholder=""
                    />
                  </div>
                  <div>
                    <label style={{ fontSize: '13px', color: '#6b7280', display: 'flex', alignItems: 'center', gap: '4px' }}>
                      Max file size (MB){' '}
                      <Tooltip text="Set the file size limit for each file uploaded. Your server settings allow a maximum of 256 MB." />
                    </label>
                    <input
                      type="number"
                      min="0"
                      step="0.1"
                      value={field.maxFileSize || ''}
                      onChange={(e) => handleChange('maxFileSize', e.target.value)}
                      placeholder=""
                    />
                  </div>
                </div>
              </div>

              {/* Upload text */}
              <div className="formtura-form-group">
                <label htmlFor="upload-text">Upload text</label>
                <input
                  id="upload-text"
                  type="text"
                  value={field.uploadText || 'Drop a file here or click to upload'}
                  onChange={(e) => handleChange('uploadText', e.target.value)}
                />
              </div>

              {/* Compact upload text */}
              <div className="formtura-form-group">
                <label htmlFor="compact-upload-text">
                  Compact upload text{' '}
                  <Tooltip text="The label shown when the file upload field is compacted with fta_compact CSS layout class." />
                </label>
                <input
                  id="compact-upload-text"
                  type="text"
                  value={field.compactUploadText || 'Choose File'}
                  onChange={(e) => handleChange('compactUploadText', e.target.value)}
                />
              </div>
            </div>
          </details>
        </div>
      )}

      {/* Rich Text Editor - After Label for rich-text field type */}
      {field.type === 'rich-text' && (
        <div className="formtura-form-group">
          <label>
            Content <Tooltip text="Enter rich text content that users can edit. Use the toolbar to format text, add links, and create lists." />
          </label>
          <WysiwygEditor
            value={field.content || ''}
            onChange={(html) => handleChange('content', html)}
          />
        </div>
      )}

      {/* Name Field Format Selector */}
      {field.type === 'name' && (
        <div className="formtura-form-group">
          <label htmlFor="field-format">
            Format <Tooltip text="Choose how the name field should be displayed: as a single input or split into multiple parts." />
          </label>
          <select
            id="field-format"
            value={field.format || 'first-last'}
            onChange={(e) => handleChange('format', e.target.value)}
          >
            <option value="simple">Simple</option>
            <option value="first-last">First + Last Names</option>
            <option value="first-middle-last">First + Middle + Last Name</option>
          </select>
        </div>
      )}

      {/* Render field-specific options */}
      {renderFieldSpecificOptions()}

      {/* Description - WYSIWYG for HTML fields, skip for rich-text (content already shown), textarea for others */}
      {field.type === 'html' ? (
        <div className="formtura-form-group">
          <label>
            Content <Tooltip text="Enter HTML content that will be displayed in the form. Use the toolbar to format text, add links, and create lists." />
          </label>
          <WysiwygEditor
            value={field.description || ''}
            onChange={(html) => handleChange('description', html)}
          />
        </div>
      ) : field.type !== 'rich-text' && (
        <div className="formtura-form-group">
          <label htmlFor="field-description">
            Description <Tooltip text="Enter text for the form field description." />
          </label>
          <textarea
            id="field-description"
            value={field.description || ''}
            onChange={(e) => handleChange('description', e.target.value)}
            rows={4}
          />
        </div>
      )}

      {/* Rich Text Field - Field Size and Rows after Description area */}
      {field.type === 'rich-text' && (
        <div className="formtura-form-group" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
          <div>
            <label htmlFor="field-size-richtext">
              Field Size <Tooltip text="Set the width unit for the field." />
            </label>
            <select
              id="field-size-richtext"
              value={field.fieldSize || 'px'}
              onChange={(e) => handleChange('fieldSize', e.target.value)}
            >
              <option value="px">px</option>
              <option value="%">%</option>
              <option value="em">em</option>
              <option value="rem">rem</option>
            </select>
          </div>
          <div>
            <label htmlFor="field-rows">
              Rows <Tooltip text="Set the number of visible text rows for the editor." />
            </label>
            <input
              id="field-rows"
              type="number"
              min={1}
              value={field.rows || 7}
              onChange={(e) => handleChange('rows', parseInt(e.target.value) || 7)}
            />
          </div>
        </div>
      )}

      {/* Enable Summary for Total field - After Description */}
      {field.type === 'total' && (
        <div className="formtura-form-group">
          <div className="formtura-toggle-group">
            <label className="formtura-toggle">
              <input
                type="checkbox"
                checked={field.enableSummary || false}
                onChange={(e) => handleChange('enableSummary', e.target.checked)}
              />
              <span className="formtura-toggle-slider"></span>
            </label>
            <span className="formtura-toggle-label">
              Enable Summary <Tooltip text="Enable order summary for this field." />
            </span>
          </div>
          {field.enableSummary && (
            <p className="formtura-info-message" style={{
              marginTop: '0.75rem',
              padding: '0.75rem',
              background: '#e8f4fc',
              borderRadius: '4px',
              color: '#1e73be',
              fontSize: '13px',
              lineHeight: '1.5',
            }}>
              Example data is shown in the form editor. Actual products and totals will be displayed when you preview or embed your form.
            </p>
          )}
        </div>
      )}

      {/* Number Slider Fields - After Description */}
      {field.type === 'number-slider' && (
        <>
          <div className="formtura-form-group">
            <label>
              Value Range <Tooltip text="Define the minimum and maximum values for the slider." />
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
              Default Value <Tooltip text="Enter a default value for this field." />
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
              Increment <Tooltip text="Set the increment between selectable values on the slider." />
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
                Collapsible <Tooltip text="Collapsible: This section will slide open and closed." />
              </span>
            </div>
          </div>

          <div className="formtura-form-group">
            <label htmlFor="field-repeat-layout">
              Repeat Layout <Tooltip text="Choose how repeater rows are displayed." />
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
              Required <Tooltip text="Check this option to mark the field as required. The form will not submit unless all required fields are completed." />
            </span>
          </div>
        </div>
      )}

      {/* Unique toggle - shown for rating field */}
      {field.type === 'rating' && (
        <div className="formtura-form-group">
          <div className="formtura-toggle-group">
            <label className="formtura-toggle">
              <input
                type="checkbox"
                checked={field.unique || false}
                onChange={(e) => handleChange('unique', e.target.checked)}
              />
              <span className="formtura-toggle-slider"></span>
            </label>
            <span className="formtura-toggle-label">
              Unique <Tooltip text="Unique: Do not allow the same response multiple times. For example, if one user enters 'Joe', then no one else will be allowed to enter the same name." />
            </span>
          </div>
        </div>
      )}
    </div>
  );
};

// Style Classes Data
const styleClassesData = {
  layouts: [
    { label: '1/2', value: 'fta-one-half', width: '50%' },
    { label: '1/2', value: 'fta-one-half', width: '50%' },
    { label: '1/3', value: 'fta-one-third', width: '33.33%' },
    { label: '2/3', value: 'fta-two-thirds', width: '66.66%' },
    { label: '1/4', value: 'fta-one-fourth', width: '25%' },
    { label: '3/4', value: 'fta-three-fourths', width: '75%' },
    { label: '1/6', value: 'fta-one-sixth', width: '16.66%' },
    { label: '5/6', value: 'fta-five-sixths', width: '83.33%' },
    { label: '100%', value: 'fta-full', width: '100%' },
  ],
  otherStyles: [
    { label: 'Total', value: 'fta_total', tooltip: 'Add this to read-only field to display the text in bold without a border or background.' },
    { label: 'Big Total', value: 'fta_total_big', tooltip: 'Add this to read-only field to display the text in large, bold text without a border or background.' },
    { label: 'Scroll Box', value: 'fta_scroll_box', tooltip: 'If you have many checkbox or radio button options, you may add this class to allow your user to easily scroll through the options. Or add a scrolling area around content in an HTML field.' },
    { label: 'First', value: 'fta_first', tooltip: 'Add this to the first field in each row along with a width. ie fta_first fta4.' },
    { label: 'Right', value: 'fta_alignright' },
    { label: 'First Grid Row', value: 'fta_grid_first' },
    { label: 'Even Grid Row', value: 'fta_grid' },
    { label: 'Odd Grid Row', value: 'fta_grid_odd' },
    { label: 'Color Block', value: 'fta_color_block', tooltip: 'Add a background color to the field or section.' },
    { label: 'Capitalize', value: 'fta_capitalize', tooltip: 'Automatically capitalize the first letter in each word.' },
  ],
};

// Reusable CSS Layout Classes Field Component
const CSSLayoutClassesField = ({ field, onUpdate }) => {
  const [showStyleClasses, setShowStyleClasses] = React.useState(false);
  const containerRef = React.useRef(null);

  // Close dropdown when clicking outside
  React.useEffect(() => {
    const handleClickOutside = (event) => {
      if (containerRef.current && !containerRef.current.contains(event.target)) {
        setShowStyleClasses(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleChange = (value) => {
    onUpdate(field.id, { cssClasses: value });
  };

  const handleStyleClassSelect = (classValue) => {
    const currentClasses = field.cssClasses || '';
    const classesArray = currentClasses.split(' ').filter(c => c.trim());
    if (!classesArray.includes(classValue)) {
      const newClasses = currentClasses ? `${currentClasses} ${classValue}` : classValue;
      handleChange(newClasses);
    }
  };

  return (
    <div className="formtura-form-group formtura-css-layout-field" ref={containerRef}>
      <label htmlFor="field-css-classes">
        CSS Layout Classes <Tooltip text="Add a class for the form field container. Use our predefined classes to align multiple fields in a single row." />
      </label>
      <div className="formtura-input-with-button">
        <input
          id="field-css-classes"
          type="text"
          value={field.cssClasses || ''}
          onChange={(e) => handleChange(e.target.value)}
        />
        <button
          type="button"
          className="formtura-ellipsis-btn"
          onClick={() => setShowStyleClasses(!showStyleClasses)}
          title="Show style classes"
        >
          <MoreHorizontal size={16} />
        </button>
      </div>

      {showStyleClasses && (
        <div className="formtura-style-classes-dropdown">
          <div className="formtura-style-classes-section">
            <div className="formtura-style-classes-layouts">
              <div className="formtura-layout-row">
                <button type="button" className="formtura-layout-btn" style={{width: '50%'}} onClick={() => handleStyleClassSelect('fta-one-half')}>1/2</button>
                <button type="button" className="formtura-layout-btn" style={{width: '50%'}} onClick={() => handleStyleClassSelect('fta-one-half')}>1/2</button>
              </div>
              <div className="formtura-layout-row">
                <button type="button" className="formtura-layout-btn" style={{width: '33.33%'}} onClick={() => handleStyleClassSelect('fta-one-third')}>1/3</button>
                <button type="button" className="formtura-layout-btn" style={{width: '66.66%'}} onClick={() => handleStyleClassSelect('fta-two-thirds')}>2/3</button>
              </div>
              <div className="formtura-layout-row">
                <button type="button" className="formtura-layout-btn" style={{width: '25%'}} onClick={() => handleStyleClassSelect('fta-one-fourth')}>1/4</button>
                <button type="button" className="formtura-layout-btn" style={{width: '75%'}} onClick={() => handleStyleClassSelect('fta-three-fourths')}>3/4</button>
              </div>
              <div className="formtura-layout-row">
                <button type="button" className="formtura-layout-btn" style={{width: '16.66%'}} onClick={() => handleStyleClassSelect('fta-one-sixth')}>1/6</button>
                <button type="button" className="formtura-layout-btn" style={{width: '83.33%'}} onClick={() => handleStyleClassSelect('fta-five-sixths')}>5/6</button>
              </div>
              <div className="formtura-layout-row">
                <button type="button" className="formtura-layout-btn" style={{width: '100%'}} onClick={() => handleStyleClassSelect('fta-full')}>100%</button>
              </div>
            </div>
          </div>
          <div className="formtura-style-classes-section">
            <div className="formtura-style-classes-header">
              <span>Other Style Classes</span>
              <ChevronDown size={14} />
            </div>
            <div className="formtura-style-classes-list">
              {styleClassesData.otherStyles.map((item) => (
                <button
                  key={item.value}
                  type="button"
                  className="formtura-style-class-item"
                  onClick={() => handleStyleClassSelect(item.value)}
                  title={item.tooltip || ''}
                >
                  <span>{item.label}</span>
                  <span className="formtura-style-class-value">{item.value}</span>
                </button>
              ))}
            </div>
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

  // Name field has different Advanced options
  if (field.type === 'name') {
    const nameFormat = field.format || 'first-last';
    const showMultipleFields = nameFormat !== 'simple';

    const appendSmartTag = (fieldKey, tagValue) => {
      const currentValue = field[fieldKey] || '';
      handleChange(fieldKey, currentValue + tagValue);
    };

    return (
      <div className="formtura-field-options">
        <div className="formtura-field-options-title">
          <strong>{field.label}</strong> <span className="formtura-field-id">(ID #{field.id.slice(-4)})</span>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-size">
            Field Size <Tooltip text="Select the default size for the field." />
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

        {/* First Name Placeholder & Default */}
        {showMultipleFields && (
          <div className="formtura-form-group">
            <label>
              First Name <Tooltip text="Configure placeholder and default value for the first name input." />
            </label>
            <div className="formtura-name-field-row">
              <div className="formtura-name-field-col">
                <input
                  type="text"
                  value={field.firstNamePlaceholder || ''}
                  onChange={(e) => handleChange('firstNamePlaceholder', e.target.value)}
                  placeholder=""
                />
                <span className="formtura-field-help">Placeholder</span>
              </div>
              <div className="formtura-name-field-col">
                <div className="formtura-input-with-inline-tag">
                  <input
                    type="text"
                    value={field.firstNameDefault || ''}
                    onChange={(e) => handleChange('firstNameDefault', e.target.value)}
                    placeholder=""
                  />
                  <SmartTagButton onSelect={(tag) => appendSmartTag('firstNameDefault', tag)} />
                </div>
                <span className="formtura-field-help">Default Value</span>
              </div>
            </div>
          </div>
        )}

        {/* Middle Name Placeholder & Default - Always show for multi-field formats */}
        {showMultipleFields && (
          <div className="formtura-form-group">
            <label>
              Middle Name <Tooltip text="Configure placeholder and default value for the middle name input." />
            </label>
            <div className="formtura-name-field-row">
              <div className="formtura-name-field-col">
                <input
                  type="text"
                  value={field.middleNamePlaceholder || ''}
                  onChange={(e) => handleChange('middleNamePlaceholder', e.target.value)}
                  placeholder=""
                />
                <span className="formtura-field-help">Placeholder</span>
              </div>
              <div className="formtura-name-field-col">
                <div className="formtura-input-with-inline-tag">
                  <input
                    type="text"
                    value={field.middleNameDefault || ''}
                    onChange={(e) => handleChange('middleNameDefault', e.target.value)}
                    placeholder=""
                  />
                  <SmartTagButton onSelect={(tag) => appendSmartTag('middleNameDefault', tag)} />
                </div>
                <span className="formtura-field-help">Default Value</span>
              </div>
            </div>
          </div>
        )}

        {/* Last Name Placeholder & Default */}
        {showMultipleFields && (
          <div className="formtura-form-group">
            <label>
              Last Name <Tooltip text="Configure placeholder and default value for the last name input." />
            </label>
            <div className="formtura-name-field-row">
              <div className="formtura-name-field-col">
                <input
                  type="text"
                  value={field.lastNamePlaceholder || ''}
                  onChange={(e) => handleChange('lastNamePlaceholder', e.target.value)}
                  placeholder=""
                />
                <span className="formtura-field-help">Placeholder</span>
              </div>
              <div className="formtura-name-field-col">
                <div className="formtura-input-with-inline-tag">
                  <input
                    type="text"
                    value={field.lastNameDefault || ''}
                    onChange={(e) => handleChange('lastNameDefault', e.target.value)}
                    placeholder=""
                  />
                  <SmartTagButton onSelect={(tag) => appendSmartTag('lastNameDefault', tag)} />
                </div>
                <span className="formtura-field-help">Default Value</span>
              </div>
            </div>
          </div>
        )}

        <CSSLayoutClassesField field={field} onUpdate={onUpdate} />

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
              Hide Label <Tooltip text="Check this option to hide the form field label." />
            </span>
          </div>
        </div>

        <div className="formtura-form-group">
          <div className="formtura-toggle-group">
            <label className="formtura-toggle">
              <input
                type="checkbox"
                checked={field.hideSublabels || false}
                onChange={(e) => handleChange('hideSublabels', e.target.checked)}
              />
              <span className="formtura-toggle-slider"></span>
            </label>
            <span className="formtura-toggle-label">
              Hide Sublabels <Tooltip text="Check this option to hide sublabels under each name field input." />
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
              Read-Only <Tooltip text="Check this option to display the field's value without allowing changes. The value will still be submitted." />
            </span>
          </div>
        </div>
      </div>
    );
  }

  // Number Slider has different Advanced options
  if (field.type === 'number-slider') {
    return (
      <div className="formtura-field-options">
        <div className="formtura-field-options-title">
          <strong>{field.label}</strong> <span className="formtura-field-id">(ID #{field.id.slice(-4)})</span>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-size">
            Field Size <Tooltip text="Select the default size for the field." />
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
            Value Display <Tooltip text="Displays the currently selected value below the slider. Use {value} placeholder for the selected number." />
          </label>
          <input
            id="field-value-display"
            type="text"
            value={field.valueDisplay || 'Selected Value: {value}'}
            onChange={(e) => handleChange('valueDisplay', e.target.value)}
            placeholder="Selected Value: {value}"
          />
        </div>

        <CSSLayoutClassesField field={field} onUpdate={onUpdate} />

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
              Hide Label <Tooltip text="Check this option to hide the form field label." />
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
              Read-Only <Tooltip text="Check this option to display the field's value without allowing changes. The value will still be submitted." />
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
            Field Size <Tooltip text="Select the default size for the field." />
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

        <div className="formtura-form-group" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
          <div>
            <label htmlFor="field-add-label">
              Add New Label <Tooltip text="Text for the add button." />
            </label>
            <input
              id="field-add-label"
              type="text"
              value={field.addNewLabel || 'Add'}
              onChange={(e) => handleChange('addNewLabel', e.target.value)}
              placeholder="Add"
            />
          </div>
          <div>
            <label htmlFor="field-remove-label">
              Remove Label <Tooltip text="Text for the remove button." />
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

        <div className="formtura-form-group" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
          <div>
            <label htmlFor="field-min-rows">
              Min Repeater Rows <Tooltip text="Minimum number of repeater rows." />
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
          <div>
            <label htmlFor="field-max-rows">
              Max Repeater Rows <Tooltip text="The maximum number of times the end user is allowed to duplicate this section of fields in one entry." />
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

        <CSSLayoutClassesField field={field} onUpdate={onUpdate} />

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
              Hide Label <Tooltip text="Check this option to hide the form field label." />
            </span>
          </div>
        </div>
      </div>
    );
  }

  // Dropdown field has different Advanced options
  if (field.type === 'select') {
    return (
      <div className="formtura-field-options">
        <div className="formtura-field-options-title">
          <strong>{field.label}</strong> <span className="formtura-field-id">(ID #{field.id.slice(-4)})</span>
        </div>

        <div className="formtura-form-group">
          <div className="formtura-toggle-group">
            <label className="formtura-toggle">
              <input
                type="checkbox"
                checked={field.multipleSelection || false}
                onChange={(e) => handleChange('multipleSelection', e.target.checked)}
              />
              <span className="formtura-toggle-slider"></span>
            </label>
            <span className="formtura-toggle-label">
              Multiple Options Selection <Tooltip text="Allow users to select multiple choices in this field." />
            </span>
          </div>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-style">
            Style <Tooltip text="Select the visual style for the dropdown field." />
          </label>
          <select
            id="field-style"
            value={field.style || 'classic'}
            onChange={(e) => handleChange('style', e.target.value)}
          >
            <option value="classic">Classic</option>
            <option value="modern">Modern</option>
          </select>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-size">
            Field Size <Tooltip text="Select the default size for the field." />
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
            Placeholder Text <Tooltip text="Enter placeholder text that appears as the first option in the dropdown." />
          </label>
          <input
            id="field-placeholder"
            type="text"
            value={field.placeholder || ''}
            onChange={(e) => handleChange('placeholder', e.target.value)}
          />
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-dynamic-choices">
            Dynamic Choices <Tooltip text="Select auto-populate method to use." />
          </label>
          <select
            id="field-dynamic-choices"
            value={field.dynamicChoices || 'off'}
            onChange={(e) => handleChange('dynamicChoices', e.target.value)}
          >
            <option value="off">Off</option>
            <option value="post_type">Post Type</option>
            <option value="taxonomy">Taxonomy</option>
          </select>
        </div>

        {field.dynamicChoices === 'post_type' && (
          <div className="formtura-form-group">
            <label htmlFor="field-dynamic-post-type">
              Dynamic Post Type Source <Tooltip text="Select Post Type to use for auto-populating the field choices." />
            </label>
            <select
              id="field-dynamic-post-type"
              value={field.dynamicPostType || 'post'}
              onChange={(e) => handleChange('dynamicPostType', e.target.value)}
            >
              <option value="post">Posts</option>
              <option value="page">Pages</option>
              <option value="attachment">Media</option>
              {/* Custom post types loaded dynamically from WordPress */}
              {window.formturaBuilderData?.postTypes?.map(pt => (
                <option key={pt.value} value={pt.value}>{pt.label}</option>
              ))}
            </select>
          </div>
        )}

        {field.dynamicChoices === 'taxonomy' && (
          <div className="formtura-form-group">
            <label htmlFor="field-dynamic-taxonomy">
              Dynamic Taxonomy Source <Tooltip text="Select Taxonomy to use for auto-populating the field choices." />
            </label>
            <select
              id="field-dynamic-taxonomy"
              value={field.dynamicTaxonomy || 'category'}
              onChange={(e) => handleChange('dynamicTaxonomy', e.target.value)}
            >
              <option value="category">Categories</option>
              <option value="post_tag">Tags</option>
              {/* Custom taxonomies loaded dynamically from WordPress */}
              {window.formturaBuilderData?.taxonomies?.map(tax => (
                <option key={tax.value} value={tax.value}>{tax.label}</option>
              ))}
            </select>
          </div>
        )}

        <CSSLayoutClassesField field={field} onUpdate={onUpdate} />

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
              Hide Label <Tooltip text="Check this option to hide the form field label." />
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
              Read-Only <Tooltip text="Check this option to display the field's value without allowing changes. The value will still be submitted." />
            </span>
          </div>
        </div>
      </div>
    );
  }

  // Number field has different Advanced options
  if (field.type === 'number') {
    const appendSmartTag = (fieldKey, tagValue) => {
      const currentValue = field[fieldKey] || '';
      handleChange(fieldKey, currentValue + tagValue);
    };

    return (
      <div className="formtura-field-options">
        <div className="formtura-field-options-title">
          <strong>{field.label}</strong> <span className="formtura-field-id">(ID #{field.id.slice(-4)})</span>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-size">
            Field Size <Tooltip text="Select the default size for the field." />
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
            Placeholder Text <Tooltip text="Enter placeholder text that appears inside the input field before the user types." />
          </label>
          <input
            id="field-placeholder"
            type="text"
            value={field.placeholder || ''}
            onChange={(e) => handleChange('placeholder', e.target.value)}
          />
        </div>

        <div className="formtura-form-group">
          <label>
            Range <Tooltip text="Define the minimum and the maximum values for the field." />
          </label>
          <div className="formtura-range-row">
            <div className="formtura-range-col">
              <input
                type="number"
                value={field.minValue !== undefined ? field.minValue : ''}
                onChange={(e) => handleChange('minValue', e.target.value === '' ? undefined : parseFloat(e.target.value))}
                placeholder=""
              />
              <span className="formtura-field-help">Minimum</span>
            </div>
            <div className="formtura-range-col">
              <input
                type="number"
                value={field.maxValue !== undefined ? field.maxValue : ''}
                onChange={(e) => handleChange('maxValue', e.target.value === '' ? undefined : parseFloat(e.target.value))}
                placeholder=""
              />
              <span className="formtura-field-help">Maximum</span>
            </div>
          </div>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-default-value">
            Default Value <Tooltip text="Enter text for the default form field value." />
          </label>
          <div className="formtura-input-with-inline-tag">
            <input
              id="field-default-value"
              type="text"
              value={field.defaultValue !== undefined ? field.defaultValue : ''}
              onChange={(e) => handleChange('defaultValue', e.target.value)}
              placeholder=""
            />
            <SmartTagButton onSelect={(tag) => appendSmartTag('defaultValue', tag)} />
          </div>
        </div>

        <CSSLayoutClassesField field={field} onUpdate={onUpdate} />

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
              Hide Label <Tooltip text="Check this option to hide the form field label." />
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
              Read-Only <Tooltip text="Check this option to display the field's value without allowing changes. The value will still be submitted." />
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
              Enable Calculation <Tooltip text="Enable mathematical calculations using values from other form fields." />
            </span>
          </div>
        </div>

        {field.enableCalculation && (
          <div className="formtura-form-group">
            <label htmlFor="field-calculation-formula">
              Calculation Formula <Tooltip text="Enter a mathematical formula using field IDs. Example: {field_1} + {field_2} * 2. Supported operators: +, -, *, /, (). Use {field_ID} to reference other number fields." />
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
              Use <code>{'{field_ID}'}</code> to reference other number fields. Supported operators: <code>+</code>, <code>-</code>, <code>*</code>, <code>/</code>, <code>()</code>
            </p>
          </div>
        )}
      </div>
    );
  }

  // Multiple Choice (checkbox/radio) and Checkboxes fields have different Advanced options
  if (field.type === 'checkbox' || field.type === 'checkboxes' || field.type === 'radio') {
    return (
      <div className="formtura-field-options">
        <div className="formtura-field-options-title">
          <strong>{field.label}</strong> <span className="formtura-field-id">(ID #{field.id.slice(-4)})</span>
        </div>

        <div className="formtura-form-group">
          <div className="formtura-toggle-group">
            <label className="formtura-toggle">
              <input
                type="checkbox"
                checked={field.randomizeChoices || false}
                onChange={(e) => handleChange('randomizeChoices', e.target.checked)}
              />
              <span className="formtura-toggle-slider"></span>
            </label>
            <span className="formtura-toggle-label">
              Randomize Choices <Tooltip text="Check this option to randomize the order of the choices." />
            </span>
          </div>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-choice-layout">
            Choice Layout <Tooltip text="Select the layout for displaying field choices." />
          </label>
          <select
            id="field-choice-layout"
            value={field.choiceLayout || 'one-column'}
            onChange={(e) => handleChange('choiceLayout', e.target.value)}
          >
            <option value="one-column">One Column</option>
            <option value="two-columns">Two Columns</option>
            <option value="three-columns">Three Columns</option>
            <option value="inline">Inline</option>
          </select>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-dynamic-choices">
            Dynamic Choices <Tooltip text="Select auto-populate method to use." />
          </label>
          <select
            id="field-dynamic-choices"
            value={field.dynamicChoices || 'off'}
            onChange={(e) => handleChange('dynamicChoices', e.target.value)}
          >
            <option value="off">Off</option>
            <option value="post_type">Post Type</option>
            <option value="taxonomy">Taxonomy</option>
          </select>
        </div>

        {field.dynamicChoices === 'post_type' && (
          <div className="formtura-form-group">
            <label htmlFor="field-dynamic-post-type">
              Dynamic Post Type Source <Tooltip text="Select Post Type to use for auto-populating the field choices." />
            </label>
            <select
              id="field-dynamic-post-type"
              value={field.dynamicPostType || 'post'}
              onChange={(e) => handleChange('dynamicPostType', e.target.value)}
            >
              <option value="post">Posts</option>
              <option value="page">Pages</option>
              <option value="attachment">Media</option>
              {/* Custom post types loaded dynamically from WordPress */}
              {window.formturaBuilderData?.postTypes?.map(pt => (
                <option key={pt.value} value={pt.value}>{pt.label}</option>
              ))}
            </select>
          </div>
        )}

        {field.dynamicChoices === 'taxonomy' && (
          <div className="formtura-form-group">
            <label htmlFor="field-dynamic-taxonomy">
              Dynamic Taxonomy Source <Tooltip text="Select Taxonomy to use for auto-populating the field choices." />
            </label>
            <select
              id="field-dynamic-taxonomy"
              value={field.dynamicTaxonomy || 'category'}
              onChange={(e) => handleChange('dynamicTaxonomy', e.target.value)}
            >
              <option value="category">Categories</option>
              <option value="post_tag">Tags</option>
              {/* Custom taxonomies loaded dynamically from WordPress */}
              {window.formturaBuilderData?.taxonomies?.map(tax => (
                <option key={tax.value} value={tax.value}>{tax.label}</option>
              ))}
            </select>
          </div>
        )}

        <CSSLayoutClassesField field={field} onUpdate={onUpdate} />

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
              Hide Label <Tooltip text="Check this option to hide the form field label." />
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
              Read-Only <Tooltip text="Check this option to display the field's value without allowing changes. The value will still be submitted." />
            </span>
          </div>
        </div>
      </div>
    );
  }

  // HTML Field has specific Advanced options
  if (field.type === 'html') {
    return (
      <div className="formtura-field-options">
        <div className="formtura-field-options-title">
          <strong>{field.label}</strong> <span className="formtura-field-id">(ID #{field.id.slice(-4)})</span>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-visibility">
            Visibility <Tooltip text="Determines who can see this field. Select 'Everyone' for public visibility or choose specific user roles." />
          </label>
          <select
            id="field-visibility"
            value={field.visibility || 'everyone'}
            onChange={(e) => handleChange('visibility', e.target.value)}
          >
            <option value="everyone">Everyone</option>
            <option value="logged_in">Logged In Users</option>
            {window.formturaBuilderData?.userRoles?.map(role => (
              <option key={role.value} value={role.value}>{role.label}</option>
            ))}
          </select>
        </div>

        <CSSLayoutClassesField field={field} onUpdate={onUpdate} />

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
              Hide Label <Tooltip text="Check this option to hide the form field label." />
            </span>
          </div>
        </div>
      </div>
    );
  }

  // Star Rating field has specific Advanced options
  if (field.type === 'rating') {
    return (
      <div className="formtura-field-options">
        <div className="formtura-field-options-title">
          <strong>{field.label}</strong> <span className="formtura-field-id">(ID #{field.id.slice(-4)})</span>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-size">
            Field Size <Tooltip text="Select the default size for the field." />
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
          <label htmlFor="max-rating">
            Maximum Rating <Tooltip text="Set the maximum number of stars that will be displayed in the rating field." />
          </label>
          <input
            id="max-rating"
            type="number"
            min={1}
            max={10}
            value={field.maxRating || 5}
            onChange={(e) => handleChange('maxRating', parseInt(e.target.value) || 5)}
          />
        </div>

        <CSSLayoutClassesField field={field} onUpdate={onUpdate} />

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
              Hide Label <Tooltip text="Check this option to hide the form field label." />
            </span>
          </div>
        </div>
      </div>
    );
  }

  // Date/Time field has specific Advanced options
  if (field.type === 'datetime') {
    return (
      <div className="formtura-field-options">
        <div className="formtura-field-options-title">
          <strong>{field.label}</strong> <span className="formtura-field-id">(ID #{field.id.slice(-4)})</span>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-size">
            Field Size <Tooltip text="Select the default size for the field." />
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
          <label>
            Year Range <Tooltip text="Use four digit years or +/- years to make it dynamic. For example, use -5 for the start of the year and +5 for the end of the year." />
          </label>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
            <input
              id="year-range-start"
              type="text"
              value={field.yearRangeStart || '-10'}
              onChange={(e) => handleChange('yearRangeStart', e.target.value)}
              placeholder="-10"
            />
            <input
              id="year-range-end"
              type="text"
              value={field.yearRangeEnd || '+10'}
              onChange={(e) => handleChange('yearRangeEnd', e.target.value)}
              placeholder="+10"
            />
          </div>
        </div>

        <CSSLayoutClassesField field={field} onUpdate={onUpdate} />

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
              Hide Label <Tooltip text="Check this option to hide the form field label." />
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
          Field Size <Tooltip text="Select the default size for the field." />
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
          Placeholder Text <Tooltip text="Enter placeholder text that appears inside the input field before the user types." />
        </label>
        <input
          id="field-placeholder"
          type="text"
          value={field.placeholder || ''}
          onChange={(e) => handleChange('placeholder', e.target.value)}
        />
      </div>

      <CSSLayoutClassesField field={field} onUpdate={onUpdate} />

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
            Hide Label <Tooltip text="Check this option to hide the form field label." />
          </span>
        </div>
      </div>

      {/* Read-Only - Not shown for Total field */}
      {field.type !== 'total' && (
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
              Read-Only <Tooltip text="Check this option to display the field's value without allowing changes. The value will still be submitted." />
            </span>
          </div>
        </div>
      )}

      {/* Enable Address Autocomplete - Not shown for Total, Rating, or DateTime field */}
      {field.type !== 'total' && field.type !== 'rating' && field.type !== 'datetime' && (
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
              Enable Address Autocomplete <Tooltip text="Enable Google Maps autocomplete for address fields to help users quickly enter their address." />
            </span>
          </div>
        </div>
      )}

      {/* Enable Calculation - Not shown for Total, Rating, or DateTime field */}
      {field.type !== 'total' && field.type !== 'rating' && field.type !== 'datetime' && (
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
              Enable Calculation <Tooltip text="Enable mathematical calculations using values from other form fields." />
            </span>
          </div>
        </div>
      )}

      {field.enableCalculation && field.type !== 'total' && (
        <div className="formtura-form-group">
          <label htmlFor="field-calculation-formula">
            Calculation Formula <Tooltip text="Enter a mathematical formula using field IDs. Example: {field_1} + {field_2} * 2. Supported operators: +, -, *, /, (). Use {field_ID} to reference other number fields." />
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
            Use <code>{'{field_ID}'}</code> to reference other number fields. Supported operators: <code>+</code>, <code>-</code>, <code>*</code>, <code>/</code>, <code>()</code>
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
              Enable Conditional Logic <Tooltip text="Show or hide this field based on the values entered in other form fields." />
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
              Dynamic Default Value <Tooltip text="Automatically populate this field with a value from another field when the form loads." />
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
              Enable/Disable Based on Logic <Tooltip text="Enable or disable this field based on conditions set from other form field values." />
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
              Custom Validation Rules <Tooltip text="Apply custom validation logic that checks this field's value against conditions from other fields." />
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
              Branching/Skip Logic <Tooltip text="Redirect users to different form pages based on their answer to this field." />
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
