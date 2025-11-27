import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Copy, GripVertical, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from './ConfirmDialog';
import FieldPreview from './FieldPreview';

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

  return (
    <>
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
              onClick={handleDeleteClick}
              title="Delete field"
              type="button"
            >
              <Trash2 size={16} />
            </button>
          </div>
        </div>

        <FieldPreview field={field} />
      </div>

      <ConfirmDialog
        isOpen={showDeleteConfirm}
        title=""
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
