const { test, expect } = require( '@playwright/test' );
const { createFormPage, deleteForm } = require( '../fixtures/forms' );
const { loginAsAdmin } = require( '../fixtures/auth' );
const { readMailLog } = require( '../fixtures/mail' );

/**
 * The backend has always fully supported notifications (recipient, subject,
 * message, reply-to, cc, bcc, smart tags) - see Notifications.php - but
 * nothing in the builder could ever create or edit one, so this spec used to
 * seed settings.notifications directly through fta_create_form() rather than
 * through the UI, which meant a regression in the builder's own notification
 * fields could never fail this test. It now drives the real Form Settings
 * dialog: a new form defaults to one enabled admin notification (see
 * FormBuilder.jsx's initial state), which is edited to a known recipient
 * here, saved, and confirmed to actually fire on a real submission.
 *
 * As with the other notification coverage, the mail-capture mu-plugin
 * (fixtures/mu-plugins/e2e-mail-log.php) intercepts wp_mail() before
 * PHPMailer/SMTP is involved - this proves the notification pipeline end to
 * end, not the SMTP wire protocol itself.
 */
test.describe( 'Notifications', () => {
	let formId;

	test.afterEach( () => {
		if ( formId ) {
			deleteForm( formId );
			formId = undefined;
		}
	} );

	test( 'configuring a notification in the builder sends it on submission', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/admin.php?page=formtura-builder' );
		await expect( page.locator( '.formtura-builder' ) ).toBeVisible();

		await page.getByRole( 'button', { name: 'Form settings' } ).click();
		await page.fill( '#formtura-settings-title', 'E2E Notification Form' );

		// A new form defaults to one enabled admin notification (to
		// {admin_email}) - overwritten here with a known recipient so the
		// test isn't coupled to the disposable instance's actual admin email.
		await expect( page.locator( '#formtura-settings-notify-enabled' ) ).toBeChecked();
		await page.fill( '#formtura-settings-notify-to', 'owner@example.test' );
		await page.fill( '#formtura-settings-notify-subject', 'New submission on {site_name}' );
		await page.fill( '#formtura-settings-notify-message', 'A new message has arrived.' );
		await page.getByRole( 'button', { name: 'Save', exact: true } ).click();

		await page.click( 'button[aria-label="Add Paragraph Text field"]' );
		await expect( page.locator( '.formtura-dropped-field' ) ).toHaveCount( 1 );

		await page.getByRole( 'button', { name: 'Save form' } ).click();
		await page.waitForURL( /form_id=\d+/ );

		formId = Number( new URL( page.url() ).searchParams.get( 'form_id' ) );
		expect( formId ).toBeGreaterThan( 0 );

		const pagePath = createFormPage( formId );
		await page.goto( pagePath );

		await page.fill( '.fta-form textarea', 'Hello from Playwright' );
		await page.click( '.fta-form .fta-submit-button' );
		await expect( page.locator( '.fta-success-message' ) ).toBeVisible();

		const toThisRecipient = ( mail ) => [].concat( mail.to ).includes( 'owner@example.test' );

		await expect.poll( () => readMailLog().filter( toThisRecipient ) ).toHaveLength( 1 );

		const [ mail ] = readMailLog().filter( toThisRecipient );
		expect( mail.subject ).toContain( 'New submission' );
		expect( mail.message ).toContain( 'A new message has arrived.' );
	} );
} );
