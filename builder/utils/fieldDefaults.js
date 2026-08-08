/**
 * Field defaults for the builder.
 *
 * createField() is the single place a newly placed field's shape is decided,
 * and getDefaultLabel() the single place its starting label is. They live here
 * rather than inside the FormBuilder component so tests can assert on what the
 * builder actually produces per type - the save-path allowlist in
 * Form_Builder::sanitize_field_data() is only as good as its idea of that
 * shape (see tests/fixtures/builder-field-settings.json).
 */

import { generateFieldId } from './helpers';

export const createField = (type) => {
  const baseField = {
    id: generateFieldId(),
    type,
    label: getDefaultLabel(type),
    placeholder: '',
    required: false,
    description: '',
  };

  // Add type-specific properties
  switch (type) {
    case 'text':
    case 'email':
    case 'number':
      return { ...baseField };
    case 'textarea':
      return { ...baseField, rows: 4 };
    case 'select':
    case 'radio':
    case 'checkbox':
      return {
        ...baseField,
        choices: [
          { label: 'First Choice', value: 'first-choice', isDefault: false },
          { label: 'Second Choice', value: 'second-choice', isDefault: false },
          { label: 'Third Choice', value: 'third-choice', isDefault: false },
        ],
      };
    case 'name':
      return {
        ...baseField,
        label: 'Name',
        format: 'first-last',
        firstNamePlaceholder: '',
        lastNamePlaceholder: '',
        middleNamePlaceholder: '',
        firstNameDefault: '',
        lastNameDefault: '',
        middleNameDefault: '',
        hideSublabels: false,
      };
    case 'address':
      return {
        ...baseField,
        label: 'Address',
        scheme: 'us',
        hideSublabels: false,
      };
    case 'number-slider':
      return {
        ...baseField,
        minValue: 0,
        maxValue: 10,
        defaultValue: 0,
        increment: 1,
        valueDisplay: 'Selected Value: {value}',
      };
    case 'repeater':
      return {
        ...baseField,
        label: 'Repeater',
        collapsible: false,
        repeatLayout: 'default',
        addNewLabel: 'Add',
        removeLabel: 'Remove',
        minRows: '',
        maxRows: '',
        children: [],
      };
    case 'rating':
      return {
        ...baseField,
        label: 'Star Rating',
        maxRating: 5,
        unique: false,
      };
    case 'datetime':
      return {
        ...baseField,
        label: 'Date',
        yearRangeStart: '-10',
        yearRangeEnd: '+10',
      };
    case 'rich-text':
      return {
        ...baseField,
        label: 'Rich Text',
        content: '',
        fieldSize: 'px',
        rows: 7,
      };
    case 'html':
      return {
        ...baseField,
        label: 'HTML',
        content: '',
      };
    // Presentational block of author-supplied copy. Its text lives under
    // `content` - the key templates/fields/content.php reads - not under
    // `description`, which that template ignores entirely.
    case 'content':
      return {
        ...baseField,
        label: 'Content',
        content: '',
      };
    case 'file-upload':
      return {
        ...baseField,
        label: 'File Upload',
        allowMultiple: false,
        attachToEmail: false,
        deleteOnReplace: false,
        autoResize: false,
        allowedFileTypes: 'specify',
        specifiedTypes: 'jpg, jpeg, jpe, png, gif',
        minFileSize: '',
        maxFileSize: '',
        uploadText: 'Drop a file here or click to upload',
        compactUploadText: 'Choose File',
      };
    case 'camera':
      return { ...baseField, label: 'Camera' };
    case 'signature':
      return { ...baseField, label: 'Signature' };
    case 'payment-single':
      return { ...baseField, label: 'Single Item', price: '10.00' };
    case 'payment-checkbox':
    case 'payment-multiple':
    case 'payment-dropdown':
      return {
        ...baseField,
        label: type === 'payment-checkbox' ? 'Checkbox Items'
          : type === 'payment-multiple' ? 'Multiple Items' : 'Dropdown Items',
        items: [
          { label: 'First Item', value: 'first-item', price: '10.00', isDefault: false },
          { label: 'Second Item', value: 'second-item', price: '25.00', isDefault: false },
          { label: 'Third Item', value: 'third-item', price: '50.00', isDefault: false },
        ],
        showPriceAfterLabels: true,
      };
    case 'total':
      return { ...baseField, label: 'Total', enableSummary: false };
    case 'coupon':
      return { ...baseField, label: 'Coupon', coupons: [] };
    default:
      return baseField;
  }
};

export const getDefaultLabel = (type) => {
  const labels = {
    text: 'Text Field',
    email: 'Email Address',
    textarea: 'Message',
    number: 'Number',
    select: 'Dropdown',
    radio: 'Multiple Choice',
    checkbox: 'Checkboxes',
    name: 'Name',
    address: 'Address',
    phone: 'Phone Number',
    date: 'Date',
    'number-slider': 'Slider',
    'repeater': 'Repeater',
    'rating': 'Star Rating',
    'datetime': 'Date',
    'rich-text': 'Rich Text',
    'html': 'HTML',
    'content': 'Content',
    'section-divider': 'Section Divider',
    'file-upload': 'File Upload',
    'camera': 'Camera',
    'website': 'Website',
    'hidden': 'Hidden Field',
    'signature': 'Signature',
    'payment-single': 'Single Item',
    'payment-checkbox': 'Checkbox Items',
    'payment-multiple': 'Multiple Items',
    'payment-dropdown': 'Dropdown Items',
    'total': 'Total',
    'coupon': 'Coupon',
  };
  return labels[type] || 'Field';
};
