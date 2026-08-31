/**
 * Create account screen JavaScript - handles signup.
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var $emailInput = $('#wthb-email');
        var $passwordInput = $('#wthb-password');
        var $siteNameInput = $('#wthb-site-name');
        var $siteUrlInput = $('#wthb-site-url');
        var $signupBtn = $('#wthb-signup-btn');
        var $tokenDisplay = $('#wthb-token-display');
        var $tokenValue = $('#wthb-token-value');
        var $continueBtn = $('#wthb-continue-btn');

        $signupBtn.on('click', function(e) {
            e.preventDefault();

            var email = $emailInput.val().trim();
            var password = $passwordInput.val().trim();
            var siteName = $siteNameInput.val().trim();
            var siteUrl = $siteUrlInput.val().trim();

            if (!email) {
                WTHB.showMessage('Please enter your email address.', 'error');
                return;
            }

            WTHB.clearMessage();
            WTHB.setButtonLoading($signupBtn, true);

            var requestData = {
                email: email,
                site_name: siteName,
                site_url: siteUrl
            };

            if (password) {
                requestData.password = password;
            }

            WTHB.ajax(
                'wthb_signup',
                requestData,
                function(data) {
                    WTHB.setButtonLoading($signupBtn, false);

                    if (data.token) {
                        $tokenValue.text(data.token);
                        $tokenDisplay.show();
                        $signupBtn.hide();
                        WTHB.showMessage(data.message || 'Account created successfully!', 'success');

                        $continueBtn.data('token', data.token);
                    } else {
                        WTHB.showMessage(data.message || 'Account created! Please check your email.', 'success');
                    }
                },
                function(data) {
                    WTHB.setButtonLoading($signupBtn, false);
                    var message = data && data.message ? data.message : 'Signup failed. Please try again.';
                    WTHB.showMessage(message, 'error');
                }
            );
        });

        $continueBtn.on('click', function(e) {
            e.preventDefault();

            var token = $(this).data('token');

            if (!token) {
                WTHB.showMessage('Token not available.', 'error');
                return;
            }

            WTHB.setButtonLoading($continueBtn, true);

            WTHB.ajax(
                'wthb_save_token',
                { token: token },
                function(data) {
                    WTHB.setButtonLoading($continueBtn, false);
                    WTHB.showMessage('Connected successfully! Redirecting...', 'success');

                    setTimeout(function() {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 1000);
                },
                function(data) {
                    WTHB.setButtonLoading($continueBtn, false);
                    var message = data && data.message ? data.message : 'Connection failed. Please try again.';
                    WTHB.showMessage(message, 'error');
                }
            );
        });
    });

})(jQuery);
