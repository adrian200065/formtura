/**
 * Formtura Entries Screen
 *
 * Client-side behaviour for the entry management table: mark as read/unread,
 * view an entry's full answers, delete an entry, and export the form.
 *
 * The screen rendered all four controls long before anything was bound to
 * them, so each was a labelled link that did nothing when clicked.
 *
 * @package Formtura
 * @since 1.0.6
 */

(function($) {
	'use strict';

	/**
	 * Formtura Entries object.
	 */
	const FormturaEntries = {

		/**
		 * Initialize the entries screen.
		 */
		init() {
			this.bindEvents();
		},

		/**
		 * Localized configuration, with defaults so a missing localization
		 * cannot turn every handler into a TypeError.
		 */
		config() {
			const data = window.formturaEntries || {};

			return {
				ajaxUrl: data.ajaxUrl || '',
				nonce: data.nonce || '',
				entriesUrl: data.entriesUrl || '',
				strings: data.strings || {}
			};
		},

		/**
		 * A localized string, falling back to its key-specific default.
		 */
		text(key, fallback) {
			const strings = FormturaEntries.config().strings;

			return strings[key] || fallback;
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents() {
			$(document).on('change', '#fta-form-select', FormturaEntries.selectForm);
			$(document).on('click', '.fta-mark-read', FormturaEntries.markRead);
			$(document).on('click', '.fta-view-entry', FormturaEntries.viewEntry);
			$(document).on('click', '.fta-delete-entry', FormturaEntries.deleteEntry);
			$(document).on('click', '.fta-export-entries', FormturaEntries.exportEntries);
			$(document).on('click', '.fta-entry-modal-close, .fta-entry-modal-backdrop', FormturaEntries.closeModal);
			$(document).on('keydown', FormturaEntries.onKeydown);
		},

		/**
		 * Leave the page. Isolated so tests can observe navigation without a
		 * jsdom "not implemented" error.
		 */
		navigate(url) {
			window.location.href = url;
		},

		/**
		 * Show a message, reusing the shared admin notice when it is present.
		 */
		notify(message, type) {
			if (window.FormturaAdmin && window.FormturaAdmin.showNotice) {
				window.FormturaAdmin.showNotice(message, type);
			}
		},

		/**
		 * The message carried by an AJAX response, if any.
		 */
		messageFrom(response, fallback) {
			return (response && response.data && response.data.message) || fallback;
		},

		/**
		 * Load the entries for the selected form.
		 */
		selectForm() {
			const formId = $(this).val();

			if (!formId) {
				return;
			}

			FormturaEntries.navigate(FormturaEntries.config().entriesUrl + '&form_id=' + encodeURIComponent(formId));
		},

		/**
		 * Toggle an entry between read and unread.
		 */
		markRead(e) {
			e.preventDefault();

			const $link = $(this);
			const entryId = String($link.data('entry-id'));

			// The server is the authority on the resulting state; this only
			// says which way the toggle was pointing when it was clicked.
			const isRead = '1' === String($link.attr('data-is-read')) ? 0 : 1;

			$.ajax({
				url: FormturaEntries.config().ajaxUrl,
				type: 'POST',
				data: {
					action: 'fta_mark_entry_read',
					nonce: FormturaEntries.config().nonce,
					entry_id: entryId,
					is_read: isRead
				},
				success(response) {
					if (!response.success) {
						FormturaEntries.notify(
							FormturaEntries.messageFrom(response, FormturaEntries.text('error', 'An error occurred.')),
							'error'
						);

						return;
					}

					// Redrawn from what the server confirmed rather than from
					// the optimistic value, so a row never shows a status the
					// database does not hold.
					FormturaEntries.redrawStatus($link, !!response.data.is_read);
				},
				error() {
					FormturaEntries.notify(FormturaEntries.text('error', 'An error occurred.'), 'error');
				}
			});
		},

		/**
		 * Repaint a row for its confirmed read status.
		 */
		redrawStatus($link, isRead) {
			const $row = $link.closest('.fta-entry-row');

			$row.toggleClass('fta-entry-read', isRead).toggleClass('fta-entry-unread', !isRead);

			$row.find('.fta-status')
				.removeClass('fta-status-read fta-status-unread')
				.addClass(isRead ? 'fta-status-read' : 'fta-status-unread')
				.text(isRead ? FormturaEntries.text('read', 'Read') : FormturaEntries.text('unread', 'Unread'));

			$link
				.attr('data-is-read', isRead ? '1' : '0')
				.text(
					isRead
						? FormturaEntries.text('markUnread', 'Mark as Unread')
						: FormturaEntries.text('markRead', 'Mark as Read')
				);
		},

		/**
		 * Open the detail dialog for an entry.
		 */
		viewEntry(e) {
			e.preventDefault();

			const entryId = String($(this).data('entry-id'));
			const $details = $('#fta-entry-details-' + entryId);

			if (!$details.length) {
				return;
			}

			FormturaEntries.openModal(entryId, $details);
		},

		/**
		 * Build and show the detail dialog.
		 *
		 * The answers are cloned from markup the server already escaped, so
		 * nothing here re-inserts visitor-supplied text as HTML.
		 */
		openModal(entryId, $details) {
			FormturaEntries.closeModal();

			FormturaEntries.lastFocused = document.activeElement;

			const titleId = 'fta-entry-modal-title-' + entryId;
			const title = FormturaEntries.text('entryTitle', 'Entry #%s').replace('%s', entryId);

			const $modal = $('<div>', {
				class: 'fta-entry-modal',
				role: 'dialog',
				'aria-modal': 'true',
				'aria-labelledby': titleId
			});

			const $panel = $('<div>', { class: 'fta-entry-modal-panel' });
			const $header = $('<div>', { class: 'fta-entry-modal-header' });

			$header.append($('<h2>', { id: titleId, text: title }));
			$header.append(
				$('<button>', {
					type: 'button',
					class: 'fta-entry-modal-close',
					'aria-label': FormturaEntries.text('close', 'Close'),
					text: '×'
				})
			);

			const $body = $('<div>', { class: 'fta-entry-modal-body' });

			const $answers = $details.clone().removeAttr('id');

			// Unhidden through the DOM rather than jQuery's removeAttr():
			// jQuery 3 stopped syncing boolean properties there, so jQuery
			// Migrate logs "removeAttr no longer sets boolean properties:
			// hidden" every time a dialog opens. removeAttribute() clears the
			// attribute and its reflected property together, and says nothing.
			$answers[0].removeAttribute('hidden');

			$body.append($answers);

			$panel.append($header).append($body);

			$modal.append($('<div>', { class: 'fta-entry-modal-backdrop' })).append($panel);

			$('body').append($modal).addClass('fta-modal-open');

			$modal.find('.fta-entry-modal-close').trigger('focus');
		},

		/**
		 * Close the detail dialog, if one is open.
		 */
		closeModal() {
			const $modal = $('.fta-entry-modal');

			if (!$modal.length) {
				return;
			}

			$modal.remove();
			$('body').removeClass('fta-modal-open');

			if (FormturaEntries.lastFocused && FormturaEntries.lastFocused.focus) {
				FormturaEntries.lastFocused.focus();
			}

			FormturaEntries.lastFocused = null;
		},

		/**
		 * Escape closes the dialog.
		 */
		onKeydown(e) {
			if ('Escape' === e.key || 'Esc' === e.key) {
				FormturaEntries.closeModal();
			}
		},

		/**
		 * Delete an entry, and its stored files with it.
		 */
		deleteEntry(e) {
			e.preventDefault();

			const $link = $(this);
			const entryId = String($link.data('entry-id'));

			// Deleting an entry also removes its uploads and signatures from
			// the private vault, and neither is recoverable.
			if (!window.confirm(FormturaEntries.text('confirmDelete', 'Delete this entry?'))) {
				return;
			}

			$.ajax({
				url: FormturaEntries.config().ajaxUrl,
				type: 'POST',
				data: {
					action: 'fta_delete_entry',
					nonce: FormturaEntries.config().nonce,
					entry_id: entryId
				},
				success(response) {
					if (!response.success) {
						FormturaEntries.notify(
							FormturaEntries.messageFrom(response, FormturaEntries.text('error', 'An error occurred.')),
							'error'
						);

						return;
					}

					$link.closest('.fta-entry-row').remove();

					FormturaEntries.notify(
						FormturaEntries.messageFrom(response, FormturaEntries.text('deleted', 'Entry deleted.')),
						'success'
					);
				},
				error() {
					FormturaEntries.notify(FormturaEntries.text('error', 'An error occurred.'), 'error');
				}
			});
		},

		/**
		 * Export every entry for a form as CSV.
		 */
		exportEntries(e) {
			e.preventDefault();

			const $button = $(this);
			const formId = String($button.data('form-id'));

			$button.prop('disabled', true);

			$.ajax({
				url: FormturaEntries.config().ajaxUrl,
				type: 'POST',
				data: {
					action: 'fta_export_entries',
					nonce: FormturaEntries.config().nonce,
					form_id: formId
				},
				success(response) {
					if (!response.success) {
						FormturaEntries.notify(
							FormturaEntries.messageFrom(response, FormturaEntries.text('error', 'An error occurred.')),
							'error'
						);

						return;
					}

					FormturaEntries.download(response.data.csv, response.data.filename);
				},
				error() {
					FormturaEntries.notify(FormturaEntries.text('error', 'An error occurred.'), 'error');
				},
				complete() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Hand CSV text to the browser as a file.
		 *
		 * The leading byte order mark is what makes Excel read the file as
		 * UTF-8; without it any non-ASCII answer opens as mojibake.
		 */
		download(csv, filename) {
			const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
			const url = window.URL.createObjectURL(blob);
			const link = document.createElement('a');

			link.href = url;
			link.download = filename || 'formtura-entries.csv';
			link.style.display = 'none';

			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);

			window.URL.revokeObjectURL(url);
		}
	};

	// Exposed so the screen's behaviour can be driven from tests and extended
	// by integrations.
	window.FormturaEntries = FormturaEntries;

	$(document).ready(() => {
		FormturaEntries.init();
	});

})(jQuery);
