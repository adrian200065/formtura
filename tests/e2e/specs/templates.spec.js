const { test, expect } = require( '@playwright/test' );
const { createFormPage, deleteForm } = require( '../fixtures/forms' );
const { loginAsAdmin } = require( '../fixtures/auth' );

/**
 * Every non-blank built-in template used to ship fields with no `id` (and no
 * `name`), because Form_Templates::get_templates() never assigned one and
 * ajax_create_from_template() saved the fields unchanged. fta_get_field_name()
 * falls back to `id` when `name` is absent, so a blank id meant a blank
 * `name` attribute on every rendered input: submitted values collided under
 * the same empty key and validate_submission() skipped the required check
 * entirely (it bails out for any field whose name is ''). This spec drives
 * the real "Use template" AJAX flow (not the fta_create_form() bypass other
 * specs use - see fixtures/forms.js) so a regression here is caught even if
 * the fields array itself sanitizes fine in isolation.
 */
test.describe( 'Form templates', () => {
	let formId;

	test.afterEach( () => {
		if ( formId ) {
			deleteForm( formId );
			formId = undefined;
		}
	} );

	test( 'creating a form from the Contact template renders fields with real names, enforces required fields, and saves entry values', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/admin.php?page=formtura-new' );

		await page.click( '.fta-use-template[data-template-id="contact"]' );
		await page.waitForURL( /page=formtura-builder&form_id=\d+/ );

		formId = Number( new URL( page.url() ).searchParams.get( 'form_id' ) );
		expect( formId ).toBeGreaterThan( 0 );

		const pagePath = createFormPage( formId );
		await page.goto( pagePath );

		// Rendering: every input posts under a real, non-empty name - not the
		// blank string every field shared before the fix.
		await expect( page.locator( '.fta-form input[name="field_contact_name[first]"]' ) ).toBeVisible();
		await expect( page.locator( '.fta-form input[name="field_contact_name[last]"]' ) ).toBeVisible();
		await expect( page.locator( '.fta-form input[name="field_contact_email"]' ) ).toBeVisible();
		await expect( page.locator( '.fta-form textarea[name="field_contact_message"]' ) ).toBeVisible();
		await expect( page.locator( '.fta-form [name=""]' ) ).toHaveCount( 0 );

		// Required validation: the required fields carry a real `required`
		// attribute (fta_get_field_input_id() also derives from `id`, so a
		// blank id broke this the same way it broke `name`), and the browser's
		// native constraint validation blocks submission while they're empty.
		await expect( page.locator( '.fta-form input[name="field_contact_email"]' ) ).toHaveAttribute( 'required', '' );
		await page.click( '.fta-form .fta-submit-button' );
		await expect( page.locator( '.fta-success-message' ) ).toBeHidden();

		// Fill every required field and submit for real.
		await page.fill( '.fta-form input[name="field_contact_name[first]"]', 'Ada' );
		await page.fill( '.fta-form input[name="field_contact_name[last]"]', 'Lovelace' );
		await page.fill( '.fta-form input[name="field_contact_email"]', 'ada@example.test' );
		await page.fill( '.fta-form textarea[name="field_contact_message"]', 'Hello from the contact template.' );
		await page.click( '.fta-form .fta-submit-button' );

		await expect( page.locator( '.fta-success-message' ) ).toBeVisible();

		// Saved entry values: the admin sees the values under their real keys,
		// not merged/lost under a shared blank name.
		await page.goto( `/wp-admin/admin.php?page=formtura-entries&form_id=${ formId }` );

		const row = page.locator( 'tr.fta-entry-row[data-entry-id]' ).filter( { hasText: 'Ada' } );
		await expect( row ).toBeVisible();
		await expect( row ).toContainText( 'ada@example.test' );
		await expect( row ).toContainText( 'Hello from the contact template.' );
	} );
} );
