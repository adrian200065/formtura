/**
 * Generate a unique field ID
 */
export const generateFieldId = () => {
  return `field_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
};

/**
 * Validate field data
 */
export const validateField = (field) => {
  if (!field.label || field.label.trim() === '') {
    return { valid: false, message: 'Field label is required' };
  }
  
  // `choices` is the current shape; `options` is the pre-1.0.3 string array.
  if (['select', 'radio', 'checkbox', 'checkboxes'].includes(field.type)) {
    const choices = field.choices || field.options;

    if (!choices || choices.length === 0) {
      return { valid: false, message: 'At least one option is required' };
    }
  }
  
  return { valid: true };
};

/**
 * Export form data as JSON
 */
export const exportFormData = (fields, settings) => {
  return JSON.stringify({ fields, settings }, null, 2);
};

/**
 * Import form data from JSON
 */
export const importFormData = (jsonString) => {
  try {
    const data = JSON.parse(jsonString);
    return { success: true, data };
  } catch (error) {
    return { success: false, error: 'Invalid JSON format' };
  }
};
