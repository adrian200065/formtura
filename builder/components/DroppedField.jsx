import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Copy, GripVertical, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from './ConfirmDialog';
import FieldPreview from './FieldPreview';
import Button from './ui/Button';
import { __, sprintf } from '../utils/i18n';

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
        aria-label={sprintf(
          __('%1$s, %2$s', 'formtura'),
          field.label || __('Untitled field', 'formtura'),
          isSelected ? __('selected', 'formtura') : __('select to edit', 'formtura')
        )}
        aria-current={isSelected ? 'true' : undefined}
      >
        <div className="formtura-field-header">
          <div className="formtura-field-type">
            <button
              className="formtura-drag-handle"
              type="button"
              aria-label={sprintf(__('Reorder %s', 'formtura'), field.label || __('field', 'formtura'))}
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
              {__('Duplicate field', 'formtura')}
            </Button>
            <Button
              className="formtura-field-action-btn formtura-field-delete-btn"
              variant="ghost"
              icon={Trash2}
              iconOnly
              onClick={handleDeleteClick}
            >
              {__('Delete field', 'formtura')}
            </Button>
          </div>
        </div>

        <FieldPreview field={field} />
      </article>

      <ConfirmDialog
        isOpen={showDeleteConfirm}
        title={__('Delete field?', 'formtura')}
        message={__('Are you sure you want to delete this field?', 'formtura')}
        confirmText={__('OK', 'formtura')}
        cancelText={__('Cancel', 'formtura')}
        onConfirm={handleConfirmDelete}
        onCancel={handleCancelDelete}
        type="danger"
      />
    </>
  );
};

export default DroppedField;
