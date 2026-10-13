/**
 * Lending System - Main JavaScript File
 */

(function() {
    'use strict';

    window.addEventListener('pageshow', event => {
        if (event.persisted) window.location.reload();
    });

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        showFieldErrors();
        initializeNotificationCenter();
        initializeEventListeners();
        initializeTooltips();
    });

    function addToNotificationCenter(notification) {
        const list = document.getElementById('notification-list');
        if (!list) return;
        document.getElementById('notification-empty')?.remove();
        const item = notification.cloneNode(true);
        item.querySelectorAll('button').forEach(button => button.remove());
        item.removeAttribute('role');
        item.removeAttribute('data-auto-dismiss');
        item.classList.remove('notification');
        list.prepend(item);
        const count = document.getElementById('notification-count');
        count.textContent = list.children.length;
        count.hidden = false;
    }

    function initializeNotificationCenter() {
        const center = document.getElementById('notification-center');
        if (!center) return;
        document.querySelectorAll('#notification-region .notification').forEach(addToNotificationCenter);
        document.getElementById('close-notification-center').addEventListener('click', () => {
            center.open = false;
            center.querySelector('summary').focus();
        });
        document.addEventListener('click', event => {
            if (!center.contains(event.target)) center.open = false;
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && center.open) {
                center.open = false;
                center.querySelector('summary').focus();
            }
        });
    }

    function showFieldErrors() {
        let first = null;
        Object.entries(window.formErrors || {}).forEach(([name, messages]) => {
            const fields = document.getElementsByName(name);
            fields.forEach(field => {
                if (field.type === 'hidden') return;
                field.classList.add('is-invalid');
                field.setAttribute('aria-invalid', 'true');
                const error = document.createElement('div');
                error.className = 'invalid-feedback d-block';
                error.id = field.id + '-error';
                error.textContent = messages[0];
                field.setAttribute('aria-describedby', error.id);
                field.insertAdjacentElement('afterend', error);
                if (!first) first = field;
            });
        });
        if (first) first.focus();
    }

    /**
     * Initialize all event listeners
     */
    function initializeEventListeners() {
        // Menu toggle
        const menuToggle = document.getElementById('menu-toggle');
        if (menuToggle) {
            menuToggle.addEventListener('click', toggleMenu);
        }

        // Form validation
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('invalid', () => {
                showMessage('Please check the highlighted fields before continuing.', 'error');
            }, true);
            form.addEventListener('submit', function(e) {
                validateForm(this, e);
                if (!e.defaultPrevented && this.checkValidity() && this.method.toLowerCase() !== 'get') {
                    setTimeout(() => {
                        if (e.defaultPrevented) return;
                        this.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach(button => button.disabled = true);
                    }, 0);
                }
            });
        });

        document.addEventListener('click', event => {
            const button = event.target.closest('[data-dismiss-notification]');
            if (button) button.closest('.notification').remove();
        });

        // Auto-dismiss successful action feedback; keep errors and reminders visible.
        autoDismissAlerts();

        // Currency input formatting
        formatCurrencyInputs();

        // Confirm delete actions
        setupDeleteConfirmation();
    }

    /**
     * Toggle sidebar menu
     */
    function toggleMenu() {
        const sidebar = document.getElementById('sidebar-wrapper');
        const content = document.getElementById('page-content-wrapper');
        
        if (!sidebar || !content) return;

        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('mobile-open');
            return;
        }

        sidebar.classList.toggle('collapsed');
        content.classList.toggle('sidebar-collapsed');
    }

    /**
     * Validate form before submission
     */
    function validateForm(form, event) {
        // Check required fields
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });

        if (!isValid) {
            event.preventDefault();
            showMessage('Please fill in all required fields.', 'error');
        }
    }

    /**
     * Format currency inputs
     */
    function formatCurrencyInputs() {
        const currencyInputs = document.querySelectorAll('input[type="number"][step="0.01"]');
        
        currencyInputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (/^[0-9]{1,10}(?:[.][0-9]{1,2})?$/.test(this.value)) {
                    const value = parseFloat(this.value);
                    if (!isNaN(value)) {
                        this.value = value.toFixed(2);
                    }
                }
            });
        });
    }

    /**
     * Auto-dismiss alerts after 5 seconds
     */
    function autoDismissAlerts() {
        const alerts = document.querySelectorAll('.notification[data-auto-dismiss]');
        
        alerts.forEach(alert => {
            // Don't auto-dismiss danger alerts (errors)
            if (!alert.classList.contains('alert-danger')) {
                setTimeout(() => {
                    if (!alert.matches(':hover') && !alert.contains(document.activeElement)) alert.remove();
                }, 8000);
            }
        });
    }

    /**
     * Setup delete confirmation dialogs
     */
    function setupDeleteConfirmation() {
        const deleteButtons = document.querySelectorAll('[data-confirm-delete]');
        
        deleteButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    }

    /**
     * Initialize Bootstrap tooltips
     */
    function initializeTooltips() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(tooltipTriggerEl => {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    /**
     * Show message to user
     */
    function showMessage(message, type = 'info') {
        const container = document.getElementById('notification-region');
        if (!container) return;
        type = type === 'error' ? 'danger' : type;
        if (!['success', 'danger', 'warning', 'info'].includes(type)) type = 'info';
        if (Array.from(container.querySelectorAll('[data-message]')).some(item => item.dataset.message === message)) return;
        const alertDiv = document.createElement('div');
        alertDiv.className = `notification alert alert-${type}`;
        alertDiv.dataset.message = message;
        alertDiv.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        const content = document.createElement('div');
        content.textContent = message;
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.setAttribute('data-dismiss-notification', '');
        alertDiv.append(content, close);
        container.prepend(alertDiv);
        addToNotificationCenter(alertDiv);

        // Auto-dismiss non-error messages
        if (type === 'success' || type === 'info') {
            setTimeout(() => {
                if (!alertDiv.matches(':hover') && !alertDiv.contains(document.activeElement)) alertDiv.remove();
            }, 8000);
        }
    }
    window.showMessage = showMessage;

    /**
     * Format number as currency
     */
    window.formatCurrency = function(amount) {
        return '\u20B1' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    };

    /**
     * Format date as readable format
     */
    window.formatDateDisplay = function(dateStr) {
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return new Date(dateStr).toLocaleDateString('en-PH', options);
    };

    /**
     * Confirm modal action
     */
    window.confirmAction = function(message) {
        return confirm(message);
    };

    /**
     * Calculate interest preview
     */
    window.calculateInterestPreview = function(principal) {
        const amount = parseFloat(principal);
        if (isNaN(amount) || amount <= 0) return 0;
        return amount * 0.10; // 10% interest
    };

    /**
     * Update pay-in-full button
     */
    window.updatePayInFull = function(balance) {
        const btn = document.getElementById('payInFullBtn');
        if (btn && balance > 0) {
            btn.textContent = 'Pay Full: ' + formatCurrency(balance);
            btn.disabled = false;
        }
    };

    /**
     * Fetch and cache API data
     */
    window.fetchAPI = function(url, options = {}) {
        return fetch(url, {
            headers: {
                'Accept': 'application/json',
                ...options.headers
            },
            ...options
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('API Error: ' + response.statusText);
            }
            return response.json();
        });
    };

})();
