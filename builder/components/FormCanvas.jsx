import { useDroppable } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import DroppedField from './DroppedField';
import { __ } from '../utils/i18n';

const FormCanvas = ({
  fields,
  selectedField,
  onFieldSelect,
  onFieldDelete,
  onFieldDuplicate,
  formSettings = {},
}) => {
  const { setNodeRef } = useDroppable({
    id: 'canvas-droppable',
  });

  return (
    <main
      id="formtura-builder-canvas"
      className="formtura-workspace"
      aria-label={__('Form canvas', 'formtura')}
    >
      <section className="formtura-canvas-card" aria-labelledby="formtura-form-title">
        <header className="formtura-form-heading">
          <p className="formtura-section-kicker">{__('Live form', 'formtura')}</p>
          <h2 id="formtura-form-title">
            {formSettings.title || __('Untitled form', 'formtura')}
          </h2>
          {formSettings.description && <p>{formSettings.description}</p>}
        </header>
        <div className="formtura-canvas" ref={setNodeRef}>
          {fields.length === 0 ? (
            <div className="formtura-canvas-empty">
          <svg
            className="formtura-canvas-empty-icon"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
          </svg>
          <h3 className="formtura-canvas-empty-title">{__('Start Building Your Form', 'formtura')}</h3>
          <p className="formtura-canvas-empty-text">
            {__('Drag and drop fields from the left sidebar, or focus a field and press Enter.', 'formtura')}
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
      </section>
    </main>
  );
};

export default FormCanvas;
