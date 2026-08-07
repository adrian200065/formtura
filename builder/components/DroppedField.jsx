import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Copy, GripVertical, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from './ConfirmDialog';
import FieldPreview from './FieldPreview';
import Button from './ui/Button';

const DroppedField = ({ field, isSelected, onSelect, onDelete, onDuplicate }) => {
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

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

  const handleDeleteClick = (e) => {
    e.stopPropagation();
    setShowDeleteConfirm(true);
  };

  const handleConfirmDelete = () => {
    setShowDeleteConfirm(false);
    onDelete();
  };

  const handleCancelDelete = () => {
    setShowDeleteConfirm(false);
  };

  const handleKeyDown = (event) => {
    if (event.currentTarget !== event.target) return;
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      onSelect();
    }
  };

  return (
    <>
      <article
        ref={setNodeRef}
        style={style}
        className={`formtura-dropped-field ${isSelected ? 'selected' : ''} ${isDragging ? 'dragging' : ''}`}
        onClick={onSelect}
        onKeyDown={handleKeyDown}
        tabIndex={0}
        aria-label={`${field.label || 'Untitled field'}, ${isSelected ? 'selected' : 'select to edit'}`}
        aria-current={isSelected ? 'true' : undefined}
      >
        <div className="formtura-field-header">
          <div className="formtura-field-type">
            <button
              className="formtura-drag-handle"
              type="button"
              aria-label={`Reorder ${field.label || 'field'}`}
              {...attributes}
              {...listeners}
            >
              <GripVertical aria-hidden="true" />
            </button>
            <span>{field.type.replaceAll('-', ' ')}</span>
          </div>
          <div className="formtura-field-actions">
            <Button
              className="formtura-field-action-btn"
              variant="ghost"
              icon={Copy}
              iconOnly
              onClick={(e) => {
                e.stopPropagation();
                onDuplicate();
              }}
            >
              Duplicate field
            </Button>
            <Button
              className="formtura-field-action-btn formtura-field-delete-btn"
              variant="ghost"
              icon={Trash2}
              iconOnly
              onClick={handleDeleteClick}
            >
              Delete field
            </Button>
          </div>
        </div>

        <FieldPreview field={field} />
      </article>

      <ConfirmDialog
        isOpen={showDeleteConfirm}
        title="Delete field?"
        message="Are you sure you want to delete this field?"
        confirmText="OK"
        cancelText="Cancel"
        onConfirm={handleConfirmDelete}
        onCancel={handleCancelDelete}
        type="danger"
      />
    </>
  );
};

export default DroppedField;
