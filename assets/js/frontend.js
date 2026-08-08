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
			this.initSliders();
			this.renderRecaptchaWidgets();
			this.initSignaturePads();
			this.initPayments();
		},

		/**
		 * reCAPTCHA configuration, or null when it is not set up.
		 */
		recaptchaConfig() {
			return (window.formturaFrontend && formturaFrontend.recaptcha) || null;
		},

		/**
		 * Render the v2 checkbox into every widget container on the page.
		 *
		 * Rendering explicitly (rather than letting api.js find .g-recaptcha
		 * divs) is what gives us each widget's ID, which is needed to read its
		 * token and to reset it once a submission has consumed it.
		 */
		renderRecaptchaWidgets() {
			const config = FormturaFrontend.recaptchaConfig();

			if (!config || config.version !== 'v2') {
				return;
			}

			// Google's API may not have arrived yet; formturaRecaptchaOnload
			// calls back into here when it does.
			if (typeof window.grecaptcha === 'undefined' || !grecaptcha.render) {
				return;
			}

			$('[data-fta-recaptcha]').each(function() {
				const $container = $(this);

				// Already rendered - re-rendering would throw.
				if ($container.data('fta-widget-id') !== undefined) {
					return;
				}

				try {
					const widgetId = grecaptcha.render(this, {
						sitekey: $container.data('sitekey') || config.siteKey
					});

					$container.data('fta-widget-id', widgetId);
				} catch (e) {
					console.error('Formtura: could not render reCAPTCHA', e);
				}
			});
		},

		/**
		 * Resolve with the reCAPTCHA token for a form, or with null when
		 * reCAPTCHA is not in play.
		 *
		 * Rejects with a user-facing message when a token cannot be obtained,
		 * so the submission stops instead of being refused by the server.
		 */
		getRecaptchaToken($form) {
			const config = FormturaFrontend.recaptchaConfig();

			if (!config) {
				return Promise.resolve(null);
			}

			const strings = (window.formturaFrontend && formturaFrontend.strings) || {};

			if (typeof window.grecaptcha === 'undefined') {
				return Promise.reject(new Error(strings.recaptchaError || 'reCAPTCHA could not be loaded.'));
			}

			if (config.version === 'v2') {
				const $container = $form.find('[data-fta-recaptcha]').first();
				const widgetId = $container.data('fta-widget-id');

				if (widgetId === undefined) {
					return Promise.reject(new Error(strings.recaptchaError || 'reCAPTCHA could not be loaded.'));
				}

				const token = grecaptcha.getResponse(widgetId);

				// The visitor has not ticked the box yet.
				if (!token) {
					return Promise.reject(new Error(strings.recaptchaMissing || 'Please confirm you are not a robot.'));
				}

				return Promise.resolve(token);
			}

			// v3: mint a fresh token for this submission.
			return new Promise((resolve, reject) => {
				grecaptcha.ready(() => {
					grecaptcha.execute(config.siteKey, { action: config.action })
						.then(resolve)
						.catch(() => {
							reject(new Error(strings.recaptchaError || 'reCAPTCHA could not be loaded.'));
						});
				});
			});
		},

		/**
		 * Clear a consumed v2 token so the next submission gets a fresh one.
		 */
		resetRecaptcha($form) {
			const config = FormturaFrontend.recaptchaConfig();

			if (!config || config.version !== 'v2' || typeof window.grecaptcha === 'undefined') {
				return;
			}

			const widgetId = $form.find('[data-fta-recaptcha]').first().data('fta-widget-id');

			if (widgetId !== undefined) {
				grecaptcha.reset(widgetId);
			}
		},

		/**
		 * Wire up every signature pad on the page.
		 *
		 * Canvas drawing cannot be delegated the way form events are, so
		 * pads are initialized directly; window.formturaInitSignaturePads()
		 * re-runs this for markup inserted after page load. Each pad's
		 * clear function is stashed on the pad's jQuery data so the
		 * submit-success path (clearSignatures) and the Clear button can
		 * share one code path instead of duplicating canvas state that
		 * otherwise only lives inside this closure.
		 */
		initSignaturePads() {
			$('[data-fta-signature]').each(function() {
				const $pad = $(this);

				if ($pad.data('fta-signature-ready')) {
					return;
				}
				$pad.data('fta-signature-ready', true);

				const canvas = $pad.find('.fta-signature-canvas')[0];
				const $value = $pad.find('.fta-signature-value');

				if (!canvas || !canvas.getContext) {
					return;
				}

				const ctx = canvas.getContext('2d');
				let drawing = false;

				const point = (e) => {
					const rect = canvas.getBoundingClientRect();
					// jsdom reports a zero-size rect; guard the scale factors.
					const scaleX = rect.width ? canvas.width / rect.width : 1;
					const scaleY = rect.height ? canvas.height / rect.height : 1;
					return {
						x: (e.clientX - rect.left) * scaleX,
						y: (e.clientY - rect.top) * scaleY,
					};
				};

				// Ends the current stroke. A cancelled stroke (OS touch-cancel,
				// palm/stylus rejection, the window losing focus) still
				// serializes whatever was drawn up to that point rather than
				// discarding it - the alternative would leave the hidden input
				// stale while the canvas shows an in-progress signature, the
				// same disagreement this fix is closing on the success path.
				const endStroke = () => {
					if (!drawing) {
						return;
					}
					drawing = false;
					$value.val(canvas.toDataURL('image/png')).trigger('change');
				};

				const clear = () => {
					drawing = false;
					ctx.clearRect(0, 0, canvas.width, canvas.height);
					$value.val('').trigger('change');
				};

				$pad.data('fta-signature-clear-fn', clear);

				canvas.addEventListener('pointerdown', (e) => {
					e.preventDefault();
					drawing = true;
					if (canvas.setPointerCapture && e.pointerId !== undefined) {
						canvas.setPointerCapture(e.pointerId);
					}
					const p = point(e);
					ctx.beginPath();
					ctx.moveTo(p.x, p.y);
				});

				canvas.addEventListener('pointermove', (e) => {
					if (!drawing) return;
					const p = point(e);
					ctx.lineTo(p.x, p.y);
					ctx.stroke();
				});

				canvas.addEventListener('pointerup', endStroke);

				// The browser's canonical "this pointer stopped generating
				// events" signal - covers OS touch-cancel and palm/stylus
				// rejection. setPointerCapture only guarantees pointerup
				// still targets the canvas when released outside it; it does
				// nothing for an interaction the OS aborts outright.
				canvas.addEventListener('pointercancel', endStroke);

				// A window losing focus mid-stroke (alt-tab, a native dialog)
				// does not reliably fire pointercancel; without this, `drawing`
				// would stay true and a later unrelated pointermove (e.g. the
				// mouse re-entering the canvas with no button held) would
				// silently resume drawing a spurious line.
				window.addEventListener('blur', endStroke);

				$pad.find('.fta-signature-clear').on('click', clear);
			});
		},

		/**
		 * Wipe every signature pad's canvas and hidden input together.
		 *
		 * form.reset() (called on a successful submission) clears the
		 * hidden input but never touches the canvas pixels - without this,
		 * the pad would still show a signature the value no longer has,
		 * which the visitor reads as a bug when a second submission is
		 * blocked as unsigned. Mirrors resetRecaptcha($form).
		 */
		clearSignatures($form) {
			$form.find('[data-fta-signature]').each(function() {
				const clear = $(this).data('fta-signature-clear-fn');

				if (typeof clear === 'function') {
					clear();
				}
			});
		},

		/**
		 * Block submission when a required pad is empty.
		 *
		 * @return {boolean} True when all required pads are signed.
		 */
		validateSignatures($form) {
			const strings = (window.formturaFrontend && formturaFrontend.strings) || {};
			let valid = true;

			$form.find('.fta-signature-value[data-required]').each(function() {
				const $value = $(this);

				if (!$value.val()) {
					FormturaFrontend.addFieldError(
						$value.closest('.fta-field'),
						strings.signatureMissing || 'Please add your signature.'
					);
					valid = false;
				}
			});

			return valid;
		},

		/**
		 * Keep each slider's readout in step with its value.
		 */
		initSliders() {
			$(document).on('input change', '.fta-field-slider', function() {
				const $slider = $(this);
				const template = $slider.data('value-display') || '{value}';

				$slider
					.closest('.fta-slider-container')
					.find('.fta-slider-value')
					.text(String(template).replace('{value}', $slider.val()));
			});
		},

		/**
		 * Keep every form's total display in step with its selections.
		 *
		 * Display-side convenience only: the server recomputes the amount
		 * from the form definition on submission and ignores this value.
		 */
		initPayments() {
			$(document).on('change', '.fta-payment-input, .fta-payment-select', function() {
				FormturaFrontend.recalculateTotal($(this).closest('.fta-form'));
			});

			// Initial state: single items count before any interaction.
			FormturaFrontend.recalculateAllTotals();
		},

		/**
		 * Recompute the total display for every form currently on the page.
		 *
		 * Separated from initPayments() so window.formturaRecalculateTotals()
		 * can re-run the computation for markup inserted after page load
		 * without re-binding the delegated change handler - that handler
		 * lives on document and already covers any matching input added
		 * later, so binding it again would fire it N times per change.
		 */
		recalculateAllTotals() {
			$('.fta-form').each(function() {
				const $form = $(this);
				if ($form.find('.fta-field-total').length) {
					FormturaFrontend.recalculateTotal($form);
				}
			});
		},

		/**
		 * Collect the currently selected payment items in a form.
		 *
		 * @return {Array<{label: string, price: number}>}
		 */
		selectedPaymentItems($form) {
			const items = [];

			$form.find('.fta-payment-input').each(function() {
				const $input = $(this);
				const type = ($input.attr('type') || '').toLowerCase();

				if ((type === 'checkbox' || type === 'radio') && !$input.prop('checked')) {
					return;
				}

				items.push({
					label: $input.data('item-label') || '',
					price: parseFloat($input.data('price')) || 0,
				});
			});

			$form.find('.fta-payment-select').each(function() {
				const $option = $(this).find('option:selected');
				const price = parseFloat($option.data('price')) || 0;

				if ($option.val()) {
					items.push({ label: $option.data('item-label') || $option.text(), price });
				}
			});

			return items;
		},

		/**
		 * Format an amount with the localized currency symbol.
		 */
		formatPrice(amount) {
			const symbol = (window.formturaFrontend && formturaFrontend.currency && formturaFrontend.currency.symbol) || '$';
			return symbol + amount.toFixed(2);
		},

		/**
		 * Recompute and render a form's total display and summary.
		 */
		recalculateTotal($form) {
			const $total = $form.find('.fta-field-total');

			if (!$total.length) {
				return;
			}

			const items = FormturaFrontend.selectedPaymentItems($form);
			let amount = items.reduce((sum, item) => sum + item.price, 0);

			// A validated coupon (set by the coupon apply flow) discounts the
			// displayed amount; the server re-validates independently.
			const coupon = $form.data('ftaCoupon');
			if (coupon) {
				amount -= coupon.type === 'percent' ? (amount * coupon.value) / 100 : coupon.value;
			}
			amount = Math.max(0, Math.round(amount * 100) / 100);

			$total.find('.fta-total-amount').text(FormturaFrontend.formatPrice(amount));

			const $summary = $total.find('.fta-order-summary-body');
			if ($summary.length) {
				$summary.empty();
				items.forEach(item => {
					$('<tr>')
						.append($('<td>').text(item.label))
						.append($('<td>').text(FormturaFrontend.formatPrice(item.price)))
						.appendTo($summary);
				});
			}
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents() {
			// Form submission
			$(document).on('submit', '.fta-form', this.handleSubmit);

			// Real-time validation
			$(document).on('blur', '.fta-field-input, .fta-field-textarea, .fta-field-select', this.validateField);

			// File upload - change event
			$(document).on('change', '.fta-file-upload-input, .fta-file-upload-input-compact', this.handleFileUpload);

			// File upload - click to trigger file input (fallback for label issues)
			$(document).on('click', '.fta-file-upload', this.handleFileUploadClick);

			// File upload - drag and drop events
			$(document).on('dragover dragenter', '.fta-file-upload', this.handleDragOver);
			$(document).on('dragleave dragend', '.fta-file-upload', this.handleDragLeave);
			$(document).on('drop', '.fta-file-upload', this.handleDrop);

			// Character counter
			$(document).on('input', '[data-char-limit]', this.updateCharCounter);

			// Coupon apply
			$(document).on('click', '.fta-coupon-apply', this.handleCouponApply);
		},

		/**
		 * Validate a coupon code over AJAX and apply it to the display.
		 *
		 * The submission re-validates the code server-side regardless; this
		 * only keeps the displayed total honest without ever shipping the
		 * code list to the page.
		 */
		handleCouponApply() {
			const $button = $(this);
			const $wrap = $button.closest('.fta-coupon');
			const $form = $button.closest('.fta-form');
			const $status = $button.closest('.fta-field').find('.fta-coupon-status');
			const strings = (window.formturaFrontend && formturaFrontend.strings) || {};
			const code = String($wrap.find('.fta-coupon-input').val() || '').trim();

			if (!code) {
				return;
			}

			$button.prop('disabled', true);

			$.ajax({
				url: formturaFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fta_validate_coupon',
					nonce: formturaFrontend.nonce,
					form_id: $form.data('form-id'),
					field_id: $wrap.data('field-id'),
					code,
				},
				success(response) {
					if (response.success) {
						$form.data('ftaCoupon', {
							type: response.data.type,
							value: parseFloat(response.data.value) || 0,
						});
						$status.text(strings.couponApplied || 'Coupon applied.');
					} else {
						$form.removeData('ftaCoupon');
						$status.text((response.data && response.data.message) || strings.couponInvalid || 'This coupon code is not valid.');
					}
					FormturaFrontend.recalculateTotal($form);
				},
				error() {
					// A transport/network failure (including a stale nonce on
					// cached HTML, which check_ajax_referer() 403s) is not the
					// same thing as a wrong code - telling the visitor their
					// code is invalid here would be false. Also clear any
					// previously-applied discount so the status message and
					// the displayed total never disagree.
					$form.removeData('ftaCoupon');
					$status.text(strings.error || 'An error occurred. Please try again.');
					FormturaFrontend.recalculateTotal($form);
				},
				complete() {
					$button.prop('disabled', false);
				}
			});
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

			if (!FormturaFrontend.validateSignatures($form)) {
				FormturaFrontend.showError($form, 'Please correct the errors below.');
				return;
			}

			// Disable submit button and show loading state
			$submitButton.prop('disabled', true).addClass('loading');

			// The token has to be in hand before the request goes out, so the
			// submission waits on it.
			FormturaFrontend.getRecaptchaToken($form)
				.then(token => {
					FormturaFrontend.sendSubmission($form, formId, $submitButton, token);
				})
				.catch(error => {
					FormturaFrontend.showError($form, error.message);
					$submitButton.prop('disabled', false).removeClass('loading');
				});
		},

		/**
		 * Post a validated form, with its reCAPTCHA token when there is one.
		 */
		sendSubmission($form, formId, $submitButton, recaptchaToken) {
			// Prepare form data
			const formData = new FormData($form[0]);
			formData.append('action', 'fta_submit_form');
			formData.append('form_id', formId);
			formData.append('nonce', formturaFrontend.nonce);

			if (recaptchaToken) {
				// set(), not append(): the v2 widget already put its textarea in
				// the form, and only one value should reach the server.
				formData.set('g-recaptcha-response', recaptchaToken);
			}

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

						// form.reset() does not clear the widget, and the token
						// is single-use, so a second submission needs a new one.
						FormturaFrontend.resetRecaptcha($form);

						// form.reset() clears the hidden input but leaves any
						// drawn strokes on the canvas - wipe both together so
						// they can never disagree on a second submission.
						FormturaFrontend.clearSignatures($form);

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

						// A rejected token is spent either way.
						if (response.data.recaptcha) {
							FormturaFrontend.resetRecaptcha($form);
						}

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
		 * Handle file upload click event (fallback for label issues).
		 */
		handleFileUploadClick(e) {
			// Don't trigger if clicking directly on the input
			if ($(e.target).is('input[type="file"]')) {
				return;
			}

			const $uploadArea = $(this);

			let $input = $uploadArea.find('.fta-file-upload-input');

			if (!$input.length) {
				$input = $uploadArea.find('.fta-file-upload-input-compact');
			}
			if (!$input.length) {
				$input = $uploadArea.find('input[type="file"]');
			}

			if ($input.length) {
				// Programmatically trigger the file input click
				$input[0].click();
			}
		},

		/**
		 * Handle file upload change event.
		 */
		handleFileUpload(e) {
			const $input = $(this);
			const files = e.target.files;

			if (files.length > 0) {
				FormturaFrontend.updateFileUploadUI($input, files);
				FormturaFrontend.validateFileUpload($input, files);
			}
		},

		/**
		 * Handle drag over event for file upload.
		 */
		handleDragOver(e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).addClass('fta-file-upload-dragover');
		},

		/**
		 * Handle drag leave event for file upload.
		 */
		handleDragLeave(e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).removeClass('fta-file-upload-dragover');
		},

		/**
		 * Handle drop event for file upload.
		 */
		handleDrop(e) {
			e.preventDefault();
			e.stopPropagation();

			const $uploadArea = $(this);
			$uploadArea.removeClass('fta-file-upload-dragover');

			// Find the file input - could be either class
			let $input = $uploadArea.find('.fta-file-upload-input');
			if (!$input.length) {
				$input = $uploadArea.find('.fta-file-upload-input-compact');
			}
			// Also check if the input is a direct child of the upload area
			if (!$input.length) {
				$input = $uploadArea.find('input[type="file"]');
			}

			const files = e.originalEvent.dataTransfer.files;

			if (files.length > 0 && $input.length > 0) {
				// Create a new DataTransfer to assign files to the input
				const dataTransfer = new DataTransfer();
				const allowMultiple = $input.prop('multiple');

				if (allowMultiple) {
					for (let i = 0; i < files.length; i++) {
						dataTransfer.items.add(files[i]);
					}
				} else {
					dataTransfer.items.add(files[0]);
				}

				$input[0].files = dataTransfer.files;

				// Trigger change event to ensure handlers are called
				$input.trigger('change');

				FormturaFrontend.updateFileUploadUI($input, dataTransfer.files);
				FormturaFrontend.validateFileUpload($input, dataTransfer.files);
			}
		},

		/**
		 * Update file upload UI with selected files.
		 */
		updateFileUploadUI($input, files) {
			const $uploadArea = $input.closest('.fta-file-upload');
			const $compactArea = $input.closest('.fta-file-upload-compact');
			const $previewArea = $uploadArea.siblings('.fta-file-upload-preview');
			const fileNames = Array.from(files).map(file => file.name);

			if ($uploadArea.length) {
				// Dropzone style - update text
				$uploadArea.find('.fta-file-upload-text').text(fileNames.join(', '));
				$uploadArea.addClass('fta-file-upload-has-files');

				// Show preview for images
				if ($previewArea.length) {
					$previewArea.empty();
					Array.from(files).forEach(file => {
						if (file.type.startsWith('image/')) {
							const reader = new FileReader();
							reader.onload = function(e) {
								$previewArea.append(`
									<div class="fta-file-preview-item">
										<img src="${e.target.result}" alt="${file.name}" />
										<span class="fta-file-preview-name">${file.name}</span>
									</div>
								`);
							};
							reader.readAsDataURL(file);
						} else {
							$previewArea.append(`
								<div class="fta-file-preview-item">
									<span class="fta-file-preview-icon">📄</span>
									<span class="fta-file-preview-name">${file.name}</span>
								</div>
							`);
						}
					});
				}
			}

			if ($compactArea.length) {
				// Compact style - update filename display
				$compactArea.find('.fta-file-upload-filename').text(fileNames.join(', '));
			}
		},

		/**
		 * Validate file upload (size limits).
		 */
		validateFileUpload($input, files) {
			const $fieldWrapper = $input.closest('.fta-field');
			const minSize = parseFloat($input.data('min-size')) || 0;
			const maxSize = parseFloat($input.data('max-size')) || 256;

			// Remove previous errors
			$fieldWrapper.removeClass('has-error');
			$fieldWrapper.find('.fta-field-error').remove();

			for (const file of files) {
				const fileSizeMB = file.size / (1024 * 1024);

				if (minSize > 0 && fileSizeMB < minSize) {
					FormturaFrontend.addFieldError($fieldWrapper, `File "${file.name}" is too small. Minimum size is ${minSize}MB.`);
					return false;
				}

				if (maxSize > 0 && fileSizeMB > maxSize) {
					FormturaFrontend.addFieldError($fieldWrapper, `File "${file.name}" is too large. Maximum size is ${maxSize}MB.`);
					return false;
				}
			}

			return true;
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

	// Called by Google's api.js once it has loaded (v2, explicit rendering).
	// The API script depends on this file, so this is defined before it runs.
	window.formturaRecaptchaOnload = function() {
		$(document).ready(() => {
			FormturaFrontend.renderRecaptchaWidgets();
		});
	};

	// Let integrations render widgets in markup added after page load.
	window.formturaRenderRecaptcha = function() {
		FormturaFrontend.renderRecaptchaWidgets();
	};

	// Let integrations initialize pads in markup added after page load.
	window.formturaInitSignaturePads = function() {
		FormturaFrontend.initSignaturePads();
	};

	// Let integrations recompute totals for markup added after page load.
	// Unlike the two hooks above, there is no per-element ready-guard here:
	// initializing a signature pad or reCAPTCHA widget twice is harmful
	// (duplicate canvases, duplicate widgets), but recomputing a total is
	// idempotent - it just overwrites the same text with the same value -
	// so a guard would only add bookkeeping with nothing to protect against.
	window.formturaRecalculateTotals = function() {
		FormturaFrontend.recalculateAllTotals();
	};

})(jQuery);
