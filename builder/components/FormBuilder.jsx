import { closestCenter, DndContext, DragOverlay, KeyboardSensor, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import { arrayMove, sortableKeyboardCoordinates } from '@dnd-kit/sortable';
import { ArrowLeft, Check, Code2, Eye } from 'lucide-react';
import { useEffect, useState } from 'react';
import { handleError, handleSuccess } from '../utils/errorHandler';
import { createField, getDefaultLabel } from '../utils/fieldDefaults';
import { generateFieldId } from '../utils/helpers';
import FieldLibrary from './FieldLibrary';
import FormCanvas from './FormCanvas';
import FormPreview from './FormPreview';
import { announce } from './LiveRegion';
import Button from './ui/Button';

const FormBuilder = ({ formId }) => {
  const [fields, setFields] = useState([]);
  const [selectedField, setSelectedField] = useState(null);
  const [activeId, setActiveId] = useState(null);
  const [formSettings, setFormSettings] = useState({
    title: '',
    description: '',
    submitButtonText: 'Submit',
    successMessage: 'Thank you for your submission!',
  });
  const [isSaving, setIsSaving] = useState(false);
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);
  const [showPreview, setShowPreview] = useState(false);

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    }),
  );

  // Load form data if editing existing form
  useEffect(() => {
    if (formId) {
      loadForm(formId);
    }
  }, [formId]);

  const loadForm = async (id) => {
    try {
      const response = await fetch(`${window.formturaBuilder.ajaxUrl}?action=fta_get_form&form_id=${id}&nonce=${window.formturaBuilder.nonce}`);
      const data = await response.json();

      if (data.success && data.data) {
        const formData = JSON.parse(data.data.form_data || '{}');
        setFields(formData.fields || []);
        setFormSettings((currentSettings) => ({
          ...currentSettings,
          ...(formData.settings || {}),
          title: formData.settings?.title || data.data.title || '',
        }));
      } else {
        handleError('Failed to load form data', {
          userMessage: 'Could not load form. Please try again.',
        });
      }
    } catch (error) {
      handleError(error, {
        userMessage: 'Failed to load form. Please refresh the page.',
      });
    }
  };

  const handleDragStart = (event) => {
    setActiveId(event.active.id);
  };

  const handleDragEnd = (event) => {
    const { active, over } = event;
    setActiveId(null);

    if (!over) return;

    // Dragging from library to canvas
    if (active.id.startsWith('library-')) {
      const fieldType = active.id.replace('library-', '');
      const newField = createField(fieldType);

      // If dropping on the canvas droppable area or on an existing field
      if (over.id === 'canvas-droppable') {
        // Add to end of fields array
        setFields([...fields, newField]);
        setSelectedField(newField.id);
        return;
      } else {
        // Dropping on an existing field - insert after that field
        const overIndex = fields.findIndex(f => f.id === over.id);
        if (overIndex !== -1) {
          const newFields = [...fields];
          newFields.splice(overIndex + 1, 0, newField);
          setFields(newFields);
          setSelectedField(newField.id);
          return;
        } else {
          // Fallback: add to end
          setFields([...fields, newField]);
          setSelectedField(newField.id);
          return;
        }
      }
    }

    // Reordering within canvas
    if (!active.id.startsWith('library-') && !over.id.startsWith('library-')) {
      const oldIndex = fields.findIndex(f => f.id === active.id);
      const newIndex = fields.findIndex(f => f.id === over.id);

      if (oldIndex !== -1 && newIndex !== -1) {
        setFields(arrayMove(fields, oldIndex, newIndex));
      }
    }
  };

  const handleFieldUpdate = (fieldId, updates) => {
    setFields(fields.map(field =>
      field.id === fieldId ? { ...field, ...updates } : field
    ));
  };

  const handleFieldAdd = (fieldType) => {
    const newField = createField(fieldType);
    setFields((currentFields) => [...currentFields, newField]);
    setSelectedField(newField.id);
    announce(`${newField.label} added`);
  };

  const handleFieldDelete = (fieldId) => {
    const field = fields.find(f => f.id === fieldId);
    setFields(fields.filter(field => field.id !== fieldId));
    if (selectedField === fieldId) {
      setSelectedField(null);
    }
    announce(`Field "${field?.label || 'Untitled'}" deleted`);
  };

  const handleFieldDuplicate = (fieldId) => {
    const fieldToDuplicate = fields.find(f => f.id === fieldId);
    if (fieldToDuplicate) {
      const newField = {
        ...fieldToDuplicate,
        id: generateFieldId(),
        label: `${fieldToDuplicate.label} (Copy)`,
      };
      const index = fields.findIndex(f => f.id === fieldId);
      const newFields = [...fields];
      newFields.splice(index + 1, 0, newField);
      setFields(newFields);
      announce(`Field "${fieldToDuplicate.label}" duplicated`);
    }
  };

  const handleSaveForm = async () => {
    setIsSaving(true);
    try {
      const formData = {
        fields,
        settings: formSettings,
      };

      const response = await fetch(window.formturaBuilder.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'fta_save_form',
          form_id: formId || '',
          form_data: JSON.stringify(formData),
          nonce: window.formturaBuilder.nonce,
        }),
      });

      const data = await response.json();

      if (data.success) {
        handleSuccess('Form saved successfully!');

        // If this was a new form (no formId), redirect to edit page with the new ID
        if (!formId && data.data?.form_id) {
          const newFormId = data.data.form_id;
          window.location.href = `${window.formturaBuilder.editUrl}&form_id=${newFormId}`;
        }
      } else {
        handleError(data.data?.message || 'Unknown error', {
          userMessage: `Error saving form: ${data.data?.message || 'Please try again'}`,
        });
      }
    } catch (error) {
      handleError(error, {
        userMessage: 'Error saving form. Please try again.',
      });
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <DndContext
      sensors={sensors}
      collisionDetection={closestCenter}
      onDragStart={handleDragStart}
      onDragEnd={handleDragEnd}
    >
      <div className={`formtura-builder ${isSidebarCollapsed ? 'sidebar-collapsed' : ''}`}>
        <a className="formtura-skip-link" href="#formtura-builder-canvas">
          Skip to form canvas
        </a>
        <header className="formtura-canvas-header">
          <div className="formtura-builder-heading">
            <a
              className="formtura-back-link"
              href={window.formturaBuilder?.formsUrl}
              aria-label="Back to all forms"
            >
              <ArrowLeft aria-hidden="true" />
            </a>
            <div>
              <p className="formtura-builder-eyebrow">Formtura workspace</p>
              <h1 className="formtura-canvas-title">
                {formSettings.title || 'Untitled form'}
              </h1>
            </div>
            <span className="formtura-save-status">
              <span aria-hidden="true" className="formtura-save-status-dot" />
              Editing
            </span>
          </div>
          <div className="formtura-canvas-actions">
            <Button
              variant="ghost"
              icon={Eye}
              onClick={() => setShowPreview(true)}
            >
              Preview
            </Button>
            <Button
              variant="secondary"
              icon={Code2}
              disabled
              title="Embed options are not available yet"
            >
              Embed
            </Button>
            <Button
              variant="primary"
              icon={Check}
              onClick={handleSaveForm}
              disabled={isSaving}
              aria-busy={isSaving}
            >
              {isSaving ? 'Saving…' : 'Save form'}
            </Button>
          </div>
        </header>

        <FieldLibrary
          selectedField={selectedField}
          fields={fields}
          onFieldUpdate={handleFieldUpdate}
          isCollapsed={isSidebarCollapsed}
          onToggleCollapse={() => setIsSidebarCollapsed(!isSidebarCollapsed)}
          onFieldAdd={handleFieldAdd}
        />

        <FormCanvas
          fields={fields}
          selectedField={selectedField}
          onFieldSelect={setSelectedField}
          onFieldDelete={handleFieldDelete}
          onFieldDuplicate={handleFieldDuplicate}
          formSettings={formSettings}
        />

        <DragOverlay>
          {activeId ? (
            <div className="formtura-drag-overlay">
              {activeId.startsWith('library-')
                ? getDefaultLabel(activeId.replace('library-', ''))
                : fields.find(f => f.id === activeId)?.label
              }
            </div>
          ) : null}
        </DragOverlay>

        {showPreview && (
          <FormPreview
            fields={fields}
            formSettings={formSettings}
            onClose={() => setShowPreview(false)}
          />
        )}
      </div>
    </DndContext>
  );
};

export default FormBuilder;
