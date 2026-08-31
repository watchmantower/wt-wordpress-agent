# Watchman Tower — WordPress Plugin

Official WordPress plugin for **[Watchman Tower](https://www.watchmantower.com)** — centralized WordPress monitoring for agencies and multi-site owners. Track uptime, performance, SSL, domain expiry, and WordPress health signals across all your client sites from a single dashboard.

> Plugin slug: `watchman-tower` · Current version: **2.1.2** · License: GPLv2 or later

---

## 📦 Installation

The recommended way to install is from the WordPress.org plugin directory:

- **[Watchman Tower on WordPress.org](https://wordpress.org/plugins/watchman-tower/)**

Or manually:

1. Download the latest release ZIP.
2. WordPress admin → **Plugins → Add New → Upload Plugin** → upload and **Activate**.
3. Go to **Settings → Watchman Tower**.
4. Either **create a new account** directly from the plugin (email only, password optional), or **paste an integration token** from an existing Watchman Tower account.
5. Heartbeat and performance metrics begin automatically.

You can manage up to **10 monitored sites for free**.

---

## ✨ Features

**Core monitoring**
- 24/7 uptime monitoring with response time tracking
- Latency breakdown (DNS, TLS, TTFB)
- SSL certificate and domain expiry alerts
- Multi-region probe checks

**WordPress-specific monitoring**
- Core / plugin / theme update visibility
- PHP version and active plugin list reporting
- Cron health checks
- Security posture visibility (XML-RPC, open registration)

**Integration**
- Account creation directly from plugin settings
- Secure token-based communication (HTTPS + Bearer)
- Lightweight, cache-compatible, CDN-safe heartbeat system
- Manual "Send Heartbeat Now" action and one-click unlink

---

## 🗂 Repository layout

```
plugin/     → Plugin source (mirrors WordPress.org SVN trunk)
```

The `plugin/` directory is what ships to users. Releases are published through the WordPress.org SVN repository (`https://plugins.svn.wordpress.org/watchman-tower`); this GitHub repository is the source of truth for development.

### Release flow

1. Develop and bump the version in `plugin/watchman-tower.php` and `plugin/readme.txt` (header + `Stable tag` + changelog).
2. Commit and push here.
3. Copy `plugin/` contents into the SVN `trunk/`, commit, then tag the release:
   `svn copy .../trunk .../tags/<version> -m "Release version <version>"`

---

## 📡 External services

The plugin communicates with the Watchman Tower platform:

| Endpoint | Purpose |
|---|---|
| `api.watchmantower.com/api/auth/wp/create-account` | Account creation from WordPress |
| `metric.watchmantower.com/wp/heartbeat` | Periodic heartbeat / performance signals |
| `metric.watchmantower.com/wp/status` | Connection status |
| `metric.watchmantower.com/wp/unlink` | Disconnecting the site |

See [Privacy Policy](https://www.watchmantower.com/privacy-policy) and [Terms of Service](https://www.watchmantower.com/terms-of-service).

---

## 🔗 Links

- 🌐 [Watchman Tower Website](https://www.watchmantower.com)
- 🧩 [Dashboard / App](https://app.watchmantower.com)
- 📚 [Documentation](https://docs.watchmantower.com/wordpress-agent/installation)
- 📨 [Support](mailto:support@watchmantower.com)
