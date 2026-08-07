/**
 * Payment total display tests for assets/js/frontend.js.
 *
 * The displayed total is convenience only - the server recomputes - but it
 * must track selections correctly or visitors are misled about what they
 * are agreeing to.
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

function loadFrontend() {
	window.formturaFrontend = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'test-nonce',
		recaptcha: null,
		currency: { symbol: '$' },
		strings: {},
	};

	// eslint-disable-next-line no-eval
	window.eval(FRONTEND_JS);

	return new Promise(resolve => jQuery(document).ready(resolve));
}

function renderPaymentForm() {
	document.body.innerHTML = `
		<form class="fta-form" data-form-id="7">
			<div class="fta-field fta-field-payment fta-field-payment-single">
				<input type="hidden" name="field_single" value="1" class="fta-payment-input" data-price="5.00" data-item-label="Base fee">
			</div>
			<div class="fta-field fta-field-payment fta-field-payment-checkbox">
				<input type="checkbox" name="field_extras[]" value="gift" class="fta-choice-input fta-payment-input" data-price="10.00" data-item-label="Gift wrap">
				<input type="checkbox" name="field_extras[]" value="rush" class="fta-choice-input fta-payment-input" data-price="20.00" data-item-label="Rush order">
			</div>
			<div class="fta-field fta-field-payment fta-field-payment-dropdown">
				<select name="field_size" class="fta-field-select fta-payment-select">
					<option value="" data-price="0">Select an item</option>
					<option value="small" data-price="10.00" data-item-label="Small">Small - $10.00</option>
					<option value="large" data-price="25.00" data-item-label="Large">Large - $25.00</option>
				</select>
			</div>
			<div class="fta-field fta-field-total">
				<table class="fta-order-summary"><tbody class="fta-order-summary-body"></tbody></table>
				<div class="fta-total-display">
					<span class="fta-total-amount">$0.00</span>
				</div>
			</div>
			<button type="submit" class="fta-submit-button">Submit</button>
		</form>
	`;
}

describe('payment totals display', () => {
	beforeEach(() => {
		jQuery(document).off();
		document.body.innerHTML = '';
		jQuery.ajax = jest.fn();
		window.HTMLElement.prototype.scrollIntoView = jest.fn();
	});

	test('initial total includes always-on single items', async () => {
		renderPaymentForm();
		await loadFrontend();

		expect(document.querySelector('.fta-total-amount').textContent).toBe('$5.00');
	});

	test('checking items adds their prices', async () => {
		renderPaymentForm();
		await loadFrontend();

		jQuery('input[value="gift"]').prop('checked', true).trigger('change');
		jQuery('input[value="rush"]').prop('checked', true).trigger('change');

		expect(document.querySelector('.fta-total-amount').textContent).toBe('$35.00');
	});

	test('selecting a dropdown item adds its price and switching replaces it', async () => {
		renderPaymentForm();
		await loadFrontend();

		jQuery('.fta-payment-select').val('small').trigger('change');
		expect(document.querySelector('.fta-total-amount').textContent).toBe('$15.00');

		jQuery('.fta-payment-select').val('large').trigger('change');
		expect(document.querySelector('.fta-total-amount').textContent).toBe('$30.00');
	});

	test('unchecking removes the price', async () => {
		renderPaymentForm();
		await loadFrontend();

		jQuery('input[value="gift"]').prop('checked', true).trigger('change');
		jQuery('input[value="gift"]').prop('checked', false).trigger('change');

		expect(document.querySelector('.fta-total-amount').textContent).toBe('$5.00');
	});

	test('the summary table lists selected items', async () => {
		renderPaymentForm();
		await loadFrontend();

		jQuery('input[value="gift"]').prop('checked', true).trigger('change');

		const rows = document.querySelectorAll('.fta-order-summary-body tr');
		const text = document.querySelector('.fta-order-summary-body').textContent;

		expect(rows.length).toBe(2);
		expect(text).toContain('Base fee');
		expect(text).toContain('Gift wrap');
	});
});
