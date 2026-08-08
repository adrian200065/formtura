/**
 * reCAPTCHA token flow tests for assets/js/frontend.js.
 *
 * The bug these cover: nothing on the page ever produced a token, so every
 * submission reached the server without one. These assert the browser half of
 * the flow - that a token is obtained before the request goes out, that a
 * missing v2 tick stops the submission locally, and that a spent token is
 * cleared afterwards.
 */

const fs = require('fs');
const path = require('path');

const FRONTEND_JS = fs.readFileSync(
	path.join(__dirname, '..', '..', 'assets', 'js', 'frontend.js'),
	'utf8'
);

const SITE_KEY = 'site-key';

// One jQuery for the whole file, the way a page has one: the document is shared
// between tests, so a second instance would leave handlers behind that off()
// could not clear.
const jQuery = require('jquery');
window.jQuery = jQuery;
window.$ = jQuery;

/**
 * Load frontend.js into the current jsdom document.
 *
 * @param {object|null} recaptcha Localized reCAPTCHA config, or null when off.
 */
function loadFrontend(recaptcha) {
	window.formturaFrontend = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'test-nonce',
		recaptcha,
		strings: {
			recaptchaMissing: 'Please confirm you are not a robot.',
			recaptchaError: 'reCAPTCHA could not be loaded. Please reload the page and try again.',
		},
	};

	// eslint-disable-next-line no-eval
	window.eval(FRONTEND_JS);

	// frontend.js binds its handlers on document ready. Queueing after it means
	// this resolves once those handlers are in place.
	return new Promise(resolve => jQuery(document).ready(resolve));
}

/**
 * Build a form with a v2 widget container.
 */
function renderForm({ withWidgetContainer = false } = {}) {
	document.body.innerHTML = `
		<div class="fta-form-container">
			<form class="fta-form" data-form-id="7">
				<div class="fta-field">
					<input type="text" class="fta-field-input" name="field_1" value="hello">
				</div>
				${withWidgetContainer ? '<div class="fta-field fta-field-recaptcha"><div class="fta-recaptcha" data-fta-recaptcha data-sitekey="' + SITE_KEY + '"></div></div>' : ''}
				<div class="fta-form-footer">
					<button type="submit" class="fta-submit-button">Submit</button>
				</div>
			</form>
		</div>
	`;
}

/**
 * Minimal stand-in for Google's grecaptcha object.
 */
function fakeGrecaptcha({ token = 'v2-token', executeToken = 'v3-token', executeFails = false } = {}) {
	const api = {
		rendered: [],
		resetCalls: [],
		token,
		render: jest.fn(function(container, options) {
			const id = api.rendered.length;
			api.rendered.push({ container, options });
			return id;
		}),
		getResponse: jest.fn(() => api.token),
		reset: jest.fn(id => {
			api.resetCalls.push(id);
			api.token = '';
		}),
		ready: jest.fn(cb => cb()),
		execute: jest.fn(() => (
			executeFails ? Promise.reject(new Error('nope')) : Promise.resolve(executeToken)
		)),
	};

	return api;
}

/**
 * Read the token from the FormData handed to $.ajax.
 */
function submittedToken(ajaxMock) {
	return ajaxMock.mock.calls[0][0].data.get('g-recaptcha-response');
}

/**
 * Submit the form and let the pending promises settle.
 */
async function submitForm() {
	window.jQuery('.fta-form').trigger('submit');

	// One tick for the token promise, one for the .then that fires the request.
	await Promise.resolve();
	await Promise.resolve();
	await Promise.resolve();
}

describe('frontend reCAPTCHA token flow', () => {
	let ajaxMock;

	beforeEach(() => {
		// Each test loads frontend.js again, so drop the previous load's
		// delegated handlers instead of stacking another set on the document.
		jQuery(document).off();

		document.body.innerHTML = '';
		delete window.grecaptcha;
		delete window.formturaRecaptchaOnload;

		ajaxMock = jest.fn();
		jQuery.ajax = ajaxMock;

		// jsdom has no scrollIntoView.
		window.HTMLElement.prototype.scrollIntoView = jest.fn();
	});

	test('submits without a token when reCAPTCHA is not configured', async () => {
		renderForm();
		await loadFrontend(null);

		await submitForm();

		expect(ajaxMock).toHaveBeenCalledTimes(1);
		expect(submittedToken(ajaxMock)).toBeNull();
	});

	test('renders the v2 widget into the container when the API is ready', async () => {
		renderForm({ withWidgetContainer: true });
		window.grecaptcha = fakeGrecaptcha();

		await loadFrontend({ siteKey: SITE_KEY, version: 'v2', action: 'formtura_submit' });

		expect(window.grecaptcha.render).toHaveBeenCalledTimes(1);
		expect(window.grecaptcha.rendered[0].options).toEqual({ sitekey: SITE_KEY });
	});

	test('renders the v2 widget when the API arrives after page load', async () => {
		renderForm({ withWidgetContainer: true });
		await loadFrontend({ siteKey: SITE_KEY, version: 'v2', action: 'formtura_submit' });

		// The onload callback must exist before Google's api.js runs it.
		expect(typeof window.formturaRecaptchaOnload).toBe('function');

		window.grecaptcha = fakeGrecaptcha();
		window.formturaRecaptchaOnload();
		await new Promise(resolve => setTimeout(resolve, 0));

		expect(window.grecaptcha.render).toHaveBeenCalledTimes(1);
	});

	test('does not render the same container twice', async () => {
		renderForm({ withWidgetContainer: true });
		window.grecaptcha = fakeGrecaptcha();

		await loadFrontend({ siteKey: SITE_KEY, version: 'v2', action: 'formtura_submit' });
		window.formturaRenderRecaptcha();

		expect(window.grecaptcha.render).toHaveBeenCalledTimes(1);
	});

	test('sends the v2 widget token with the submission', async () => {
		renderForm({ withWidgetContainer: true });
		window.grecaptcha = fakeGrecaptcha({ token: 'checked-token' });

		await loadFrontend({ siteKey: SITE_KEY, version: 'v2', action: 'formtura_submit' });
		await submitForm();

		expect(ajaxMock).toHaveBeenCalledTimes(1);
		expect(submittedToken(ajaxMock)).toBe('checked-token');
	});

	test('blocks the submission and explains why when the v2 box is unticked', async () => {
		renderForm({ withWidgetContainer: true });
		window.grecaptcha = fakeGrecaptcha({ token: '' });

		await loadFrontend({ siteKey: SITE_KEY, version: 'v2', action: 'formtura_submit' });
		await submitForm();

		expect(ajaxMock).not.toHaveBeenCalled();
		expect(document.querySelector('.fta-error-message').textContent)
			.toContain('Please confirm you are not a robot.');
		// The visitor has to be able to try again.
		expect(document.querySelector('.fta-submit-button').disabled).toBe(false);
	});

	test('resets the widget after a successful submission so the next one gets a fresh token', async () => {
		renderForm({ withWidgetContainer: true });
		window.grecaptcha = fakeGrecaptcha({ token: 'checked-token' });

		await loadFrontend({ siteKey: SITE_KEY, version: 'v2', action: 'formtura_submit' });
		await submitForm();

		ajaxMock.mock.calls[0][0].success({ success: true, data: { message: 'Thanks' } });

		expect(window.grecaptcha.reset).toHaveBeenCalledWith(0);
	});

	test('resets the widget when the server rejects the token', async () => {
		renderForm({ withWidgetContainer: true });
		window.grecaptcha = fakeGrecaptcha({ token: 'stale-token' });

		await loadFrontend({ siteKey: SITE_KEY, version: 'v2', action: 'formtura_submit' });
		await submitForm();

		ajaxMock.mock.calls[0][0].success({
			success: false,
			data: { message: 'reCAPTCHA verification failed.', recaptcha: 'recaptcha_failed' },
		});

		expect(window.grecaptcha.reset).toHaveBeenCalledWith(0);
	});

	test('executes v3 for the configured action and sends the minted token', async () => {
		renderForm();
		window.grecaptcha = fakeGrecaptcha({ executeToken: 'fresh-v3-token' });

		await loadFrontend({ siteKey: SITE_KEY, version: 'v3', action: 'formtura_submit' });
		await submitForm();

		expect(window.grecaptcha.execute)
			.toHaveBeenCalledWith(SITE_KEY, { action: 'formtura_submit' });
		expect(submittedToken(ajaxMock)).toBe('fresh-v3-token');
	});

	test('does not render a widget for v3', async () => {
		renderForm();
		window.grecaptcha = fakeGrecaptcha();

		await loadFrontend({ siteKey: SITE_KEY, version: 'v3', action: 'formtura_submit' });

		expect(window.grecaptcha.render).not.toHaveBeenCalled();
	});

	test('stops the submission when v3 cannot mint a token', async () => {
		renderForm();
		window.grecaptcha = fakeGrecaptcha({ executeFails: true });

		await loadFrontend({ siteKey: SITE_KEY, version: 'v3', action: 'formtura_submit' });
		await submitForm();

		expect(ajaxMock).not.toHaveBeenCalled();
		expect(document.querySelector('.fta-error-message').textContent)
			.toContain('reCAPTCHA could not be loaded.');
		expect(document.querySelector('.fta-submit-button').disabled).toBe(false);
	});

	test('stops the submission when the reCAPTCHA API never loaded', async () => {
		renderForm({ withWidgetContainer: true });
		// grecaptcha is absent - blocked network, ad blocker, and so on.

		await loadFrontend({ siteKey: SITE_KEY, version: 'v2', action: 'formtura_submit' });
		await submitForm();

		expect(ajaxMock).not.toHaveBeenCalled();
		expect(document.querySelector('.fta-error-message').textContent)
			.toContain('reCAPTCHA could not be loaded.');
	});
});
