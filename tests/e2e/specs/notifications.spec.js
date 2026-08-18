const { test, expect } = require( '@playwright/test' );
const { createForm, createFormPage, deleteForm } = require( '../fixtures/forms' );
const { readMailLog } = require( '../fixtures/mail' );

/**
 * Covers the "SMTP" checklist item at the level that matters for a
 * disposable, no-real-SMTP-server test environment: that a submission
 * triggers exactly the notification email the form's settings describe.
 * The mail-capture mu-plugin (fixtures/mu-plugins/e2e-mail-log.php)
 * intercepts wp_mail() before PHPMailer/SMTP ever gets involved, so this
 * does not exercise the actual SMTP wire protocol - proving the SMTP
 * settings UI drives a real outbound connection would need a fake SMTP
 * server (e.g. MailHog) and is out of scope for this pass.
 */
test.describe( 'Notifications', () => {
	let formId;
	let pagePath;

	test.beforeAll( () => {
		formId = createForm( {
			title: 'E2E Notification Form',
			status: 'active',
			fields: [
				{ id: 'field_message', type: 'textarea', label: 'Message', required: true },
			],
			settings: {
				notifications: [
					{
						enabled: true,
						to: 'owner@example.test',
						subject: 'New submission on {form_title}',
						message: 'Message: {field_message}',
					},
				],
			},
		} );
		pagePath = createFormPage( formId );
	} );

	test.afterAll( () => {
		deleteForm( formId );
	} );

	test( 'submitting a form sends its configured notification email', async ( { page } ) => {
		await page.goto( pagePath );
		await page.fill( '.fta-form textarea[name="field_message"]', 'Hello from Playwright' );
		await page.click( '.fta-form .fta-submit-button' );
		await expect( page.locator( '.fta-success-message' ) ).toBeVisible();

		const toThisRecipient = ( mail ) => [].concat( mail.to ).includes( 'owner@example.test' );

		await expect.poll( () => readMailLog().filter( toThisRecipient ) ).toHaveLength( 1 );

		const [ mail ] = readMailLog().filter( toThisRecipient );
		expect( mail.subject ).toContain( 'New submission' );
		expect( mail.message ).toContain( 'Hello from Playwright' );
	} );
} );
