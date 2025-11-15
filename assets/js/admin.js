/**
 * Formtura Admin Scripts
 * 
 * General admin area JavaScript for settings pages and admin UI.
 *
 * @package Formtura
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Formtura Admin object.
	 */
	const FormturaAdmin = {
		
		/**
		 * Initialize admin functionality.
		 */
		init() {
			this.bindEvents();
			this.initTabs();
			this.initTooltips();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents() {
			// Test email button
			$(document).on('click', '.fta-test-email', this.sendTestEmail);
			
			// Form settings save
			$(document).on('click', '.fta-save-settings', this.saveSettings);
			
			// Toggle visibility
			$(document).on('change', '.fta-toggle-visibility', this.toggleVisibility);
		},

		/**
		 * Initialize tab navigation.
		 */
		initTabs() {
			$('.fta-tabs').each(function() {
				const $tabs = $(this);
				const $tabButtons = $tabs.find('.fta-tab-button');
				const $tabPanels = $tabs.find('.fta-tab-panel');

				$tabButtons.on('click', function(e) {
					e.preventDefault();
					const targetPanel = $(this).data('tab');

					// Update active states
					$tabButtons.removeClass('active');
					$(this).addClass('active');

					$tabPanels.removeClass('active');
					$(`#${targetPanel}`).addClass('active');

					// Update URL hash
					window.location.hash = targetPanel;
				});

				// Activate tab from URL hash
				const hash = window.location.hash.substring(1);
				if (hash) {
					$tabButtons.filter(`[data-tab="${hash}"]`).trigger('click');
				}
			});
		},

		/**
		 * Initialize tooltips.
		 */
		initTooltips() {
			$('[data-tooltip]').each(function() {
				$(this).attr('title', $(this).data('tooltip'));
			});
		},

		/**
		 * Send test email.
		 */
		sendTestEmail(e) {
			e.preventDefault();
			const $button = $(this);
			const $form = $button.closest('form');
			const email = $form.find('input[name="test_email"]').val();

			if (!email) {
				FormturaAdmin.showNotice('Please enter an email address.', 'error');
				return;
			}

			$button.prop('disabled', true).text('Sending...');

			$.ajax({
				url: formturaAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fta_send_test_email',
					nonce: formturaAdmin.nonce,
					email: email,
					smtp_settings: $form.serialize()
				},
				success(response) {
					if (response.success) {
						FormturaAdmin.showNotice(response.data.message, 'success');
					} else {
						FormturaAdmin.showNotice(response.data.message, 'error');
					}
				},
				error() {
					FormturaAdmin.showNotice('An error occurred. Please try again.', 'error');
				},
				complete() {
					$button.prop('disabled', false).text('Send Test Email');
				}
			});
		},

		/**
		 * Save settings via AJAX.
		 */
		saveSettings(e) {
			e.preventDefault();
			const $button = $(this);
			const $form = $button.closest('form');

			$button.prop('disabled', true).text('Saving...');

			$.ajax({
				url: formturaAdmin.ajaxUrl,
				type: 'POST',
				data: $form.serialize() + '&action=fta_save_settings&nonce=' + formturaAdmin.nonce,
				success(response) {
					if (response.success) {
						FormturaAdmin.showNotice(response.data.message, 'success');
					} else {
						FormturaAdmin.showNotice(response.data.message, 'error');
					}
				},
				error() {
					FormturaAdmin.showNotice('An error occurred. Please try again.', 'error');
				},
				complete() {
					$button.prop('disabled', false).text('Save Settings');
				}
			});
		},

		/**
		 * Toggle visibility of dependent fields.
		 */
		toggleVisibility(e) {
			const $toggle = $(this);
			const target = $toggle.data('toggle-target');
			const $target = $(target);

			if ($toggle.is(':checked')) {
				$target.slideDown();
			} else {
				$target.slideUp();
			}
		},

		/**
		 * Show admin notice.
		 */
		showNotice(message, type = 'info') {
			const $notice = $('<div>', {
				class: `fta-notice fta-notice-${type}`,
				html: `<p>${message}</p>`
			});

			$('.fta-admin-page').prepend($notice);

			setTimeout(() => {
				$notice.fadeOut(() => $notice.remove());
			}, 5000);
		}
	};

	// Initialize when document is ready
	$(document).ready(() => {
		FormturaAdmin.init();
	});

})(jQuery);
