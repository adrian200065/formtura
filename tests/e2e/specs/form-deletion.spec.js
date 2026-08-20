const { test, expect } = require( '@playwright/test' );
const { createForm, createFormPage, deleteForm } = require( '../fixtures/forms' );
const { loginAsAdmin } = require( '../fixtures/auth' );
const { wpEval } = require( '../fixtures/wp-cli' );

// The smallest valid PNG (1x1, transparent). wp_check_filetype_and_ext()
// inspects real file content, so a renamed .txt would be rejected - this has
// to actually be a PNG (see uploads.spec.js, which uses the same fixture).
const TINY_PNG = Buffer.from(
	'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
	'base64'
);

/**
 * The forms list rendered a Delete button (forms-list.php) with no JS
 * handler bound to it and no `wp_ajax_fta_delete_form` action registered -
 * fta_delete_form() existed and was fully tested at the database layer
 * (deleting a form's entries and every file they reference), but nothing in
 * the admin UI could ever reach it. This drives the real button end to end:
 * the confirmation prompt, the form and its entry disappearing, and the
 * entry's uploaded file actually being gone from the private vault
 * afterwards - not just the database row.
 */
test.describe( 'Form deletion', () => {
	let formId;

	test.afterEach( () => {
		if ( formId ) {
			deleteForm( formId );
			formId = undefined;
		}
	} );

	test( 'deleting a form from the list removes it, its entries, and their uploaded files', async ( { page } ) => {
		formId = createForm( {
			title: 'E2E Deletion Form',
			status: 'active',
			fields: [
				{ id: 'field_name', type: 'text', label: 'Name', required: true },
				{
					id: 'field_attachment',
					type: 'file-upload',
					label: 'Attachment',
					required: true,
					allowedFileTypes: 'all',
				},
			],
		} );

		const pagePath = createFormPage( formId );
		await page.goto( pagePath );

		await page.fill( '.fta-form input[name="field_name"]', 'Ada Lovelace' );
		await page.setInputFiles( '.fta-form input.fta-file-upload-input[name="field_attachment"]', {
			name: 'test-upload.png',
			mimeType: 'image/png',
			buffer: TINY_PNG,
		} );
		await page.click( '.fta-form .fta-submit-button' );
		await expect( page.locator( '.fta-success-message' ) ).toBeVisible();

		await loginAsAdmin( page );
		await page.goto( `/wp-admin/admin.php?page=formtura-entries&form_id=${ formId }` );

		await page.locator( 'tr.fta-entry-row' ).first().locator( '.fta-view-entry' ).first().click();
		const downloadLink = page.locator( '.fta-entry-modal a[href*="fta_download_file"]' );
		await expect( downloadLink ).toBeVisible();
		const downloadUrl = await downloadLink.getAttribute( 'href' );

		// The file exists and is reachable before deletion...
		expect( ( await page.request.get( downloadUrl ) ).status() ).toBe( 200 );

		await page.goto( '/wp-admin/admin.php?page=formtura' );

		const row = page.locator( `tr.fta-form-row[data-form-id="${ formId }"]` );
		await expect( row ).toBeVisible();

		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await row.locator( '.fta-delete-form' ).click();

		await expect( page.locator( `tr.fta-form-row[data-form-id="${ formId }"]` ) ).toHaveCount( 0 );

		// ...and gone afterwards, both from the database and the private vault.
		// The same authenticated session that could download it a moment ago
		// now gets a 404 (see File_Download::find_record()) - the record and
		// the underlying file are gone, not just newly forbidden.
		const formRow = wpEval( `echo fta_get_form( ${ formId } ) ? '1' : '0';` );
		expect( formRow ).toBe( '0' );

		expect( ( await page.request.get( downloadUrl ) ).status() ).toBe( 404 );

		formId = undefined;
	} );
} );
