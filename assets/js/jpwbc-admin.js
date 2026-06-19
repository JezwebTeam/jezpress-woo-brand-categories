/**
 * JezPress Woo Brand Categories Admin JavaScript
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Admin module
	 */
	const JPWBCAdmin = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			// Example: Handle form submission with AJAX
			$(document).on('submit', '.jpwbc-ajax-form', this.handleAjaxSubmit.bind(this));

			// Example: Handle button click
			$(document).on('click', '.jpwbc-action-button', this.handleAction.bind(this));
		},

		/**
		 * Handle AJAX form submission
		 *
		 * @param {Event} e Form submit event
		 */
		handleAjaxSubmit: function(e) {
			e.preventDefault();

			const $form = $(e.currentTarget);
			const $submitBtn = $form.find('button[type="submit"]');
			const originalText = $submitBtn.text();

			// Show loading state
			$submitBtn.prop('disabled', true).text(jpwbcAdmin.i18n.saving);

			$.ajax({
				url: jpwbcAdmin.ajaxUrl,
				type: 'POST',
				data: $form.serialize() + '&action=jpwbc_save_settings&nonce=' + jpwbcAdmin.nonce,
				success: function(response) {
					if (response.success) {
						this.showNotice('success', response.data.message || jpwbcAdmin.i18n.saved);
					} else {
						this.showNotice('error', response.data.message || jpwbcAdmin.i18n.error);
					}
				}.bind(this),
				error: function() {
					this.showNotice('error', jpwbcAdmin.i18n.error);
				}.bind(this),
				complete: function() {
					$submitBtn.prop('disabled', false).text(originalText);
				}
			});
		},

		/**
		 * Handle action button click
		 *
		 * @param {Event} e Click event
		 */
		handleAction: function(e) {
			e.preventDefault();

			const $btn = $(e.currentTarget);
			const action = $btn.data('action');

			if (!action) {
				return;
			}

			// Confirm if needed
			if ($btn.data('confirm') && !confirm($btn.data('confirm'))) {
				return;
			}

			// Add loading state
			$btn.addClass('updating-message');

			$.ajax({
				url: jpwbcAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'jpwbc_' + action,
					nonce: jpwbcAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						this.showNotice('success', response.data.message);
						// Optionally reload or update UI
						if (response.data.reload) {
							window.location.reload();
						}
					} else {
						this.showNotice('error', response.data.message);
					}
				}.bind(this),
				error: function() {
					this.showNotice('error', jpwbcAdmin.i18n.error);
				}.bind(this),
				complete: function() {
					$btn.removeClass('updating-message');
				}
			});
		},

		/**
		 * Show admin notice
		 *
		 * @param {string} type    Notice type (success, error, warning)
		 * @param {string} message Message to display
		 */
		showNotice: function(type, message) {
			const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

			// Remove any existing notices
			$('.wrap > .notice').remove();

			// Add new notice after the page title
			$('.wrap h1').first().after($notice);

			// Initialize WordPress dismiss button
			if (typeof wp !== 'undefined' && wp.updates && wp.updates.addAdminNotice) {
				wp.updates.addAdminNotice({
					id: 'jpwbc-notice',
					className: 'notice-' + type,
					message: message
				});
			}

			// Auto-dismiss after 5 seconds
			setTimeout(function() {
				$notice.fadeOut(300, function() {
					$(this).remove();
				});
			}, 5000);
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		JPWBCAdmin.init();
	});

})(jQuery);
