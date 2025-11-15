import React from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical, Copy, Trash2 } from 'lucide-react';
import FieldPreview from './FieldPreview';

const DroppedField = ({ field, isSelected, onSelect, onDelete, onDuplicate }) => {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: field.id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`formtura-dropped-field ${isSelected ? 'selected' : ''} ${isDragging ? 'dragging' : ''}`}
      onClick={onSelect}
    >
      <div className="formtura-field-header">
        <div className="formtura-field-type">
          <GripVertical size={16} {...attributes} {...listeners} style={{ cursor: 'grab' }} />
          {field.type.toUpperCase()}
        </div>
        <div className="formtura-field-actions">
          <button
            className="formtura-field-action-btn"
            onClick={(e) => {
              e.stopPropagation();
              onDuplicate();
            }}
            title="Duplicate field"
            type="button"
          >
            <Copy size={16} />
          </button>
          <button
            className="formtura-field-action-btn"
            onClick={(e) => {
              e.stopPropagation();
              if (confirm('Are you sure you want to delete this field?')) {
                onDelete();
              }
            }}
            title="Delete field"
            type="button"
          >
            <Trash2 size={16} />
          </button>
        </div>
      </div>
      
      <FieldPreview field={field} />
    </div>
  );
};

export default DroppedField;
