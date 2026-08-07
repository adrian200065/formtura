/**
 * Signature pad tests for assets/js/frontend.js.
 *
 * jsdom has no canvas implementation, so 2D context and toDataURL are
 * mocked; the tests cover the wiring, not the pixels: strokes populate the
 * hidden input, Clear empties it, and a required empty pad blocks submit.
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

const FAKE_DATA_URL = 'data:image/png;base64,AAAA';

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
		window.formturaInitSignaturePads();

		drawStroke(document.querySelector('.fta-signature-canvas'));

		expect(document.querySelector('.fta-signature-value').value).toBe(FAKE_DATA_URL);
	});

	test('clear empties the hidden input', async () => {
		renderForm();
		await loadFrontend();
		window.formturaInitSignaturePads();

		drawStroke(document.querySelector('.fta-signature-canvas'));
		jQuery('.fta-signature-clear').trigger('click');

		expect(document.querySelector('.fta-signature-value').value).toBe('');
	});

	test('a required empty pad blocks submission with a message', async () => {
		renderForm({ required: true });
		await loadFrontend();
		window.formturaInitSignaturePads();

		jQuery('.fta-form').trigger('submit');
		await Promise.resolve();
		await Promise.resolve();
		await Promise.resolve();

		expect(ajaxMock).not.toHaveBeenCalled();
		expect(document.querySelector('.fta-field-error').textContent)
			.toContain('Please add your signature.');
	});

	test('a signed required pad submits', async () => {
		renderForm({ required: true });
		await loadFrontend();
		window.formturaInitSignaturePads();

		drawStroke(document.querySelector('.fta-signature-canvas'));

		jQuery('.fta-form').trigger('submit');
		await Promise.resolve();
		await Promise.resolve();
		await Promise.resolve();

		expect(ajaxMock).toHaveBeenCalledTimes(1);
	});
});
