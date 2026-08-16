/**
 * Entry management interactions for assets/js/entries.js.
 *
 * The entries screen rendered View, Delete, Mark as Read and Export controls
 * that no handler was ever bound to, so clicking them did nothing at all -
 * every one of them was a dead link with a real-looking label.
 */

const fs = require('fs');
const path = require('path');

const ENTRIES_JS = fs.readFileSync(
	path.join(__dirname, '..', '..', 'assets', 'js', 'entries.js'),
	'utf8'
);

const jQuery = require('jquery');
window.jQuery = jQuery;
window.$ = jQuery;

function loadEntries() {
	window.formturaEntries = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'test-nonce',
		entriesUrl: '/wp-admin/admin.php?page=formtura-entries',
		strings: {
			confirmDelete: 'Delete this entry?',
			deleted: 'Entry deleted.',
			read: 'Read',
			unread: 'Unread',
			markRead: 'Mark as Read',
			markUnread: 'Mark as Unread',
			entryTitle: 'Entry #%s',
			close: 'Close',
			error: 'An error occurred.',
		},
	};

	// eslint-disable-next-line no-eval
	window.eval(ENTRIES_JS);

	return new Promise(resolve => jQuery(document).ready(resolve));
}

function renderScreen() {
	document.body.innerHTML = `
		<div class="fta-admin-page">
			<button class="fta-export-entries" data-form-id="7">Export Entries</button>
			<select id="fta-form-select"><option value=""></option><option value="9">Other</option></select>
			<table>
				<tbody>
					<tr class="fta-entry-row fta-entry-unread" data-entry-id="4">
						<td>4</td>
						<td>
							<div class="fta-entry-preview"></div>
							<div class="fta-entry-details" id="fta-entry-details-4" hidden>
								<div class="fta-entry-field"><strong>Your name:</strong> Ada Lovelace</div>
								<div class="fta-entry-field"><strong>Notes:</strong> A late answer</div>
							</div>
						</td>
						<td><span class="fta-status fta-status-unread">Unread</span></td>
						<td>2026-08-15</td>
						<td>
							<a href="#" class="fta-mark-read" data-entry-id="4" data-is-read="0">Mark as Read</a>
							<a href="#" class="fta-view-entry" data-entry-id="4">View</a>
							<a href="#" class="fta-delete-entry" data-entry-id="4">Delete</a>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	`;
}

describe('entry management controls', () => {
	let ajaxSpy;

	beforeEach(async () => {
		document.body.innerHTML = '';
		jQuery(document).off();
		delete window.FormturaEntries;

		await loadEntries();
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

	describe('mark as read', () => {
		it('posts the new status for the clicked entry', () => {
			respond({ message: 'Entry status updated.', is_read: true });

			jQuery('.fta-mark-read').trigger('click');

			expect(ajaxSpy).toHaveBeenCalledTimes(1);
			expect(ajaxSpy.mock.calls[0][0].data).toMatchObject({
				action: 'fta_mark_entry_read',
				nonce: 'test-nonce',
				entry_id: '4',
				is_read: 1,
			});
		});

		it('redraws the row from the status the server confirmed', () => {
			respond({ message: 'Entry status updated.', is_read: true });

			jQuery('.fta-mark-read').trigger('click');

			expect(jQuery('.fta-entry-row').hasClass('fta-entry-read')).toBe(true);
			expect(jQuery('.fta-entry-row').hasClass('fta-entry-unread')).toBe(false);
			expect(jQuery('.fta-status').text().trim()).toBe('Read');
			expect(jQuery('.fta-mark-read').text().trim()).toBe('Mark as Unread');
		});

		it('toggles back to unread on a second click', () => {
			respond({ message: 'Entry status updated.', is_read: true });
			jQuery('.fta-mark-read').trigger('click');

			respond({ message: 'Entry status updated.', is_read: false });
			jQuery('.fta-mark-read').trigger('click');

			expect(ajaxSpy.mock.calls[1][0].data.is_read).toBe(0);
			expect(jQuery('.fta-status').text().trim()).toBe('Unread');
		});

		it('leaves the row alone when the write fails', () => {
			respond({ message: 'Failed to update the entry status.' }, false);

			jQuery('.fta-mark-read').trigger('click');

			expect(jQuery('.fta-entry-row').hasClass('fta-entry-unread')).toBe(true);
			expect(jQuery('.fta-status').text().trim()).toBe('Unread');
		});
	});

	describe('delete', () => {
		it('asks before deleting and removes the row once confirmed', () => {
			jest.spyOn(window, 'confirm').mockReturnValue(true);
			respond({ message: 'Entry deleted successfully.' });

			jQuery('.fta-delete-entry').trigger('click');

			expect(ajaxSpy.mock.calls[0][0].data).toMatchObject({
				action: 'fta_delete_entry',
				entry_id: '4',
			});
			expect(jQuery('.fta-entry-row').length).toBe(0);
		});

		it('does nothing when the confirmation is dismissed', () => {
			jest.spyOn(window, 'confirm').mockReturnValue(false);

			jQuery('.fta-delete-entry').trigger('click');

			expect(ajaxSpy).not.toHaveBeenCalled();
			expect(jQuery('.fta-entry-row').length).toBe(1);
		});

		it('keeps the row when the server refuses', () => {
			jest.spyOn(window, 'confirm').mockReturnValue(true);
			respond({ message: 'Failed to delete entry.' }, false);

			jQuery('.fta-delete-entry').trigger('click');

			expect(jQuery('.fta-entry-row').length).toBe(1);
		});
	});

	describe('view', () => {
		it('opens a dialog showing every stored answer', () => {
			jQuery('.fta-view-entry').trigger('click');

			const $modal = jQuery('.fta-entry-modal');

			expect($modal.length).toBe(1);
			expect($modal.attr('role')).toBe('dialog');
			expect($modal.text()).toContain('Ada Lovelace');
			expect($modal.text()).toContain('A late answer');
		});

		it('does not need a network round trip', () => {
			jQuery('.fta-view-entry').trigger('click');

			expect(ajaxSpy).not.toHaveBeenCalled();
		});

		it('closes on Escape', () => {
			jQuery('.fta-view-entry').trigger('click');

			const event = jQuery.Event('keydown', { key: 'Escape' });
			jQuery(document).trigger(event);

			expect(jQuery('.fta-entry-modal').length).toBe(0);
		});

		it('closes on the close control', () => {
			jQuery('.fta-view-entry').trigger('click');
			jQuery('.fta-entry-modal-close').trigger('click');

			expect(jQuery('.fta-entry-modal').length).toBe(0);
		});

		it('never stacks two dialogs', () => {
			jQuery('.fta-view-entry').trigger('click');
			jQuery('.fta-view-entry').trigger('click');

			expect(jQuery('.fta-entry-modal').length).toBe(1);
		});

		// The row's copy is hidden by both the attribute and the matching
		// property. jQuery 3 stopped resetting the property when the
		// attribute is removed, so clearing only the attribute leaves the
		// clone's hidden property true and logs a Migrate deprecation warning
		// on every open.
		it('unhides the cloned answers by property, not just attribute', () => {
			jQuery('.fta-view-entry').trigger('click');

			const clone = jQuery('.fta-entry-modal .fta-entry-details')[0];

			expect(clone.hasAttribute('hidden')).toBe(false);
			expect(clone.hidden).toBe(false);
		});

		it('leaves the row\'s own copy hidden', () => {
			jQuery('.fta-view-entry').trigger('click');

			expect(document.getElementById('fta-entry-details-4').hidden).toBe(true);
		});
	});

	describe('export', () => {
		let clicked;

		beforeEach(() => {
			window.URL.createObjectURL = jest.fn(() => 'blob:formtura');
			window.URL.revokeObjectURL = jest.fn();

			// Anchor clicks are captured rather than performed: letting jsdom
			// follow a blob: href logs a "not implemented" navigation error
			// that has nothing to do with what is under test.
			clicked = [];

			const realCreate = document.createElement.bind(document);

			jest.spyOn(document, 'createElement').mockImplementation(tag => {
				const element = realCreate(tag);

				if ('a' === tag) {
					element.click = () => clicked.push(element);
				}

				return element;
			});
		});

		it('requests the export for the button\'s form', () => {
			respond({ csv: 'Entry ID\n4\n', filename: 'formtura-entries-7-2026-08-15.csv' });

			jQuery('.fta-export-entries').trigger('click');

			expect(ajaxSpy.mock.calls[0][0].data).toMatchObject({
				action: 'fta_export_entries',
				form_id: '7',
			});
		});

		it('hands the CSV to the browser as a named download', () => {
			respond({ csv: 'Entry ID\n4\n', filename: 'formtura-entries-7-2026-08-15.csv' });

			jQuery('.fta-export-entries').trigger('click');

			expect(clicked).toHaveLength(1);
			expect(clicked[0].getAttribute('download')).toBe('formtura-entries-7-2026-08-15.csv');
			expect(window.URL.revokeObjectURL).toHaveBeenCalled();
		});

		it('re-enables the button after a failed export', () => {
			respond({ message: 'This form has no entries to export.' }, false);

			jQuery('.fta-export-entries').trigger('click');

			expect(jQuery('.fta-export-entries').prop('disabled')).toBe(false);
		});
	});

	describe('form selection', () => {
		it('navigates to the chosen form', () => {
			const assign = jest.fn();
			window.FormturaEntries.navigate = assign;

			jQuery('#fta-form-select').val('9').trigger('change');

			expect(assign).toHaveBeenCalledWith('/wp-admin/admin.php?page=formtura-entries&form_id=9');
		});

		it('ignores the placeholder option', () => {
			const assign = jest.fn();
			window.FormturaEntries.navigate = assign;

			jQuery('#fta-form-select').val('').trigger('change');

			expect(assign).not.toHaveBeenCalled();
		});
	});
});
