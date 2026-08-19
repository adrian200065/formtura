const { test, expect } = require( '@playwright/test' );
const { createFormPage, deleteForm } = require( '../fixtures/forms' );
const { loginAsAdmin } = require( '../fixtures/auth' );

/**
 * Drives the React builder SPA itself, the one flow every other E2E spec
 * deliberately skips by seeding forms via fta_create_form() (see
 * fixtures/forms.js: "the builder itself... gets its own dedicated
 * browser-driven coverage"). Confirms the palette's add-field buttons, the
 * settings dialog and the save-to-AJAX flow together produce a form that
 * actually renders and submits on the frontend - not just that
 * fta_create_form() does.
 *
 * Fields are added by clicking their library button rather than simulating
 * a dnd-kit drag: DraggableField's onClick calls the same onAdd() handler
 * handleDragEnd() does for a canvas drop, and the canvas empty-state hint
 * ("focus a field and press Enter") confirms this is a supported path, not
 * a workaround - drag reordering itself is a separate, dnd-kit-internal
 * concern this spec doesn't need to cover.
 */
test.describe( 'Form builder', () => {
	let formId;

	test.afterEach( () => {
		if ( formId ) {
			deleteForm( formId );
			formId = undefined;
		}
	} );

	test( 'an admin can build a form in the builder UI and a visitor can submit it', async ( { page } ) => {
		await loginAsAdmin( page );
		await page.goto( '/wp-admin/admin.php?page=formtura-builder' );
		await expect( page.locator( '.formtura-builder' ) ).toBeVisible();

		await page.getByRole( 'button', { name: 'Form settings' } ).click();
		await page.fill( '#formtura-settings-title', 'E2E Builder Form' );
		await page.getByRole( 'button', { name: 'Save', exact: true } ).click();

		// Adding a field selects it, which auto-switches the sidebar to
		// "Field Options" (see FieldLibrary's selectedField effect) and hides
		// the palette - so the "Add Fields" tab has to be reselected before
		// each add.
		await page.click( 'button[aria-label="Add Single Line Text field"]' );
		await page.getByRole( 'button', { name: 'Add Fields' } ).click();
		await page.click( 'button[aria-label="Add Email field"]' );
		await expect( page.locator( '.formtura-dropped-field' ) ).toHaveCount( 2 );

		await page.getByRole( 'button', { name: 'Save form' } ).click();
		await page.waitForURL( /form_id=\d+/ );

		formId = Number( new URL( page.url() ).searchParams.get( 'form_id' ) );
		expect( formId ).toBeGreaterThan( 0 );

		const pagePath = createFormPage( formId );
		await page.goto( pagePath );

		await page.fill( '.fta-form input[type="text"]', 'Ada Lovelace' );
		await page.fill( '.fta-form input[type="email"]', 'ada@example.test' );
		await page.click( '.fta-form .fta-submit-button' );

		await expect( page.locator( '.fta-success-message' ) ).toBeVisible();
	} );
} );
