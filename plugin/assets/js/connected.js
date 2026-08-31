/**
 * Connected screen JavaScript - handles settings, unlink, and periodic heartbeats.
 */

(function ($) {
  "use strict";

  var heartbeatTimer = null;
  var quickActions = {
    containerSelector: "#wthb-site-status-container",

    init: function () {
      if (!$(this.containerSelector).length) {
        return;
      }

      this.fetchSiteStatus();
    },

    fetchSiteStatus: function () {
      var self = this;

      WTHB.ajax(
        "wthb_get_site_status",
        {},
        function (data) {
          self.renderSiteStatus(data);
        },
        function () {
          self.renderError();
        },
      );
    },

    renderSiteStatus: function (data) {
      var $container = $(this.containerSelector);
      if (!$container.length) return;

      if (!data || !data.ok) {
        this.renderError();
        return;
      }

      var status = data.siteStatus || "unknown";
      var downRate = Math.round(parseFloat(data.downRate || 0) * 100);
      var upRate = Math.round(parseFloat(data.upRate || 0) * 100);
      var domain = data.domain || "Not available";
      var domainRegistrar = data.domainRegistrar || "Unknown";
      var remainingDays =
        typeof data.remainingDays === "number" ? data.remainingDays : null;
      var issuer = data.issuer || "Unknown";
      var daysLeft = typeof data.daysLeft === "number" ? data.daysLeft : null;
      var chainTrusted =
        typeof data.chainTrusted === "boolean" ? data.chainTrusted : null;

      var statusClass = "wthb-status-unknown";
      var statusText = "Unknown";
      var statusIcon = "●";

      if (status === "up") {
        statusClass = "wthb-status-up";
        statusText = "Up & Running";
        statusIcon = "✓";
      } else if (status === "down") {
        statusClass = "wthb-status-down";
        statusText = "Down";
        statusIcon = "✕";
      } else if (status === "paused") {
        statusClass = "wthb-status-paused";
        statusText = "Paused";
        statusIcon = "⏸";
      }

      var html = '<div class="wthb-status-widget">';
      html += '<div class="wthb-status-item ' + statusClass + '">';
      html += '<span class="wthb-status-icon">' + statusIcon + "</span>";
      html += '<div class="wthb-status-content">';
      html += '<div class="wthb-status-label">Status</div>';
      html += '<div class="wthb-status-value">' + statusText + "</div>";
      html += "</div>";
      html += "</div>";

      html += '<div class="wthb-status-metrics">';
      html += '<div class="wthb-metric-item wthb-metric-up">';
      html += '<div class="wthb-metric-label">Uptime</div>';
      html += '<div class="wthb-metric-value">' + upRate + "%</div>";
      html += "</div>";
      html += '<div class="wthb-metric-item wthb-metric-down">';
      html += '<div class="wthb-metric-label">Downtime</div>';
      html += '<div class="wthb-metric-value">' + downRate + "%</div>";
      html += "</div>";
      html += "</div>";

      html += '<div class="wthb-detail-panels">';
      html += '<div class="wthb-detail-panel">';
      html += '<div class="wthb-detail-title">Domain</div>';
      html += '<div class="wthb-detail-row">';
      html += '<span class="wthb-detail-label">Domain</span>';
      html += '<span class="wthb-detail-value">' + domain + "</span>";
      html += "</div>";
      html += '<div class="wthb-detail-row">';
      html += '<span class="wthb-detail-label">Registrar</span>';
      html += '<span class="wthb-detail-value">' + domainRegistrar + "</span>";
      html += "</div>";
      html += '<div class="wthb-detail-row">';
      html += '<span class="wthb-detail-label">Remaining</span>';
      html += this.renderBadgeValue(this.getDaysBadge(remainingDays));
      html += "</div>";
      html += "</div>";

      html += '<div class="wthb-detail-panel">';
      html += '<div class="wthb-detail-title">SSL</div>';
      html += '<div class="wthb-detail-row">';
      html += '<span class="wthb-detail-label">Issuer</span>';
      html += '<span class="wthb-detail-value">' + issuer + "</span>";
      html += "</div>";
      html += '<div class="wthb-detail-row">';
      html += '<span class="wthb-detail-label">Expires In</span>';
      html += this.renderBadgeValue(this.getDaysBadge(daysLeft));
      html += "</div>";
      html += '<div class="wthb-detail-row">';
      html += '<span class="wthb-detail-label">Chain Trust</span>';
      html += this.renderBadgeValue(this.getTrustBadge(chainTrusted));
      html += "</div>";
      html += "</div>";
      html += "</div>";
      html += "</div>";

      $container.html(html);
    },

    formatDays: function (value, unit) {
      if (value === null) {
        return "Not available";
      }

      return value + " " + unit;
    },

    formatBoolean: function (value) {
      if (value === null) {
        return "Not available";
      }

      return value ? "Trusted" : "Not trusted";
    },

    getDaysBadge: function (value) {
      if (value === null) {
        return {
          text: "Not available",
          tone: "neutral",
        };
      }

      if (value <= 7) {
        return {
          text: this.formatDays(value, "days"),
          tone: "danger",
        };
      }

      if (value <= 30) {
        return {
          text: this.formatDays(value, "days"),
          tone: "warning",
        };
      }

      return {
        text: this.formatDays(value, "days"),
        tone: "success",
      };
    },

    getTrustBadge: function (value) {
      if (value === null) {
        return {
          text: "Not available",
          tone: "neutral",
        };
      }

      return {
        text: this.formatBoolean(value),
        tone: value ? "success" : "danger",
      };
    },

    renderBadgeValue: function (badge) {
      return (
        '<span class="wthb-detail-value"><span class="wthb-badge wthb-badge-' +
        badge.tone +
        '">' +
        badge.text +
        "</span></span>"
      );
    },

    renderError: function () {
      var $container = $(this.containerSelector);
      if (!$container.length) return;

      $container.html(
        '<p class="wthb-status-error">' + "Failed to load status" + "</p>",
      );
    },
  };

  $(document).ready(function () {
    var $saveSettingsBtn = $("#wthb-save-settings");
    var $sendHeartbeatBtn = $("#wthb-send-heartbeat-btn");
    var $unlinkBtn = $("#wthb-unlink-btn");
    var $intervalInput = $("#wthb-interval");
    var $pauseInput = $("#wthb-pause");

    $saveSettingsBtn.on("click", function (e) {
      e.preventDefault();

      var intervalSec = parseInt($intervalInput.val(), 10);
      var pause = $pauseInput.is(":checked");

      if (isNaN(intervalSec) || intervalSec < 60 || intervalSec > 3600) {
        WTHB.showMessage(
          "Interval must be between 60 and 3600 seconds.",
          "error",
        );
        return;
      }

      WTHB.clearMessage();
      WTHB.setButtonLoading($saveSettingsBtn, true);

      WTHB.ajax(
        "wthb_save_settings",
        {
          interval_sec: intervalSec,
          pause: pause ? 1 : 0,
        },
        function (data) {
          WTHB.setButtonLoading($saveSettingsBtn, false);
          WTHB.showMessage("Settings saved successfully.", "success");

          if (window.wthbConnected) {
            window.wthbConnected.intervalSec = data.interval_sec;
            window.wthbConnected.pause = data.pause;
          }

          // NOTE: Disabled. In WT-triggered mode, heartbeats are triggered externally.
          // restartHeartbeatLoop();
        },
        function (data) {
          WTHB.setButtonLoading($saveSettingsBtn, false);
          var message =
            data && data.message ? data.message : "Failed to save settings.";
          WTHB.showMessage(message, "error");
        },
      );
    });

    // "Send Heartbeat Now": islek + nonce bastan beri vardi, buton yoktu.
    // Tek gercek `manual=true` ureticisi bu tiklama -- cron ve WT tetigi
    // artik manual tasimiyor (PayloadBuilder, 2.1.2).
    $sendHeartbeatBtn.on("click", function (e) {
      e.preventDefault();

      WTHB.clearMessage();
      WTHB.setButtonLoading($sendHeartbeatBtn, true);

      WTHB.ajax(
        "wthb_trigger_heartbeat",
        {},
        function (data) {
          WTHB.setButtonLoading($sendHeartbeatBtn, false);
          WTHB.showMessage("Heartbeat sent successfully.", "success");
          quickActions.init();
        },
        function (data) {
          WTHB.setButtonLoading($sendHeartbeatBtn, false);
          var message =
            data && data.message ? data.message : "Failed to send heartbeat.";
          WTHB.showMessage(message, "error");
        },
      );
    });

    $unlinkBtn.on("click", function (e) {
      e.preventDefault();

      var confirmHtml =
        '<div class="wthb-confirm-dialog">' +
        "<h3>Unlink Site from Watchman Tower?</h3>" +
        "<p>This will stop monitoring and remove the connection between your WordPress site and Watchman Tower.</p>" +
        "<p><strong>You can reconnect at any time using your register token.</strong></p>" +
        '<div class="wthb-confirm-actions">' +
        '<button type="button" class="button button-large" id="wthb-confirm-cancel">Cancel</button>' +
        '<button type="button" class="button button-primary button-large" id="wthb-confirm-unlink">Yes, Unlink Site</button>' +
        "</div>" +
        "</div>";

      WTHB.showMessage(confirmHtml, "warning");

      $("#wthb-confirm-cancel").on("click", function () {
        WTHB.clearMessage();
      });

      $("#wthb-confirm-unlink").on("click", function () {
        WTHB.clearMessage();
        WTHB.setButtonLoading($unlinkBtn, true);

        WTHB.ajax(
          "wthb_unlink",
          {},
          function (data) {
            WTHB.setButtonLoading($unlinkBtn, false);

            // Show success message
            var messageType = data.server ? "success" : "warning";
            var displayMessage = data.message || "Site unlinked successfully.";

            // If there's a warning, append it
            if (data.warning) {
              displayMessage +=
                "<br><br><strong>Warning:</strong> " + data.warning;
            }

            WTHB.showMessage(displayMessage, messageType);

            stopHeartbeatLoop();

            // Redirect to disconnected screen
            setTimeout(function () {
              window.location.href =
                window.wthbData.settingsUrl + "&mode=disconnected";
            }, 2500);
          },
          function (data) {
            WTHB.setButtonLoading($unlinkBtn, false);
            var message =
              data && data.message ? data.message : "Failed to unlink site.";
            WTHB.showMessage(message, "error");
          },
        );
      });
    });

    // NOTE: Disabled. In WT-triggered mode, heartbeats are triggered externally.
    // startHeartbeatLoop();

    quickActions.init();
  });

  /**
   * Start periodic heartbeat loop.
   */
  function startHeartbeatLoop() {
    stopHeartbeatLoop();

    if (!window.wthbConnected) {
      return;
    }

    var intervalMs = (window.wthbConnected.intervalSec || 300) * 1000;

    function sendHeartbeat() {
      if (window.wthbConnected && window.wthbConnected.pause) {
        scheduleNext();
        return;
      }

      WTHB.ajax(
        "wthb_trigger_heartbeat",
        {},
        function (data) {
          scheduleNext();
        },
        function (data) {
          scheduleNext();
        },
      );
    }

    function scheduleNext() {
      var currentInterval = window.wthbConnected
        ? (window.wthbConnected.intervalSec || 300) * 1000
        : intervalMs;
      heartbeatTimer = setTimeout(sendHeartbeat, currentInterval);
    }

    scheduleNext();
  }

  /**
   * Stop periodic heartbeat loop.
   */
  function stopHeartbeatLoop() {
    if (heartbeatTimer) {
      clearTimeout(heartbeatTimer);
      heartbeatTimer = null;
    }
  }

  /**
   * Restart heartbeat loop with new settings.
   */
  function restartHeartbeatLoop() {
    stopHeartbeatLoop();
    startHeartbeatLoop();
  }
})(jQuery);
