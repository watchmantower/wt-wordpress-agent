/**
 * Shared utility functions for Watchman Tower admin interface.
 */

(function ($) {
  "use strict";

  window.WTHB = window.WTHB || {};

  /**
   * Show a status message.
   */
  WTHB.showMessage = function (message, type) {
    type = type || "info";
    var classes = "notice notice-" + type + " inline";
    var html = '<div class="' + classes + '"><p>' + message + "</p></div>";
    $("#wthb-status").html(html);
  };

  /**
   * Clear status message.
   */
  WTHB.clearMessage = function () {
    $("#wthb-status").html("");
  };

  /**
   * Show loading state on button.
   */
  WTHB.setButtonLoading = function ($button, loading) {
    if (loading) {
      $button.prop("disabled", true).addClass("wthb-loading");
      var originalText = $button.text();
      $button.data("original-text", originalText);
      $button.text($button.data("loading-text") || "Processing...");
    } else {
      $button.prop("disabled", false).removeClass("wthb-loading");
      var originalText = $button.data("original-text");
      if (originalText) {
        $button.text(originalText);
      }
    }
  };

  /**
   * Make an AJAX request.
   */
  WTHB.ajax = function (action, data, success, error) {
    data = data || {};
    data.action = action;

    if (!window.wthbData || !window.wthbData.ajaxurl) {
      if (typeof error === "function") {
        error({
          message: "Missing AJAX configuration (wthbData.ajaxurl).",
        });
      }
      return;
    }

    if (window.wthbData && window.wthbData.nonces) {
      var nonceMap = {
        wthb_signup: "signup",
        wthb_save_token: "token",
        wthb_trigger_heartbeat: "heartbeat",
        wthb_check_connection: "connection",
        wthb_unlink: "unlink",
        wthb_save_settings: "settings",
        wthb_get_site_status: "siteStatus",
      };

      var nonceKey = nonceMap[action];
      if (nonceKey && window.wthbData.nonces[nonceKey]) {
        data._ajax_nonce = window.wthbData.nonces[nonceKey];
      }
    }

    $.post(window.wthbData.ajaxurl, data)
      .done(function (response) {
        if (response.success && typeof success === "function") {
          success(response.data);
        } else if (!response.success && typeof error === "function") {
          error(response.data);
        }
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        if (typeof error === "function") {
          error({
            message: "Request failed: " + textStatus,
          });
        }
      });
  };
})(jQuery);
