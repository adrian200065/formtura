/**
 * Form deletion interaction for assets/js/admin.js.
 *
 * The forms list rendered a Delete button (see forms-list.php) with no
 * handler bound to it at all - clicking it did nothing. fta_delete_form()
 * existed and was fully tested at the database layer, and the AJAX endpoint
 * (`fta_delete_form`) exists (see FormDeleteActionTest), but nothing in the
 * browser could ever reach either.
 */

const fs = require('fs');
const path = require('path');

const ADMIN_JS = fs.readFileSync(
	path.join(__dirname, '..', '..', 'assets', 'js', 'admin.js'),
	'utf8'
);

const jQuery = require('jquery');
window.jQuery = jQuery;
window.$ = jQuery;

function loadAdmin() {
	window.formturaAdmin = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'test-nonce',
		strings: {
			confirmDelete: 'Are you sure you want to delete this item?',
			confirmDeleteForm: 'Delete this form? All of its entries and uploaded files are deleted with it and cannot be recovered.',
			formDeleted: 'Form deleted.',
			saving: 'Saving...',
			saved: 'Saved!',
			error: 'An error occurred.',
		},
	};

	// eslint-disable-next-line no-eval
	window.eval(ADMIN_JS);

	return new Promise(resolve => jQuery(document).ready(resolve));
}

function renderScreen() {
	document.body.innerHTML = `
		<div class="fta-admin-page">
			<table>
				<tbody>
					<tr class="fta-form-row" data-form-id="5">
						<td class="fta-table-primary"><a href="#">Contact Form</a></td>
						<td>3</td>
						<td>Active</td>
						<td>2026-08-01</td>
						<td>
							<div class="fta-table-actions">
								<a href="#">Edit</a>
								<a href="#">Entries</a>
								<button type="button" class="fta-link-button fta-link-button-danger fta-delete-form" data-form-id="5">Delete</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	`;
}

describe('forms list delete control', () => {
	let ajaxSpy;

	beforeEach(async () => {
		document.body.innerHTML = '';
		jQuery(document).off();
		delete window.FormturaAdmin;

		await loadAdmin();
		renderScreen();

		ajaxSpy = jest.spyOn(jQuery, 'ajax');
	});

	afterEach(() => {
		ajaxSpy.mockRestore();
		jest.restoreAllMocks();
	});

	function respond(data, success = true) {
		ajaxSpy.mockImplementation(options => {
			options.success({ success, data });

			if (options.complete) {
				options.complete();
			}

			return jQuery.Deferred().resolve();
		});
	}

	it('asks before deleting and removes the row once confirmed', () => {
		jest.spyOn(window, 'confirm').mockReturnValue(true);
		respond({ message: 'Form deleted successfully.' });

		jQuery('.fta-delete-form').trigger('click');

		expect(window.confirm).toHaveBeenCalledWith(
			'Delete this form? All of its entries and uploaded files are deleted with it and cannot be recovered.'
		);
		expect(ajaxSpy.mock.calls[0][0].data).toMatchObject({
			action: 'fta_delete_form',
			nonce: 'test-nonce',
			form_id: '5',
		});
		expect(jQuery('.fta-form-row').length).toBe(0);
	});

	it('does nothing when the confirmation is dismissed', () => {
		jest.spyOn(window, 'confirm').mockReturnValue(false);

		jQuery('.fta-delete-form').trigger('click');

		expect(ajaxSpy).not.toHaveBeenCalled();
		expect(jQuery('.fta-form-row').length).toBe(1);
	});

	it('keeps the row when the server refuses', () => {
		jest.spyOn(window, 'confirm').mockReturnValue(true);
		respond({ message: 'Failed to delete form.' }, false);

		jQuery('.fta-delete-form').trigger('click');

		expect(jQuery('.fta-form-row').length).toBe(1);
	});
});
