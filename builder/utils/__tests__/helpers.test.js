import { generateFieldId, validateField, exportFormData, importFormData } from '../helpers';

describe('helpers utility functions', () => {
  describe('generateFieldId', () => {
    it('should generate a unique field ID', () => {
      const id1 = generateFieldId();
      const id2 = generateFieldId();

      expect(id1).toMatch(/^field_\d+_[a-z0-9]+$/);
      expect(id1).not.toBe(id2);
    });

    it('should start with "field_" prefix', () => {
      const id = generateFieldId();
      expect(id).toMatch(/^field_/);
    });
  });

  describe('validateField', () => {
    it('should return valid for field with label', () => {
      const field = { label: 'Test Field', type: 'text' };
      const result = validateField(field);

      expect(result.valid).toBe(true);
    });

    it('should return invalid for field without label', () => {
      const field = { label: '', type: 'text' };
      const result = validateField(field);

      expect(result.valid).toBe(false);
      expect(result.message).toBe('Field label is required');
    });

    it('should return invalid for field with whitespace-only label', () => {
      const field = { label: '   ', type: 'text' };
      const result = validateField(field);

      expect(result.valid).toBe(false);
      expect(result.message).toBe('Field label is required');
    });

    it('should return invalid for select field without options', () => {
      const field = { label: 'Dropdown', type: 'select', options: [] };
      const result = validateField(field);

      expect(result.valid).toBe(false);
      expect(result.message).toBe('At least one option is required');
    });

    it('should return valid for select field with options', () => {
      const field = {
        label: 'Dropdown',
        type: 'select',
        options: ['Option 1', 'Option 2']
      };
      const result = validateField(field);

      expect(result.valid).toBe(true);
    });

    it('should validate radio and checkbox fields for options', () => {
      const radioField = { label: 'Radio', type: 'radio', options: [] };
      const checkboxField = { label: 'Checkbox', type: 'checkbox', options: [] };

      expect(validateField(radioField).valid).toBe(false);
      expect(validateField(checkboxField).valid).toBe(false);
    });
  });

  describe('exportFormData', () => {
    it('should export form data as JSON string', () => {
      const fields = [
        { id: 'field_1', type: 'text', label: 'Name' }
      ];
      const settings = { title: 'Contact Form' };

      const result = exportFormData(fields, settings);

      expect(typeof result).toBe('string');
      expect(JSON.parse(result)).toEqual({ fields, settings });
    });

    it('should format JSON with indentation', () => {
      const fields = [{ id: 'field_1', type: 'text' }];
      const settings = { title: 'Form' };

      const result = exportFormData(fields, settings);

      expect(result).toContain('\n');
      expect(result).toContain('  ');
    });
  });

  describe('importFormData', () => {
    it('should import valid JSON string', () => {
      const jsonString = '{"fields":[{"id":"field_1"}],"settings":{"title":"Test"}}';
      const result = importFormData(jsonString);

      expect(result.success).toBe(true);
      expect(result.data.fields).toHaveLength(1);
      expect(result.data.settings.title).toBe('Test');
    });

    it('should return error for invalid JSON', () => {
      const invalidJson = '{invalid json}';
      const result = importFormData(invalidJson);

      expect(result.success).toBe(false);
      expect(result.error).toBe('Invalid JSON format');
    });

    it('should handle empty string', () => {
      const result = importFormData('');

      expect(result.success).toBe(false);
    });
  });
});
