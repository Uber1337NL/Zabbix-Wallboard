/**
 * Security Helper Functions
 * Version: 2.0.0
 */

'use strict';

(function(window) {
    const Security = {
        /**
         * Sanitize user input
         * @param {string} input - User input
         * @returns {string} - Sanitized input
         */
        sanitizeInput: function(input) {
            if (typeof input !== 'string') {
                return '';
            }
            
            return input
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#x27;')
                .replace(/\//g, '&#x2F;');
        },

        /**
         * Validate numeric ID
         * @param {*} id - ID to validate
         * @returns {boolean}
         */
        isValidId: function(id) {
            return /^[0-9]+$/.test(String(id));
        },

        /**
         * Generate random nonce for CSP
         * @returns {string}
         */
        generateNonce: function() {
            const array = new Uint8Array(16);
            window.crypto.getRandomValues(array);
            return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
        }
    };

    window.ZabbixSecurity = Security;

})(window);
