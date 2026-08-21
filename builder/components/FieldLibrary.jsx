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
import { createPortal } from 'react-dom';
import InfoDialog from './InfoDialog';
import { __, sprintf } from '../utils/i18n';

// Tooltip Component
const Tooltip = ({ text, children }) => {
  const [isVisible, setIsVisible] = React.useState(false);
  const [position, setPosition] = React.useState({
    block: '0rem',
    inline: '0rem',
    placement: 'top',
    ready: false,
  });
  const triggerRef = React.useRef(null);
  const tooltipRef = React.useRef(null);
  const tooltipId = React.useId();

  const updatePosition = React.useCallback(() => {
    if (!triggerRef.current || !tooltipRef.current) {
      return;
    }

    const triggerBounds = triggerRef.current.getBoundingClientRect();
    const tooltipBounds = tooltipRef.current.getBoundingClientRect();
    const rootFontSize = Number.parseFloat(
      window.getComputedStyle(document.documentElement).fontSize
    ) || 16;
    const isRtl = window.getComputedStyle(document.documentElement).direction === 'rtl';
    const viewportPadding = rootFontSize * 0.75;
    const tooltipGap = rootFontSize * 0.5;
    const fitsAbove = triggerBounds.top - tooltipBounds.height - tooltipGap >= viewportPadding;
    const fitsBelow = triggerBounds.bottom + tooltipBounds.height + tooltipGap <= window.innerHeight - viewportPadding;
    const placement = fitsAbove || !fitsBelow ? 'top' : 'bottom';
    const unclampedBlock = placement === 'top'
      ? triggerBounds.top - tooltipBounds.height - tooltipGap
      : triggerBounds.bottom + tooltipGap;
    const unclampedInline = triggerBounds.left + (triggerBounds.width / 2) - (tooltipBounds.width / 2);
    const maxInline = window.innerWidth - tooltipBounds.width - viewportPadding;
    const block = Math.max(
      viewportPadding,
      Math.min(unclampedBlock, window.innerHeight - tooltipBounds.height - viewportPadding)
    );
    const inline = Math.max(viewportPadding, Math.min(unclampedInline, maxInline));
    const logicalInline = isRtl
      ? window.innerWidth - inline - tooltipBounds.width
      : inline;

    setPosition({
      block: `${block / rootFontSize}rem`,
      inline: `${logicalInline / rootFontSize}rem`,
      placement,
      ready: true,
    });
  }, []);

  React.useLayoutEffect(() => {
    if (!isVisible) {
      return undefined;
    }

    const animationFrame = window.requestAnimationFrame(updatePosition);
    window.addEventListener('resize', updatePosition);
    document.addEventListener('scroll', updatePosition, true);

    return () => {
      window.cancelAnimationFrame(animationFrame);
      window.removeEventListener('resize', updatePosition);
      document.removeEventListener('scroll', updatePosition, true);
    };
  }, [isVisible, updatePosition]);

  return (
    <span
      className="formtura-tooltip-wrapper"
      onMouseEnter={() => {
        setPosition((current) => ({ ...current, ready: false }));
        setIsVisible(true);
      }}
      onMouseLeave={() => setIsVisible(false)}
      onFocus={() => {
        setPosition((current) => ({ ...current, ready: false }));
        setIsVisible(true);
      }}
      onBlur={() => setIsVisible(false)}
      onKeyDown={(event) => {
        if (event.key === 'Escape') {
          setIsVisible(false);
        }
      }}
      ref={triggerRef}
    >
      {children || (
        <button
          type="button"
          className="formtura-help-trigger"
          aria-label={__('Help', 'formtura')}
          aria-describedby={isVisible ? tooltipId : undefined}
        >
          <HelpCircle className="formtura-help-icon" aria-hidden="true" />
        </button>
      )}
      {isVisible && typeof document !== 'undefined' && createPortal(
        <span
          id={tooltipId}
          ref={tooltipRef}
          className="formtura-tooltip"
          role="tooltip"
          data-placement={position.placement}
          data-ready={position.ready}
          style={{
            insetBlockStart: position.block,
            insetInlineStart: position.inline,
          }}
        >
          {text}
        </span>,
        document.body
      )}
    </span>
  );
};

// Smart Tags data
const smartTagsData = [
  { category: __('OTHER', 'formtura'), tags: [
    { label: __('Site Administrator Email', 'formtura'), value: '{admin_email}' },
    { label: __('Form ID', 'formtura'), value: '{form_id}' },
    { label: __('Form Name', 'formtura'), value: '{form_name}' },
    { label: __('Embedded Post/Page Title', 'formtura'), value: '{page_title}' },
    { label: __('Embedded Post/Page URL', 'formtura'), value: '{page_url}' },
    { label: __('Embedded Post/Page ID', 'formtura'), value: '{page_id}' },
    { label: __('Date', 'formtura'), value: '{date}' },
    { label: __('Query String Variable', 'formtura'), value: '{query_var key=""}' },
    { label: __('User IP Address', 'formtura'), value: '{user_ip}' },
    { label: __('User ID', 'formtura'), value: '{user_id}' },
    { label: __('User Display Name', 'formtura'), value: '{user_display_name}' },
    { label: __('User Full Name', 'formtura'), value: '{user_full_name}' },
    { label: __('User First Name', 'formtura'), value: '{user_first_name}' },
    { label: __('User Last Name', 'formtura'), value: '{user_last_name}' },
    { label: __("Logged-in User's Email", 'formtura'), value: '{user_email}' },
    { label: __('User Meta', 'formtura'), value: '{user_meta key=""}' },
    { label: __('Author ID', 'formtura'), value: '{author_id}' },
    { label: __('Author Name', 'formtura'), value: '{author_name}' },
    { label: __('Author Email', 'formtura'), value: '{author_email}' },
    { label: __('Referrer URL', 'formtura'), value: '{referrer_url}' },
    { label: __('Login URL', 'formtura'), value: '{login_url}' },
    { label: __('Logout URL', 'formtura'), value: '{logout_url}' },
    { label: __('Register URL', 'formtura'), value: '{register_url}' },
    { label: __('Lost Password URL', 'formtura'), value: '{lost_password_url}' },
    { label: __('Unique Value', 'formtura'), value: '{unique_value}' },
    { label: __('Site Name', 'formtura'), value: '{site_name}' },
    { label: __('Order Summary', 'formtura'), value: '{order_summary}' },
  ]},
];

// SmartTagsPopup Component
const SmartTagsPopup = ({ isOpen, onClose, onSelect }) => {
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
    >
      <div className="formtura-smart-tags-header">
        <strong>{__('Smart Tags', 'formtura')}</strong>
      </div>
      <div className="formtura-smart-tags-search">
        <input
          type="text"
          placeholder={__('Search', 'formtura')}
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
    <div className="formtura-popover-anchor">
      <button
        ref={buttonRef}
        type="button"
        className="formtura-smart-tag-btn"
        title={__('Smart Tags', 'formtura')}
        onClick={() => setIsOpen(!isOpen)}
      >
        <Tag size={16} />
      </button>
      <SmartTagsPopup
        isOpen={isOpen}
        onClose={() => setIsOpen(false)}
        onSelect={onSelect}
      />
    </div>
  );
};

const fieldTypes = [
  {
    category: __('Standard Fields', 'formtura'),
    fields: [
      { type: 'text', label: __('Single Line Text', 'formtura'), icon: Type },
      { type: 'textarea', label: __('Paragraph Text', 'formtura'), icon: MessageSquare },
      { type: 'name', label: __('Name', 'formtura'), icon: User },
      { type: 'email', label: __('Email', 'formtura'), icon: Mail },
      { type: 'select', label: __('Dropdown', 'formtura'), icon: ChevronDown },
      { type: 'radio', label: __('Multiple Choice', 'formtura'), icon: Circle },
      { type: 'checkbox', label: __('Checkboxes', 'formtura'), icon: CheckSquare },
      { type: 'number', label: __('Numbers', 'formtura'), icon: Hash },
      { type: 'phone', label: __('Phone', 'formtura'), icon: Phone },
      { type: 'website', label: __('Website / URL', 'formtura'), icon: Globe },
      { type: 'html', label: __('HTML', 'formtura'), icon: Code },
      { type: 'hidden', label: __('Hidden Field', 'formtura'), icon: Eye },
      { type: 'captcha', label: __('CAPTCHA', 'formtura'), icon: Lock },
    ],
  },
  {
    category: __('Advanced Fields', 'formtura'),
    fields: [
      { type: 'address', label: __('Address', 'formtura'), icon: MapPin },
      { type: 'datetime', label: __('Date / Time', 'formtura'), icon: Calendar },
      { type: 'password', label: __('Password', 'formtura'), icon: Lock },
      { type: 'file-upload', label: __('File Upload', 'formtura'), icon: Upload },
      { type: 'camera', label: __('Camera', 'formtura'), icon: Camera },
      { type: 'layout', label: __('Layout', 'formtura'), icon: Layout },
      { type: 'repeater', label: __('Repeater', 'formtura'), icon: Circle },
      { type: 'page-break', label: __('Page Break', 'formtura'), icon: Minus },
      { type: 'section-divider', label: __('Section Divider', 'formtura'), icon: Minus },
      { type: 'rich-text', label: __('Rich Text', 'formtura'), icon: FileText },
      { type: 'content', label: __('Content', 'formtura'), icon: FileText },
      { type: 'entry-preview', label: __('Entry Preview', 'formtura'), icon: Eye },
      { type: 'signature', label: __('Signature', 'formtura'), icon: PenTool },
      { type: 'rating', label: __('Star Rating', 'formtura'), icon: Star },
      { type: 'number-slider', label: __('Slider', 'formtura'), icon: TrendingUp },
    ],
  },
  {
    category: __('Payment Fields', 'formtura'),
    fields: [
      { type: 'payment-single', label: __('Single Item', 'formtura'), icon: DollarSign },
      { type: 'payment-checkbox', label: __('Checkbox Items', 'formtura'), icon: CheckSquare },
      { type: 'payment-multiple', label: __('Multiple Items', 'formtura'), icon: ShoppingCart },
      { type: 'payment-dropdown', label: __('Dropdown Items', 'formtura'), icon: ChevronDown },
      { type: 'paypal', label: __('PayPal Commerce', 'formtura'), icon: CreditCard },
      { type: 'stripe', label: __('Stripe Credit Card', 'formtura'), icon: CreditCard },
      { type: 'square', label: __('Square', 'formtura'), icon: CreditCard },
      { type: 'authorize-net', label: __('Authorize.Net', 'formtura'), icon: CreditCard },
      { type: 'coupon', label: __('Coupon', 'formtura'), icon: DollarSign },
      { type: 'total', label: __('Total', 'formtura'), icon: DollarSign },
    ],
  },
];

/**
 * Palette types with no frontend template, and what is actually missing.
 *
 * These are draggable-looking but unbuildable: placed on a form they render
 * nothing on the public site (see doc/CHECKLIST.md, "In the palette, no
 * frontend template"). They are gated the same way the payment gateway types
 * are - a ClickableField opening an info dialog rather than adding the field -
 * but the copy deliberately points at no settings screen, because unlike the
 * gateways there is no setting anywhere that would turn these on.
 *
 * One shared dialog rather than the gateways' one-boolean-per-type, since the
 * only thing that varies is this copy.
 */
const unavailableFieldTypes = {
  repeater: {
    label: __('Repeater', 'formtura'),
    reason: __('A repeater needs to hold other fields, and the builder cannot place a field inside another field yet. Until it can, a repeater would have nothing to repeat.', 'formtura'),
  },
  layout: {
    label: __('Layout', 'formtura'),
    reason: __('Layout rows are part of the multi-page form subsystem, which Formtura does not have yet. To place fields side by side today, use the width classes under a field’s Advanced tab.', 'formtura'),
  },
  'page-break': {
    label: __('Page Break', 'formtura'),
    reason: __('A page break needs multi-page forms, which Formtura does not have yet. Every form is a single page for now, so there is nothing to break.', 'formtura'),
  },
  'entry-preview': {
    label: __('Entry Preview', 'formtura'),
    reason: __('An entry preview shows a visitor their answers before submitting, which needs the multi-page form subsystem Formtura does not have yet.', 'formtura'),
  },
};

const DraggableField = ({ type, label, icon: Icon, onAdd }) => {
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
    id: `library-${type}`,
    data: { type },
  });

  return (
    <button
      type="button"
      ref={setNodeRef}
      {...listeners}
      {...attributes}
      className={`formtura-field-item ${isDragging ? 'dragging' : ''}`}
      aria-label={sprintf(__('Add %s field', 'formtura'), label)}
      onClick={() => onAdd(type)}
    >
      <span className="formtura-field-icon-wrap">
        <Icon className="formtura-field-icon" aria-hidden="true" />
      </span>
      <span className="formtura-field-label">{label}</span>
      <GripVertical className="formtura-field-grip" aria-hidden="true" />
    </button>
  );
};

// Non-draggable field that triggers a click action (for CAPTCHA, etc.)
const ClickableField = ({ label, icon: Icon, onClick }) => {
  return (
    <button
      type="button"
      className="formtura-field-item formtura-field-item-clickable"
      onClick={onClick}
    >
      <span className="formtura-field-icon-wrap">
        <Icon className="formtura-field-icon" aria-hidden="true" />
      </span>
      <span className="formtura-field-label">{label}</span>
      <ChevronRight className="formtura-field-grip" aria-hidden="true" />
    </button>
  );
};

const FieldLibrary = ({
  selectedField,
  fields,
  onFieldUpdate,
  isCollapsed,
  onToggleCollapse,
  onFieldAdd,
}) => {
  const [searchTerm, setSearchTerm] = React.useState('');
  const [activeTab, setActiveTab] = React.useState('add'); // 'add' or 'options'
  const [optionsTab, setOptionsTab] = React.useState('general'); // 'general', 'advanced', 'smart-logic'
  const [collapsedGroups, setCollapsedGroups] = React.useState({}); // Track which groups are collapsed
  const [showCaptchaDialog, setShowCaptchaDialog] = React.useState(false); // CAPTCHA info dialog
  const [showStripeDialog, setShowStripeDialog] = React.useState(false); // Stripe info dialog
  const [showPayPalDialog, setShowPayPalDialog] = React.useState(false); // PayPal info dialog
  const [showSquareDialog, setShowSquareDialog] = React.useState(false); // Square info dialog
  const [showAuthorizeNetDialog, setShowAuthorizeNetDialog] = React.useState(false); // Authorize.Net info dialog
  const [unavailableType, setUnavailableType] = React.useState(null); // Palette type with no frontend template

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
    <aside
      className={`formtura-sidebar ${isCollapsed ? 'collapsed' : ''}`}
      aria-label={__('Builder component library', 'formtura')}
    >
      <button
        className="formtura-sidebar-collapse-btn"
        onClick={onToggleCollapse}
        type="button"
        title={isCollapsed ? __('Expand sidebar', 'formtura') : __('Collapse sidebar', 'formtura')}
        aria-label={isCollapsed ? __('Expand component library', 'formtura') : __('Collapse component library', 'formtura')}
        aria-expanded={!isCollapsed}
      >
        {isCollapsed ? <ChevronRight size={20} /> : <ChevronLeft size={20} />}
      </button>

      {!isCollapsed && (
        <>
          <div className="formtura-sidebar-header">
            <div className="formtura-sidebar-heading">
              <p className="formtura-sidebar-kicker">{__('Build', 'formtura')}</p>
              <h2>{activeTab === 'add' ? __('Component library', 'formtura') : __('Field settings', 'formtura')}</h2>
            </div>
            <div className="formtura-panel-toggle">
              <button
                className={`formtura-panel-toggle-btn ${activeTab === 'add' ? 'active' : ''}`}
                onClick={() => setActiveTab('add')}
                type="button"
                aria-pressed={activeTab === 'add'}
              >
                <List size={16} />
                {__('Add Fields', 'formtura')}
              </button>
              <button
                className={`formtura-panel-toggle-btn ${activeTab === 'options' ? 'active' : ''}`}
                onClick={() => setActiveTab('options')}
                disabled={!field}
                type="button"
                aria-pressed={activeTab === 'options'}
              >
                <Settings size={16} />
                {__('Field Options', 'formtura')}
              </button>
            </div>
          </div>

          {activeTab === 'add' && (
            <>
              <div className="formtura-sidebar-search">
                <label className="formtura-sr-only" htmlFor="formtura-field-search">
                  {__('Search available fields', 'formtura')}
                </label>
                <input
                  id="formtura-field-search"
                  type="search"
                  placeholder={__('Search fields...', 'formtura')}
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
                      aria-expanded={!collapsedGroups[group.category]}
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
                          // CAPTCHA field shows a popup instead of being draggable
                          if (fieldItem.type === 'captcha') {
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
                          // Authorize.Net field shows a popup for connection setup
                          if (fieldItem.type === 'authorize-net') {
                            return (
                              <ClickableField
                                key={fieldItem.type}
                                type={fieldItem.type}
                                label={fieldItem.label}
                                icon={fieldItem.icon}
                                onClick={() => setShowAuthorizeNetDialog(true)}
                              />
                            );
                          }
                          // Types with no frontend template: placing one
                          // would render nothing on the public site, so say
                          // what is missing instead of adding the field.
                          if (unavailableFieldTypes[fieldItem.type]) {
                            return (
                              <ClickableField
                                key={fieldItem.type}
                                type={fieldItem.type}
                                label={fieldItem.label}
                                icon={fieldItem.icon}
                                onClick={() => setUnavailableType(fieldItem.type)}
                              />
                            );
                          }
                          return (
                            <DraggableField
                              key={fieldItem.type}
                              type={fieldItem.type}
                              label={fieldItem.label}
                              icon={fieldItem.icon}
                              onAdd={onFieldAdd}
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
                    <p>{__('Select a field to view options', 'formtura')}</p>
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
                      {__('General', 'formtura')}
                    </button>
                    <button
                      className={`formtura-settings-tab ${optionsTab === 'advanced' ? 'active' : ''}`}
                      onClick={() => setOptionsTab('advanced')}
                      type="button"
                    >
                      {__('Advanced', 'formtura')}
                    </button>
                    <button
                      className={`formtura-settings-tab ${optionsTab === 'smart-logic' ? 'active' : ''}`}
                      onClick={() => setOptionsTab('smart-logic')}
                      type="button"
                    >
                      {__('Smart Logic', 'formtura')}
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
                      <SmartLogicTab field={field} fields={fields} onUpdate={onFieldUpdate} />
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
        title={__('Heads up!', 'formtura')}
        message={
          <>
            {__('Please complete the CAPTCHA setup in your', 'formtura')}{' '}
            <a
              href={`${window.formturaBuilder?.adminUrl || '/wp-admin/'}admin.php?page=formtura-settings&tab=captcha`}
            >
              {__('Formtura Settings', 'formtura')}
            </a>
            {' '}{__('to enable CAPTCHA protection on your forms.', 'formtura')}
          </>
        }
        buttonText={__('OK', 'formtura')}
        onClose={() => setShowCaptchaDialog(false)}
      />

      {/* Stripe Info Dialog */}
      <InfoDialog
        isOpen={showStripeDialog}
        title={__('Heads up!', 'formtura')}
        message={
          <>
            <p className="formtura-dialog-lead">
              {__('Stripe account connection is required when using the Stripe Credit Card field.', 'formtura')}
            </p>
            <p>
              {__('To proceed, please go to', 'formtura')}{' '}
              <a
                href={`${window.formturaBuilder?.adminUrl || '/wp-admin/'}admin.php?page=formtura-settings&tab=payments`}
              >
                {__('Formtura Settings » Payments » Stripe', 'formtura')}
              </a>
              {' '}{__('and press', 'formtura')}{' '}
              <strong>{__('Connect with Stripe', 'formtura')}</strong> {__('button.', 'formtura')}
            </p>
          </>
        }
        buttonText={__('OK', 'formtura')}
        onClose={() => setShowStripeDialog(false)}
      />

      {/* PayPal Info Dialog */}
      <InfoDialog
        isOpen={showPayPalDialog}
        title={__('Heads up!', 'formtura')}
        message={
          <>
            <p className="formtura-dialog-lead">
              {__('PayPal account connection is required when using the PayPal Commerce field.', 'formtura')}
            </p>
            <p>
              {__('To proceed, please go to', 'formtura')}{' '}
              <a
                href={`${window.formturaBuilder?.adminUrl || '/wp-admin/'}admin.php?page=formtura-settings&tab=payments`}
              >
                {__('Formtura Settings » Payments » PayPal', 'formtura')}
              </a>
              {' '}{__('and press', 'formtura')}{' '}
              <strong>{__('Connect with PayPal', 'formtura')}</strong> {__('button.', 'formtura')}
            </p>
          </>
        }
        buttonText={__('OK', 'formtura')}
        onClose={() => setShowPayPalDialog(false)}
      />

      {/* Square Info Dialog */}
      <InfoDialog
        isOpen={showSquareDialog}
        title={__('Heads up!', 'formtura')}
        message={
          <>
            <p className="formtura-dialog-lead">
              {__('Square account connection is required when using the Square field.', 'formtura')}
            </p>
            <p>
              {__('To proceed, please go to', 'formtura')}{' '}
              <a
                href={`${window.formturaBuilder?.adminUrl || '/wp-admin/'}admin.php?page=formtura-settings&tab=payments`}
              >
                {__('Formtura Settings » Payments » Square', 'formtura')}
              </a>
              {' '}{__('and press', 'formtura')}{' '}
              <strong>{__('Connect with Square', 'formtura')}</strong> {__('button.', 'formtura')}
            </p>
          </>
        }
        buttonText={__('OK', 'formtura')}
        onClose={() => setShowSquareDialog(false)}
      />

      {/* Authorize.Net Info Dialog */}
      <InfoDialog
        isOpen={showAuthorizeNetDialog}
        title={__('Heads up!', 'formtura')}
        message={
          <>
            <p className="formtura-dialog-lead">
              {__('Authorize.Net account connection is required when using the Authorize.Net field.', 'formtura')}
            </p>
            <p>
              {__('To proceed, please go to', 'formtura')}{' '}
              <a
                href={`${window.formturaBuilder?.adminUrl || '/wp-admin/'}admin.php?page=formtura-settings&tab=payments`}
              >
                {__('Formtura Settings » Payments » Authorize.Net', 'formtura')}
              </a>
              {' '}{__('and press', 'formtura')}{' '}
              <strong>{__('Connect with Authorize.Net', 'formtura')}</strong> {__('button.', 'formtura')}
            </p>
          </>
        }
        buttonText={__('OK', 'formtura')}
        onClose={() => setShowAuthorizeNetDialog(false)}
      />

      {/* No-template Info Dialog (repeater, layout, page-break, entry-preview) */}
      <InfoDialog
        isOpen={null !== unavailableType}
        title={__('Not available yet', 'formtura')}
        message={
          <>
            <p className="formtura-dialog-lead">
              {sprintf(__('The %s field is not available yet.', 'formtura'), unavailableFieldTypes[unavailableType]?.label)}
            </p>
            <p>{unavailableFieldTypes[unavailableType]?.reason}</p>
          </>
        }
        buttonText={__('OK', 'formtura')}
        onClose={() => setUnavailableType(null)}
      />
    </aside>
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
    const url = prompt(__('Enter URL:', 'formtura'), 'https://');
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
      case 'link': {
        const url = prompt(__('Enter URL:', 'formtura'), 'https://');
        if (!url) return;
        newText = `<a href="${url}">${selectedText || url}</a>`;
        break;
      }
      case 'b-quote':
        newText = `<blockquote>${selectedText}</blockquote>`;
        break;
      case 'del':
        newText = `<del>${selectedText}</del>`;
        break;
      case 'ins':
        newText = `<ins>${selectedText}</ins>`;
        break;
      case 'img': {
        const imgUrl = prompt(__('Enter image URL:', 'formtura'), 'https://');
        if (!imgUrl) return;
        newText = `<img src="${imgUrl}" alt="${selectedText || ''}" />`;
        break;
      }
      case 'ul':
        newText = `<ul>\n  <li>${selectedText || __('Item', 'formtura')}</li>\n</ul>`;
        break;
      case 'ol':
        newText = `<ol>\n  <li>${selectedText || __('Item', 'formtura')}</li>\n</ol>`;
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

  return (
    <div className="formtura-wysiwyg-editor">
      {/* Toolbar Row */}
      <div className="formtura-wysiwyg-toolbar">
        {/* Quick Tags for Code mode / Format buttons for Visual */}
        <div className="formtura-wysiwyg-actions">
          {mode === 'code' ? (
            <>
              <button type="button" onClick={() => insertTag('b')}>{__('b', 'formtura')}</button>
              <button type="button" onClick={() => insertTag('i')}>{__('i', 'formtura')}</button>
              <button type="button" onClick={() => insertTag('link')}>{__('link', 'formtura')}</button>
              <button type="button" onClick={() => insertTag('b-quote')}>{__('b-quote', 'formtura')}</button>
              <button type="button" onClick={() => insertTag('del')}>{__('del', 'formtura')}</button>
              <button type="button" onClick={() => insertTag('ins')}>{__('ins', 'formtura')}</button>
              <button type="button" onClick={() => insertTag('img')}>{__('img', 'formtura')}</button>
              <button type="button" onClick={() => insertTag('ul')}>{__('ul', 'formtura')}</button>
              <button type="button" onClick={() => insertTag('ol')}>{__('ol', 'formtura')}</button>
              <button type="button" onClick={() => insertTag('li')}>{__('li', 'formtura')}</button>
              <button type="button" onClick={() => insertTag('code')}>{__('code', 'formtura')}</button>
              <button type="button" onClick={() => insertTag('more')}>{__('more', 'formtura')}</button>
            </>
          ) : (
            <>
              <button type="button" onClick={() => execCommand('bold')} title={__('Bold', 'formtura')}>
                <strong>B</strong>
              </button>
              <button type="button" onClick={() => execCommand('italic')} title={__('Italic', 'formtura')}>
                <em>I</em>
              </button>
              <button type="button" onClick={handleLink} title={__('Insert Link', 'formtura')}>
                {__('link', 'formtura')}
              </button>
              <button type="button" onClick={() => execCommand('formatBlock', 'blockquote')} title={__('Blockquote', 'formtura')}>
                {__('b-quote', 'formtura')}
              </button>
              <button type="button" onClick={() => execCommand('strikeThrough')} title={__('Strikethrough', 'formtura')}>
                {__('del', 'formtura')}
              </button>
              <button type="button" onClick={() => execCommand('underline')} title={__('Underline', 'formtura')}>
                {__('ins', 'formtura')}
              </button>
              <button type="button" onClick={() => execCommand('insertUnorderedList')} title={__('Bullet List', 'formtura')}>
                {__('ul', 'formtura')}
              </button>
              <button type="button" onClick={() => execCommand('insertOrderedList')} title={__('Numbered List', 'formtura')}>
                {__('ol', 'formtura')}
              </button>
              <button type="button" onClick={() => execCommand('removeFormat')} title={__('Clear Formatting', 'formtura')}>
                {__('close tags', 'formtura')}
              </button>
            </>
          )}
        </div>

        {/* Visual / Code Tabs */}
        <div className="formtura-wysiwyg-tabs">
          <button
            type="button"
            onClick={() => setMode('visual')}
            className={mode === 'visual' ? 'active' : ''}
          >
            {__('Visual', 'formtura')}
          </button>
          <button
            type="button"
            onClick={() => setMode('code')}
            className={mode === 'code' ? 'active' : ''}
          >
            {__('Code', 'formtura')}
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
          dangerouslySetInnerHTML={{ __html: value || '' }}
        />
      ) : (
        <textarea
          id="formtura-code-editor"
          value={codeValue}
          onChange={handleCodeChange}
          className="formtura-code-editor"
          placeholder={__('Enter HTML code here...', 'formtura')}
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
    if ((field.type === 'select' || field.type === 'radio' || field.type === 'checkbox' || field.type === 'checkboxes') && !field.choices) {
      handleChange('choices', [
        { label: __('First Choice', 'formtura'), value: 'first-choice', isDefault: false },
        { label: __('Second Choice', 'formtura'), value: 'second-choice', isDefault: false },
        { label: __('Third Choice', 'formtura'), value: 'third-choice', isDefault: false }
      ]);
    }
    if (['payment-dropdown', 'payment-checkbox', 'payment-multiple'].includes(field.type) && !field.items) {
      handleChange('items', [
        { label: __('First Item', 'formtura'), value: 'first-item', price: '10.00', isDefault: false },
        { label: __('Second Item', 'formtura'), value: 'second-item', price: '25.00', isDefault: false },
        { label: __('Third Item', 'formtura'), value: 'third-item', price: '50.00', isDefault: false }
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
      label: sprintf(__('Choice %d', 'formtura'), newChoices.length + 1),
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
      label: sprintf(__('Item %d', 'formtura'), newItems.length + 1),
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
    const samples = [
      __('Option A', 'formtura'), __('Option B', 'formtura'), __('Option C', 'formtura'),
      __('Option D', 'formtura'), __('Option E', 'formtura'),
    ];
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
              {__('Choices', 'formtura')} <Tooltip text={__('Add dropdown options. Use the radio button to set a default selection.', 'formtura')} />
              <button
                type="button"
                className="formtura-bulk-add-btn"
                onClick={() => setShowBulkAdd(!showBulkAdd)}
              >
                <Download size={12} /> {__('Bulk Add', 'formtura')}
              </button>
            </label>

            {showBulkAdd && (
              <div className="formtura-bulk-add-container">
                <textarea
                  placeholder={__('Paste your list here (one item per line)&#10;Example:&#10;Alabama&#10;Alaska&#10;Arizona', 'formtura')}
                  value={bulkText}
                  onChange={(e) => setBulkText(e.target.value)}
                  rows={6}
                />
                <button
                  type="button"
                  onClick={handleBulkAdd}
                  className="formtura-btn formtura-btn-primary"
                >
                  {__('Add Choices', 'formtura')}
                </button>
                <button
                  type="button"
                  onClick={() => { setBulkText(''); setShowBulkAdd(false); }}
                  className="formtura-btn formtura-btn-secondary"
                >
                  {__('Cancel', 'formtura')}
                </button>
              </div>
            )}

            <div className="formtura-choices-list">
              {(field.choices || []).map((choice, index) => (
                <div key={index} className="formtura-choice-item">
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
                    className="formtura-choice-radio"
                  />
                  <GripVertical className="formtura-choice-drag" aria-hidden="true" />
                  <input
                    type="text"
                    value={choice.label}
                    onChange={(e) => handleChoiceChange(index, 'label', e.target.value)}
                    placeholder={__('Choice label', 'formtura')}
                    className="formtura-choice-input"
                  />
                  <button
                    type="button"
                    onClick={addChoice}
                    className="formtura-icon-btn formtura-btn-add"
                    title={__('Add choice', 'formtura')}
                  >
                    <Plus size={16} />
                  </button>
                  <button
                    type="button"
                    onClick={() => removeChoice(index)}
                    className="formtura-icon-btn formtura-btn-remove"
                    title={__('Remove choice', 'formtura')}
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
              className="formtura-btn formtura-btn-secondary formtura-generate-choices"
            >
              <Wand2 size={14} /> {__('Generate Choices', 'formtura')}
            </button>
          </div>
        </>
      );
    }

    // Multiple Choice field (single answer, radio buttons)
    if (field.type === 'radio') {
      return (
        <>
          <div className="formtura-form-group">
            <label>
              {__('Choices', 'formtura')} <Tooltip text={__('Add options for users to select. Use the radio button to set a default selection.', 'formtura')} />
              <button
                type="button"
                className="formtura-bulk-add-btn"
                onClick={() => setShowBulkAdd(!showBulkAdd)}
              >
                <Download size={12} /> {__('Bulk Add', 'formtura')}
              </button>
            </label>

            {showBulkAdd && (
              <div className="formtura-bulk-add-container">
                <textarea
                  placeholder={__('Paste your list here (one item per line)', 'formtura')}
                  value={bulkText}
                  onChange={(e) => setBulkText(e.target.value)}
                  rows={6}
                />
                <button
                  type="button"
                  onClick={handleBulkAdd}
                  className="formtura-btn formtura-btn-primary"
                >
                  {__('Add Choices', 'formtura')}
                </button>
                <button
                  type="button"
                  onClick={() => { setBulkText(''); setShowBulkAdd(false); }}
                  className="formtura-btn formtura-btn-secondary"
                >
                  {__('Cancel', 'formtura')}
                </button>
              </div>
            )}

            <div className="formtura-choices-list">
              {(field.choices || []).map((choice, index) => (
                <div key={index} className="formtura-choice-item">
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
                    className="formtura-choice-radio"
                  />
                  <GripVertical className="formtura-choice-drag" aria-hidden="true" />
                  <input
                    type="text"
                    value={choice.label}
                    onChange={(e) => handleChoiceChange(index, 'label', e.target.value)}
                    placeholder={__('Choice label', 'formtura')}
                    className="formtura-choice-input"
                  />
                  <button
                    type="button"
                    onClick={addChoice}
                    className="formtura-icon-btn formtura-btn-add"
                    title={__('Add choice', 'formtura')}
                  >
                    <Plus size={16} />
                  </button>
                  <button
                    type="button"
                    onClick={() => removeChoice(index)}
                    className="formtura-icon-btn formtura-btn-remove"
                    title={__('Remove choice', 'formtura')}
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
              className="formtura-btn formtura-btn-secondary formtura-generate-choices"
            >
              <Wand2 size={14} /> {__('Generate Choices', 'formtura')}
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
                {__('Add Other Choice', 'formtura')} <Tooltip text={__('Allow users to enter a custom response if none of the provided options apply.', 'formtura')} />
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
                {__('Use Image Choices', 'formtura')} <Tooltip text={__('Display images alongside or instead of text labels for each choice.', 'formtura')} />
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
                {__('Use Icon Choices', 'formtura')} <Tooltip text={__('Display icons alongside or instead of text labels for each choice.', 'formtura')} />
              </span>
            </div>
          </div>
        </>
      );
    }

    // Checkboxes field (multiple answers). `checkboxes` is the pre-1.0.3 slug.
    if (field.type === 'checkbox' || field.type === 'checkboxes') {
      return (
        <>
          <div className="formtura-form-group">
            <label>
              {__('Choices', 'formtura')} <Tooltip text={__('Add checkbox options. Users can select multiple options. Check boxes to set default selections.', 'formtura')} />
              <button
                type="button"
                className="formtura-bulk-add-btn"
                onClick={() => setShowBulkAdd(!showBulkAdd)}
              >
                <Download size={12} /> {__('Bulk Add', 'formtura')}
              </button>
            </label>

            {showBulkAdd && (
              <div className="formtura-bulk-add-container">
                <textarea
                  placeholder={__('Paste your list here (one item per line)', 'formtura')}
                  value={bulkText}
                  onChange={(e) => setBulkText(e.target.value)}
                  rows={6}
                />
                <button
                  type="button"
                  onClick={handleBulkAdd}
                  className="formtura-btn formtura-btn-primary"
                >
                  {__('Add Choices', 'formtura')}
                </button>
                <button
                  type="button"
                  onClick={() => { setBulkText(''); setShowBulkAdd(false); }}
                  className="formtura-btn formtura-btn-secondary"
                >
                  {__('Cancel', 'formtura')}
                </button>
              </div>
            )}

            <div className="formtura-choices-list">
              {(field.choices || []).map((choice, index) => (
                <div key={index} className="formtura-choice-item">
                  <input
                    type="checkbox"
                    checked={choice.isDefault || false}
                    onChange={(e) => handleChoiceChange(index, 'isDefault', e.target.checked)}
                    className="formtura-choice-radio"
                  />
                  <GripVertical className="formtura-choice-drag" aria-hidden="true" />
                  <input
                    type="text"
                    value={choice.label}
                    onChange={(e) => handleChoiceChange(index, 'label', e.target.value)}
                    placeholder={__('Choice label', 'formtura')}
                    className="formtura-choice-input"
                  />
                  <button
                    type="button"
                    onClick={addChoice}
                    className="formtura-icon-btn formtura-btn-add"
                    title={__('Add choice', 'formtura')}
                  >
                    <Plus size={16} />
                  </button>
                  <button
                    type="button"
                    onClick={() => removeChoice(index)}
                    className="formtura-icon-btn formtura-btn-remove"
                    title={__('Remove choice', 'formtura')}
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
              className="formtura-btn formtura-btn-secondary formtura-generate-choices"
            >
              <Wand2 size={14} /> {__('Generate Choices', 'formtura')}
            </button>
          </div>
        </>
      );
    }

    // Dropdown Items (payment-dropdown, payment-checkbox, payment-multiple)
    if (['payment-dropdown', 'payment-checkbox', 'payment-multiple'].includes(field.type)) {
      return (
        <>
          <div className="formtura-form-group">
            <label>
              {__('Items', 'formtura')} <Tooltip text={__('Add payment items with prices. Use the radio button to set a default selection.', 'formtura')} />
            </label>

            <div className="formtura-items-list">
              {(field.items || []).map((item, index) => (
                <div key={index} className="formtura-item-row">
                  {field.type === 'payment-checkbox' ? (
                    <input
                      type="checkbox"
                      checked={item.isDefault || false}
                      onChange={(e) => handleItemChange(index, 'isDefault', e.target.checked)}
                      className="formtura-choice-radio"
                    />
                  ) : (
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
                      className="formtura-choice-radio"
                    />
                  )}
                  <GripVertical className="formtura-choice-drag" aria-hidden="true" />
                  <input
                    type="text"
                    value={item.label}
                    onChange={(e) => handleItemChange(index, 'label', e.target.value)}
                    placeholder={__('Item name', 'formtura')}
                    className="formtura-choice-input"
                  />
                  <input
                    type="number"
                    value={item.price}
                    onChange={(e) => handleItemChange(index, 'price', e.target.value)}
                    placeholder="0.00"
                    step="0.01"
                    min="0"
                    className="formtura-price-input"
                  />
                  <button
                    type="button"
                    onClick={addItem}
                    className="formtura-icon-btn formtura-btn-add"
                    title={__('Add item', 'formtura')}
                  >
                    <Plus size={16} />
                  </button>
                  <button
                    type="button"
                    onClick={() => removeItem(index)}
                    className="formtura-icon-btn formtura-btn-remove"
                    title={__('Remove item', 'formtura')}
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
                  checked={field.showPriceAfterLabels || false}
                  onChange={(e) => handleChange('showPriceAfterLabels', e.target.checked)}
                />
                <span className="formtura-toggle-slider"></span>
              </label>
              <span className="formtura-toggle-label">
                {__('Show Price After Item Labels', 'formtura')} <Tooltip text={__('Display the price next to each item label in the dropdown.', 'formtura')} />
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
                {__('Enable Quantity', 'formtura')} <Tooltip text={__('Allow users to specify the quantity of the selected item.', 'formtura')} />
              </span>
            </div>
          </div>
        </>
      );
    }

    // Single Item price
    if (field.type === 'payment-single') {
      return (
        <div className="formtura-form-group">
          <label htmlFor="field-price">
            {__('Item Price', 'formtura')} <Tooltip text={__('The amount this item adds to the order total.', 'formtura')} />
          </label>
          <input
            id="field-price"
            type="number"
            min="0"
            step="0.01"
            className="formtura-price-input"
            value={field.price || ''}
            onChange={(e) => handleChange('price', e.target.value)}
          />
        </div>
      );
    }

    // Coupon codes
    if (field.type === 'coupon') {
      const coupons = field.coupons || [];
      const setCoupons = (next) => handleChange('coupons', next);

      return (
        <div className="formtura-form-group">
          <label>
            {__('Coupon Codes', 'formtura')} <Tooltip text={__('Codes are validated on the server and are never shown to visitors.', 'formtura')} />
          </label>

          {coupons.map((coupon, index) => (
            <div key={index} className="formtura-coupon-row">
              <input
                type="text"
                placeholder={__('CODE', 'formtura')}
                value={coupon.code || ''}
                onChange={(e) => {
                  const next = [...coupons];
                  next[index] = { ...next[index], code: e.target.value };
                  setCoupons(next);
                }}
              />
              <select
                value={coupon.type || 'fixed'}
                onChange={(e) => {
                  const next = [...coupons];
                  next[index] = { ...next[index], type: e.target.value };
                  setCoupons(next);
                }}
              >
                <option value="fixed">{__('Fixed amount', 'formtura')}</option>
                <option value="percent">{__('Percent', 'formtura')}</option>
              </select>
              <input
                type="number"
                min="0"
                step="0.01"
                placeholder={__('Value', 'formtura')}
                className="formtura-price-input"
                value={coupon.value || ''}
                onChange={(e) => {
                  const next = [...coupons];
                  next[index] = { ...next[index], value: e.target.value };
                  setCoupons(next);
                }}
              />
              <button
                type="button"
                onClick={() => setCoupons(coupons.filter((_, i) => i !== index))}
              >
                ×
              </button>
            </div>
          ))}

          <button
            type="button"
            className="formtura-btn formtura-btn-secondary"
            onClick={() => setCoupons([...coupons, { code: '', type: 'fixed', value: '' }])}
          >
            {__('Add Coupon', 'formtura')}
          </button>
        </div>
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
          {__('Label', 'formtura')} <Tooltip text={__('Enter text for the form field label. Labels are recommended but can be hidden in Advanced Settings.', 'formtura')} />
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
              <span>{__('File Upload Options', 'formtura')}</span>
              <ChevronDown size={16} className="formtura-collapsible-icon" />
            </summary>
            <div className="formtura-collapsible-content">
              {/* Warning notice */}
              <div className="formtura-warning-notice">
                <span className="formtura-warning-icon" aria-hidden="true">⚠</span>
                <span>
                  {__('Uploads are public. File access can be updated in', 'formtura')}{' '}
                  <a href="#">{__('Form Permissions Settings', 'formtura')}</a>.{' '}
                  <Tooltip text={__('Files uploaded with this field can be viewed by anyone with access to a link and could be indexed by search engines. If this is a concern, we recommend enabling file protection and turning off indexing.', 'formtura')} />
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
                  <span className="formtura-toggle-label">{__('Allow multiple files to be uploaded', 'formtura')}</span>
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
                  <span className="formtura-toggle-label">{__('Attach this file to the email notification', 'formtura')}</span>
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
                  <span className="formtura-toggle-label">{__('Permanently delete old files when replaced or when the entry is deleted', 'formtura')}</span>
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
                    {__('Automatically resize files before upload', 'formtura')}{' '}
                    <Tooltip text={__('When a large image is uploaded, resize it before you save it to your site.', 'formtura')} />
                  </span>
              </div>
              </div>

              {/* Allowed file types */}
              <div className="formtura-form-group">
                <label>{__('Allowed file types', 'formtura')}</label>
                <div className="formtura-radio-inline-group">
                  <label className="formtura-radio-inline">
                    <input
                      type="radio"
                      name={`file-types-${field.id}`}
                      checked={field.allowedFileTypes === 'all'}
                      onChange={() => handleChange('allowedFileTypes', 'all')}
                    />
                    <span>{__('Allow all file types', 'formtura')}</span>
                  </label>
                  <label className="formtura-radio-inline">
                    <input
                      type="radio"
                      name={`file-types-${field.id}`}
                      checked={field.allowedFileTypes === 'specify' || !field.allowedFileTypes}
                      onChange={() => handleChange('allowedFileTypes', 'specify')}
                    />
                    <span>{__('Specify allowed types', 'formtura')}</span>
                  </label>
                </div>
                {(field.allowedFileTypes === 'specify' || !field.allowedFileTypes) && (
                  <select
                    value={field.specifiedTypes || 'jpg, jpeg, jpe, png, gif'}
                    onChange={(e) => handleChange('specifiedTypes', e.target.value)}
                  >
                    <option value="jpg, jpeg, jpe, png, gif">jpg, jpeg, jpe, png, gif</option>
                    <option value="pdf">pdf</option>
                    <option value="doc, docx">doc, docx</option>
                    <option value="xls, xlsx">xls, xlsx</option>
                    <option value="jpg, jpeg, jpe, png, gif, pdf">{__('Images & PDF', 'formtura')}</option>
                    <option value="jpg, jpeg, jpe, png, gif, pdf, doc, docx">{__('Images, PDF & Documents', 'formtura')}</option>
                  </select>
                )}
              </div>

              {/* File size limits */}
              <div className="formtura-form-group">
                <label>{__('File size limits', 'formtura')}</label>
                <div className="formtura-grid-2">
                  <div>
                    <label className="formtura-subfield-label">
                      {__('Min file size (MB)', 'formtura')}{' '}
                      <Tooltip text={__('Set the minimum file size limit for each file uploaded.', 'formtura')} />
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
                    <label className="formtura-subfield-label">
                      {__('Max file size (MB)', 'formtura')}{' '}
                      <Tooltip text={__('Set the file size limit for each file uploaded. Your server settings allow a maximum of 256 MB.', 'formtura')} />
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
                <label htmlFor="upload-text">{__('Upload text', 'formtura')}</label>
                <input
                  id="upload-text"
                  type="text"
                  value={field.uploadText || __('Drop a file here or click to upload', 'formtura')}
                  onChange={(e) => handleChange('uploadText', e.target.value)}
                />
              </div>

              {/* Compact upload text */}
              <div className="formtura-form-group">
                <label htmlFor="compact-upload-text">
                  {__('Compact upload text', 'formtura')}{' '}
                  <Tooltip text={__('The label shown when the file upload field is compacted with fta_compact CSS layout class.', 'formtura')} />
                </label>
                <input
                  id="compact-upload-text"
                  type="text"
                  value={field.compactUploadText || __('Choose File', 'formtura')}
                  onChange={(e) => handleChange('compactUploadText', e.target.value)}
                />
              </div>
            </div>
          </details>
        </div>
      )}

      {/* Rich Text Editor - After Label for rich-text field type.
          Plain textarea, not WysiwygEditor: templates/fields/rich-text.php
          renders this content through wp_strip_all_tags() into a plain
          <textarea> on the frontend, so a formatting toolbar here would
          promise markup the field can never actually deliver. */}
      {field.type === 'rich-text' && (
        <div className="formtura-form-group">
          <label htmlFor="field-content">
            {__('Content', 'formtura')} <Tooltip text={__('Enter the text this field shows by default. It renders as plain text on the frontend, so formatting here will not be preserved.', 'formtura')} />
          </label>
          <textarea
            id="field-content"
            value={field.content || ''}
            onChange={(e) => handleChange('content', e.target.value)}
            rows={6}
          />
        </div>
      )}

      {/* Name Field Format Selector */}
      {field.type === 'name' && (
        <div className="formtura-form-group">
          <label htmlFor="field-format">
            {__('Format', 'formtura')} <Tooltip text={__('Choose how the name field should be displayed: as a single input or split into multiple parts.', 'formtura')} />
          </label>
          <select
            id="field-format"
            value={field.format || 'first-last'}
            onChange={(e) => handleChange('format', e.target.value)}
          >
            <option value="simple">{__('Simple', 'formtura')}</option>
            <option value="first-last">{__('First + Last Names', 'formtura')}</option>
            <option value="first-middle-last">{__('First + Middle + Last Name', 'formtura')}</option>
          </select>
        </div>
      )}

      {/* Address Scheme Selector */}
      {field.type === 'address' && (
        <div className="formtura-form-group">
          <label htmlFor="field-scheme">
            {__('Scheme', 'formtura')} <Tooltip text={__('US shows State and ZIP Code; International shows Province/Region and Postal Code.', 'formtura')} />
          </label>
          <select
            id="field-scheme"
            value={field.scheme || 'us'}
            onChange={(e) => handleChange('scheme', e.target.value)}
          >
            <option value="us">{__('United States', 'formtura')}</option>
            <option value="international">{__('International', 'formtura')}</option>
          </select>
        </div>
      )}

      {/* Render field-specific options */}
      {renderFieldSpecificOptions()}

      {/*
        Description - or, for the two presentational types, the block of
        content they render instead of a description.

        Both templates (templates/fields/html.php, content.php) read the
        `content` key and ignore `description`, so these editors must write
        `content`. They read `field.description` as a fallback so a field
        saved before this binding was fixed still shows its text here, and
        migrates to `content` on the next edit.

        rich-text is skipped: it has its own Content editor above.
      */}
      {field.type === 'html' ? (
        <div className="formtura-form-group">
          <label>
            {__('Content', 'formtura')} <Tooltip text={__('Enter HTML content that will be displayed in the form. Use the toolbar to format text, add links, and create lists.', 'formtura')} />
          </label>
          <WysiwygEditor
            value={field.content || field.description || ''}
            onChange={(html) => handleChange('content', html)}
          />
        </div>
      ) : field.type === 'content' ? (
        <div className="formtura-form-group">
          <label htmlFor="field-content">
            {__('Content', 'formtura')} <Tooltip text={__('Enter the text or basic HTML this block displays on the form. It is not an input - visitors read it, they do not fill it in.', 'formtura')} />
          </label>
          <textarea
            id="field-content"
            value={field.content || field.description || ''}
            onChange={(e) => handleChange('content', e.target.value)}
            rows={6}
          />
        </div>
      ) : field.type !== 'rich-text' && (
        <div className="formtura-form-group">
          <label htmlFor="field-description">
            {__('Description', 'formtura')} <Tooltip text={__('Enter text for the form field description.', 'formtura')} />
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
        <div className="formtura-form-group formtura-grid-2">
          <div>
            <label htmlFor="field-size-richtext">
              {__('Field Size', 'formtura')} <Tooltip text={__('Set the width unit for the field.', 'formtura')} />
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
              {__('Rows', 'formtura')} <Tooltip text={__('Set the number of visible text rows for the editor.', 'formtura')} />
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
              {__('Enable Summary', 'formtura')} <Tooltip text={__('Enable order summary for this field.', 'formtura')} />
            </span>
          </div>
          {field.enableSummary && (
            <p className="formtura-info-message formtura-info-panel">
              {__('Example data is shown in the form editor. Actual products and totals will be displayed when you preview or embed your form.', 'formtura')}
            </p>
          )}
        </div>
      )}

      {/* Number Slider Fields - After Description */}
      {field.type === 'number-slider' && (
        <>
          <div className="formtura-form-group">
            <label>
              {__('Value Range', 'formtura')} <Tooltip text={__('Define the minimum and maximum values for the slider.', 'formtura')} />
            </label>
            <div className="formtura-grid-2">
              <div>
                <input
                  type="number"
                  value={field.minValue !== undefined ? field.minValue : 0}
                  onChange={(e) => handleChange('minValue', parseInt(e.target.value) || 0)}
                  placeholder="0"
                />
                <span className="formtura-field-help">{__('Minimum', 'formtura')}</span>
              </div>
              <div>
                <input
                  type="number"
                  value={field.maxValue !== undefined ? field.maxValue : 10}
                  onChange={(e) => handleChange('maxValue', parseInt(e.target.value) || 10)}
                  placeholder="10"
                />
                <span className="formtura-field-help">{__('Maximum', 'formtura')}</span>
              </div>
            </div>
          </div>

          <div className="formtura-form-group">
            <label htmlFor="field-default-value">
              {__('Default Value', 'formtura')} <Tooltip text={__('Enter a default value for this field.', 'formtura')} />
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
              {__('Increment', 'formtura')} <Tooltip text={__('Set the increment between selectable values on the slider.', 'formtura')} />
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
                {__('Collapsible', 'formtura')} <Tooltip text={__('Collapsible: This section will slide open and closed.', 'formtura')} />
              </span>
            </div>
          </div>

          <div className="formtura-form-group">
            <label htmlFor="field-repeat-layout">
              {__('Repeat Layout', 'formtura')} <Tooltip text={__('Choose how repeater rows are displayed.', 'formtura')} />
            </label>
            <select
              id="field-repeat-layout"
              value={field.repeatLayout || 'default'}
              onChange={(e) => handleChange('repeatLayout', e.target.value)}
            >
              <option value="default">{__('Default', 'formtura')}</option>
              <option value="inline">{__('Inline', 'formtura')}</option>
              <option value="grid">{__('Grid', 'formtura')}</option>
            </select>
            <span className="formtura-field-help">
              {field.repeatLayout === 'default' && __('No automatic formatting', 'formtura')}
              {field.repeatLayout === 'inline' && __('Display each field and label in one row', 'formtura')}
              {field.repeatLayout === 'grid' && __('Display labels as headings above rows of fields', 'formtura')}
            </span>
          </div>
        </>
      )}

      {/*
        Required toggle - not shown for number-slider, repeater, or total.
        The total field renders no input the visitor can fill in, so marking
        it required could only ever block the form. The server ignores the
        flag as well (Submission::is_presentational_field()), since forms
        saved while this toggle was offered may still carry required:true.
      */}
      {field.type !== 'number-slider' && field.type !== 'repeater' && field.type !== 'total' && (
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
              {__('Required', 'formtura')} <Tooltip text={__('Check this option to mark the field as required. The form will not submit unless all required fields are completed.', 'formtura')} />
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
              {__('Unique', 'formtura')} <Tooltip text={__('Unique: Do not allow the same response multiple times. For example, if one user enters \'Joe\', then no one else will be allowed to enter the same name.', 'formtura')} />
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
        {__('CSS Layout Classes', 'formtura')} <Tooltip text={__('Add a class for the form field container. Use our predefined classes to align multiple fields in a single row.', 'formtura')} />
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
          title={__('Show style classes', 'formtura')}
        >
          <MoreHorizontal size={16} />
        </button>
      </div>

      {showStyleClasses && (
        <div className="formtura-style-classes-dropdown">
          <div className="formtura-style-classes-section">
            <div className="formtura-style-classes-layouts">
              <div className="formtura-layout-row">
                <button type="button" className="formtura-layout-btn fta-one-half" onClick={() => handleStyleClassSelect('fta-one-half')}>1/2</button>
                <button type="button" className="formtura-layout-btn fta-one-half" onClick={() => handleStyleClassSelect('fta-one-half')}>1/2</button>
              </div>
              <div className="formtura-layout-row">
                <button type="button" className="formtura-layout-btn fta-one-third" onClick={() => handleStyleClassSelect('fta-one-third')}>1/3</button>
                <button type="button" className="formtura-layout-btn fta-two-thirds" onClick={() => handleStyleClassSelect('fta-two-thirds')}>2/3</button>
              </div>
              <div className="formtura-layout-row">
                <button type="button" className="formtura-layout-btn fta-one-fourth" onClick={() => handleStyleClassSelect('fta-one-fourth')}>1/4</button>
                <button type="button" className="formtura-layout-btn fta-three-fourths" onClick={() => handleStyleClassSelect('fta-three-fourths')}>3/4</button>
              </div>
              <div className="formtura-layout-row">
                <button type="button" className="formtura-layout-btn fta-one-sixth" onClick={() => handleStyleClassSelect('fta-one-sixth')}>1/6</button>
                <button type="button" className="formtura-layout-btn fta-five-sixths" onClick={() => handleStyleClassSelect('fta-five-sixths')}>5/6</button>
              </div>
              <div className="formtura-layout-row">
                <button type="button" className="formtura-layout-btn fta-full" onClick={() => handleStyleClassSelect('fta-full')}>100%</button>
              </div>
            </div>
          </div>
          <div className="formtura-style-classes-section">
            <div className="formtura-style-classes-header">
              <span>{__('Other Style Classes', 'formtura')}</span>
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
            {__('Field Size', 'formtura')} <Tooltip text={__('Select the default size for the field.', 'formtura')} />
          </label>
          <select
            id="field-size"
            value={field.fieldSize || 'medium'}
            onChange={(e) => handleChange('fieldSize', e.target.value)}
          >
            <option value="small">{__('Small', 'formtura')}</option>
            <option value="medium">{__('Medium', 'formtura')}</option>
            <option value="large">{__('Large', 'formtura')}</option>
          </select>
        </div>

        {/* First Name Placeholder & Default */}
        {showMultipleFields && (
          <div className="formtura-form-group">
            <label>
              {__('First Name', 'formtura')} <Tooltip text={__('Configure placeholder and default value for the first name input.', 'formtura')} />
            </label>
            <div className="formtura-name-field-row">
              <div className="formtura-name-field-col">
                <input
                  type="text"
                  value={field.firstNamePlaceholder || ''}
                  onChange={(e) => handleChange('firstNamePlaceholder', e.target.value)}
                  placeholder=""
                />
                <span className="formtura-field-help">{__('Placeholder', 'formtura')}</span>
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
                <span className="formtura-field-help">{__('Default Value', 'formtura')}</span>
              </div>
            </div>
          </div>
        )}

        {/* Middle Name Placeholder & Default - Always show for multi-field formats */}
        {showMultipleFields && (
          <div className="formtura-form-group">
            <label>
              {__('Middle Name', 'formtura')} <Tooltip text={__('Configure placeholder and default value for the middle name input.', 'formtura')} />
            </label>
            <div className="formtura-name-field-row">
              <div className="formtura-name-field-col">
                <input
                  type="text"
                  value={field.middleNamePlaceholder || ''}
                  onChange={(e) => handleChange('middleNamePlaceholder', e.target.value)}
                  placeholder=""
                />
                <span className="formtura-field-help">{__('Placeholder', 'formtura')}</span>
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
                <span className="formtura-field-help">{__('Default Value', 'formtura')}</span>
              </div>
            </div>
          </div>
        )}

        {/* Last Name Placeholder & Default */}
        {showMultipleFields && (
          <div className="formtura-form-group">
            <label>
              {__('Last Name', 'formtura')} <Tooltip text={__('Configure placeholder and default value for the last name input.', 'formtura')} />
            </label>
            <div className="formtura-name-field-row">
              <div className="formtura-name-field-col">
                <input
                  type="text"
                  value={field.lastNamePlaceholder || ''}
                  onChange={(e) => handleChange('lastNamePlaceholder', e.target.value)}
                  placeholder=""
                />
                <span className="formtura-field-help">{__('Placeholder', 'formtura')}</span>
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
                <span className="formtura-field-help">{__('Default Value', 'formtura')}</span>
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
              {__('Hide Label', 'formtura')} <Tooltip text={__('Check this option to hide the form field label.', 'formtura')} />
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
              {__('Hide Sublabels', 'formtura')} <Tooltip text={__('Check this option to hide sublabels under each name field input.', 'formtura')} />
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
              {__('Read-Only', 'formtura')} <Tooltip text={__('Check this option to display the field\'s value without allowing changes. The value will still be submitted.', 'formtura')} />
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
            {__('Field Size', 'formtura')} <Tooltip text={__('Select the default size for the field.', 'formtura')} />
          </label>
          <select
            id="field-size"
            value={field.fieldSize || 'medium'}
            onChange={(e) => handleChange('fieldSize', e.target.value)}
          >
            <option value="small">{__('Small', 'formtura')}</option>
            <option value="medium">{__('Medium', 'formtura')}</option>
            <option value="large">{__('Large', 'formtura')}</option>
          </select>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-value-display">
            {__('Value Display', 'formtura')} <Tooltip text={__('Displays the currently selected value below the slider. Use {value} placeholder for the selected number.', 'formtura')} />
          </label>
          <input
            id="field-value-display"
            type="text"
            value={field.valueDisplay || __('Selected Value: {value}', 'formtura')}
            onChange={(e) => handleChange('valueDisplay', e.target.value)}
            placeholder={__('Selected Value: {value}', 'formtura')}
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
              {__('Hide Label', 'formtura')} <Tooltip text={__('Check this option to hide the form field label.', 'formtura')} />
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
              {__('Read-Only', 'formtura')} <Tooltip text={__('Check this option to display the field\'s value without allowing changes. The value will still be submitted.', 'formtura')} />
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
            {__('Field Size', 'formtura')} <Tooltip text={__('Select the default size for the field.', 'formtura')} />
          </label>
          <select
            id="field-size"
            value={field.fieldSize || 'medium'}
            onChange={(e) => handleChange('fieldSize', e.target.value)}
          >
            <option value="small">{__('Small', 'formtura')}</option>
            <option value="medium">{__('Medium', 'formtura')}</option>
            <option value="large">{__('Large', 'formtura')}</option>
          </select>
        </div>

        <div className="formtura-form-group formtura-grid-2">
          <div>
            <label htmlFor="field-add-label">
              {__('Add New Label', 'formtura')} <Tooltip text={__('Text for the add button.', 'formtura')} />
            </label>
            <input
              id="field-add-label"
              type="text"
              value={field.addNewLabel || __('Add', 'formtura')}
              onChange={(e) => handleChange('addNewLabel', e.target.value)}
              placeholder={__('Add', 'formtura')}
            />
          </div>
          <div>
            <label htmlFor="field-remove-label">
              {__('Remove Label', 'formtura')} <Tooltip text={__('Text for the remove button.', 'formtura')} />
            </label>
            <input
              id="field-remove-label"
              type="text"
              value={field.removeLabel || __('Remove', 'formtura')}
              onChange={(e) => handleChange('removeLabel', e.target.value)}
              placeholder={__('Remove', 'formtura')}
            />
          </div>
        </div>

        <div className="formtura-form-group formtura-grid-2">
          <div>
            <label htmlFor="field-min-rows">
              {__('Min Repeater Rows', 'formtura')} <Tooltip text={__('Minimum number of repeater rows.', 'formtura')} />
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
              {__('Max Repeater Rows', 'formtura')} <Tooltip text={__('The maximum number of times the end user is allowed to duplicate this section of fields in one entry.', 'formtura')} />
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
              {__('Hide Label', 'formtura')} <Tooltip text={__('Check this option to hide the form field label.', 'formtura')} />
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
              {__('Multiple Options Selection', 'formtura')} <Tooltip text={__('Allow users to select multiple choices in this field.', 'formtura')} />
            </span>
          </div>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-style">
            {__('Style', 'formtura')} <Tooltip text={__('Select the visual style for the dropdown field.', 'formtura')} />
          </label>
          <select
            id="field-style"
            value={field.style || 'classic'}
            onChange={(e) => handleChange('style', e.target.value)}
          >
            <option value="classic">{__('Classic', 'formtura')}</option>
            <option value="modern">{__('Modern', 'formtura')}</option>
          </select>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-size">
            {__('Field Size', 'formtura')} <Tooltip text={__('Select the default size for the field.', 'formtura')} />
          </label>
          <select
            id="field-size"
            value={field.fieldSize || 'medium'}
            onChange={(e) => handleChange('fieldSize', e.target.value)}
          >
            <option value="small">{__('Small', 'formtura')}</option>
            <option value="medium">{__('Medium', 'formtura')}</option>
            <option value="large">{__('Large', 'formtura')}</option>
          </select>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-placeholder">
            {__('Placeholder Text', 'formtura')} <Tooltip text={__('Enter placeholder text that appears as the first option in the dropdown.', 'formtura')} />
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
            {__('Dynamic Choices', 'formtura')} <Tooltip text={__('Select auto-populate method to use.', 'formtura')} />
          </label>
          <select
            id="field-dynamic-choices"
            value={field.dynamicChoices || 'off'}
            onChange={(e) => handleChange('dynamicChoices', e.target.value)}
          >
            <option value="off">{__('Off', 'formtura')}</option>
            <option value="post_type">{__('Post Type', 'formtura')}</option>
            <option value="taxonomy">{__('Taxonomy', 'formtura')}</option>
          </select>
        </div>

        {field.dynamicChoices === 'post_type' && (
          <div className="formtura-form-group">
            <label htmlFor="field-dynamic-post-type">
              {__('Dynamic Post Type Source', 'formtura')} <Tooltip text={__('Select Post Type to use for auto-populating the field choices.', 'formtura')} />
            </label>
            <select
              id="field-dynamic-post-type"
              value={field.dynamicPostType || 'post'}
              onChange={(e) => handleChange('dynamicPostType', e.target.value)}
            >
              <option value="post">{__('Posts', 'formtura')}</option>
              <option value="page">{__('Pages', 'formtura')}</option>
              <option value="attachment">{__('Media', 'formtura')}</option>
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
              {__('Dynamic Taxonomy Source', 'formtura')} <Tooltip text={__('Select Taxonomy to use for auto-populating the field choices.', 'formtura')} />
            </label>
            <select
              id="field-dynamic-taxonomy"
              value={field.dynamicTaxonomy || 'category'}
              onChange={(e) => handleChange('dynamicTaxonomy', e.target.value)}
            >
              <option value="category">{__('Categories', 'formtura')}</option>
              <option value="post_tag">{__('Tags', 'formtura')}</option>
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
              {__('Hide Label', 'formtura')} <Tooltip text={__('Check this option to hide the form field label.', 'formtura')} />
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
              {__('Read-Only', 'formtura')} <Tooltip text={__('Check this option to display the field\'s value without allowing changes. The value will still be submitted.', 'formtura')} />
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
            {__('Field Size', 'formtura')} <Tooltip text={__('Select the default size for the field.', 'formtura')} />
          </label>
          <select
            id="field-size"
            value={field.fieldSize || 'medium'}
            onChange={(e) => handleChange('fieldSize', e.target.value)}
          >
            <option value="small">{__('Small', 'formtura')}</option>
            <option value="medium">{__('Medium', 'formtura')}</option>
            <option value="large">{__('Large', 'formtura')}</option>
          </select>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-placeholder">
            {__('Placeholder Text', 'formtura')} <Tooltip text={__('Enter placeholder text that appears inside the input field before the user types.', 'formtura')} />
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
            {__('Range', 'formtura')} <Tooltip text={__('Define the minimum and the maximum values for the field.', 'formtura')} />
          </label>
          <div className="formtura-range-row">
            <div className="formtura-range-col">
              <input
                type="number"
                value={field.minValue !== undefined ? field.minValue : ''}
                onChange={(e) => handleChange('minValue', e.target.value === '' ? undefined : parseFloat(e.target.value))}
                placeholder=""
              />
              <span className="formtura-field-help">{__('Minimum', 'formtura')}</span>
            </div>
            <div className="formtura-range-col">
              <input
                type="number"
                value={field.maxValue !== undefined ? field.maxValue : ''}
                onChange={(e) => handleChange('maxValue', e.target.value === '' ? undefined : parseFloat(e.target.value))}
                placeholder=""
              />
              <span className="formtura-field-help">{__('Maximum', 'formtura')}</span>
            </div>
          </div>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-default-value">
            {__('Default Value', 'formtura')} <Tooltip text={__('Enter text for the default form field value.', 'formtura')} />
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
              {__('Hide Label', 'formtura')} <Tooltip text={__('Check this option to hide the form field label.', 'formtura')} />
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
              {__('Read-Only', 'formtura')} <Tooltip text={__('Check this option to display the field\'s value without allowing changes. The value will still be submitted.', 'formtura')} />
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
              {__('Enable Calculation', 'formtura')} <Tooltip text={__('Enable mathematical calculations using values from other form fields.', 'formtura')} />
            </span>
          </div>
        </div>

        {field.enableCalculation && (
          <div className="formtura-form-group">
            <label htmlFor="field-calculation-formula">
              {__('Calculation Formula', 'formtura')} <Tooltip text={__('Enter a mathematical formula using field IDs. Example: {field_1} + {field_2} * 2. Supported operators: +, -, *, /, (). Use {field_ID} to reference other number fields.', 'formtura')} />
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
              {__('Use', 'formtura')} <code>{'{field_ID}'}</code> {__('to reference other number fields. Supported operators:', 'formtura')} <code>+</code>, <code>-</code>, <code>*</code>, <code>/</code>, <code>()</code>
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
              {__('Randomize Choices', 'formtura')} <Tooltip text={__('Check this option to randomize the order of the choices.', 'formtura')} />
            </span>
          </div>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-choice-layout">
            {__('Choice Layout', 'formtura')} <Tooltip text={__('Select the layout for displaying field choices.', 'formtura')} />
          </label>
          <select
            id="field-choice-layout"
            value={field.choiceLayout || 'one-column'}
            onChange={(e) => handleChange('choiceLayout', e.target.value)}
          >
            <option value="one-column">{__('One Column', 'formtura')}</option>
            <option value="two-columns">{__('Two Columns', 'formtura')}</option>
            <option value="three-columns">{__('Three Columns', 'formtura')}</option>
            <option value="inline">{__('Inline', 'formtura')}</option>
          </select>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="field-dynamic-choices">
            {__('Dynamic Choices', 'formtura')} <Tooltip text={__('Select auto-populate method to use.', 'formtura')} />
          </label>
          <select
            id="field-dynamic-choices"
            value={field.dynamicChoices || 'off'}
            onChange={(e) => handleChange('dynamicChoices', e.target.value)}
          >
            <option value="off">{__('Off', 'formtura')}</option>
            <option value="post_type">{__('Post Type', 'formtura')}</option>
            <option value="taxonomy">{__('Taxonomy', 'formtura')}</option>
          </select>
        </div>

        {field.dynamicChoices === 'post_type' && (
          <div className="formtura-form-group">
            <label htmlFor="field-dynamic-post-type">
              {__('Dynamic Post Type Source', 'formtura')} <Tooltip text={__('Select Post Type to use for auto-populating the field choices.', 'formtura')} />
            </label>
            <select
              id="field-dynamic-post-type"
              value={field.dynamicPostType || 'post'}
              onChange={(e) => handleChange('dynamicPostType', e.target.value)}
            >
              <option value="post">{__('Posts', 'formtura')}</option>
              <option value="page">{__('Pages', 'formtura')}</option>
              <option value="attachment">{__('Media', 'formtura')}</option>
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
              {__('Dynamic Taxonomy Source', 'formtura')} <Tooltip text={__('Select Taxonomy to use for auto-populating the field choices.', 'formtura')} />
            </label>
            <select
              id="field-dynamic-taxonomy"
              value={field.dynamicTaxonomy || 'category'}
              onChange={(e) => handleChange('dynamicTaxonomy', e.target.value)}
            >
              <option value="category">{__('Categories', 'formtura')}</option>
              <option value="post_tag">{__('Tags', 'formtura')}</option>
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
              {__('Hide Label', 'formtura')} <Tooltip text={__('Check this option to hide the form field label.', 'formtura')} />
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
              {__('Read-Only', 'formtura')} <Tooltip text={__('Check this option to display the field\'s value without allowing changes. The value will still be submitted.', 'formtura')} />
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
            {__('Visibility', 'formtura')} <Tooltip text={__('Determines who can see this field. Select \'Everyone\' for public visibility or choose specific user roles.', 'formtura')} />
          </label>
          <select
            id="field-visibility"
            value={field.visibility || 'everyone'}
            onChange={(e) => handleChange('visibility', e.target.value)}
          >
            <option value="everyone">{__('Everyone', 'formtura')}</option>
            <option value="logged_in">{__('Logged In Users', 'formtura')}</option>
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
              {__('Hide Label', 'formtura')} <Tooltip text={__('Check this option to hide the form field label.', 'formtura')} />
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
            {__('Field Size', 'formtura')} <Tooltip text={__('Select the default size for the field.', 'formtura')} />
          </label>
          <select
            id="field-size"
            value={field.fieldSize || 'medium'}
            onChange={(e) => handleChange('fieldSize', e.target.value)}
          >
            <option value="small">{__('Small', 'formtura')}</option>
            <option value="medium">{__('Medium', 'formtura')}</option>
            <option value="large">{__('Large', 'formtura')}</option>
          </select>
        </div>

        <div className="formtura-form-group">
          <label htmlFor="max-rating">
            {__('Maximum Rating', 'formtura')} <Tooltip text={__('Set the maximum number of stars that will be displayed in the rating field.', 'formtura')} />
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
              {__('Hide Label', 'formtura')} <Tooltip text={__('Check this option to hide the form field label.', 'formtura')} />
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
            {__('Field Size', 'formtura')} <Tooltip text={__('Select the default size for the field.', 'formtura')} />
          </label>
          <select
            id="field-size"
            value={field.fieldSize || 'medium'}
            onChange={(e) => handleChange('fieldSize', e.target.value)}
          >
            <option value="small">{__('Small', 'formtura')}</option>
            <option value="medium">{__('Medium', 'formtura')}</option>
            <option value="large">{__('Large', 'formtura')}</option>
          </select>
        </div>

        <div className="formtura-form-group">
          <label>
            {__('Year Range', 'formtura')} <Tooltip text={__('Use four digit years or +/- years to make it dynamic. For example, use -5 for the start of the year and +5 for the end of the year.', 'formtura')} />
          </label>
          <div className="formtura-grid-2">
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
              {__('Hide Label', 'formtura')} <Tooltip text={__('Check this option to hide the form field label.', 'formtura')} />
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
          {__('Field Size', 'formtura')} <Tooltip text={__('Select the default size for the field.', 'formtura')} />
        </label>
        <select
          id="field-size"
          value={field.fieldSize || 'medium'}
          onChange={(e) => handleChange('fieldSize', e.target.value)}
        >
          <option value="small">{__('Small', 'formtura')}</option>
          <option value="medium">{__('Medium', 'formtura')}</option>
          <option value="large">{__('Large', 'formtura')}</option>
        </select>
      </div>

      <div className="formtura-form-group">
        <label htmlFor="field-placeholder">
          {__('Placeholder Text', 'formtura')} <Tooltip text={__('Enter placeholder text that appears inside the input field before the user types.', 'formtura')} />
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
            {__('Hide Label', 'formtura')} <Tooltip text={__('Check this option to hide the form field label.', 'formtura')} />
          </span>
        </div>
      </div>

      {/*
        Hide Sublabels - composite fields only. The name field has its own
        Advanced branch above carrying the same toggle; address reaches this
        default branch, and its template, sanitizer and createField() default
        all honour hideSublabels, so the toggle has to be reachable here too.
      */}
      {field.type === 'address' && (
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
              {__('Hide Sublabels', 'formtura')} <Tooltip text={__('Check this option to hide the sublabels under each address input.', 'formtura')} />
            </span>
          </div>
        </div>
      )}

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
              {__('Read-Only', 'formtura')} <Tooltip text={__('Check this option to display the field\'s value without allowing changes. The value will still be submitted.', 'formtura')} />
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
              {__('Enable Address Autocomplete', 'formtura')} <Tooltip text={__('Enable Google Maps autocomplete for address fields to help users quickly enter their address.', 'formtura')} />
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
              {__('Enable Calculation', 'formtura')} <Tooltip text={__('Enable mathematical calculations using values from other form fields.', 'formtura')} />
            </span>
          </div>
        </div>
      )}

      {field.enableCalculation && field.type !== 'total' && (
        <div className="formtura-form-group">
          <label htmlFor="field-calculation-formula">
            {__('Calculation Formula', 'formtura')} <Tooltip text={__('Enter a mathematical formula using field IDs. Example: {field_1} + {field_2} * 2. Supported operators: +, -, *, /, (). Use {field_ID} to reference other number fields.', 'formtura')} />
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
const SmartLogicTab = ({ field, fields = [], onUpdate }) => {
  const handleChange = (key, value) => {
    onUpdate(field.id, { [key]: value });
  };

  const otherFields = fields.filter((f) => f.id !== field.id);
  const conditions = field.conditionalLogic?.conditions || [];

  const updateConditions = (updated) => {
    handleChange('conditionalLogic', { ...field.conditionalLogic, conditions: updated });
  };

  const addCondition = () => {
    updateConditions([ ...conditions, { field: '', operator: 'is', value: '' } ]);
  };

  const updateCondition = (index, key, value) => {
    updateConditions(conditions.map((c, i) => (i === index ? { ...c, [key]: value } : c)));
  };

  const removeCondition = (index) => {
    updateConditions(conditions.filter((_, i) => i !== index));
  };

  return (
    <div className="formtura-field-options">
      <div className="formtura-field-options-title">
        <strong>{field.label}</strong> <span className="formtura-field-id">(ID #{field.id.slice(-4)})</span>
      </div>

      <div className="formtura-settings-group">
        <div className="formtura-settings-group-title">
          <Zap size={16} /> {__('Conditional Logic', 'formtura')}
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
              {__('Enable Conditional Logic', 'formtura')} <Tooltip text={__('Show or hide this field based on the values entered in other form fields.', 'formtura')} />
            </span>
          </div>
          <p className="formtura-field-description">
            {__('Show or hide this field based on other field values', 'formtura')}
          </p>
        </div>

        {field.conditionalLogic?.enabled && (
          <div className="formtura-logic-rules">
            <div className="formtura-form-group">
              <label>{__('Show this field if:', 'formtura')}</label>
              <select
                value={field.conditionalLogic?.action || 'show'}
                onChange={(e) => handleChange('conditionalLogic', {
                  ...field.conditionalLogic,
                  action: e.target.value
                })}
              >
                <option value="show">{__('Show', 'formtura')}</option>
                <option value="hide">{__('Hide', 'formtura')}</option>
              </select>
            </div>

            <div className="formtura-form-group">
              <label>{__('When the following match:', 'formtura')}</label>
              <select
                value={field.conditionalLogic?.match || 'all'}
                onChange={(e) => handleChange('conditionalLogic', {
                  ...field.conditionalLogic,
                  match: e.target.value
                })}
              >
                <option value="all">{__('All conditions', 'formtura')}</option>
                <option value="any">{__('Any condition', 'formtura')}</option>
              </select>
            </div>

            {conditions.map((condition, index) => (
              <div className="formtura-logic-condition" key={index}>
                <select
                  aria-label={__('Condition field', 'formtura')}
                  value={condition.field}
                  onChange={(e) => updateCondition(index, 'field', e.target.value)}
                >
                  <option value="">{__('Select a field...', 'formtura')}</option>
                  {otherFields.map((f) => (
                    <option key={f.id} value={f.id}>{f.label || f.id}</option>
                  ))}
                </select>

                <select
                  aria-label={__('Condition operator', 'formtura')}
                  value={condition.operator}
                  onChange={(e) => updateCondition(index, 'operator', e.target.value)}
                >
                  <option value="is">{__('is', 'formtura')}</option>
                  <option value="is_not">{__('is not', 'formtura')}</option>
                  <option value="contains">{__('contains', 'formtura')}</option>
                  <option value="greater_than">{__('greater than', 'formtura')}</option>
                  <option value="less_than">{__('less than', 'formtura')}</option>
                </select>

                <input
                  type="text"
                  className="formtura-choice-input"
                  aria-label={__('Condition value', 'formtura')}
                  value={condition.value}
                  onChange={(e) => updateCondition(index, 'value', e.target.value)}
                  placeholder={__('Value', 'formtura')}
                />

                <button
                  type="button"
                  className="formtura-btn-icon"
                  aria-label={__('Remove condition', 'formtura')}
                  onClick={() => removeCondition(index)}
                >
                  &times;
                </button>
              </div>
            ))}

            <button className="formtura-btn" type="button" onClick={addCondition}>
              + {__('Add Condition', 'formtura')}
            </button>
          </div>
        )}
      </div>

      <div className="formtura-settings-group">
        <div className="formtura-settings-group-title">
          <Zap size={16} /> {__('Field Behavior', 'formtura')}
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
              {__('Dynamic Default Value', 'formtura')} <Tooltip text={__('Automatically populate this field with a value from another field when the form loads.', 'formtura')} />
            </span>
          </div>
          <p className="formtura-field-description">
            {__('Auto-populate this field based on another field\'s value', 'formtura')}
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
              {__('Enable/Disable Based on Logic', 'formtura')} <Tooltip text={__('Enable or disable this field based on conditions set from other form field values.', 'formtura')} />
            </span>
          </div>
          <p className="formtura-field-description">
            {__('Make field interactive or read-only based on conditions', 'formtura')}
          </p>
        </div>
      </div>

      <div className="formtura-settings-group">
        <div className="formtura-settings-group-title">
          <Zap size={16} /> {__('Page Navigation', 'formtura')}
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
              {__('Branching/Skip Logic', 'formtura')} <Tooltip text={__('Redirect users to different form pages based on their answer to this field.', 'formtura')} />
            </span>
          </div>
          <p className="formtura-field-description">
            {__('Redirect users to different pages based on their answer', 'formtura')}
          </p>
        </div>

        {field.branchingLogic && (
          <div className="formtura-form-group">
            <label htmlFor="branch-target">{__('Go to Page', 'formtura')}</label>
            <select
              id="branch-target"
              value={field.branchTarget || ''}
              onChange={(e) => handleChange('branchTarget', e.target.value)}
            >
              <option value="">{__('Select page...', 'formtura')}</option>
              <option value="page-2">{__('Page 2', 'formtura')}</option>
              <option value="page-3">{__('Page 3', 'formtura')}</option>
              <option value="submit">{__('Submit Form', 'formtura')}</option>
            </select>
          </div>
        )}
      </div>
    </div>
  );
};

export default FieldLibrary;
