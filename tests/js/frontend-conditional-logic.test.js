/**
 * Conditional logic (show/hide) tests for assets/js/frontend.js.
 *
 * Fields carry their conditionalLogic as a JSON `data-conditional-logic`
 * attribute (see fta_get_field_wrapper_data() in src/Functions.php), which
 * jQuery's .data() deserializes automatically. Three bugs are covered here:
 * live re-evaluation never ran (bound listeners via a `logic.triggers` key
 * that never existed in the saved data), match:"any" was unimplemented
 * (only .every() ran), and a checkbox/radio trigger's value was read with a
 * plain .val(), which only ever returns one option regardless of how many
 * are checked.
 */

const fs = require('fs');
const path = require('path');

const FRONTEND_JS = fs.readFileSync(
	path.join(__dirname, '..', '..', 'assets', 'js', 'frontend.js'),
	'utf8'
);

const jQuery = require('jquery');
window.jQuery = jQuery;
window.$ = jQuery;

// Animations complete synchronously, so a field's shown/hidden state can be
// asserted right after the triggering event instead of waiting on a timer.
jQuery.fx.off = true;

function loadFrontend() {
	window.formturaFrontend = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'test-nonce',
		recaptcha: null,
		strings: {},
	};

	// eslint-disable-next-line no-eval
	window.eval(FRONTEND_JS);

	return new Promise(resolve => jQuery(document).ready(resolve));
}

function isHidden($field) {
	return $field.css('display') === 'none';
}

describe('conditional logic', () => {
	beforeEach(() => {
		jest.resetModules();
	});

	it('hides a field on load when its condition is not met', async () => {
		document.body.innerHTML = `
			<form class="fta-form" data-form-id="7">
				<div class="fta-field" data-field-id="field_1">
					<input type="text" name="field_1" class="fta-field-input">
				</div>
				<div class="fta-field" data-field-id="field_2"
					data-conditional-logic='${JSON.stringify({ enabled: true, action: 'show', match: 'all', conditions: [ { field: 'field_1', operator: 'is', value: 'yes' } ] })}'>
					<input type="text" name="field_2" class="fta-field-input">
				</div>
			</form>
		`;
		await loadFrontend();

		expect(isHidden(jQuery('[data-field-id="field_2"]'))).toBe(true);
	});

	it('shows the field as soon as the trigger field is changed to a matching value', async () => {
		document.body.innerHTML = `
			<form class="fta-form" data-form-id="7">
				<div class="fta-field" data-field-id="field_1">
					<input type="text" name="field_1" class="fta-field-input">
				</div>
				<div class="fta-field" data-field-id="field_2"
					data-conditional-logic='${JSON.stringify({ enabled: true, action: 'show', match: 'all', conditions: [ { field: 'field_1', operator: 'is', value: 'yes' } ] })}'>
					<input type="text" name="field_2" class="fta-field-input">
				</div>
			</form>
		`;
		await loadFrontend();

		jQuery('[name="field_1"]').val('yes').trigger('change');

		expect(isHidden(jQuery('[data-field-id="field_2"]'))).toBe(false);
	});

	it('hides the field again once the trigger field no longer matches', async () => {
		document.body.innerHTML = `
			<form class="fta-form" data-form-id="7">
				<div class="fta-field" data-field-id="field_1">
					<input type="text" name="field_1" class="fta-field-input" value="yes">
				</div>
				<div class="fta-field" data-field-id="field_2"
					data-conditional-logic='${JSON.stringify({ enabled: true, action: 'show', match: 'all', conditions: [ { field: 'field_1', operator: 'is', value: 'yes' } ] })}'>
					<input type="text" name="field_2" class="fta-field-input">
				</div>
			</form>
		`;
		await loadFrontend();
		expect(isHidden(jQuery('[data-field-id="field_2"]'))).toBe(false);

		jQuery('[name="field_1"]').val('no').trigger('input');

		expect(isHidden(jQuery('[data-field-id="field_2"]'))).toBe(true);
	});

	it('shows the field when only one of two conditions matches and match is "any"', async () => {
		document.body.innerHTML = `
			<form class="fta-form" data-form-id="7">
				<div class="fta-field" data-field-id="field_1">
					<input type="text" name="field_1" class="fta-field-input">
				</div>
				<div class="fta-field" data-field-id="field_3">
					<input type="text" name="field_3" class="fta-field-input" value="yes">
				</div>
				<div class="fta-field" data-field-id="field_2"
					data-conditional-logic='${JSON.stringify({
						enabled: true,
						action: 'show',
						match: 'any',
						conditions: [
							{ field: 'field_1', operator: 'is', value: 'yes' },
							{ field: 'field_3', operator: 'is', value: 'yes' },
						],
					})}'>
					<input type="text" name="field_2" class="fta-field-input">
				</div>
			</form>
		`;
		await loadFrontend();

		expect(isHidden(jQuery('[data-field-id="field_2"]'))).toBe(false);
	});

	it('keeps the field hidden when match is "all" and only one of two conditions matches', async () => {
		document.body.innerHTML = `
			<form class="fta-form" data-form-id="7">
				<div class="fta-field" data-field-id="field_1">
					<input type="text" name="field_1" class="fta-field-input">
				</div>
				<div class="fta-field" data-field-id="field_3">
					<input type="text" name="field_3" class="fta-field-input" value="yes">
				</div>
				<div class="fta-field" data-field-id="field_2"
					data-conditional-logic='${JSON.stringify({
						enabled: true,
						action: 'show',
						match: 'all',
						conditions: [
							{ field: 'field_1', operator: 'is', value: 'yes' },
							{ field: 'field_3', operator: 'is', value: 'yes' },
						],
					})}'>
					<input type="text" name="field_2" class="fta-field-input">
				</div>
			</form>
		`;
		await loadFrontend();

		expect(isHidden(jQuery('[data-field-id="field_2"]'))).toBe(true);
	});

	it('treats a checkbox group trigger as the set of its checked values, not just one', async () => {
		document.body.innerHTML = `
			<form class="fta-form" data-form-id="7">
				<div class="fta-field" data-field-id="field_1">
					<input type="checkbox" name="field_1[]" value="red">
					<input type="checkbox" name="field_1[]" value="blue">
				</div>
				<div class="fta-field" data-field-id="field_2"
					data-conditional-logic='${JSON.stringify({ enabled: true, action: 'show', match: 'all', conditions: [ { field: 'field_1', operator: 'contains', value: 'blue' } ] })}'>
					<input type="text" name="field_2" class="fta-field-input">
				</div>
			</form>
		`;
		await loadFrontend();
		expect(isHidden(jQuery('[data-field-id="field_2"]'))).toBe(true);

		// Checking "red" first must not falsely satisfy a condition on "blue".
		jQuery('input[name="field_1[]"][value="red"]').prop('checked', true).trigger('change');
		expect(isHidden(jQuery('[data-field-id="field_2"]'))).toBe(true);

		jQuery('input[name="field_1[]"][value="blue"]').prop('checked', true).trigger('change');
		expect(isHidden(jQuery('[data-field-id="field_2"]'))).toBe(false);
	});

	it('does nothing for a field with no data-conditional-logic attribute', async () => {
		document.body.innerHTML = `
			<form class="fta-form" data-form-id="7">
				<div class="fta-field" data-field-id="field_1">
					<input type="text" name="field_1" class="fta-field-input">
				</div>
			</form>
		`;

		await loadFrontend();

		expect(isHidden(jQuery('[data-field-id="field_1"]'))).toBe(false);
	});
});
