/**
 * Coupon apply flow tests for assets/js/frontend.js.
 *
 * Codes live server-side only; Apply round-trips through AJAX and the
 * validated discount adjusts the displayed total.
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
		strings: {
			couponApplied: 'Coupon applied.',
			couponInvalid: 'This coupon code is not valid.',
		},
	};

	// eslint-disable-next-line no-eval
	window.eval(FRONTEND_JS);

	return new Promise(resolve => jQuery(document).ready(resolve));
}

function renderForm() {
	document.body.innerHTML = `
		<form class="fta-form" data-form-id="7">
			<div class="fta-field fta-field-payment fta-field-payment-single">
				<input type="hidden" name="field_fee" value="1" class="fta-payment-input" data-price="20.00" data-item-label="Fee">
			</div>
			<div class="fta-field fta-field-coupon">
				<div class="fta-coupon" data-field-id="field_coupon">
					<input type="text" name="field_coupon" class="fta-field-input fta-coupon-input">
					<button type="button" class="fta-coupon-apply">Apply</button>
				</div>
				<span class="fta-coupon-status" role="status"></span>
			</div>
			<div class="fta-field fta-field-total">
				<div class="fta-total-display"><span class="fta-total-amount">$0.00</span></div>
				<input type="hidden" name="field_total" class="fta-total-input" value="0">
			</div>
		</form>
	`;
}

describe('coupon apply flow', () => {
	let ajaxMock;

	beforeEach(() => {
		jQuery(document).off();
		document.body.innerHTML = '';
		ajaxMock = jest.fn();
		jQuery.ajax = ajaxMock;
		window.HTMLElement.prototype.scrollIntoView = jest.fn();
	});

	test('a valid code discounts the displayed total', async () => {
		renderForm();
		await loadFrontend();

		jQuery('.fta-coupon-input').val('SAVE5');
		jQuery('.fta-coupon-apply').trigger('click');

		expect(ajaxMock).toHaveBeenCalledTimes(1);
		const request = ajaxMock.mock.calls[0][0];
		expect(request.data.action).toBe('fta_validate_coupon');
		expect(request.data.code).toBe('SAVE5');
		expect(request.data.field_id).toBe('field_coupon');

		request.success({ success: true, data: { code: 'SAVE5', type: 'fixed', value: 5 } });

		expect(document.querySelector('.fta-total-amount').textContent).toBe('$15.00');
		expect(document.querySelector('.fta-coupon-status').textContent).toContain('Coupon applied.');
	});

	test('an invalid code shows the error and leaves the total alone', async () => {
		renderForm();
		await loadFrontend();

		jQuery('.fta-coupon-input').val('NOPE');
		jQuery('.fta-coupon-apply').trigger('click');

		ajaxMock.mock.calls[0][0].success({ success: false, data: { message: 'This coupon code is not valid.' } });

		expect(document.querySelector('.fta-total-amount').textContent).toBe('$20.00');
		expect(document.querySelector('.fta-coupon-status').textContent).toContain('not valid');
	});

	test('an empty code does not fire a request', async () => {
		renderForm();
		await loadFrontend();

		jQuery('.fta-coupon-apply').trigger('click');

		expect(ajaxMock).not.toHaveBeenCalled();
	});
});
