import { render, screen } from '@testing-library/react';
import { DndContext } from '@dnd-kit/core';
import FormCanvas from '../FormCanvas';

// Mock DroppedField component
jest.mock('../DroppedField', () => {
  return function MockDroppedField({ field, isSelected, onSelect, onDelete, onDuplicate }) {
    return (
      <div data-testid={`field-${field.id}`}>
        <span>{field.label}</span>
        <button onClick={onSelect}>Select</button>
        <button onClick={onDelete}>Delete</button>
        <button onClick={onDuplicate}>Duplicate</button>
        {isSelected && <span>Selected</span>}
      </div>
    );
  };
});

describe('FormCanvas', () => {
  const defaultProps = {
    fields: [],
    selectedField: null,
    onFieldSelect: jest.fn(),
    onFieldDelete: jest.fn(),
    onFieldDuplicate: jest.fn(),
  };

  const renderWithDnd = (component) => {
    return render(
      <DndContext>
        {component}
      </DndContext>
    );
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('empty state', () => {
    it('should display empty state when no fields', () => {
      renderWithDnd(<FormCanvas {...defaultProps} />);

      expect(screen.getByText('Start Building Your Form')).toBeInTheDocument();
      expect(screen.getByText(/Drag and drop fields from the left sidebar/)).toBeInTheDocument();
    });

    it('should show empty icon in empty state', () => {
      const { container } = renderWithDnd(<FormCanvas {...defaultProps} />);
      const icon = container.querySelector('.formtura-canvas-empty-icon');

      expect(icon).toBeInTheDocument();
    });
  });

  describe('with fields', () => {
    const fields = [
      { id: 'field_1', type: 'text', label: 'Name Field' },
      { id: 'field_2', type: 'email', label: 'Email Field' },
      { id: 'field_3', type: 'textarea', label: 'Message Field' },
    ];

    it('should render all fields', () => {
      renderWithDnd(
        <FormCanvas {...defaultProps} fields={fields} />
      );

      expect(screen.getByText('Name Field')).toBeInTheDocument();
      expect(screen.getByText('Email Field')).toBeInTheDocument();
      expect(screen.getByText('Message Field')).toBeInTheDocument();
    });

    it('should not show empty state when fields exist', () => {
      renderWithDnd(
        <FormCanvas {...defaultProps} fields={fields} />
      );

      expect(screen.queryByText('Start Building Your Form')).not.toBeInTheDocument();
    });

    it('should mark selected field', () => {
      renderWithDnd(
        <FormCanvas {...defaultProps} fields={fields} selectedField="field_2" />
      );

      const selectedFields = screen.getAllByText('Selected');
      expect(selectedFields).toHaveLength(1);
    });

    it('should call onFieldSelect when field is selected', () => {
      const onFieldSelect = jest.fn();
      renderWithDnd(
        <FormCanvas
          {...defaultProps}
          fields={fields}
          onFieldSelect={onFieldSelect}
        />
      );

      const selectButtons = screen.getAllByText('Select');
      selectButtons[0].click();

      expect(onFieldSelect).toHaveBeenCalledWith('field_1');
    });

    it('should call onFieldDelete when field is deleted', () => {
      const onFieldDelete = jest.fn();
      renderWithDnd(
        <FormCanvas
          {...defaultProps}
          fields={fields}
          onFieldDelete={onFieldDelete}
        />
      );

      const deleteButtons = screen.getAllByText('Delete');
      deleteButtons[1].click();

      expect(onFieldDelete).toHaveBeenCalledWith('field_2');
    });

    it('should call onFieldDuplicate when field is duplicated', () => {
      const onFieldDuplicate = jest.fn();
      renderWithDnd(
        <FormCanvas
          {...defaultProps}
          fields={fields}
          onFieldDuplicate={onFieldDuplicate}
        />
      );

      const duplicateButtons = screen.getAllByText('Duplicate');
      duplicateButtons[2].click();

      expect(onFieldDuplicate).toHaveBeenCalledWith('field_3');
    });
  });

  describe('droppable behavior', () => {
    it('should have droppable area', () => {
      const { container } = renderWithDnd(<FormCanvas {...defaultProps} />);
      const canvas = container.querySelector('.formtura-canvas');

      expect(canvas).toBeInTheDocument();
    });
  });
});
