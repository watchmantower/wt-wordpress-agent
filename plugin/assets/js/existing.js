/**
 * Existing account screen JavaScript - handles token submission.
 */

(function ($) {
  "use strict";

  $(document).ready(function () {
    var $tokenInput = $("#wthb-token");
    var $connectBtn = $("#wthb-connect-btn");

    $connectBtn.on("click", function (e) {
      e.preventDefault();

      var token = $tokenInput.val().trim();

      if (!token) {
        WTHB.showMessage("Please enter a register token.", "error");
        return;
      }

      WTHB.clearMessage();
      WTHB.setButtonLoading($connectBtn, true);

      WTHB.ajax(
        "wthb_save_token",
        { token: token },
        function (data) {
          WTHB.setButtonLoading($connectBtn, false);
          WTHB.showMessage("Connection successful! Redirecting...", "success");

          setTimeout(function () {
            if (data.redirect) {
              window.location.href = data.redirect;
            } else {
              window.location.reload();
            }
          }, 1000);
        },
        function (data) {
          WTHB.setButtonLoading($connectBtn, false);
          var message =
            data && data.message
              ? data.message
              : "Connection failed. Please try again.";
          WTHB.showMessage(message, "error");
        },
      );
    });

    $tokenInput.on("keypress", function (e) {
      if (e.which === 13) {
        e.preventDefault();
        $connectBtn.click();
      }
    });
  });
})(jQuery);
