=== AI Traffic Guardian ===
Contributors: aitrafficguardian
Tags: ai bots, bot protection, ai crawlers, woocommerce, analytics
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Layered AI & bot traffic control: verified-bot classification, vendor×purpose policy engine, analytics integrity, accessible form & checkout protection, shadow mode, and a full visual dashboard.

== Description ==

AI crawlers, scrapers and fraud bots now make up a large share of WordPress traffic. AI Traffic Guardian gives you **visibility first, control second** — so you never block real customers while you tune your policies.

= How it works =

1. **Shadow mode by default.** For the first 7 days the plugin only *observes*: every request is classified and logged, nothing is blocked. The dashboard shows exactly what would have happened.
2. **Business-critical traffic is untouchable.** Payment webhooks (/wc-api/, WooCommerce REST), WP-Cron, WP-CLI, authenticated REST/AJAX and logged-in users bypass classification entirely.
3. **Verified identity, not just user agents.** Googlebot & co. are confirmed with reverse+forward DNS. OpenAI / Perplexity bots are matched against their published IP ranges (auto-refreshed weekly). Claims that fail verification are treated as spoofing; identities that can't be verified are throttled and logged — never silently trusted.
4. **Granular AI policy matrix.** Vendor × purpose × action: allow OpenAI search, block OpenAI training, throttle SEO tools — your call, per bot. One-click presets for publishers, WooCommerce stores, and private sites.
5. **Human-intent proxies.** Agentic AI fetchers (ChatGPT-User, Claude-User, Perplexity-User) get their own category so real users' AI assistants are not treated as scrapers.
6. **Analytics integrity.** GTM-safe compatibility mode tags bot sessions with a custom parameter; conditional mode strips GA4/GTM for flagged bots; an optional server-side bridge sends WooCommerce purchases straight to GA4.
7. **Accessible form protection.** Honeypot fields hidden from assistive tech, no mouse-movement gates, timing checks off by default, keyboard-only users fully supported. WooCommerce velocity ladder stops card-testing attacks.
8. **robots.txt without conflicts.** Rules are appended through the WordPress filter — Yoast, Rank Math, SEOPress and AIOSEO keep working.
9. **New-bot alerts.** When an unrecognized AI crawler shows up, you get an alert with its full user agent.
10. **Panic button.** One click disables all blocking instantly.

= Dashboard =

* Bot share, blocked/throttled counts, human-equivalent traffic
* Stacked traffic timeline and category doughnut (Chart.js, bundled — no external CDN)
* Top AI vendors, latest decisions, filterable traffic log with CSV export
* Live policy matrix, allowlist manager, alerts inbox, environment report

= Privacy =

* IPs in logs are salted-hashed by default (GDPR-friendly)
* Configurable retention (7–365 days, automatic pruning)
* Human traffic is aggregated only unless you opt into row-level logging
* No data leaves your site except explicit IP-range fetches (OpenAI, Perplexity) and the optional GA4 server-side bridge

= Honest limitations =

* Ghost traffic sent directly to GA4's Measurement Protocol never touches WordPress and cannot be intercepted by any plugin.
* Sophisticated agentic traffic inside a real browser can be indistinguishable from a human. This is risk reduction, not elimination.
* For raw CPU savings at scale, pair this plugin with an edge layer (Cloudflare or host caching) — a PHP plugin sees requests only after WordPress boots.

== Installation ==

1. Upload the `ai-traffic-guardian` folder to `/wp-content/plugins/` (or install the ZIP via Plugins → Add New → Upload).
2. Activate the plugin.
3. Open **Traffic Guardian → Dashboard** in wp-admin.
4. Leave shadow mode running for a few days, review the log, then click **Go live: start enforcing**.

== Frequently Asked Questions ==

= Will it block my customers? =
Logged-in users always pass. Business-critical endpoints (payment webhooks, WooCommerce REST, WP-Cron) can never be blocked. Rate limiting is session-first and IP-second, so shared office/university/mobile networks are safe. Shadow mode lets you verify all of this before enforcing anything.

= Does it work with Yoast / Rank Math? =
Yes. robots.txt rules are appended via the core `robots_txt` filter; your SEO plugin keeps full control.

= Does it work with page caching (WP Rocket, LiteSpeed)? =
Blocked/throttled responses are sent with `no-store` and are never 200s, so page caches ignore them by design.

= Multisite? =
Yes — per-site tables and policies. Network activation provisions up to 100 sites automatically, and new sites are provisioned on creation.

== Changelog ==

= 1.0.0 =
* Initial release.
