const { test, expect } = require( '@playwright/test' );
const { createForm, createFormPage, deleteForm } = require( '../fixtures/forms' );
const { loginAsAdmin } = require( '../fixtures/auth' );

// The smallest valid PNG (1x1, transparent). wp_check_filetype_and_ext()
// inspects real file content, so a renamed .txt would be rejected - this
// has to actually be a PNG.
const TINY_PNG = Buffer.from(
	'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
	'base64'
);

test.describe( 'File uploads', () => {
	let formId;
	let pagePath;

	test.beforeAll( () => {
		formId = createForm( {
			title: 'E2E Upload Form',
			status: 'active',
			fields: [
				{
					id: 'field_attachment',
					type: 'file-upload',
					label: 'Attachment',
					required: true,
					allowedFileTypes: 'all',
				},
			],
		} );
		pagePath = createFormPage( formId );
	} );

	test.afterAll( () => {
		deleteForm( formId );
	} );

	test( 'an uploaded file is stored privately and only reachable by a logged-in admin', async ( { page, request } ) => {
		await page.goto( pagePath );

		await page.setInputFiles( '.fta-form input.fta-file-upload-input[name="field_attachment"]', {
			name: 'test-upload.png',
			mimeType: 'image/png',
			buffer: TINY_PNG,
		} );
		await page.click( '.fta-form .fta-submit-button' );
		await expect( page.locator( '.fta-success-message' ) ).toBeVisible();

		await loginAsAdmin( page );
		await page.goto( `/wp-admin/admin.php?page=formtura-entries&form_id=${ formId }` );

		const row = page.locator( 'tr.fta-entry-row' ).first();
		await row.locator( '.fta-view-entry' ).first().click();

		// viewEntry() clones the (already server-escaped) hidden details into
		// a modal dialog rather than un-hiding them in place.
		const downloadLink = page.locator( '.fta-entry-modal a[href*="fta_download_file"]' );
		await expect( downloadLink ).toBeVisible();
		const downloadUrl = await downloadLink.getAttribute( 'href' );

		// Reachable while logged in, through the same authenticated context...
		const authedResponse = await page.request.get( downloadUrl );
		expect( authedResponse.status() ).toBe( 200 );
		expect( authedResponse.headers()[ 'content-type' ] ).toContain( 'image/png' );

		// ...but denied entirely for a request with no session at all - the
		// vault is private, not just hidden from the admin list.
		const anonymousResponse = await request.get( downloadUrl );
		expect( anonymousResponse.status() ).not.toBe( 200 );
	} );
} );
