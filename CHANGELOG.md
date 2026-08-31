# Changelog

Full changelog is also maintained in `plugin/readme.txt` for WordPress.org.

## 2.1.2
### Security
- Removed authentication debug details from unauthorized heartbeat responses.
- Removed the built-in fallback signing key; requests are now rejected outright when no site secret is configured.
### Fixed
- REST health metric now reflects an actual measurement instead of a hardcoded value.
- Manual-send flag corrected so scheduled heartbeats are no longer reported as manual.
### Added
- "Send Heartbeat Now" button on the Connected screen (was documented but missing from the UI).
- Confirmed compatibility with WordPress 7.1.

## 2.1.1
### Added
- Domain validation during registration to block tokens from being connected to the wrong WordPress site.
- Setup guidance on the account creation and existing token screens.
### Changed
- Improved connection error handling so domain mismatch responses are shown clearly in the plugin UI.

## 2.1.0
### Added
- Domain monitoring details in the Quick Actions status widget.
- SSL monitoring details in the Quick Actions status widget.
- Visual severity badges for domain expiry, SSL expiry, and certificate trust state.
### Changed
- Refactored the Connected screen Quick Actions area into a dedicated component.
- Improved Connected screen status rendering with cleaner client-side structure.

## 2.0.0
- Major release with complete UI overhaul and enhanced monitoring features.
- Account creation directly from WordPress settings.
- Step-by-step guided connection flow.
- Enhanced heartbeat system with real-time status updates.
- Manual "Send Heartbeat Now" functionality.
- One-click site unlinking.
- Improved security with enhanced token management.

## 1.0.1
- Added custom header metadata to all outgoing API requests for improved request tracking.
- Minor code cleanup and stability improvements.

## 1.0.0
- Initial release: lightweight heartbeat system, settings page, secure authentication, scheduled metric sending.

---

## Legacy (pre-rewrite `watchmantower-agent`)

## 0.1.0 — 2025-08-16
- Initial heartbeat sender, DB/REST/cron checks, instanceId init.
- Admin settings with token, interval, pause, send now.
