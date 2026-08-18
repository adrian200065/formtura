const { test, expect } = require( '@playwright/test' );
const { createForm, createFormPage, deleteForm } = require( '../fixtures/forms' );
const { loginAsAdmin } = require( '../fixtures/auth' );

/**
 * Covers the core visitor-to-admin loop: a form is filled in and submitted
 * on the frontend, the resulting entry appears in the admin entries list,
 * and deleting it there removes it. The form itself is seeded directly
 * (see fixtures/forms.js) rather than built through the React builder UI -
 * that gets its own dedicated coverage.
 */
test.describe( 'Form submission and entries', () => {
	let formId;
	let pagePath;

	test.beforeAll( () => {
		formId = createForm( {
			title: 'E2E Submission Form',
			status: 'active',
			fields: [
				{ id: 'field_name', type: 'text', label: 'Name', required: true },
				{ id: 'field_email', type: 'email', label: 'Email', required: true },
			],
		} );
		pagePath = createFormPage( formId );
	} );

	test.afterAll( () => {
		deleteForm( formId );
	} );

	test( 'a visitor can submit the form and the entry shows up for an admin, who can delete it', async ( { page } ) => {
		await page.goto( pagePath );

		await page.fill( '.fta-form input[name="field_name"]', 'Ada Lovelace' );
		await page.fill( '.fta-form input[name="field_email"]', 'ada@example.test' );
		await page.click( '.fta-form .fta-submit-button' );

		await expect( page.locator( '.fta-success-message' ) ).toBeVisible();

		await loginAsAdmin( page );
		await page.goto( `/wp-admin/admin.php?page=formtura-entries&form_id=${ formId }` );

		const row = page.locator( `tr.fta-entry-row[data-entry-id]` ).filter( { hasText: 'Ada Lovelace' } );
		await expect( row ).toBeVisible();
		await expect( row ).toContainText( 'ada@example.test' );

		const entryId = await row.getAttribute( 'data-entry-id' );

		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await row.locator( '.fta-delete-entry' ).click();

		await expect( page.locator( `tr.fta-entry-row[data-entry-id="${ entryId }"]` ) ).toHaveCount( 0 );
	} );
} );
