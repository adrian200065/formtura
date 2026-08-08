/**
 * Signature pad tests for assets/js/frontend.js.
 *
 * jsdom has no canvas implementation, so 2D context and toDataURL are
 * mocked; the tests cover the wiring, not the pixels: strokes populate the
 * hidden input, Clear empties it, a required empty pad blocks submit, a
 * successful submission wipes the pad the way it wipes the reCAPTCHA
 * widget, and an interrupted stroke can't leave the pad stuck drawing.
 */

const fs = require('fs');
const path = require('path');

const FRONTEND_JS = fs.readFileSync(
	path.join(__dirname, '..', '..', 'assets', 'js', 'frontend.js'),
	'utf8'
);

// One jQuery for the whole file, matching frontend-recaptcha.test.js: the
// document is shared between tests, so a second instance would leave
// handlers bound on it that off() could not reach.
const jQuery = require('jquery');
window.jQuery = jQuery;
window.$ = jQuery;

const FAKE_DATA_URL = 'data:image/png;base64,AAAA';

/**
 * Load frontend.js into the current jsdom document.
 *
 * frontend.js binds its handlers, including initSignaturePads(), on
 * document ready. Queueing after it means this resolves once those are in
 * place, so tests rely on that automatic init rather than calling
 * window.formturaInitSignaturePads() themselves - that hook is for markup
 * inserted after page load, and is exercised directly only by the
 * double-init test below.
 */
function loadFrontend() {
	window.formturaFrontend = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'test-nonce',
		recaptcha: null,
		strings: {
			signatureMissing: 'Please add your signature.',
		},
	};

	// eslint-disable-next-line no-eval
	window.eval(FRONTEND_JS);

	return new Promise(resolve => jQuery(document).ready(resolve));
}

function renderForm({ required = false } = {}) {
	document.body.innerHTML = `
		<form class="fta-form" data-form-id="7">
			<div class="fta-field fta-field-signature">
				<div class="fta-signature" data-fta-signature>
					<canvas class="fta-signature-canvas" width="600" height="180"></canvas>
					<input type="hidden" name="field_sig" class="fta-signature-value" ${required ? 'data-required="1"' : ''}>
					<button type="button" class="fta-signature-clear">Clear</button>
				</div>
			</div>
			<button type="submit" class="fta-submit-button">Submit</button>
		</form>
	`;
}

function drawStroke(canvas) {
	const opts = { bubbles: true, clientX: 10, clientY: 10 };
	canvas.dispatchEvent(new window.PointerEvent('pointerdown', opts));
	canvas.dispatchEvent(new window.PointerEvent('pointermove', { ...opts, clientX: 40, clientY: 30 }));
	canvas.dispatchEvent(new window.PointerEvent('pointerup', opts));
}

/**
 * The mocked 2D context returned by the pad's one getContext('2d') call.
 * Grabbing it lets tests assert on drawing calls (clearRect, lineTo) that
 * canvas pixels themselves can't prove anything about under jsdom.
 */
function currentContext() {
	return window.HTMLCanvasElement.prototype.getContext.mock.results[0].value;
}

describe('signature pad', () => {
	let ajaxMock;

	beforeEach(() => {
		jQuery(document).off();
		document.body.innerHTML = '';

		ajaxMock = jest.fn();
		jQuery.ajax = ajaxMock;

		window.HTMLElement.prototype.scrollIntoView = jest.fn();
		window.HTMLCanvasElement.prototype.getContext = jest.fn(() => ({
			beginPath: jest.fn(),
			moveTo: jest.fn(),
			lineTo: jest.fn(),
			stroke: jest.fn(),
			clearRect: jest.fn(),
		}));
		window.HTMLCanvasElement.prototype.toDataURL = jest.fn(() => FAKE_DATA_URL);
		window.HTMLCanvasElement.prototype.setPointerCapture = jest.fn();
		if (typeof window.PointerEvent === 'undefined') {
			window.PointerEvent = window.MouseEvent;
		}
	});

	test('a stroke serializes the canvas into the hidden input', async () => {
		renderForm();
		await loadFrontend();

		drawStroke(document.querySelector('.fta-signature-canvas'));

		expect(document.querySelector('.fta-signature-value').value).toBe(FAKE_DATA_URL);
	});

	test('clear empties the hidden input', async () => {
		renderForm();
		await loadFrontend();

		drawStroke(document.querySelector('.fta-signature-canvas'));
		jQuery('.fta-signature-clear').trigger('click');

		expect(document.querySelector('.fta-signature-value').value).toBe('');
	});

	test('a required empty pad blocks submission with a message', async () => {
		renderForm({ required: true });
		await loadFrontend();

		jQuery('.fta-form').trigger('submit');
		await Promise.resolve();
		await Promise.resolve();
		await Promise.resolve();

		expect(ajaxMock).not.toHaveBeenCalled();
		expect(document.querySelector('.fta-field-error').textContent)
			.toContain('Please add your signature.');
		// The visitor has to be able to try again.
		expect(document.querySelector('.fta-submit-button').disabled).toBe(false);
	});

	test('a signed required pad submits', async () => {
		renderForm({ required: true });
		await loadFrontend();

		drawStroke(document.querySelector('.fta-signature-canvas'));

		jQuery('.fta-form').trigger('submit');
		await Promise.resolve();
		await Promise.resolve();
		await Promise.resolve();

		expect(ajaxMock).toHaveBeenCalledTimes(1);
	});

	test('a second explicit init call does not double-wire an already-ready pad', async () => {
		renderForm();
		await loadFrontend(); // init() already wired the pad automatically on ready.

		// window.formturaInitSignaturePads() exists for markup inserted after
		// page load; calling it again for a pad that's already ready must be
		// a no-op, or every stroke would fire duplicated listeners.
		window.formturaInitSignaturePads();

		expect(window.HTMLCanvasElement.prototype.getContext).toHaveBeenCalledTimes(1);

		drawStroke(document.querySelector('.fta-signature-canvas'));
		expect(document.querySelector('.fta-signature-value').value).toBe(FAKE_DATA_URL);
	});

	test('a successful submission clears the canvas and the hidden input together', async () => {
		renderForm();
		await loadFrontend();

		drawStroke(document.querySelector('.fta-signature-canvas'));
		expect(document.querySelector('.fta-signature-value').value).toBe(FAKE_DATA_URL);

		jQuery('.fta-form').trigger('submit');
		await Promise.resolve();
		await Promise.resolve();
		await Promise.resolve();

		const ctx = currentContext();

		// form.reset() (called by the success handler) clears the hidden
		// input on its own, since the template sets no value attribute; the
		// bug this proves is fixed is the canvas pixels being left behind.
		ajaxMock.mock.calls[0][0].success({ success: true, data: { message: 'Thanks' } });

		expect(ctx.clearRect).toHaveBeenCalled();
		expect(document.querySelector('.fta-signature-value').value).toBe('');
	});

	test('a cancelled stroke stops drawing and serializes what was drawn so far', async () => {
		renderForm();
		await loadFrontend();

		const canvas = document.querySelector('.fta-signature-canvas');
		const ctx = currentContext();

		canvas.dispatchEvent(new window.PointerEvent('pointerdown', { bubbles: true, clientX: 10, clientY: 10 }));
		canvas.dispatchEvent(new window.PointerEvent('pointermove', { bubbles: true, clientX: 20, clientY: 20 }));
		canvas.dispatchEvent(new window.PointerEvent('pointercancel', { bubbles: true }));

		// The interrupted stroke is preserved, not silently discarded.
		expect(document.querySelector('.fta-signature-value').value).toBe(FAKE_DATA_URL);

		const lineToCallsAtCancel = ctx.lineTo.mock.calls.length;

		// A later, unrelated pointermove (e.g. the mouse re-entering the
		// canvas with no button held) must not resume the cancelled stroke.
		canvas.dispatchEvent(new window.PointerEvent('pointermove', { bubbles: true, clientX: 90, clientY: 90 }));

		expect(ctx.lineTo.mock.calls.length).toBe(lineToCallsAtCancel);
	});
});
