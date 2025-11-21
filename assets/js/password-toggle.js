/**
 * Password Toggle Functionality
 * Adds show/hide password eye icons to password input fields
 */

(function() {
    'use strict';

    /**
     * Initialize password toggle for a specific password input element
     * @param {HTMLElement} passwordInput - The password input element
     */
    function initPasswordToggle(passwordInput) {
        // Skip if already initialized
        if (passwordInput.hasAttribute('data-password-toggle-initialized')) {
            return;
        }

        // Mark as initialized
        passwordInput.setAttribute('data-password-toggle-initialized', 'true');

        // Wrap the password input if not already wrapped
        if (!passwordInput.parentElement.classList.contains('password-input-wrapper')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'password-input-wrapper';
            passwordInput.parentNode.insertBefore(wrapper, passwordInput);
            wrapper.appendChild(passwordInput);
        }

        // Create the eye icon toggle button
        const toggleIcon = document.createElement('span');
        toggleIcon.className = 'password-toggle-icon';
        toggleIcon.innerHTML = '<i class="bi bi-eye"></i>';
        toggleIcon.setAttribute('role', 'button');
        toggleIcon.setAttribute('tabindex', '0');
        toggleIcon.setAttribute('aria-label', 'Show password');

        // Insert the icon after the password input
        passwordInput.parentNode.appendChild(toggleIcon);

        // Track toggle state
        let isPasswordVisible = false;

        /**
         * Toggle password visibility while preserving cursor position
         */
        function togglePasswordVisibility() {
            isPasswordVisible = !isPasswordVisible;

            // Save the current cursor position
            const cursorPosition = passwordInput.selectionStart;
            const selectionEnd = passwordInput.selectionEnd;

            if (isPasswordVisible) {
                // Show password
                passwordInput.type = 'text';
                toggleIcon.innerHTML = '<i class="bi bi-eye-slash"></i>';
                toggleIcon.setAttribute('aria-label', 'Hide password');
            } else {
                // Hide password
                passwordInput.type = 'password';
                toggleIcon.innerHTML = '<i class="bi bi-eye"></i>';
                toggleIcon.setAttribute('aria-label', 'Show password');
            }

            // Restore cursor position
            // Use setTimeout to ensure the DOM has updated
            setTimeout(function() {
                passwordInput.setSelectionRange(cursorPosition, selectionEnd);
                passwordInput.focus();
            }, 0);
        }

        // Add click event listener
        toggleIcon.addEventListener('click', togglePasswordVisibility);

        // Add keyboard support (Enter and Space)
        toggleIcon.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                togglePasswordVisibility();
            }
        });

        // Add focus to input on icon focus for better UX
        toggleIcon.addEventListener('focus', function() {
            passwordInput.focus();
        });
    }

    /**
     * Initialize all password inputs on the page
     */
    function initAllPasswordToggles() {
        const passwordInputs = document.querySelectorAll('input[type="password"]');
        passwordInputs.forEach(function(input) {
            initPasswordToggle(input);
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllPasswordToggles);
    } else {
        initAllPasswordToggles();
    }

    // Watch for dynamically added password inputs
    if (window.MutationObserver) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        if (node.tagName === 'INPUT' && node.type === 'password') {
                            initPasswordToggle(node);
                        } else if (node.querySelectorAll) {
                            const passwordInputs = node.querySelectorAll('input[type="password"]');
                            passwordInputs.forEach(function(input) {
                                initPasswordToggle(input);
                            });
                        }
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
})();
