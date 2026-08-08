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
			error: 'An error occurred. Please try again.',
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

	/**
	 * A transport/network failure (including a stale nonce on cached HTML,
	 * which check_ajax_referer() 403s) is not the same thing as a wrong
	 * code. Telling the visitor their code is invalid here would be false -
	 * it must show the generic error string instead.
	 */
	test('a transport failure shows the generic error, not the invalid-code message', async () => {
		renderForm();
		await loadFrontend();

		jQuery('.fta-coupon-input').val('SAVE5');
		jQuery('.fta-coupon-apply').trigger('click');

		ajaxMock.mock.calls[0][0].error();

		const status = document.querySelector('.fta-coupon-status').textContent;
		expect(status).toContain('An error occurred');
		expect(status).not.toContain('not valid');
	});

	/**
	 * A previously-applied discount must not survive a later rejection - the
	 * status and the displayed total must never disagree.
	 */
	test('a previously applied coupon is cleared when a later code is rejected', async () => {
		renderForm();
		await loadFrontend();

		jQuery('.fta-coupon-input').val('SAVE5');
		jQuery('.fta-coupon-apply').trigger('click');
		ajaxMock.mock.calls[0][0].success({ success: true, data: { code: 'SAVE5', type: 'fixed', value: 5 } });
		ajaxMock.mock.calls[0][0].complete();
		expect(document.querySelector('.fta-total-amount').textContent).toBe('$15.00');

		jQuery('.fta-coupon-input').val('NOPE');
		jQuery('.fta-coupon-apply').trigger('click');
		ajaxMock.mock.calls[1][0].success({ success: false, data: { message: 'This coupon code is not valid.' } });

		expect(document.querySelector('.fta-total-amount').textContent).toBe('$20.00');
		expect(document.querySelector('.fta-coupon-status').textContent).toContain('not valid');
	});

	/**
	 * The same guarantee, but the second attempt fails at the transport
	 * level rather than being answered "invalid" - the stale discount must
	 * still be cleared and the total must still revert.
	 */
	test('a previously applied coupon is cleared when a later attempt hits a transport error', async () => {
		renderForm();
		await loadFrontend();

		jQuery('.fta-coupon-input').val('SAVE5');
		jQuery('.fta-coupon-apply').trigger('click');
		ajaxMock.mock.calls[0][0].success({ success: true, data: { code: 'SAVE5', type: 'fixed', value: 5 } });
		ajaxMock.mock.calls[0][0].complete();
		expect(document.querySelector('.fta-total-amount').textContent).toBe('$15.00');

		jQuery('.fta-coupon-input').val('SAVE5');
		jQuery('.fta-coupon-apply').trigger('click');
		ajaxMock.mock.calls[1][0].error();

		expect(document.querySelector('.fta-total-amount').textContent).toBe('$20.00');
		expect(document.querySelector('.fta-coupon-status').textContent).toContain('An error occurred');
	});

	/**
	 * The Apply button must come back on every outcome - success, a
	 * rejected code, and a transport error alike - so a visitor is never
	 * left staring at a permanently disabled button.
	 */
	test('the Apply button is re-enabled after every outcome, including a transport error', async () => {
		renderForm();
		await loadFrontend();
		const $button = jQuery('.fta-coupon-apply');

		jQuery('.fta-coupon-input').val('SAVE5');
		$button.trigger('click');
		expect($button.prop('disabled')).toBe(true);
		const first = ajaxMock.mock.calls[0][0];
		first.success({ success: true, data: { code: 'SAVE5', type: 'fixed', value: 5 } });
		first.complete();
		expect($button.prop('disabled')).toBe(false);

		$button.trigger('click');
		const second = ajaxMock.mock.calls[1][0];
		second.success({ success: false, data: { message: 'This coupon code is not valid.' } });
		second.complete();
		expect($button.prop('disabled')).toBe(false);

		$button.trigger('click');
		const third = ajaxMock.mock.calls[2][0];
		third.error();
		third.complete();
		expect($button.prop('disabled')).toBe(false);
	});
});
