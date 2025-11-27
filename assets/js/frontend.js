/**
 * Formtura Frontend Scripts
 *
 * Scripts for front-end forms (validation, conditional logic, submission).
 *
 * @package Formtura
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Formtura Frontend object.
	 */
	const FormturaFrontend = {

		/**
		 * Initialize frontend functionality.
		 */
		init() {
			this.bindEvents();
			this.initConditionalLogic();
			this.initCalculations();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents() {
			// Form submission
			$(document).on('submit', '.fta-form', this.handleSubmit);

			// Real-time validation
			$(document).on('blur', '.fta-field-input, .fta-field-textarea, .fta-field-select', this.validateField);

			// File upload
			$(document).on('change', '.fta-file-upload-input', this.handleFileUpload);

			// Character counter
			$(document).on('input', '[data-char-limit]', this.updateCharCounter);
		},

		/**
		 * Handle form submission.
		 */
		handleSubmit(e) {
			e.preventDefault();
			const $form = $(this);
			const formId = $form.data('form-id');
			const $submitButton = $form.find('.fta-submit-button');

			// Clear previous messages
			$form.find('.fta-error-message, .fta-success-message').remove();
			$form.find('.fta-field').removeClass('has-error');
			$form.find('.fta-field-error').remove();

			// Validate all fields
			let isValid = true;
			$form.find('.fta-field-input, .fta-field-textarea, .fta-field-select').each(function() {
				if (!FormturaFrontend.validateField.call(this)) {
					isValid = false;
				}
			});

			if (!isValid) {
				FormturaFrontend.showError($form, 'Please correct the errors below.');
				return;
			}

			// Disable submit button and show loading state
			$submitButton.prop('disabled', true).addClass('loading');

			// Prepare form data
			const formData = new FormData($form[0]);
			formData.append('action', 'fta_submit_form');
			formData.append('form_id', formId);
			formData.append('nonce', formturaFrontend.nonce);

			// Submit via AJAX
			$.ajax({
				url: formturaFrontend.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success(response) {
					if (response.success) {
						FormturaFrontend.showSuccess($form, response.data.message);
						$form[0].reset();

						// Trigger custom event
						$(document).trigger('formtura:submit:success', [formId, response.data]);

						// Redirect if configured
						if (response.data.redirect_url) {
							setTimeout(() => {
								window.location.href = response.data.redirect_url;
							}, 1500);
						}
					} else {
						FormturaFrontend.showError($form, response.data.message);

						// Show field-specific errors
						if (response.data.errors) {
							FormturaFrontend.showFieldErrors($form, response.data.errors);
						}
					}
				},
				error() {
					FormturaFrontend.showError($form, 'An error occurred. Please try again.');
				},
				complete() {
					$submitButton.prop('disabled', false).removeClass('loading');
				}
			});
		},

		/**
		 * Validate a single field.
		 */
		validateField() {
			const $field = $(this);
			const $fieldWrapper = $field.closest('.fta-field');
			const value = $field.val().trim();
			const isRequired = $field.prop('required');
			const fieldType = $field.attr('type') || $field.prop('tagName').toLowerCase();

			// Remove previous error
			$fieldWrapper.removeClass('has-error');
			$fieldWrapper.find('.fta-field-error').remove();

			// Required field validation
			if (isRequired && !value) {
				FormturaFrontend.addFieldError($fieldWrapper, 'This field is required.');
				return false;
			}

			// Email validation
			if (fieldType === 'email' && value) {
				const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
				if (!emailRegex.test(value)) {
					FormturaFrontend.addFieldError($fieldWrapper, 'Please enter a valid email address.');
					return false;
				}
			}

			// URL validation
			if (fieldType === 'url' && value) {
				try {
					new URL(value);
				} catch {
					FormturaFrontend.addFieldError($fieldWrapper, 'Please enter a valid URL.');
					return false;
				}
			}

			// Number validation
			if (fieldType === 'number' && value) {
				const min = parseFloat($field.attr('min'));
				const max = parseFloat($field.attr('max'));
				const numValue = parseFloat(value);

				if (!isNaN(min) && numValue < min) {
					FormturaFrontend.addFieldError($fieldWrapper, `Value must be at least ${min}.`);
					return false;
				}

				if (!isNaN(max) && numValue > max) {
					FormturaFrontend.addFieldError($fieldWrapper, `Value must be at most ${max}.`);
					return false;
				}
			}

			// Character limit validation
			const charLimit = $field.data('char-limit');
			if (charLimit && value.length > charLimit) {
				FormturaFrontend.addFieldError($fieldWrapper, `Maximum ${charLimit} characters allowed.`);
				return false;
			}

			return true;
		},

		/**
		 * Add error to a field.
		 */
		addFieldError($fieldWrapper, message) {
			$fieldWrapper.addClass('has-error');
			$fieldWrapper.append(`<span class="fta-field-error">${message}</span>`);
		},

		/**
		 * Show field-specific errors.
		 */
		showFieldErrors($form, errors) {
			Object.keys(errors).forEach(fieldName => {
				const $field = $form.find(`[name="${fieldName}"]`);
				const $fieldWrapper = $field.closest('.fta-field');
				FormturaFrontend.addFieldError($fieldWrapper, errors[fieldName]);
			});
		},

		/**
		 * Show success message.
		 */
		showSuccess($form, message) {
			const $successMessage = $(`
				<div class="fta-success-message">
					<div class="fta-success-title">Success!</div>
					<div>${message}</div>
				</div>
			`);
			$form.prepend($successMessage);
			$form[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
		},

		/**
		 * Show error message.
		 */
		showError($form, message) {
			const $errorMessage = $(`
				<div class="fta-error-message">
					<div>${message}</div>
				</div>
			`);
			$form.prepend($errorMessage);
			$form[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
		},

		/**
		 * Handle file upload.
		 */
		handleFileUpload(e) {
			const $input = $(this);
			const $uploadArea = $input.closest('.fta-file-upload');
			const files = e.target.files;

			if (files.length > 0) {
				const fileNames = Array.from(files).map(file => file.name).join(', ');
				$uploadArea.find('.fta-file-upload-text').text(fileNames);
			}
		},

		/**
		 * Update character counter.
		 */
		updateCharCounter() {
			const $field = $(this);
			const limit = $field.data('char-limit');
			const current = $field.val().length;
			const $counter = $field.siblings('.fta-char-counter');

			if ($counter.length) {
				$counter.text(`${current} / ${limit}`);
			} else {
				$field.after(`<span class="fta-char-counter">${current} / ${limit}</span>`);
			}
		},

		/**
		 * Initialize conditional logic.
		 */
		initConditionalLogic() {
			$('[data-conditional-logic]').each(function() {
				const $field = $(this);
				const logic = $field.data('conditional-logic');

				if (!logic) return;

				// Watch for changes on trigger fields
				$(logic.triggers).on('change', function() {
					FormturaFrontend.evaluateConditionalLogic($field, logic);
				});

				// Initial evaluation
				FormturaFrontend.evaluateConditionalLogic($field, logic);
			});
		},

		/**
		 * Evaluate conditional logic for a field.
		 */
		evaluateConditionalLogic($field, logic) {
			let show = logic.action === 'show';

			// Evaluate conditions
			const conditionsMet = logic.conditions.every(condition => {
				const $triggerField = $(`[name="${condition.field}"]`);
				const triggerValue = $triggerField.val();

				switch (condition.operator) {
					case 'is':
						return triggerValue === condition.value;
					case 'is_not':
						return triggerValue !== condition.value;
					case 'contains':
						return triggerValue.includes(condition.value);
					case 'greater_than':
						return parseFloat(triggerValue) > parseFloat(condition.value);
					case 'less_than':
						return parseFloat(triggerValue) < parseFloat(condition.value);
					default:
						return false;
				}
			});

			// Show or hide based on logic
			if ((show && conditionsMet) || (!show && !conditionsMet)) {
				$field.slideDown();
			} else {
				$field.slideUp();
			}
		},

		/**
		 * Initialize calculation fields.
		 */
		initCalculations() {
			const self = this;

			$('[data-calculation]').each(function() {
				const $field = $(this);
				const formula = $field.data('calculation');
				const $form = $field.closest('.fta-form');

				if (!formula) return;

				// Extract field references from formula (e.g., {field_abc123})
				const fieldRefs = formula.match(/\{([^}]+)\}/g) || [];
				const fieldIds = fieldRefs.map(ref => ref.replace(/[{}]/g, ''));

				// Bind change events to all referenced fields
				fieldIds.forEach(fieldId => {
					// Find the input by looking for fields with matching name or data-field-id
					$form.find(`[name="${fieldId}"], [data-field-id="${fieldId}"]`).on('input change', function() {
						self.evaluateCalculation($field, formula, $form);
					});
				});

				// Initial calculation
				self.evaluateCalculation($field, formula, $form);
			});
		},

		/**
		 * Evaluate a calculation formula and update the field.
		 */
		evaluateCalculation($field, formula, $form) {
			let expression = formula;

			// Replace field references with their values
			const fieldRefs = formula.match(/\{([^}]+)\}/g) || [];

			fieldRefs.forEach(ref => {
				const fieldId = ref.replace(/[{}]/g, '');
				// Try to find the field by name or data-field-id attribute
				let $sourceField = $form.find(`[name="${fieldId}"]`);
				if (!$sourceField.length) {
					$sourceField = $form.find(`[data-field-id="${fieldId}"]`);
				}

				let value = 0;
				if ($sourceField.length) {
					const rawValue = $sourceField.val();
					value = parseFloat(rawValue) || 0;
				}

				// Replace the field reference with the numeric value
				expression = expression.replace(ref, value.toString());
			});

			// Safely evaluate the mathematical expression
			try {
				// Only allow numbers, operators, parentheses, and whitespace
				const sanitized = expression.replace(/[^0-9+\-*/().\s]/g, '');

				if (sanitized.trim() === '') {
					$field.val(0);
					return;
				}

				// Use Function constructor to evaluate (safer than eval)
				const result = new Function('return ' + sanitized)();

				// Round to 2 decimal places for cleaner display
				const roundedResult = Math.round(result * 100) / 100;

				// Update the field value
				$field.val(isNaN(roundedResult) ? 0 : roundedResult);

				// Trigger change event so other calculations can chain
				$field.trigger('calculation:updated');
			} catch (e) {
				// If evaluation fails, set to 0
				console.warn('Formtura: Calculation error', e);
				$field.val(0);
			}
		}
	};

	// Initialize when document is ready
	$(document).ready(() => {
		FormturaFrontend.init();
	});

})(jQuery);
