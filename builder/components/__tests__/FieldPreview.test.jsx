import { render, screen } from '@testing-library/react';
import FieldPreview from '../FieldPreview';

describe('FieldPreview', () => {
  describe('text input fields', () => {
    it('should render text field with label', () => {
      const field = {
        id: 'field_1',
        type: 'text',
        label: 'Full Name',
        placeholder: 'Enter your name',
        required: false,
      };

      render(<FieldPreview field={field} />);

      expect(screen.getByText('Full Name')).toBeInTheDocument();
      expect(screen.getByPlaceholderText('Enter your name')).toBeInTheDocument();
    });

    it('should show required asterisk when field is required', () => {
      const field = {
        id: 'field_1',
        type: 'email',
        label: 'Email Address',
        required: true,
      };

      render(<FieldPreview field={field} />);

      expect(screen.getByText('*')).toBeInTheDocument();
    });

    it('should hide label when hideLabel is true', () => {
      const field = {
        id: 'field_1',
        type: 'text',
        label: 'Hidden Label',
        hideLabel: true,
      };

      render(<FieldPreview field={field} />);

      expect(screen.queryByText('Hidden Label')).not.toBeInTheDocument();
    });

    it('should render description when provided', () => {
      const field = {
        id: 'field_1',
        type: 'text',
        label: 'Username',
        description: 'Choose a unique username',
      };

      render(<FieldPreview field={field} />);

      expect(screen.getByText('Choose a unique username')).toBeInTheDocument();
    });
  });

  describe('textarea field', () => {
    it('should render textarea with custom rows', () => {
      const field = {
        id: 'field_1',
        type: 'textarea',
        label: 'Message',
        placeholder: 'Your message',
        rows: 6,
      };

      const { container } = render(<FieldPreview field={field} />);
      const textarea = container.querySelector('textarea');

      expect(textarea).toBeInTheDocument();
      expect(textarea).toHaveAttribute('rows', '6');
      expect(textarea).toHaveAttribute('placeholder', 'Your message');
    });
  });

  describe('select field', () => {
    it('should render select with options', () => {
      const field = {
        id: 'field_1',
        type: 'select',
        label: 'Choose Country',
        options: ['USA', 'Canada', 'UK'],
      };

      render(<FieldPreview field={field} />);

      expect(screen.getByText('USA')).toBeInTheDocument();
      expect(screen.getByText('Canada')).toBeInTheDocument();
      expect(screen.getByText('UK')).toBeInTheDocument();
    });

    it('should support choice objects with label and value', () => {
      const field = {
        id: 'field_1',
        type: 'select',
        label: 'Select Option',
        choices: [
          { label: 'Option One', value: 'opt1' },
          { label: 'Option Two', value: 'opt2' },
        ],
      };

      render(<FieldPreview field={field} />);

      expect(screen.getByText('Option One')).toBeInTheDocument();
      expect(screen.getByText('Option Two')).toBeInTheDocument();
    });
  });

  describe('radio field', () => {
    it('should render radio buttons', () => {
      const field = {
        id: 'field_1',
        type: 'radio',
        label: 'Select One',
        options: ['Yes', 'No'],
      };

      const { container } = render(<FieldPreview field={field} />);
      const radios = container.querySelectorAll('input[type="radio"]');

      expect(radios).toHaveLength(2);
      expect(screen.getByText('Yes')).toBeInTheDocument();
      expect(screen.getByText('No')).toBeInTheDocument();
    });
  });

  describe('checkboxes field', () => {
    it('should render multiple checkboxes', () => {
      const field = {
        id: 'field_1',
        type: 'checkboxes',
        label: 'Select Multiple',
        options: ['Apple', 'Banana', 'Orange'],
      };

      const { container } = render(<FieldPreview field={field} />);
      const checkboxes = container.querySelectorAll('input[type="checkbox"]');

      expect(checkboxes).toHaveLength(3);
      expect(screen.getByText('Apple')).toBeInTheDocument();
      expect(screen.getByText('Banana')).toBeInTheDocument();
    });
  });

  describe('name field', () => {
    it('should render simple name format', () => {
      const field = {
        id: 'field_1',
        type: 'name',
        label: 'Your Name',
        format: 'simple',
      };

      const { container } = render(<FieldPreview field={field} />);
      const inputs = container.querySelectorAll('input[type="text"]');

      expect(inputs).toHaveLength(1);
      expect(inputs[0]).toHaveAttribute('placeholder', 'Name');
    });

    it('should render first-last name format (default)', () => {
      const field = {
        id: 'field_1',
        type: 'name',
        label: 'Full Name',
      };

      const { container } = render(<FieldPreview field={field} />);
      const inputs = container.querySelectorAll('input[type="text"]');

      expect(inputs).toHaveLength(2);
      expect(screen.getByPlaceholderText('First Name')).toBeInTheDocument();
      expect(screen.getByPlaceholderText('Last Name')).toBeInTheDocument();
    });

    it('should render first-middle-last name format', () => {
      const field = {
        id: 'field_1',
        type: 'name',
        label: 'Full Name',
        format: 'first-middle-last',
      };

      const { container } = render(<FieldPreview field={field} />);
      const inputs = container.querySelectorAll('input[type="text"]');

      expect(inputs).toHaveLength(3);
      expect(screen.getByPlaceholderText('Middle Name')).toBeInTheDocument();
    });
  });

  describe('number-slider field', () => {
    it('should render slider with default values', () => {
      const field = {
        id: 'field_1',
        type: 'number-slider',
        label: 'Rating',
      };

      const { container } = render(<FieldPreview field={field} />);
      const slider = container.querySelector('input[type="range"]');

      expect(slider).toBeInTheDocument();
      expect(slider).toHaveAttribute('min', '0');
      expect(slider).toHaveAttribute('max', '10');
    });

    it('should render slider with custom min, max, and default values', () => {
      const field = {
        id: 'field_1',
        type: 'number-slider',
        label: 'Score',
        minValue: 1,
        maxValue: 100,
        defaultValue: 50,
        increment: 5,
      };

      const { container } = render(<FieldPreview field={field} />);
      const slider = container.querySelector('input[type="range"]');

      expect(slider).toHaveAttribute('min', '1');
      expect(slider).toHaveAttribute('max', '100');
      expect(slider).toHaveAttribute('value', '50');
      expect(slider).toHaveAttribute('step', '5');
    });

    it('should display value with custom format', () => {
      const field = {
        id: 'field_1',
        type: 'number-slider',
        label: 'Temperature',
        defaultValue: 72,
        valueDisplay: 'Current: {value}°F',
      };

      render(<FieldPreview field={field} />);

      expect(screen.getByText('Current: 72°F')).toBeInTheDocument();
    });
  });

  describe('field sizing', () => {
    it('should apply small size class', () => {
      const field = {
        id: 'field_1',
        type: 'text',
        label: 'Small Field',
        fieldSize: 'small',
      };

      const { container } = render(<FieldPreview field={field} />);
      const sizeDiv = container.querySelector('.formtura-field-size-small');

      expect(sizeDiv).toBeInTheDocument();
    });

    it('should apply large size class', () => {
      const field = {
        id: 'field_1',
        type: 'text',
        label: 'Large Field',
        fieldSize: 'large',
      };

      const { container } = render(<FieldPreview field={field} />);
      const sizeDiv = container.querySelector('.formtura-field-size-large');

      expect(sizeDiv).toBeInTheDocument();
    });

    it('should default to medium size', () => {
      const field = {
        id: 'field_1',
        type: 'text',
        label: 'Default Field',
      };

      const { container } = render(<FieldPreview field={field} />);
      const sizeDiv = container.querySelector('.formtura-field-size-medium');

      expect(sizeDiv).toBeInTheDocument();
    });
  });

  describe('phone field', () => {
    it('should render phone input with placeholder', () => {
      const field = {
        id: 'field_1',
        type: 'phone',
        label: 'Phone Number',
      };

      const { container } = render(<FieldPreview field={field} />);
      const input = container.querySelector('input[type="tel"]');

      expect(input).toBeInTheDocument();
      expect(input).toHaveAttribute('placeholder', '(123) 456-7890');
    });
  });

  describe('date field', () => {
    it('should render date input', () => {
      const field = {
        id: 'field_1',
        type: 'date',
        label: 'Birth Date',
        required: true,
      };

      const { container } = render(<FieldPreview field={field} />);
      const input = container.querySelector('input[type="date"]');

      expect(input).toBeInTheDocument();
      expect(input).toHaveAttribute('required');
    });
  });
});
