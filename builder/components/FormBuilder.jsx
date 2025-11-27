import { closestCenter, DndContext, DragOverlay, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import { arrayMove } from '@dnd-kit/sortable';
import { useEffect, useState } from 'react';
import { handleError, handleSuccess } from '../utils/errorHandler';
import { generateFieldId } from '../utils/helpers';
import FieldLibrary from './FieldLibrary';
import FormCanvas from './FormCanvas';
import FormPreview from './FormPreview';
import { announce } from './LiveRegion';

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
    })
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
        setFormSettings(formData.settings || formSettings);
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

  const createField = (type) => {
    const baseField = {
      id: generateFieldId(),
      type,
      label: getDefaultLabel(type),
      placeholder: '',
      required: false,
      description: '',
    };

    // Add type-specific properties
    switch (type) {
      case 'text':
      case 'email':
      case 'number':
        return { ...baseField };
      case 'textarea':
        return { ...baseField, rows: 4 };
      case 'select':
      case 'radio':
      case 'checkbox':
        return { ...baseField, options: ['Option 1', 'Option 2', 'Option 3'] };
      case 'name':
        return {
          ...baseField,
          label: 'Name',
          format: 'first-last',
          firstNamePlaceholder: '',
          lastNamePlaceholder: '',
          middleNamePlaceholder: '',
          firstNameDefault: '',
          lastNameDefault: '',
          middleNameDefault: '',
          hideSublabels: false,
        };
      case 'number-slider':
        return {
          ...baseField,
          minValue: 0,
          maxValue: 10,
          defaultValue: 0,
          increment: 1,
          valueDisplay: 'Selected Value: {value}',
        };
      case 'repeater':
        return {
          ...baseField,
          label: 'Repeater',
          collapsible: false,
          repeatLayout: 'default',
          addNewLabel: 'Add',
          removeLabel: 'Remove',
          minRows: '',
          maxRows: '',
          children: [],
        };
      case 'rating':
        return {
          ...baseField,
          label: 'Star Rating',
          maxRating: 5,
          unique: false,
        };
      case 'datetime':
        return {
          ...baseField,
          label: 'Date',
          yearRangeStart: '-10',
          yearRangeEnd: '+10',
        };
      case 'rich-text':
        return {
          ...baseField,
          label: 'Rich Text',
          content: '',
          fieldSize: 'px',
          rows: 7,
        };
      case 'html':
        return {
          ...baseField,
          label: 'HTML',
          content: '',
        };
      case 'file-upload':
        return {
          ...baseField,
          label: 'File Upload',
          allowMultiple: false,
          attachToEmail: false,
          deleteOnReplace: false,
          autoResize: false,
          allowedFileTypes: 'specify',
          specifiedTypes: 'jpg, jpeg, jpe, png, gif',
          minFileSize: '',
          maxFileSize: '',
          uploadText: 'Drop a file here or click to upload',
          compactUploadText: 'Choose File',
        };
      default:
        return baseField;
    }
  };

  const getDefaultLabel = (type) => {
    const labels = {
      text: 'Text Field',
      email: 'Email Address',
      textarea: 'Message',
      number: 'Number',
      select: 'Dropdown',
      radio: 'Radio Buttons',
      checkbox: 'Checkboxes',
      name: 'Name',
      phone: 'Phone Number',
      date: 'Date',
      'number-slider': 'Number Slider',
      'repeater': 'Repeater',
      'rating': 'Star Rating',
      'datetime': 'Date',
      'rich-text': 'Rich Text',
      'html': 'HTML',
      'file-upload': 'File Upload',
    };
    return labels[type] || 'Field';
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
        <header className="formtura-canvas-header">
          <h1 className="formtura-canvas-title">Form Builder</h1>
          <div className="formtura-canvas-actions">
            <button
              className="formtura-btn formtura-btn-secondary"
              type="button"
              onClick={() => setShowPreview(true)}
            >
              Preview
            </button>
            <button className="formtura-btn formtura-btn-secondary" type="button">
              Embed
            </button>
            <button
              className="formtura-btn formtura-btn-primary"
              type="button"
              onClick={handleSaveForm}
              disabled={isSaving}
            >
              {isSaving ? 'Saving...' : '✔ Save'}
            </button>
          </div>
        </header>

        <FieldLibrary
          selectedField={selectedField}
          fields={fields}
          onFieldUpdate={handleFieldUpdate}
          isCollapsed={isSidebarCollapsed}
          onToggleCollapse={() => setIsSidebarCollapsed(!isSidebarCollapsed)}
        />

        <FormCanvas
          fields={fields}
          selectedField={selectedField}
          onFieldSelect={setSelectedField}
          onFieldDelete={handleFieldDelete}
          onFieldDuplicate={handleFieldDuplicate}
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
