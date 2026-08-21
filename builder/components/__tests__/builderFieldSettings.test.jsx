import { render, fireEvent, screen } from '@testing-library/react';
import { DndContext } from '@dnd-kit/core';
import FieldLibrary from '../FieldLibrary';
import { createField, getDefaultLabel } from '../../utils/fieldDefaults';
import fixture from '../../../tests/fixtures/builder-field-settings.json';

/**
 * The builder half of the save-path guard.
 *
 * tests/Unit/Admin/FormBuilderSanitizeTest.php proves the PHP allowlist keeps
 * every setting in tests/fixtures/builder-field-settings.json. That guard is
 * only worth something if the fixture describes fields the builder can really
 * produce - the version it replaced fed sanitize_field_data() a `content` key
 * for the content field that no builder control ever wrote, so it passed while
 * the field rendered nothing on the frontend.
 *
 * This test closes that gap from the JS side: for each type in the same
 * fixture it collects every key the builder can write - createField()'s
 * defaults plus every key produced by exercising every control in that field's
 * options panel - and asserts the fixture's keys are among them. A setting
 * whose editor is missing, or bound to the wrong key, fails here.
 *
 * The split is deliberate: PHP cannot execute the React options panel, and
 * Jest cannot run the sanitizer, so each side asserts its own half against one
 * shared list of settings.
 */

// Keys written by controls this sweep cannot drive, so they can only be
// credited to createField()'s defaults: none currently. rich-text's
// `content` used to come from WysiwygEditor, a contentEditable div rather
// than a form control - it is now a plain textarea (AUDIT_FINDINGS.md #8)
// and IS swept, same as the content field's editor.

/**
 * Fire a plausible change at every form control in the container and return
 * the keys the panel asked to update.
 *
 * @param {HTMLElement} container Rendered options panel.
 * @param {jest.Mock}   onFieldUpdate Mock passed to FieldLibrary.
 * @returns {Set<string>} Keys the panel wrote.
 */
const sweepControls = (container, onFieldUpdate) => {
  container.querySelectorAll('input, select, textarea').forEach((control) => {
    if (control.type === 'checkbox' || control.type === 'radio') {
      fireEvent.click(control);
      return;
    }

    if ('SELECT' === control.tagName) {
      const next = Array.from(control.options)
        .map((option) => option.value)
        .find((value) => value !== control.value);

      if (undefined !== next) {
        fireEvent.change(control, { target: { value: next } });
      }

      return;
    }

    // Numeric-looking so number inputs parse it, non-empty so text inputs
    // register a real change.
    fireEvent.change(control, { target: { value: '7' } });
  });

  const keys = new Set();

  onFieldUpdate.mock.calls.forEach(([, updates]) => {
    Object.keys(updates || {}).forEach((key) => keys.add(key));
  });

  return keys;
};

/**
 * Every key the builder can put on a field of this type.
 *
 * @param {string} type Field type.
 * @returns {Set<string>} Keys from createField() plus the options panel.
 */
const writableKeys = (type) => {
  const field = createField(type);
  const keys = new Set(Object.keys(field));
  const onFieldUpdate = jest.fn();

  const { container, unmount } = render(
    <DndContext>
      <FieldLibrary
        selectedField={field.id}
        fields={[field]}
        onFieldUpdate={onFieldUpdate}
        isCollapsed={false}
        onToggleCollapse={() => {}}
        onFieldAdd={() => {}}
      />
    </DndContext>
  );

  // General sub-tab is the default; Advanced carries the rest of the panel.
  sweepControls(container, onFieldUpdate).forEach((key) => keys.add(key));

  fireEvent.click(screen.getByRole('button', { name: 'Advanced' }));
  sweepControls(container, onFieldUpdate).forEach((key) => keys.add(key));

  unmount();

  return keys;
};

describe('builder settings the save path is guarded against', () => {
  it('covers every field type the PHP guard asserts on', () => {
    // Neither side may quietly shrink to a subset of the fixture.
    expect(Object.keys(fixture.types).sort()).toEqual([
      'address',
      'camera',
      'content',
      'coupon',
      'payment-checkbox',
      'payment-dropdown',
      'payment-multiple',
      'payment-single',
      'rich-text',
      'section-divider',
      'signature',
      'total',
    ]);
  });

  // A type with no getDefaultLabel() entry starts life labelled "Field", and
  // section-divider renders its label as the section heading - so the frontend
  // showed "<h3>Field</h3>" for a freshly placed divider.
  it.each(Object.keys(fixture.types))('gives %s a default label of its own', (type) => {
    expect(getDefaultLabel(type)).not.toBe('Field');
  });

  describe.each(Object.keys(fixture.types))('%s', (type) => {
    it('produces every setting the fixture claims for it', () => {
      const produced = writableKeys(type);

      // A non-empty list here means the fixture - and therefore the PHP guard
      // reading it - describes a setting this field type cannot actually
      // carry: no createField() default and no control in its options panel
      // writes that key. Either wire the editor up or drop the key.
      const unreachable = Object.keys(fixture.types[type]).filter((key) => !produced.has(key));

      expect(unreachable).toEqual([]);
    });
  });

  it('writes the content field body under the key its template reads', () => {
    // Direct regression cover for the defect the fixture guard exists for:
    // templates/fields/content.php reads `content` and ignores `description`.
    const field = createField('content');
    const onFieldUpdate = jest.fn();

    const { container } = render(
      <DndContext>
        <FieldLibrary
          selectedField={field.id}
          fields={[field]}
          onFieldUpdate={onFieldUpdate}
          isCollapsed={false}
          onToggleCollapse={() => {}}
          onFieldAdd={() => {}}
        />
      </DndContext>
    );

    fireEvent.change(container.querySelector('#field-content'), {
      target: { value: 'Read me before submitting.' },
    });

    expect(onFieldUpdate).toHaveBeenCalledWith(field.id, {
      content: 'Read me before submitting.',
    });
  });

  /**
   * AUDIT_FINDINGS.md #8: the rich-text field's editor offered a full
   * bold/link/list WYSIWYG toolbar, but templates/fields/rich-text.php
   * renders the saved content through wp_strip_all_tags() into a plain
   * <textarea> - none of that formatting ever survives to the frontend.
   * The editor must match what the field can actually deliver: plain text,
   * like the content field's editor.
   */
  it('rich-text uses a plain textarea, not a formatting toolbar', () => {
    const field = createField('rich-text');
    const onFieldUpdate = jest.fn();

    const { container } = render(
      <DndContext>
        <FieldLibrary
          selectedField={field.id}
          fields={[field]}
          onFieldUpdate={onFieldUpdate}
          isCollapsed={false}
          onToggleCollapse={() => {}}
          onFieldAdd={() => {}}
        />
      </DndContext>
    );

    expect(screen.queryByTitle('Bold')).not.toBeInTheDocument();
    expect(container.querySelector('.formtura-wysiwyg-editor')).not.toBeInTheDocument();

    const contentField = container.querySelector('#field-content');
    expect(contentField.tagName).toBe('TEXTAREA');

    fireEvent.change(contentField, { target: { value: 'Plain draft copy.' } });

    expect(onFieldUpdate).toHaveBeenCalledWith(field.id, {
      content: 'Plain draft copy.',
    });
  });
});
