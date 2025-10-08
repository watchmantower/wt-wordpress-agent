# Watchman Tower WordPress Agent

Lightweight WordPress agent plugin for **Watchman Tower**.  
Sends periodic **heartbeat** and **site health** data (WP/PHP versions, theme, plugins, DB/REST/cron checks, security bits) to your Watchman Tower project.

> Repo: `wt-wordpress-agent` · License: MIT

---

## ✨ Features
- Heartbeat with site health summary (DB, WP-REST, cron overdue, updates count)
- Theme & limited active plugins inventory (sample)
- XML-RPC / registration flags
- Configurable interval (+ server-side override)
- Pause / Send now
- Works without front-end visitors (scheduler, no admin-page trigger)

---

## 🚀 Install
1. **Download** the ZIP from GitHub Releases (or your manifest link).
2. WordPress admin → **Plugins → Add New → Upload Plugin** → upload the ZIP and **Activate**.
3. Go to **Settings → Watchman Tower**.
4. Paste the **Integration Token (JWT)** you got from Watchman Tower **Install** flow.
5. Click **Save** then **Send now** to test.

> Tip: The token is one-time/short-lived for setup. You can generate a fresh one anytime from WT UI.

---

## ⚙️ Settings
- **Integration Token (JWT)** – required
- **Interval** – default 10 min (server can suggest a different interval)
- **Pause** – stops sending while on

Advanced:
- **Instance ID** – auto-generated UUID (used to identify this WP instance)
- **Last success / Last error** – quick diagnostics

---

## 📡 What is sent (payload)
- **identity**: `instanceId`, `pluginVersion`, `sentAt`
- **site**: `homeUrl`, `siteUrl`, `multisite`
- **wordpress**: `wpVersion`, `phpVersion`, `theme{name,version}`, `comments{enabled}`
- **health**: `db{ok}`, `rest{ok}`, `cron{overdue,nextDueInSec}`, `updates{core,plugins,themes}`
- **security**: `xmlrpc{enabled}`, `registration{enabled}`
- **inventory**: `activePlugins{count,sample[]}`

---

## 🧪 Troubleshooting
- **No heartbeat / “Waiting…”**  
  - Check token and endpoint; try **Send now**.
- **REST check fails**  
  - Some security plugins block `/wp-json/`. It’s fine; you’ll just see `rest.ok=false`.
- **Cron overdue**  
  - If host blocks WP-Cron, set a real cron:  
    `*/10 * * * * php /path/to/wp/wp-cron.php > /dev/null 2>&1`
- **XML-RPC disabled**  
  - Many hosts/plugins disable it intentionally (expected).

---

## 🔗 Links
- 🌐 [Watchman Tower Website](https://www.watchmantower.com)
- 🧩 [Dashboard / App](https://app.watchmantower.com)
- 📨 [Support](mailto:support@watchmantower.com)
- 🧠 [GitHub Repository](https://github.com/watchmantower/wt-wordpress-agent)