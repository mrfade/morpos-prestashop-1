/**
 * MorPOS Payment Plugin - Checkout Integration
 * Handles embedded iframe payment in PrestaShop checkout
 * Compatible with PrestaShop 1.7, 8, and 9
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 */

(function () {
  'use strict';

  // Debug flag - automatically detects PrestaShop debug mode
  // Falls back to false in production
  var DEBUG = (typeof prestashop !== 'undefined' && prestashop.debug === true);

  var MorposCheckout = {
    iframeWrapper: null,
    modal: null,
    modalContent: null,
    closeBtn: null,
    iframe: null,
    loadingDiv: null,
    errorDiv: null,
    errorMessage: null,
    confirmButton: null,
    validateUrl: '',
    isProcessing: false,
    currentBlobUrl: null,
    currentOrderId: null, // Store order ID for retries

    /**
     * Debug logging - only logs when DEBUG flag is true
     */
    _log: function () {
      if (DEBUG && console && console.log) {
        console.log.apply(console, arguments);
      }
    },

    /**
     * Initialize the checkout integration
     */
    init: function () {
      this._log('[MorPOS] Initializing...');
      // Wait for DOM to be ready
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', this.setup.bind(this));
      } else {
        this.setup();
      }
    },

    /**
     * Setup event listeners and element references
     */
    setup: function () {
      this._log('[MorPOS] Setting up...');
      // Get iframe container elements
      this.iframeWrapper = document.getElementById('morpos-iframe-wrapper');

      if (!this.iframeWrapper) {
        this._log('[MorPOS] Iframe wrapper not found - not embedded payment or not on checkout page');
        return;
      }

      this._log('[MorPOS] Iframe wrapper found, form type:', this.iframeWrapper.getAttribute('data-form-type'));

      // Get modal elements
      this.modal = document.getElementById('morpos-modal');
      this.modalContent = document.getElementById('morpos-modal-content');
      this.closeBtn = document.getElementById('morpos-close-btn');
      this.iframe = document.getElementById('morpos-iframe');
      this.loadingDiv = document.getElementById('morpos-loading');
      this.errorDiv = document.getElementById('morpos-error');
      this.errorTitle = document.getElementById('morpos-error-title');
      this.errorMessage = document.getElementById('morpos-error-message');
      this.errorDetails = document.getElementById('morpos-error-details');
      this.validateUrl = this.iframeWrapper.getAttribute('data-validate-url');

      // Setup modal event listeners
      this.setupModalListeners();

      // Method 1: Listen to PrestaShop event (PS 1.7+)
      if (typeof prestashop !== 'undefined' && prestashop.on) {
        this._log('[MorPOS] PrestaShop event API available, registering payment-confirmation event');
        prestashop.on('payment-confirmation', this.handlePaymentConfirmation.bind(this));
      } else {
        this._log('[MorPOS] PrestaShop event API not available');
      }

      // Method 2: Intercept the confirmation button click (works for all versions)
      var self = this;

      // Try immediately and with delays to catch dynamic buttons
      var tryAttachButton = function (delay) {
        setTimeout(function () {
          var confirmButton = document.querySelector('#payment-confirmation button[type="submit"]');
          if (!confirmButton) {
            confirmButton = document.querySelector('.ps-shown-by-js button[type="submit"]');
          }
          if (!confirmButton) {
            confirmButton = document.querySelector('button.btn-primary[type="submit"]');
          }
          if (!confirmButton) {
            // Try to find any submit button in checkout
            var checkoutStep = document.querySelector('#checkout-payment-step, .checkout-step.-current');
            if (checkoutStep) {
              confirmButton = checkoutStep.querySelector('button[type="submit"]');
            }
          }

          if (confirmButton && !confirmButton.dataset.morposAttached) {
            confirmButton.dataset.morposAttached = 'true';
            self.confirmButton = confirmButton;
            self._log('[MorPOS] Found confirm button:', confirmButton);

            // Add click listener with high priority (capture phase)
            confirmButton.addEventListener('click', function (e) {
              self._log('[MorPOS] Button clicked, checking if embedded payment selected...');
              if (self.isEmbeddedPaymentSelected()) {
                self._log('[MorPOS] Embedded payment selected, preventing default');
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                self.initiateEmbeddedPayment();
                return false;
              } else {
                self._log('[MorPOS] Not embedded payment or not selected, allowing default behavior');
              }
            }, true); // Use capture phase to run before PrestaShop handlers
          } else if (!confirmButton) {
            self._log('[MorPOS] Confirm button not found (attempt at ' + delay + 'ms)');
          }
        }, delay);
      };

      tryAttachButton(0);
      tryAttachButton(500);
      tryAttachButton(1000);
      tryAttachButton(2000);

      // Method 3: Listen to form submit as fallback
      setTimeout(function () {
        var confirmationForm = document.querySelector('#payment-confirmation');
        if (!confirmationForm) {
          confirmationForm = document.querySelector('section.checkout-step form');
        }
        if (!confirmationForm) {
          confirmationForm = document.querySelector('#checkout form');
        }

        if (confirmationForm) {
          self._log('[MorPOS] Found confirmation form, attaching submit listener');
          confirmationForm.addEventListener('submit', self.handleFormSubmit.bind(self));
        } else {
          self._log('[MorPOS] Confirmation form not found');
        }
      }, 500);

      // Setup postMessage listener for iframe communication
      window.addEventListener('message', this.handleIframeMessage.bind(this));

      this._log('[MorPOS] Setup complete');
    },

    /**
     * Setup modal event listeners
     */
    setupModalListeners: function () {
      var self = this;

      // Close button click
      if (this.closeBtn) {
        this.closeBtn.addEventListener('click', function () {
          self.closeModal();
        });
      }

      // ESC key to close modal
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && self.modal && self.modal.classList.contains('show')) {
          self.closeModal();
        }
      });

      // Backdrop click to close (optional - uncomment if needed)
      // if (this.modal) {
      //   this.modal.addEventListener('click', function(e) {
      //     if (e.target === self.modal) {
      //       self.closeModal();
      //     }
      //   });
      // }
    },

    /**
     * Open modal and show payment iframe
     */
    openModal: function () {
      this._log('[MorPOS] Opening modal...');

      if (!this.modal) {
        this._log('[MorPOS] Modal element not found');
        return;
      }

      // Show modal
      this.modal.classList.add('show');

      // Lock body scroll
      document.body.classList.add('morpos-modal-open');

      this._log('[MorPOS] Modal opened');
    },

    /**
     * Close modal and cleanup
     */
    closeModal: function () {
      this._log('[MorPOS] Closing modal...');

      if (!this.modal) {
        return;
      }

      // Hide modal
      this.modal.classList.remove('show');

      // Unlock body scroll
      document.body.classList.remove('morpos-modal-open');

      // Clear iframe source
      if (this.iframe) {
        this.iframe.src = 'about:blank';
      }

      // Hide loading and error states
      this.hideLoading();
      this.hideError();

      // Re-enable Place Order button
      this.enableConfirmButton();

      // Reset processing state
      this.isProcessing = false;

      // Clean up blob URL if exists
      if (this.currentBlobUrl) {
        URL.revokeObjectURL(this.currentBlobUrl);
        this.currentBlobUrl = null;
      }

      this._log('[MorPOS] Modal closed');
    },

    /**
     * Check if MorPOS embedded payment is selected
     */
    isEmbeddedPaymentSelected: function () {
      this._log('[MorPOS] Checking if embedded payment selected...');

      // First check if iframe wrapper exists
      if (!this.iframeWrapper) {
        this._log('[MorPOS] Iframe wrapper not available');
        return false;
      }

      // Check if form type is embedded
      var formType = this.iframeWrapper.getAttribute('data-form-type');
      this._log('[MorPOS] Form type:', formType);

      if (formType !== 'embedded') {
        this._log('[MorPOS] Not embedded payment type');
        return false;
      }

      // Find any checked payment option radio button
      var checkedPayment = document.querySelector('input[name="payment-option"]:checked');
      this._log('[MorPOS] Checked payment option:', checkedPayment);

      if (!checkedPayment) {
        this._log('[MorPOS] No payment option selected');
        return false;
      }

      // Check if it's the MorPOS payment option
      var moduleName = checkedPayment.getAttribute('data-module-name');
      this._log('[MorPOS] Selected module name:', moduleName);

      var isMorpos = moduleName === 'morposgateway';
      this._log('[MorPOS] Is MorPOS embedded:', isMorpos);

      return isMorpos;
    },

    /**
     * Handle PrestaShop payment confirmation event
     */
    handlePaymentConfirmation: function (event) {
      this._log('[MorPOS] handlePaymentConfirmation called', event);

      if (!this.isEmbeddedPaymentSelected()) {
        this._log('[MorPOS] Not embedded payment, allowing normal flow');
        return;
      }

      this._log('[MorPOS] Embedded payment detected in confirmation handler, preventing default');

      // Prevent default form submission for embedded payment
      if (event && event.preventDefault) {
        event.preventDefault();
      }
      if (event && event.stopPropagation) {
        event.stopPropagation();
      }

      this.initiateEmbeddedPayment();
    },

    /**
     * Handle form submit (fallback)
     */
    handleFormSubmit: function (event) {
      this._log('[MorPOS] handleFormSubmit called', event);

      if (!this.isEmbeddedPaymentSelected()) {
        this._log('[MorPOS] Not embedded payment, allowing form submit');
        return true;
      }

      this._log('[MorPOS] Embedded payment detected in form submit, preventing default');

      // Prevent default submission for embedded payment
      event.preventDefault();
      event.stopPropagation();
      if (event.stopImmediatePropagation) {
        event.stopImmediatePropagation();
      }

      this.initiateEmbeddedPayment();
      return false;
    },

    /**
     * Initiate embedded payment via AJAX
     */
    initiateEmbeddedPayment: function () {
      this._log('[MorPOS] initiateEmbeddedPayment called');

      if (this.isProcessing) {
        this._log('[MorPOS] Already processing, preventing double submission');
        return;
      }

      this._log('[MorPOS] Starting AJAX payment request to:', this.validateUrl);

      this.isProcessing = true;
      this.hideError();
      this.showLoading();
      this.disableConfirmButton();

      var xhr = new XMLHttpRequest();
      xhr.open('POST', this.validateUrl, true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

      xhr.onload = this.handleAjaxResponse.bind(this, xhr);
      xhr.onerror = this.handleAjaxError.bind(this);

      // Include order_id if this is a retry
      var params = 'ajax=1';
      if (this.currentOrderId) {
        params += '&id_order=' + this.currentOrderId;
        this._log('[MorPOS] Retrying payment for existing order:', this.currentOrderId);
      } else {
        this._log('[MorPOS] Creating new order and initiating payment');
      }

      xhr.send(params);
    },

    /**
     * Handle AJAX response
     */
    handleAjaxResponse: function (xhr) {
      this.hideLoading();
      this.isProcessing = false;

      if (xhr.status === 200) {
        try {
          var response = JSON.parse(xhr.responseText);

          if (response.redirect) {
            // Hosted payment - redirect to gateway
            window.location.href = response.redirect;
          } else if (response.html) {
            // Store order_id if provided (for future retries)
            if (response.order_id) {
              this.currentOrderId = response.order_id;
            }
            // Embedded payment - inject iframe
            this.showIframe(response.html);
          } else if (response.error) {
            // Store order_id if provided (so user can retry with same order)
            if (response.order_id) {
              this.currentOrderId = response.order_id;
              this._log('[MorPOS] Order created but payment init failed. Order ID:', response.order_id);
            }
            // Error occurred - pass full error object
            this.showError({
              title: response.error_title || 'Payment Error',
              message: response.error || response.error_message || 'Payment initialization failed',
              details: response.error_details || response.details || ''
            });
            this.enableConfirmButton();
          } else {
            // Unknown response
            this.showError({
              title: title_payment_error,
              message: text_payment_init_failed,
              details: ''
            });
            this.enableConfirmButton();
          }
        } catch (e) {
          this._log('MorPOS: Failed to parse response', e);
          this.showError({
            title: title_system_error,
            message: text_payment_init_failed,
            details: DEBUG ? e.message : ''
          });
          this.enableConfirmButton();
        }
      } else {
        this._log('MorPOS: HTTP error', xhr.status);
        this.showError({
          title: title_network_error,
          message: text_unable_to_connect,
          details: 'HTTP Status: ' + xhr.status
        });
        this.enableConfirmButton();
      }
    },

    /**
     * Handle AJAX error
     */
    handleAjaxError: function () {
      this._log('MorPOS: Network error');
      this.hideLoading();
      this.showError({
        title: title_network_error,
        message: text_check_connection,
        details: ''
      });
      this.enableConfirmButton();
      this.isProcessing = false;
    },

    /**
     * Show iframe with payment form in modal
     */
    showIframe: function (html) {
      if (!this.iframe) return;

      this._log('[MorPOS] Showing iframe in modal...');

      // Hide loading
      this.hideLoading();

      // Clean up previous blob URL if exists
      if (this.currentBlobUrl) {
        URL.revokeObjectURL(this.currentBlobUrl);
        this.currentBlobUrl = null;
      }

      // Create blob URL for iframe content
      var blob = new Blob([html], { type: 'text/html; charset=utf-8' });
      this.currentBlobUrl = URL.createObjectURL(blob);

      // Set iframe source
      this.iframe.src = this.currentBlobUrl;

      // Open modal
      this.openModal();

      this._log('[MorPOS] Iframe loaded in modal');
    },

    /**
     * Handle postMessage from iframe
     */
    handleIframeMessage: function (event) {
      // Basic origin validation
      if (!event.data || typeof event.data !== 'object') {
        return;
      }

      // Check if message is from our payment iframe
      if (event.data.type === 'MORPOS_RESULT' || event.data.morpos) {
        var status = event.data.status || (event.data.morpos && event.data.morpos.status);
        var redirectUrl = event.data.redirect_url || (event.data.morpos && event.data.morpos.redirect_url);

        this._log('[MorPOS] Received postMessage:', status);

        if (status === 'success') {
          // Payment successful - close modal and redirect
          this._log('[MorPOS] Payment successful, redirecting...');
          this.closeModal();

          if (redirectUrl) {
            window.location.href = redirectUrl;
          } else {
            // Fallback: reload page
            window.location.reload();
          }
        } else if (status === 'failure' || status === 'error') {
          // Payment failed - show error in modal
          this._log('[MorPOS] Payment failed');
          var errorMsg = event.data.message || 'Payment failed. Please try again.';
          var errorDetails = event.data.details || event.data.error_details || '';

          // Hide iframe, show error in modal
          if (this.iframe) {
            this.iframe.style.display = 'none';
          }

          this.showError({
            title: title_payment_failed,
            message: errorMsg,
            details: errorDetails
          });
          this.enableConfirmButton();
        }
      }
    },

    /**
     * Show loading state in modal
     */
    showLoading: function () {
      this._log('[MorPOS] Showing loading state...');

      // Open modal first
      this.openModal();

      // Show loading, hide iframe and error
      if (this.loadingDiv) {
        this.loadingDiv.style.display = 'block';
      }
      if (this.iframe) {
        this.iframe.style.display = 'none';
      }
      if (this.errorDiv) {
        this.errorDiv.style.display = 'none';
      }
    },

    /**
     * Hide loading state
     */
    hideLoading: function () {
      if (this.loadingDiv) {
        this.loadingDiv.style.display = 'none';
      }
      if (this.iframe) {
        this.iframe.style.display = 'block';
      }
    },

    /**
     * Show error message - closes modal and shows error outside
     * @param {string|object} error - Error message string or object with title, message, details
     */
    showError: function (error) {
      // Parse error parameter
      var title = 'Payment Error';
      var message = '';
      var details = '';

      if (typeof error === 'string') {
        message = error;
      } else if (typeof error === 'object') {
        title = error.title || title;
        message = error.message || error.error || '';
        details = error.details || error.error_details || '';
      }

      this._log('[MorPOS] Showing error:', { title: title, message: message, details: details });

      // Close modal if open
      this.closeModal();

      // Show error message
      if (this.errorDiv) {
        // Set title
        if (this.errorTitle) {
          this.errorTitle.textContent = title;
        }

        // Set message
        if (this.errorMessage) {
          this.errorMessage.textContent = message;
        }

        // Set details (if provided)
        if (this.errorDetails) {
          if (details) {
            this.errorDetails.textContent = details;
            this.errorDetails.style.display = 'block';
          } else {
            this.errorDetails.textContent = '';
            this.errorDetails.style.display = 'none';
          }
        }

        this.errorDiv.style.display = 'block';

        // Scroll to error and focus
        setTimeout(function () {
          if (this.errorDiv) {
            this.errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            this.errorDiv.focus();
          }
        }.bind(this), 100);
      }
    },

    /**
     * Hide error message
     */
    hideError: function () {
      if (this.errorDiv) {
        this.errorDiv.style.display = 'none';
      }
    },

    /**
     * Disable confirm button
     */
    disableConfirmButton: function () {
      this.confirmButton = document.querySelector('#payment-confirmation button[type="submit"]');
      if (this.confirmButton) {
        this.confirmButton.disabled = true;
        this.confirmButton.classList.add('disabled');
        // Store original text
        if (!this.confirmButton.dataset.originalText) {
          this.confirmButton.dataset.originalText = this.confirmButton.textContent;
        }
        this.confirmButton.textContent = 'Processing...';
      }
    },

    /**
     * Enable confirm button
     */
    enableConfirmButton: function () {
      if (this.confirmButton) {
        this.confirmButton.disabled = false;
        this.confirmButton.classList.remove('disabled');
        // Restore original text
        if (this.confirmButton.dataset.originalText) {
          this.confirmButton.textContent = this.confirmButton.dataset.originalText;
        }
      }
    }
  };

  // Initialize on page load
  MorposCheckout.init();

})();
