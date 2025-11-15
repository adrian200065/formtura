import { useDroppable } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import DroppedField from './DroppedField';

const FormCanvas = ({
  fields,
  selectedField,
  onFieldSelect,
  onFieldDelete,
  onFieldDuplicate
}) => {
  const { setNodeRef } = useDroppable({
    id: 'canvas-droppable',
  });

  return (
    <div className="formtura-canvas" ref={setNodeRef}>
      {fields.length === 0 ? (
        <div className="formtura-canvas-empty">
          <svg
            className="formtura-canvas-empty-icon"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
          </svg>
          <h3 className="formtura-canvas-empty-title">Start Building Your Form</h3>
          <p className="formtura-canvas-empty-text">
            Drag and drop fields from the left sidebar to begin creating your form
          </p>
        </div>
      ) : (
        <SortableContext
          items={fields.map(f => f.id)}
          strategy={verticalListSortingStrategy}
        >
          {fields.map((field) => (
            <DroppedField
              key={field.id}
              field={field}
              isSelected={selectedField === field.id}
              onSelect={() => onFieldSelect(field.id)}
              onDelete={() => onFieldDelete(field.id)}
              onDuplicate={() => onFieldDuplicate(field.id)}
            />
          ))}
        </SortableContext>
      )}
    </div>
  );
};

export default FormCanvas;
