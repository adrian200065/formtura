import { render, screen, fireEvent } from '@testing-library/react';
import { DndContext } from '@dnd-kit/core';
import FieldLibrary from '../FieldLibrary';

// Renders FieldLibrary with a single field selected, which auto-switches the
// sidebar to the "options" tab / "general" sub-tab where GeneralTab (and its
// payment items editor) lives. `otherFields` are additional sibling fields on
// the same form, needed by conditional-logic tests that populate a
// trigger-field dropdown from the form's other fields.
const renderWithField = (field, onFieldUpdate = jest.fn(), otherFields = []) => {
  const utils = render(
    <DndContext>
      <FieldLibrary
        selectedField={field.id}
        fields={[field, ...otherFields]}
        onFieldUpdate={onFieldUpdate}
        isCollapsed={false}
        onToggleCollapse={() => {}}
        onFieldAdd={() => {}}
      />
    </DndContext>
  );
  return { ...utils, onFieldUpdate };
};

// Navigates to the Smart Logic sub-tab, where the Conditional Logic section
// lives.
const openSmartLogicTab = () => {
  fireEvent.click(screen.getByRole('button', { name: 'Smart Logic' }));
};

// Renders FieldLibrary with nothing selected, which is its default state:
// the "add" tab showing the field palette (DraggableField / ClickableField
// buttons), rather than the field options panel renderWithField switches to.
const renderPalette = (onFieldAdd = jest.fn()) => {
  const utils = render(
    <DndContext>
      <FieldLibrary
        selectedField={null}
        fields={[]}
        onFieldUpdate={() => {}}
        isCollapsed={false}
        onToggleCollapse={() => {}}
        onFieldAdd={onFieldAdd}
      />
    </DndContext>
  );
  return { ...utils, onFieldAdd };
};

describe('FieldLibrary payment gateway fields', () => {
  // Payment gateways are whole integrations Formtura does not implement, not
  // templates - each palette entry must open an info dialog rather than being
  // draggable onto a form. Regression coverage for the authorize-net field,
  // which was previously missing this treatment (unlike its three siblings)
  // and would silently render nothing on the frontend if placed on a form.
  describe.each([
    ['PayPal Commerce', 'PayPal'],
    ['Stripe Credit Card', 'Stripe'],
    ['Square', 'Square'],
    ['Authorize.Net', 'Authorize.Net'],
  ])('%s', (label, dialogName) => {
    it('is not draggable onto the form canvas', () => {
      renderPalette();

      expect(screen.queryByRole('button', { name: `Add ${label} field` })).not.toBeInTheDocument();
    });

    it('opens an info dialog instead of adding the field', () => {
      const { onFieldAdd } = renderPalette();

      fireEvent.click(screen.getByText(label));

      expect(onFieldAdd).not.toHaveBeenCalled();
      expect(screen.getByText(new RegExp(`${dialogName} account connection is required`))).toBeInTheDocument();
    });
  });
});

// hideSublabels is defaulted by createField(), sanitized by the save path and
// read by templates/fields/address.php - but its only toggle used to live
// inside the Advanced tab's `field.type === 'name'` branch, which an address
// field never reaches, leaving the setting unreachable for the field type that
// most needs it.
describe.each(['name', 'address'])('FieldLibrary %s field sublabels', (type) => {
  it('offers a reachable Hide Sublabels toggle', () => {
    const field = { id: 'field_1', type, label: 'Where do you live?' };
    const { onFieldUpdate } = renderWithField(field);

    fireEvent.click(screen.getByRole('button', { name: 'Advanced' }));
    fireEvent.click(
      screen
        .getByText(/Hide Sublabels/)
        .closest('.formtura-toggle-group')
        .querySelector('input[type="checkbox"]')
    );

    expect(onFieldUpdate).toHaveBeenCalledWith('field_1', { hideSublabels: true });
  });
});

// The total field renders no input on the frontend, so a Required flag on it
// could only ever block the form with an error the visitor cannot clear. The
// builder must not offer the toggle at all.
describe('FieldLibrary required toggle', () => {
  it('is offered for an ordinary field', () => {
    renderWithField({ id: 'field_1', type: 'text', label: 'Name' });

    expect(screen.getByText(/^Required/)).toBeInTheDocument();
  });

  it.each(['total', 'number-slider', 'repeater'])('is not offered for %s', (type) => {
    renderWithField({ id: 'field_1', type, label: 'Total' });

    expect(screen.queryByText(/^Required/)).not.toBeInTheDocument();
  });
});

// The other half of the same rule: four Advanced palette types have no
// frontend template at all (doc/CHECKLIST.md documents them as gaps), so an
// author could place one and ship a form that renders nothing where that field
// should be. They must be gated exactly like the gateway types above.
describe('FieldLibrary field types with no frontend template', () => {
  describe.each([
    ['Repeater', /cannot place a field inside another field yet/],
    ['Layout', /multi-page form subsystem/],
    ['Page Break', /needs multi-page forms/],
    ['Entry Preview', /multi-page form subsystem/],
  ])('%s', (label, reason) => {
    it('is not draggable onto the form canvas', () => {
      renderPalette();

      expect(screen.queryByRole('button', { name: `Add ${label} field` })).not.toBeInTheDocument();
    });

    it('opens an info dialog instead of adding the field', () => {
      const { onFieldAdd } = renderPalette();

      fireEvent.click(screen.getByText(label));

      expect(onFieldAdd).not.toHaveBeenCalled();
      expect(screen.getByText(`The ${label} field is not available yet.`)).toBeInTheDocument();
      expect(screen.getByText(reason)).toBeInTheDocument();
    });

    // The gateway dialogs point at a settings tab because connecting an
    // account there really does enable them. Nothing enables these four, so
    // the copy must not send an author looking for a switch.
    it('does not imply a setting would enable it', () => {
      renderPalette();

      fireEvent.click(screen.getByText(label));

      const dialog = screen.getByRole('dialog');

      expect(dialog.querySelector('a')).toBeNull();
      expect(dialog.textContent).not.toMatch(/Settings/);
    });
  });
});

describe('FieldLibrary payment items editor', () => {
  describe.each(['payment-checkbox', 'payment-multiple', 'payment-dropdown'])(
    '%s',
    (type) => {
      it('writes the plural showPriceAfterLabels key when the toggle is flipped', () => {
        const field = {
          id: 'field_1',
          type,
          label: 'Items',
          items: [
            { label: 'Small', value: 'small', price: '10.00', isDefault: false },
          ],
          showPriceAfterLabels: true,
        };

        const { onFieldUpdate } = renderWithField(field);

        fireEvent.click(screen.getByText(/Show Price After Item Labels/).closest('.formtura-toggle-group').querySelector('input[type="checkbox"]'));

        expect(onFieldUpdate).toHaveBeenCalledWith('field_1', { showPriceAfterLabels: false });
      });
    }
  );

  it('lets multiple payment-checkbox items be marked default independently', () => {
    const onFieldUpdate = jest.fn();
    const field = {
      id: 'field_1',
      type: 'payment-checkbox',
      label: 'Checkbox Items',
      items: [
        { label: 'Small', value: 'small', price: '10.00', isDefault: true },
        { label: 'Large', value: 'large', price: '25.00', isDefault: false },
      ],
    };

    const { container } = renderWithField(field, onFieldUpdate);

    const defaultMarkers = container.querySelectorAll('.formtura-item-row input.formtura-choice-radio');
    expect(defaultMarkers).toHaveLength(2);

    // The first item is already isDefault:true (via props); checking the
    // second item's marker must not clear the first one's default.
    fireEvent.click(defaultMarkers[1]);

    expect(onFieldUpdate).toHaveBeenCalledWith('field_1', {
      items: [
        { label: 'Small', value: 'small', price: '10.00', isDefault: true },
        { label: 'Large', value: 'large', price: '25.00', isDefault: true },
      ],
    });
  });

  it('uses an exclusive radio (not an independent checkbox) for the default marker on single-select payment types', () => {
    const field = {
      id: 'field_1',
      type: 'payment-multiple',
      label: 'Multiple Items',
      items: [
        { label: 'Small', value: 'small', price: '10.00', isDefault: false },
        { label: 'Large', value: 'large', price: '25.00', isDefault: false },
      ],
    };

    const { container } = renderWithField(field);
    const defaultMarkers = container.querySelectorAll('.formtura-item-row input.formtura-choice-radio');

    expect(defaultMarkers).toHaveLength(2);
    defaultMarkers.forEach((marker) => expect(marker).toHaveAttribute('type', 'radio'));
  });
});

describe('FieldLibrary conditional logic', () => {
  // "+ Add Condition" previously had no click handler at all, so there was
  // no way to actually create a condition through the builder UI.
  it('adds an empty condition row when "+ Add Condition" is clicked', () => {
    const field = {
      id: 'field_2',
      type: 'text',
      label: 'Comments',
      conditionalLogic: { enabled: true, action: 'show', match: 'all', conditions: [] },
    };
    const trigger = { id: 'field_1', type: 'text', label: 'Name' };

    const { onFieldUpdate } = renderWithField(field, jest.fn(), [trigger]);
    openSmartLogicTab();

    fireEvent.click(screen.getByRole('button', { name: '+ Add Condition' }));

    expect(onFieldUpdate).toHaveBeenCalledWith('field_2', {
      conditionalLogic: {
        enabled: true,
        action: 'show',
        match: 'all',
        conditions: [ { field: '', operator: 'is', value: '' } ],
      },
    });
  });

  it('lists the form\'s other fields, but not the field being edited, in the trigger dropdown', () => {
    const field = {
      id: 'field_2',
      type: 'text',
      label: 'Comments',
      conditionalLogic: {
        enabled: true,
        action: 'show',
        match: 'all',
        conditions: [ { field: '', operator: 'is', value: '' } ],
      },
    };
    const others = [
      { id: 'field_1', type: 'text', label: 'Name' },
      { id: 'field_3', type: 'select', label: 'Country' },
    ];

    renderWithField(field, jest.fn(), others);
    openSmartLogicTab();

    const triggerSelect = screen.getByLabelText('Condition field');
    const optionLabels = Array.from(triggerSelect.options).map((o) => o.textContent);

    expect(optionLabels).toEqual(expect.arrayContaining([ 'Name', 'Country' ]));
    expect(optionLabels).not.toContain('Comments');
  });

  it('updates the condition\'s trigger field when changed', () => {
    const field = {
      id: 'field_2',
      type: 'text',
      label: 'Comments',
      conditionalLogic: {
        enabled: true,
        action: 'show',
        match: 'all',
        conditions: [ { field: '', operator: 'is', value: '' } ],
      },
    };
    const trigger = { id: 'field_1', type: 'text', label: 'Name' };

    const { onFieldUpdate } = renderWithField(field, jest.fn(), [trigger]);
    openSmartLogicTab();

    fireEvent.change(screen.getByLabelText('Condition field'), { target: { value: 'field_1' } });

    expect(onFieldUpdate).toHaveBeenCalledWith('field_2', {
      conditionalLogic: {
        enabled: true,
        action: 'show',
        match: 'all',
        conditions: [ { field: 'field_1', operator: 'is', value: '' } ],
      },
    });
  });

  it('updates the condition\'s operator when changed', () => {
    const field = {
      id: 'field_2',
      type: 'text',
      label: 'Comments',
      conditionalLogic: {
        enabled: true,
        action: 'show',
        match: 'all',
        conditions: [ { field: 'field_1', operator: 'is', value: '' } ],
      },
    };
    const trigger = { id: 'field_1', type: 'text', label: 'Name' };

    const { onFieldUpdate } = renderWithField(field, jest.fn(), [trigger]);
    openSmartLogicTab();

    fireEvent.change(screen.getByLabelText('Condition operator'), { target: { value: 'contains' } });

    expect(onFieldUpdate).toHaveBeenCalledWith('field_2', {
      conditionalLogic: {
        enabled: true,
        action: 'show',
        match: 'all',
        conditions: [ { field: 'field_1', operator: 'contains', value: '' } ],
      },
    });
  });

  it('updates the condition\'s value when changed', () => {
    const field = {
      id: 'field_2',
      type: 'text',
      label: 'Comments',
      conditionalLogic: {
        enabled: true,
        action: 'show',
        match: 'all',
        conditions: [ { field: 'field_1', operator: 'is', value: '' } ],
      },
    };
    const trigger = { id: 'field_1', type: 'text', label: 'Name' };

    const { onFieldUpdate } = renderWithField(field, jest.fn(), [trigger]);
    openSmartLogicTab();

    fireEvent.change(screen.getByLabelText('Condition value'), { target: { value: 'Ada' } });

    expect(onFieldUpdate).toHaveBeenCalledWith('field_2', {
      conditionalLogic: {
        enabled: true,
        action: 'show',
        match: 'all',
        conditions: [ { field: 'field_1', operator: 'is', value: 'Ada' } ],
      },
    });
  });

  it('removes a condition row when its remove button is clicked, leaving the others', () => {
    const field = {
      id: 'field_2',
      type: 'text',
      label: 'Comments',
      conditionalLogic: {
        enabled: true,
        action: 'show',
        match: 'all',
        conditions: [
          { field: 'field_1', operator: 'is', value: 'Ada' },
          { field: 'field_1', operator: 'is_not', value: 'Bob' },
        ],
      },
    };
    const trigger = { id: 'field_1', type: 'text', label: 'Name' };

    const { onFieldUpdate } = renderWithField(field, jest.fn(), [trigger]);
    openSmartLogicTab();

    fireEvent.click(screen.getAllByRole('button', { name: 'Remove condition' })[0]);

    expect(onFieldUpdate).toHaveBeenCalledWith('field_2', {
      conditionalLogic: {
        enabled: true,
        action: 'show',
        match: 'all',
        conditions: [ { field: 'field_1', operator: 'is_not', value: 'Bob' } ],
      },
    });
  });
});
